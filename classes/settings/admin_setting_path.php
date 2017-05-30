<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2017 Remote-Learner.net Inc (http://www.remote-learner.net)
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
 * @package    local_datahub
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2017 onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

namespace local_datahub\settings;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot."/lib/adminlib.php");

/**
 * This class defines admin_setting_path, creates directory upon update.
 */
class admin_setting_path extends \admin_setting_configtext {
    /**
     * Returns the config if possible
     *
     * @return mixed returns config if successful else null
     */
    public function config_read($name) {
        global $CFG;
        $value = parent::config_read($name);
        if (!$value || !@file_exists($CFG->dataroot.$value)) {
            return null;
        }
        return $value;
    }

    /**
     * Validate data before storage
     * @param string data
     * @return mixed true if ok string if error found
     */
    public function validate($data) {
        global $CFG;
        if (($ret = parent::validate($data)) === true) {
            if (strpos($data, '../') !== false) {
                $ret = get_string('illegaldirectoryerror', 'local_datahub', $data);
            } else {
                $path = $CFG->dataroot.$data;
                if (!@file_exists($path) && !@mkdir($path, 0775, true)) {
                    $ret = get_string('createdirectoryerror', 'local_datahub', $path);
                }
            }
        }
        return $ret;
    }

    /**
     * Return an XHTML string for the setting
     * @param string data
     * @param string query
     * @return string Returns an XHTML string
     */
    public function output_html($data, $query = '') {
        if ($data == null) {
            $data = parent::config_read($this->name);
        }
        return parent::output_html($data, $query);
    }
}
