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
 * @package    local_datahub
 * @copyright  Remote-Learner.net
 * @author     Amy Groshek <amy@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_datahub\output;
defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use renderable;

/**
 * Local datahub renderer class
 *
 * @package    local_datahub
 * @copyright  Remote-Learner.net
 * @author     Amy Groshek <amy@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Defer to template.
     * @return string HTML for the page
     */
    public function render_queue_table(renderable $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_datahub/queue', $data);
    }

    /**
     * Defer to template.
     * @return string HTML for the page
     */
    public function render_completed_table(renderable $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('local_datahub/completed', $data);
    }
}
