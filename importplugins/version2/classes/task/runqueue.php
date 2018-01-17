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
 * @copyright (C) 2017 Remote-Learner.net Inc (http://www.remote-learner.net)
 */

namespace dhimport_version2\task;

/**
 * Scheduled task to run the queue.
 */
class runqueue extends \core\task\scheduled_task {
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_runqueue', 'dhimport_version2');
    }

    /**
     * Attempt token refresh.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/datahub/lib.php');
        require_once($CFG->dirroot.'/local/datahub/lib/rlip_dataplugin.class.php');
        require_once($CFG->dirroot.'/local/datahub/lib/rlip_fileplugin.class.php');
        require_once($CFG->dirroot.'/local/datahub/lib/rlip_importprovider_csv.class.php');

        // Construct the import plugin.
        $fcnname = 'dhimport_version2_runqueue';
        $plugin = 'dhimport_version2';
        $type = 'dhimport';
        $userid = null;
        $state = get_config('dhimport_version2', 'queuestate');
        $state = (!empty($state)) ? unserialize($state) : null;
        $instance = rlip_get_run_instance($fcnname, $plugin, $type, $userid, $state);

        if (empty($instance)) {
            mtrace('Could not construct dhimport_version2 import plugin in runqueue task');
            return false;
        }

        // See how many records to process.
        $totalrecords = $DB->count_records('dhimport_version2_queue');

        // Run the job.
        $targetstarttime = time();
        $lastruntime = $this->get_last_run_time();
        $maxruntime = (isset($CFG->dhimport_version2_queuemaxruntime))
                ? $CFG->dhimport_version2_queuemaxruntime
                : IP_SCHEDULE_TIMELIMIT;
        $newstate = $instance->run($targetstarttime, $lastruntime, $maxruntime, $state);

        // Save state.
        $newstate = (!empty($newstate)) ? serialize($newstate) : '';
        $state = set_config('queuestate', $newstate, 'dhimport_version2');

        if ($totalrecords > '0') {
            // Potentially many DB updates, so clear caches.
            \core\task\manager::clear_static_caches();
        }
        return true;
    }
}
