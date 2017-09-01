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

require_once($CFG->dirroot.'/local/datahub/lib/rlip_dataplugin.class.php');

/**
 * Base class for a provider that instantiates a file plugin
 * for a particular import entity type
 */
abstract class rlip_importprovider {
    //full path of the log file, including its filename, NOT relative to the
    //moodledata directory
    var $logpath = NULL;

    /**
     * Hook for providing a file plugin for a particular
     * import entity type
     *
     * @param string $entity The type of entity
     * @return object The file plugin instance, or false if not applicable
     */
    abstract function get_import_file($entity);

    /**
     * Provides the object used to log information to the database to the
     * import
     *
     * @return object the DB logger
     */
    function get_dblogger() {
        global $CFG;
        require_once($CFG->dirroot.'/local/datahub/lib/rlip_dblogger.class.php');

        //for now, the only db logger, and assume scheduled
        return new rlip_dblogger_import(false);
    }

    /**
     * Provides the object used to log information to the file system logfile
     *
     * @param  string $plugin  the plugin
     * @param  string $entity  the entity type
     * @param boolean $manual  Set to true if a manual run
     * @param  integer $starttime the time used in the filename
     * @return object the fslogger
     */
    function get_fslogger($plugin, $entity = '', $manual = false, $starttime = 0) {
        global $CFG;
        require_once($CFG->dirroot.'/local/datahub/lib/rlip_fslogger.class.php');
        //set up the file-system logger
        $filepath = get_config($plugin, 'logfilelocation');

        //get filename
        $filename = rlip_log_file_name('import', $plugin, $filepath, $entity, $manual, $starttime);
        if (!empty($filename)) {
            $this->set_log_path($filename);
            $fileplugin = rlip_fileplugin_factory::factory($filename, NULL, true);
            return rlip_fslogger_factory::factory($plugin, $fileplugin, $manual);
        }
        return null;
    }

    /**
     * Set the full path of the log file, including its filename
     *
     * @param string $logpath The appropriate path and filename
     */
    public function set_log_path($logpath) {
        $this->logpath = $logpath;
    }

    /**
     * Obtains the full path of the log file, including its filename
     *
     * @return string $logpath The appropriate path and filename
     */
    public function get_log_path() {
        return $this->logpath;
    }
}

/**
 * Base class for Integration Point import plugins
 *
 * This is the *legacy* base class for anything pre version2.
 */
abstract class rlip_importplugin_base extends \local_datahub\importplugin_base {
    /**
     * Determines whether the current plugin supports the supplied feature
     *
     * @param string $feature A feature description, either in the form
     *                        [entity] or [entity]_[action]
     *
     * @return mixed An array of actions for a supplied entity, an array of
     *               required fields for a supplied action, or false on error
     */
    function plugin_supports($feature) {
        $parts = explode('_', $feature);

        if (count($parts) == 1) {
            //is this entity supported?
            return $this->plugin_supports_entity($feature);
        } else if (count($parts) == 2) {
            //is this action supported?
            list($entity, $action) = $parts;
            return $this->plugin_supports_action($entity, $action);
        }

        return false;
    }

    /**
     * Get the current import plugin.
     *
     * @return string The current plugin component name (i.e. "dhimport_version1").
     */
    protected function get_plugin() {
        // Convert class name to plugin name.
        $class = get_class($this);
        $plugin = str_replace('rlip_importplugin_', 'dhimport_', $class);
        return $plugin;
    }

    /**
     * Get the URL for a manual run.
     *
     * @return string The URL for a manual run.
     */
    public function get_manualrun_url() {
        global $CFG;
        $directories = core_component::get_plugin_types();
        $directory = $directories['dhimport'];
        $directory = str_replace($CFG->dirroot, $CFG->wwwroot, $directory);
        list($prefix, $plugintype, $pluginname) = explode('_', get_called_class());
        return $directory.'/manualrun.php?plugin=dhimport_'.$pluginname;
    }

    /**
     * Determines whether the current plugin supports the supplied entity type
     *
     * @param string $entity The type of entity
     *
     * @return mixed An array of actions for a supplied entity, or false on
     *               error
     */
    function plugin_supports_entity($entity) {
        $methods = get_class_methods($this);
        //look for a method named [entity]_action
        $method = "{$entity}_action";

        if (method_exists($this, $method)) {
            /* for performance, retrieve the temporary stored import actions
             * from the previous entity; otherwise, retrieve new import actions
             */
            if ($this->tmp_entity == $entity) {
                return $this->tmp_import_actions;
            } else {
                $this->tmp_entity = $entity;
                $this->tmp_import_actions = $this->get_import_actions($entity);
                return $this->tmp_import_actions;
            }
        }

        return false;
    }

    /**
     * Determines whether the current plugin supports the supplied combination
     * of entity type and action
     *
     * @param string $entity The type of entity
     * @param string $action The action being performed
     *
     * @return mixed An array of required fields, or false on error
     */
    function plugin_supports_action($entity, $action) {
        //first make sure the entity is supported
        if (!$this->plugin_supports_entity($entity)) {
            return false;
        }

        //look for a method named [entity]_[action]
        $method = "{$entity}_{$action}";
        if (method_exists($this, $method)) {
            return $this->get_import_fields($entity, $action);
        }

        return false;
    }

