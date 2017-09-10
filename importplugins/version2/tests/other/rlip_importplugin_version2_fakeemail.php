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
 * A test object that replaces the sendemail function in the user entity for easy testing.
 */
class rlip_importplugin_version2_userentity_fakeemail extends \dhimport_version2\entity\user {

    /**
     * If enabled, sends a new user email notification to a user.
     *
     * @param \stdClass $user The user to send the email to.
     * @return bool Success/Failure.
     */
    public function newuseremail($user) {
        return parent::newuseremail($user);
    }

    /**
     * Generates the new user email text.
     *
     * @param string $templatetext The template email text.
     * @param \stdClass $user The user object to generate the email for.
     * @return string The final email text.
     */
    public function newuseremail_generate($templatetext, $user) {
        return parent::newuseremail_generate($templatetext, $user);
    }

    /**
     * Send the email.
     *
     * @param object $user The user the email is to.
     * @param object $from The user the email is from.
     * @param string $subject The subject of the email.
     * @param string $body The body of the email.
     * @return array An array containing all inputs.
     */
    public function sendemail($user, $from, $subject, $body) {
        return array(
            'user' => $user,
            'from' => $from,
            'subject' => $subject,
            'body' => $body
        );
    }
}

/**
 * A test object that replaces the sendemail function in the enrolment entity for easy testing.
 */
class rlip_importplugin_version2_enrolmententity_fakeemail extends \dhimport_version2\entity\enrolment {

    /**
     * Send an email to the user when they are enroled.
     *
     * @param int $userid The user id being enroled.
     * @param int $courseid The course id they're being enroled into.
     * @return bool Success/Failure.
     */
    public function newenrolmentemail($userid, $courseid) {
        return parent::newenrolmentemail($userid, $courseid);
    }

    /**
     * Generate a new enrolment email based on an email template, a user, and a course.
     *
     * @param string $templatetext The template for the message.
     * @param \stdClass $user The user object to use for placeholder substitutions.
     * @param \stdClass $course The course object to use for placeholder substitutions.
     * @return string The generated email.
     */
    public function newenrolmentemail_generate($templatetext, $user, $course) {
        return parent::newenrolmentemail_generate($templatetext, $user, $course);
    }

    /**
     * Send the email.
     *
     * @param object $user The user the email is to.
     * @param object $from The user the email is from.
     * @param string $subject The subject of the email.
     * @param string $body The body of the email.
     * @return array An array containing all inputs.
     */
    public function sendemail($user, $from, $subject, $body) {
        return array(
            'user' => $user,
            'from' => $from,
            'subject' => $subject,
            'body' => $body
        );
    }
}
