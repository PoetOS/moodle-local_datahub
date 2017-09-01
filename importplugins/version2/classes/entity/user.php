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
    /**
     * Get the available fields for a given action.
     *
     * @param string $action The action we want fields for, or null for general list.
     * @return array An array of valid field names.
     */
    public function get_available_fields($action = null) {
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
        return $fields;
    }

    /**
     * Get a list of required fields for a given action.
     *
     * @param string $action The action we want fields for.
     * @return array|null An array of required field names, or null if not available for that action.
     */
    protected function get_required_fields($action = null) {
        return [];
    }

    /**
     * Validate file headers.
     *
     * @param array $headers An array of file headers.
     * @return bool True if valid, false if not valid.
     */
    public function validate_headers(array $headers) {
        $this->dblogger->signal_invalid_encoding('TEST. Version 2 successfully passed off header validation.');
        $this->dblogger->flush($this->filename);
        return true;
    }

    /**
     * Process a single record.
     *
     * @param \stdClass $record The record to process.
     * @param int $linenumber The line number of this record from the import file. Used for logging.
     * @return bool Success/Failure.
     */
    public function process_record(\stdClass $record, $linenumber) {
        $message = print_r($record, true);
        $this->dblogger->signal_invalid_encoding($message);
        $this->dblogger->flush($this->filename);
        return true;
    }
}