    /**
     * Specifies the list of entities that the current import
     * plugin supports actions for
     *
     * @return array An array of entity types
     */
    public function get_import_entities() {
        $result = array();
        $methods = get_class_methods($this);

        foreach ($methods as $method) {
            $parts = explode('_', $method);
            if (count($parts) == 2) {
                if (end($parts) == 'action') {
                    $result[] = $parts[0];
                }
            }
        }

        return $result;
    }

    /**
     * Specifies the list of actions that the current import
     * plugin supports on the supplied entity type
     *
     * @param string $entity The type of entity
     *
     * @return array An array of actions
     */
    function get_import_actions($entity) {
        $result = array();
        $methods = get_class_methods($this);

        foreach ($methods as $method) {
            $parts = explode('_', $method);
            if (count($parts) == 2) {
                if (reset($parts) == $entity && end($parts) != 'action') {
                    $result[] = $parts[1];
                }
            }
        }

        return $result;
    }

    /**
     * Specifies the list of required import fields that the
     * current import requires for the supplied entity type
     * and action
     *
     * @param string $entity The type of entity
     * @param string $action The action being performed
     *
     * @return array An array of required fields
     */
    function get_import_fields($entity, $action) {
        $attribute = 'import_fields_'.$entity.'_'.$action;

        if (property_exists($this, $attribute)) {
            return static::$$attribute;
        }

        return array();
    }

