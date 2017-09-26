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

namespace dhimport_version2\page;

use \dhimport_version2\provider\queue as queueprovider;

/**
 * Ajax page.
 */
class ajax extends base {

    /** Error indicating input received was invalid. */
    const ERROR_BADINPUT = 'badinput';

    /** Error indicating operation cannot be performed unless queue in paused. */
    const ERROR_QUEUENOTPAUSED = 'queuenotpaused';

    /** Queue item was not found. */
    const ERROR_QUEUEITEMNOTFOUND = 'queueitemnotfound';

    /**
     * Hook function run before the main page mode.
     *
     * @return bool True.
     */
    public function header() {
        global $OUTPUT;
        echo $OUTPUT->header();
    }

    /**
     * Run a page mode.
     *
     * @param string $mode The page mode to run.
     */
    public function run($mode) {
        try {
            $this->header();
            $methodname = (!empty($mode)) ? 'mode_'.$mode : 'mode_default';
            if (!method_exists($this, $methodname)) {
                $methodname = 'mode_default';
            }
            $this->$methodname();
        } catch (\Exception $e) {
            echo $this->error_response($e->getMessage());
        }
    }

    /**
     * Build an error ajax response.
     *
     * @param mixed $data Wrapper for response data.
     * @param bool $success General success indicator.
     */
    protected function error_response($errormessage, $errorcode = '') {
        $result = new \stdClass;
        $result->success = false;
        $result->errorcode = $errorcode;
        $result->errormessage = $errormessage;
        return json_encode($result);
    }

    /**
     * Build a generic ajax response.
     *
     * @param mixed $data Wrapper for response data.
     * @param bool $success General success indicator.
     * @param array $extraparams Additional parameters to include as top-level parameters.
     */
    protected function ajax_response($data, $success = true, array $extraparams = []) {
        $result = new \stdClass;
        $result->success = $success;
        $result->data = $data;
        $baselink = new \moodle_url('/local/datahub/importplugins/version2/ajax.php');
        $result->baselink = (string)$baselink;
        foreach ($extraparams as $param => $val) {
            $result->$param = $val;
        }
        return json_encode($result);
    }

