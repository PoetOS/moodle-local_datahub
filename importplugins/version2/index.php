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

require_once('../../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$PAGE->set_url('/local/datahub/importplugins/version2/');
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_heading($SITE->fullname);
$pagetitle = get_string('version2header', 'dhimport_version2');
$PAGE->set_title($pagetitle);
$PAGE->set_pagelayout('standard');
$PAGE->navbar->add($pagetitle);
$PAGE->add_body_class("datahub-version2");
$PAGE->requires->css('/local/datahub/styles.css');

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('version2header', 'dhimport_version2'));

// Start tabs UI.
$tabs = array();

// Define tab constants.
if (!defined('DHIMPORT_VERSION2_TAB_IMPORT')) {
    // DHIMPORT_VERSION2_TAB_IMPORT - Order/link reference for import tab.
    define('DHIMPORT_VERSION2_TAB_IMPORT', 1);

    // DHIMPORT_VERSION2_TAB_QUEUE - Order/link reference for queue tab.
    define('DHIMPORT_VERSION2_TAB_QUEUE', 2);

    // DHIMPORT_VERSION2_TAB_SETTINGS - Order/link reference for settings tab.
    define('DHIMPORT_VERSION2_TAB_SETTINGS', 3);
}

$section = DHIMPORT_VERSION2_TAB_IMPORT;
$url = $CFG->wwwroot.'/local/datahub/importplugins/version2/?section='.$section;
$tab = new tabobject($section, $url, get_string('version2importtab', 'dhimport_version2'));
$tabs[0][] = $tab;

$section = DHIMPORT_VERSION2_TAB_QUEUE;
$url = $CFG->wwwroot.'/local/datahub/importplugins/version2/?section='.$section;
$tab = new tabobject($section, $url, get_string('version2queuetab', 'dhimport_version2'));
$tabs[0][] = $tab;

$section = DHIMPORT_VERSION2_TAB_SETTINGS;
$url = $CFG->wwwroot.'/local/datahub/importplugins/version2/?section='.$section;
$tab = new tabobject($section, $url, get_string('version2settingstab', 'dhimport_version2'));
$tabs[0][] = $tab;

$selectedtab = optional_param('section', 0, PARAM_INT);
if (!$selectedtab) {
    $selectedtab = DHIMPORT_VERSION2_TAB_IMPORT;
}

echo print_tabs($tabs, $selectedtab, null, null, true);
// End tabs UI.

switch ($selectedtab) {
    case DHIMPORT_VERSION2_TAB_IMPORT:
        require_once('includes/import.php');
        break;
    case DHIMPORT_VERSION2_TAB_QUEUE:
        include_once('includes/queue.php');
        break;
    case DHIMPORT_VERSION2_TAB_SETTINGS:
        // TODO: Settings tab content here.
        break;
}

echo $OUTPUT->footer();
