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

namespace dhimport_version2;

use \dhimport_version2\provider\queue as queueprovider;
/**
 * Version 2 import plugin.
 */
class importplugin extends \local_datahub\importplugin_base {
    /**
     * Import plugin constructor.
     *
     * @param object $provider The import file provider that will be used to obtain import files.
     * @param bool $manual Set to true if a manual run.
     */
    public function __construct($provider = null, $manual = false) {
        /*
            The moodlefile provider implies a manual import, so if we're not doing that, we're
            in a cron run. Version2 uses a queue so the provider used in a cron is our queue
            provider.

            A little hacky, and should be changed when v1 removed.
        */
        if (!($provider instanceof \rlip_importprovider_moodlefile)) {
            $provider = new queueprovider();
        }
        parent::__construct($provider, $manual);
    }

    /**
     * Specifies the UI labels for the various import files supported by this plugin.
     *
     * @return array The string labels for the import page.
     */
    public function get_file_labels() {
        return [get_string('importfile', 'dhimport_version2')];
    }

    /**
     * Get the URL for a manual run.
     *
     * @return string The URL for a manual run.
     */
    public function get_manualrun_url() {
        global $CFG;
        return $CFG->wwwroot.'/local/datahub/importplugins/manualrun2.php';
        /*
        We'll use this once the UI is further along.

        $directories = \core_component::get_plugin_types();
        $directory = $directories['dhimport'];
        $directory = str_replace($CFG->dirroot, $CFG->wwwroot, $directory);
        list($prefix, $plugintype, $pluginname) = explode('_', get_called_class());
        return $directory.'/'.$pluginname.'/';
         */
    }

    /**
     * Gets the list of entities that the import plugin supports.
     *
     * @return array An array of entity types
     */
    public function get_import_entities() {
        return ['user', 'course', 'enrolment'];
    }

    /**
     * Get the available fields for a given entity type.
     *
     * @param string $entitytype The entity type.
     * @return array Array of available fields for that entity.
     */
    public function get_available_fields($entitytype) {
        $entityinstance = $this->get_entity_instance($entitytype);
        return (!empty($entityinstance)) ? $entityinstance->get_available_fields() : null;
    }

    /**
     * Determine whether the current plugin supports a particular entity.
     *
     * @param string $entity The name of the entity.
     * @return array|bool An array of supported actions for the entity, or false if not supported.
     */
    protected function plugin_supports_entity($entity) {
        $entities = $this->get_import_entities();
        if (in_array($entity, $entities, true)) {
            $instance = $this->get_entity_instance($entity);
            if (!empty($instance)) {
                return $instance->get_supported_actions();
            }
        }
        return false;
    }

    /**
     * Determine whether the current plugin supports an action for an entity.
     *
     * @param string $entity The name of the entity.
     * @param string $action The action.
     * @return array|bool An array of required fields for the entity and action, or false if not supported.
     */
    protected function plugin_supports_action($entity, $action) {
        $entities = $this->get_import_entities();
        if (in_array($entity, $entities, true)) {
            $instance = $this->get_entity_instance($entity);
            if (!empty($instance)) {
                $requiredfields = $instance->get_required_fields($action);
                return (is_array($requiredfields)) ? $requiredfields : false;
            }
        }
        return false;
    }

    /**
     * Should stop processing hook that stops processing if the queue has been paused.
     *
     * @param int $starttime The timestamp the job started.
     * @param int $maxruntime The maximum number of seconds allowed for the job.
     * @return bool If true, stop processing. If false, continue as normal.
     */
    protected function hook_should_stop_processing($starttime, $maxruntime) {
        $queuepaused = get_config('dhimport_version2', 'queuepaused');
        if (!empty($queuepaused) && $this->provider instanceof queueprovider) {
            return true;
        }
        return false;
    }

    /**
     * Mainline for running the import.
     *
     * @param int $targetstarttime The timestamp for when this task was meant to be run.
     * @param int $lastruntime The last time the export was run. (N/A for import).
     * @param int $maxruntime The max time in seconds to complete import. (default/0 = unlimited).
     * @param object $state Previous ran state data to continue from.
     * @return object State data to pass back on re-entry, null on success.
     *           ->result false on error, i.e. time limit exceeded.
     */
    public function run($targetstarttime = 0, $lastruntime = 0, $maxruntime = 0, $state = null) {
        global $CFG;
        require_once($CFG->dirroot.'/local/datahub/lib.php');

        if ($this->provider instanceof queueprovider) {
            // Use queue.
            $queuepaused = get_config('dhimport_version2', 'queuepaused');
            if (empty($queuepaused)) {
                $result = $this->runqueue($targetstarttime, $lastruntime, $maxruntime, $state);
            } else {
                return false;
            }
        } else {
            // Process directly.
            $result = parent::run($targetstarttime, $lastruntime, $maxruntime, $state);
        }

        if (!defined('PHPUnit_MAIN_METHOD')) {
            // Not in a unit test, so send out log files in a zip.
            $logids = $this->dblogger->get_log_ids();
            rlip_send_log_emails('dhimport_version2', $logids, $this->manual);
        }

        return $result;
    }

