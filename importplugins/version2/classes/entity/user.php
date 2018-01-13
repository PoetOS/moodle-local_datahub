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
 * User import class.
 */
class user extends base {
    /** @var array Cached list of themes on the site. Used for field validation. */
    protected $themes = [];

    /** @var array Array of relevant custom field information. In the format [shortname => field record] */
    protected $customfields = [];

    /**
     * Get the available fields for a given action.
     *
     * @param string $action The action we want fields for, or null for general list.
     * @return array An array of valid field names.
     */
    public function get_available_fields($action = null) {
        global $DB;
        $fields = [
            'useraction',
            'username',
            'auth',
            'password',
            'firstname',
            'lastname',
            'email',
            'maildigest',
            'autosubscribe',
            'trackforums',
            'screenreader',
            'city',
            'country',
            'timezone',
            'theme',
            'lang',
            'description',
            'idnumber',
            'institution',
            'department',
            'user_idnumber',
            'user_username',
            'user_email',
            'suspended'
        ];

        // Add user profile fields.
        if ($customfields = $DB->get_records('user_info_field')) {
            foreach ($customfields as $customfield) {
                $fields[] = 'profile_field_'.$customfield->shortname;
            }
        }

        return $fields;
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
                return [
                    'username',
                    'password',
                    'firstname',
                    'lastname',
                    'email',
                    'city',
                ];
            case 'update':
            case 'disable': // 1.9 BC.
            case 'delete':
                $fields = [['user_username', 'user_email', 'user_idnumber']];
                $idfields = ['username', 'email', 'idnumber'];
                foreach ($idfields as $idfield) {
                    if (get_config('dhimport_version2', 'identfield_'.$idfield)) {
                        $fields[0][] = $idfield;
                    }
                }
                return $fields;
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

        if (empty($record->useraction)) {
            $message = "Required field \"useraction\" is unspecified or empty.";
            $this->fslogger->log_failure($message, 0, $this->filename, $this->linenumber, $record, 'user');
            return false;
        }

        // Apply "createorupdate" flag, if necessary.
        if (in_array($record->useraction, ['create', 'add', 'update'], true)) {
            $record->useraction = $this->handle_createorupdate($record, $record->useraction);
        }

        if (!$this->check_required_fields($record->useraction, $record)) {
            // Missing a required field.
            return false;
        }

        switch ($record->useraction) {
            case 'add': // 1.9 BC.
            case 'create':
                return $this->user_create($record);
            case 'update':
                return $this->user_update($record);
            case 'disable': // 1.9 BC.
            case 'delete':
                return $this->user_delete($record);
            default:
                // Invalid action for this entity type.
                $message = "Action of \"{$record->useraction}\" is not supported.";
                $this->fslogger->log_failure($message, 0, $this->filename, $this->linenumber, $record, 'user');
                return false;
        }
    }

