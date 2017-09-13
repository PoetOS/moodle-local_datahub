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
 * @package    dhimport_version2
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2017 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once($CFG->dirroot.'/lib/adminlib.php');
require_once($CFG->dirroot.'/local/datahub/importplugins/version2/form/import_form.class.php');

$form = new version2_import_form(null, ['Import file']);
if ($form->is_submitted() && $form->is_validated()) {
    $queueid = $form->process();
    if (!empty($queueid)) {
        $queuetab = $CFG->wwwroot.'/local/datahub/importplugins/version2/?section='.DHIMPORT_VERSION2_TAB_QUEUE;
        echo html_writer::tag('div', get_string('queueaddsuccess', 'dhimport_version2', $queuetab),
            array('class' => 'dhimport_version2_alert alert alert-block alert-info'));
    }
}

// CSV template builder section.
echo html_writer::tag('h3', get_string('csvtemplateheader', 'dhimport_version2'));
echo html_writer::tag('p', get_string('csvtemplatedesc', 'dhimport_version2'));
echo html_writer::tag('a', get_string('csvtemplatebtn', 'dhimport_version2'),
    array('id' => 'csvtemplatelauncher', 'class' => 'btn', 'href' => '#csvtemplatelauncher'));

/*** START CSV Template builder modal. ***/
echo html_writer::start_tag('div', array('id' => 'csvmodal'));
echo html_writer::start_tag('div', array('id' => 'csvtemplatebuilder'));
$csvformparams = array('id' => 'csvbuilder', 'action' => $CFG->wwwroot.'/local/datahub/importplugins/version2/csvtemplate.php',
    'target' => '_blank', 'method' => 'POST');
echo html_writer::start_tag('form', $csvformparams);

// Start template type select.
echo html_writer::start_tag('div', array('class' => 'csvheader'));
echo html_writer::tag('h4', get_string('csvtemplatetypelabel', 'dhimport_version2'));

$templatetypes = html_writer::tag('option', get_string('csvtemplatetypechoose', 'dhimport_version2'), array('value' => ''));
// Dynamically get CSV templates from /templates/csv directory.
$csvfiletypes = [];
if ($csvfilehandle = opendir('./templates/csv/')) {
    while (false !== ($file = readdir($csvfilehandle))) {
        if (preg_match('/^.*\.csv$/i', $file)) {
            $csvfiletypes[] = $file;
            $optionstr = ucfirst(str_replace('.csv', '', $file));
            $templatetypes .= html_writer::tag('option', $optionstr, array('value' => $file));
        }
    }
    closedir($csvfilehandle);
}
echo html_writer::tag('select', $templatetypes, array('id' => 'csvtemplatetypeselect', 'name' => 'csvtemplatetypeselect'));
echo html_writer::tag('p', get_string('csvtemplateinstructions', 'dhimport_version2'));
echo html_writer::end_tag('div');
// End template type select.

// Start left field select area.
echo html_writer::start_tag('div', array('class' => 'fieldselect'));
echo html_writer::tag('h4', get_string('csvincludedfieldslabel', 'dhimport_version2'));
echo html_writer::tag('select', '', array('name' => 'removeselect', 'id' => 'removeselect', 'size' => 20, 'multiple' => 'true'));
echo html_writer::tag('div', get_string('csvrequiredfieldsnote', 'dhimport_version2'));
echo html_writer::end_tag('div'); // End .fieldselect.
// End left field select area.

// Start middle add/remove buttons area.
echo html_writer::start_tag('div', array('class' => 'fieldbuttons'));
echo html_writer::start_tag('p', array('class' => 'arrow_button'));
$addattributes = array('name' => 'addcsvfield', 'id' => 'addcsvfield', 'type' => 'submit',
    'value' => $OUTPUT->larrow().' '.get_string('add'), 'title' => get_string('add'), 'disabled' => 'disabled');
echo html_writer::tag('button', $OUTPUT->larrow().' '.get_string('add'), $addattributes);
$removeattributes = array('name' => 'removecsvfield', 'id' => 'removecsvfield', 'type' => 'submit',
    'value' => get_string('remove').' '.$OUTPUT->rarrow(), 'title' => get_string('remove'), 'disabled' => 'disabled');
echo html_writer::tag('button', get_string('remove').' '.$OUTPUT->rarrow(), $removeattributes);
echo html_writer::end_tag('p'); // End .arrow_button.
echo html_writer::end_tag('div'); // End .fieldbuttons.
// End middle add remove buttons area.

// Start right field select area.
echo html_writer::start_tag('div', array('class' => 'fieldselect'));
echo html_writer::tag('h4', get_string('csvavailablefieldslabel', 'dhimport_version2'));
echo html_writer::tag('select', '', array('name' => 'addselect', 'id' => 'addselect', 'size' => 20, 'multiple' => 'true'));
echo html_writer::tag('div', '&nbsp;');
echo html_writer::end_tag('div'); // End .fieldselect.
// End right field select area.

echo html_writer::empty_tag('input', array('name' => 'fields', 'type' => 'hidden', 'value' => ''));
echo html_writer::empty_tag('input', array('name' => 'file', 'type' => 'hidden', 'value' => ''));
$formsubmitparams = array('type' => 'submit', 'value' => get_string('downloadcsvtemplate', 'dhimport_version2'),
    'id' => 'csvtemplatedownload', 'disabled' => 'disabled');
echo html_writer::empty_tag('input', $formsubmitparams);

echo html_writer::end_tag('form');
echo html_writer::end_tag('div'); // End #csvtemplatebuilder.
echo html_writer::end_tag('div'); // End #csvmodal.
/*** END CSV template builder modal. ***/

// CSV builder JS.
$args = array();
$PAGE->requires->js_call_amd('local_datahub/csvtemplatebuilder', 'initialize', $args);

/*** START File upload and schedular. ***/
echo html_writer::tag('h3', get_string('uploadheader', 'dhimport_version2'));
echo html_writer::tag('p', get_string('uploaddesc', 'dhimport_version2'));
echo html_writer::start_tag('div', array('id' => 'uploadformwrapper'));
$plugin = 'dhimport_version2';
$form->set_data(array('plugin' => $plugin));

$PAGE->requires->js_call_amd('local_datahub/importfilehandler', 'init', array(json_encode($csvfiletypes)));

// Strings required for CSV template builder JS.
$strings = array('validationerrorheader', 'validationerrorunknowntype', 'validationerrorimporttype',
    'validationerrormissingrequired', 'validationerrorunknownfields');
$PAGE->requires->strings_for_js($strings, 'dhimport_version2', '');

// Display the form.
$form->display();

// Display time zone message.
echo html_writer::tag('div', get_string('timezonenote', 'dhimport_version2'), array('id' => 'timezonenote'));
echo html_writer::end_tag('div');

/*** END File upload and schedular. ***/