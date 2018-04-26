<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package dhimport_version2
 * @author Remote-Learner.net Inc
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2017 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace dhimport_version2\entity;
use \local_datahub\dblogger;
use \local_datahub\fslogger;

/**
 * Course import class.
 */
class course extends base {
    /** @var array Cached list of themes on the site. Used for field validation. */
    protected $themes = [];

    /**
     * Get the available fields for a given action.
     *
     * @param string $action The action we want fields for, or null for general list.
     * @return array An array of valid field names.
     */
    public function get_available_fields($action = null) {
        return [
                'courseaction',
                'shortname',
                'fullname',
                'idnumber',
                'summary',
                'format',
                'numsections',
                'startdate',
                'newsitems',
                'showgrades',
                'showreports',
                'maxbytes',
                'guest',
                'password',
                'visible',
                'lang',
                'category',
                'link',
                'theme'
        ];
    }

    /**
     * Get a list of required fields for a given action.
     *
     * @param string $action The action we want fields for.
     * @return array|null An array of required field names, or null if not available for that action.
     */
    public function get_required_fields($action = null) {
        switch ($action) {
            case 'add': // 1.9 BC.
            case 'create':
                return ['shortname', 'fullname', 'category'];
            case 'update':
            case 'disable': // 1.9 BC.
            case 'delete':
                return ['shortname'];
            default:
                return null;
        }
    }

    /**
     * Get a list of supported actions for this entity.
     *
     * @return array An array of support action names.
     */
    public function get_supported_actions() {
        return ['create', 'add', 'update', 'delete', 'disable'];
    }

    /**
     * Process a single record.
     *
     * @param \stdClass $record The record to process.
     * @param int $linenumber The line number of this record from the import file. Used for logging.
     * @return bool Success/Failure.
     */
    public function process_record(\stdClass $record, $linenumber) {
        $this->linenumber = $linenumber;

        $record = $this->remove_empty_fields($record);
        $record = $this->apply_mapping($record);

        if (empty($record->courseaction)) {
            $message = "Required field \"courseaction\" is unspecified or empty.";
            $this->fslogger->log_failure($message, 0, $this->filename, $this->linenumber, $record, 'user');
            return false;
        }

        // Apply "createorupdate" flag, if necessary.
        if (in_array($record->courseaction, ['create', 'add', 'update'], true)) {
            $record->courseaction = $this->handle_createorupdate($record, $record->courseaction);
        }

        if (!$this->check_required_fields($record->courseaction, $record)) {
            // Missing a required field.
            return false;
        }

        switch ($record->courseaction) {
            case 'add': // 1.9 BC.
            case 'create':
                return $this->course_create($record);
            case 'update':
                return $this->course_update($record);
            case 'disable': // 1.9 BC.
            case 'delete':
                return $this->course_delete($record);
            default:
                // Invalid action for this entity type.
                $message = "Action of \"{$record->courseaction}\" is not supported.";
                $this->fslogger->log_failure($message, 0, $this->filename, $this->linenumber, $record, 'course');
                return false;
        }
    }

    /**
     * Performs any necessary conversion of the action value based on the
     * "createorupdate" setting
     *
     * @param object $record One record of import data
     * @param string $action The supplied action
     * @return string The action to use in the import
     */
    protected function handle_createorupdate($record, $action) {
        global $DB;

        // Check config setting.
        $createorupdate = get_config('dhimport_version2', 'createorupdate');

        if (!empty($createorupdate)) {
            if (isset($record->shortname) && $record->shortname !== '') {
                // Identify the course.
                if ($DB->record_exists('course', ['shortname' => $record->shortname])) {
                    // Course shortname exists, so the action is an update.
                    $action = 'update';
                } else {
                    if (isset($record->idnumber) && $record->idnumber !== '' &&
                            $DB->count_records('course', ['idnumber' => $record->idnumber]) == 1) {
                        // Course idnumber exists, so the action is an update.
                        $action = 'update';
                    } else {
                        // Course does not exist, so the action is a create.
                        $action = 'create';
                    }
                }
            } else {
                $action = 'create';
            }
        }

        return $action;
    }

    /**
     * Check the lengths of fields from a course record
     *
     * @param object $record The course record
     * @return boolean True if field lengths are ok, otherwise false
     */
    protected function check_course_field_lengths($record) {
        $lengths = ['fullname' => 254, 'shortname' => 100, 'idnumber' => 100];
        return $this->check_field_lengths('course', $record, $lengths);
    }