    /**
     * Obtains a list of required fields that are missing from the supplied
     * import record (helper method)
     *
     * @param object $record One import record
     * @param array $required_fields The required fields, with sub-arrays used
     *                               in "1-of-n required" scenarios
     * @return array An array, in the same format as $required_fields
     */
    function get_missing_required_fields($record, $required_fields) {
        $result = array();

        if (empty($required_fields)) {
            return false;
        }

        foreach ($required_fields as $field_or_group) {
            if (is_array($field_or_group)) {
                //"1-of-n" secnario
                $group = $field_or_group;

                //determine if one or more values in the group is set
                $found = false;
                foreach ($group as $key => $value) {
                    if (isset($record->$value) && $record->$value != '') {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    //not found, so include this group as missing and required
                    $result[] = $group;
                }
            } else {
                //simple scenario
                $field = $field_or_group;
                if (!isset($record->$field) || $record->$field === '') {
                    //not found, so include this field as missing an required
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
     * Validate that the action field is included in the header
     *
     * @param string $entity Type of entity, such as 'user'
     * @param array $header The list of supplied header columns
     * @param string $filename The name of the import file, to use in logging
     * @return bool True if the action column is correctly specified, otherwise false
     */
    protected function check_action_header($entity, $header, $filename) {
        if (!in_array('action', $header)) {
            //action column not specified
            $message = "Import file {$filename} was not processed because it is missing the ".
                       "following column: action. Please fix the import file and re-upload it.";
            $this->fslogger->log_failure($message, 0, $filename, $this->linenumber);
            $this->dblogger->signal_missing_columns($message);
            return false;
        }

        return true;
    }

    /**
     * Validate that all required fields are included in the header
     *
     * @param string $entity Type of entity, such as 'user'
     * @param array $header The list of supplied header columns
     * @param string $filename The name of the import file, to use in logging
     * @return bool True if the action column is correctly specified, otherwise false.
     */
    protected function check_required_headers($entity, $header, $filename) {
        //get list of required fields
        //note: for now, assuming that the delete action is available for
        //all entity types and requires the bare minimum in terms of fields
        $required_fields = $this->plugin_supports_action($entity, 'delete');

        //convert the header into a data record
        $record = new stdClass;
        foreach ($header as $value) {
            $record->$value = $value;
        }

        //figure out which are missing
        $missing_fields = $this->get_missing_required_fields($record, $required_fields);

        if ($missing_fields !== false) {
            $field_display = '';
            $first = reset($missing_fields);

            //for now, assume "groups" are always first and only showing
            //that one problem in the log
            if (!is_array($first)) {
                //1-of-n case

                //list of fields, as displayed
                $field_display = implode(', ', $missing_fields);

                //singular/plural handling
                $label = count($missing_fields) > 1 ? 'columns' : 'column';

                $message = "Import file {$filename} was not processed because it is missing the following ".
                           "required {$label}: {$field_display}. Please fix the import file and re-upload it.";
            } else {
                //basic case, all missing fields are required

                //list of fields, as displayed
                $group = reset($missing_fields);
                $field_display = implode(', ', $group);

                $message = "Import file {$filename} was not processed because one of the following columns is ".
                           "required but all are unspecified: {$field_display}. Please fix the import file and re-upload it.";
            }

            $this->fslogger->log_failure($message, 0, $filename, $this->linenumber);
            $this->dblogger->signal_missing_columns($message);
            return false;
        }

        return true;
    }

    /**
     * Validates whether all required fields are set, logging to the filesystem
     * where not - call from child class where needed
     *
     * @param string $entity Type of entity, such as 'user'
     * @param object $record One data import record
     * @param string $filename The name of the import file, to use in logging
     * @param array $exceptions A mapping from a field to a key, value pair that
     *                          allows that missing field to be ignored - does
     *                          not work for "1-of-n" setups
     * @return boolean true if fields ok, otherwise false
     */
    function check_required_fields($entity, $record, $filename, $exceptions = array()) {

        //get list of required fields
        $required_fields = $this->plugin_supports_action($entity, $record->action);
        //figure out which are missing
        $missing_fields = $this->get_missing_required_fields($record, $required_fields);

        $messages = array();

        if ($missing_fields !== false) {
            //missing one or more fields

            //process "1-of-n" type fields first
            foreach ($missing_fields as $key => $value) {
                if (count($value) > 1) {
                    //use helper to do any display-related field name transformation
                    $display_value = $this->get_required_field_display($value);
                    $fields = implode(', ', $display_value);

                    $messages[] = "One of {$fields} is required but all are unspecified or empty.";
                    //remove so we don't re-process
                    unset($missing_fields[$key]);
                }
            }

            //handle absolutely required fields
            if (count($missing_fields) == 1) {
                $append = true;

                $field = reset($missing_fields);

                if (isset($exceptions[$field])) {
                    //determine the dependency key and value
                    $dependency = $exceptions[$field];
                    $arraykeys = array_keys($dependency);
                    $key = reset($arraykeys);
                    $value = reset($arraykeys);

                    if (isset($record->$key) && $record->$key == $value) {
                        //dependency applies, so no error
                        $append = false;
                    }
                }

                if ($append) {
                    //use helper to do any display-related field name transformation
                    $field_display = $this->get_required_field_display($field);
                    $messages[] = "Required field {$field_display} is unspecified or empty.";
                }
            } else if (count($missing_fields) > 1) {
                //use helper to do any display-related field name transformation
                $missing_fields_display = $this->get_required_field_display($missing_fields);
                $fields = implode(', ', $missing_fields_display);
                $messages[] = "Required fields {$fields} are unspecified or empty.";
            }

            if (count($messages) > 0) {
                //combine and log
                $message = implode(' ', $messages);
                //todo: consider only adding these parameters in the version 1 import plugin
                $this->fslogger->log_failure($message, 0, $filename, $this->linenumber, $record, $entity);
                return false;
            }
        }

        return true;
    }

    /**
     * Perform any necessary transformation on required fields
     * for display purposes
     *
     * @param mixed $fieldorgroup a single field name string, or an array
     *                            of them
     * @return mixed the field or array of fields to display
     */
    function get_required_field_display($fieldorgroup) {
        return $fieldorgroup;
    }

    /**
     * Obtains the listing of fields that are available for the specified
     * entity type
     *
     * @param string $entitytype The type of entity
     */
    function get_available_fields($entitytype) {
        global $DB;

        if ($this->plugin_supports($entitytype) !== false) {
            $attribute = 'available_fields_'.$entitytype;

            $result = array_merge(array('action'), static::$$attribute);

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Validates whether the "action" field is correctly set on a record,
     * logging error to the file system, if necessary - call from child class
     * when needed
     *
     * @param string $entitytype The type of entity we are performing an action on
     * @param object $record One data import record
     * @param string $filename The name of the import file, to use in logging
     * @return boolean true if action field is set, otherwise false
     */
    function check_action_field($entitytype, $record, $filename) {
        if (!isset($record->action) || $record->action === '') {
            //not set, so error

            //use helper to do any display-related field name transformation
            $field_display = $this->get_required_field_display('action');
            $message = "Required field {$field_display} is unspecified or empty.";
            $this->fslogger->log_failure($message, 0, $filename, $this->linenumber);

            return false;
        }

        //feature, in the standard Moodle "plugin_supports" format
        $feature = $entitytype.'_'.$record->action;

        if (!$this->plugin_supports($feature)) {
            //invalid action for this entity type
            $message = "Action of \"{$record->action}\" is not supported.";
            $this->fslogger->log_failure($message, 0, $filename, $this->linenumber);
            return false;
        }

        return true;
    }

    /**
     * Entry point for processing a single record.
     *
     * @param string $entity The type of entity.
     * @param object $record One record of import data.
     * @param string $filename Import file name to user for logging.
     * @return bool True on success, otherwise false.
     */
    protected function process_record($entity, $record, $filename) {
        //increment which record we're on
        $this->linenumber++;

        $action = isset($record->action) ? $record->action : '';
        $method = "{$entity}_action";

        try {
            $result = $this->$method($record, $action, $filename);
        } catch (Exception $e) {
            $result = false;
            // log error
            $message = 'Exception processing record: '.$e->getMessage();
            $this->fslogger->log_failure($message, 0, $filename, $this->linenumber);
        }
        return $result;
    }

    /**
     * Specifies the UI labels for the various import files supported by this plugin.
     *
     * @return array The string labels for files.
     */
    abstract public function get_file_labels();

    /**
     * Getter for the file system logging object
     *
     * @return object The file system logging object
     */
    function get_fslogger() {
        return $this->fslogger;
    }
}
