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
 * Gère l'affichage du bloc Mon espace SIUAPS.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use UniversiteRennes2\Apsolu\Payment;

require_once($CFG->dirroot.'/enrol/select/lib.php');

class block_apsolu_dashboard extends block_base {
    /**
     * Initialise le bloc.
     */
    public function init() {
        $this->title = get_string('title', 'block_apsolu_dashboard');
    }

    /**
     * Retourne une session formatée correctement pour l'affichage.
     *
     * @return stdClass the content
     */
    private function format_session($session) {
        global $CFG;

        $today = mktime(23, 59, 59);
        $tomorrow = $today + 24 * 60 * 60;

        if ($today > $session->sessiontime) {
            $formatdate = '%FT%T%z|'.get_string('today', 'calendar').' '.get_string('strftimetime');
            list($start, $startstr) = explode('|', userdate($session->sessiontime, $formatdate));
        } else if ($tomorrow > $session->sessiontime) {
            $formatdate = '%FT%T%z|'.get_string('tomorrow', 'calendar').' '.get_string('strftimetime');
            list($start, $startstr) = explode('|', userdate($session->sessiontime, $formatdate));
        } else {
            $formatdate = '%FT%T%z|'.get_string('strftimedayshort').' '.get_string('strftimetime');
            list($start, $startstr) = explode('|', userdate($session->sessiontime, $formatdate));
        }

        $endstr = $session->endtime;
        $end = str_replace('T'.$session->starttime, 'T'.$session->endtime, $start);

        if (empty($session->location)) {
            $session->location = '<p>'.get_string('no_description', 'block_apsolu_dashboard').'</p>';
        } else if ($session->locationid !== $session->defaultlocationid) {
            $session->location = '<span class="block-apsolu-attendance-warning text-danger">'.$session->location.'</span>';
        }

        if (empty($session->event) === true) {
            $session->label = $session->activity.' - '.$session->skill;
        } else {
            $session->label = $session->activity.' '.$session->event.' - '.$session->skill;
        }

        switch ($session->status) {
            case enrol_select_plugin::MAIN:
                $session->enrolment_list = enrol_select_plugin::get_enrolment_list_name(enrol_select_plugin::MAIN);
                break;
            case enrol_select_plugin::WAIT:
                $session->enrolment_list = enrol_select_plugin::get_enrolment_list_name(enrol_select_plugin::WAIT);
                break;
            default:
                $session->enrolment_list = '';
        }

        $session->link = html_writer::link($CFG->wwwroot.'/course/view.php?id='.$session->courseid, $session->label);

        $session->start = $start;
        $session->startstr = $startstr;
        $session->end = $end;
        $session->endstr = $endstr;

        $session->defaultsessiontime = (strftime('%u%H:%M', $session->sessiontime) === $session->numweekday.$session->starttime);

        return $session;
    }

    /*
     * Retourne la liste des cours où l'utilisateur courant étudie. Cette méthode est utilisée pour l'onglet "Mes cours".
     *
     * @return array Retourne un tuple de données array(liste_des_cours[], nombre de cours)
     */
    private function get_courses($archetype = 'student') {
        global $DB, $USER;

        $courses = array();
        $count_courses = 0;

        $roles = role_fix_names($DB->get_records('role', array(), 'sortorder'));

        $sql = "SELECT c.id, c.fullname, e.id AS enrolid, e.customint7, e.customint8, ra.roleid, apc.id AS apsolucourse, ue.status".
            " FROM {course} c".
            " LEFT JOIN {apsolu_courses} apc ON apc.id = c.id".
            " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50".
            " JOIN {role_assignments} ra ON ctx.id = ra.contextid".
            " JOIN {role} r ON r.id = ra.roleid".
            " JOIN {enrol} e ON c.id = e.courseid AND e.status = 0 AND ra.itemid = e.id".
            " JOIN {user_enrolments} ue ON e.id = ue.enrolid AND ue.userid = ra.userid".
            " WHERE ra.userid = :userid".
            " AND r.archetype = :archetype".
            " AND c.visible = 1".
            " ORDER BY apc.numweekday IS NULL ASC, apc.starttime IS NULL ASC, e.customint7, c.fullname";
        $parameters = array('userid' => $USER->id, 'archetype' => $archetype);

        foreach ($DB->get_recordset_sql($sql, $parameters) as $course) {
            if (isset($courses[$course->id]) === false) {
                $course->viewable = false;
                $course->enrolments = array();
                $course->count_enrolments = 0;
                $courses[$course->id] = $course;

                $count_courses++;
            }

            $startcourse = $course->customint7;
            $endcourse = $course->customint8;

            if (time() >= $startcourse && time() <= $endcourse && $course->status === '0') {
                $course->viewable = true;
            }

            if ($course->apsolucourse !== null) {
                $parameters = new stdClass();
                $parameters->startcourse = userdate($startcourse, get_string('strftimedate'));
                $parameters->endcourse = userdate($endcourse, get_string('strftimedate'));
                $parameters->role = strtolower($roles[$course->roleid]->localname);

                $courses[$course->id]->enrolments[] = get_string('from_date_to_date_with_enrolement_type', 'block_apsolu_dashboard', $parameters);
                $courses[$course->id]->count_enrolments++;
            }
        }

        return array(array_values($courses), $count_courses);
    }

