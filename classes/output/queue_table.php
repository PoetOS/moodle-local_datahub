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
 * Competencies to review renderable.
 *
 * @package    local_datahub
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_datahub\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;
use moodle_url;

/**
 * Competencies to review renderable class.
 *
 * @package    local_datahub
 * @copyright  Remote-Learner.net
 * @author     Amy Groshek <amy@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_table implements renderable, templatable {

    /**
     * Constructor.
     */
    public function __construct() {
        // $this->jobs = $jobs;
    }

    /**
     * Export data for use in renderer template.
     * @param  renderer_base $output Renderer object
     * @return object        $data   Object of
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER, $OUTPUT;
        $data = new stdClass();
        $data->jobs = array();
        return $data;
    }
}

/**
 * Class to render the completed table from template.
 *
 * @package    local_datahub
 * @copyright  Remote-Learner.net
 * @author     Amy Groshek <amy@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completed_table implements renderable, templatable {

    /**
     * Constructor.
     */
    public function __construct() {
        // $this->jobs = $jobs;
    }

    /**
     * Export data for use in renderer template.
     * @param  renderer_base $output Renderer object
     * @return object        $data   Object of
     */
    public function export_for_template(renderer_base $output) {
        global $CFG, $DB, $USER, $OUTPUT;
        $data = new stdClass();
        $data->jobs = array();
        $obj = new stdClass();
        $obj->start = "2017-09-15";
        $obj->stop = "2017-09-30";
        $data->jobs[] = $obj;
        $data->jobs = array_values($data->jobs);
        return json_encode($data);
    }
}
