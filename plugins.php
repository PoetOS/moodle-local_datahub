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

require_once('../../config.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_dataplugin.class.php');

//permissions checking
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

//header
admin_externalpage_setup('rlipsettingplugins');
$PAGE->requires->css('/local/datahub/styles.css');
echo $OUTPUT->header();

//the types of plugins we are considering
$plugintypes = array('dhimport', 'dhexport');
//lookup for the directory paths for plugins
$directories = core_component::get_plugin_types();

foreach ($plugintypes as $plugintype) {
    //plugin header
    echo $OUTPUT->box_start('generalbox pluginspageheading');
    print_string("{$plugintype}plugins", 'local_datahub');
    echo $OUTPUT->box_end();

    //initialize table
    $table = new html_table();
    $table->head = array(get_string('name'), get_string('settings'), get_string('schedule'), get_string('runmanually', 'local_datahub'));
    $table->align = array('left', 'center', 'center', 'center');
    $table->size = array('60%', '13%', '13%', '13%');
    $table->data = array();
    $table->width = '40%';

    //obtain plugins and iterate through them
    $plugins = core_component::get_plugin_list($plugintype);
    //base directory
    $directory = $directories[$plugintype];
    $directory = str_replace($CFG->dirroot, $CFG->wwwroot, $directory);

    foreach ($plugins as $name => $path) {
        //skip plugins used for testing only / ones that are not available
        $instance = rlip_dataplugin_factory::factory("{$plugintype}_{$name}");
        if (!$instance->is_available()) {
            continue;
        }

        //get the display name from the plugin-specific language string
        $displayname = get_string('pluginname', "{$plugintype}_{$name}");

        //configuration link
        $url = $CFG->wwwroot."/admin/settings.php?section=rlipsetting{$plugintype}_{$name}";
        $attributes = array('href' => $url);
        $config_tag = html_writer::tag('a', get_string('edit'), $attributes);

        // Schedule link.
        if ($plugintype.'_'.$name === 'dhimport_version2') {
            $sched_tag = '';
        } else {
            $url = $CFG->wwwroot."/local/datahub/schedulepage.php?plugin={$plugintype}_{$name}&action=list";
            $attributes = array('href' => $url);
            $sched_tag = html_writer::tag('a', get_string('managesched', 'local_datahub'), $attributes);
        }

        // Manual run link.
        $url = $instance->get_manualrun_url();
        $attributes = ['href' => $url];
        $run_tag = html_writer::tag('a', get_string('go', 'local_datahub'), $attributes);

        //combine into row data
        $table->data[] = array($displayname, $config_tag, $sched_tag, $run_tag);
    }

    //output the table
    echo html_writer::table($table);
}

// ELIS-9084: DH logs link.
echo $OUTPUT->box_start('generalbox');
echo html_writer::tag('a', get_string('logs', 'local_datahub'), array(
    'href' => $CFG->wwwroot.'/local/datahub/viewlogs.php'));
echo $OUTPUT->box_end();

//footer
echo $OUTPUT->footer();
