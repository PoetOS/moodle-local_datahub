<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_datahub
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

namespace dhimport_version2;

require_once(__DIR__.'/../../../../../config.php');
global $CFG;
require_once($CFG->dirroot.'/local/datahub/lib/rlip_fslogger.class.php');

/**
 * Class for logging general entry messages to the file system.
 * These "general" messages should likely NOT have been separated from the "specific" messages,
 * but rather inserted together.
 */
class fslogger extends \rlip_fslogger_linebased {
    /** @var bool Are we tracking role actions? */
    private $trackroleactions = false;

    /** @var bool Are we tracking enrolment actions? */
    private $trackenrolmentactions = false;

    /**
     * Set this logger into a particular state with respect to tracking specific
     * role assignment and enrolment actions
     *
     * @param bool $trackroleactions True if we should track role assignment actions, otherwise false.
     * @param bool $trackenrolmentactions True if we should track enrolment actions, otherwise false.
     */
    public function set_enrolment_state($trackroleactions, $trackenrolmentactions) {
        $this->trackroleactions = $trackroleactions;
        $this->trackenrolmentactions = $trackenrolmentactions;
    }

    /**
     * Log a failure message to the log file, and potentially the screen
     *
     * @param string $message The message to long
     * @param int $timestamp The timestamp to associate the message with, or 0 for the current time
     * @param string $filename The name of the import / export file we are reporting on
     * @param int $entitydescriptor A descriptor of which entity from an import file we are handling, if applicable
     * @param \stdClass $record Imported data
     * @param string $type Type of import
     */
    public function log_failure($message, $timestamp = 0, $filename = NULL, $entitydescriptor = NULL, $record = NULL, $type = NULL) {
        if (!empty($record) && !empty($type)) {
            $this->type_validation($type);
            $message = $this->general_validation_message($record, $message, $type);
        }
        parent::log_failure($message, $timestamp, $filename, $entitydescriptor);
    }

