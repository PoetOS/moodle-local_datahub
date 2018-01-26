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
 * @package    dhimport_version2
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2015 Remote Learner.net Inc http://www.remote-learner.net
 *
 */

require_once(dirname(__FILE__).'/../../../../../local/eliscore/test_config.php');
global $CFG;
require_once($CFG->dirroot.'/local/datahub/tests/other/rlip_test.class.php');

// Libs.
require_once(dirname(__FILE__).'/other/rlip_mock_provider.class.php');
require_once($CFG->dirroot.'/local/datahub/lib/rlip_importplugin.class.php');
require_once($CFG->dirroot.'/local/datahub/tests/other/readmemory.class.php');
require_once($CFG->dirroot.'/local/eliscore/lib/lib.php');
require_once($CFG->dirroot.'/tag/lib.php');

require_once(dirname(__FILE__).'/../version2.class.php');

// Must expose protected method for testing
class open_rlip_importplugin_version2 extends rlip_importplugin_version2 {
    /**
     * Determine userid from user import record
     *
     * @param object $record One record of import data
     * @param string $filename The import file name, used for logging
     * @param bool   $error Returned errors status, true means error, false ok
     * @param array  $errors Array of error strings (if $error == true)
     * @param string $errsuffix returned error suffix string
     * @return int|bool userid on success, false is not found
     */
    public function get_userid_for_user_actions(&$record, $filename, &$error, &$errors, &$errsuffix) {
        return parent::get_userid_for_user_actions($record, $filename, $error, $errors, $errsuffix);
    }
}

/**
 * Class for version 2 user import correctness.
 * @group local_datahub
 * @group dhimport_version2
 */
class version2userimport_testcase extends rlip_test {

    /**
     * Helper function to get the core fields for a sample user
     *
     * @return array The user data
     */
    private function get_core_user_data() {
        $data = array(
            'entity' => 'any',
            'useraction' => 'create',
            'username' => 'rlipusername',
            'password' => 'Rlippassword!1234',
            'firstname' => 'rlipfirstname',
            'lastname' => 'rliplastname',
            'email' => 'rlipuser@rlipdomain.com',
            'city' => 'rlipcity',
            'country' => 'CA'
        );
        return $data;
    }

