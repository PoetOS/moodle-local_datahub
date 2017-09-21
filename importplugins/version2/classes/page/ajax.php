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
     */
    protected function ajax_response($data, $success = true) {
        $result = new \stdClass;
        $result->success = $success;
        $result->data = $data;
        return json_encode($result);
    }

    /**
     * Get queue list.
     */
    protected function mode_getqueuelist() {
        global $DB;
        $records = $DB->get_records(queueprovider::QUEUETABLE, null, 'id DESC');
        echo $this->ajax_response($records, true);
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

}
