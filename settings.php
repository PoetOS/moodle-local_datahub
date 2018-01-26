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
 * @package    local_datahub
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2008-2013 Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/local/datahub/lib.php');

if ($hassiteconfig) {
    // Add all IP-related entities to the standard Moodle admin tree
    rlip_admintree_setup($ADMIN);

    $settings = new admin_settingpage('local_datahub', get_string('datahub_settings', 'local_datahub'));
    $ADMIN->add('localplugins', $settings);

    // Start of "scheduling" section
    $settings->add(new admin_setting_heading('local_datahub/scheduling', get_string('rlip_global_scheduling', 'local_datahub'), ''));

    // Setting for disabling in Moodle cron
    if (empty($CFG->forcedatahubcron)) {
        $settings->add(new admin_setting_configcheckbox('local_datahub/disableincron', get_string('disableincron', 'local_datahub'),
            get_string('configdisableincron', 'local_datahub'), ''));
    } else {
        $settings->add(new admin_setting_heading('local_datahub/disableincron_override', '', get_string('cronforcedinconfig', 'local_datahub')));
    }

    // Setting for allowing 'changeme' as password
    if (empty($CFG->allowchangemepass)) {
        $settings->add(new admin_setting_configcheckbox('local_datahub/allowchangemepass', get_string('allowchangemepass', 'local_datahub'),
            get_string('configallowchangemepass', 'local_datahub'), ''));
    } else {
        $settings->add(new admin_setting_heading('local_datahub/allowchangemepass_override', '', get_string('allowchangemepassforcedinconfig', 'local_datahub')));
    }
}
