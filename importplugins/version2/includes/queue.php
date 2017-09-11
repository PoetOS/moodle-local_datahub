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

require_once('../../../../config.php');

// Permissions.
require_login(null, false);

// CSS and JS.
$stringman = get_string_manager();
$strings = $stringman->load_component_strings('local_datahub', 'en');
$PAGE->requires->strings_for_js(array_keys($strings), 'local_datahub');
$sesskey = sesskey();
$args = array($sesskey);
$PAGE->requires->js_call_amd('local_datahub/queue', 'init', $args);
// Print queue table.
$output = $PAGE->get_renderer('local_datahub');
$queue = new \local_datahub\output\queue_table(array());
echo $output->render($queue);
// Print completed table.
$completed = new \local_datahub\output\completed_table(array());
echo $output->render($completed);

