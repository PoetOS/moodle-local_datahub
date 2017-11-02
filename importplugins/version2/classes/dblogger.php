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
 * Import queue. This class overrides provider claass and ensures queue status is maintained.
 *
 * @package    dhimport_importqueue
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace dhimport_version2;

use \dhimport_version2\provider\queue as queueprovider;

require_once($CFG->dirroot.'/local/datahub/lib/rlip_fslogger.class.php');

/**
 * Logger which saves messages to importqueuelog.
 */
class dblogger extends \dhimport_version2\fslogger {
    /** @var int $queueid Queue id of import. */
    protected $queueid = 0;

    /**
     * Filesystem logger constructor.
     *
     * @param int $queueid Id of queued import.
     */
    public function __construct($queueid) {
        $this->queueid = $queueid;
        $this->fileplugin = null;
        $this->manual = false;
    }

    /**
     * API hook for customizing the contents for a file-system log line / record
     *
     * @param string $message The message to long
     * @param int $timestamp The timestamp to associate the message with, or 0 for the current time
     * @param string $filename The name of the import / export file we are reporting on
     * @param int $entitydescriptor A descriptor of which entity from an import file we are handling, if applicable
     * @param boolean $success True if the operation was a success, otherwise false
     */
    public function customize_record($message, $timestamp = 0, $filename = NULL, $entitydescriptor = NULL, $success = false) {
        return $message;
    }

    /**
     * Log a message to the log file - used internally only (use log_success or
     * log_failure instead for external calls).
     *
     * @param string $message The message to long.
     * @param int $timestamp The timestamp to associate the message with, or 0 for the current time.
     * @param string $filename The name of the import / export file we are reporting on.
     * @param int $line Line number in file.
     * @param bool $success True if the operation was a success, otherwise false.
     * @return bool True if the operation was a success, otherwise false.
     */
    protected function log($message, $timestamp = 0, $filename = null, $line = null, $success = false) {
        global $CFG, $DB;
        $message = $this->customize_record($message, $timestamp, $filename, $line, $success);

        if (empty($timestamp)) {
            // Default to current time if time not specified.
            $timestamp = time();
        }

        $logentry = new \stdClass();
        $logentry->timecreated = $timestamp;
        $logentry->message = $message;
        $logentry->line = $line;
        $logentry->queueid = $this->queueid;
        if (empty($filename)) {
            $filename = $this->queueid;
        }
        $logentry->filename = $filename;
        $logentry->queueid = $this->queueid;
        if ($success) {
            $logentry->status = 1;
        } else {
            $logentry->status = 0;
        }
        $DB->insert_record(queueprovider::LOGTABLE, $logentry);
        return true;
    }

    /**
     * Perform any cleanup that the logger needs to do.
     */
    public function close() {
        // Overriden to prevent closing of file handle that does not exist.
    }
}

