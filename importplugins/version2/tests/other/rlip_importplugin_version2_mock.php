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

require_once(dirname(__FILE__).'/../../version2.class.php');

/**
 * A test object that replaces the sendemail function in rlip_importplugin_version2 for easy testing.
 */
class rlip_importplugin_version2_mock extends \dhimport_version2\entity\user {
    /** @var array Array of mappings. */
    public $mappings = [];

    /** @var fslogger An FS logger class for logging progress. */
    public $fslogger = null;

    /**
     * Obtains a userid from a data record, logging an error message to the file system log on failure.
     *
     * @param \stdClass $record One record of import data
     * @param string $filename The import file name, used for logging
     * @param string $fieldprefix Optional prefix for identifying fields, default ''
     * @return int|bool The user id, or false if not found
     */
    public function get_userid_from_record(&$record, $fieldprefix = '') {
        return parent::get_userid_from_record($record, $fieldprefix);
    }

    /**
     * Determine userid from user import record
     *
     * @param \stdClass $record One record of import data
     * @param string $filename The import file name, used for logging
     * @param bool $error Returned errors status, true means error, false ok
     * @param array $errors Array of error strings (if $error == true)
     * @param string $errsuffix returned error suffix string
     * @return int|bool userid on success, false is not found
     */
    public function get_userid_for_user_actions(&$record, &$error, &$errors, &$errsuffix) {
        return parent::get_userid_for_user_actions($record, $error, $errors, $errsuffix);
    }
}
