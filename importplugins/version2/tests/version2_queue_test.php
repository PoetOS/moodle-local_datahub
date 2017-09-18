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
            'timemodified' => $now,
            'timecreated' => $now,
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
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord0->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord0);

        $queuerecord1 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_FINISHED,
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        $queuerecord2 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord2->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord2);

        $queuerecord3 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_QUEUED,
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord3->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord3);

        $queuerecord4 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_ERRORS,
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord4->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord4);

        $queuerecord5 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_FINISHED,
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord5->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord5);


        $provider = new testqueueprovider();

        $expected = $queuerecord2->id;
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
            'timemodified' => $now,
            'timecreated' => $now,
        ];
        $queuerecord1->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord1);

        $queuerecord2 = (object)[
            'userid' => $USER->id,
            'status' => queueprovider::STATUS_PROCESSING,
            'timemodified' => $now,
            'timecreated' => $now,
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
            'timemodified' => $now,
            'timecreated' => $now,
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
            'timemodified' => $now,
            'timecreated' => $now,
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
            'timemodified' => $now,
            'timecreated' => $now,
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
            'message' => 'Required field lastname is unspecified or empty.',
            'filename' => $queuerecord1->id.'.csv',
            'line' => 1,
        ];
        $this->assertLogRecord($expectedrecord, $logrecord);
    }

}