    /*
     * Retourne la liste des cours où l'utilisateur enseigne.
     *
     * @return array Retourne un tuple de données array(liste_des_cours[], nombre de cours, liste_des_autres_cours[], nombre de cours 'autres')
     */
    private function get_teachings() {
        global $DB, $USER;

        $mains = array();
        $count_mains = 0;

        $others = array();
        $count_others = 0;

        $sql = "SELECT c.id, c.fullname, c.visible, e.id AS enrolid, ra.roleid, apc.id AS apsolucourse".
            " FROM {course} c".
            " LEFT JOIN {apsolu_courses} apc ON apc.id = c.id".
            " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50".
            " JOIN {role_assignments} ra ON ctx.id = ra.contextid".
            " JOIN {role} r ON r.id = ra.roleid".
            " LEFT JOIN {enrol} e ON c.id = e.courseid AND e.status = 0 AND e.enrol = 'select'".
            " WHERE ra.userid = :userid".
            " AND r.archetype = 'editingteacher'".
            " ORDER BY c.visible DESC, apc.numweekday, apc.starttime, c.fullname";
        $parameters = array('userid' => $USER->id);

        foreach ($DB->get_recordset_sql($sql, $parameters) as $course) {
            // Différencie les cours apsolu et les 'autres' cours (meta-cours, etc).
            if ($course->apsolucourse === null) {
                $others[$course->id] = $course;
                $count_others++;
            } else {
                $mains[$course->id] = $course;
                $count_mains++;
            }
        }

        return array(array_values($mains), $count_mains, array_values($others), $count_others);
    }

    private function set_contacts() {
        global $DB;

        $this->courses_contacts = array();

        $sql = "SELECT ra.id, ac.id AS courseid, u.firstname, u.lastname, u.email".
            " FROM {role_assignments} ra".
            " JOIN {context} ctx ON ctx.id = ra.contextid".
            " JOIN {apsolu_courses} ac ON ac.id = ctx.instanceid".
            " JOIN {user} u ON u.id = ra.userid".
            " WHERE ctx.contextlevel = 50".
            " AND ra.roleid = 3".
            " AND u.deleted = 0".
            " ORDER BY u.lastname, u.firstname";
        $assignments = $DB->get_records_sql($sql);
        foreach ($assignments as $assignment) {
            if (isset($this->courses_contacts[$assignment->courseid]) === false) {
                $this->courses_contacts[$assignment->courseid] = new stdClass();
                $this->courses_contacts[$assignment->courseid]->teachers = array();
                $this->courses_contacts[$assignment->courseid]->count_teachers = 0;
            }

            $teacher = new stdClass();
            $teacher->firstname = $assignment->firstname;
            $teacher->lastname = $assignment->lastname;
            $teacher->email = $assignment->email;
            $this->courses_contacts[$assignment->courseid]->teachers[] = $teacher;
            $this->courses_contacts[$assignment->courseid]->count_teachers++;
        }
    }

    private function set_locations() {
        global $DB;

        $this->locations = array();
        foreach ($DB->get_records('apsolu_locations') as $location) {
            $this->locations[$location->name] = $location;
        }
    }

