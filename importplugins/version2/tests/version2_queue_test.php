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

use \dhimport_version2\provider\queue as queueprovider;

require_once(__DIR__.'/../../../../../local/eliscore/test_config.php');
global $CFG;

require_once($CFG->dirroot.'/local/datahub/tests/other/rlip_test.class.php');

/**
 * Test class exposing protected properties and methods for inspection.
 */
class testqueueprovider extends queueprovider {
    public $files;
}

/**
 * This is a test class that will auto-pause the queue after a given number of records.
 * Used to test pause behavior.
 */
class autopauseimportplugin extends \dhimport_version2\importplugin {
    /**
     * Entry point for processing a single record.
     *
     * @param string $entity The type of entity.
     * @param \stdClass $record One record of import data.
     * @param string $filename Import file name to user for logging.
     * @return bool True on success, otherwise false.
     */
    protected function process_record($entity, $record, $filename) {
        $result = $this->entityobj->process_record($record, $this->linenumber);
        set_config('queuepaused', true, 'dhimport_version2');
        return $result;
    }
}

/**
 * This is a test class that will interrupt the queue after each record to allow data inspection.
 */
class interruptimportplugin extends \dhimport_version2\importplugin {
    /**
     * A hook that is called after a record is processed.
     *
     * @param \stdClass $record The record that was processed.
     * @param array $metadata Various items of metadata about the process run.
     *                  string 'filepath' The path where the file is stored.
     *                  string 'filename' The name of the file being processed.
     *                  int 'filelines' The number of lines in the file.
     *                  int 'linenumber' The current line being processed.
     *                  \stdClass 'state' The state object.
     *                  string 'entity' The entity being processed.
     *                  int 'maxruntime' The maximum number of seconds allowed for this run.
     *                  int 'starttime' The timestamp when this run started.
     * @param rlip_fileplugin_base $fileplugin The file plugin in use.
     */
    protected function hook_did_process_record($record, $metadata, $fileplugin) {
        parent::hook_did_process_record($record, $metadata, $fileplugin);
        throw new \Exception('INTERRUPT');
    }
}


/**
 * Test version 2 with queue provider.
 *
 * @group local_datahub
 * @group dhimport_version2
 */
class version2_queue_testcase extends \rlip_test {
    /**
     * Assert correct properties of a log record.
     *
     * @param \stdClass $expectedrecord The expected record.
     * @param \stdClass $actualrecord The actual record.
     */
    protected function assertLogRecord($expectedrecord, $actualrecord) {
        $this->assertNotEmpty($actualrecord, 'No log record found');

        $expected = $expectedrecord->status;
        $actual = $actualrecord->status;
        $message = 'Log record status was incorrect.';
        $this->assertEquals($expected, $actual, $message);

        $expected = $expectedrecord->message;
        $actual = $actualrecord->message;
        $message = 'Log record message was incorrect.';
        $this->assertEquals($expected, $actual, $message);

        $expected = $expectedrecord->filename;
        $actual = $actualrecord->filename;
        $message = 'Log record filename was incorrect.';
        $this->assertEquals($expected, $actual, $message);

        $expected = $expectedrecord->line;
        $actual = $actualrecord->line;
        $message = 'Log record line was incorrect.';
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Test get_queueid.
     */
    public function test_getqueueid() {
        global $DB, $USER;

        $this->setAdminUser();
        $now = time();

        $queuerecord = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord);

        $provider = new testqueueprovider();

        $expected = $queuerecord->id;
        $actual = $provider->get_queueid();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test get_queueid gets first queued record (ignoring errors and finished entries).
     */
    public function test_getqueueidgetsfirstqueued() {
        global $DB, $USER;

        $this->setAdminUser();
        $now = time();

        $queuerecord0 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_ERRORS,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord0->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord0);

        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_FINISHED,
            'state' => '',
            'queueorder' => 1,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        $queuerecord2 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 2,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord2->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord2);

