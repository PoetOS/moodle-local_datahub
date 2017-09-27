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
 * Enrolment import class.
 */
class enrolment extends base {

    /**
     * Get the available fields for a given action.
     *
     * @param string $action The action we want fields for, or null for general list.
     * @return array An array of valid field names.
     */
    public function get_available_fields($action = null) {
        $fields = [
            'username',
            'email',
            'idnumber',
            'context',
            'instance',
            'role',
            'group',
            'grouping',
            'enrolmenttime',
            'completetime',
            'status',
            'remove_role',
        ];
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
                    [
                        'username',
                        'email',
                        'idnumber'
                    ],
                    'context',
                    'instance',
                    'role'
                ];
            case 'update':
                return [
                    [
                        'username',
                        'email',
                        'idnumber'
                    ],
                    'context',
                    'instance',
                ];
            case 'delete':
                return [
                    [
                        'username',
                        'email',
                        'idnumber'
                    ],
                    'context',
                    'instance',
                    'role'
                ];
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
        return ['create', 'add', 'update', 'delete'];
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

        if (empty($record->enrolmentaction)) {
            $message = "Required field \"enrolmentaction\" is unspecified or empty.";
            $this->log_failure($message, $record);
            return false;
        }

        if (!$this->check_required_fields($record->enrolmentaction, $record)) {
            // Missing a required field.
            return false;
        }

