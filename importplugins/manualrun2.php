<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

require_once('../../../config.php');
require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot.'/local/datahub/lib.php');
require_once($CFG->dirroot.'/local/datahub/form/rlip_manualimport_form.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_dataplugin.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_importprovider_moodlefile.class.php');

// Permissions checking.
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

// Determine which plugin we're using.
$plugin = 'dhimport_version2';

// Need base URL for form and Moodle block management.
$baseurl = new \moodle_url('/local/datahub/importplugins/manualrun2.php?plugin='.$plugin);

// Header.
$plugindisplay = get_string('pluginname', $plugin);
rlip_manualrun_page_setup($baseurl, $plugindisplay);
echo $OUTPUT->header();

// Init a JS listener to check file size of uploads.
$schedulingurl = new \moodle_url('/local/datahub/schedulepage.php?plugin=dhimport_version1&action=list');
$args = array($schedulingurl);
$PAGE->requires->js_call_amd('local_datahub/manualrun', 'initialize', $args);

// Add a warning message for all imports.
echo write_manual_import_warning($schedulingurl);

// Need to get number of different files.
$instance = rlip_dataplugin_factory::factory($plugin);

// Create our basic form.
$form = new rlip_manualimport_form(null, ['Import file']);
$form->set_data(array('plugin' => $plugin));

// Need to collect the ids of the important files.
$fileids = array();

if ($data = $form->get_data()) {
    // Process each uploaded file, moving it out of "draft" space.
    $fileid = rlip_handle_file_upload($data, 'file0');

    // Run the entire import once.
    $importprovider = new rlip_importprovider_moodlefile(['any'], [$fileid]);
    // Indicate to the factory class that this is a manual run.
    $manual = true;
    $instance = rlip_dataplugin_factory::factory($plugin, $importprovider, null, $manual);
    $instance->run(0, 0, rlip_get_maxruntime());
}

// Display the form.
$form->display();

// Footer.
echo $OUTPUT->footer();

/*
Add a modal dialog for people who insist on uploading larger CSV files.
This modal needs to come below the footer so that z-index works correctly for
both the modal and the modal background.
*/
echo write_manual_import_modal();
