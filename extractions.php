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
 * Permet d'extraire la liste des étudiants inscrits à un cours.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_apsolu\core\customfields;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/blocks/apsolu_dashboard/extractions_form.php');
require_once($CFG->libdir . '/excellib.class.php');

$forcemanager = optional_param('manager', null, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_url('/blocks/apsolu_dashboard/extractions.php');
$PAGE->set_title(get_string('mystudents', 'local_apsolu'));

// Navigation.
$PAGE->navbar->add(get_string('mystudents', 'local_apsolu'));

require_login();

// Load courses.
$courses = ['*' => get_string('all')];

if ($forcemanager) {
    $ismanager = $DB->get_record('role_assignments', ['contextid' => 1, 'roleid' => 1, 'userid' => $USER->id]);

    if (!$ismanager) {
        $ismanager = is_siteadmin();
    }
} else {
    $ismanager = false;
}

if ($ismanager) {
    // Managers.
    $sql = "SELECT c.id, c.fullname" .
        " FROM {course} c" .
        " JOIN {apsolu_courses} ac ON ac.id = c.id" .
        " ORDER BY c.fullname";
    $records = $DB->get_records_sql($sql);
} else {
    // Teachers.
    $sql = "SELECT DISTINCT c.*" .
        " FROM {enrol} e" .
        " JOIN {course} c ON c.id = e.courseid" .
        " JOIN {apsolu_courses} ac ON ac.id = c.id" .
        " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50" .
        " JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.roleid = 3" .
        " WHERE ra.userid = ?" .
        " AND e.enrol = 'select'" .
        " AND e.status = 0" .
        " ORDER BY c.fullname";
    $records = $DB->get_records_sql($sql, [$USER->id]);
}

if (count($records) === 0) {
    throw new moodle_exception('usernotavailable');
}

foreach ($records as $record) {
    $courses[$record->id] = $record->fullname;
}

// Load institutions.
$sql = "SELECT DISTINCT institution FROM {user} WHERE id > 2 AND deleted = 0 AND auth = 'shibboleth' ORDER BY institution";
$institutions = ['*' => get_string('all')];
foreach ($DB->get_records_sql($sql) as $record) {
    if (!empty($record->institution)) {
        $institutions[$record->institution] = $record->institution;
    }
}

// Load roles.
$roles = ['*' => get_string('all')];
foreach ($DB->get_records('role', ['archetype' => 'student']) as $role) {
    if (!empty($role->name) !== false) {
        $roles[$role->id] = $role->name;
    }
}

// Load semesters.
$semesters = ['*' => get_string('all')];
foreach ($DB->get_records('apsolu_calendars_types', $conditions = null, $sort = 'name') as $record) {
    $semesters[$record->id] = $record->name;
}

if (date('m') > 8) {
    $year = date('y');
    $defaultsemester = 1;
} else {
    $year = date('y') - 1;
    $defaultsemester = 2;
}

// Load lists.
$lists = [
    '*' => get_string('all'),
    '0' => get_string('accepted_list', 'enrol_select'),
    '2' => get_string('main_list', 'enrol_select'),
    '3' => get_string('wait_list', 'enrol_select'),
    '4' => get_string('deleted_list', 'enrol_select'),
];

// Departments list.
$departmentslist = [];
foreach ($DB->get_records_sql('SELECT DISTINCT department FROM {user} ORDER BY department') as $record) {
    if (empty($record->department) === true) {
        continue;
    }
    $departmentslist[] = $record->department;
}

// Build form.
$defaults = (object) ['institutions' => '*', 'roles' => '*', 'semesters' => $defaultsemester, 'lists' => '0'];
$customdata = [$defaults, $courses, $institutions, $roles, $semesters, $lists, $forcemanager];
$mform = new local_apsolu_courses_users_export_form(null, $customdata);

if ($data = $mform->get_data()) {
    // Save data.
    $conditions = [];

    $sql = "SELECT u.*, r.name AS rolename, ue.status AS listid, c.id AS courseid, c.fullname AS course, e.name AS enrol" .
        " FROM {user} u" .
        " JOIN {user_enrolments} ue ON u.id = ue.userid" .
        " JOIN {enrol} e ON e.id = ue.enrolid AND e.enrol = 'select' AND e.status = 0" .
        " JOIN {apsolu_calendars} cal ON cal.id = e.customchar1" .
        " JOIN {course} c ON c.id = e.courseid" .
        " JOIN {apsolu_courses} ac ON ac.id = c.id" .
        " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50" .
        " JOIN {role_assignments} ra1 ON ctx.id = ra1.contextid AND ra1.userid = u.id AND ra1.itemid = e.id" .
        " JOIN {role} r ON r.id = ra1.roleid";

    if (!$ismanager) {
        // Teachers.
        $sql .= " JOIN {role_assignments} ra2 ON ctx.id = ra2.contextid AND ra2.roleid = 3 AND ra2.userid = :owner";
        $conditions['owner'] = $USER->id;
    }

    $where = ['u.deleted = 0'];

    // Lastnames filter.
    if (isset($data->lastnames)) {
        $lastnames = [];
        foreach (explode(',', $data->lastnames) as $i => $lastname) {
            if (empty($lastname)) {
                continue;
            }
            $lastnames[] = 'u.lastname LIKE :lastname' . $i;
            $conditions['lastname' . $i] = '%' . trim($lastname) . '%';
        }

        if (isset($lastnames[0])) {
            $where[] = '( ' . implode(' OR ', $lastnames) . ' )';
        }
    }

    // Courses filter.
    if (isset($data->courses[0]) && $data->courses[0] !== '*') {
        $courses = [];
        foreach ($data->courses as $course) {
            if (ctype_digit($course)) {
                $courses[] = $course;
            }
        }

        if (isset($courses[0])) {
            $where[] = "c.id IN (" . implode(', ', $courses) . ")";
        }
    }

    // Institutions filter.
    if (isset($data->institutions[0]) && $data->institutions[0] !== '*') {
        $institutions = [];
        foreach ($data->institutions as $i => $institution) {
            $institutions[] = ':institution' . $i;
            $conditions['institution' . $i] = $institution;
        }
        $where[] = "u.institution IN (" . implode(', ', $institutions) . ")";
    }

    // UFR filter.
    if (isset($data->ufrs)) {
        $ufrs = [];
        foreach (explode(',', $data->ufrs) as $i => $ufr) {
            if (empty($ufr)) {
                continue;
            }
            $ufrs[] = 'ui4.data LIKE :ufr' . $i;
            $conditions['ufr' . $i] = '%' . trim($ufr) . '%';
        }

        if (isset($ufrs[0])) {
            $sql .= " LEFT JOIN {user_info_data} ui4 ON u.id = ui4.userid AND ui4.fieldid = 4";
            $where[] = '( ' . implode(' OR ', $ufrs) . ' )';
        }
    }

    // Departments filter.
    if (isset($data->departments)) {
        $departments = [];
        foreach (explode(',', $data->departments) as $i => $department) {
            if (empty($department)) {
                continue;
            }
            $departments[] = 'u.department LIKE :department' . $i;
            $conditions['department' . $i] = '%' . trim($department) . '%';
        }

        if (isset($departments[0])) {
            $where[] = '( ' . implode(' OR ', $departments) . ' )';
        }
    }

    // Roles filter.
    if (isset($data->roles[0]) && $data->roles[0] !== '*') {
        $roles = [];
        foreach ($data->roles as $role) {
            if (ctype_digit($role)) {
                $roles[] = $role;
            }
        }

        if (isset($roles[0])) {
            $where[] = "ra1.roleid IN (" . implode(', ', $roles) . ")";
        }
    }

    // Semesters filter.
    if (isset($data->semesters) && $data->semesters !== '*') {
        $where[] = 'cal.typeid = :typeid';
        $conditions['typeid'] = $data->semesters;
    }

    // Lists filter.
    if (isset($data->lists[0]) && $data->lists[0] !== '*') {
        $lists = [];
        foreach ($data->lists as $list) {
            if (ctype_digit($list)) {
                $lists[] = $list;
            }
        }

        if (isset($lists[0])) {
            $where[] = "ue.status IN (" . implode(', ', $lists) . ")";
        }
    }

    // Build final query.
    if (isset($where[0])) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }

    $sql .= " ORDER BY u.lastname, u.firstname, u.institution";

    $headers = [];
    $headers['lastname'] = get_string('lastname');
    $headers['firstname'] = get_string('firstname');
    $headers['idnumber'] = get_string('idnumber');

    // Récupère les champs additionnels pour l'exportation.
    $extrafields = customfields::get_extra_fields_for_export();
    foreach ($extrafields as $fieldname => $label) {
        $headers[$fieldname] = $label;
    }

    if (isset($data->courses[1]) === true || $data->courses[0] === '*') {
        $headers['course'] = get_string('course');
    }

    if (isset($data->semesters[1]) === true || $data->semesters[0] === '*') {
        $headers['enrol'] = get_string('enrolments', 'enrol');
    }

    if (isset($data->lists[1]) === true || $data->lists[0] === '*') {
        $headers['list'] = get_string('list');
    }

    if (isset($data->roles[1]) === true || $data->roles[0] === '*') {
        $headers['rolename'] = get_string('role', 'local_apsolu');
    }

    if ($data->submitbutton === get_string('display', 'local_apsolu')) {
        // TODO: display.
        $data = new stdClass();
        $data->headers = array_values($headers);
        $data->users = [];
        $data->count_users = 0;
        $data->action = $CFG->wwwroot . '/blocks/apsolu_dashboard/notify.php';

        $recordset = $DB->get_recordset_sql($sql, $conditions);
        foreach ($recordset as $user) {
            $user->data = [];
            $user->htmlpicture = $OUTPUT->user_picture($user, ['courseid' => $user->courseid]);
            $user->list = $customdata[5][$user->listid];

            $customfields = profile_user_record($user->id);
            foreach ($headers as $fieldname => $unused) {
                if (isset($user->$fieldname) === true) {
                    $user->data[] = $user->$fieldname;
                } else if (isset($customfields->$fieldname) === true) {
                    $user->data[] = $customfields->$fieldname;
                }
            }

            $data->users[] = $user;
            $data->count_users++;
        }
        $recordset->close();

        $data->found_users = get_string('students_found', 'local_apsolu', $data->count_users);

        $PAGE->requires->js_call_amd('block_apsolu_dashboard/select_all_checkboxes', 'initialise');

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('mystudents', 'local_apsolu'));
        $mform->display();
        echo $OUTPUT->render_from_template('block_apsolu_dashboard/extractions', $data);
        echo $OUTPUT->render_from_template('block_apsolu_dashboard/departments', (object) ['departments' => $departmentslist]);
        echo $OUTPUT->footer();
    } else {
        // TODO: export csv.

        // Creating a workbook.
        $workbook = new MoodleExcelWorkbook("-");
        // Sending HTTP headers.
        if (isset($data->courses[1]) === false && $data->courses[0] !== '*') {
            $filename = preg_replace('/[^a-z0-9\-]/', '_', strtolower($customdata[1][$data->courses[0]])) . '_';
        } else if (isset($data->courses[0]) && $data->courses[0] === '*') {
            $filename = 'tout_';
        } else {
            $filename = '';
        }

        $workbook->send('liste_etudiants_' . $filename . time() . '.xls');
        // Adding the worksheet.
        $myxls = $workbook->add_worksheet();

        $excelformat = new MoodleExcelFormat(['border' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]);

        // Set headers.
        $i = 0;
        foreach ($headers as $value) {
            $myxls->write_string(0, $i, $value, $excelformat);
            $i++;
        }

        // Set data.
        $line = 1;
        $recordset = $DB->get_recordset_sql($sql, $conditions);
        foreach ($recordset as $user) {
            $user->list = $customdata[5][$user->listid];
            $customfields = profile_user_record($user->id);

            $i = 0;
            foreach ($headers as $fieldname => $unused) {
                $value = null;
                if (isset($user->$fieldname) === true) {
                    $value = $user->$fieldname;
                } else if (isset($customfields->$fieldname) === true) {
                    $value = $customfields->$fieldname;
                }

                if ($value === null) {
                    continue;
                }

                $myxls->write_string($line, $i, $value, $excelformat);
                $i++;
            }

            $line++;
        }
        $recordset->close();

        // MDL-83543: positionne un cookie pour qu'un script js déverrouille le bouton submit après le téléchargement.
        setcookie('moodledownload_' . sesskey(), time());

        // Transmet le fichier au navigateur.
        $workbook->close();
    }
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('mystudents', 'local_apsolu'));
    $mform->display();
    echo $OUTPUT->render_from_template('block_apsolu_dashboard/departments', (object) ['departments' => $departmentslist]);
    echo $OUTPUT->footer();
}