        switch ($record->enrolmentaction) {
            case 'add': // 1.9 BC.
            case 'create':
                return $this->enrolment_create($record);
            case 'update':
                return $this->enrolment_update($record);
            case 'delete':
                return $this->enrolment_delete($record);
            default:
                // Invalid action for this entity type.
                $message = "Action of \"{$record->enrolmentaction}\" is not supported.";
                $this->log_failure($message, $record);
                return false;
        }
    }

    /**
     * Create an enrolment.
     *
     * @param object $record One record of import data
     * @return bool True on success, otherwise false
     */
    protected function enrolment_create($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/enrollib.php');

        /*
            NOTE: "Update" action forwards here, so be sure to use
            $record->enrolmentaction when referencing the action, rather
            than hard-coding "create", as it may not be accurate.
        */

        // Break references so we're self-contained.
        $record = clone $record;
        $action = $record->enrolmentaction;

        // Set initial logging state with respect to enrolments (give non-specific message for now).
        $this->fslogger->set_enrolment_state(false, false);

        // Remove invalid fields.
        $record = $this->remove_invalid_fields($action, $record);

        // Field length checking.
        $lengthcheck = $this->check_enrolment_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // Data checking.
        $hasstatus = (!empty($record->status)) ? true : false;
        // If successful, this method will return a new $record which may contain altered data for alternative values.
        $record = $this->validate_core_data($action, $record);
        if (empty($record)) {
            // Validation failed.
            return false;
        }

        // Find existing user record.
        if (!$userid = $this->get_userid_from_record($record)) {
            return false;
        }

        // Track context info.
        $contextinfo = $this->get_contextinfo_from_record($record);
        if ($contextinfo == false) {
            return false;
        }
        list($contextlevel, $context) = $contextinfo;

        // Make sure the role is assignable at the course context level.
        $roleid = (!empty($record->role)) ? $DB->get_field('role', 'id', ['shortname' => $record->role]) : null;
        $params = ['roleid' => $roleid, 'contextlevel' => $contextlevel];
        if (!empty($roleid) && !$DB->record_exists('role_context_levels', $params)) {
            $errstr = "The role with shortname \"{$record->role}\" is not assignable on the {$record->context} context level.";
            $this->log_failure($errstr, $record);
            return false;
        }

        // Note: this seems redundant but will be useful for error messages later.
        $params = [
            'roleid' => $roleid,
            'contextid' => $context->id,
            'userid' => $userid,
            'component' => '',
            'itemid' => 0
        ];
        $roleassignmentexists = empty($roleid) || $DB->record_exists('role_assignments', $params);

        // Track whether an enrolment exists.
        $enrolmentexists = false;
        if ($contextlevel == CONTEXT_COURSE) {
            $enrolmentexists = is_enrolled($context, $userid);
        }

        /*
          After this point, general error messages should contain role assignment info
          they should also contain enrolment info if the context is a course.
        */

        $trackenrolments = ($record->context == 'course') ? true : false;
        $this->fslogger->set_enrolment_state(true, $trackenrolments);

        // Track the group and grouping specified.
        $groupid = 0;
        $groupingid = 0;

        // Duplicate group / grouping name checks and name validity checking.
        if ($record->context == 'course' && isset($record->group)) {

            // Check group.
            $params = ['name' => $record->group, 'courseid' => $context->instanceid];
            $groupcount = $DB->count_records('groups', $params);
            $creategroups = get_config('dhimport_version2', 'creategroupsandgroupings');
            if ($groupcount > 1) {
                // Ambiguous.
                $identifier = $this->mappings['group'];
                $errstr = "{$identifier} value of \"{$record->group}\" refers to multiple ";
                $errstr .= "groups in course with shortname \"{$record->instance}\".";
                $this->log_failure($errstr, $record);
                return false;
            } else if (empty($groupcount)) {
                if (empty($creategroups)) {
                    // Does not exist and not creating.
                    $identifier = $this->mappings['group'];
                    $errstr = "{$identifier} value of \"{$record->group}\" does not refer to ";
                    $errstr .= "a valid group in course with shortname \"{$record->instance}\".";
                    $this->log_failure($errstr, $record);
                    return false;
                }
            } else if ($groupcount === 1) {
                // Exact group exists.
                $groupid = groups_get_group_by_name($context->instanceid, $record->group);
            }

            // Check Grouping.
            if (isset($record->grouping)) {
                $params = ['name' => $record->grouping, 'courseid' => $context->instanceid];
                $groupingcount = $DB->count_records('groupings', $params);
                if ($groupingcount > 1) {
                    // Ambiguous.
                    $identifier = $this->mappings['grouping'];
                    $errstr = "{$identifier} value of \"{$record->grouping}\" refers to multiple ";
                    $errstr .= "groupings in course with shortname \"{$record->instance}\".";
                    $this->log_failure($errstr, $record);
                    return false;
                } else if (empty($groupingcount)) {
                    if (empty($creategroups)) {
                        // Does not exist and not creating.
                        $identifier = $this->mappings['grouping'];
                        $errstr = "{$identifier} value of \"{$record->grouping}\" does not refer to ";
                        $errstr .= "a valid grouping in course with shortname \"{$record->instance}\".";
                        $this->log_failure($errstr, $record);
                        return false;
                    }
                } else if ($groupingcount === 1) {
                    // Exact grouping exists.
                    $groupingid = groups_get_grouping_by_name($context->instanceid, $record->grouping);
                }
            }
        }

        // String to describe the user.
        $userdescriptor = static::get_user_descriptor($record);
        // String to describe the context instance.
        $contextdescriptor = $this->get_context_descriptor($record);

        // Going to collect all messages for this action.
        $logmessages = array();
        $enrolresult = null;
        if ($record->context == 'course') {
            // Set enrolment start and end time if specified, otherwise set enrolment time to 'now' to allow immediate access.
            $updateok = false;
            if ($enrolmentexists && ($enrolinstance = static::get_manual_enrolment_instance($context->instanceid)) &&
                    count($enrolinstance) == 2) {
                $updateok = true;
                if (empty($record->enrolmenttime)) {
                    $params = ['enrolid' => $enrolinstance['instance']->id, 'userid' => $userid];
                    $timestart = $DB->get_field('user_enrolments', 'timestart', $params);
                } else {
                    $timestart = $this->parse_date($record->enrolmenttime);
                }
                if (empty($record->completetime)) {
                    $params = ['enrolid' => $enrolinstance['instance']->id, 'userid' => $userid];
                    $timeend = $DB->get_field('user_enrolments', 'timeend', $params);
                } else {
                    $timeend = $this->parse_date($record->completetime);
                }
                if (!$hasstatus) {
                    $params = ['enrolid' => $enrolinstance['instance']->id, 'userid' => $userid];
                    $record->status = $DB->get_field('user_enrolments', 'status', $params);
                }
            } else {
                $timestart = empty($record->enrolmenttime) ? time() : $this->parse_date($record->enrolmenttime);
                $timeend = empty($record->completetime) ? 0 : $this->parse_date($record->completetime);
            }
            $enrolresult = true;
            if ($roleassignmentexists && !$enrolmentexists) {
                // Role assignment already exists, so just enrol the user.
                $enrolresult = static::enrol_try_internal_enrol($context->instanceid, $userid, null, $timestart, $timeend, $record->status);
            } else if (!$enrolmentexists) {
                // Role assignment does not exist, so enrol and assign role.
                if (($enrolresult = static::enrol_try_internal_enrol($context->instanceid, $userid, $roleid, $timestart, $timeend, $record->status)) !== false) {
                    // Collect success message for logging at end of action.
                    $logmessages[] = "User with {$userdescriptor} successfully assigned role with shortname ".
                            "\"{$record->role}\" on {$contextdescriptor}.";
                }
            } else if (!$roleassignmentexists) {
                // Updates timestart/timeend/status fields in enrolment & creates role assignment!
                if (($enrolresult = static::enrol_try_internal_enrol($context->instanceid, $userid, $roleid, $timestart, $timeend, $record->status)) !== false) {
                    // Collect success message for logging at end of action.
                    $logmessages[] = "User with {$userdescriptor} successfully updated enrolment and assigned role with ".
                            "shortname \"{$record->role}\" on {$contextdescriptor}.";
                }
            } else if ($updateok && ($hasstatus || !empty($record->enrolmenttime) || !empty($record->completetime))) {
                // Updates time and/or status field(s) in enrolment ...
                if (($enrolresult = static::enrol_try_internal_enrol($context->instanceid, $userid, $roleid, $timestart, $timeend,
                        $record->status)) !== false) {
                    // Collect success message for logging at end of action.
                    $logmessages[] = "User with {$userdescriptor} successfully updated enrolment on {$contextdescriptor}.";
                }
            } else if ($action != 'update' || empty($record->remove_role)) {
                // Duplicate enrolment attempt.
                $errstr = "User with {$userdescriptor} is already assigned role ";
                $errstr .= "with shortname \"{$record->role}\" on {$contextdescriptor}. ";
                $errstr .=  "User with {$userdescriptor} is already enrolled in course with shortname \"{$record->instance}\".";
                $this->log_failure($errstr, $record);
                return false;
            }

            // Collect success message for logging at end of action.
            if ($enrolresult && !$enrolmentexists) {
                $logmessages[] = "User with {$userdescriptor} enrolled in course with shortname \"{$record->instance}\".";
                if (!empty($context->instanceid) && !empty($userid)) {
                    $this->newenrolmentemail($userid, $context->instanceid);
                }
            }
        } else {
            if ($roleassignmentexists) {
                if ($action == 'update' && !empty($record->remove_role)) {
                    // Don't want to exit here if we have more to do, so just log error and continue.
                    $message = "User with {$userdescriptor} is already assigned role with ";
                    $message .= "shortname \"{$record->role}\" on {$contextdescriptor}.";
                    $logmessages[] = $message;
                } else {
                    // Role assignment already exists, so this action serves no purpose.
                    $errstr = "User with {$userdescriptor} is already assigned role ";
                    $errstr .= "with shortname \"{$record->role}\" on {$contextdescriptor}.";
                    $this->log_failure($errstr, $record);
                    return false;
                }
            } else {
                role_assign($roleid, $userid, $context->id);

                // Collect success message for logging at end of action.
                $message = "User with {$userdescriptor} successfully assigned role ";
                $message .= "with shortname \"{$record->role}\" on {$contextdescriptor}.";
                $logmessages[] = $message;
            }
        }

        if ($enrolresult === false) {
            // ELIS-8669: Enrolment in Moodle course failed, likely due to manual enrol plugin being disabled.
            $errstr = "Enrolment into {$contextdescriptor} has failed for user with {$userdescriptor}, ";
            $errstr .= "likely due to manual enrolments being disabled.";
            $this->log_failure($errstr, $record);
            return false;
        }

        if ($action == 'update' && !empty($record->remove_role)) {
            $rridentifier = $this->mappings['remove_role'];
            $roleassignparams = [
                'contextid' => $context->id,
                'userid' => $userid,
                'component' => '',
                'itemid' => 0,
            ];
            $numroleassigns = $DB->count_records('role_assignments', $roleassignparams);
            $lcremoverole = strtolower($record->remove_role);
            if ($roleid && in_array($lcremoverole, ['1', 'yes', 'true', 'all'])) {
                if ($numroleassigns > 1 && ($lcremoverole == 'all' || $numroleassigns == 2)) {
                    $select = 'roleid != ? AND contextid = ? AND userid = ? AND component = "" AND itemid = 0';
                    $params = [$roleid, $context->id, $userid];
                    $fields = 'id,roleid,userid,contextid,component,itemid';
                    $recs = $DB->get_records_select('role_assignments', $select, $params , '', $fields);
                    foreach ($recs as $rec) {
                        $rdata = (array)$rec;
                        unset($rdata['id']);
                        role_unassign_all($rdata);
                        $rmrolename = $DB->get_field('role', 'shortname', ['id' => $rec->roleid]);
                        $message = "User with {$userdescriptor} successfully unassigned role with";
                        $message .= " shortname \"{$rmrolename}\" on {$contextdescriptor}.";
                        $logmessages[] = $message;
                    }
                } else if ($numroleassigns <= 1) {
                    // Error no other role assignments exist.
                    $logmessages[] = "No other role assignments exist for User with {$userdescriptor} on {$contextdescriptor}.";
                } else {
                    // Error multiple role assignments, remove_role too ambiguous, must set to all or specify role shortname.
                    $message = "Multiple role assignments exist for User with {$userdescriptor} on {$contextdescriptor};";
                    $message .= " {$rridentifier} too ambiguous - set to \"all\" or  a valid role shortname.";
                    $logmessages[] = $message;
                }
            } else if ($lcremoverole != 'no' && $lcremoverole != 'false') {
                if ($numroleassigns > 1) {
                    if (($rmroleid = $DB->get_field('role', 'id', ['shortname' => $record->remove_role]))) {
                        $params = $roleassignparams;
                        $params['roleid'] = $rmroleid;
                        if ($DB->record_exists('role_assignments', $params)) {
                            role_unassign_all($params);
                            $message = "User with {$userdescriptor} successfully unassigned role with";
                            $message .= " shortname \"{$record->remove_role}\" on {$contextdescriptor}.";
                            $logmessages[] = $message;
                        } else {
                            // Error remove_role not assigned!
                            $message = "Cannot remove role with shortname \"{$record->remove_role}\"";
                            $message .= " - not assigned for User with {$userdescriptor} on {$contextdescriptor}.";
                            $logmessages[] = $message;
                        }
                    } else {
                        // Error invalid remove_role specified.
                        $logmessages[] = "Invalid {$rridentifier} value of \"{$record->remove_role}\" specified.";
                    }
                } else {
                    // Error cannot remove only role assignment.
                    $logmessages[] = "Cannot remove only role assignment for User with {$userdescriptor} on {$contextdescriptor}.";
                }
            }
        }

        if ($record->context == 'course' && isset($record->group)) {
            // Process specified group.
            require_once($CFG->dirroot.'/lib/grouplib.php');
            require_once($CFG->dirroot.'/group/lib.php');

            if ($groupid == 0) {
                // Need to create the group.
                $data = new \stdClass;
                $data->courseid = $context->instanceid;
                $data->name = $record->group;

                $groupid = groups_create_group($data);

                // Collect success message for logging at end of action.
                $logmessages[] = "Group created with name \"{$record->group}\".";
            }

            if (groups_is_member($groupid, $userid)) {
                // Error handling.
                $logmessages[] = "User with {$userdescriptor} is already assigned to group with name \"{$record->group}\".";
            } else {
                // Try to assign the user to the group.
                if (!groups_add_member($groupid, $userid)) {
                    // Should never happen...
                }

                // Collect success message for logging at end of action.
                $logmessages[] = "Assigned user with {$userdescriptor} to group with name \"{$record->group}\".";
            }

            if (isset($record->grouping)) {
                // Process the specified grouping.

                if ($groupingid == 0) {
                    // Need to create the grouping.
                    $data = new \stdClass;
                    $data->courseid = $context->instanceid;
                    $data->name = $record->grouping;

                    $groupingid = groups_create_grouping($data);

                    // Collect success message for logging at end of action.
                    $logmessages[] = "Created grouping with name \"{$record->grouping}\".";
                }

                // Assign the group to the grouping.
                if ($DB->record_exists('groupings_groups', ['groupingid' => $groupingid, 'groupid' => $groupid])) {
                    // Error handling.
                    $logmessages[] = "Group with name \"{$record->group}\" is already assigned to grouping with name \"{$record->grouping}\".";
                } else {
                    if (!groups_assign_grouping($groupingid, $groupid)) {
                        // Should never happen...
                    }

                    // Collect success message for logging at end of action.
                    $logmessages[] = "Assigned group with name \"{$record->group}\" to grouping with name \"{$record->grouping}\".";
                }
            }
        }

        // Log success.
        $this->fslogger->log_success(implode(' ', $logmessages), 0, $this->filename, $this->linenumber);

        if (!$this->fslogger->get_logfile_status()) {
            return false;
        }

        return true;
    }

    /**
     * Update an enrolment.
     *
     * @param \stdClass $record One record of import data.
     * @return bool True on success, otherwise false.
     */
    protected function enrolment_update($record) {
        return $this->enrolment_create($record);
    }

    /**
     * Delete an enrolment.
     *
     * @param \stdClass $record One record of import data.
     * @return bool True on success, otherwise false.
     */
    protected function enrolment_delete($record) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/enrollib.php');

        // Break references so we're self-contained.
        $record = clone $record;
        $action = $record->enrolmentaction;

        // Set initial logging state with respect to enrolments (give non-specific message for now).
        $this->fslogger->set_enrolment_state(false, false);

        // Field length checking.
        $lengthcheck = $this->check_enrolment_field_lengths($record);
        if (!$lengthcheck) {
            return false;
        }

        // If successful, this method will return a new $record which may contain altered data for alternative values.
        $record = $this->validate_core_data($action, $record);
        if (empty($record)) {
            // Validation failed.
            return false;
        }
        $roleid = $DB->get_field('role', 'id', ['shortname' => $record->role]);

        // Find existing user record.
        if (!$userid = $this->get_userid_from_record($record)) {
            return false;
        }

        // Track context info.
        $contextinfo = $this->get_contextinfo_from_record($record);
        if ($contextinfo == false) {
            return false;
        }
        list($contextlevel, $context) = $contextinfo;

        // Track whether an enrolment exists.
        $enrolmentexists = false;
        if ($contextlevel == CONTEXT_COURSE) {
            $enrolmentexists = is_enrolled($context, $userid);
        }

        // Determine whether the role assignment and enrolment records exist.
        $params = ['roleid' => $roleid, 'contextid' => $context->id, 'userid' => $userid];
        $roleassignmentexists = $DB->record_exists('role_assignments', $params);

        /*
          After this point, general error messages should contain role assignment info
          they should also contain enrolment info if the context is a course.
        */

        $trackenrolments = ($record->context === 'course') ? true : false;
        $this->fslogger->set_enrolment_state(true, $trackenrolments);

        if (!$roleassignmentexists) {
            $userdescriptor = static::get_user_descriptor($record);
            $contextdescriptor = $this->get_context_descriptor($record);
            $message = "User with {$userdescriptor} is not assigned role with ";
            $message .= "shortname \"{$record->role}\" on {$contextdescriptor}.";

            if ($record->context != 'course') {
                // Nothing to delete.
                $this->log_failure($message, $record);
                return false;
            } else if (!$enrolmentexists) {
                $message .= " User with {$userdescriptor} is not enrolled in ";
                $message .= "course with shortname \"{$record->instance}\".";
                $this->log_failure($message, $record);
                return false;
            } else {
                // Count how many role assignments the user has on this context.
                $params = ['userid' => $userid, 'contextid' => $context->id];
                $numassignments = $DB->count_records('role_assignments', $params);

                if ($numassignments > 0) {
                    // Can't unenrol because of some other role assignment.
                    $message .= " User with {$userdescriptor} requires their enrolment ";
                    $message .= "to be maintained because they have another role assignment in this course.";
                    $this->log_failure($message, $record);
                    return false;
                }
            }
        }

        // String to describe the user.
        $userdescriptor = static::get_user_descriptor($record);
        // String to describe the context instance.
        $contextdescriptor = $this->get_context_descriptor($record);

        // Going to collect all messages for this action.
        $logmessages = array();

        if ($roleassignmentexists) {
            // Unassign role.
            role_unassign($roleid, $userid, $context->id);

            // Collect success message for logging at end of action.
            $logmessages[] = "User with {$userdescriptor} successfully unassigned role with shortname \"{$record->role}\" on {$contextdescriptor}.";
        }

        if ($enrolmentexists) {
            // Remove enrolment.
            if ($instance = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $context->instanceid])) {

                // Count how many role assignments the user has on this context.
                $numassignments = $DB->count_records('role_assignments', ['userid' => $userid, 'contextid' => $context->id]);

                if ($numassignments == 0) {
                    // No role assignments left, so we can delete enrolment record.
                    $plugin = enrol_get_plugin('manual');
                    $plugin->unenrol_user($instance, $userid);

                    // Collect success message for logging at end of action.
                    $logmessages[] = "User with {$userdescriptor} unenrolled from course with shortname \"{$record->instance}\".";
                }
            }
        }

        // Log success.
        $this->fslogger->log_success(implode(' ', $logmessages), 0, $this->filename, $this->linenumber);

        if (!$this->fslogger->get_logfile_status()) {
            return false;
        }
        return true;
    }

    /**
     * Check the lengths of fields from an enrolment record.
     *
     * @param object $record The enrolment record
     * @return bool True if field lengths are ok, otherwise false
     */
    protected function check_enrolment_field_lengths($record) {
        $lengths = [
            'username' => 100,
            'email' => 100,
            'idnumber' => 255,
            'group' => 254,
            'grouping' => 254,
        ];
        return $this->check_field_lengths('enrolment', $record, $lengths);
    }

    /**
     * Validates that core user fields are set to valid values, if they are set on the import record.
     *
     * @param string $action One of 'create' or 'update'
     * @param \stdClass $record The import record
     * @return bool|\stdClass true if the record validates correctly, otherwise false
     */
    protected function validate_core_data($action, $record) {
        global $CFG, $DB;

        // Break references. We return validated object.
        $record = clone $record;

        switch ($action) {
            case 'create':
            case 'add':
                if (empty($record->role)) {
                    $identifier = $this->mappings['role'];
                    $message = "Required column {$identifier} must be specified for {$record->action}.";
                    $this->log_failure($message, $record);
                    return false;
                }
                break;
        }

        switch ($action) {
            case 'create':
            case 'add':
            case 'update':
                $roleid = null;
                if (!empty($record->role)) {
                    $roleid = $DB->get_field('role', 'id', ['shortname' => $record->role]);
                    if (empty($roleid)) {
                        $identifier = $this->mappings['role'];
                        $message = "{$identifier} value of \"{$record->role}\" does not refer to a valid role.";
                        $this->log_failure($message, $record);
                        return false;
                    }
                }

                // Check for valid enrolment time.
                if (isset($record->enrolmenttime)) {
                    $value = $this->parse_date($record->enrolmenttime);
                    if ($value === false) {
                        $identifier = $this->mappings['enrolmenttime'];
                        $errstr = "$identifier value of \"{$record->enrolmenttime}\" is not a valid date in ";
                        $errstr .= "MM/DD/YYYY, DD-MM-YYYY, YYYY.MM.DD, or MMM/DD/YYYY format.";
                        $this->log_failure($errstr, $record);
                        return false;
                    }
                }

                // Check for valid complete time.
                if (isset($record->completetime)) {
                    $value = $this->parse_date($record->completetime);
                    if ($value === false) {
                        $identifier = $this->mappings['completetime'];
                        $errstr = "$identifier value of \"{$record->completetime}\" is not a valid date in ";
                        $errstr .= "MM/DD/YYYY, DD-MM-YYYY, YYYY.MM.DD, or MMM/DD/YYYY format.";
                        $this->log_failure($errstr, $record);
                        return false;
                    }
                }

                // Check for valid status field: active/suspended - ELIS-8394.
                if (!empty($record->status)) {
                    $record->status = \core_text::strtolower($record->status);
                    if ($record->status != 'active' && $record->status != 'suspended') {
                        $identifier = $this->mappings['status'];
                        $errstr = "$identifier value of \"{$record->status}\" is not a valid status, ";
                        $errstr .= "allowed values are: active or suspended.";
                        $this->log_failure($errstr, $record);
                        return false;
                    }
                    $record->status = ($record->status == 'suspended') ? ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;
                } else {
                    $record->status = ENROL_USER_ACTIVE;
                }
                break;
        }

        switch ($action) {
            case 'delete':
                $roleid = $DB->get_field('role', 'id', ['shortname' => $record->role]);
                if (empty($roleid)) {
                    $identifier = $this->mappings['role'];
                    $errstr = "{$identifier} value of \"{$record->role}\" does not refer to a valid role.";
                    $this->log_failure($errstr, $record);
                    return false;
                }
                break;
        }

        return $record;
    }

    /**
     * Obtains a context level and context record based on a role assignment
     * data record, logging an error message to the file system on failure.
     *
     * @param \stdClass $record One record of import data.
     * @return array|false List of context level and context object or false on error.
     */
    protected function get_contextinfo_from_record($record) {
        global $CFG, $DB;

        switch ($record->context) {
            case 'course':
                // Find existing course.
                $courseid = $DB->get_field('course', 'id', ['shortname' => $record->instance]);
                if (empty($courseid)) {
                    // Invalid shortname.
                    $identifier = $this->mappings['instance'];
                    $errstr = "{$identifier} value of \"{$record->instance}\" does not refer ";
                    $errstr .= "to a valid instance of a course context.";
                    $this->log_failure($errstr, $record);
                    return false;
                }

                // Obtain the course context instance.
                $contextlevel = CONTEXT_COURSE;
                $context = \context_course::instance($courseid);
                return [$contextlevel, $context];

            case 'system':
                // Obtain the system context instance.
                $contextlevel = CONTEXT_SYSTEM;
                $context = \context_system::instance();
                return [$contextlevel, $context];

            case 'coursecat':
                // Make sure category name is not ambiguous.
                $count = $DB->count_records('course_categories', array('name' => $record->instance));
                if ($count > 1) {
                    // Ambiguous category name.
                    $identifier = $this->mappings['instance'];
                    $errstr = "{$identifier} value of \"{$record->instance}\" refers to multiple course category contexts.";
                    $this->log_failure($errstr, $record);
                    return false;
                }

                // Find existing course category.
                $categoryid = $DB->get_field('course_categories', 'id', ['name' => $record->instance]);
                if (empty($categoryid)) {
                    // Invalid name.
                    $identifier = $this->mappings['instance'];
                    $errstr = "{$identifier} value of \"{$record->instance}\" does not refer to a valid instance of a ";
                    $errstr .= "course category context.";
                    $this->log_failure($errstr, $record);
                    return false;
                }

                // Obtain the course category context instance.
                $contextlevel = CONTEXT_COURSECAT;
                $context = \context_coursecat::instance($categoryid);
                return [$contextlevel, $context];

            case 'user':
                // Find existing user.
                $params = ['username' => $record->instance, 'mnethostid' => $CFG->mnet_localhost_id];
                $targetuserid = $DB->get_field('user', 'id', $params);
                if (empty($targetuserid)) {
                    // Invalid username.
                    $identifier = $this->mappings['instance'];
                    $errstr = "{$identifier} value of \"{$record->instance}\" does not refer to a valid instance of a ";
                    $errstr .= "user context.";
                    $this->log_failure($errstr, $record);
                    return false;
                }

                // Obtain the user context instance.
                $contextlevel = CONTEXT_USER;
                $context = \context_user::instance($targetuserid);
                return [$contextlevel, $context];

            default:
                // Unsupported context level.
                $identifier = $this->mappings['context'];
                $errstr = "{$identifier} value of \"{$record->context}\" is not one of the available options ";
                $errstr .= "(system, user, coursecat, course).";
                $this->log_failure($errstr, $record);
                return false;
        }
    }

    /**
     * Calculates a string that specifies a descriptor for a context instance.
     *
     * @param \stdClass $record The object specifying the context and instance
     * @return string The descriptive string
     */
    static function get_context_descriptor($record) {
        if ($record->context == 'system') {
            // No instance for the system context.
            $contextdescriptor = 'the system context';
        } else if ($record->context == 'coursecat') {
            // Convert "coursecat" to "course category" due to legacy 1.9 weirdness.
            $contextdescriptor = "course category \"{$record->instance}\"";
        } else {
            // Standard case.
            $contextdescriptor = "{$record->context} \"{$record->instance}\"";
        }

        return $contextdescriptor;
    }

    /**
     * Get manual enrolment instance.
     * @param int $courseid The course id.
     * @return bool|array ['plugin' => , 'instance' => ] or false on failure.
     */
    public static function get_manual_enrolment_instance($courseid) {
        global $DB;
        if (!enrol_is_enabled('manual')) {
            return false;
        }

        if (!$enrol = enrol_get_plugin('manual')) {
            return false;
        }

        $params = ['enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED];
        if (!$instances = $DB->get_records('enrol', $params, 'sortorder,id ASC')) {
            return false;
        }
        $instance = reset($instances);
        return ['plugin' => $enrol, 'instance' => $instance];
    }

    /**
     * Try to enrol user via default internal auth plugin.
     *
     * For now this is always using the manual enrol plugin...
     * This is from Moodle lib/enrollib.php modified to add status param for ELIS-8394
     * @see lib/enrollib.php
     * @param int $courseid The course id.
     * @param int $userid The user id.
     * @param int $roleid The role id.
     * @param int $timestart The start time.
     * @param int $timeend The end time.
     * @param int $status either: ENROL_USER_ACTIVE or ENROL_USER_SUSPENDED, defaults to ENROL_USER_ACTIVE.
     * @return bool success
     */
    public static function enrol_try_internal_enrol($courseid, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null) {
        // Note: this is hardcoded to manual plugin for now!
        if (!($enrolinstance = static::get_manual_enrolment_instance($courseid)) || count($enrolinstance) != 2) {
            return false;
        }
        $enrolinstance['plugin']->enrol_user($enrolinstance['instance'], $userid, $roleid, $timestart, $timeend,
                is_null($status) ? ENROL_USER_ACTIVE : $status);
        return true;
    }

    /**
     * Send an email to the user when they are enroled.
     *
     * @param int $userid The user id being enroled.
     * @param int $courseid The course id they're being enroled into.
     * @return bool Success/Failure.
     */
    protected function newenrolmentemail($userid, $courseid) {
        global $DB;

        if (empty($userid) || empty($courseid)) {
            return false;
        }

        $user = $DB->get_record('user', array('id' => $userid));
        $course = $DB->get_record('course', array('id' => $courseid));

        if (empty($user) || empty($course)) {
            return false;
        }

        $enabled = get_config('dhimport_version2', 'newenrolmentemailenabled');
        if (empty($enabled)) {
            // Emails disabled.
            return false;
        }

        $template = get_config('dhimport_version2', 'newenrolmentemailtemplate');
        if (empty($template)) {
            // No text set.
            return false;
        }

        if (empty($user->email)) {
            // User has no email.
            return false;
        }

        $subject = get_config('dhimport_version2', 'newenrolmentemailsubject');
        if (empty($subject) || !is_string($subject)) {
            $subject = '';
        }

        $from = get_config('dhimport_version2', 'newenrolmentemailfrom');
        if ($from === 'teacher') {
            $context = \context_course::instance($courseid);
            if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC', '', '', '', '', false, true)) {
                $users = sort_by_roleassignment_authority($users, $context);
                $from = current($users);
            } else {
                $from = get_admin();
            }
        } else {
            $from = get_admin();
        }

        $body = $this->newenrolmentemail_generate($template, $user, $course);
        return $this->sendemail($user, $from, $subject, $body);
    }

    /**
     * Generate a new enrolment email based on an email template, a user, and a course.
     *
     * @param string $templatetext The template for the message.
     * @param \stdClass $user The user object to use for placeholder substitutions.
     * @param \stdClass $course The course object to use for placeholder substitutions.
     * @return string The generated email.
     */
    protected function newenrolmentemail_generate($templatetext, $user, $course) {
        global $SITE;
        $placeholders = array(
            '%%sitename%%' => $SITE->fullname,
            '%%user_username%%' => (isset($user->username)) ?  $user->username : '',
            '%%user_idnumber%%' => (isset($user->idnumber)) ?  $user->idnumber : '',
            '%%user_firstname%%' => (isset($user->firstname)) ?  $user->firstname : '',
            '%%user_lastname%%' => (isset($user->lastname)) ?  $user->lastname : '',
            '%%user_fullname%%' => datahub_fullname($user),
            '%%user_email%%' => (isset($user->email)) ? $user->email : '',
            '%%course_fullname%%' => (isset($course->fullname)) ? $course->fullname : '',
            '%%course_shortname%%' => (isset($course->shortname)) ? $course->shortname : '',
            '%%course_idnumber%%' => (isset($course->idnumber)) ? $course->idnumber : '',
            '%%course_summary%%' => (isset($course->summary)) ? $course->summary : '',
        );
        return str_replace(array_keys($placeholders), array_values($placeholders), $templatetext);
    }
}

