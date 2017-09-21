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
 * Language strings for dhimport_version2.
 *
 * @package dhimport_version2
 * @author Remote-Learner.net Inc
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2017 Remote Learner.net Inc http://www.remote-learner.net
 */

$string['pluginname'] = 'Version 2 Import';
$string['version2header'] = 'Data Hub Version 2';

$string['version2importtab'] = 'Import';
$string['version2queuetab'] = 'Queue';
$string['version2settingstab'] = 'Settings';

$string['admin'] = 'Site Administrator';
$string['allowduplicateemails'] = 'Allow duplicate email addresses.';
$string['configallowduplicateemails'] = 'If on, duplicate email addresses will be allowed.';
$string['config_schedule_files_path'] = 'The directory path, relative to moodledata, to the location of import files.';
$string['config_course_schedule_file'] = 'The filename of the "course" import file.';
$string['config_enrolment_schedule_file'] = 'The filename of the "enrolment" import file.';
$string['config_user_schedule_file'] = 'The filename of the "user" import file.';
$string['configcreategroupsandgroupings'] = 'If enabled, groups and groupings can be created in the enrolment import.';
$string['configcreateorupdate'] = 'If enabled, all create or update actions on users and courses are converted to create
actions if the entity does not already exist, or update actions if the entity exists.';
$string['configemailnotification'] = 'A comma-separated list of email addresses to send import log files to.';
$string['configfieldstreelink'] = 'Field mapping';
$string['configidentfield'] = 'The above specify which standard user fields will be used as identifying fields for user import.';
$string['configlogfilelocation'] = 'The directory path, relative to moodledata, to the location of log files.';
$string['configurelink'] = 'Click here to configure field mappings to use for importing data';
$string['configuretitle'] = 'Plugins Blocks: Configure Version 2 Import Fields';
$string['course_schedule_file'] = 'Course import filename';
$string['coursefile'] = 'Course file';
$string['courseimports'] = 'Course Imports';
$string['coursetab'] = 'Course fields';
$string['creategroupsandgroupings'] = 'Create groups and groupings';
$string['createorupdate'] = 'Create or update';
$string['datahandling'] = 'Data handling';
$string['emails'] = 'Emails';
$string['emailnotification'] = 'Email notification';
$string['enrolment_schedule_file'] = 'Enrolment import filename';
$string['enrolmentfile'] = 'Enrolment file';
$string['enrolmentimports'] = 'Enrolment Imports';
$string['enrolmenttab'] = 'Enrolment fields';
$string['identfield_email'] = 'Use email as identifying field';
$string['identfield_idnumber'] = 'Use idnumber as identifying field';
$string['identfield_username'] = 'Use username as identifying field';
$string['importfields'] = 'Version 2 import fields';
$string['importfile'] = 'Import File';
$string['importfilesheading'] = 'Scheduled Import File Locations';
$string['import_file_timestamp'] = '%b_%d_%Y_%H%M%S';
$string['logfilelocation'] = 'Log file location';
$string['logging'] = 'Logging';
$string['mappingscancelled'] = 'Previously saved field mappings successfully restored';
$string['mappingsreset'] = 'Default field mappings successfully restored';
$string['mappingssaved'] = 'Field mappings successfully saved';

$string['newuseremailenabledname'] = 'Send New User Email Notifications';
$string['newuseremailenableddesc'] = 'When a new user is created with this import plugin, send them an email using the template below';
$string['newuseremailsubjectname'] = 'New User Email Notifications Subject';
$string['newuseremailsubjectdesc'] = 'This is the text to use for the subject line of the email.';
$string['newuseremailtemplatename'] = 'New User Email Notifications Template';
$string['newuseremailtemplatedesc'] = 'If enabled, send new users created with this plugin the above text. Note that if the above text is empty, no email will be sent.<br />
<b>The following placeholders are available:</b>
<ul>
<li><b>%%sitename%%</b>: The site\'s name.</li>
<li><b>%%loginlink%%</b>: A URL to the site\'s login page</li>
<li><b>%%username%%</b>: The user\'s username.</li>
<li><b>%%idnumber%%</b>: The user\'s idnumber.</li>
<li><b>%%password%%</b>: The user\'s password.</li>
<li><b>%%firstname%%</b>: The user\'s first name.</li>
<li><b>%%lastname%%</b>: The user\'s last name.</li>
<li><b>%%fullname%%</b>: The user\'s full name.</li>
<li><b>%%email%%</b>: The user\'s email address.</li>
</ul>
';
$string['newenrolmentemailenabledname'] = 'Send New Enrolment Email Notifications';
$string['newenrolmentemailenableddesc'] = 'When a user is enrolled into a course with this import plugin, send them an email using the template below.';
$string['newenrolmentemailfromname'] = 'Send Enrolment Email from';
$string['newenrolmentemailfromdesc'] = 'Set who the email should be from.';
$string['newenrolmentemailsubjectname'] = 'New Enrolment Email Notifications Subject';
$string['newenrolmentemailsubjectdesc'] = 'This is the text to use for the subject line of the email.';
$string['newenrolmentemailtemplatename'] = 'New Enrolment Email Notifications Template';
$string['newenrolmentemailtemplatedesc'] = 'If enabled, send users enrolled with this plugin the above text. Note that if the above text is empty, no email will be sent.<br />
<b>The following placeholders are available:</b>
<ul>
<li><b>%%sitename%%</b>: The site\'s name.</li>
<li><b>%%user_username%%</b>: The user\'s username.</li>
<li><b>%%user_idnumber%%</b>: The user\'s idnumber.</li>
<li><b>%%user_firstname%%</b>: The user\'s first name.</li>
<li><b>%%user_lastname%%</b>: The user\'s last name.</li>
<li><b>%%user_fullname%%</b>: The user\'s full name.</li>
<li><b>%%user_email%%</b>: The user\'s email address.</li>
<li><b>%%course_fullname%%</b>: The full name of the course..</li>
<li><b>%%course_shortname%%</b>: The shortname of the course.</li>
<li><b>%%course_idnumber%%</b>: The idnumber of the course.</li>
<li><b>%%course_summary%%</b>: The course\'s summary.</li>
</ul>
';
$string['queue_error_badidforreorder'] = 'An item in the order parameter was not a valid ID';
$string['queue_error_cannotreorderwhileunpaused'] = 'Queue reordering can only be done while the queue is paused.';
$string['queue_error_itemnotfound'] = 'Queue item not found.';
$string['queue_status_processing'] = 'Processing';
$string['queue_status_waiting'] = 'Waiting';
$string['queue_status_scheduled'] = 'Scheduled';
$string['queue_status_complete'] = 'Complete';
$string['queue_status_errors'] = 'Complete with errors/notices.';
$string['scheduling'] = 'Scheduling';
$string['teacher'] = 'Course Teacher';
$string['import_files_path'] = 'Import files path';
$string['user_schedule_file'] = 'User import filename';
$string['userfile'] = 'User file';
$string['userimports'] = 'User Imports';
$string['usertab'] = 'User fields';
