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
 * @package    local_datahub
 * @copyright  Remote-Learner.net
 * @author     Amy Groshek <amy@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();

// Get view parameter.
$type = optional_param('type', 'refresh', PARAM_TEXT);
$id = optional_param('id', '', PARAM_TEXT);
$timestamp = optional_param('timestamp', '', PARAM_TEXT);
$start = optional_param('start', '', PARAM_TEXT);
$end = optional_param('end', '', PARAM_TEXT);

switch ($type) {
    case 'refresh':
        $json = file_get_contents('queue_test.json');
        sleep(3);
        header('Content-type: application/json');
        echo $json;
        break;
    case 'reschedule':
        $output = array('success' => true, 'timestamp' => $timestamp, 'id' => $id);
        // $output = array('success' => false, 'timestamp' => $timestamp, 'id' => $id);
        sleep(3);
        header('Content-type: application/json');
        echo json_encode($output);
        break;
    case 'pause':
        $output = array('success' => true);
        // $output = array('success' => false);
        sleep(3);
        header('Content-type: application/json');
        echo json_encode($output);
        break;
    case 'cancel':
        $output = array('success' => true, 'id' => $id);
        // $output = array('success' => false, 'id' => $id);
        sleep(3);
        header('Content-type: application/json');
        echo json_encode($output);
        break;
    case 'reorder':
        $output = array('success' => true);
        // $output = array('success' => false);
        sleep(3);
        header('Content-type: application/json');
        echo json_encode($output);
        break;
    case 'getcompleted':
        $json = file_get_contents('completed_test.json');
        // $output = array('success' => true);
        // $output = array('success' => false);
        sleep(3);
        // $output['start'] = $start;
        // $output['end'] = $end;
        // $output['courses']
        header('Content-type: application/json');
        echo $json;
        break;
}