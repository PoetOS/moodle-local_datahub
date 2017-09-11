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
 * @package    dhimport_version2
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(__DIR__.'/../../../../../local/eliscore/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/local/datahub/tests/other/rlip_test.class.php');

// Libs.
require_once(__DIR__.'/other/rlip_mock_provider.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_importplugin.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_dataplugin.class.php');
require_once($CFG->dirroot.'/local/datahub/tests/other/readmemory.class.php');
require_once($CFG->dirroot.'/local/datahub/tests/other/rlip_test.class.php');

/**
 * Class for testing how Version 2 deals with "empty" values in updates for users.
 *
 * @group local_datahub
 * @group dhimport_version2
 */
class version2emptyvalueupdates_user_testcase extends rlip_test {

    /**
     * Asserts that a record in the given table exists
     *
     * @param string $table The database table to check
     * @param array $params The query parameters to validate against
     */
    private function assert_record_exists($table, $params = array()) {
        global $DB;

        $exists = $DB->record_exists($table, $params);
        $this->assertTrue($exists);
    }

    /**
     * Validates that the version 2 update ignores empty values and does not
     * blank out fields for users.
     */
    public function test_version2userupdateignoresemptyvalues() {
        global $CFG, $DB;

        set_config('createorupdate', 0, 'dhimport_version2');

        // Create, then update a user.
        $data = array(
                array(
                    'useraction' => 'create',
                    'username' => 'rlipusername',
                    'password' => 'Rlippassword!0',
                    'firstname' => 'rlipfirstname',
                    'lastname' => 'rliplastname',
                    'email' => 'rlipuser@rlipdomain.com',
                    'city' => 'rlipcity',
                    'country' => 'CA',
                    'idnumber' => 'rlipidnumber'
                ),
                array(
                    'useraction' => 'update',
                    'username' => 'rlipusername',
                    'password' => '',
                    'firstname' => 'updatedrlipfirstname',
                    'lastname' => '',
                    'email' => '',
                    'city' => '',
                    'country' => '',
                    'idnumber' => ''
                )
        );
        $provider = new rlipimport_version2_importprovider_emptyuser($data);

        $importplugin = rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        // Validation.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => 'updatedrlipfirstname',
            'lastname' => 'rliplastname',
            'email' => 'rlipuser@rlipdomain.com',
            'city' => 'rlipcity',
            'country' => 'CA',
            'idnumber' => 'rlipidnumber'
        );
        $this->assert_record_exists('user', $params);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => $params['username']));
        $this->assertTrue(validate_internal_user_password($userrec, 'Rlippassword!0'));
    }

    /**
     * Validates that the version 2 update ignores empty fields that could
     * potentially be used to identify a user
     */
    public function test_version2userupdateignoresemptyidentifyingfields() {
        global $CFG;

        require_once($CFG->dirroot.'/user/lib.php');

        // Create a user.
        $user = new stdClass;
        $user->username = 'rlipusername';
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->idnumber = 'rlipidnumber';
        $user->email = 'rlipuser@rlipdomain.com';
        $user->password = 'Password!0';
        $user->idnumber = 'rlipidnumber';
        $user->country = 'CA';
        $user->id = user_create_user($user);

        // Update a user with blank email and idnumber.
        $data = array(
                array(
                    'useraction' => 'update',
                    'username' => 'rlipusername',
                    'email' => '',
                    'idnumber' => '',
                    'firstname' => 'updatedrlipfirstname1'
                )
        );

        $provider = new rlipimport_version2_importprovider_emptyuser($data);

        $importplugin = rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        // Validation.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber',
            'email' => 'rlipuser@rlipdomain.com',
            'firstname' => 'updatedrlipfirstname1'
        );
        $this->assert_record_exists('user', $params);

        // Update a user with a blank username.
        $data = array(
                array(
                    'useraction' => 'update',
                    'username' => '',
                    'email' => 'rlipuser@rlipdomain.com',
                    'idnumber' => 'rlipidnumber',
                    'firstname' => 'updatedrlipfirstname2'
                )
        );

        $provider = new rlipimport_version2_importprovider_emptyuser($data);

        $importplugin = rlip_dataplugin_factory::factory('dhimport_version2', $provider);
        $importplugin->run();

        // Validation.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber',
            'email' => 'rlipuser@rlipdomain.com',
            'firstname' => 'updatedrlipfirstname2'
        );
        $this->assert_record_exists('user', $params);
    }
}