    /**
     * Mainline for running the queue import.
     *
     * @param int $targetstarttime The timestamp for when this task was meant to be run.
     * @param int $lastruntime The last time the export was run. (N/A for import).
     * @param int $maxruntime The max time in seconds to complete import. (default/0 = unlimited).
     * @param object $state Previous ran state data to continue from.
     * @return object State data to pass back on re-entry, null on success.
     *           ->result false on error, i.e. time limit exceeded.
     */
    protected function runqueue($targetstarttime = 0, $lastruntime = 0, $maxruntime = 0, $state = null) {
        global $DB;
        $record = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $this->provider->get_queueid()]);
        if (empty($record)) {
             // Should never happen.
             return null;
        }

        // Queued import is in progress.
        $newrecord = (object)['id' => $record->id, 'status' => queueprovider::STATUS_PROCESSING];
        $DB->update_record(queueprovider::QUEUETABLE, $newrecord);

        $state = (!empty($record->state)) ? unserialize($record->state) : null;

        // Run import.
        $result = parent::run($targetstarttime, $lastruntime, $maxruntime, $state);

        if ($result !== null) {
            // Job is not finished, and state is saved for next cron job run.
            $newrecord = (object)[
                'id' => $record->id,
                'status' => queueprovider::STATUS_QUEUED,
                'state' => serialize($result),
            ];
            $DB->update_record(queueprovider::QUEUETABLE, $newrecord);
            return null;
        }

        // Queued import has been completed.
        $params = ['queueid' => $record->id, 'status' => 0];
        $count = $DB->count_records(queueprovider::LOGTABLE, $params);
        if (!empty($count)) {
            // There are errors.
            $newrecord = (object)[
                'id' => $record->id,
                'state' => '',
                'status' => queueprovider::STATUS_ERRORS
            ];
            $DB->update_record(queueprovider::QUEUETABLE, $newrecord);
        } else {
            $newrecord = (object)[
                'id' => $record->id,
                'state' => '',
                'status' => queueprovider::STATUS_FINISHED
            ];
            $DB->update_record(queueprovider::QUEUETABLE, $newrecord);
        }
        return $result;
    }

    /**
     * Mainline for running the manual import.
     *
     * @param int $targetstarttime The timestamp for when this task was meant to be run.
     * @param int $lastruntime The last time the export was run. (N/A for import).
     * @param int $maxruntime The max time in seconds to complete import. (default/0 = unlimited).
     * @param object $state Previous ran state data to continue from.
     * @return object State data to pass back on re-entry, null on success.
     *           ->result false on error, i.e. time limit exceeded.
     */
    protected function runmanual($targetstarttime = 0, $lastruntime = 0, $maxruntime = 0, $state = null) {
        return parent::run($targetstarttime, $lastruntime, $maxruntime, $state);
    }

    /**
     * Add custom entries to the Settings block tree menu
     *
     * @param object $adminroot The main admin tree root object
     * @param string $parentname The name of the parent node to add children to
     */
    public function admintree_setup(&$adminroot, $parentname) {
        global $CFG;

        // Create a link to the page for configuring field mappings.
        $displaystring = get_string('configfieldstreelink', 'dhimport_version2');
        $url = $CFG->wwwroot.'/local/datahub/importplugins/version2/config_fields.php';
        $page = new \admin_externalpage("{$parentname}_fields", $displaystring, $url);

        // Add it to the tree.
        $adminroot->add($parentname, $page);
    }

    /**
     * Get the current import plugin.
     *
     * @return string The current plugin component name (i.e. "dhimport_version2").
     */
    protected function get_plugin() {
        return 'dhimport_version2';
    }

    /**
     * Construct an entity class for a given entity name.
     *
     * @param string $entity The name of the entity.
     * @return \dhimport_version2\entity\base|null The constructed class, or null if error.
     */
    protected function get_entity_instance($entity, $filename = '') {
        $class = '\dhimport_version2\entity\\'.$entity;
        if (class_exists($class) === true) {
            return new $class($filename, $this->dblogger, $this->fslogger);
        }
        return null;
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
        $entities = $this->get_import_entities();
        foreach ($entities as $entity) {
            if (in_array($entity.'action', $header, true) === true) {
                $instance = $this->get_entity_instance($entity, $filename);
                if (!empty($instance)) {
                    $this->entityobj = $instance;
                    return true;
                }
            }
        }
        return false;
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
        return $this->entityobj->validate_headers($header);
    }

    /**
     * Entry point for processing a single record.
     *
     * @param string $entity The type of entity.
     * @param \stdClass $record One record of import data.
     * @param string $filename Import file name to user for logging.
     * @return bool True on success, otherwise false.
     */
    protected function process_record($entity, $record, $filename) {
        return $this->entityobj->process_record($record, $this->linenumber);
    }

    /**
     * A hook to modify the import entities.
     *
     * @param array $entities The entities passed to the run method.
     * @param int $targetstarttime The timestamp for when this task was meant to be run.
     * @param int $lastruntime The last time the export was run. (N/A for import).
     * @param int $maxruntime The max time in seconds to complete import. (default/0 = unlimited).
     * @param object $state Previous ran state data to continue from.
     * @return array A modified entities array, if needed.
     */
    protected function hook_import_entities($entities, $targetstarttime, $lastruntime, $maxruntime, $state) {
        return ['any'];
    }

    /**
     * Obtain the file-system logger for this plugin.
     *
     * @param \stdClass $fileplugin The file plugin used for IO in the logger
     * @param bool $manual True on a manual run, false on a scheduled run
     * @return \rlip_import_version2_fslogger The appropriate logging object.
     */
    static function get_fs_logger($fileplugin, $manual) {
        return new \dhimport_version2\fslogger($fileplugin, $manual);
    }

}

