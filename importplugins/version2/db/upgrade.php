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

defined('MOODLE_INTERNAL') || die();


function xmldb_dhimport_version2_upgrade($oldversion=0) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/local/datahub/lib.php');

    $dbman = $DB->get_manager();
    $result = true;
    if ($oldversion < 2017111300.01) {
       $table = new xmldb_table('dhimport_version2_log');
       $field = new xmldb_field('message',XMLDB_TYPE_TEXT, null, null, null, null, null);
       $dbman->change_field_type($table, $field);
       upgrade_plugin_savepoint(true, 2017111300.01, 'local', 'dhimport_version2');
    }
    return $result;
}