    private function get_pre_next_rendez_vous() {
        global $DB, $USER;

        $sql = "SELECT sess.*, c.id AS courseid, c.fullname, apc.event, aps.name AS skill, cc.name AS activity, ue.status, ue.timestart, ue.timeend, apc.numweekday, apc.starttime, apc.endtime, apc.locationid AS defaultlocationid, apl.name AS location".
            " FROM {apsolu_attendance_sessions} sess".
            " JOIN {course} c ON c.id = sess.courseid".
            " JOIN {course_categories} cc ON cc.id = c.category".
            " JOIN {apsolu_courses} apc ON apc.id = c.id".
            " JOIN {apsolu_skills} aps ON aps.id = apc.skillid".
            " JOIN {apsolu_locations} apl ON apl.id = sess.locationid".
            " JOIN {enrol} e ON c.id = e.courseid".
            " JOIN {user_enrolments} ue ON e.id = ue.enrolid".
            " WHERE c.visible = 1".
            " AND e.status = 0". // Only active enrolments.
            " AND ue.status IN (2,3)". // Only active user enrolments.
            " AND ue.userid = :userid".
            " AND (ue.timeend = 0 OR ue.timeend > :currenttime)". // Seulement les cours dont l'inscription n'est pas expirée (note: mais peut-être qu'elle n'a pas commencé...).
            " AND sess.sessiontime <= :maxtime".
            " GROUP BY c.id". // Ne retourne que la première session (ORDER BY sess.sessiontime) de chaque cours.
            " ORDER BY sess.sessiontime, c.fullname";
        $params = array('userid' => $USER->id, 'currenttime' => $this->currenttime, 'maxtime' => $this->maxtime);

        $sessions = array();
        foreach ($DB->get_recordset_sql($sql, $params) as $session) {
            $duration = 0;

            $endtime = explode(':', $session->endtime);
            $starttime = explode(':', $session->starttime);

            if (isset($endtime[1], $starttime[1]) === true) {
                $duration = ($endtime[0] * 60 * 60 + $endtime[1] * 60) - ($starttime[0] * 60 * 60 + $starttime[1] * 60);
            }

            if ($duration <= 0) {
                $duration = 60 * 60;
            }

            if ($session->sessiontime + $duration < $this->currenttime) {
                // N'affiche pas ce cours dans le bloc si la première session du cours est déjà terminée.
                continue;
            }

            if (isset($this->courses_contacts[$session->courseid]) === false) {
                $this->courses_contacts[$session->courseid] = array();
            }

            $session->teachers = $this->courses_contacts[$session->courseid]->teachers;
            $session->count_teachers = $this->courses_contacts[$session->courseid]->count_teachers;

            $location = strip_tags($session->location);
            if (isset($this->locations[$location]) === true) {
                $session->latitude = $this->locations[$location]->latitude;
                $session->longitude = $this->locations[$location]->longitude;
                $session->marker_pix = $this->marker_pix;
            }

            $sessions[] = $session;
        }

        return $sessions;
    }

