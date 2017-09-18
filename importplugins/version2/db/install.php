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

require_once(dirname(__FILE__).'/../lib.php');

function xmldb_dhimport_version2_install() {
    global $CFG, $DB;

    require_once($CFG->dirroot.'/local/datahub/lib.php');

    $result = true;
    $dbman = $DB->get_manager();

    $data = [
        'plugin' => 'dhimport_version2',
        'label' => 'Queue process',
        'recurrencetype' => 'period',
        'period' => '5m',
        'schedule' => ['period' => '5m'],
        'timemodified' => time(),
    ];
    rlip_schedule_add_job($data);

    return $result;
}
