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
 * Base class for entity import classes.
 */
abstract class base implements entityinterface {
    /** @var string The name of the file being imported. */
    protected $filename;

    /** @var dblogger A DB logger class for logging progress. */
    protected $dblogger;

    /** @var fslogger An FS logger class for logging progress. */
    protected $fslogger;

    /** @var int The line number of the current record. */
    protected $linenumber = 0;

    /** @var array Array of mappings. */
    protected $mappings = [];

    /**
     * Constructor.
     *
     * @param string $filename The name of the file being imported.
     * @param dblogger $dblogger A DB logger class for logging progress.
     * @param fslogger $fslogger An FS logger class for logging progress.
     */
    public function __construct($filename, dblogger $dblogger = null, fslogger $fslogger = null) {
        $this->filename = $filename;
        $this->dblogger = $dblogger;
        $this->fslogger = $fslogger;
    }

    /**
     * Get the available fields for a given action.
     *
     * @param string $action The action we want fields for, or null for general list.
     * @return array|null An array of valid field names, or null if not available for that action.
     */
    abstract public function get_available_fields($action = null);

    /**
     * Get a list of required fields for a given action.
     *
     * @param string $action The action we want fields for.
     * @return array|null An array of required field names, or null if not available for that action.
     */
    abstract public function get_required_fields($action = null);

    /**
     * Get a list of supported actions for this entity.
     *
     * @return array An array of support action names.
     */
    abstract public function get_supported_actions();

    /**
     * Process a single record.
     *
     * @param \stdClass $record The record to process.
     * @param int $linenumber The line number of this record from the import file. Used for logging.
     * @return bool Success/Failure.
     */
    abstract public function process_record(\stdClass $record, $linenumber);

    /**
     * Validate file headers.
     *
     * @param array $headers An array of file headers.
     * @return bool True if valid, false if not valid.
     */
    public function validate_headers(array $headers) {
        $this->mappings = $this->get_mappings();
        $entity = $this->get_entity_name();
        if (!in_array($entity.'action', $headers, true)) {
            return false;
        }
        return true;
    }

    /**
     * Get the name of the entity for this class. i.e. "user"
     *
     * @return string The entity name.
     */
    public function get_entity_name() {
        $class = get_called_class();
        return substr($class, strrpos($class, '\\')+1);
    }

    /**
     * Return any configured field maps.
     *
     * @return array Array of field maps in the form [{standard field name} => {custom field name}]
     */
    public function get_mappings() {
        global $DB;
        $file = \core_component::get_plugin_directory('dhimport', 'version2').'/lib.php';
        require_once($file);
        return rlipimport_version2_get_mapping($this->get_entity_name());
    }

    /**
     * Apply the configured field mapping to a single record.
     *
     * @param string $entity The type of entity.
     * @param \stdClass $record One record of import data.
     * @return \stdClass The record, with the field mapping applied
     */
    protected function apply_mapping($record) {
        $record = clone $record;
        foreach ($this->mappings as $standardfieldname => $customfieldname) {
            if ($standardfieldname != $customfieldname) {
                if (isset($record->$customfieldname)) {
                    // Do the conversion.
                    $record->$standardfieldname = $record->$customfieldname;
                    unset($record->$customfieldname);
                } else if (isset($record->$standardfieldname)) {
                    // Remove the standard field because it should have been provided as a mapped value.
                    unset($record->$standardfieldname);
                }
            }
        }
        return $record;
    }

    /**
     * Log a failure.
     *
     * @param string $msg The message to log.
     * @param \stdClass $record The current record.
     * @param int $time The timestamp to use, or 0 for current time.
     */
    protected function log_failure($msg, $record, $time = 0) {
        $entityname = $this->get_entity_name();
        $this->fslogger->log_failure($msg, $time, $this->filename, $this->linenumber, $record, $entityname);
    }