    private function get_next_rendez_vous() {
        global $DB, $USER;

        $sql = "SELECT sess.*, c.id AS courseid, c.fullname, apc.event, aps.name AS skill, cc.name AS activity, ue.status, ue.timestart, ue.timeend, apc.numweekday, apc.starttime, apc.endtime, apc.locationid AS defaultlocationid, apl.name AS location".
            " FROM {apsolu_attendance_sessions} sess".
            " JOIN {course} c ON c.id = sess.courseid".
            " JOIN {course_categories} cc ON cc.id = c.category".
            " JOIN {apsolu_courses} apc ON apc.id = c.id".
            " JOIN {apsolu_skills} aps ON aps.id = apc.skillid".
            " JOIN {apsolu_locations} apl ON apl.id = sess.locationid".
            " JOIN {enrol} e ON c.id = e.courseid".
            " JOIN {user_enrolments} ue ON e.id = ue.enrolid".
            " WHERE c.visible = 1".
            " AND e.status = 0". // Only active enrolments.
            " AND ue.status = 0". // Only active user enrolments.
            " AND ue.userid = :userid".
            " AND (ue.timeend = 0 OR ue.timeend > :currenttime)". // Seulement les cours dont l'inscription n'est pas expirée (note: mais peut-être qu'elle n'a pas commencé...).
            " AND sess.sessiontime BETWEEN :today AND :maxtime".
            " ORDER BY sess.sessiontime, c.fullname";
        $params = array('userid' => $USER->id, 'currenttime' => $this->currenttime, 'today' => $this->currenttime, 'maxtime' => $this->maxtime);

        $sessions = array();
        foreach ($DB->get_recordset_sql($sql, $params) as $session) {
            $session->started = ($session->timestart <= time());

            if (isset($this->courses_contacts[$session->courseid]) === false) {
                $this->courses_contacts[$session->courseid] = new stdClass();
                $this->courses_contacts[$session->courseid]->teachers = '';
                $this->courses_contacts[$session->courseid]->count_teachers = 0;
            }

            $session->teachers = $this->courses_contacts[$session->courseid]->teachers;
            $session->count_teachers = $this->courses_contacts[$session->courseid]->count_teachers;

            $location = strip_tags($session->location);
            if (isset($this->locations[$location]) === true) {
                $session->latitude = $this->locations[$location]->latitude;
                $session->longitude = $this->locations[$location]->longitude;
                $session->marker_pix = $this->marker_pix;
            }

            $sessions[] = $session;
        }

        return $sessions;
    }