    /**
     * Validate file headers.
     *
     * @param array $headers An array of file headers.
     * @return bool True if valid, false if not valid.
     */
    public function validate_headers(array $headers) {
        global $DB;

        if (parent::validate_headers($headers) !== true) {
            return false;
        }

        // Load custom field information.
        $this->customfields = [];
        $invalidfieldnames = [];
        $errors = false;
        foreach ($headers as $column) {
            // Determine the "real" fieldname, taking mappings into account.
            $realcolumn = $column;
            foreach ($this->mappings as $standardfieldname => $customfieldname) {
                if ($column == $customfieldname) {
                    $realcolumn = $standardfieldname;
                    break;
                }
            }

            // Attempt to fetch the field.
            if (strpos($realcolumn, 'profile_field_') === 0) {
                $shortname = substr($realcolumn, strlen('profile_field_'));
                if ($result = $DB->get_record('user_info_field', array('shortname' => $shortname))) {
                    $this->customfields[$shortname] = $result;
                } else {
                    $invalidfieldnames[] = $shortname;
                    $errors = true;
                }
            }
        }

        if ($errors === true) {
            $errstr = "Import file contains the following invalid user profile field(s): ".implode(', ', $invalidfieldnames);
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber);
            if (!$this->fslogger->get_logfile_status()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Performs any necessary conversion of the action value based on the "createorupdate" setting.
     *
     * @param object $record One record of import data
     * @param string $action The supplied action
     * @return string The action to use in the import
     */
    protected function handle_createorupdate(&$record, $action) {
        global $CFG, $DB;

        // Check config setting.
        $createorupdate = get_config('dhimport_version2', 'createorupdate');

        if (!empty($createorupdate)) {
            // Check for new user_ prefix fields that are only valid for update.
            if (isset($record->user_idnumber) || isset($record->user_username) || isset($record->user_email)) {
                return 'update';
            }

            // Determine if any identifying fields are set.
            $usernameset = get_config('dhimport_version2','identfield_username') && isset($record->username) && $record->username !== '';
            $emailset = get_config('dhimport_version2','identfield_email') && isset($record->email) && $record->email !== '';
            $idnumberset = get_config('dhimport_version2','identfield_idnumber') && isset($record->idnumber) && $record->idnumber !== '';

            // Make sure at least one identifying field is set.
            if ($usernameset || $emailset || $idnumberset) {
                // Identify the user.
                $params = array();
                if ($usernameset) {
                    $record->username = \core_text::strtolower($record->username);
                    $params['username'] = $record->username;
                    $params['mnethostid'] = $CFG->mnet_localhost_id;
                }
                if ($emailset) {
                    $params['email'] = $record->email;
                }
                if ($idnumberset) {
                    $params['idnumber'] = $record->idnumber;
                }

                if ($DB->record_exists('user', $params)) {
                    // User exists, so the action is an update.
                    $action = 'update';
                } else {
                    // User does not exist, so the action is a create.
                    $action = 'create';
                }
            } else {
                $action = 'create';
            }
        }
        if (isset($record->username)) {
            $record->username = \core_text::strtolower($record->username);
        }
        return $action;
    }

    /**
     * Create a user.
     *
     * @param object $record One record of import data
     * @return boolean true on success, otherwise false
     */
    protected function user_create($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/lib.php');
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/admin/tool/uploaduser/locallib.php');

        // Break references so we're self-contained.
        $record = clone $record;

        // Remove invalid fields.
        $record = $this->remove_invalid_fields('create', $record);

        // Field length checking.
        $lengthcheck = $this->check_user_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // Data checking.
        // If successfuly, this method will return a new $record which may contain altered data for alternative values.
        $record = $this->validate_core_data('create', $record);
        if (empty($record)) {
            // Validation failed.
            return false;
        }

        // Profile field validation.
        $record = $this->validate_custom_profile_data($record);
        if (empty($record)) {
            // Validation failed.
            return false;
        }

        // Uniqueness checks.
        $select = '(username = :username AND mnethostid = :mnethostid)';
        $params = ['username' => $record->username, 'mnethostid' => $CFG->mnet_localhost_id];

        if (isset($record->idnumber)) {
             $select .= ' OR (idnumber = :idnumber)';
             $params['idnumber'] = $record->idnumber;
        }
        $allowduplicateemails = get_config('dhimport_version2', 'allowduplicateemails');
        if (empty($allowduplicateemails)) {
            $select .= ' OR (email = :email)';
            $params['email'] = $record->email;
        }

        $matchingusers = $DB->get_records_select('user', $select, $params, '', 'username, email, idnumber');
        foreach ($matchingusers as $matchinguser) {
            // Username uniqueness.
            if ($matchinguser->username === $record->username) {
                $identifier = $this->mappings['username'];
                $errorstring = "{$identifier} value of \"{$record->username}\" refers to a user that already exists.";
                $this->fslogger->log_failure($errorstring, 0, $this->filename, $this->linenumber, $record, 'user');
                return false;
            }

            // Email uniqueness (if required).
            if (empty($allowduplicateemails) && $matchinguser->email === $record->email) {
                $identifier = $this->mappings['email'];
                $errorstring = "{$identifier} value of \"{$record->email}\" refers to a user that already exists.";
                $this->fslogger->log_failure($errorstring, 0, $this->filename, $this->linenumber, $record, 'user');
                return false;
            }

            // Idnumber uniqueness (if present).
            if (isset($record->idnumber) && $matchinguser->idnumber == $record->idnumber) {
                $identifier = $this->mappings['idnumber'];
                $errorstring = "{$identifier} value of \"{$record->idnumber}\" refers to a user that already exists.";
                $this->fslogger->log_failure($errorstring, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Final data sanitization.
        if (!isset($record->description)) {
            $record->description = '';
        }

        if (!isset($record->lang)) {
            $record->lang = $CFG->lang;
        }

        // Force password change only when 'changeme' not allowed as an actual value.
        $allowchangemepass = isset($CFG->allowchangemepass) ? $CFG->allowchangemepass : get_config('local_datahub', 'allowchangemepass');
        $requireforcepasswordchange = (!$allowchangemepass && $record->password == 'changeme') ? true : false;

        //write to the database
        $record->descriptionformat = FORMAT_HTML;
        $record->mnethostid = $CFG->mnet_localhost_id;
        $cleartextpassword = $requireforcepasswordchange ? generate_password() : $record->password;
        $record->password = hash_internal_user_password($cleartextpassword);
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;
        // Make sure the user is confirmed!
        $record->confirmed = 1;

        $suspended = null;
        if (isset($record->suspended)) {
            $suspended = $record->suspended; // This would force current user logout???
            unset($record->suspended);
        }

        $record->id = $DB->insert_record('user', $record);
        $record = uu_pre_process_custom_profile_data($record);
        profile_save_data($record);

        // Sync to PM is necessary.
        $user = $DB->get_record('user', array('id' => $record->id));
        $eventdata = [
            'objectid' => $record->id,
            'context' => \context_user::instance($record->id)
        ];
        $event = \core\event\user_created::create($eventdata);
        $event->trigger();

        if (!is_null($suspended)) {
            $DB->set_field('user', 'suspended', $suspended, ['id' => $record->id]);
        }

        // String to describe the user.
        $userdescriptor = static::get_user_descriptor($record);

        // Set force password change if required.
        if ($requireforcepasswordchange) {
            set_user_preference('auth_forcepasswordchange', true, $record->id);
        }

        // Log success.
        $this->fslogger->log_success("User with {$userdescriptor} successfully created.", 0, $this->filename, $this->linenumber);

        $user->cleartextpassword = $cleartextpassword;
        $this->newuseremail($user);

        if (!$this->fslogger->get_logfile_status()) {
            return false;
        }
        return true;
    }

    /**
     * Update a user.
     *
     * @param \stdClass $record One record of import data.
     * @return bool True on success, otherwise false.
     */
    protected function user_update($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/lib.php');
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/admin/tool/uploaduser/locallib.php');

        // Break references so we're self-contained.
        $record = clone $record;

        // Remove invalid fields.
        $record = $this->remove_invalid_fields('update', $record);

        // Field length checking.
        $lengthcheck = $this->check_user_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // Data checking.
        // If successfuly, this method will return a new $record which may contain altered data for alternative values.
        $record = $this->validate_core_data('update', $record);
        if (empty($record)) {
            // Validation failed.
            return false;
        }

        // Profile field validation.
        $record = $this->validate_custom_profile_data($record);
        if (empty($record)) {
            // Validation failed.
            return false;
        }

        // Find existing user record.
        $errors = array();
        $error = false;
        $errsuffix = '';
        $uid = $this->get_userid_for_user_actions($record, $error, $errors, $errsuffix);
        if ($error) {
            $errstr = implode($errors, ", ").$errsuffix;
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
            return false;
        }

        if ($uid) {
            $record->id = $uid;
        }

        // Force password change only when 'changeme' not allowed as an actual value.
        $allowchangemepass = isset($CFG->allowchangemepass) ? $CFG->allowchangemepass : get_config('local_datahub', 'allowchangemepass');
        $requireforcepasswordchange = (!$allowchangemepass && isset($record->password) && $record->password == 'changeme') ? true : false;

        // If forcing password change, do not actually change users existing password.
        if ($requireforcepasswordchange) {
            unset($record->password);
        }

        // Write to the database.

        // Taken from user_update_user.
        // Hash the password.
        if (isset($record->password)) {
            $record->password = hash_internal_user_password($record->password);
        }

        $suspended = null;
        if (isset($record->suspended)) {
            $suspended = $record->suspended; // This would force current user logout???
            unset($record->suspended);
        }

        $record->timemodified = time();
        $DB->update_record('user', $record);
        $record = uu_pre_process_custom_profile_data($record);
        profile_save_data($record);

        // Trigger user_updated event on the full database user row.
        $eventdata = array(
            'objectid' => $record->id,
            'context' => \context_user::instance($record->id)
        );
        $event = \core\event\user_updated::create($eventdata);
        $event->trigger();

        if (!is_null($suspended)) {
            $DB->set_field('user', 'suspended', $suspended, ['id' => $record->id]);
        }

        // String to describe the user.
        $userdescriptor = static::get_user_descriptor($record);

        // Set force password change if required.
        if ($requireforcepasswordchange) {
            set_user_preference('auth_forcepasswordchange', true, $record->id);
        }

        // Log success.
        $this->fslogger->log_success("User with {$userdescriptor} successfully updated.", 0, $this->filename, $this->linenumber);

        if (!$this->fslogger->get_logfile_status()) {
            return false;
        }
        return true;
    }

    /**
     * Delete a user.
     *
     * @param \stdClass $record One record of import data.
     * @return bool True on success, otherwise false.
     */
    protected function user_delete($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/lib.php');

        // Break references so we're self-contained.
        $record = clone $record;

        // Field length checking.
        $lengthcheck = $this->check_user_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // Find existing user record.
        $errors = array();
        $error = false;
        $errsuffix = '';
        $uid = $this->get_userid_for_user_actions($record, $error, $errors, $errsuffix);
        if ($error) {
            $errstr = implode($errors, ", ").$errsuffix;
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
            return false;
        }

        // Make the appropriate changes.
        if ($user = $DB->get_record('user', array('id' => $uid))) {
            user_delete_user($user);

            // String to describe the user.
            $userdescriptor = static::get_user_descriptor($record);

            // Log success.
            $errstr = "User with {$userdescriptor} successfully deleted.";
            $this->fslogger->log_success($errstr, 0, $this->filename, $this->linenumber);

            if (!$this->fslogger->get_logfile_status()) {
                return false;
            }
            return true;
        } else {
            // String to describe the user.
            $userdescriptor = static::get_user_descriptor($record);
            // Generic error.
            $errstr = "Error deleting user with {$userdescriptor}";
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
        }

        return false;
    }

    /**
     * Check the lengths of fields from a user record.
     *
     * @param object $record The user record
     * @return boolean True if field lengths are ok, otherwise false
     */
    protected function check_user_field_lengths($record) {
        $lengths = [
            'username' => 100,
            'firstname' => 100,
            'lastname' => 100,
            'email' => 100,
            'city' => 120,
            'idnumber' => 255,
            'institution' => 40,
            'department' => 30
        ];
        return $this->check_field_lengths('user', $record, $lengths);
    }

    /**
     * Validates that core user fields are set to valid values, if they are set on the import record.
     *
     * @param string $action One of 'create' or 'update'
     * @param \stdClass $record The import record
     * @return bool|\stdClass true if the record validates correctly, otherwise false
     */
    protected function validate_core_data($action, $record) {
        global $CFG;

        // Break references. We return validated object.
        $record = clone $record;

        // Make sure auth plugin refers to a valid plugin.
        if (isset($record->auth)) {
            $auths = \core_component::get_plugin_list('auth');
            if (in_array($record->auth, array_keys($auths), true) !== true) {
                $identifier = $this->mappings['auth'];
                $errstr = "{$identifier} value of \"{$record->auth}\" is not a valid auth plugin.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Make sure password satisfies the site password policy (but allow "changeme" which will trigger forced password change).
        if (isset($record->password)) {
            $errmsg = '';
            if ($record->password != 'changeme' && !check_password_policy($record->password, $errmsg)) {
                $identifier = $this->mappings['password'];
                $errstr = "{$identifier} value of \"{$record->password}\" does not conform to your site's password policy.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Make sure email is in user@domain.ext format.
        if ($action == 'create') {
            if (!validate_email($record->email)) {
                $identifier = $this->mappings['email'];
                $errstr = "{$identifier} value of \"{$record->email}\" is not a valid email address.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Make sure maildigest is one of the available values.
        if (isset($record->maildigest)) {
            if (in_array($record->maildigest, ['0', '1', '2'], true) !== true) {
                $identifier = $this->mappings['maildigest'];
                $errstr = "{$identifier} value of \"{$record->maildigest}\" is not one of the available options (0, 1, 2).";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Make sure autosubscribe is one of the available values,
        if (!$this->validate_fixed_list($record, 'autosubscribe', ['0', '1'], ['no' => '0', 'yes' => '1'])) {
            $identifier = $this->mappings['autosubscribe'];
            $errstr = "{$identifier} value of \"{$record->autosubscribe}\" is not one of the available options (0, 1).";
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
            return false;
        }

        // Make sure trackforums can only be set if feature is enabled.
        if (isset($record->trackforums)) {
            if (empty($CFG->forum_trackreadposts)) {
                $errstr = "Tracking unread posts is currently disabled on this site.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }

            // Make sure trackforums is one of the available values.
            if (!$this->validate_fixed_list($record, 'trackforums', ['0', '1'], ['no' => '0', 'yes' => '1'])) {
                $identifier = $this->mappings['trackforums'];
                $errstr = "{$identifier} value of \"{$record->trackforums}\" is not one of the available options (0, 1).";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Make sure screenreader is one of the available values.
        if (!$this->validate_fixed_list($record, 'screenreader', ['0', '1'], ['no' => '0', 'yes' => '1'])) {
            $identifier = $this->mappings['screenreader'];
            $errstr = "{$identifier} value of \"{$record->screenreader}\" is not one of the available options (0, 1).";
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
            return false;
        }

        // Make sure country refers to a valid country code.
        if (isset($record->country)) {
            $countries = get_string_manager()->get_list_of_countries();
            if (!$this->validate_fixed_list($record, 'country', array_keys($countries), array_flip($countries))) {
                $identifier = $this->mappings['country'];
                $errstr = "{$identifier} value of \"{$record->country}\" is not a valid country or country code.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Validate timezone.
        if (isset($record->timezone)) {
            // Make sure timezone can only be set if feature is enabled.
            if ($CFG->forcetimezone != 99 && $record->timezone != $CFG->forcetimezone) {
                $identifier = $this->mappings['timezone'];
                $errstr = "{$identifier} value of \"{$record->timezone}\" is not consistent with forced timezone value of \"{$CFG->forcetimezone}\" on your site.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }

            // Make sure timezone refers to a valid timezone offset.
            $timezones = \core_date::get_list_of_timezones();
            if (!$this->validate_fixed_list($record, 'timezone', array_keys($timezones))) {
                $identifier = $this->mappings['timezone'];
                $errstr = "{$identifier} value of \"{$record->timezone}\" is not a valid timezone.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Validate theme.
        if (isset($record->theme)) {
            // Make sure theme can only be set if feature is enabled.
            if (empty($CFG->allowuserthemes)) {
                $errstr = "User themes are currently disabled on this site.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }

            // Make sure theme refers to a valid theme.
            if (empty($this->themes)) {
                // Lazy-loading of themes, store to save time.
                $this->themes = get_list_of_themes();
            }

            if (in_array($record->theme, array_keys($this->themes), true) !== true) {
                $identifier = $this->mappings['theme'];
                $errstr = "{$identifier} value of \"{$record->theme}\" is not a valid theme.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Validate language.
        if (isset($record->lang)) {
            $languages = get_string_manager()->get_list_of_translations();
            if (in_array($record->lang, array_keys($languages), true) !== true) {
                $identifier = $this->mappings['lang'];
                $errstr = "{$identifier} value of \"{$record->lang}\" is not a valid language code.";
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                return false;
            }
        }

        // Make sure binary 'suspended' field is valid.
        if (!$this->validate_fixed_list($record, 'suspended', ['0', '1'], ['no' => '0', 'yes' => '1'])) {
            $identifier = $this->mappings['suspended'];
            $errstr = "{$identifier} value of \"{$record->suspended}\" is not one of the available options (0, 1).";
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
            return false;
        }

        return $record;
    }

    /**
     * Validates user profile field data. If successful, a cleaned object is returned.
     *
     * @param \stdClass $record The import record.
     * @return bool If valid, returns a cleaned object. False if irrecoverably invalid.
     */
    protected function validate_custom_profile_data($record) {
        global $CFG, $DB, $USER;

        // Break references, we return validated object.
        $record = clone $record;

        foreach ($this->customfields as $shortname => $field) {
            $key = 'profile_field_'.$shortname;
            if (!isset($record->$key)) {
                continue;
            }

            $data = $record->$key;

            //perform type-specific validation and transformation
            if ($field->datatype == 'checkbox') {
                if ($data != 0 && $data != 1) {
                    $errstr = "\"{$data}\" is not one of the available options for a checkbox profile field {$shortname} (0, 1).";
                    $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                    return false;
                }
            } else if ($field->datatype == 'menu') {
                // ELIS-8306: Must support multi-lang options
                require_once($CFG->dirroot.'/user/profile/field/menu/field.class.php');
                $menufield = new \profile_field_menu($field->id, $USER->id);
                if ($menufield->convert_external_data($data) === null) {
                    $errstr = "\"{$data}\" is not one of the available options for a menu of choices profile field {$shortname}.";
                    $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                    return false;
                }
            } else if ($field->datatype == 'datetime') {
                $value = $this->parse_date($data);
                if ($value === false) {
                    $identifier = $this->mappings["profile_field_{$shortname}"];
                    $errstr = "{$identifier} value of \"{$data}\" is not a valid date in MMM/DD/YYYY or MM/DD/YYYY format.";
                    $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, "user");
                    return false;
                }

                $record->$key = $value;
            }
        }

        return $record;
    }

    /**
     * If enabled, sends a new user email notification to a user.
     *
     * @param \stdClass $user The user to send the email to.
     * @return bool Success/Failure.
     */
    protected function newuseremail($user) {
        $enabled = get_config('dhimport_version2', 'newuseremailenabled');
        if (empty($enabled)) {
            // Emails disabled.
            return false;
        }

        $template = get_config('dhimport_version2', 'newuseremailtemplate');
        if (empty($template)) {
            // No text set.
            return false;
        }

        if (empty($user->email)) {
            // User has no email.
            return false;
        }

        $subject = get_config('dhimport_version2', 'newuseremailsubject');
        if (empty($subject) || !is_string($subject)) {
            $subject = '';
        }
        $from = get_admin();
        $body = $this->newuseremail_generate($template, $user);
        return $this->sendemail($user, $from, $subject, $body);
    }

    /**
     * Generates the new user email text.
     *
     * @param string $templatetext The template email text.
     * @param \stdClass $user The user object to generate the email for.
     * @return string The final email text.
     */
    protected function newuseremail_generate($templatetext, $user) {
        global $SITE, $CFG;
        require_once($CFG->dirroot.'/local/datahub/lib.php');

        $placeholders = array(
            '%%sitename%%' => $SITE->fullname,
            '%%loginlink%%' => get_login_url(),
            '%%username%%' => (isset($user->username)) ? $user->username : '',
            '%%idnumber%%' => (isset($user->idnumber)) ? $user->idnumber : '',
            '%%password%%' => (isset($user->cleartextpassword)) ? $user->cleartextpassword : '',
            '%%firstname%%' => (isset($user->firstname)) ? $user->firstname : '',
            '%%lastname%%' => (isset($user->lastname)) ? $user->lastname : '',
            '%%fullname%%' => datahub_fullname($user),
            '%%email%%' => (isset($user->email)) ? $user->email : '',
        );
        return str_replace(array_keys($placeholders), array_values($placeholders), $templatetext);
    }

    /**
     * Determine userid from user import record
     *
     * @param \stdClass $record One record of import data
     * @param bool $error Returned errors status, true means error, false ok
     * @param array $errors Array of error strings (if $error == true)
     * @param string $errsuffix returned error suffix string
     * @return int|bool userid on success, false is not found
     */
    protected function get_userid_for_user_actions(&$record, &$error, &$errors, &$errsuffix) {
        global $CFG, $DB;
        $idfields = array('idnumber', 'username', 'email');
        $uniquefields = array('idnumber' => 0, 'username' => 0);
        if (!get_config('dhimport_version2','allowduplicateemails')) {
            $uniquefields['email'] = 0;
        }

        // First check for new user_ identifying fields.
        $params = array();
        $uid = $this->get_userid_from_record_no_logging($record, $params, 'user_');
        $usingstdident = !isset($record->user_idnumber) && !isset($record->user_username) && !isset($record->user_email);
        $params = array();
        $identfields = array();
        foreach ($idfields as $idfield) {
            if (isset($record->$idfield)) {
                $testparams = array($idfield => $record->$idfield);
                if ($idfield == 'username') {
                    $testparams['mnethostid'] = $CFG->mnet_localhost_id;
                }
                // Moodle bug: get_field will return first record found.
                $fid = false;
                $numrecs = $DB->count_records('user', $testparams);
                if ($numrecs > 1) {
                    $fid = -1; // Set to non-zero value that won't match a valid user.
                } else if ($numrecs == 1) {
                    $fid = $DB->get_field('user', 'id', $testparams);
                }
                if (isset($uniquefields[$idfield])) {
                    $uniquefields[$idfield] = $fid;
                }
                if ($usingstdident && get_config('dhimport_version2','identfield_'.$idfield)) {
                    $identifier = $this->mappings[$idfield];
                    $params[$idfield] = $record->$idfield;
                    $identstr = "$identifier value of \"{$record->$idfield}\"";
                    if (!$fid) {
                        $errors[] = $identstr;
                        $error = true;
                    } else {
                        $identfields[] = $identstr;
                        if ($idfield == 'username') {
                            $params['mnethostid'] = $CFG->mnet_localhost_id;
                        }
                    }
                }
            }
        }
        if ($usingstdident && !$error && !empty($params)) {
            try {
                $uid = $DB->get_field('user', 'id', $params);
            } catch (Exception $e) {
                $error = true;
            }
        }

        $errsuffixsingular = ' does not refer to a valid user.';
        $errsuffixplural = ' do not refer to a valid user.';
        if (!$error && !$uid) {
            $error = true;
            // Error: could not find user with specified identifying fields or multiple user matches found.
            if (empty($errors)) {
                foreach ($idfields as $idfield) {
                    if (isset($record->{'user_'.$idfield})) {
                        $errors[] = "user_{$idfield} value of \"".$record->{'user_'.$idfield}.'"';
                    }
                }
            }
            if (empty($errors)) {
                $errors = $identfields;
                $errsuffixplural = ' refer to different users.';
            }
        }

        if ($uid && !$error) {
            $errsuffixsingular = ' refers to another user - field must be unique.';
            $errsuffixplural = ' refer to other user(s) - fields must be unique.';
            foreach ($uniquefields as $key => $fielduid) {
                if ($fielduid && $uid != $fielduid) {
                    // Error: user already exists with unique $key field setting.
                    $error = true;
                    $identifier = $this->mappings[$key];
                    $errors[] = "$identifier set to \"{$record->$key}\"";
                }
            }
            if ($error) {
                $uid = false;
            }
        }

        $errsuffix = (count($errors) == 1) ? $errsuffixsingular : $errsuffixplural;
        return $uid;
    }
}

