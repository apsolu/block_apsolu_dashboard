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
 * Script envoyer des notifications aux étudiants.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/apsolu/forms/notification_form.php');

if (!isset($_POST['users'])) {
    $_POST['users'] = [];
}

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/blocks/apsolu_dashboard/notify.php');
$PAGE->set_title(get_string('mystudents', 'local_apsolu'));

// Navigation.
$PAGE->navbar->add(get_string('mystudents', 'local_apsolu'));

require_login($courseorid = null, $autologinguest = false);

// Load courses.
$courses = ['*' => get_string('all')];
$ismanager = $DB->get_record('role_assignments', ['contextid' => 1, 'roleid' => 1, 'userid' => $USER->id]);

if (!$ismanager) {
    $ismanager = is_siteadmin();
}

if (!$ismanager) {
    // Check if is teacher.
    $sql = "SELECT DISTINCT c.*" .
        " FROM {enrol} e" .
        " JOIN {course} c ON c.id = e.courseid" .
        " JOIN {apsolu_courses} ac ON ac.id = c.id" .
        " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50" .
        " JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.roleid = 3" .
        " WHERE ra.userid = ?" .
        " AND e.enrol = 'select'" .
        " AND e.status = 0";
    $records = $DB->get_records_sql($sql, [$USER->id]);

    if (count($records) === 0) {
        throw new moodle_exception('usernotavailable');
    }
}

$users = [];
foreach ($_POST['users'] as $userid) {
    $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);

    if ($user) {
        $users[$user->id] = $user;
    }
}

if ($users === []) {
    throw new moodle_exception('usernotavailable');
}

if (!isset($users[$USER->id])) {
    $users[$USER->id] = $USER;
}

$redirecturl = new moodle_url('/my/#teachings');
$customdata = [];
$customdata[] = (object) ['subject' => ''];
$customdata[] = $users;
$customdata[] = $redirecturl;

$mform = new local_apsolu_notification_form($PAGE->url->out(false), $customdata);

if ($mform->is_cancelled()) {
    redirect($return);
} else if ($data = $mform->get_data()) {
    $mform->local_apsolu_notify($data->users, $course->id);

    $message = get_string('notifications_have_been_sent', 'local_apsolu');
    redirect($redirecturl, $message, 5, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