    /**
     * Return the content of this block.
     *
     * @return stdClass the content
     */
    public function get_content() {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';

        $this->set_contacts();
        $this->set_locations();

        $this->currenttime = time();
        $this->maxtime = $this->currenttime + 1.5 * 30 * 24 * 60 * 60; // Affiche les rendez-vous des 45 prochains jours.
        $this->marker_pix = $OUTPUT->pix_icon('a/marker', $alt = '', 'enrol_select', array('class' => 'apsolu-location-markers-img', 'width' => '15px', 'height' => '20px'));

        // Template data.
        $data = new stdClass();
        $data->wwwroot = $CFG->wwwroot;
        $data->is_siuaps_rennes = isset($CFG->is_siuaps_rennes);
        $data->pre_sessions = array();
        $data->pre_count_sessions = 0;
        $data->isonwaitlist = false;
        $data->sessions = array();
        $data->count_sessions = 0;
        $data->enrolment_errors = array();
        $data->count_enrolment_errors = 0;
        $data->marker_pix = $this->marker_pix;

        // Pre-rendez-vous à venir.
        foreach ($this->get_pre_next_rendez_vous() as $session) {
            $data->pre_sessions[] = $this->format_session($session);
            $data->pre_count_sessions++;

            if ($session->status === enrol_select_plugin::WAIT && isset($CFG->is_siuaps_rennes) === true) {
                $data->isonwaitlist = true;
            }
        }

        // Rendez-vous à venir.
        foreach ($this->get_next_rendez_vous() as $session) {
            $data->sessions[] = $this->format_session($session);
            $data->count_sessions++;
        }

        // Vérifie si l'étudiant peut s'inscrire à ce cours, afin d'afficher un avertissement à l'étudiant.
        if (isset($CFG->is_siuaps_rennes) === true) {
            $sesame = $DB->get_record('user_info_data', array('userid' => $USER->id, 'fieldid' => 11)); // TODO: rendre plus flexible.
            if ($sesame !== false && $sesame->data === '1') {
                require_once $CFG->dirroot.'/enrol/select/locallib.php';

                $roles = role_fix_names($DB->get_records('role'));

                $enrolments = UniversiteRennes2\Apsolu\get_real_user_activity_enrolments();
                foreach ($enrolments as $enrolment) {
                    $sql = "SELECT ac.id".
                       " FROM {apsolu_colleges} ac".
                       " JOIN {apsolu_colleges_members} acm ON ac.id = acm.collegeid".
                       " JOIN {cohort_members} cm ON cm.cohortid = acm.cohortid".
                       " JOIN {enrol_select_cohorts} esc ON cm.cohortid = esc.cohortid".
                       " WHERE cm.userid = :userid".
                       " AND ac.roleid = :roleid".
                       " AND esc.enrolid = :enrolid";
                    $allow = $DB->get_records_sql($sql, array('userid' => $USER->id, 'roleid' => $enrolment->roleid, 'enrolid' => $enrolment->enrolid));
                    if (count($allow) === 0) {
                        $params = new stdClass();
                        $params->rolename = $roles[$enrolment->roleid]->name;
                        $params->coursename = $enrolment->fullname;
                        $data->enrolment_errors[] = get_string('unallowed_enrolment_to', 'block_apsolu_dashboard', $params);
                        $data->count_enrolment_errors++;
                    }
                }
            }
        }

        // Récupère les cours que l'utilisateur suit.
        list($data->courses, $data->count_courses) = $this->get_courses('student');

        // Récupère les cours où l'utilisateur enseigne.
        list($data->main_teachings, $data->count_main_teachings, $data->other_teachings, $data->count_other_teachings) = $this->get_teachings();
        $data->count_teachings = $data->count_main_teachings + $data->count_other_teachings;

        if ($data->count_teachings > 0) {
            // TODO: rendre plus flexible.
            // $shnu = $DB->get_record('role_assignments', array('contextid' => 16964, 'roleid' => 3, 'userid' => $USER->id)); // Courseid 320. // 2017-2018
            $shnu = $DB->get_record('role_assignments', array('contextid' => 29119, 'roleid' => 3, 'userid' => $USER->id)); // Courseid 423. // 2019-2020
            $data->shnu = ($shnu !== false);

            // Vérifie si des inscriptions sont en attente.
            $sql = "SELECT ue.id, ue.userid".
                " FROM {user_enrolments} ue".
                " JOIN {enrol} e ON e.id = ue.enrolid".
                " JOIN {apsolu_courses} ac ON ac.id = e.courseid".
                " JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = 50".
                " JOIN {role_assignments} ra ON ctx.id = ra.contextid AND ra.roleid = 3".
                " WHERE e.enrol = 'select'".
                " AND e.status = 0".
                " AND ue.status IN (1, 2)". // Liste principale et liste complémentaire.
                " AND ue.timecreated >= :lastlogin".
                " AND ra.userid = :teacherid".
                " AND ue.userid != :userid";
            $data->pendingenrolments = count($DB->get_records_sql($sql, array('teacherid' => $USER->id, 'userid' => $USER->id, 'lastlogin' => $USER->lastlogin)));
        }

        // Gestion de l'onglet "mes paiements".
        $payments_startdate = get_config('local_apsolu', 'payments_startdate');
        $payments_enddate = get_config('local_apsolu', 'payments_enddate');
        $data->payments_open = (time() > $payments_startdate && time() < $payments_enddate);
        $data->count_cards = 0;

        if ($data->payments_open === true) {
            // Calcule des cartes dues.
            require_once($CFG->dirroot.'/local/apsolu/locallib.php');
            require_once($CFG->dirroot.'/local/apsolu/classes/apsolu/payment.php');

            $data->images = Payment::get_statuses_images();

            $data->count_due_cards = 0;

            $cards = Payment::get_user_cards();
            $data->count_cards = count($cards);
            if ($data->count_cards > 0) {
                $gift = false;

                foreach ($cards as $card) {
                    $card->status = Payment::get_user_card_status($card);
                    $card->image = $data->images[$card->status]->image;

                    switch ($card->status) {
                        case Payment::DUE:
                            $data->count_due_cards++;
                            break;
                        case Payment::GIFT:
                            $gift = true;
                    }
                }

                if( $gift === false) {
                    unset($data->images[Payment::GIFT]);
                }

                $data->cards = array_values($cards);
                $data->images = array_values($data->images);
            }
        }

        // Gestion de l'onglet collaboratif.
        $data->collaborative = false;
        if (isset($CFG->is_siuaps_rennes) === true) {
            $sql = "SELECT ue.userid FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = 284 AND ue.userid = :userid";
            $data->collaborative = $DB->get_record_sql($sql, array('userid' => $USER->id));
        }

        // Display templates
        $this->content->text .= $OUTPUT->render_from_template('block_apsolu_dashboard/dashboard', $data);

        $PAGE->requires->css(new moodle_url($CFG->wwwroot.'/enrol/select/styles/ol.css'));
        $PAGE->requires->js_call_amd('enrol_select/select_mapping', 'initialise');

        return $this->content;
    }
}