    /**
     * Intelligently splits a category specification into a list of categories
     *
     * @param string $categorystring   The category specification string, using
     *                                 \\\\ to represent \, \\/ to represent /,
     *                                 and / as a category separator
     * @return array An array with one entry per category, containing the
     *               unescaped category names
     */
    protected static function get_category_path($categorystring) {
        // In-progress method result.
        $result = [];

        // Used to build up the current token before splitting.
        $currenttoken = '';

        // Tracks which token we are currently looking at.
        $currenttokennum = 0;
        $max = strlen($categorystring);
        for ($i = 0; $i < $max; $i++) {
            // Initialize the entry if necessary.
            if (!isset($result[$currenttokennum])) {
                $result[$currenttokennum] = '';
            }

            // Get the i-th character from the category string.
            $currenttoken .= substr($categorystring, $i, 1);

            if (strpos($currenttoken, '\\\\') === strlen($currenttoken) - strlen('\\\\')) {
                // Backslash character.
                // Append the result.
                $result[$currenttokennum] .= substr($currenttoken, 0, strlen($currenttoken) - strlen('\\\\')).'\\';
                // Reset the token.
                $currenttoken = '';
            } else if (strpos($currenttoken, '\\/') === strlen($currenttoken) - strlen('\\/')) {
                // Forward slash character.
                // Append the result.
                $result[$currenttokennum] .= substr($currenttoken, 0, strlen($currenttoken) - strlen('\\/')).'/';
                // Reset the token so that the / is not accidentally counted as a category separator.
                $currenttoken = '';
            } else if (strpos($currenttoken, '/') === strlen($currenttoken) - strlen('/')) {
                // Category separator.
                // Append the result.
                $result[$currenttokennum] .= substr($currenttoken, 0, strlen($currenttoken) - strlen('/'));
                // Reset the token.
                $currenttoken = '';
                // Move on to the next token.
                $currenttokennum++;
            }
        }

        // Append leftovers after the last slash.
        // Initialize the entry if necessary.
        if (!isset($result[$currenttokennum])) {
            $result[$currenttokennum] = '';
        }
        $result[$currenttokennum] .= $currenttoken;

        return $result;
    }

    /**
     * Map the specified category to a record id
     *
     * @param \stdClass $record
     * @return mixed Returns false on error, or the integer category id otherwise
     */
    protected function get_category_id($record) {
        global $DB;

        $categorystring = $record->category;
        $parentids = [];

        // Check for a leading / for the case where an absolute path is specified.
        if (strpos($categorystring, '/') === 0) {
            $categorystring = substr($categorystring, 1);
            $parentids[] = 0;
        }

        // Split the category string into a list of categories.
        $path = static::get_category_path($categorystring);
        foreach ($path as $categoryname) {
            // Look for categories with the correct name.
            $select = "name = ?";
            $params = [$categoryname];

            if (!empty($parentids)) {
                // Only allow categories that also are children of categories;
                // Found in the last iteration of the specified path.
                list($parentselect, $parentparams) = $DB->get_in_or_equal($parentids);
                $select = "{$select} AND parent {$parentselect}";
                $params = array_merge($params, $parentparams);
            }

            // Find matching records.
            if ($records = $DB->get_recordset_select('course_categories', $select, $params)) {
                if (!$records->valid()) {
                    // None found, so try see if the id was specified.
                    if (is_numeric($categorystring)) {
                        if ($DB->record_exists('course_categories', array('id' => $categorystring))) {
                            return $categorystring;
                        }
                    }

                    $parent = 0;
                    if (count($parentids) == 1) {
                        // We have a specific parent to create a child for.
                        $parent = $parentids[0];
                    } else if (count($parentids) > 0) {
                        // Ambiguous parent, so we can't continue.
                        $identifier = $this->mappings['category'];
                        $this->fslogger->log_failure("{$identifier} value of \"{$categorystring}\" ".
                                "refers to an ambiguous parent category path.", 0, $this->filename, $this->linenumber, $record, 'course');
                        return false;
                    }

                    // Create a new category.
                    $newcategory = new \stdClass;
                    $newcategory->name = $categoryname;
                    $newcategory->parent = $parent;
                    $newcategory->id = $DB->insert_record('course_categories', $newcategory);

                    // Set "parent ids" to the new category id.
                    $parentids = [$newcategory->id];
                } else {
                    // Set "parent ids" to the current result set for our next iteration.
                    $parentids = [];
                    foreach ($records as $childrecord) {
                        $parentids[] = $childrecord->id;
                    }
                }
            }
        }

        if (count($parentids) == 1) {
            // Found our category.
            return $parentids[0];
        } else {
            // Path refers to multiple potential categories.
            $identifier = $this->mappings['category'];
            $this->fslogger->log_failure("{$identifier} value of \"{$categorystring}\" refers to ".
                    "multiple categories.", 0, $this->filename, $this->linenumber, $record, 'course');
            return false;
        }
    }