    /**
     * Check the lengths of fields based on the supplied maximum lengths
     *
     * @param string $entitytype The entity type, as expected by the logger
     * @param object $record The import record
     * @param array $lengths Mapping of fields to max lengths
     */
    protected function check_field_lengths($entitytype, $record, $lengths) {
        foreach ($lengths as $field => $length) {
            // Note: do not worry about missing fields here.
            if (isset($record->$field)) {
                $value = $record->$field;
                if (strlen($value) > $length) {
                    $identifier = $this->mappings[$field];
                    $errstr = "{$identifier} value of \"{$value}\" exceeds the maximum field length of {$length}.";
                    $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, $entitytype);
                    return false;
                }
            }
        }

        // No problems found.
        return true;
    }

    /**
     * Obtains a list of required fields that are missing from the supplied import record (helper method).
     *
     * @param object $record One import record.
     * @param array $requiredfields The required fields, with sub-arrays used
     *                              in "1-of-n required" scenarios.
     * @return array An array, in the same format as $requiredfields.
     */
    protected function get_missing_required_fields($record, $requiredfields) {
        $result = array();

        if (empty($requiredfields)) {
            return false;
        }

        foreach ($requiredfields as $fieldorgroup) {
            if (is_array($fieldorgroup)) {
                // The "1-of-n" secnario.
                $group = $fieldorgroup;

                // Determine if one or more values in the group is set.
                $found = false;
                foreach ($group as $key => $value) {
                    if (isset($record->$value) && $record->$value != '') {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    // Not found, so include this group as missing and required.
                    $result[] = $group;
                }
            } else {
                // Simple scenario.
                $field = $fieldorgroup;
                if (!isset($record->$field) || $record->$field === '') {
                    // Not found, so include this field as missing an required.
                    $result[] = $field;
                }
            }
        }

        if (count($result) == 0) {
            return false;
        }

        return $result;
    }

    /**
     * Perform any necessary transformation on required fields for display purposes.
     *
     * @param mixed $fieldorgroup A single field name string, or an array of them.
     * @return mixed the field or array of fields to display.
     */
    protected function get_required_field_display($fieldorgroup) {
        if (is_array($fieldorgroup)) {
            $result = array();
            foreach ($fieldorgroup as $field) {
                $result[] = $this->mappings[$field];
            }
            return $result;
        } else {
            return $this->mappings[$fieldorgroup];
        }
    }

    /**
     * Validates whether all required fields are set, logging to the filesystem where not.
     *
     * @param string $action The action to check fields for.
     * @param object $record One data import record.
     * @param array $exceptions A mapping from a field to a key, value pair that
     *                          allows that missing field to be ignored - does
     *                          not work for "1-of-n" setups.
     * @return bool True if fields ok, otherwise false.
     */
    protected function check_required_fields($action, $record, $exceptions = array()) {
        $entity = $this->get_entity_name();

        // Get list of required fields.
        $requiredfields = $this->get_required_fields($action);

        // Figure out which are missing.
        $missingfields = $this->get_missing_required_fields($record, $requiredfields);

        $messages = array();

        if ($missingfields !== false) {
            // Missing one or more fields.

            // Process "1-of-n" type fields first.
            foreach ($missingfields as $key => $value) {
                if (count($value) > 1) {
                    // Use helper to do any display-related field name transformation.
                    $displayvalue = $this->get_required_field_display($value);
                    $fields = implode(', ', $displayvalue);

                    $messages[] = "One of {$fields} is required but all are unspecified or empty.";
                    // Remove so we don't re-process.
                    unset($missingfields[$key]);
                }
            }

            // Handle absolutely required fields.
            if (count($missingfields) == 1) {
                $append = true;

                $field = reset($missingfields);

                if (isset($exceptions[$field])) {
                    // Determine the dependency key and value.
                    $dependency = $exceptions[$field];
                    $arraykeys = array_keys($dependency);
                    $key = reset($arraykeys);
                    $value = reset($arraykeys);

                    if (isset($record->$key) && $record->$key == $value) {
                        // Dependency applies, so no error.
                        $append = false;
                    }
                }

                if ($append) {
                    // Use helper to do any display-related field name transformation.
                    $fielddisplay = $this->get_required_field_display($field);
                    $messages[] = "Required field {$fielddisplay} is unspecified or empty.";
                }
            } else if (count($missingfields) > 1) {
                // Use helper to do any display-related field name transformation.
                $missingfieldsdisplay = $this->get_required_field_display($missingfields);
                $fields = implode(', ', $missingfieldsdisplay);
                $messages[] = "Required fields {$fields} are unspecified or empty.";
            }

            if (count($messages) > 0) {
                // Combine and log.
                $message = implode(' ', $messages);
                $this->fslogger->log_failure($message, 0, $this->filename, $this->linenumber, $record, $entity);
                return false;
            }
        }

        return true;
    }

    /**
     * Remove invalid fields from a record.
     *
     * @param string $action The action we're performing.
     * @param \stdClass $record The record.
     * @return \stdClass The record with the invalid fields removed.
     */
    protected function remove_invalid_fields($action, $record) {
        return (object)array_intersect_key((array)$record, array_flip($this->get_available_fields($action)));
    }

    /**
     * Removes fields equal to the empty string from the provided record.
     *
     * @param \stdClass $record The import record.
     * @return \stdClass A version of the import record, with all empty fields removed.
     */
    protected function remove_empty_fields($record) {
        $record = clone $record;
        foreach ($record as $key => $value) {
            if ($value === '') {
                unset($record->$key);
            }
        }
        return $record;
    }

    /**
     * Checks a field's data is one of the specified values.
     *
     * @param \stdClass $record The record containing the data to validate, and possibly modify if $stringvalues used.
     * @param string $property The field / property to check.
     * @param array $list The valid possible values.
     * @param array $stringvalues associative array of strings to map back to $list value. Eg. array('no' => 0, 'yes' => 1)
     */
    protected function validate_fixed_list(&$record, $property, $list, $stringvalues = null) {
        // Note: do not worry about missing fields here.
        if (isset($record->$property)) {
            if (is_array($stringvalues) && isset($stringvalues[$record->$property])) {
                $record->$property = (string)$stringvalues[$record->$property];
            }
            // CANNOT use in_array() 'cause types don't match ...
            // AND PHP::in_array('yes', array(0, 1)) == true ???
            foreach ($list as $entry) {
                if ((string)$record->$property == (string)$entry) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * Converts a date in MMM/DD/YYYY format to a unix timestamp.
     *
     * @param string $date Date in MMM/DD/YYYY format
     * @return mixed The unix timestamp, or false if date is not in the right format.
     */
    protected function parse_date($date) {
        global $CFG;
        require_once($CFG->dirroot.'/local/datahub/lib.php');

        // Make sure there are three parts.
        $parts = explode('/', $date);
        if (count($parts) != 3) {
            return false;
        }

        // Make sure the month is valid.
        $month = $parts[0];
        $day = $parts[1];
        $year = $parts[2];
        $months = array('jan', 'feb', 'mar', 'apr',
                        'may', 'jun', 'jul', 'aug',
                        'sep', 'oct', 'nov', 'dec');
        $pos = array_search(strtolower($month), $months);
        if ($pos === false) {
            // Legacy format (zero values handled below by checkdate).
            $month = (int)$month;
        } else {
            // New "text" format.
            $month = $pos + 1;
        }

        // Make sure the combination of date components is valid.
        $day = (int)$day;
        $year = (int)$year;
        if (!checkdate($month, $day, $year)) {
            // Invalid combination of month, day and year.
            return false;
        }

        // Return unix timestamp.
        return rlip_timestamp(0, 0, 0, $month, $day, $year);
    }

    /**
     * Send the email.
     *
     * @param object $user The user the email is to.
     * @param object $from The user the email is from.
     * @param string $subject The subject of the email.
     * @param string $body The body of the email.
     * @return bool Success/Failure.
     */
    protected function sendemail($user, $from, $subject, $body) {
        return email_to_user($user, $from, $subject, strip_tags($body), $body);
    }

    /**
     * Calculates a string that specifies which fields can be used to identify
     * a user record based on the import record provided.
     *
     * @param \stdClass $record The user record.
     * @param \dhimport_version2\entity\entityinterface $obj Object to get mappings from.
     * @return string The description of identifying fields, as a comma-separated string.
     */
    public static function get_user_descriptor($record, $obj = null) {
        $fragments = array();

        // The fields we care to check.
        $possiblefields = array('user_username', 'user_email', 'user_idnumber');

        foreach ($possiblefields as $field) {
            if (isset($record->$field) && $record->$field !== '') {
                // Data for that field.
                $value = $record->$field;

                // Calculate syntax fragment.
                if (!is_null($obj)) {
                    $mappings = $obj->get_mappings();
                    $identifier = $mappings[substr($field, 5)];
                    $fragments[] = "{$identifier} value of \"{$value}\"";
                } else {
                    $fragments[] = "{$field} \"{$value}\"";
                }
            }
        }

        if (empty($fragments)) {
            // The fields we care to check.
            $possiblefields = array('username', 'email', 'idnumber');

            foreach ($possiblefields as $field) {
                if (isset($record->$field) && $record->$field !== '') {
                    // Data for that field.
                    $value = $record->$field;

                    // Calculate syntax fragment.
                    if (!is_null($obj)) {
                        $mappings = $obj->get_mappings();
                        $identifier = $mappings[$field];
                        $fragments[] = "{$identifier} value of \"{$value}\"";
                    } else {
                        $fragments[] = "{$field} \"{$value}\"";
                    }
                }
            }
        }

        // Combine into string.
        return implode(', ', $fragments);
    }

    /**
     * Obtains a userid from a data record without any logging
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @param array  $params The returned user parameters found in record
     * @param string $fieldprefix Optional prefix for identifying fields, default ''
     * @return mixed The user id, or false if not found
     */
    protected function get_userid_from_record_no_logging(&$record, &$params, $fieldprefix = '') {
        global $CFG, $DB;

        $field = $fieldprefix.'username';
        if (isset($record->$field)) {
            $record->$field = \core_text::strtolower($record->$field);
            $params['username'] = $record->$field;
            $params['mnethostid'] = $CFG->mnet_localhost_id;
        }
        $field = $fieldprefix.'email';
        if (isset($record->$field)) {
            $params['email'] = $record->$field;
        }
        $field = $fieldprefix.'idnumber';
        if (isset($record->$field)) {
            $params['idnumber'] = $record->$field;
        }

        if (empty($params) || $DB->count_records('user', $params) != 1 || !($userid = $DB->get_field('user', 'id', $params))) {
            return false;
        }
        return $userid;
    }

    /**
     * Obtains a userid from a data record, logging an error message to the file system log on failure.
     *
     * @param \stdClass $record One record of import data
     * @param string $filename The import file name, used for logging
     * @param string $fieldprefix Optional prefix for identifying fields, default ''
     * @return int|bool The user id, or false if not found
     */
    protected function get_userid_from_record(&$record, $fieldprefix = '') {
        $params = array();
        if (!($userid = $this->get_userid_from_record_no_logging($record, $params, $fieldprefix))) {
            // Failure.
            if (empty($params)) {
                $errstr = "No identifying fields found.";
                $entitytype = ($fieldprefix == '') ? 'enrolment' : 'user';
                $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, $entitytype);
                return false;
            }

            // Get description of identifying fields.
            $userdescriptor = static::get_user_descriptor((object)$params, $this);

            $uniqidentifiers = $params;
            unset($uniqidentifiers['mnethostid']); // Don't count this one.
            if (count($uniqidentifiers) > 1) {
                $doestoken = 'do';
            } else {
                $doestoken = 'does';
            }

            // Log message.
            $errstr = "{$userdescriptor} {$doestoken} not refer to a valid user.";
            $entitytype = ($fieldprefix == '') ? 'enrolment' : 'user';
            $this->fslogger->log_failure($errstr, 0, $this->filename, $this->linenumber, $record, $entitytype);
            return false;
        }

        // Success.
        return $userid;
    }
}