    /**
     * Helper function that runs the user import for a sample user
     *
     * @param array $extradata Extra fields to set for the new user
     */
    private function run_core_user_import($extradata, $usedefaultdata = true) {
        global $CFG;
        set_config('country', 'CA'); // Moodle's user_delete_user()?

        if ($usedefaultdata) {
            $data = $this->get_core_user_data();
        } else {
            $data = array();
        }

        foreach ($extradata as $key => $value) {
            $data[$key] = $value;
        }

        $provider = new \rlipimport_version2_importprovider_mockuser($data);

        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider, null, true);
        $importplugin->run();
    }

    /**
     * Helper function for creating a Moodle user profile field
     *
     * @param string $name Profile field shortname
     * @param string $datatype Profile field data type
     * @param int $categoryid Profile field category id
     * @param string $param1 Extra parameter, used for select options
     * @param string $param2 Extra parameter, used for select options
     */
    private function create_profile_field($name, $datatype, $categoryid, $param1 = null, $param2 = null) {
        global $CFG;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/profile/definelib.php');
        require_once($CFG->dirroot.'/user/profile/field/'.$datatype.'/define.class.php');

        $class = "profile_define_{$datatype}";
        $field = new $class();
        $data = new stdClass;
        $data->shortname = $name;
        $data->name = $name;
        $data->datatype = $datatype;
        $data->categoryid = $categoryid;
        $data->startyear = null;
        $data->endyear = null;
        $data->startmonth = null;
        $data->endmonth = null;
        $data->startday = null;
        $data->endday = null;
        $data->param1 = null;
        $data->param2 = null;

        if ($param1 !== null) {
            $data->param1 = $param1;
        }

        if ($param2 !== null) {
            $data->param2 = $param2;
        }

        $field->define_save($data);
    }

    /**
     * Asserts, using PHPunit, that the test user does not exist
     */
    private function assert_core_user_does_not_exist() {
        global $CFG, $DB;

        $exists = $DB->record_exists('user', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));
        $this->assertEquals($exists, false);
    }

    /**
     * Asserts that a record in the given table exists
     *
     * @param string $table The database table to check
     * @param array $params The query parameters to validate against
     */
    private function assert_record_exists($table, $params = array()) {
        global $DB;

        $exists = $DB->record_exists($table, $params);
        $tmp = '';
        if (!$exists) {
            ob_start();
            var_dump($params);
            var_dump($DB->get_records($table));
            $tmp = ob_get_contents();
            ob_end_clean();
        }
        $this->assertTrue($exists, "Error: record should exist in {$table} => params/DB = {$tmp}\n");
    }

    /**
     * Validate that the version 2 plugin supports user actions.
     */
    public function test_version2importsupportsuseractions() {
        $actual = plugin_supports('dhimport', 'version2', 'user');
        $expected = ['create', 'add', 'update', 'delete', 'disable'];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Validate that the version 2 plugin supports user creation.
     */
    public function test_version2importsupportsusercreate() {
        $actual = plugin_supports('dhimport', 'version2', 'user_create');
        $expected = ['username', 'password', 'firstname', 'lastname', 'email', 'city'];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Validate that the version 2 plugin supports user addition.
     *
     * Note: this is the same as user creation, but makes up for a weirdness in IP for 1.9.
     */
    public function test_version2importsupportsuseradd() {
        $actual = plugin_supports('dhimport', 'version2', 'user_add');
        $expected = ['username', 'password', 'firstname', 'lastname', 'email', 'city'];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Validate that the version 2 plugin supports user updates.
     */
    public function test_version2importsupportsuserupdate() {
        $actual = plugin_supports('dhimport', 'version2', 'user_update');
        $expected = [['user_username', 'user_email', 'user_idnumber', 'username', 'email', 'idnumber']];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Validate that required fields are set to specified values during user creation.
     */
    public function test_version2importsetsrequireduserfieldsoncreate() {
        global $CFG, $DB;

        $data = $this->get_core_user_data();
        $provider = new \rlipimport_version2_importprovider_mockuser($data);

        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider, null, true);
        $importplugin->run();

        $password = $data['password'];
        unset($data['password']);
        unset($data['entity']);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        // User should be confirmed by default.
        $data['confirmed'] = 1;

        $exists = $DB->record_exists('user', $data);
        $this->assertTrue($exists);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => $data['username']));
        $this->assertTrue(validate_internal_user_password($userrec, $password));
    }

    /**
     * Validate that required fields are set to specified values during user creation.
     */
    public function test_version2importsetsrequireduserfieldsonadd() {
        global $CFG, $DB;

        $data = $this->get_core_user_data();
        $data['useraction'] = 'add';
        $provider = new \rlipimport_version2_importprovider_mockuser($data);

        $importplugin = \rlip_dataplugin_factory::factory('dhimport_version2', $provider, null, true);
        $importplugin->run();

        $password = $data['password'];
        unset($data['password']);
        unset($data['entity']);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;

        $exists = $DB->record_exists('user', $data);
        $this->assertTrue($exists);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => $data['username']));
        $this->assertTrue(validate_internal_user_password($userrec, $password));
    }

    /**
     * Validate that non-required fields are set to specified values during user creation.
     */
    public function test_version2importsetsnonrequireduserfieldsoncreate() {
        global $CFG, $DB;

        set_config('allowuserthemes', 1);

        $data = array(
            'country' => 'CA',
            'auth' => 'mnet',
            'maildigest' => '2',
            'autosubscribe' => '1',
            'trackforums' => '1',
            'timezone' => 'America/Toronto',
            'theme' => 'bootstrapbase',
            'lang' => 'en',
            'description' => 'rlipdescription',
            'idnumber' => 'rlipidnumber',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        );

        $this->run_core_user_import($data);

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   auth = :auth AND
                   maildigest = :maildigest AND
                   autosubscribe = :autosubscribe AND
                   trackforums = :trackforums AND
                   timezone = :timezone AND
                   theme = :theme AND
                   lang = :lang AND
                   {$DB->sql_compare_text('description')} = :description AND
                   idnumber = :idnumber AND
                   institution = :institution AND
                   department = :department";

        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => 'mnet',
            'maildigest' => 2,
            'autosubscribe' => 1,
            'trackforums' => 1,
            'timezone' => 'America/Toronto',
            'theme' => 'bootstrapbase',
            'lang' => 'en',
            'description' => 'rlipdescription',
            'idnumber' => 'rlipidnumber',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        );

        $exists = $DB->record_exists_select('user', $select, $params);
        $this->assertEquals($exists, true);
    }

    /**
     * Validate that fields are set to specified values during user update.
     */
    public function test_version2importsetsfieldsonuserupdate() {
        global $CFG, $DB;
        $CFG->allowuserthemes = true;
        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'auth' => 'mnet',
            'maildigest' =>  '2',
            'autosubscribe' => '1',
            'trackforums' => '1',
            'timezone' => 'America/Toronto',
            'theme' => 'bootstrapbase',
            'lang' => 'en',
            'description' => 'rlipdescription',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        );

        $this->run_core_user_import($data, false);

        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   auth = :auth AND
                   maildigest = :maildigest AND
                   autosubscribe = :autosubscribe AND
                   trackforums = :trackforums AND
                   timezone = :timezone AND
                   theme = :theme AND
                   lang = :lang AND
                   {$DB->sql_compare_text('description')} = :description AND
                   institution = :institution AND
                   department = :department";

        $exists = $DB->record_exists_select('user', $select, $data);
        $this->assertTrue($exists);
    }

    /**
     * Validate that fields are set to specified values during user update.
     */
    public function test_version2importsetsfieldsonextendeduserupdate() {
        global $CFG, $DB;
        $CFG->allowuserthemes = true;
        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'user_username' => 'rlipusername',
            'auth' => 'mnet',
            'maildigest' => '2',
            'autosubscribe' => '1',
            'trackforums' => '1',
            'timezone' => 'America/Toronto',
            'theme' => 'bootstrapbase',
            'lang' => 'en',
            'description' => 'rlipdescription',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        );

        $this->run_core_user_import($data, false);

        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $data['username'] = $data['user_username'];
        unset($data['user_username']);

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   auth = :auth AND
                   maildigest = :maildigest AND
                   autosubscribe = :autosubscribe AND
                   trackforums = :trackforums AND
                   timezone = :timezone AND
                   theme = :theme AND
                   lang = :lang AND
                   {$DB->sql_compare_text('description')} = :description AND
                   institution = :institution AND
                   department = :department";
        $exists = $DB->record_exists_select('user', $select, $data);

        $this->assertTrue($exists);
    }

    /**
     * Validate that yes/no fields are mapped to valid values during user update.
     */
    public function test_version2importmapsfieldsonuserupdate() {
        global $CFG, $DB;
        $CFG->allowuserthemes = true;
        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'auth' => 'mnet',
            'maildigest' => '2',
            'autosubscribe' => 'yes',
            'trackforums' => 'yes',
            'timezone' => 'America/Toronto',
            'theme' => 'bootstrapbase',
            'lang' => 'en',
            'description' => 'rlipdescription',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        );

        $this->run_core_user_import($data, false);

        foreach ($data as $key => $val) {
            if (in_array((string)$val, array('no', 'yes'))) {
                $data[$key] = ((string)$val == 'yes') ? 1: 0;
            }
        }
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   auth = :auth AND
                   maildigest = :maildigest AND
                   autosubscribe = :autosubscribe AND
                   trackforums = :trackforums AND
                   timezone = :timezone AND
                   theme = :theme AND
                   lang = :lang AND
                   {$DB->sql_compare_text('description')} = :description AND
                   institution = :institution AND
                   department = :department";

        $exists = $DB->record_exists_select('user', $select, $data);
        $this->assertEquals($exists, true);
    }

    /**
     * Validate that invalid auth plugins can't be set on user creation.
     */
    public function test_version2importpreventsinvaliduserauthoncreate() {
        $this->run_core_user_import(array('auth' => 'invalidauth'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid auth plugins can't be set on user update.
     */
    public function test_version2importpreventsinvaliduserauthonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import([]);

        $data = ['useraction' => 'update', 'username' => 'rlipusername', 'auth' => 'bogus'];

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = [
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => 'manual'
        ];
        $this->assert_record_exists('user', $params);
    }

    protected function set_password_policy_for_tests() {
        global $CFG;
        $CFG->passwordpolicy = true;
        $CFG->minpasswordlength = 8;
        $CFG->minpassworddigits = 1;
        $CFG->minpasswordlower = 1;
        $CFG->minpasswordupper = 1;
        $CFG->minpasswordnonalphanum = 1;
        $CFG->maxconsecutiveidentchars = 0;
    }

    /**
     * Validate that supplied passwords must match the site's password policy on user creation.
     */
    public function test_version2importpreventsinvaliduserpasswordoncreate() {
        $this->set_password_policy_for_tests();
        $this->run_core_user_import(array('password' => 'asdf'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that supplied passwords must match the site's password policy on user update.
     */
    public function test_version2importpreventsinvaliduserpasswordonupdate() {
        global $CFG, $DB;
        $this->set_password_policy_for_tests();
        $this->run_core_user_import([]);

        $data = ['useraction' => 'update', 'username' => 'rlipusername', 'password' => 'asdf'];

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = [
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        $this->assert_record_exists('user', $params);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => 'rlipusername'));
        $this->assertTrue(validate_internal_user_password($userrec, 'Rlippassword!1234'));
    }

    /**
     * Validate that changeme can be used as valid password
     * if allowed in config on user update
     */
    public function test_version1importallowschangemepasswordonupdate() {
        global $CFG, $DB;
        $this->set_password_policy_for_tests();
        $this->run_core_user_import([]);

        $data = ['useraction' => 'update', 'username' => 'rlipusername', 'password' => 'changeme'];

        $this->run_core_user_import($data, false);

        // Set the option to allow using the literal value 'changeme'
        $CFG->allowchangemepass = true;

        // Make sure the data hasn't changed.
        $params = [
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
        ];
        $this->assert_record_exists('user', $params);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => 'rlipusername'));
        $this->assertTrue(validate_internal_user_password($userrec, 'changeme'));
    }

    /**
     * Validate that invalid email addresses can't be set on user creation.
     */
    public function test_version2importpreventsinvaliduseremailoncreate() {
        $this->run_core_user_import(array('email' => 'invalidemail'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid maildigest values can't be set on user creation.
     */
    public function test_version2importpreventsinvalidusermaildigestoncreate() {
        $this->run_core_user_import(array('maildigest' => '3'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid maildigest values can't be set on user update.
     */
    public function test_version2importpreventsinvalidusermaildigestonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        $data = array('useraction' => 'update', 'username' => 'rlipusername', 'maildigest' => '3');

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'maildigest' => 0
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid autosubscribe values can't be set on user creation.
     */
    public function test_version2importpreventsinvaliduserautosubscribeoncreate() {
        $this->run_core_user_import(array('autosubscribe' => '3'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid autosubscribe values can't be set on user update.
     */
    public function test_version2importpreventsinvaliduserautosubscribeonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        $data = array('useraction' => 'update', 'username' => 'rlipusername', 'autosubscribe' => '3');

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'autosubscribe' => 1
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid trackforums values can't be set on user creation.
     */
    public function test_version2importpreventsinvalidusertrackforumsoncreate() {
        set_config('forum_trackreadposts', 0);

        $this->run_core_user_import(array('trackforums' => '1'));
        $this->assert_core_user_does_not_exist();

        set_config('forum_trackreadposts', 1);

        $this->run_core_user_import(array('trackforums' => '2'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid trackforums values can't be set on user update.
     */
    public function test_version2importpreventsinvalidusertrackforumsonupdate() {
        global $CFG, $DB;

        set_config('forum_trackreadposts', 0);
        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'trackforums' => '1'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'trackforums' => 0
        );
        $this->assert_record_exists('user', $params);

        set_config('forum_trackreadposts', 1);
        $data['trackforums'] = '2';
        $this->run_core_user_import($data);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'trackforums' => 0
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid screenreader values can't be set on user creation.
     */
    public function test_version2importpreventsinvaliduserscreenreaderoncreate() {
        $this->run_core_user_import(array('screenreader' => '2'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid screenreader values can't be set on user update.
     */
    public function test_version2importpreventsinvaliduserscreenreaderonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id);
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid country values can't be set on user creation.
     */
    public function test_version2importpreventsinvalidusercountryoncreate() {
        $this->run_core_user_import(array('country' => '12'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid country values can't be set on user update.
     */
    public function test_version2importpreventsinvalidusercountryonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array('country' => 'CA'));

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'country' => '12'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'country' => 'CA'
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid timezone values can't be set on user creation.
     */
    public function test_version2importpreventsinvalidusertimezoneoncreate() {
        $this->run_core_user_import(array('timezone' => '14.0'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid timezone values can't be set on user update.
     */
    public function test_version2importpreventsinvalidusertimezoneonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'timezone' => '14.0'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'timezone' => 99
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that timezone values can't be set on user creation when they are forced globally.
     */
    public function test_version2importpreventsoverridingforcedtimezoneoncreate() {
        set_config('forcetimezone', 10);

        $this->run_core_user_import(array('timezone' => '5.0'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that timezone values can't be set on user update when they are forced globally.
     */
    public function test_version2importpreventsoverridingforcedtimezoneonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        set_config('forcetimezone', 10);

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'timezone' => '5.0'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'timezone' => 99
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid theme values can't be set on user creation.
     */
    public function test_version2importpreventsinvaliduserthemeoncreate() {
        set_config('allowuserthemes', 0);

        $this->run_core_user_import(array('theme' => 'rlmaster'));
        $this->assert_core_user_does_not_exist();

        set_config('allowuserthemes', 1);

        $this->run_core_user_import(array('theme' => 'bogus'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid theme values can't be set on user update.
     */
    public function test_version2importpreventsinvaliduserthemeonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        set_config('allowuserthemes', 0);

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'theme' => 'rlmaster'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'theme' => ''
        );
        $this->assert_record_exists('user', $params);

        set_config('allowuserthemes', 1);

        $data['theme'] = 'bogus';

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'theme' => ''
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that invalid lang values can't be set on user creation.
     */
    public function test_version2importpreventsinvaliduserlangoncreate() {
        $this->run_core_user_import(array('lang' => '12'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that invalid lang values can't be set on user update.
     */
    public function test_version2importpreventsinvaliduserlangonupdate() {
        global $DB, $CFG;

        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'lang' => '12'
        );

        $this->run_core_user_import($data, false);

        // Make sure the data hasn't changed.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'lang' => $CFG->lang
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that the import defaults to not setting idnumber values if
     * a value is not supplied and ELIS is not configured to auto-assign.
     */
    public function test_version2importdoesnotsetidnumberwhennotsuppliedorconfigured() {
        global $CFG;
        require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');

        // Make sure we are not auto-assigning idnumbers.
        set_config('auto_assign_user_idnumber', 0, 'local_elisprogram');
        elis::$config = new elis_config();

        $this->run_core_user_import(array());

        // Make sure idnumber wasn't set.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => ''
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate the the import can set a user's idnumber value on user creation.
     */
    public function test_version2importsetssuppliedidnumberoncreate() {
        global $CFG;
        require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');

        // Make sure we are not auto-assigning idnumbers.
        set_config('auto_assign_user_idnumber', 0, 'local_elisprogram');
        elis::$config = new elis_config();

        // Run the import.
        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        // Make sure idnumber was set.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber'
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate the the import can't set a user's idnumber value on user update.
     */
    public function test_version2importdoesnotsetsuppliedidnumberonupdate() {
        global $CFG;
        require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');

        // Make sure we are not auto-assigning idnumbers.
        set_config('auto_assign_user_idnumber', 0, 'local_elisprogram');
        elis::$config = new elis_config();

        // Create the user.
        $this->run_core_user_import(array());

        // Run the import.
        $this->run_core_user_import(array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'idnumber' => 'rlipidnumber'
        ));

        // Make sure the idnumber was not set.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => ''
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate the the import can set a user's idnumber value on user update (ELIS-9373).
     */
    public function test_version2importdoessetsuppliedidnumberonupdate() {
        global $CFG;
        require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');

        // Make sure we are not auto-assigning idnumbers.
        set_config('auto_assign_user_idnumber', 0, 'local_elisprogram');
        elis::$config = new elis_config();

        // Disable idnumber as identifying field
        set_config('identfield_idnumber', 0, 'dhimport_version2');

        // Create the user.
        $this->run_core_user_import(array());

        // Run the import.
        $this->run_core_user_import(array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'idnumber' => 'rlipidnumber'
        ));

        // Make sure the idnumber was set.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber'
        );
        $this->assert_record_exists('user', $params);
    }

    /**
     * Validate that default values are correctly set on user creation.
     */
    public function test_version2importsetsdefaultsonusercreate() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/eliscore/lib/setup.php');

        set_config('forcetimezone', 99);

        // Make sure we are not auto-assigning idnumbers.
        set_config('auto_assign_user_idnumber', 0, 'local_elisprogram');
        elis::$config = new elis_config();

        $this->run_core_user_import(array());

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   auth = :auth AND
                   maildigest = :maildigest AND
                   autosubscribe = :autosubscribe AND
                   trackforums = :trackforums AND
                   timezone = :timezone AND
                   theme = :theme AND
                   lang = :lang AND
                   {$DB->sql_compare_text('description')} = :description AND
                   idnumber = :idnumber AND
                   institution = :institution AND
                   department = :department";

        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => 'manual',
            'maildigest' => 0,
            'autosubscribe' => 1,
            'trackforums' => 0,
            'timezone' => 99,
            'theme' => '',
            'lang' => $CFG->lang,
            'description' => '',
            'idnumber' => '',
            'institution' => '',
            'department' => ''
        );
        $exists = $DB->record_exists_select('user', $select, $params);
        $this->assertEquals(true, $exists);
    }

    /**
     * Validate that the import does not set unsupported fields on user creation.
     */
    public function test_version2importpreventssettingunsupporteduserfieldsoncreate() {
        global $CFG, $DB;

        $data = array();
        $data['forcepasswordchange'] = '1';
        $data['maildisplay'] = '1';
        $data['mailformat'] = '0';
        $data['descriptionformat'] = (string)FORMAT_WIKI;
        $this->run_core_user_import($data);

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   maildisplay = :maildisplay AND
                   mailformat = :mailformat AND
                   descriptionformat = :descriptionformat";

        // Make sure that a record exists with the default data rather than with the specified values.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'maildisplay' => 2,
            'mailformat' => 1,
            'descriptionformat' => FORMAT_HTML
        );
        $exists = $DB->record_exists_select('user', $select, $params);
        $this->assertEquals($exists, true);

        // Check force password change separately.
        $params = array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id);
        $user = $DB->get_record('user', $params);
        $preferences = get_user_preferences('forcepasswordchange', null, $user);

        $this->assertEquals(count($preferences), 0);
    }

    /**
     * Validate that import does not set unsupported fields on user update.
     */
    public function test_version2importpreventssettingunsupporteduserfieldsonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        $data = array();
        $data['useraction'] = 'update';
        $data['username'] = 'rlipusername';
        $data['forcepasswordchange'] = '1';
        $data['maildisplay'] = '1';
        $data['mailformat'] = '0';
        $data['descriptionformat'] = (string)FORMAT_WIKI;

        $this->run_core_user_import($data, false);

        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   maildisplay = :maildisplay AND
                   mailformat = :mailformat AND
                   descriptionformat = :descriptionformat";

        // Make sure that a record exists with the default data rather than with the specified values.
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'maildisplay' => 2,
            'mailformat' => 1,
            'descriptionformat' => FORMAT_HTML
        );
        $exists = $DB->record_exists_select('user', $select, $params);
        $this->assertEquals($exists, true);

        // Check force password change separately.
        $params = array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id);
        $user = $DB->get_record('user', $params);
        $preferences = get_user_preferences('forcepasswordchange', null, $user);

        $this->assertEquals(count($preferences), 0);
    }

    /**
     * Validate that field-length checking works correctly on user creation.
     */
    public function test_version2importpreventslonguserfieldsoncreate() {
        $this->run_core_user_import(array('firstname' => str_repeat('a', 101)));
        $this->assert_core_user_does_not_exist();

        $this->run_core_user_import(array('lastname' => str_repeat('a', 101)));
        $this->assert_core_user_does_not_exist();

        $value = str_repeat('a', 50).'@'.str_repeat('b', 50);
        $this->run_core_user_import(array('email' => $value));
        $this->assert_core_user_does_not_exist();

        $this->run_core_user_import(array('city' => str_repeat('a', 256)));
        $this->assert_core_user_does_not_exist();

        $this->run_core_user_import(array('idnumber' => str_repeat('a', 256)));
        $this->assert_core_user_does_not_exist();

        $this->run_core_user_import(array('institution' => str_repeat('a', 41)));
        $this->assert_core_user_does_not_exist();

        $this->run_core_user_import(array('department' => str_repeat('a', 31)));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that field-length checking works correct on user update.
     */
    public function test_version2importpreventslonguserfieldsonupdate() {
        global $CFG, $DB;

        $this->run_core_user_import(array(
            'idnumber' => 'rlipidnumber',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'firstname' => str_repeat('a', 101)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'firstname' => 'rlipfirstname'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'lastname' => str_repeat('a', 101)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'lastname' => 'rliplastname'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'email' => str_repeat('a', 50).'@'.str_repeat('b', 50)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'email' => 'rlipuser@rlipdomain.com'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'city' => str_repeat('a', 256)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'city' => 'rlipcity'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'idnumber' => str_repeat('a', 256)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'institution' => str_repeat('a', 41)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'institution' => 'rlipinstitution'
        ));

        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'department' => str_repeat('a', 31)
        );
        $this->run_core_user_import($params, false);
        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'department' => 'rlipdepartment'
        ));
    }

    /**
     * Validate that setting profile fields works on user creation.
     */
    public function test_version2importsetsuserprofilefieldsoncreate() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/user/profile/lib.php');
        require_once($CFG->dirroot.'/user/profile/definelib.php');

        // Create custom field category.
        $category = new stdClass;
        $category->sortorder = $DB->count_records('user_info_category') + 1;
        $category->id = $DB->insert_record('user_info_category', $category);

        // Create custom profile fields.
        $this->create_profile_field('rlipcheckbox', 'checkbox', $category->id);
        $this->create_profile_field('rlipdatetime', 'datetime', $category->id, 2000, 3000);
        $this->create_profile_field('rliplegacydatetime', 'datetime', $category->id, 2000, 3000);
        $this->create_profile_field('rlipmenu', 'menu', $category->id, "rlipoption1\nrlipoption2");
        $this->create_profile_field('rliptextarea', 'textarea', $category->id);
        $this->create_profile_field('rliptext', 'text', $category->id);

        // Run import.
        $data = array();
        $data['profile_field_rlipcheckbox'] = '1';
        $data['profile_field_rlipdatetime'] = 'jan/12/2011';
        $data['profile_field_rliplegacydatetime'] = '1/12/2011';
        $data['profile_field_rlipmenu'] = 'rlipoption1';
        $data['profile_field_rliptextarea'] = 'rliptextarea';
        $data['profile_field_rliptext'] = 'rliptext';

        $this->run_core_user_import($data);

        // Fetch the user and their profile field data.
        $user = $DB->get_record('user', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));
        profile_load_data($user);
        fix_moodle_profile_fields($user);

        // Validate data.
        $this->assertEquals(isset($user->profile_field_rlipcheckbox), true);
        $this->assertEquals($user->profile_field_rlipcheckbox, 1);
        $this->assertEquals(isset($user->profile_field_rlipdatetime), true);
        $this->assertEquals($user->profile_field_rlipdatetime, rlip_timestamp(0, 0, 0, 1, 12, 2011));
        $this->assertEquals(isset($user->profile_field_rliplegacydatetime), true);
        $this->assertEquals($user->profile_field_rliplegacydatetime, rlip_timestamp(0, 0, 0, 1, 12, 2011));
        $this->assertEquals(isset($user->profile_field_rlipmenu), true);
        $this->assertEquals($user->profile_field_rlipmenu, 'rlipoption1');
        $this->assertEquals(isset($user->profile_field_rliptextarea['text']), true);
        $this->assertEquals($user->profile_field_rliptextarea['text'], 'rliptextarea');
        $this->assertEquals(isset($user->profile_field_rliptext), true);
        $this->assertEquals($user->profile_field_rliptext, 'rliptext');
    }

    /**
     * Validate that setting profile fields works on user update.
     */
    public function test_version2importsetsuserprofilefieldsonupdate() {
        global $CFG, $DB;

        // Perform default "user create" import.
        $this->run_core_user_import(array());

        // Create custom field category.
        $category = new stdClass;
        $category->sortorder = $DB->count_records('user_info_category') + 1;
        $category->id = $DB->insert_record('user_info_category', $category);

        // Create custom profile fields.
        $this->create_profile_field('rlipcheckbox', 'checkbox', $category->id);
        $this->create_profile_field('rlipdatetime', 'datetime', $category->id, 2000, 3000);
        $this->create_profile_field('rliplegacydatetime', 'datetime', $category->id, 2000, 3000);
        $this->create_profile_field('rlipmenu', 'menu', $category->id, "rlipoption1\nrlipoption2");
        $this->create_profile_field('rliptextarea', 'textarea', $category->id);
        $this->create_profile_field('rliptext', 'text', $category->id);

        // Run import.
        $data = array();
        $data['useraction'] = 'update';
        $data['username'] = 'rlipusername';
        $data['profile_field_rlipcheckbox'] = '1';
        $data['profile_field_rlipdatetime'] = 'jan/12/2011';
        $data['profile_field_rliplegacydatetime'] = '1/12/2011';
        $data['profile_field_rlipmenu'] = 'rlipoption1';
        $data['profile_field_rliptextarea'] = 'rliptextarea';
        $data['profile_field_rliptext'] = 'rliptext';

        $this->run_core_user_import($data, false);

        // Fetch the user and their profile field data.
        $user = $DB->get_record('user', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));
        profile_load_data($user);
        fix_moodle_profile_fields($user);

        // Validate data.
        $this->assertEquals(isset($user->profile_field_rlipcheckbox), true);
        $this->assertEquals($user->profile_field_rlipcheckbox, 1);
        $this->assertEquals(isset($user->profile_field_rlipdatetime), true);
        $this->assertEquals($user->profile_field_rlipdatetime, rlip_timestamp(0, 0, 0, 1, 12, 2011));
        $this->assertEquals(isset($user->profile_field_rliplegacydatetime), true);
        $this->assertEquals($user->profile_field_rliplegacydatetime, rlip_timestamp(0, 0, 0, 1, 12, 2011));
        $this->assertEquals(isset($user->profile_field_rlipmenu), true);
        $this->assertEquals($user->profile_field_rlipmenu, 'rlipoption1');
        $this->assertEquals(isset($user->profile_field_rliptextarea['text']), true);
        $this->assertEquals($user->profile_field_rliptextarea['text'], 'rliptextarea');
        $this->assertEquals(isset($user->profile_field_rliptext), true);
        $this->assertEquals($user->profile_field_rliptext, 'rliptext');
    }

    /**
     * Validate that the import does not create bogus profile field data on user creation.
     */
    public function test_version2importvalidatesprofilefieldsoncreate() {
        global $DB;

        $category = new stdClass;
        $category->sortorder = $DB->count_records('user_info_category') + 1;
        $category->id = $DB->insert_record('user_info_category', $category);

        $this->create_profile_field('rlipcheckbox', 'checkbox', $category->id);
        $this->run_core_user_import(array('profile_field_rlipcheckbox' => '2'));
        $this->assert_core_user_does_not_exist();

        $this->create_profile_field('rlipdatetime', 'datetime', $category->id);
        $this->run_core_user_import(array('profile_field_rlipdatetime' => '1000000000'));
        $this->assert_core_user_does_not_exist();

        $this->create_profile_field('rlipmenu', 'menu', $category->id, "rlipoption1\nrlipoption1B");
        $this->run_core_user_import(array('profile_field_rlipmenu' => 'rlipoption2'));
        $this->assert_core_user_does_not_exist();
    }

    /**
     * Validate that the import does not create bogus profile field data on user update.
     */
    public function test_version2importvalidatesprofilefieldsonupdate() {
        global $CFG, $DB;

        // Run the "create user" import.
        $this->run_core_user_import(array());

        $userid = $DB->get_field('user', 'id', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));

        // Create the category.
        $category = new stdClass;
        $category->sortorder = $DB->count_records('user_info_category') + 1;
        $category->id = $DB->insert_record('user_info_category', $category);

        // Try to insert bogus checkbox data.
        $this->create_profile_field('rlipcheckbox', 'checkbox', $category->id);
        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'profile_field_rlipcheckbox' => '2'
        );
        $this->run_core_user_import($params);
        $user = new stdClass;
        $user->id = $userid;
        profile_load_data($user);
        fix_moodle_profile_fields($user);
        $this->assertEquals(isset($user->profile_field_rlipcheckbox), false);

        // Try to insert bogus datetime data.
        $this->create_profile_field('rlipdatetime', 'datetime', $category->id);
        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'profile_field_rlipdatetime' => '1000000000'
        );
        $this->run_core_user_import($params);
        $user = new stdClass;
        $user->id = $userid;
        profile_load_data($user);
        fix_moodle_profile_fields($user);
        $this->assertEquals(isset($user->profile_field_rlipcheckbox), false);

        // Try to insert bogus menu data.
        $this->create_profile_field('rlipmenu', 'menu', $category->id, "rlipoption1\nrlipoption1B");
        $params = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'profile_field_rlipmenu' => 'rlipoption2'
        );
        $this->run_core_user_import($params);
        $user = new stdClass;
        $user->id = $userid;
        profile_load_data($user);
        fix_moodle_profile_fields($user);
        $this->assertEquals(isset($user->profile_field_rlipcheckbox), false);
    }

    /**
     * Validate that the import does not create duplicate user records on creation.
     */
    public function test_version2importpreventsduplicateusercreation() {
        global $DB;

        $initialcount = $DB->count_records('user');

        // Set up our data.
        $this->run_core_user_import(array('idnumber' => 'testdupidnumber'));
        $count = $DB->count_records('user');
        $this->assertEquals($initialcount + 1, $count);

        // Test duplicate username.
        $data = array(
            'email' => 'testdup2@testdup2.com',
            'idnumber' => 'testdupidnumber2'
        );
        $this->run_core_user_import($data);
        $count = $DB->count_records('user');
        $this->assertEquals($initialcount + 1, $count);

        // Test duplicate email.
        $data = array(
            'username' => 'testdupusername3',
            'idnumber' => 'testdupidnumber3'
        );
        $this->run_core_user_import($data);
        $count = $DB->count_records('user');
        $this->assertEquals($initialcount + 1, $count);

        // Test duplicate idnumber.
        $data = array(
            'username' => 'testdupusername4',
            'email' => 'testdup2@testdup4.com',
            'idnumber' => 'testdupidnumber'
        );
        $this->run_core_user_import($data);
        $count = $DB->count_records('user');
        $this->assertEquals($initialcount + 1, $count);
    }

    /**
     * Validate that the plugin can update users based on any combination
     * of username, email and idnumber.
     */
    public function test_version2importupdatesbasedonidentifyingfields() {
        global $CFG;

        // Set up our data.
        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        // Update based on username.
        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'firstname' => 'setfromusername'
        );
        $this->run_core_user_import($data, false);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $this->assert_record_exists('user', $data);

        // Update based on email.
        $data = array(
            'useraction' => 'update',
            'email' => 'rlipuser@rlipdomain.com',
            'firstname' => 'setfromemail'
        );
        $this->run_core_user_import($data, false);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $this->assert_record_exists('user', $data);

        // Update based on idnumber.
        $data = array(
            'useraction' => 'update',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'setfromidnumber'
        );
        $this->run_core_user_import($data, false);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $this->assert_record_exists('user', $data);

        // Update based on username, email.
        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'email' => 'rlipuser@rlipdomain.com',
            'firstname' => 'setfromusernameemail'
        );
        $this->run_core_user_import($data, false);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $this->assert_record_exists('user', $data);

        // Update based on username, idnumber.
        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'setfromusernameidnumber'
        );
        $this->run_core_user_import($data, false);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $this->assert_record_exists('user', $data);

        // Update based on email, idnumber.
        $data = array(
            'useraction' => 'update',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'setfromemailidnumber'
        );
        $this->run_core_user_import($data, false);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $this->assert_record_exists('user', $data);
    }

    /**
     * Validate that updating users does not produce any side-effects
     * in the user data.
     */
    public function test_version2importonlyupdatessupplieduserfields() {
        global $CFG, $DB;

        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'firstname' => 'updatedfirstname'
        );

        $this->run_core_user_import($data, false);

        $data = $this->get_core_user_data();
        $password = $data['password'];
        unset($data['password']);
        unset($data['entity']);
        unset($data['useraction']);
        $data['mnethostid'] = $CFG->mnet_localhost_id;
        $data['firstname'] = 'updatedfirstname';

        $this->assert_record_exists('user', $data);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => $data['username']));
        $this->assertTrue(validate_internal_user_password($userrec, $password));
    }

    /**
     * Validate that update actions must match existing users to do anything.
     */
    public function test_version2importdoesnotupdatenonmatchingusers() {
        global $CFG;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber', 'firstname' => 'oldfirstname'));

        $checkdata = array(
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => 'rlipusername',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'oldfirstname'
        );

        // Bogus username.
        $data = array(
            'useraction' => 'update',
            'username' => 'bogususername',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Bogus email.
        $data = array(
            'useraction' => 'update',
            'email' => 'bogus@domain.com',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Bogus idnumber.
        $data = array(
            'useraction' => 'update',
            'idnumber' => 'bogusidnumber',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);
    }

    /**
     * Validate that fields identifying users in updates are not updated.
     */
    public function test_version2importdoesnotupdateidentifyinguserfields() {
        global $CFG;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber', 'firstname' => 'oldfirstname'));

        $checkdata = array(
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => 'rlipusername',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'oldfirstname'
        );

        // Valid username, bogus email.
        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'email' => 'bogus@domain.com',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Valid username, bogus idnumber.
        $data = array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'idnumber' => 'bogusidnumber',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Valid email, bogus username.
        $data = array(
            'useraction' => 'update',
            'username' => 'bogususername',
            'email' => 'rlipuser@rlipdomain.com',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Valid email, bogus idnumber.
        $data = array(
            'useraction' => 'update',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'bogusidnumber',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Valid idnumber, bogus username.
        $data = array(
            'useraction' => 'update',
            'username' => 'bogususername',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);

        // Valid idnumber, bogus email.
        $data = array(
            'useraction' => 'update',
            'email' => 'bogus@domain.com',
            'idnumber' => 'rlipidnumber',
            'firstname' => 'newfirstname'
        );
        $this->run_core_user_import($data, false);
        $this->assert_record_exists('user', $checkdata);
    }

    /**
     * Validate that user create and update actions set time created
     * and time modified appropriately.
     */
    public function test_version2importsetsusertimestamps() {
        global $CFG, $DB;

        $starttime = time();

        // Set up base data.
        $this->run_core_user_import(array());

        // Validate timestamps.
        $where = "username = ? AND
                  mnethostid = ? AND
                  timecreated >= ? AND
                  timemodified >= ?";
        $params = array('rlipusername', $CFG->mnet_localhost_id, $starttime, $starttime);
        $exists = $DB->record_exists_select('user', $where, $params);
        $this->assertEquals($exists, true);

        // Reset time modified.
        $user = new stdClass;
        $user->id = $DB->get_field('user', 'id', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));
        $user->timemodified = 0;
        $DB->update_record('user', $user);

        // Update data.
        $this->run_core_user_import(array(
            'useraction' => 'update',
            'username' => 'rlipusername',
            'firstname' => 'newfirstname'
        ));

        // Validate timestamps.
        $where = "username = ? AND
                  mnethostid = ? AND
                  timecreated >= ? AND
                  timemodified >= ?";
        $params = array('rlipusername', $CFG->mnet_localhost_id, $starttime, $starttime);
        $exists = $DB->record_exists_select('user', $where, $params);
        $this->assertEquals($exists, true);
    }

    /**
     * Validate that the version 2 plugin supports user deletes.
     */
    public function test_version2importsupportsuserdelete() {
        $supports = plugin_supports('dhimport', 'version2', 'user_delete');
        $requiredfields = array(array('user_username', 'user_email', 'user_idnumber', 'username', 'email', 'idnumber'));
        $this->assertEquals($supports, $requiredfields);
    }

    /**
     * Validate that the version 2 plugin supports user disabling.
     */
    public function test_version2importsupportsuserdisable() {
        $supports = plugin_supports('dhimport', 'version2', 'user_disable');
        $requiredfields = array(array('user_username', 'user_email', 'user_idnumber', 'username', 'email', 'idnumber'));
        $this->assertEquals($supports, $requiredfields);
    }

    /**
     * Validate that the version 2 plugin can delete uses based on username.
     */
    public function test_version2importdeletesuserbasedonusername() {
        global $CFG, $DB;
        set_config('siteguest', 0);

        $this->run_core_user_import(array());
        $userid = $DB->get_field('user', 'id', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));

        $data = array('useraction' => 'delete', 'username' => 'rlipusername');
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

    /**
     * Validate that the version 2 plugin can delete uses based on email.
     */
    public function test_version2importdeletesuserbasedonemail() {
        global $DB;

        $this->run_core_user_import(array());
        $userid = $DB->get_field('user', 'id', array('email' => 'rlipuser@rlipdomain.com'));

        $data = array('useraction' => 'delete', 'email' => 'rlipuser@rlipdomain.com');
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

     /**
      * Validate that the version 2 plugin can delete uses based on idnumber.
      */
    public function test_version2importdeletesuserbasedonidnumber() {
        global $DB;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));
        $userid = $DB->get_field('user', 'id', array('idnumber' => 'rlipidnumber'));

        $data = array('useraction' => 'delete', 'idnumber' => 'rlipidnumber');
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

    /**
     * Validate that the version 2 plugin can delete uses based on username and
     * email.
     */
    public function test_version2importdeletesuserbasedonusernameemail() {
        global $CFG, $DB;

        $this->run_core_user_import(array());
        $userid = $DB->get_field('user', 'id', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'email' => 'rlipuser@rlipdomain.com'
        ));

        $data = array(
            'useraction' => 'delete',
            'username' => 'rlipusername',
            'email' => 'rlipuser@rlipdomain.com'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

    /**
     * Validate that the version 2 plugin can delete uses based on username and
     * idnumber.
     */
    public function test_version2importdeletesuserbasedonusernameidnumber() {
        global $CFG, $DB;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));
        $userid = $DB->get_field('user', 'id', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber'
        ));

        $data = array(
            'useraction' => 'delete',
            'username' => 'rlipusername',
            'idnumber' => 'rlipidnumber'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

    /**
     * Validate that the version 2 plugin can delete uses based on email and
     * idnumber.
     */
    public function test_version2importdeletesuserbasedonemailidnumber() {
        global $DB;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));
        $userid = $DB->get_field('user', 'id', array('email' => 'rlipuser@rlipdomain.com', 'idnumber' => 'rlipidnumber'));

        $data = array(
            'useraction' => 'delete',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

    /**
     * Validate that the version 2 plugin can delete uses based on username, email and
     * idnumber.
     */
    public function test_version2importdeletesuserbasedonusernameemailidnumber() {
        global $CFG, $DB;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));
        $userid = $DB->get_field('user', 'id', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber'
        ));

        $data = array(
            'useraction' => 'delete',
            'username' => 'rlipusername',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('id' => $userid, 'deleted' => 1));
    }

    /**
     * Validate that the version 2 plugin does not delete users when the
     * specified username is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithinvalidusername() {
        global $CFG;

        $this->run_core_user_import(array());

        $data = array('useraction' => 'delete', 'username' => 'bogususername');
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin does not delete users when the
     * specified email is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithinvalidemail() {
        $this->run_core_user_import(array());

        $data = array('useraction' => 'delete', 'email' => 'bogus@domain.com');
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('email' => 'rlipuser@rlipdomain.com', 'deleted' => 0));
    }

    /**
     * Validate that the version 2 plugin does not delete users when the
     * specified idnumber is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithinvalididnumber() {
        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        $data = array('useraction' => 'delete', 'idnumber' => 'bogusidnumber');
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array('idnumber' => 'rlipidnumber', 'deleted' => 0));
    }

    /**
     * Validate that the version 2 plugin does not delete a user with the
     * specified username if the specified email is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithvalidusernameinvalidemail() {
        global $CFG;

        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'delete',
            'username' => 'rlipusername',
            'email' => 'bogus@domain.com'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'email' => 'rlipuser@rlipdomain.com',
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin does not delete a user with the
     * specified username if the specified idnumber is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithvalidusernameinvalididnumber() {
        global $CFG;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        $data = array(
            'useraction' => 'delete',
            'username' => 'rlipusername',
            'idnumber' => 'bogusidnumber'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'rlipidnumber',
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin does not delete a user with the
     * specified email if the specified username is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithvalidemailinvalidusername() {
        global $CFG;

        $this->run_core_user_import(array());

        $data = array(
            'useraction' => 'delete',
            'email' => 'rlipuser@rlipdomain.com',
            'username' => 'bogususername'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'email' => 'rlipuser@rlipdomain.com',
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin does not delete a user with the
     * specified email if the specified idnumber is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithvalidemailinvalididnumber() {
        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        $data = array(
            'useraction' => 'delete',
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'bogusidnumber'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'email' => 'rlipuser@rlipdomain.com',
            'idnumber' => 'rlipidnumber',
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin does not delete a user with the
     * specified idnumber if the specified username is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithvalididnumberinvalidusername() {
        global $CFG;

        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        $data = array(
            'useraction' => 'delete',
            'idnumber' => 'rlipidnumber',
            'username' => 'bogususername'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'idnumber' => 'rlipidnumber',
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin does not delete a user with the
     * specified idnumber if the specified email is incorrect.
     */
    public function test_version2importdoesnotdeleteuserwithvalididnumberinvalidemail() {
        $this->run_core_user_import(array('idnumber' => 'rlipidnumber'));

        $data = array(
            'useraction' => 'delete',
            'idnumber' => 'rlipidnumber',
            'email' => 'bogus@domain.com'
        );
        $this->run_core_user_import($data, false);

        $this->assert_record_exists('user', array(
            'idnumber' => 'rlipidnumber',
            'email' => 'rlipuser@rlipdomain.com',
            'deleted' => 0
        ));
    }

    /**
     * Validate that the version 2 plugin deletes appropriate associations when
     * deleting a user.
     */
    public function test_version2importdeleteuserdeletesassociations() {
        global $CFG, $DB;
        set_config('siteadmins', 0);
        // New config settings needed for course format refactoring in 2.4.
        set_config('numsections', 15, 'moodlecourse');
        set_config('hiddensections', 0, 'moodlecourse');
        set_config('coursedisplay', 1, 'moodlecourse');

        require_once($CFG->dirroot.'/cohort/lib.php');
        require_once($CFG->dirroot.'/course/lib.php');
        require_once($CFG->dirroot.'/group/lib.php');
        require_once($CFG->dirroot.'/lib/enrollib.php');
        require_once($CFG->dirroot.'/lib/gradelib.php');

        // Create our test user, and determine their userid.
        $this->run_core_user_import(array());
        $userid = (int)$DB->get_field('user', 'id', array('username' => 'rlipusername', 'mnethostid' => $CFG->mnet_localhost_id));

        // Create cohort.
        $cohort = new stdClass();
        $cohort->name = 'testcohort';
        $cohort->contextid = context_system::instance()->id;
        $cohortid = cohort_add_cohort($cohort);

        // Add the user to the cohort.
        cohort_add_member($cohortid, $userid);

        // Create a course category - there is no API for doing this.
        $category = new stdClass;
        $category->name = 'testcategory';
        $category->id = $DB->insert_record('course_categories', $category);

        // Create a course.
        set_config('defaultenrol', 1, 'enrol_manual');
        set_config('status', ENROL_INSTANCE_ENABLED, 'enrol_manual');
        $course = new stdClass;
        $course->category = $category->id;
        $course->fullname = 'testfullname';
        $course = create_course($course);

        // Create a grade.
        $gradeitem = new grade_item(array('courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'testitem'), false);
        $gradeitem->insert();
        $gradegrade = new grade_grade(array('itemid' => $gradeitem->id, 'userid' => $userid), false);
        $gradegrade->insert();

        // Send the user an unprocessed message.
        set_config('noemailever', true);

        // Set up a user tag.
        core_tag_tag::set_item_tags(null, 'user', $userid, context_user::instance($userid), ['testtag']);

        // Create a new course-level role.
        $roleid = create_role('testrole', 'testrole', 'testrole');
        set_role_contextlevels($roleid, array(CONTEXT_COURSE));

        // Enrol the user in the course with the new role.
        enrol_try_internal_enrol($course->id, $userid, $roleid);

        // Create a group.
        $group = new stdClass;
        $group->name = 'testgroup';
        $group->courseid = $course->id;
        $groupid = groups_create_group($group);

        // Add the user to the group.
        groups_add_member($groupid, $userid);

        set_user_preference('testname', 'testvalue', $userid);

        // Create profile field data - don't both with the API here because it's a bit unwieldy.
        $userinfodata = new stdClass;
        $userinfodata->fieldid = 1;
        $userinfodata->data = 'bogus';
        $userinfodata->userid = $userid;
        $DB->insert_record('user_info_data', $userinfodata);

        // There is no easily accessible API for doing this.
        $lastaccess = new stdClass;
        $lastaccess->userid = $userid;
        $lastaccess->courseid = $course->id;
        $DB->insert_record('user_lastaccess', $lastaccess);

        $data = array('useraction' => 'delete', 'username' => 'rlipusername');
        $this->run_core_user_import($data, false);

        // Assert data condition after delete.
        $this->assertEquals($DB->count_records('message_read', array('useridto' => $userid)), 0);
        $this->assertEquals($DB->count_records('grade_grades'), 0);
        // Following line fails >= m31 and is testing Moodle not DataHub.
        // $this->assertEquals($DB->count_records('tag_instance'), 0);
        $this->assertEquals($DB->count_records('cohort_members'), 0);
        $this->assertEquals($DB->count_records('user_enrolments'), 0);
        $this->assertEquals($DB->count_records('role_assignments'), 0);
        $this->assertEquals($DB->count_records('groups_members'), 0);
        $this->assertEquals($DB->count_records('user_preferences'), 0);
        $this->assertEquals($DB->count_records('user_info_data'), 0);
        $this->assertEquals($DB->count_records('user_lastaccess'), 0);
    }

    /**
     * Validate that the version 2 import plugin correctly uses field mappings
     * on user creation.
     */
    public function test_version2importusesuserfieldmappings() {
        global $CFG, $DB;
        $file = core_component::get_plugin_directory('dhimport', 'version2').'/lib.php';
        require_once($file);
        $CFG->allowuserthemes = true;

        // Set up our mapping of standard field names to custom field names.
        $mapping = array(
            'username' => 'username1',
            'auth' => 'auth1',
            'password' => 'password1',
            'firstname' => 'firstname1',
            'lastname' => 'lastname1',
            'email' => 'email1',
            'maildigest' => 'maildigest1',
            'autosubscribe' => 'autosubscribe1',
            'trackforums' => 'trackforums1',
            'city' => 'city1',
            'country' => 'country1',
            'timezone' => 'timezone1',
            'theme' => 'theme1',
            'lang' => 'lang1',
            'description' => 'description1',
            'idnumber' => 'idnumber1',
            'institution' => 'institution1',
            'department' => 'department1'
        );

        // Store the mapping records in the database.
        foreach ($mapping as $standardfieldname => $customfieldname) {
            $record = new stdClass;
            $record->entitytype = 'user';
            $record->standardfieldname = $standardfieldname;
            $record->customfieldname = $customfieldname;
            $DB->insert_record(RLIPIMPORT_VERSION2_MAPPING_TABLE, $record);
        }

        // Run the import.
        $data = array(
            'useraction' => 'create',
            'username1' => 'rlipusername',
            'auth1' => 'mnet',
            'password1' => 'Rlippassword!0',
            'firstname1' => 'rlipfirstname',
            'lastname1' => 'rliplastname',
            'email1' => 'rlipuser@rlipdomain.com',
            'maildigest1' => '2',
            'autosubscribe1' => '1',
            'trackforums1' => '1',
            'city1' => 'rlipcity',
            'country1' => 'CA',
            'timezone1' => 'America/Toronto',
            'theme1' => 'bootstrapbase',
            'lang1' => 'en',
            'description1' => 'rlipdescription',
            'idnumber1' => 'rlipidnumber',
            'institution1' => 'rlipinstitution',
            'department1' => 'rlipdepartment'
        );
        $this->run_core_user_import($data, false);

        // Validate user record.
        $select = "username = :username AND
                   mnethostid = :mnethostid AND
                   auth = :auth AND
                   firstname = :firstname AND
                   lastname = :lastname AND
                   email = :email AND
                   maildigest = :maildigest AND
                   autosubscribe = :autosubscribe AND
                   trackforums = :trackforums AND
                   city = :city AND
                   country = :country AND
                   theme = :theme AND
                   lang = :lang AND
                   {$DB->sql_compare_text('description')} = :description AND
                   idnumber = :idnumber AND
                   institution = :institution AND
                   department = :department";
        $params = array(
            'username' => 'rlipusername',
            'mnethostid' => $CFG->mnet_localhost_id,
            'auth' => 'mnet',
            'firstname' => 'rlipfirstname',
            'lastname' => 'rliplastname',
            'email' => 'rlipuser@rlipdomain.com',
            'maildigest' => 2,
            'autosubscribe' => 1,
            'trackforums' => 1,
            'city' => 'rlipcity',
            'country' => 'CA',
            'timezone' => 'America/Toronto',
            'theme' => 'bootstrapbase',
            'lang' => 'en',
            'description' => 'rlipdescription',
            'idnumber' => 'rlipidnumber',
            'institution' => 'rlipinstitution',
            'department' => 'rlipdepartment'
        );
        $exists = $DB->record_exists_select('user', $select, $params);
        $this->assertTrue($exists);

        // Validate password.
        $userrec = $DB->get_record('user', array('username' => $data['username1']));
        $this->assertTrue(validate_internal_user_password($userrec, $data['password1']));
    }

    /**
     * Validate that the import succeeds with fixed-size fields at their
     * maximum sizes.
     */
    public function test_version2importsucceedswithmaxlengthuserfields() {
        // Data for all fixed-size fields at their maximum sizes.
        $data = array(
            'username' => str_repeat('x', 100),
            'firstname' => str_repeat('x', 100),
            'lastname' => str_repeat('x', 100),
            'email' => str_repeat('x', 47).'@'.str_repeat('x', 48).'.com',
            'city' => str_repeat('x', 120),
            'idnumber' => str_repeat('x', 255),
            'institution' => str_repeat('x', 40),
            'department' => str_repeat('x', 30)
        );
        // Run the import.
        $this->run_core_user_import($data);

        // Data validation.
        $this->assert_record_exists('user', $data);
    }

    /**
     * Validate version2 import detects duplicate users under a specific condition.
     *
     * This verifies that duplicate users are detected when:
     *     - The user being imported has the email of an existing user, the username of another existing user, and the
     *     allowduplicateemails setting is on.
     */
    public function test_version2importdetectsduplicateswhenmultipleexist() {
        global $DB, $CFG;
        $userone = array(
            'username' => 'three',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'one',
            'email' => 'email@example.com',
            'firstname' => 'Test',
            'lastname' => 'User'
        );

        $usertwo = array(
            'username' => 'two',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'two',
            'email' => 'email2@example.com',
            'firstname' => 'Test',
            'lastname' => 'User'
        );
        $DB->insert_record('user', $userone);
        $DB->insert_record('user', $usertwo);
        set_config('allowduplicateemails', 1, 'dhimport_version2');
        $usertoimport = array(
            'username' => 'two',
            'mnethostid' => $CFG->mnet_localhost_id,
            'idnumber' => 'two',
            'email' => 'email@example.com'
        );
        // Run the import.
        $this->run_core_user_import($usertoimport);
    }

    /**
     * Test main newusermail() function.
     */
    public function test_version2importnewuseremail() {
        global $CFG; // This is needed by the required files.
        require_once(dirname(__FILE__).'/other/rlip_importplugin_version2_fakeemail.php');
        $importplugin = new rlip_importplugin_version2_userentity_fakeemail('test');

        $testuser = new stdClass;
        $testuser->username = 'testusername';
        $testuser->idnumber = 'testidnumber';
        $testuser->firstname = 'testfirstname';
        $testuser->lastname = 'testlastname';
        $testuser->email = 'testemail@example.com';

        // Test false return when not enabled.
        set_config('newuseremailenabled', '0', 'dhimport_version2');
        set_config('newuseremailsubject', 'Test Subject', 'dhimport_version2');
        set_config('newuseremailtemplate', 'Test Body', 'dhimport_version2');
        $result = $importplugin->newuseremail($testuser);
        $this->assertFalse($result);

        // Test false return when enabled but empty template.
        set_config('newuseremailenabled', '1', 'dhimport_version2');
        set_config('newuseremailsubject', 'Test Subject', 'dhimport_version2');
        set_config('newuseremailtemplate', '', 'dhimport_version2');
        $result = $importplugin->newuseremail($testuser);
        $this->assertFalse($result);

        // Test false return when enabled and has template, but user has empty email.
        set_config('newuseremailenabled', '1', 'dhimport_version2');
        set_config('newuseremailsubject', 'Test Subject', 'dhimport_version2');
        set_config('newuseremailtemplate', 'Test Body', 'dhimport_version2');
        $testuser->email = '';
        $result = $importplugin->newuseremail($testuser);
        $this->assertFalse($result);
        $testuser->email = 'test@example.com';

        // Test success when enabled, has template text, and user has email.
        $testsubject = 'Test Subject';
        $testbody = 'Test Body';
        set_config('newuseremailenabled', '1', 'dhimport_version2');
        set_config('newuseremailsubject', $testsubject, 'dhimport_version2');
        set_config('newuseremailtemplate', $testbody, 'dhimport_version2');
        $result = $importplugin->newuseremail($testuser);
        $this->assertNotEmpty($result);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($testuser, $result['user']);
        $this->assertArrayHasKey('subject', $result);
        $this->assertEquals($testsubject, $result['subject']);
        $this->assertArrayHasKey('body', $result);
        $this->assertEquals($testbody, $result['body']);

        // Test that subject is replaced by empty string when not present.
        $testsubject = null;
        $testbody = 'Test Body';
        set_config('newuseremailenabled', '1', 'dhimport_version2');
        set_config('newuseremailsubject', $testsubject, 'dhimport_version2');
        set_config('newuseremailtemplate', $testbody, 'dhimport_version2');
        $result = $importplugin->newuseremail($testuser);
        $this->assertNotEmpty($result);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($testuser, $result['user']);
        $this->assertArrayHasKey('subject', $result);
        $this->assertEquals('', $result['subject']);
        $this->assertArrayHasKey('body', $result);
        $this->assertEquals($testbody, $result['body']);

        // Full testing of replacement is done below, but just test that it's being done at all from the main function.
        $testsubject = 'Test Subject';
        $testbody = 'Test Body %%username%%';
        $expectedtestbody = 'Test Body '.$testuser->username;
        set_config('newuseremailenabled', '1', 'dhimport_version2');
        set_config('newuseremailsubject', $testsubject, 'dhimport_version2');
        set_config('newuseremailtemplate', $testbody, 'dhimport_version2');
        $result = $importplugin->newuseremail($testuser);
        $this->assertNotEmpty($result);
        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($testuser, $result['user']);
        $this->assertArrayHasKey('subject', $result);
        $this->assertEquals($testsubject, $result['subject']);
        $this->assertArrayHasKey('body', $result);
        $this->assertEquals($expectedtestbody, $result['body']);
    }

    /**
     * Test new user email notifications.
     */
    public function test_version2importnewuseremailgenerate() {
        global $CFG; // This is needed by the required files.
        require_once(dirname(__FILE__).'/other/rlip_importplugin_version2_fakeemail.php');
        $importplugin = new rlip_importplugin_version2_userentity_fakeemail('test');

        $templatetext = '<p>Hi %%fullname%%, your account has been created! It has the following information
            Sitename: %%sitename%%
            Login Link: %%loginlink%%
            Username: %%username%%
            Password: %%password%%
            Idnumber: %%idnumber%%
            First Name: %%firstname%%
            Last Name: %%lastname%%
            Full Name: %%fullname%%
            Email Address: %%email%%</p>';
        $user = new stdClass;
        $user->username = 'testusername';
        $user->cleartextpassword = 'cleartextpassword';
        $user->idnumber = 'testidnumber';
        $user->firstname = 'testfirstname';
        $user->lastname = 'testlastname';
        $user->email = 'testemail@example.com';
        $actualtext = $importplugin->newuseremail_generate($templatetext, $user);

        $expectedtext = '<p>Hi testfirstname testlastname, your account has been created! It has the following information
            Sitename: PHPUnit test site
            Login Link: http://www.example.com/moodle/login/index.php
            Username: testusername
            Password: cleartextpassword
            Idnumber: testidnumber
            First Name: testfirstname
            Last Name: testlastname
            Full Name: testfirstname testlastname
            Email Address: testemail@example.com</p>';
        $this->assertEquals($expectedtext, $actualtext);
    }

   /**
     * Validate that force password flag is set when password "changeme" is used on user creation.
     */
    public function test_version2importforcepasswordchangeoncreate() {
        global $DB;
        $changeme = 'changeme';

        $this->set_password_policy_for_tests();
        $this->run_core_user_import(['password' => $changeme]);

        $record = $DB->get_record('user', array('username' => 'rlipusername'));
        $id = isset($record->id) ? $record->id : 0;
        $this->assertGreaterThan(0, $id);
        $this->assertTrue($DB->record_exists('user_preferences', ['userid' => $id, 'name' => 'auth_forcepasswordchange']));

        // DATAHUB-1609: Verify the password is *not* set to 'changeme'
        $this->assertNotEquals(hash_internal_user_password($changeme), $record->password);
    }

    /**
     * Validate that force password flag is set when password "changeme" is used on user update.
     */
    public function test_version2importforcepasswordchangeonupdate() {
        global $DB;

        $this->set_password_policy_for_tests();
        $this->run_core_user_import(array());

        $record = $DB->get_record('user', array('username' => 'rlipusername'));
        $id = isset($record->id) ? $record->id : 0;
        $this->assertGreaterThan(0, $id);

        $this->run_core_user_import(array('useraction' => 'update', 'password' => 'changeme'));

        $this->assertTrue($DB->record_exists('user_preferences', ['userid' => $id, 'name' => 'auth_forcepasswordchange']));
    }

    /**
     * Data Provider for test_version2_get_userid_from_record
     * @return array the test data array(array(array(array(usersdata), ...), array(inputdata), string(prefix), int(expected[offset into usersdata array + 1])), ...)
     */
    public function version2_get_userid_from_record_dataprovider() {
        return array(
                array( // Test case 1: create existing w/ non-prefix id fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'create',
                            'username' => 'rlipuser1',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' =>
                            'Test1234!',
                            'country' => 'CA'
                        ),
                        '',
                        1
                ),
                array( // Test case 2: update existing w/ non-prefix id fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipuser1',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        '',
                        1
                ),
                array( // Test case 3: create existing w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'create',
                            'user_username' => 'rlipuser1',
                            'user_idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        'user_',
                        1
                ),
                array( // Test case 4: update existing w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1',
                            'user_idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        'user_',
                        1
                ),
                array( // Test case 5: create new w/ non-prefixed id fields
                        array(), // no existing users
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipuser0',
                            'idnumber' => 'rlipuser0',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply0@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        '',
                        false
                ),
                array( // Test case 6: update matching multiple users w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser2',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1a',
                            'user_idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'user_email' => 'noreplyB@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        'user_',
                        false
                ),
                array( // Test case 7: update matching user with others w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1c',
                            'user_idnumber' => 'rlipuser3',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        'user_',
                        3
                ),
                array( // Test case 8: update matching multiple users w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyA@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser2',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1a',
                            'user_idnumber' => 'rlipuser2',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'user_email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        'user_',
                        false
                ),
                array( // Test case 9: update existing w/ mixed-case username & non-prefix id fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'RLIPuser1',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        '',
                        1
                ),
                array( // Test case 10: update matching user with others w/ mixed-case username & user_ prefixed id fields.
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'RLIPUser1C',
                            'user_idnumber' => 'rlipuser3',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        'user_',
                        3
                ),
        );
    }

    /**
     * Validate method get_userid_from_record.
     *
     * @param array  $usersdata list of users w/ data to insert before test
     * @param array  $inputdata the user import record
     * @param string $prefix the identifying field prefix (e.g. 'user_')
     * @param int    $expected the matching user's id (false for none expected)
     * @dataProvider version2_get_userid_from_record_dataprovider
     */
    public function test_version2_get_userid_from_record($usersdata, $inputdata, $prefix, $expected) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/datahub/importplugins/version2/lib.php');
        require_once($CFG->dirroot.'/local/datahub/importplugins/version2/tests/other/rlip_importplugin_version2_mock.php');
        // Create users for test saving ids for later comparison
        $uids = array(false);
        foreach ($usersdata as $userdata) {
            if (!isset($userdata['mnethostid'])) {
                $userdata['mnethostid'] = $CFG->mnet_localhost_id;
            }
            $uids[] = $DB->insert_record('user', (object)$userdata);
        }
        $provider = new rlipimport_version2_importprovider_mockuser(array());
        $importplugin = new rlip_importplugin_version2_mock('test');
        $importplugin->mappings = rlipimport_version2_get_mapping('user');
        $importplugin->fslogger = $provider->get_fslogger('dhimport_version2', 'user');
        $inputobj = (object)$inputdata;
        $expected = $expected ? $uids[$expected] : false;
        $actual = $importplugin->get_userid_from_record($inputobj, $prefix);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Data Provider for test_version2_get_userid_for_user_actions
     * @return array the test data array(array(array(array(usersdata), ...), array(inputdata), array(expected - keys: 'uid', 'error', 'errsuffix', ...)))
     */
    public function version2_get_userid_for_user_actions_dataprovider() {
        return array(
                array( // Test case 1: no existing user w/ std.ident. fields
                        array(),
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipuser1',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " do not refer to a valid user.",
                            'errors' => array(
                                    "username value of \"rlipuser1\"",
                                    "email value of \"noreply1@remote-learner.net\"",
                                    "idnumber value of \"rlipuser1\""
                            )
                        )
                ),
                array( // Test case 2: no existing user w/ user_ prefixed id fields
                        array(),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1',
                            'user_idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " do not refer to a valid user.",
                            'errors' => array(
                                    "user_username value of \"rlipuser1\"",
                                    "user_idnumber value of \"rlipuser1\""
                            )
                        )
                ),
                array( // Test case 3: existing user w/ std.ident. fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipuser1',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array('uid' => 1, 'error' => false)
                ),
                array( // Test case 4: existing w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1',
                            'user_idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array('uid' => 1, 'error' => false)
                ),
                array( // Test case 5: update matching multiple users w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser2',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1a',
                            'user_idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'user_email' => 'noreplyB@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " do not refer to a valid user.",
                            'errors' => array(
                                    "user_username value of \"rlipuser1a\"",
                                    "user_email value of \"noreplyB@remote-learner.net\"",
                                    "user_idnumber value of \"rlipuser1\""
                            )
                        )
                ),
                array( // Test case 6: update matching user with others w/ user_ prefixed id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1c',
                            'user_idnumber' => 'rlipuser3',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array('uid' => 3, 'error' => false)
                ),
                array( // Test case 7: update matching multiple users w/ std. id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser2',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipuser1a',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyB@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " refer to different users.",
                            'errors' => array()
                        )
                ),
                array( // Test case 8: update matching user with others w/ std. id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipuser1c',
                            'idnumber' => 'rlipuser3',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array('uid' => 3, 'error' => false)
                ),
                array( // Test case 9: update no matching user w/ std. id fields
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser2',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'bogus1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " does not refer to a valid user.",
                            'errors' => array(
                                    "username value of \"bogus1\""
                            )
                        )
                ),
                array( // Test case 10: update matching ident-field not unique
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'rlipuser1a',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " refers to another user - field must be unique.",
                            'errors' => array(
                                    "email set to \"noreplyC@remote-learner.net\""
                            )
                        )
                ),
                array( // Test case 11: update matching ident-fields not unique
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_idnumber' => 'rlipuser3',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'username' => 'rlipuser1a',
                            'email' => 'noreplyB@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array(
                            'uid' => false,
                            'error' => true,
                            'errsuffix' => " refer to other user(s) - fields must be unique.",
                            'errors' => array(
                                    "username set to \"rlipuser1a\"",
                                    "email set to \"noreplyB@remote-learner.net\""
                            )
                        )
                ),
                array( // Test case 12: existing user w/ mixed-case username & std.ident. fields
                        array(
                                array(
                                    'username' => 'rlipuser1',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'username' => 'rlipUSER1',
                            'idnumber' => 'rlipuser1',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreply1@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array('uid' => 1, 'error' => false)
                ),
                array( // Test case 13: update matching user with others w/ mixed-case uername & user_ prefixed id fields.
                        array(
                                array(
                                    'username' => 'rlipuser1a',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreply1@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1b',
                                    'idnumber' => 'rlipuser1',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyB@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                                array(
                                    'username' => 'rlipuser1c',
                                    'idnumber' => 'rlipuser3',
                                    'firstname' => 'Test',
                                    'lastname' => 'User',
                                    'email' => 'noreplyC@remote-learner.net',
                                    'password' => 'Test1234!',
                                    'country' => 'CA'
                                ),
                        ),
                        array(
                            'useraction' => 'update',
                            'user_username' => 'RlipUser1C',
                            'user_idnumber' => 'rlipuser3',
                            'firstname' => 'Test',
                            'lastname' => 'User',
                            'email' => 'noreplyC@remote-learner.net',
                            'password' => 'Test1234!',
                            'country' => 'CA'
                        ),
                        array('uid' => 3, 'error' => false)
                ),
        );
    }

    /**
     * Validate method get_userid_for_user_actions.
     *
     * @param array  $usersdata list of users w/ data to insert before test
     * @param array  $inputdata the user import record
     * @param array  $expected array of expected return param values
     * @dataProvider version2_get_userid_for_user_actions_dataprovider
     */
    public function test_version2_get_userid_for_user_actions($usersdata, $inputdata, $expected) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/local/datahub/importplugins/version2/lib.php');
        require_once($CFG->dirroot.'/local/datahub/importplugins/version2/tests/other/rlip_importplugin_version2_mock.php');
        // Create users for test saving ids for later comparison
        $uids = array(false);
        foreach ($usersdata as $userdata) {
            if (!isset($userdata['mnethostid'])) {
                $userdata['mnethostid'] = $CFG->mnet_localhost_id;
            }
            $uids[] = $DB->insert_record('user', (object)$userdata);
        }
        $provider = new rlipimport_version2_importprovider_mockuser(array());
        $importplugin = new rlip_importplugin_version2_mock('test');
        $importplugin->mappings = rlipimport_version2_get_mapping('user');
        $importplugin->fslogger = $provider->get_fslogger('dhimport_version2', 'user');
        $error = 0;
        $errors = array();
        $errsuffix = '';
        // Cannot use ReflectionMethod for pass-by-reference params in method
        $inputobj = (object)$inputdata;
        $uid = $importplugin->get_userid_for_user_actions($inputobj, $error, $errors, $errsuffix);
        $expecteduid = $expected['uid'];
        if ($expecteduid) {
            $expecteduid = $uids[$expecteduid];
        }
        $this->assertEquals($expecteduid, $uid);
        $this->assertEquals($expected['error'], $error);
        if (isset($expected['errsuffix'])) {
            $this->assertEquals($expected['errsuffix'], $errsuffix);
        }
        if (isset($expected['errors'])) {
            foreach ($expected['errors'] as $err) {
                $this->assertTrue(in_array($err, $errors));
            }
        }
    }
}