    /**
     * Validates that core course fields are set to valid values, if they are set
     * on the import record
     *
     * @param string $action One of 'create' or 'update'
     * @param object $record The import record
     *
     * @return boolean true if the record validates correctly, otherwise false
     */
    protected function validate_core_data($action, $record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/enrollib.php');

        // Make sure theme can only be set if feature is enabled.
        if (isset($record->theme)) {
            if (empty($CFG->allowcoursethemes)) {
                $this->fslogger->log_failure("Course themes are currently disabled on this site.", 0,
                        $this->filename, $this->linenumber, $record, "course");
                return false;
            }
        }

        // Make sure theme refers to a valid theme.
        if (empty($this->themes)) {
            // Lazy-loading of themes, store to save time.
            $this->themes = get_list_of_themes();
        }

        if (!$this->validate_fixed_list($record, 'theme', array_keys($this->themes))) {
            $identifier = $this->mappings['theme'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->theme}\" is not a valid theme.",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Make sure format refers to a valid course format.
        if (isset($record->format)) {
            $courseformats = \core_component::get_plugin_list('format');
            if (!$this->validate_fixed_list($record, 'format', array_keys($courseformats))) {
                $identifier = $this->mappings['format'];
                $this->fslogger->log_failure("{$identifier} value of \"{$record->format}\" does not refer to a valid course format.",
                        0, $this->filename, $this->linenumber, $record, "course");
                return false;
            }
        }

        // Make sure numsections is an integer between 0 and the configured max.
        if (isset($record->numsections)) {
            $maxsections = (int)get_config('moodlecourse', 'maxsections');
            if ((int)$record->numsections != $record->numsections) {
                // Not an integer.
                return false;
            }

            $record->numsections = (int)$record->numsections;
            if ($record->numsections < 0 || $record->numsections > $maxsections) {
                $identifier = $this->mappings['numsections'];
                $this->fslogger->log_failure("{$identifier} value of \"{$record->numsections}\" is not one of the available options".
                        " (0 .. {$maxsections}).", 0, $this->filename, $this->linenumber, $record, "course");
                // Not between 0 and max.
                return false;
            }
        }

        // Make sure startdate is a valid date.
        if (isset($record->startdate)) {
            $value = $this->parse_date($record->startdate);
            if ($value === false) {
                $identifier = $this->mappings['startdate'];
                $this->fslogger->log_failure("{$identifier} value of \"{$record->startdate}\" is not a ".
                        "valid date in MMM/DD/YYYY or MM/DD/YYYY format.", 0, $this->filename, $this->linenumber, $record, "course");
                return false;
            }

            // Use the unix timestamp.
            $record->startdate = $value;
        }

        // Make sure newsitems is an integer between 0 and 10.
        $options = range(0, 10);
        if (!$this->validate_fixed_list($record, 'newsitems', $options)) {
            $identifier = $this->mappings['newsitems'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->newsitems}\" is not one of the available options".
                    " (0 .. 10).", 0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Make sure showgrades is one of the available values.
        if (!$this->validate_fixed_list($record, 'showgrades', [0, 1], ['no' => 0, 'yes' => 1])) {
            $identifier = $this->mappings['showgrades'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->showgrades}\" is not one of the available options (0, 1).",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Make sure showreports is one of the available values.
        if (!$this->validate_fixed_list($record, 'showreports', [0, 1], ['no' => 0, 'yes' => 1])) {
            $identifier = $this->mappings['showreports'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->showreports}\" is not one of the available options (0, 1).",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Make sure maxbytes is one of the available values.
        if (isset($record->maxbytes)) {
            $choices = get_max_upload_sizes($CFG->maxbytes);
            if (!$this->validate_fixed_list($record, 'maxbytes', array_keys($choices))) {
                $identifier = $this->mappings['maxbytes'];
                $this->fslogger->log_failure("{$identifier} value of \"{$record->maxbytes}\" is not one of the available options.",
                        0, $this->filename, $this->linenumber, $record, "course");
                return false;
            }
        }

        // Make sure guest is one of the available values.
        if (!$this->validate_fixed_list($record, 'guest', [0, 1], ['no' => 0, 'yes' => 1])) {
            $identifier = $this->mappings['guest'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->guest}\" is not one of the available options (0, 1).",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Make sure visible is one of the available values.
        if (!$this->validate_fixed_list($record, 'visible', [0, 1], ['no' => 0, 'yes' => 1])) {
            $identifier = $this->mappings['visible'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->visible}\" is not one of the available options (0, 1).",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Make sure lang refers to a valid language or the default value.
        $languages = get_string_manager()->get_list_of_translations();
        $languagecodes = array_merge([''], array_keys($languages));
        if (!$this->validate_fixed_list($record, 'lang', $languagecodes)) {
            $identifier = $this->mappings['lang'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->lang}\" is not a valid language code.",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        // Determine if this plugin is even enabled.
        $enabled = explode(',', $CFG->enrol_plugins_enabled);
        if (!in_array('guest', $enabled) && !empty($record->guest)) {
            $this->fslogger->log_failure("guest enrolments cannot be enabled because the guest enrolment plugin is globally disabled.",
                    0, $this->filename, $this->linenumber, $record, "course");
            return false;
        }

        if ($action == 'create') {
            // Make sure "guest" settings are consistent for new course.
            if (isset($record->guest) && empty($record->guest) && !empty($record->password)) {
                // Password set but guest is not enabled.
                $this->fslogger->log_failure('guest enrolment plugin cannot be assigned a password '.
                        ' because the guest enrolment plugin is not enabled.',
                        0, $this->filename, $this->linenumber, $record, 'course');
                return false;
            }

            $defaultenrol = get_config('enrol_guest', 'defaultenrol');
            if (empty($defaultenrol) && !empty($record->guest)) {
                // Enabling guest access without the guest plugin being added by default.
                $this->fslogger->log_failure('guest enrolment plugin cannot be assigned a password '.
                        'because the guest enrolment plugin is not configured '.
                        'to be added to new courses by default.',
                        0, $this->filename, $this->linenumber, $record, 'course');
                return false;
            } else if (empty($defaultenrol) && !empty($record->password)) {
                // Enabling guest password without the guest plugin being added by default.
                $this->fslogger->log_failure('guest enrolment plugin cannot be assigned a password '.
                        'because the guest enrolment plugin is not configured to '.
                        'be added to new courses by default.', 0, $this->filename, $this->linenumber, $record, 'course');
                return false;
            }

            // Make sure we don't have a course "link" (template) that refers to an invalid course shortname.
            if (isset($record->link)) {
                if (!$DB->record_exists('course', ['shortname' => $record->link])) {
                    $this->fslogger->log_failure("Template course with shortname \"{$record->link}\" ".
                            "could not be found.", 0, $this->filename, $this->linenumber, $record, 'course');
                    return false;
                }
            }
        }

        if ($action == 'update') {
            // Todo: consider moving into course_update function.
            // Make sure "guest" settings are consistent for new course.

            // Determine whether the guest enrolment plugin is added to the current course.
            $guestpluginexists = false;
            if ($courseid = $DB->get_field('course', 'id', ['shortname' => $record->shortname])) {
                if ($DB->record_exists('enrol', ['courseid' => $courseid, 'enrol' => 'guest'])) {
                    $guestpluginexists = true;
                }
            }

            if (!$guestpluginexists) {
                // Guest enrolment plugin specifically removed from course.
                if (isset($record->guest)) {
                    $this->fslogger->log_failure("guest enrolment plugin cannot be enabled because ".
                            "the guest enrolment plugin has been removed from course \"{$record->shortname}\".",
                            0, $this->filename, $this->linenumber, $record, 'course');
                    return false;
                } else if (isset($record->password)) {
                    $this->fslogger->log_failure("guest enrolment plugin cannot be assigned a password ".
                            "because the guest enrolment plugin has been removed from course \"{$record->shortname}\".",
                            0, $this->filename, $this->linenumber, $record, 'course');
                    return false;
                }
            }

            if (!empty($record->password)) {
                // Make sure a password can only be set if guest access is enabled.
                if ($courseid = $DB->get_field('course', 'id', ['shortname' => $record->shortname])) {

                    if (isset($record->guest) && empty($record->guest)) {
                        // Guest access specifically disabled, which isn't consistent with providing a password.
                        $this->fslogger->log_failure("guest enrolment plugin cannot be assigned a ".
                                "password because the guest enrolment plugin has been disabled in course \"{$record->shortname}\".",
                                0, $this->filename, $this->linenumber, $record, 'course');
                        return false;
                    } else if (!isset($record->guest)) {
                        $params = ['courseid' => $courseid, 'enrol' => 'guest', 'status' => ENROL_INSTANCE_ENABLED];
                        if (!$DB->record_exists('enrol', $params)) {
                            // Guest access disabled in the database.
                            $this->fslogger->log_failure("guest enrolment plugin cannot be assigned a ".
                                    "password because the guest enrolment plugin has been ".
                                    "disabled in course \"{$record->shortname}\".",
                                    0, $this->filename, $this->linenumber, $record, 'course');
                            return false;
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * Create a course
     *
     * @param object $record One record of import data
     * @return boolean true on success, otherwise false
     */
    public function course_create($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/lib.php');

        // Remove invalid fields.
        $record = $this->remove_invalid_fields('create', $record);

        // Field length checking.
        $lengthcheck = $this->check_course_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // Data checking.
        if (!$this->validate_core_data('create', $record)) {
            return false;
        }

        // Validate and set up the category.
        $categoryid = $this->get_category_id($record);
        if ($categoryid === false) {
            return false;
        }

        $record->category = $categoryid;
        // Uniqueness check.
        if ($DB->record_exists('course', ['shortname' => $record->shortname])) {
            $identifier = $this->mappings['shortname'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->shortname}\" refers to a ".
                    "course that already exists.", 0, $this->filename, $this->linenumber, $record, 'course');
            return false;
        }

        // ID number uniqueness check.
        if (isset($record->idnumber) && $record->idnumber !== '' && $DB->record_exists('course', ['idnumber' => $record->idnumber])) {
            $identifier = $this->mappings['idnumber'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->idnumber}\" already exists ".
                    "in an existing course.", 0, $this->filename, $this->linenumber, $record, 'course');
            return false;
        }

        // Final data sanitization.
        if (isset($record->guest)) {
            if ($record->guest == 0) {
                $record->enrol_guest_status_0 = ENROL_INSTANCE_DISABLED;
            } else {
                $record->enrol_guest_status_0 = ENROL_INSTANCE_ENABLED;
                $record->enrol_guest_password_0 = isset($record->password) ? $record->password : null;
            }
        }

        // Write to the database.
        if (isset($record->link)) {
            // Creating from template.
            require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');
            require_once(\elis::lib('rollover/lib.php'));
            $courseid = $DB->get_field('course', 'id', ['shortname' => $record->link]);
            $oldstartdate = $DB->get_field('course', 'startdate', array('shortname' => $record->link));

            // Perform the content rollover.
            $record->id = course_rollover($courseid);
            // Update appropriate fields, such as shortname.
            // Todo: validate if this fully works with guest enrolments?
            update_course($record);
            if (isset($record->startdate)) {
                $offset = $record->startdate - $oldstartdate;
                // Update course completion.
                $coursecompletions = $DB->get_records("course_completion_criteria", array('course' => $record->id));
                foreach ($coursecompletions as $coursecomp) {
                    $te = $coursecomp->timeend;
                    if ($te > 0) {
                        $te = $te + $offset;
                        $coursecomp->timeend = $te;
                        $DB->update_record('course_completion_criteria', $coursecomp);
                    }
                }
                $badgelist = $DB->get_records("badge", array('courseid' => $courseid));
                foreach ($badgelist as $badge) {
                    $badgeexpire = $badge->expiredate;
                    if ($badgeexpire > 0) {
                        $badgeexpire = $badgeexpire + $offset;
                        $badge->expiredate = $badgeexpire;
                        $DB->update_record('badge', $badge);
                    }
                }
                // Update scorms.
                $scormitems = $DB->get_records('scorm', array('course' => $record->id));
                foreach ($scormitems as $scorms) {
                    $timeopen = $scorms->timeopen;
                    $timeclose = $scorms->timeclose;
                    $rechang = '0';
                    if ($timeopen > '0') {
                        $timeopen = $timeopen + $offset;
                        $scorms->timeopen = $timeopen;
                        $rechang = '1';
                    }
                    if ($timeclose > '0') {
                        $timeclose = $timeclose + $offset;
                        $scorms->timeclose = $timeclose;
                        $rechang = '1';
                    }
                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('scorm', $scorms);
                    }
                }

                // Update quizzes.
                $quizitems = $DB->get_records('quiz', array('course' => $record->id));
                foreach ($quizitems as $quiz) {
                    $timeopen = $quiz->timeopen;
                    $timeclose = $quiz->timeclose;
                    $rechang = '0';
                    if ($timeopen > '0') {
                        $timeopen = $timeopen + $offset;
                        $quiz->timeopen = $timeopen;
                        $rechang = '1';
                    }
                    if ($timeclose > '0') {
                        $timeclose = $timeclose + $offset;
                        $quiz->timeclose = $timeclose;
                        $rechang = '1';
                    }
                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('quiz', $quiz);
                    }
                }

                // Update lessons.
                $lessonitems = $DB->get_records('lesson', array('course' => $record->id));
                foreach ($lessonitems as $lesson) {
                    $avail = $lesson->available;
                    $close = $lesson->deadline;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $lesson->available = $avail;
                        $rechang = '1';
                    }
                    if ($close > '0') {
                        $close = $close + $offset;
                        $lesson->deadline = $close;
                        $rechang = '1';
                    }
                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('lesson', $lesson);
                    }
                }
                // Update Choice.
                $choiceitems = $DB->get_records('choice', array('course' => $record->id));
                foreach ($choiceitems as $choice) {
                    $avail = $choice->timeopen;
                    $close = $choice->timeclose;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $choice->timeopen = $avail;
                        $rechang = '1';
                    }
                    if ($close > '0') {
                        $close = $close + $offset;
                        $choice->timeclose = $close;
                        $rechang = '1';
                    }
                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('choice', $choice);
                    }
                }
                // Update database.
                $dataitems = $DB->get_records('data', array('course' => $record->id));
                foreach ($dataitems as $data) {
                    $avail = $data->timeavailablefrom;
                    $availto = $data->timeavailableto;
                    $timefrom = $data->timeviewfrom;
                    $timeto = $data->timeviewto;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $data->timeavailablefrom = $avail;
                        $rechang = '1';
                    }
                    if ($availto > '0') {
                        $availto = $availto + $offset;
                        $data->timeavailableto = $availto;
                        $rechang = '1';
                    }
                    if ($timefrom > '0') {
                        $timefrom = $timefrom + $offset;
                        $data->timeviewfrom = $timefrom;
                        $rechang = '1';
                    }
                    if ($timeto > '0') {
                        $timeto = $timeto + $offset;
                        $data->timeviewto = $timeto;
                        $rechang = '1';
                    }

                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('data', $data);
                    }
                }
                // Update Workshop.
                $workshopitems = $DB->get_records('workshop', array('course' => $record->id));
                foreach ($workshopitems as $workshop) {
                    $avail = $workshop->submissionstart;
                    $availto = $workshop->submissionend;
                    $timefrom = $workshop->assessmentstart;
                    $timeto = $workshop->assessmentend;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $workshop->submissionstart = $avail;
                        $rechang = '1';
                    }
                    if ($availto > '0') {
                        $availto = $availto + $offset;
                        $workshop->submissionend = $availto;
                        $rechang = '1';
                    }
                    if ($timefrom > '0') {
                        $timefrom = $timefrom + $offset;
                        $workshop->assessmentstart = $timefrom;
                        $rechang = '1';
                    }
                    if ($timeto > '0') {
                        $timeto = $timeto + $offset;
                        $workshop->assessmentend = $timeto;
                        $rechang = '1';
                    }

                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('workshop', $workshop);
                    }
                }
                // Update assignment.
                $assignmentitems = $DB->get_records('assign', array('course' => $record->id));
                foreach ($assignmentitems as $assign) {
                    $avail = $assign->duedate;
                    $availto = $assign->allowsubmissionsfromdate;
                    $timefrom = $assign->cutoffdate;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $assign->duedate = $avail;
                        $rechang = '1';
                    }
                    if ($availto > '0') {
                        $availto = $availto + $offset;
                        $assign->allowsubmissionsfromdate = $availto;
                        $rechang = '1';
                    }
                    if ($timefrom > '0') {
                        $timefrom = $timefrom + $offset;
                        $assign->cutoffdate = $timefrom;
                        $rechang = '1';
                    }

                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('assign', $assign);
                    }
                }
                // Update forums ratings.
                $forumitems = $DB->get_records('forum', array('course' => $record->id));
                foreach ($forumitems as $forum) {
                    $avail = $forum->assesstimestart;
                    $availto = $forum->assesstimefinish;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $forum->assesstimestart = $avail;
                        $rechang = '1';
                    }
                    if ($availto > '0') {
                        $availto = $availto + $offset;
                        $forum->assesstimefinish = $availto;
                        $rechang = '1';
                    }

                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('forum', $forum);
                    }
                }

