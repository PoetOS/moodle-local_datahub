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

    if ($result && $oldversion < 2016120501) {
        if (!$dbman->table_exists('dhimport_version2_mapping')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__.'/install.xml', 'dhimport_version2_mapping');
        }
        upgrade_plugin_savepoint($result, '2016120501', 'dhimport', 'version2');
    }

    if ($result && $oldversion < 2016120502) {
        if (!$dbman->table_exists('dhimport_version2_queue')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__.'/install.xml', 'dhimport_version2_queue');
        }
        if (!$dbman->table_exists('dhimport_version2_log')) {
            $dbman->install_one_table_from_xmldb_file(__DIR__.'/install.xml', 'dhimport_version2_log');
        }
        upgrade_plugin_savepoint($result, '2016120502', 'dhimport', 'version2');
    }

    if ($result && $oldversion < 2016120504) {
        $data = [
            'plugin' => 'dhimport_version2',
            'label' => 'Queue process',
            'recurrencetype' => 'period',
            'period' => '5m',
            'schedule' => ['period' => '5m'],
            'timemodified' => time(),
        ];
        rlip_schedule_add_job($data);
        upgrade_plugin_savepoint($result, '2016120504', 'dhimport', 'version2');
    }

    if ($result && $oldversion < 2016120505) {
        $table = new xmldb_table('dhimport_version2_queue');
        $field = new xmldb_field('state', XMLDB_TYPE_TEXT, null, null, null, null, null, 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint($result, '2016120505', 'dhimport', 'version2');
    }

    if ($result && $oldversion < 2016120506) {
        $table = new xmldb_table('dhimport_version2_queue');
        $field = new xmldb_field('queueorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Set the initial values for the order.
            $order = 0;
            $queuerecords = $DB->get_records('dhimport_version2_queue', null, 'id ASC');
            foreach ($queuerecords as $record) {
                $newrecord = (object)['id' => $record->id, 'queueorder' => $order];
                $DB->update_record('dhimport_version2_queue', $newrecord);
                $order++;
            }
        }
        upgrade_plugin_savepoint($result, '2016120506', 'dhimport', 'version2');
    }

    return $result;
}
