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
 *
 * @package    dhimport_version2
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2017 Remote Learner.net Inc http://www.remote-learner.net
 */

require_once('../../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_login();

$file = required_param('file', PARAM_TEXT);
$fields = required_param('fields', PARAM_RAW);
$fields = json_decode($fields);

// Get the CSV data.
if (($csvhandle = fopen("./templates/csv/".$file, "r")) !== false) {
    $row = 0;
    $csvdata = array();
    while (($data = fgetcsv($csvhandle)) !== false) {
        $csvdata[$row] = $data;
        $row++;
    }
    fclose($csvhandle);
}

// Iterate over CSV fields and get only the fields and data that was selected.
$selectedfields = array();
$selecteddata = array();
$sampledata = array();
for ($i=0; $i<count($csvdata[0]); $i++) {
    $header = $csvdata[0][$i];
    if (isset($fields->{$header}) && $fields->{$header} === 1) {
        if (substr($header, 0, 1) == "*") {
            $header = substr($header, 1);
        }
        $selectedfields[] = $header;
        if (isset($csvdata[1][$i])) {
            $selecteddata[] = $csvdata[1][$i];
            $sampledata[] = $csvdata[2][$i];
       } else {
            $selecteddata[] = '';
            $sampledata[] = '';
        }
    }
}

header('Content-Disposition: attachment; filename="dhtemplate_'.$file.'"');
echo implode(",", $selectedfields);
echo "\n";
echo implode(",", $selecteddata);
echo "\n";
echo implode(",", $sampledata);