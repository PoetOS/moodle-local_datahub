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

/**
 * Version 2 import plugin.
 */
class importplugin extends \local_datahub\importplugin_base {

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
     * Mainline for running the import
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

        $result = parent::run($targetstarttime, $lastruntime, $maxruntime, $state);

        if (!defined('PHPUnit_MAIN_METHOD')) {
            // Not in a unit test, so send out log files in a zip.
            $logids = $this->dblogger->get_log_ids();
            rlip_send_log_emails('dhimport_version2', $logids, $this->manual);
        }

        return $result;
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
}

