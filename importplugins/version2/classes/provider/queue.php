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
 * @package    dhimport_version2
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Remote Learner.net Inc http://www.remote-learner.net
 */

namespace dhimport_version2\provider;

require_once($CFG->dirroot.'/local/datahub/lib/rlip_fslogger.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_importplugin.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_fileplugin.class.php');

class queue extends \rlip_importprovider {
    /** @var int $queueid id of queue currently being processed. */
    protected $queueid = 0;

    /** @var array Array of files to process. At the moment, only holds one. */
    protected $files = [];

    /** Queue entry is queued. */
    const STATUS_QUEUED = 0;

    /** Queue entry is finished. */
    const STATUS_FINISHED = 1;

    /** Queue entry has errors. */
    const STATUS_ERRORS = 2;

    /** Queue entry is processing. */
    const STATUS_PROCESSING = 3;

    /** Queue entry is scheduled. */
    const STATUS_SCHEDULED = 4;

    /** The table that holds the queue. */
    const QUEUETABLE = 'dhimport_version2_queue';

    /** The table that holds the logs. */
    const LOGTABLE = 'dhimport_version2_log';

    /**
     * Constructor.
     */
    public function __construct() {
        global $DB;

        // Check in progress import. 0 = queued, 1 = finished, 2 = finished with errors, 3 = processing.
        $params = ['status' => static::STATUS_PROCESSING];
        $queue = $DB->get_records(static::QUEUETABLE, $params, 'queueorder asc');
        if (!empty($queue)) {
            $current = reset($queue);
            /*
                Something went wrong when processing this queue.
                The file was marked as processing, but it did not finish gracefully
                and update its status to errors.
            */
            $current->status = static::STATUS_ERRORS;
            $DB->update_record(static::QUEUETABLE, $current);
        }

        // Check for scheduled items first.
        $select = 'status = ? AND scheduledtime <= ?';
        $params = [static::STATUS_SCHEDULED, time()];
        $queue = $DB->get_records_select(static::QUEUETABLE, $select, $params, 'queueorder asc', '*', 0, 1);
        if (!empty($queue)) {
            $next = reset($queue);
            $this->queueid = $next->id;
            $this->files = $this->build_files($this->queueid);
        } else {
            // Nothing is currently being processed, checking for unprocessed.
            $params = ['status' => static::STATUS_QUEUED];
            $queue = $DB->get_records(static::QUEUETABLE, $params, 'queueorder asc');
            if (!empty($queue)) {
                $next = reset($queue);
                $this->queueid = $next->id;
                $this->files = $this->build_files($this->queueid);
            }
        }
    }

    /**
     * Build files array for provider.
     *
     * @param int $queueid Id of queue.
     * @return array Array of files, empty array if files do not exist.
     */
    public function build_files($queueid) {
        global $CFG;
        $schedulefilespath = get_config('dhimport_version2', 'schedule_files_path');
        if ($schedulefilespath[0] !== '/') {
            $schedulefilespath = '/'.$schedulefilespath;
        }
        $directory = $CFG->dataroot.$schedulefilespath.'/';
        $filename = $queueid.'.csv';
        return [$directory.$filename];
    }

    /**
     * Get current queue id provider is processing.
     *
     * @return int Id of queue.
     */
    public function get_queueid() {
        return $this->queueid;
    }

    /**
     * Return loggger which saves message to the database.
     *
     * @return object importqueuedblogger class.
     */
    public function get_fslogger($plugin, $entity = '', $manual = false, $starttime = 0) {
        return new \dhimport_version2\dblogger($this->queueid);
    }

    /**
     * Hook for providing a file plugin for a particular import entity type.
     *
     * @param string $entity The type of entity.
     * @return object The file plugin instance, or false if not applicable.
     */
    public function get_import_file($entity = null) {
        global $CFG;
        if (!empty($this->files) && file_exists($this->files[0])) {
            return \rlip_fileplugin_factory::factory($this->files[0]);
        }
        return false;
    }

}
