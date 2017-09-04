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

interface entityinterface {
    /**
     * Constructor.
     *
     * @param string $filename The name of the file being imported.
     * @param dblogger $dblogger A DB logger class for logging progress.
     * @param fslogger $fslogger An FS logger class for logging progress.
     */
    public function __construct($filename, dblogger $dblogger = null, fslogger $fslogger = null);

    /**
     * Validate file headers.
     *
     * @param array $headers An array of file headers.
     * @return bool True if valid, false if not valid.
     */
    public function validate_headers(array $headers);

    /**
     * Process a single record.
     *
     * @param \stdClass $record The record to process.
     * @param int $linenumber The line number of this record from the import file. Used for logging.
     * @return bool Success/Failure.
     */
    public function process_record(\stdClass $record, $linenumber);

    /**
     * Get the available fields for a given action.
     *
     * @param string $action The action we want fields for, or null for general list.
     * @return array An array of valid field names.
     */
    public function get_available_fields($action = null);

    /**
     * Get a list of required fields for a given action.
     *
     * @param string $action The action we want fields for.
     * @return array|null An array of required field names, or null if not available for that action.
     */
    public function get_required_fields($action = null);

    /**
     * Get a list of supported actions for this entity.
     *
     * @return array An array of support action names.
     */
    public function get_supported_actions();

    /**
     * Return any configured field maps.
     *
     * @return array Array of field maps in the form [{standard field name} => {custom field name}]
     */
    public function get_mappings();
}

