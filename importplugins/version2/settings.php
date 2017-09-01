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

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/lib.php');

// Start of "data handling" section, along with link for configuring mapping.
$url = new \moodle_url('/local/datahub/importplugins/version2/config_fields.php');
$attributes = ['href' => $url, 'target' => '_blank'];

//
// User import settings.
//
$lang = get_string('userimports', 'dhimport_version2');
$settings->add(new admin_setting_heading('dhimport_version2/userimportsheader', $lang, null));
$settings->add(new admin_setting_configcheckbox('dhimport_version2/identfield_idnumber',
        get_string('identfield_idnumber', 'dhimport_version2'), '', 1));
$settings->add(new admin_setting_configcheckbox('dhimport_version2/identfield_username',
        get_string('identfield_username', 'dhimport_version2'), '', 1));
$settings->add(new admin_setting_configcheckbox('dhimport_version2/identfield_email',
        get_string('identfield_email', 'dhimport_version2'), '', 1));
// Create or update.
$settingkey = 'dhimport_version2/createorupdateuser';
$settingname = get_string('createorupdate', 'dhimport_version2');
$settingdesc = get_string('configcreateorupdate', 'dhimport_version2');
$settings->add(new admin_setting_configcheckbox($settingkey, $settingname, $settingdesc, 0));

//
// Enrolment import settings.
//
$lang = get_string('enrolmentimports', 'dhimport_version2');
$settings->add(new admin_setting_heading('dhimport_version2/enrolmentimportsheader', $lang, null));
// Create groups and groupings.
$settingkey = 'dhimport_version2/creategroupsandgroupings';
$settingname = get_string('creategroupsandgroupings', 'dhimport_version2');
$settingdesc = get_string('configcreategroupsandgroupings', 'dhimport_version2');
$settings->add(new admin_setting_configcheckbox($settingkey, $settingname, $settingdesc, ''));

//
// Course import settings.
//
$settingname = get_string('courseimports', 'dhimport_version2');
$settings->add(new admin_setting_heading('dhimport_version2/courseimportsheader', $settingname, null));
$settingkey = 'dhimport_version2/createorupdatecourse';
$settingname = get_string('createorupdate', 'dhimport_version2');
$settingdesc = get_string('configcreateorupdate', 'dhimport_version2');
$settings->add(new admin_setting_configcheckbox($settingkey, $settingname, $settingdesc, 0));

//
// Scheduling settings.
//
$lang = get_string('importfilesheading', 'dhimport_version2');
$settings->add(new admin_setting_heading('dhimport_version2/scheduling', $lang, ''));

// Setting for schedule_files_path.
$settingkey = 'dhimport_version2/schedule_files_path';
$settingname = get_string('import_files_path', 'dhimport_version2');
$settingdesc = get_string('config_schedule_files_path', 'dhimport_version2');
$settingdefault = '/datahub/dhimport_version2';
$settings->add(new \local_datahub\settings\admin_setting_path($settingkey, $settingname, $settingdesc, $settingdefault));

// Setting for user_schedule_file.
$settingkey = 'dhimport_version2/user_schedule_file';
$settingname = get_string('user_schedule_file', 'dhimport_version2');
$settingdesc = get_string('config_user_schedule_file', 'dhimport_version2');
$settings->add(new admin_setting_configtext($settingkey, $settingname, $settingdesc, 'user.csv'));

// Setting for course_schedule_file.
$settingkey = 'dhimport_version2/course_schedule_file';
$settingname = get_string('course_schedule_file', 'dhimport_version2');
$settingdesc = get_string('config_course_schedule_file', 'dhimport_version2');
$settings->add(new admin_setting_configtext($settingkey, $settingname, $settingdesc, 'course.csv'));

// Setting for enrolment_schedule_file.
$settingkey = 'dhimport_version2/enrolment_schedule_file';
$settingname = get_string('enrolment_schedule_file', 'dhimport_version2');
$settingdesc = get_string('config_enrolment_schedule_file', 'dhimport_version2');
$settings->add(new admin_setting_configtext($settingkey, $settingname, $settingdesc, 'enroll.csv'));

//
// Logging settings.
//
$lang = get_string('logging', 'dhimport_version2');
$settings->add(new admin_setting_heading('dhimport_version2/logging', $lang, ''));

