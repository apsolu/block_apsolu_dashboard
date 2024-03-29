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
 * Script pour afficher les inscriptions en attente de validation.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/enrol/select/lib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/blocks/apsolu_dashboard/pendingenrolments.php');
$PAGE->set_title(get_string('pending_enrolments', 'block_apsolu_dashboard'));

// Navigation.
$PAGE->navbar->add(get_string('pending_enrolments', 'block_apsolu_dashboard'));

require_login();

// Récupère les cours et les méthodes d'inscription de l'enseignant.
$sql = "SELECT DISTINCT e.*, c.fullname".
    " FROM {course} c".
    " JOIN {apsolu_courses} ac ON ac.id = c.id".
    " JOIN {course_categories} cc ON cc.id = c.category".
    " JOIN {enrol} e ON c.id = e.courseid".
    " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50".
    " JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.roleid = 3".
    " WHERE ra.userid = ?".
    " AND e.enrol = 'select'".
    " AND e.status = 0".
    " ORDER BY cc.name, ac.numweekday, ac.starttime";
$enrols = $DB->get_records_sql($sql, [$USER->id]);

if (count($enrols) === 0) {
    throw new moodle_exception('accessdenied', 'admin');
}

// Récupère les utilisateurs inscrits au cours.
$sql = "SELECT ue.status, ue.enrolid, e.courseid, COUNT(ue.id) AS total, MAX(ue.timecreated) AS lastenrolment".
    " FROM {user_enrolments} ue".
    " JOIN {enrol} e ON e.id = ue.enrolid".
    " JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = 50".
    " JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.roleid = 3".
    " WHERE e.enrol = 'select'".
    " AND e.status = 0".
    " AND ra.userid = :userid".
    " GROUP BY e.id, ue.status".
    " ORDER BY e.id, ue.status";
$recordset = $DB->get_recordset_sql($sql, ['userid' => $USER->id]);
foreach ($recordset as $record) {
    if (isset($enrols[$record->enrolid]) === false) {
        continue;
    }

    if (isset($enrols[$record->enrolid]->enrolments) === false) {
        $enrols[$record->enrolid]->enrolments = [];
        foreach (enrol_select_plugin::$states as $state => $label) {
            $enrols[$record->enrolid]->enrolments[$state] = 0;
        }

        $enrols[$record->enrolid]->lastenrolment = 0;
        $enrols[$record->enrolid]->warninglastenrolment = false;
    }

    $enrols[$record->enrolid]->enrolments[$record->status] = $record->total;
    if ($enrols[$record->enrolid]->lastenrolment < $record->lastenrolment) {
        $enrols[$record->enrolid]->lastenrolment = $record->lastenrolment;
        if (in_array($record->status, [enrol_select_plugin::MAIN, enrol_select_plugin::WAIT], $strict = true) === true) {
            // Affiche un warning si la nouvelle inscription concerne une inscription en liste principale ou complémentaire.
            $enrols[$record->enrolid]->warninglastenrolment = ($USER->lastlogin <= $record->lastenrolment);
        }
    }
}
$recordset->close();

foreach ($enrols as $enrolid => $enrol) {
    if (empty($enrol->name) === true) {
        // Ajoute au besoin d'un nom par défaut à la méthode d'inscription.
        $enrol->name = get_string('pluginname', 'enrol_select');
    }

    if (isset($enrol->lastenrolment) === false) {
        // Initialise les valeurs par défaut d'une méthode sans aucune inscription.
        $enrol->enrolments = array_fill(0, count(enrol_select_plugin::$states), '0');
        $enrol->lastenrolment = get_string('none');
        continue;
    }

    // Format la date de la dernière inscription.
    $enrol->lastenrolment = userdate($enrol->lastenrolment, get_string('strftimedatetime', 'langconfig'));

    $enrol->enrolments = array_values($enrol->enrolments);
}

$enrolmentstypes = [];
foreach (enrol_select_plugin::$states as $state => $label) {
    $enrolmentstypes[] = enrol_select_plugin::get_enrolment_list_name($state);
}

$data = new stdClass();
$data->wwwroot = $CFG->wwwroot;
$data->enrolmentstypes = $enrolmentstypes;
$data->enrols = array_values($enrols);
$data->count_enrols = isset($data->enrols[0]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pending_enrolments', 'block_apsolu_dashboard'));
echo $OUTPUT->render_from_template('block_apsolu_dashboard/pendingenrolments', $data);
echo $OUTPUT->footer();
