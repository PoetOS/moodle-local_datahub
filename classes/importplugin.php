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

namespace local_datahub;
use \local_datahub\dblogger;
use \local_datahub\fslogger;

/**
 * Public interface for import plugins.
 */
interface importplugin {
    /**
     * Import plugin constructor.
     *
     * @param object $provider The import file provider that will be used to obtain import files.
     * @param boolean $manual Set to true if a manual run.
     */
    public function __construct($provider = null, $manual = false);

    /**
     * Get the URL for a manual run.
     *
     * @return string The URL for a manual run.
     */
    public function get_manualrun_url();

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
    public function run($targetstarttime = 0, $lastruntime = 0, $maxruntime = 0, $state = null);

    /**
     * Set the class fslogger.
     *
     * @param fslogger $fslogger The fslogger object to set.
     */
    public function set_fslogger(fslogger $fslogger);

    /**
     * Get the set fslogger.
     *
     * @return fslogger The set fslogger, or null if none set.
     */
    public function get_fslogger();

    /**
     * Set the class dblogger.
     *
     * @param dblogger $dblogger The dblogger object to set.
     */
    public function set_dblogger(dblogger $dblogger);

    /**
     * Get the set dblogger.
     *
     * @return dblogger The set dblogger, or null if none set.
     */
    public function get_dblogger();

    /**
     * Gets the list of entities that the import plugin supports.
     *
     * @return array An array of entity types
     */
    public function get_import_entities();

    /**
     * Get the available fields for a given entity type.
     *
     * @param string $entitytype The entity type.
     * @return array Array of available fields for that entity.
     */
    public function get_available_fields($entitytype);
}