        $queuerecord3 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 3,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord3->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord3);

        $queuerecord4 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_ERRORS,
            'state' => '',
            'queueorder' => 4,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord4->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord4);

        $queuerecord5 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_FINISHED,
            'state' => '',
            'queueorder' => 5,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord5->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord5);

        $provider = new testqueueprovider();

        $expected = $queuerecord2->id;
        $actual = $provider->get_queueid();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test get_queueid gets scheduled records first.
     */
    public function test_getqueueidgetsscheduledfirst() {
        global $DB, $USER;

        $this->setAdminUser();
        $now = time();

        $queuerecord0 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_ERRORS,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord0->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord0);

        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_FINISHED,
            'state' => '',
            'queueorder' => 1,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        $queuerecord2 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 2,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord2->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord2);

        $queuerecord3 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_SCHEDULED,
            'state' => '',
            'queueorder' => 3,
            'scheduledtime' => time() + 100000,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord3->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord3);

        $queuerecord4 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_SCHEDULED,
            'state' => '',
            'queueorder' => 4,
            'scheduledtime' => time() - 100000,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord4->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord4);

        $queuerecord5 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_SCHEDULED,
            'state' => '',
            'queueorder' => 5,
            'scheduledtime' => time() - 100000,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord5->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord5);

        $queuerecord6 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_ERRORS,
            'state' => '',
            'queueorder' => 4,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord6->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord6);

        $queuerecord7 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_FINISHED,
            'state' => '',
            'queueorder' => 5,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord7->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord7);

        $provider = new testqueueprovider();

        $expected = $queuerecord4->id;
        $actual = $provider->get_queueid();
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test provider checks for processing records and updates the status.
     */
    public function test_getleftoverprocessingrecords() {
        global $DB, $USER;

        $this->setAdminUser();
        $now = time();

        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        $queuerecord2 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_PROCESSING,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord2->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord2);


        $provider = new testqueueprovider();

        // Validate get_queueid gets the first "queued" record.
        $expected = $queuerecord1->id;
        $actual = $provider->get_queueid();
        $this->assertEquals($expected, $actual);

        // Validate the processing record's status was updated.
        $queuerecord2new = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord2->id]);
        $actual = $queuerecord2new->status;
        $expected = queueprovider::STATUS_ERRORS;
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test the build_files method.
     */
    public function test_buildfiles() {
        global $DB, $USER, $CFG;

        $this->setAdminUser();
        $now = time();

        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        $provider = new testqueueprovider();

        // Validate get_queueid gets the first "queued" record.
        $actual = $provider->files;
        $expected = [$CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv'];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Simple test import, start-to-finish.
     */
    public function test_queueduserimport() {
        global $DB, $CFG, $USER;

        $this->setAdminUser();
        $now = time();

        // Create queue record.
        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        // Write file.
        $data = [
            [
                    'useraction',
                    'username',
                    'password',
                    'firstname',
                    'lastname',
                    'email',
                    'city',
            ],
            [
                    'create',
                    'testuser',
                    'Testpass!0',
                    'MyFirstName',
                    'MyLastName',
                    'test@example.com',
                    'Toronto',
            ]
        ];
        if (!file_exists($CFG->dataroot.'/datahub')) {
            mkdir($CFG->dataroot.'/datahub');
        }
        if (!file_exists($CFG->dataroot.'/datahub/dhimport_version2')) {
            mkdir($CFG->dataroot.'/datahub/dhimport_version2');
        }
        $filename = $CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv';
        $data = implode(',', $data[0])."\n".implode(',', $data[1]);
        file_put_contents($filename, $data);

        $provider = new testqueueprovider();
        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        $exists = $DB->record_exists('user', ['username' => 'testuser']);
        $this->assertTrue($exists, 'User was not created.');

        $queuerecord1new = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $expected = queueprovider::STATUS_FINISHED;
        $actual = $queuerecord1new->status;
        $this->assertEquals($expected, $actual, 'Queue record status was not updated.');

        // Check log record.
        $logrecord = $DB->get_record(queueprovider::LOGTABLE, ['queueid' => $queuerecord1->id]);
        $this->assertNotEmpty($logrecord, 'No log record found');
        $expectedrecord = (object)[
            'status' => 1,
            'message' => 'User with username "testuser", email "test@example.com" successfully created.',
            'filename' => $queuerecord1->id.'.csv',
            'line' => 1,
        ];
        $this->assertLogRecord($expectedrecord, $logrecord);
    }

    /**
     * Simple test enrolment import, start-to-finish.
     */
    public function test_queuedenrolmentimport() {
        global $DB, $CFG, $USER;

        $this->setAdminUser();
        $now = time();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Create queue record.
        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        // Write file.
        $data = [
            [
                    'enrolmentaction',
                    'username',
                    'context',
                    'instance',
                    'role',
            ],
            [
                    'create',
                    $user->username,
                    'course',
                    $course->shortname,
                    'student',
            ]
        ];
        if (!file_exists($CFG->dataroot.'/datahub')) {
            mkdir($CFG->dataroot.'/datahub');
        }
        if (!file_exists($CFG->dataroot.'/datahub/dhimport_version2')) {
            mkdir($CFG->dataroot.'/datahub/dhimport_version2');
        }
        $filename = $CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv';
        $data = implode(',', $data[0])."\n".implode(',', $data[1]);
        file_put_contents($filename, $data);

        $provider = new testqueueprovider();
        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        $enrolrec = $DB->get_record('enrol', ['enrol' => 'manual', 'courseid' => $course->id]);
        $exists = $DB->record_exists('user_enrolments', ['userid' => $user->id, 'enrolid' => $enrolrec->id]);
        $this->assertTrue($exists, 'Enrolment was not created.');

        $ctx = \context_course::instance($course->id);
        $role = $DB->get_record('role', ['shortname' => 'student']);
        $exists = $DB->record_exists('role_assignments', ['roleid' => $role->id, 'contextid' => $ctx->id, 'userid' => $user->id]);
        $this->assertTrue($exists, 'Role assignment was not created.');

        $queuerecord1new = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $expected = queueprovider::STATUS_FINISHED;
        $actual = $queuerecord1new->status;
        $this->assertEquals($expected, $actual, 'Queue record status was not updated.');

        // Check log record.
        $logrecord = $DB->get_record(queueprovider::LOGTABLE, ['queueid' => $queuerecord1->id]);
        $this->assertNotEmpty($logrecord, 'No log record found');
        $msg = 'User with username "'.$user->username.'" successfully assigned role with shortname "student" on ';
        $msg .= 'course "'.$course->shortname.'". ';
        $msg .= 'User with username "'.$user->username.'" enrolled in course with shortname "'.$course->shortname.'".';
        $expectedrecord = (object)[
            'status' => 1,
            'message' => $msg,
            'filename' => $queuerecord1->id.'.csv',
            'line' => 1,
        ];
        $this->assertLogRecord($expectedrecord, $logrecord);
    }

    /**
     * Simple test course import, start-to-finish.
     */
    public function test_queuedcourseimport() {
        global $DB, $CFG, $USER;

        $this->setAdminUser();
        $now = time();

        $user = $this->getDataGenerator()->create_user();
        $coursecat = $this->getDataGenerator()->create_category();

        // Create queue record.
        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        // Write file.
        $data = [
            [
                    'courseaction',
                    'fullname',
                    'shortname',
                    'category',
            ],
            [
                    'create',
                    'Test course',
                    'testcourse',
                    $coursecat->name,
            ]
        ];
        if (!file_exists($CFG->dataroot.'/datahub')) {
            mkdir($CFG->dataroot.'/datahub');
        }
        if (!file_exists($CFG->dataroot.'/datahub/dhimport_version2')) {
            mkdir($CFG->dataroot.'/datahub/dhimport_version2');
        }
        $filename = $CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv';
        $data = implode(',', $data[0])."\n".implode(',', $data[1]);
        file_put_contents($filename, $data);

        $provider = new testqueueprovider();
        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        $exists = $DB->record_exists('course', ['fullname' => 'Test course']);
        $this->assertTrue($exists, 'Course was not created.');

        $queuerecord1new = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $expected = queueprovider::STATUS_FINISHED;
        $actual = $queuerecord1new->status;
        $this->assertEquals($expected, $actual, 'Queue record status was not updated.');

        // Check log record.
        $logrecord = $DB->get_record(queueprovider::LOGTABLE, ['queueid' => $queuerecord1->id]);
        $this->assertNotEmpty($logrecord, 'No log record found');
        $expectedrecord = (object)[
            'status' => 1,
            'message' => 'Course with shortname "testcourse" successfully created.',
            'filename' => $queuerecord1->id.'.csv',
            'line' => 1,
        ];
        $this->assertLogRecord($expectedrecord, $logrecord);
    }

    /**
     * Simple error-triggering import.
     */
    public function test_queueduserimportwitherror() {
        global $DB, $CFG, $USER;

        $this->setAdminUser();
        $now = time();

        // Create queue record.
        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        // Write file.
        $data = [
            [
                    'useraction',
                    'username',
                    'password',
                    'firstname',
                    'email',
                    'city',
            ],
            [
                    'create',
                    'testuser',
                    'Testpass!0',
                    'MyFirstName',
                    'test@example.com',
                    'Toronto',
            ]
        ];
        if (!file_exists($CFG->dataroot.'/datahub')) {
            mkdir($CFG->dataroot.'/datahub');
        }
        if (!file_exists($CFG->dataroot.'/datahub/dhimport_version2')) {
            mkdir($CFG->dataroot.'/datahub/dhimport_version2');
        }
        $filename = $CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv';
        $data = implode(',', $data[0])."\n".implode(',', $data[1]);
        file_put_contents($filename, $data);

        $provider = new testqueueprovider();
        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        $exists = $DB->record_exists('user', ['username' => 'testuser']);
        $this->assertFalse($exists, 'User was not created.');

        $queuerecord1new = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $expected = queueprovider::STATUS_ERRORS;
        $actual = $queuerecord1new->status;
        $this->assertEquals($expected, $actual, 'Queue record status was not updated.');

        // Check log record.
        $logrecord = $DB->get_record(queueprovider::LOGTABLE, ['queueid' => $queuerecord1->id]);
        $this->assertNotEmpty($logrecord, 'No log record found');
        $expectedrecord = (object)[
            'status' => 0,
            'message' => 'User could not be processed. Required field lastname is unspecified or empty.',
            'filename' => $queuerecord1->id.'.csv',
            'line' => 1,
        ];
        $this->assertLogRecord($expectedrecord, $logrecord);
    }

    /**
     * Test import that stops mid-way due to pause.
     */
    public function test_queueduserimportwithpause() {
        global $DB, $CFG, $USER;

        $this->setAdminUser();
        $now = time();

        // Create queue record.
        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        // Write file.
        $data = [
            [
                    'useraction',
                    'username',
                    'password',
                    'firstname',
                    'lastname',
                    'email',
                    'city',
            ],
            [
                    'create',
                    'testuser1',
                    'Testpass!0',
                    'MyFirstName1',
                    'MyLastName1',
                    'test1@example.com',
                    'Toronto',
            ],
            [
                    'create',
                    'testuser2',
                    'Testpass!0',
                    'MyFirstName2',
                    'MyLastName2',
                    'test2@example.com',
                    'Toronto',
            ],
        ];
        if (!file_exists($CFG->dataroot.'/datahub')) {
            mkdir($CFG->dataroot.'/datahub');
        }
        if (!file_exists($CFG->dataroot.'/datahub/dhimport_version2')) {
            mkdir($CFG->dataroot.'/datahub/dhimport_version2');
        }
        $filename = $CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv';
        $filecontents = '';
        foreach ($data as $line) {
            $filecontents .= implode(',', $line)."\n";
        }
        file_put_contents($filename, $filecontents);

        $provider = new testqueueprovider();
        $importplugin = new autopauseimportplugin($provider);
        $importplugin->run();

        // Check that the queue record still exists, that state is present, and status is queued.
        $queuerecord2 = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $this->assertNotEmpty($queuerecord2);
        $this->assertNotEmpty($queuerecord2->state);
        $this->assertEquals(queueprovider::STATUS_QUEUED, $queuerecord2->status);

        // Check the log table.
        $logrecords = $DB->get_records(queueprovider::LOGTABLE, ['queueid' => $queuerecord1->id]);
        $this->assertEquals(2, count($logrecords));

        // The first user should have been created.
        $expectedrecord = (object)[
            'status' => 1,
            'message' => 'User with username "testuser1", email "test1@example.com" successfully created.',
            'line' => 1,
            'filename' => $queuerecord1->id.'.csv',
        ];
        $this->assertLogRecord($expectedrecord, array_shift($logrecords));

        // The next log record is an interrupt message.
        $expectedrecord = (object)[
            'status' => 0,
            'message' => 'Import processing of entity \'any\' partially processed. Processed 0 of 2 total records. Import will continue at next cron.',
            'line' => '',
            'filename' => $queuerecord1->id,
        ];
        $this->assertLogRecord($expectedrecord, array_shift($logrecords));

        // Assert only the first user was created.
        $testuser1 = $DB->get_record('user', ['username' => 'testuser1']);
        $this->assertNotEmpty($testuser1);
        $testuser2 = $DB->get_record('user', ['username' => 'testuser2']);
        $this->assertEmpty($testuser2);

        // Unpause the queue.
        set_config('queuepaused', false, 'dhimport_version2');

        // Run again.
        $importplugin->run();

        // Check that the queue record still exists, that state is present, and status is finished with errors.
        $queuerecord2 = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $this->assertNotEmpty($queuerecord2);
        $this->assertEmpty($queuerecord2->state);
        $this->assertEquals(queueprovider::STATUS_ERRORS, $queuerecord2->status);

        // Check the log table.
        $logrecords = $DB->get_records(queueprovider::LOGTABLE, ['queueid' => $queuerecord1->id]);
        $this->assertEquals(3, count($logrecords));

        // The first user should have been created.
        $expectedrecord = (object)[
            'status' => 1,
            'message' => 'User with username "testuser1", email "test1@example.com" successfully created.',
            'line' => 1,
            'filename' => $queuerecord1->id.'.csv',
        ];
        $this->assertLogRecord($expectedrecord, array_shift($logrecords));

        // The next log record is an interrupt message.
        $expectedrecord = (object)[
            'status' => 0,
            'message' => 'Import processing of entity \'any\' partially processed. Processed 0 of 2 total records. Import will continue at next cron.',
            'line' => '',
            'filename' => $queuerecord1->id,
        ];
        $this->assertLogRecord($expectedrecord, array_shift($logrecords));

        // The first user should have been created.
        $expectedrecord = (object)[
            'status' => 1,
            'message' => 'User with username "testuser2", email "test2@example.com" successfully created.',
            'line' => 2,
            'filename' => $queuerecord1->id.'.csv',
        ];
        $this->assertLogRecord($expectedrecord, array_shift($logrecords));

        // Assert now both users are created.
        $testuser1 = $DB->get_record('user', ['username' => 'testuser1']);
        $this->assertNotEmpty($testuser1);
        $testuser2 = $DB->get_record('user', ['username' => 'testuser2']);
        $this->assertNotEmpty($testuser2);
    }

    /**
     * Test import tracks processing progress.
     */
    public function test_queuedimporttracksprogress() {
        global $DB, $CFG, $USER;

        $this->setAdminUser();
        $now = time();

        // Create queue record.
        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'state' => '',
            'queueorder' => 0,
            'scheduledtime' => 0,
            'timemodified' => $now,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        // Write file.
        $data = [
            [
                    'useraction',
                    'username',
                    'password',
                    'firstname',
                    'lastname',
                    'email',
                    'city',
            ],
            [
                    'create',
                    'testuser1',
                    'Testpass!0',
                    'MyFirstName1',
                    'MyLastName1',
                    'test1@example.com',
                    'Toronto',
            ],
            [
                    'create',
                    'testuser2',
                    'Testpass!0',
                    'MyFirstName2',
                    'MyLastName2',
                    'test2@example.com',
                    'Toronto',
            ],
            [
                    'create',
                    'testuser3',
                    'Testpass!0',
                    'MyFirstName3',
                    'MyLastName3',
                    'test3@example.com',
                    'Toronto',
            ],
        ];
        if (!file_exists($CFG->dataroot.'/datahub')) {
            mkdir($CFG->dataroot.'/datahub');
        }
        if (!file_exists($CFG->dataroot.'/datahub/dhimport_version2')) {
            mkdir($CFG->dataroot.'/datahub/dhimport_version2');
        }
        $filename = $CFG->dataroot.'/datahub/dhimport_version2/'.$queuerecord1->id.'.csv';
        $filecontents = '';
        foreach ($data as $line) {
            $filecontents .= implode(',', $line)."\n";
        }
        file_put_contents($filename, $filecontents);

        $provider = new testqueueprovider();
        $importplugin = new interruptimportplugin($provider);

        // Run one record.
        try {
            $importplugin->run();
        } catch (\Exception $e) {
        }
        $qrec = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $this->assertNotEmpty($qrec->state);
        $state = unserialize($qrec->state);
        $this->assertEquals(4, $state->filelines);
        $this->assertEquals(1, $state->linenumber);
        $this->assertNotEmpty($DB->get_record('user', ['username' => 'testuser1']));
        $this->assertEmpty($DB->get_record('user', ['username' => 'testuser2']));
        $this->assertEmpty($DB->get_record('user', ['username' => 'testuser3']));

        // Run second record.
        try {
            $importplugin->run();
        } catch (\Exception $e) {
        }
        $qrec = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $this->assertNotEmpty($qrec->state);
        $state = unserialize($qrec->state);
        $this->assertEquals(4, $state->filelines);
        $this->assertEquals(2, $state->linenumber);
        $this->assertNotEmpty($DB->get_record('user', ['username' => 'testuser1']));
        $this->assertNotEmpty($DB->get_record('user', ['username' => 'testuser2']));
        $this->assertEmpty($DB->get_record('user', ['username' => 'testuser3']));

        // Run third record.
        try {
            $importplugin->run();
        } catch (\Exception $e) {
        }
        $qrec = $DB->get_record(queueprovider::QUEUETABLE, ['id' => $queuerecord1->id]);
        $this->assertNotEmpty($qrec->state);
        $state = unserialize($qrec->state);
        $this->assertEquals(4, $state->filelines);
        $this->assertEquals(3, $state->linenumber);
        $this->assertNotEmpty($DB->get_record('user', ['username' => 'testuser1']));
        $this->assertNotEmpty($DB->get_record('user', ['username' => 'testuser2']));
        $this->assertNotEmpty($DB->get_record('user', ['username' => 'testuser3']));
    }

}
