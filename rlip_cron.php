<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_datahub
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2015 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

// External Datahub 'cron' processing file
define('CLI_SCRIPT', 1);

require_once(dirname(__FILE__) .'/../../config.php');
require_once($CFG->dirroot .'/local/eliscore/lib/tasklib.php');
require_once($CFG->dirroot .'/local/datahub/lib.php');
require_once($CFG->dirroot .'/local/datahub/lib/rlip_dataplugin.class.php');
require_once($CFG->dirroot .'/local/datahub/lib/rlip_fileplugin.class.php');
require_once($CFG->dirroot .'/local/datahub/lib/rlip_importprovider_csv.class.php');

$filename = basename(__FILE__);
$disabledincron = !empty($CFG->forcedatahubcron) || get_config('local_datahub', 'disableincron');
$upgraderunning = get_config(null, 'upgraderunning');
if (!empty($upgraderunning) || empty($disabledincron)) {
    exit(0);
}

global $USER;
$USER = get_admin();

$rlipshortname = 'DH';

// TBD: adjust some php variables for the execution of this script
set_time_limit(0);
@ini_set('max_execution_time', '3000');
if (empty($CFG->extramemorylimit)) {
    raise_memory_limit('128M');
} else {
    raise_memory_limit($CFG->extramemorylimit);
}

mtrace($rlipshortname.' external cron start - Server Time: '. date('r', time()) ."\n");

$pluginstorun = array('dhimport', 'dhexport');

$timenow = time();
$params = array('timenow' => $timenow);
$tasks = $DB->get_recordset_select('local_eliscore_sched_tasks', 'nextruntime <= :timenow', $params, 'nextruntime ASC');
if ($tasks && $tasks->valid()) {
    foreach ($tasks as $task) {
        // Make sure we have an import/export task
        $taskparts = explode('_', $task->taskname);
        if (count($taskparts) < 2 || $taskparts[0] !== 'ipjob') {
            continue;
        }
        $id = $taskparts[1];

        // Get ipjob from ip_schedule
        $ipjob = $DB->get_record(RLIP_SCHEDULE_TABLE, array('id' => $id));
        if (empty($ipjob)) {
            mtrace("{$filename}: DB Error retrieving {$rlipshortname} schedule record for taskname '{$task->taskname}' - aborting!");
            continue;
        }

        // validate plugin
        $plugin = $ipjob->plugin;

        // Import plugin dhimport_version2 uses a regular scheduled task.
        if ($plugin === 'dhimport_version2') {
            mtrace('Skipping ELIS scheduled task for dhimport_version2 because it uses a regular scheduled task.');
            continue;
        }

        $plugparts = explode('_', $plugin);
        if (!in_array($plugparts[0], $pluginstorun)) {
            mtrace("{$filename}: {$rlipshortname} plugin '{$plugin}' not configured to run externally - aborting!");
            continue;
        }

        $rlip_plugins = core_component::get_plugin_list($plugparts[0]);
        //print_object($rlip_plugins);
        if (!array_key_exists($plugparts[1], $rlip_plugins)) {
            mtrace("{$filename}: {$rlipshortname} plugin '{$plugin}' unknown!");
            continue;
        }

        mtrace("{$filename}: Processing external cron function for: {$plugin}, taskname: {$task->taskname} ...");

        //determine the "ideal" target start time
        $targetstarttime = $ipjob->nextruntime;

        // Set the next run time & lastruntime
        //record last runtime
        $lastruntime = $ipjob->lastruntime;

        $data = unserialize($ipjob->config);
        $state = isset($data['state']) ? $data['state'] : null;
        $nextruntime = cron_next_run_time($targetstarttime, (array)$task);
        $task->nextruntime = $nextruntime;
        $task->lastruntime = $timenow;
        $DB->update_record('local_eliscore_sched_tasks', $task);

        //update the next runtime on the ip schedule record
        $ipjob->nextruntime = $task->nextruntime;
        $ipjob->lastruntime = $timenow;
        unset($data['state']);
        $ipjob->config = serialize($data);
        $DB->update_record(RLIP_SCHEDULE_TABLE, $ipjob);

        $instance = rlip_get_run_instance($filename, $plugin, $plugparts[0],
                                          $ipjob->userid, $state);
        if ($instance == null) {
            continue;
        }

        $instance->run($targetstarttime, $lastruntime, 0, $state);
        //^TBD: since this should run 'til complete, no state should be returned
    }
}

mtrace("\n{$rlipshortname} external cron end - Server Time: ". date('r', time()) ."\n\n");

// end of file