                // Update questionaire open / close dates.
                $questionitems = $DB->get_records('questionnaire', array('course' => $record->id));
                foreach ($questionitems as $questions) {
                    $avail = $questions->opendate;
                    $availto = $questions->closedate;
                    $rechang = '0';
                    if ($avail > '0') {
                        $avail = $avail + $offset;
                        $questions->opendate = $avail;
                        $rechang = '1';
                    }
                    if ($availto > '0') {
                        $availto = $availto + $offset;
                        $questions->closedate = $availto;
                        $rechang = '1';
                    }

                    // Only update record if there has been a change.
                    if ($rechang == '1') {
                        $DB->update_record('questionnaire', $questions);
                    }
                }

                // Update availability conditions.
                \availability_date\condition::update_all_dates($record->id, $offset);
            }
            // Log success.
            $this->fslogger->log_success("Course with shortname \"{$record->shortname}\" successfully created ".
                    " from template course with shortname \"{$record->link}\".", 0, $this->filename, $this->linenumber);
        } else {
            // Creating directly (not from template).

            // Check that any unset fields are set to course default.
            $courseconfig = get_config('moodlecourse');

            // Set up an array with all the course fields that have defaults.
            $coursedefaults = ['format', 'numsections', 'hiddensections', 'newsitems', 'showgrades',
                    'showreports', 'maxbytes', 'groupmode', 'groupmodeforce', 'visible', 'lang'];
            foreach ($coursedefaults as $coursedefault) {
                if (!isset($record->$coursedefault) && isset($courseconfig->$coursedefault)) {
                    $record->$coursedefault = $courseconfig->$coursedefault;
                }
            }

            create_course($record);

            // Log success.
            $this->fslogger->log_success("Course with shortname \"{$record->shortname}\" successfully created.",
                    0, $this->filename, $this->linenumber);
        }

        if (!$this->fslogger->get_logfile_status()) {
            return false;
        }
        return true;
    }

    /**
     * Update a course
     *
     * @param object $record One record of import data
     * @return boolean true on success, otherwise false
     */
    public function course_update($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/lib/enrollib.php');

        // Remove invalid fields.
        $record = $this->remove_invalid_fields('update', $record);

        // Field length checking.
        $lengthcheck = $this->check_course_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // Data checking.
        if (!$this->validate_core_data('update', $record)) {
            return false;
        }

        // Validate and set up the category.
        if (isset($record->category)) {
            $categoryid = $this->get_category_id($record);
            if ($categoryid === false) {
                return false;
            }

            $record->category = $categoryid;
        }

        try {
            $record->id = $DB->get_field('course', 'id', ['shortname' => $record->shortname], MUST_EXIST);
        } catch (\dml_multiple_records_exception $dmle1) {
            $identifier = $this->mappings['shortname'];
            $this->fslogger->log_failure("{$identifier} value of \"{$record->shortname}\" refers to multiple courses - check DB!",
                    0, $this->filename, $this->linenumber, $record, 'course');
            return false;
        } catch (\dml_missing_record_exception $dmle2) {
            $record->id = false;
        }
        if (empty($record->id)) {
            $numrecs = 0;
            if (isset($record->idnumber) && $record->idnumber !== '' &&
                    ($numrecs = $DB->count_records('course', ['idnumber' => $record->idnumber])) == 1) {
                $record->id = $DB->get_field('course', 'id', ['idnumber' => $record->idnumber]);
            } else {
                if ($numrecs) {
                    $msg = 'refers to multiple courses - check DB!';
                    $identifier = $this->mappings['idnumber'];
                    $val = $record->idnumber;
                } else {
                    $msg = 'does not refer to a valid course.';
                    $identifier = $this->mappings['shortname'];
                    $val = $record->shortname;
                }
                $this->fslogger->log_failure("{$identifier} value of \"{$val}\" {$msg}",
                        0, $this->filename, $this->linenumber, $record, 'course');
                return false;
            }
        } else {
            if (isset($record->idnumber) && $record->idnumber !== '' && $DB->record_exists('course', ['idnumber' => $record->idnumber])) {
                $identifier = $this->mappings['idnumber'];
                try {
                    $checkrecordid = $DB->get_field('course', 'id', ['idnumber' => $record->idnumber], MUST_EXIST);
                } catch (\dml_multiple_records_exception $dmle) {
                    $this->fslogger->log_failure("{$identifier} value of \"{$record->idnumber}\" refers to multiple courses - check DB!",
                            0, $this->filename, $this->linenumber, $record, 'course');
                    return false;
                }
                if ($checkrecordid != $record->id) {
                    $this->fslogger->log_failure("{$identifier} value of \"{$record->idnumber}\" already exists ".
                            "in an existing course.", 0, $this->filename, $this->linenumber, $record, 'course');
                    return false;
                }
            }
        }

        update_course($record);

        // Special work for "guest" settings.
        if (isset($record->guest) && empty($record->guest)) {
            // Todo: add more error checking.
            if ($enrol = $DB->get_record('enrol', ['courseid' => $record->id, 'enrol' => 'guest'])) {
                // Disable the plugin for the current course.
                $enrol->status = ENROL_INSTANCE_DISABLED;
                $DB->update_record('enrol', $enrol);
            } else {
                // Should never get here due to validation.
                // $this->process_error("[$this->filename line $this->linenumber] \"guest\" enrolments cannot be enabled because".
                // " the guest enrolment plugin has been removed from course {$record->shortname}.");
                return false;
            }
        }

        if (!empty($record->guest)) {
            // Todo: add more error checking.
            if ($enrol = $DB->get_record('enrol', ['courseid' => $record->id, 'enrol' => 'guest'])) {
                // Enable the plugin for the current course.
                $enrol->status = ENROL_INSTANCE_ENABLED;
                if (isset($record->password)) {
                    // Password specified, so set it.
                    $enrol->password = $record->password;
                }
                $DB->update_record('enrol', $enrol);
            } else {
                // Should never get here due to validation.
                // $this->process_error("[$this->filename line $this->linenumber] guest enrolment plugin cannot be assigned a password because".
                // " the guest enrolment plugin has been removed from course {$record->shortname}.");
                return false;
            }
        }

        // Log success.
        $this->fslogger->log_success("Course with shortname \"{$record->shortname}\" successfully updated.", 0, $this->filename,
                $this->linenumber);

        if (!$this->fslogger->get_logfile_status()) {
            return false;
        }
        return true;
    }

    /**
     * Delete a course
     *
     * @param object $record One record of import data
     * @return boolean true on success, otherwise false
     */
    public function course_delete($record) {
        global $DB;

        // Field length checking.
        $lengthcheck = $this->check_course_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        if ($courseid = $DB->get_field('course', 'id', ['shortname' => $record->shortname])) {
            delete_course($courseid, false);
            fix_course_sortorder();

            // Log success.
            $this->fslogger->log_success("Course with shortname \"{$record->shortname}\" successfully deleted.",
                    0, $this->filename, $this->linenumber);

            if (!$this->fslogger->get_logfile_status()) {
                return false;
            }
            return true;
        }

        $identifier = $this->mappings['shortname'];
        $this->fslogger->log_failure("{$identifier} value of \"{$record->shortname}\" does not ".
                "refer to a valid course.", 0, $this->filename, $this->linenumber, $record, 'course');

        return false;
    }
}