    /**
     * Adds the general message to the specific message for a given type
     *
     * @param \stdClass $record Imported data
     * @param string $message The specific message
     * @param string $type Type of import
    */
    public function general_validation_message($record, $message, $type) {

        // Item "action" is not always provided. In that case, return only the specific message.
        if (empty($record->action)) {
            // Missing action, general message will be fairly generic.
            $typedisplay = ucfirst($type);
            return "{$typedisplay} could not be processed. {$message}";
        }

        $msg = "";

        // Instantiate the import plugin.
        $plugin = new \dhimport_version2\importplugin();

        if ($type == "enrolment") {
            if ($record->action != 'create' && $record->action != 'delete') {
                // Invalid action.
                return 'Enrolment could not be processed. '.$message;
            }

            if (!$this->trackroleactions && !$this->trackenrolmentactions) {
                // Error without sufficient information to properly provide details.
                if ($record->action == 'create') {
                    return 'Enrolment could not be created. '.$message;
                } else if($record->action == 'delete') {
                    return 'Enrolment could not be deleted. '.$message;
                }
            }

            // Collect role assignment and enrolment messages.
            $lines = array();

            if ($this->trackroleactions) {
                // Determine if a user identifier was set.
                $useridentifierset = !empty($record->username) || !empty($record->email) || !empty($record->idnumber);
                // Determine if all required fields were set.
                $requiredfieldsset = !empty($record->role) && $useridentifierset && !empty($record->context);
                // List of contexts at which role assignments are allowed for specific instances.
                $validcontexts = array('coursecat', 'course', 'user');

                // Descriptive string for user and context.
                $userdescriptor = $plugin->get_user_descriptor($record);
                $contextdescriptor = $plugin->get_context_descriptor($record);

                switch ($record->action) {
                    case "create":
                        if ($requiredfieldsset && in_array($record->context, $validcontexts) && !empty($record->instance)) {
                            // Assignment on a specific context.
                            $lines[] = "User with {$userdescriptor} could not be assigned role ".
                                       "with shortname \"{$record->role}\" on {$contextdescriptor}.";
                        } else if ($requiredfieldsset && $record->context == 'system') {
                            // Assignment on the system context.
                            $lines[] = "User with {$userdescriptor} could not be assigned role ".
                                       "with shortname \"{$record->role}\" on the system context.";
                        } else {
                            // Not valid.
                            $lines[] = "Role assignment could not be created.";
                        }
                        break;
                    case "delete":
                        if ($requiredfieldsset && in_array($record->context, $validcontexts) && !empty($record->instance)) {
                            // Unassignment from a specific context.
                            $lines[] = "User with {$userdescriptor} could not be unassigned role ".
                                       "with shortname \"{$record->role}\" on {$contextdescriptor}.";
                        } else if ($requiredfieldsset && $record->context == 'system') {
                            // Unassignment from the system context.
                            $lines[] = "User with {$userdescriptor} could not be unassigned role ".
                                       "with shortname \"{$record->role}\" on the system context.";
                        } else {
                            // Not valid.
                            $lines[] = "Role assignment could not be deleted. ";
                        }
                        break;
                }
            }

            if ($this->trackenrolmentactions) {
                // Determine if a user identifier was set.
                $useridentifierset = !empty($record->username) || !empty($record->email) || !empty($record->idnumber);
                // Determine if some required field is missing.
                $missingrequiredfield = !$useridentifierset || empty($record->instance);

                // Descriptive string for user.
                $userdescriptor = $plugin->get_user_descriptor($record);

                switch ($record->action) {
                    case "create":
                        if ($missingrequiredfield) {
                            // Required field missing, so use generic failure message.
                            $lines[] = "Enrolment could not be created.";
                        } else {
                            // More accurate failure message.
                            $lines[] = "User with {$userdescriptor} could not be enrolled in ".
                                       "course with shortname \"{$record->instance}\".";
                        }
                        break;
                    case "delete":
                        if ($missingrequiredfield) {
                            // Required field missing, so use generic failure message.
                            $lines[] = "Enrolment could not be deleted.";
                        } else {
                            // More accurate failure message.
                            $lines[] = "User with {$userdescriptor} could not be unenrolled ".
                                       "from course with shortname \"{$record->instance}\".";
                        }
                        break;
                }
            }

            // Create combined message, potentially containing role assignment and enrolment components.
            $msg = implode(' ', $lines).' '.$message;
        }

        if ($type == "course") {
            $type = ucfirst($type);
            switch ($record->action) {
                case "create":
                    if (empty($record->shortname)) {
                        $msg = "Course could not be created. " . $message;
                    } else {
                        $msg =  "{$type} with shortname \"{$record->shortname}\" could not be created. " . $message;
                    }
                    break;
                case "update":
                    if (empty($record->shortname)) {
                        $msg = "Course could not be updated. " . $message;
                    } else {
                        $msg = "{$type} with shortname \"{$record->shortname}\" could not be updated. " . $message;
                    }
                    break;
                case "delete":
                    if (empty($record->shortname)) {
                        $msg = "Course could not be deleted. " . $message;
                    } else {
                        $msg = "{$type} with shortname \"{$record->shortname}\" could not be deleted. " . $message;
                    }
                    break;
                default:
                    // Invalid action.
                    $msg = 'Course could not be processed. '.$message;
                    break;
            }
        }

        if ($type == "user") {
            $type = ucfirst($type);
            switch ($record->action) {
                case "create":
                    // Make sure all required fields are specified.
                    if (empty($record->username) || empty($record->email)) {
                        $msg = "User could not be created. " . $message;
                    } else {
                        $userdescriptor = $plugin->get_user_descriptor($record);
                        $msg =  "{$type} with {$userdescriptor} could not be created. " . $message;
                    }
                    break;
                case "update":
                    // Make sure all required fields are specified.
                    if (empty($record->username) && empty($record->email) && empty($record->idnumber)) {
                        $msg = "User could not be updated. " . $message;
                    } else {
                        $userdescriptor = $plugin->get_user_descriptor($record);
                        $msg = "{$type} with {$userdescriptor} could not be updated. " . $message;
                    }
                    break;
                case "delete":
                    // Make sure all required fields are specified.
                    if (empty($record->username) && empty($record->email) && empty($record->idnumber)) {
                        $msg = "User could not be deleted. " . $message;
                    } else {
                        $userdescriptor = $plugin->get_user_descriptor($record);
                        $msg = "{$type} with {$userdescriptor} could not be deleted. " . $message;
                    }
                    break;
                default:
                    // Invalid action.
                    $msg = 'User could not be processed. '.$message;
                    break;
            }
        }

        return $msg;
    }

    /**
     * Validate the provided type.
     *
     * @param string $type The type to validate.
     */
    private function type_validation($type) {
        $types = array('course', 'user', 'roleassignment', 'group', 'enrolment');
        if (!in_array($type, $types)) {
            throw new Exception("\"$type\" in an invalid type. The available types are " . implode(', ', $types));
        }
    }
}
