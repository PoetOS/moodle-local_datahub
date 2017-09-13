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
use \dhimport_version2\provider\queue as queueprovider;

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Form that displays filepickers for each available import file, plus
 * appropriate buttons, for running imports manually
 */
class version2_import_form extends moodleform {

    /**
     * Method that defines all of the elements of the form.
     */
    public function definition() {
        //obtain the QuickForm
        $mform = $this->_form;

        //used to store the plugin between form submits
        $mform->addElement('hidden', 'plugin');
        $mform->setType('plugin', PARAM_TEXT);

        $mform->addElement('filepicker', 'version2importfile', get_string('importfilefieldlabel', 'dhimport_version2'));

        $mform->addElement('radio', 'queueschedule', null, get_string('runasap', 'dhimport_version2'), 0, array('disabled' => 'disabled'));
        $mform->addElement('radio', 'queueschedule', null, get_string('runschedule', 'dhimport_version2'), 1, array('disabled' => 'disabled'));

        $monthchoices = array('choose' => get_string('monthselect', 'dhimport_version2'));
        $mform->addElement('select', 'month', null, $monthchoices, array('class' => 'timeselect', 'disabled' => 'disabled'));

        $daychoices = array('choose' => get_string('dayselect', 'dhimport_version2'));
        $mform->addElement('select', 'day', null, $daychoices, array('class' => 'timeselect', 'disabled' => 'disabled'));

        $timechoices = array('choose' => get_string('timeselect', 'dhimport_version2'));
        $mform->addElement('select', 'time', null, $timechoices, array('class' => 'timeselect', 'disabled' => 'disabled'));

        $mform->addElement('hidden', 'queuetimestamp', null, array('id' => 'queuetimestamp'));
        $mform->setType('queuetimestamp', PARAM_INT);

        $mform->addElement('submit', 'submit', get_string('savetoqueue', 'dhimport_version2'), array('disabled' => 'disabled'));
    }

    /**
     * Show form or process upload if submitted.
     *
     * @return int Id of import queue if successful.
     */
    public function process() {
        global $USER, $DB, $SESSION;
        if (!$this->is_submitted() && !$this->is_validated()) {
            $this->error = $this->render();
            return 0;
        } else {
            $queueprovider = new queueprovider();
            $data = $this->get_data();
            $now = time();
            if ($data->queueschedule == 0) {
                $scheduletime = 0;
            } else {
                $scheduletime = $data->queuetimestamp;
            }
            $queueorder = $DB->get_field_select(queueprovider::QUEUETABLE, 'MAX(queueorder)', 'queueorder > 0');
            $queueorder++;
            $queuerecord = (object)[
                'userid' => $USER->id,
                'status' => queueprovider::STATUS_QUEUED,
                'state' => '',
                'queueorder' => $queueorder,
                'timemodified' => $now,
                'timecreated' => $now,
                'timecompleted' => 0,
                /*'scheduledtime' => $scheduletime */ // TODO: implement scheduletime once db scheme is updated.
            ];
            $queuerecord->id = $DB->insert_record(queueprovider::QUEUETABLE, $queuerecord);
            $csvfile = $queueprovider->build_files($queuerecord->id);
            $savedfile = $this->save_file('version2importfile', $csvfile[0], true);
            return $queuerecord->id;
        }
    }
}