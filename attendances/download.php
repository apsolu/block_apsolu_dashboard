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
 * Script permettant à un étudiant de télécharger ses présences auf ormat pdf.
 *
 * Note : ce script n'est pas encore affiché sur la tableau de bord des étudiants.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2019 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_apsolu\core\attendance;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/pdflib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$PAGE->set_url('/blocks/apsolu_dashboard/attendances/download.php');

require_login();

$pdf = new pdf();

$courses = Attendance::getUserPresencesPerCourses($USER->id);

if (empty($courses) === true) {
    $html = '<h1>' . fullname($USER) . '</h1>';
    $html .= '<p>' . get_string('no_recorded_attendances', 'local_apsolu') . '</p>';

    $pdf->AddPage();
    $pdf->WriteHTML($html);
} else {
    foreach ($courses as $course) {
        $html = '<h1>' . fullname($USER) . '</h1>';
        $html .= '<h2>' . $course->fullname . '</h2>';
        if (empty($course->sessions) === true) {
            $html .= get_string('no_sessions', 'local_apsolu');
        } else {
            $html .= '<table><thead><tr>' .
                '<th><b>' . get_string('date') . '</b></th>' .
                '<th><b>' . get_string('status') . '</b></th>' .
                '<th><b>' . get_string('courseduration') . '</b></th>' .
                '</tr></thead><tbody>';
            foreach ($course->sessions as $session) {
                $html .= '<tr>' .
                    '<td>' . userdate($session->sessiontime, get_string('strftimedaydatetime')) . '</td>' .
                    '<td>' . $session->status . '</td>' .
                    '<td>' . format_time($session->duration) . '</td>' .
                    '</tr>';
            }
            $html .= '</tbody></table>';
        }

        $pdf->AddPage();
        $pdf->WriteHTML($html);
    }
}

$filename = clean_filename(fullname($USER)) . '.pdf';
$pdf->Output($filename, 'D');