// Log file location.
$settingkey = 'dhimport_version2/logfilelocation';
$settingname = get_string('logfilelocation', 'dhimport_version2');
$settingdesc = get_string('configlogfilelocation', 'dhimport_version2');
$settings->add(new \local_datahub\settings\admin_setting_path($settingkey, $settingname, $settingdesc, RLIP_DEFAULT_LOG_PATH));

// Email notification.
$settingkey = 'dhimport_version2/emailnotification';
$settingname = get_string('emailnotification', 'dhimport_version2');
$settingdesc = get_string('configemailnotification', 'dhimport_version2');
$settings->add(new admin_setting_configtext($settingkey, $settingname, $settingdesc, ''));

$settingkey = 'dhimport_version2/allowduplicateemails';
$settingname = get_string('allowduplicateemails', 'dhimport_version2');
$settingdesc = get_string('configallowduplicateemails', 'dhimport_version2');
$settings->add(new admin_setting_configcheckbox($settingkey, $settingname, $settingdesc, ''));

//
// Emails settings.
//
$settings->add(new admin_setting_heading('dhimport_version2/emails', get_string('emails', 'dhimport_version2'), ''));

// Toggle new user email notifications.
$newuseremailenabled = 'dhimport_version2/newuseremailenabled';
$newuseremailenabledname = get_string('newuseremailenabledname', 'dhimport_version2');
$newuseremailenableddesc = get_string('newuseremailenableddesc', 'dhimport_version2');
$settings->add(new admin_setting_configcheckbox($newuseremailenabled, $newuseremailenabledname, $newuseremailenableddesc, '0'));

$newuseremailsubject = 'dhimport_version2/newuseremailsubject';
$newuseremailsubjectname = get_string('newuseremailsubjectname', 'dhimport_version2');
$newuseremailsubjectdesc = get_string('newuseremailsubjectdesc', 'dhimport_version2');
$settings->add(new admin_setting_configtext($newuseremailsubject, $newuseremailsubjectname, $newuseremailsubjectdesc, ''));

$newuseremailtemplate = 'dhimport_version2/newuseremailtemplate';
$newuseremailtemplatename = get_string('newuseremailtemplatename', 'dhimport_version2');
$newuseremailtemplatedesc = get_string('newuseremailtemplatedesc', 'dhimport_version2');
$settings->add(new admin_setting_confightmleditor($newuseremailtemplate, $newuseremailtemplatename, $newuseremailtemplatedesc, '',
        PARAM_RAW, '60', '20'));

// Toggle new enrolment email notifications.
$settingkey = 'dhimport_version2/newenrolmentemailenabled';
$settingname = get_string('newenrolmentemailenabledname', 'dhimport_version2');
$settingdesc = get_string('newenrolmentemailenableddesc', 'dhimport_version2');
$settings->add(new admin_setting_configcheckbox($settingkey, $settingname, $settingdesc, '0'));

$settingkey = 'dhimport_version2/newenrolmentemailfrom';
$settingname = get_string('newenrolmentemailfromname', 'dhimport_version2');
$settingdesc = get_string('newenrolmentemailfromdesc', 'dhimport_version2');
$choices = array(
    'admin' => get_string('admin', 'dhimport_version2'),
    'teacher' => get_string('teacher', 'dhimport_version2')
);
$settings->add(new admin_setting_configselect($settingkey, $settingname, $settingdesc, 'admin', $choices));

$settingkey = 'dhimport_version2/newenrolmentemailsubject';
$settingname = get_string('newenrolmentemailsubjectname', 'dhimport_version2');
$settingdesc = get_string('newenrolmentemailsubjectdesc', 'dhimport_version2');
$settings->add(new admin_setting_configtext($settingkey, $settingname, $settingdesc, ''));

$settingkey = 'dhimport_version2/newenrolmentemailtemplate';
$settingname = get_string('newenrolmentemailtemplatename', 'dhimport_version2');
$settingdesc = get_string('newenrolmentemailtemplatedesc', 'dhimport_version2');
$settings->add(new admin_setting_confightmleditor($settingkey, $settingname, $settingdesc, '', PARAM_RAW, '60', '20'));