    /**
     * Get queue list.
     */
    protected function mode_getqueuelist() {
        global $DB;
        $output = [];
        $sql = 'SELECT q.id AS qid,
                       q.userid AS quserid,
                       q.filename AS qfilename,
                       q.status AS qstatus,
                       q.state AS qstate,
                       q.scheduledtime AS qscheduledtime,
                       u.*
                  FROM {'.queueprovider::QUEUETABLE.'} q
                  JOIN {user} u ON u.id = q.userid
              ORDER BY q.queueorder ASC';
        $params = [];
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            // Calculate various metadata.
            $type = ($record->qstatus == queueprovider::STATUS_PROCESSING) ? 'processing' : 'waiting';
            if (!empty($record->qscheduledtime) && $type == 'waiting') {
                $type = 'scheduled';
            }
            $status = get_string('queue_status_'.$type, 'dhimport_version2');
            if ($type === 'scheduled') {
                $scheduledtime = userdate($record->qscheduledtime);
                $reschedule = 1;
                $draggable = 0;
            } else {
                $scheduledtime = 0;
                $reschedule = 0;
                $draggable = 1;
            }

            // Unpack process progress.
            $recordstotal = 0;
            $recordscomplete = 0;
            if (!empty($record->qstate)) {
                $state = @unserialize($record->qstate);
                if (!empty($state)) {
                    if (isset($state->filelines)) {
                        $recordstotal = $state->filelines;
                    }
                    if (isset($state->linenumber)) {
                        $recordscomplete = $state->linenumber;
                    }
                }
            }
            $progress = ($recordstotal > 0) ? round(($recordscomplete/$recordstotal)*100) : 0;

            // Assemble the full output record.
            $toadd = [
                'id' => (int)$record->qid,
                'userid' => (int)$record->quserid,
                'user_fullname' => fullname($record),
                'filename' => $record->qfilename,
                'type' => $type,
                'status' => $status,
                'scheduled_time' => $scheduledtime,
                'progress' => $progress,
                'reschedule' => $reschedule,
                'draggable' => $draggable,
                'records_total' => $recordstotal,
                'records_complete' => $recordscomplete,
            ];
            $output[] = $toadd;
        }
        echo $this->ajax_response($output, true);
    }

    /**
     * Pause/unpause queue processing.
     */
    protected function mode_pausequeue() {
        $enabled = (bool)required_param('enabled', PARAM_BOOL);
        set_config('queuepaused', $enabled, 'dhimport_version2');
        $this->mode_getpausestate();
    }

    /**
     * Get the current pause state.
     */
    protected function mode_getpausestate() {
        $paused = (bool)get_config('dhimport_version2', 'queuepaused');
        echo $this->ajax_response(['state' => $paused], true);
    }

    /*
     * Reorder the queue list.
     */
    protected function mode_reorderqueue() {
        global $DB;

        $queuepaused = get_config('dhimport_version2', 'queuepaused');
        if (empty($queuepaused)) {
            $errmsg = get_string('queue_error_cannotreorderwhileunpaused', 'dhimport_version2');
            echo $this->error_response($errmsg, static::ERROR_QUEUENOTPAUSED);
            return false;
        }

        $order = required_param('order', PARAM_TEXT);
        $order = explode(',', $order);

        // Validate each item.
        foreach ($order as $id) {
            if (!is_numeric($id)) {
                $errmsg = get_string('queue_error_badidforreorder', 'dhimport_version2');
                echo $this->error_response($errmsg, static::ERROR_BADINPUT);
                return false;
            }
        }

        // Do reordering.
        $queueorder = 0;
        foreach ($order as $id) {
            $updateobj = (object)['id' => $id, 'queueorder' => $queueorder];
            $DB->update_record(queueprovider::QUEUETABLE, $updateobj);
            $queueorder++;
        }

        // Return the queue list. In the correct order.
        $this->mode_getqueuelist();
    }

    /**
     * Get completed queue items.
     */
    protected function mode_getcompleted() {
        global $DB;
        $start = optional_param('start', 0, PARAM_INT);
        $end = optional_param('end', 0, PARAM_INT);
        $select = '(status = ? OR status = ?)';
        $params = [queueprovider::STATUS_FINISHED, queueprovider::STATUS_ERRORS];
        $extra = [];
        if (!empty($start)) {
            $select .= ' AND timecompleted >= ?';
            $params[] = $start;
            $extra['start'] = $start;
        }
        if (!empty($end)) {
            $select .= ' AND timecompleted <= ?';
            $params[] = $end;
            $extra['end'] = $end;
        }
        $output = [];
        $order = 'timecompleted DESC';
        $completedrecords = $DB->get_recordset_select(queueprovider::QUEUETABLE, $select, $params, $order);
        foreach ($completedrecords as $record) {
            $statusstr = ($record->status == queueprovider::STATUS_FINISHED)
                ? get_string('queue_status_complete', 'dhimport_version2')
                : get_string('queue_status_errors', 'dhimport_version2');
            $output[] = [
                'id' => $record->id,
                'filename' => $record->filename,
                'status' => $statusstr,
                'date_completed' => userdate($record->timecompleted),
            ];
        }
        echo $this->ajax_response($output, true, $extra);
    }

    /**
     * Cancel a queue item.
     */
    protected function mode_cancelitem() {
        global $DB;
        $itemid = required_param('itemid', PARAM_INT);
        $record = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $itemid]);
        if (!empty($record)) {
            $DB->delete_records(queueprovider::QUEUETABLE, ['id' => $record->id]);
            echo $this->ajax_response(null, true);
        } else {
            $errmsg = get_string('queue_error_itemnotfound', 'dhimport_version2');
            echo $this->error_response($errmsg, static::ERROR_QUEUEITEMNOTFOUND);
        }
    }

}
