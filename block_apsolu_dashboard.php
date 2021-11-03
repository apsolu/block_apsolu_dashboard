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
 * Gère l'affichage du bloc Mon espace.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use UniversiteRennes2\Apsolu\Payment;
use local_apsolu\core\attendance as Attendance;

defined('MOODLE_INTERNAL') || die();

/**
 * Classe principale du module block_apsolu_dashboard.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_apsolu_dashboard extends block_base {
    /**
     * Initialise la classe block_apsolu_dashboard.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();

        $this->currenttime = time();
        $this->maxtime = $this->currenttime + (45 * DAYSECS); // Affiche les rendez-vous des 45 prochains jours.
    }

    /**
     * Initialise le bloc.
     */
    public function init() {
        $this->title = get_string('title', 'block_apsolu_dashboard');
    }

    /**
     * Default return is false - header will be shown
     *
     * @return boolean
     */
    public function hide_header() {
        return true;
    }

    /**
     * Retourne une session formatée correctement pour l'affichage.
     *
     * @param stdClass $session Mets en forme une session.
     *
     * @return stdClass Retourne une session formatée pour le rendu HTML.
     */
    private function format_session($session) {
        global $CFG;

        $today = mktime(23, 59, 59);
        $tomorrow = $today + 24 * 60 * 60;

        $session->soon = false;
        if ($today > $session->sessiontime) {
            $session->soon = true;
            $formatdate = '%FT%T%z|'.get_string('today', 'calendar').' '.get_string('strftimetime');
        } else if ($tomorrow > $session->sessiontime) {
            $session->soon = true;
            $formatdate = '%FT%T%z|'.get_string('tomorrow', 'calendar').' '.get_string('strftimetime');
        } else {
            $formatdate = '%FT%T%z|'.get_string('strftimedayshort').' '.get_string('strftimetime');
        }

        list($start, $startstr) = explode('|', userdate($session->sessiontime, $formatdate));

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

        $listname = enrol_select_plugin::get_enrolment_list_name($session->status);
        switch ($session->status) {
            case enrol_select_plugin::ACCEPTED:
                $session->enrolment_accepted = $listname;
                break;
            case enrol_select_plugin::MAIN:
                $session->enrolment_main = $listname;
                break;
            case enrol_select_plugin::WAIT:
                $session->enrolment_wait = $listname;
                break;
            case enrol_select_plugin::DELETED:
                $session->enrolment_deleted = $listname;
        }

        $session->link = html_writer::link($CFG->wwwroot.'/course/view.php?id='.$session->courseid, $session->label);

        $session->start = $start;
        $session->startstr = $startstr;
        $session->end = $end;
        $session->endstr = $endstr;

        $session->defaultsessiontime = (strftime('%u%H:%M', $session->sessiontime) === $session->numweekday.$session->starttime);

        return $session;
    }

    /**
     * Retourne la liste des cours où l'utilisateur courant étudie. Cette méthode est utilisée pour l'onglet "Mes cours".
     *
     * @param int|null $courseid Identifiant du cours.
     *
     * @return array Retourne un tuple de données array(liste_des_cours[], nombre de cours)
     */
    private function get_attendances($courseid = null) {
        global $USER;

        $attendances = Attendance::getUserPresencesPerCourses($USER->id);

        if ($courseid !== null) {
            if (isset($attendances[$courseid]) === false) {
                $attendances[$courseid] = new stdClass();
            }

            return array($attendances[$courseid]);
        }

        return array_values($attendances);
    }

    /**
     * Retourne la liste des cours où l'utilisateur courant étudie. Cette méthode est utilisée pour l'onglet "Mes cours".
     *
     * @param string $archetype Nom de l'archétype du rôle. TODO: à remplacer par une permission Moodle.
     *
     * @return array Retourne un tuple de données array(liste_des_cours[], nombre de cours)
     */
    private function get_courses($archetype = 'student') {
        global $DB, $USER;

        $courses = array();
        $countcourses = 0;

        $roles = role_fix_names($DB->get_records('role', array(), 'sortorder'));

        $sql = "SELECT ac.id, act.name".
            " FROM {apsolu_calendars} ac".
            " JOIN {apsolu_calendars_types} act ON act.id = ac.typeid";
        $calendartypes = $DB->get_records_sql($sql);

        $sql = "SELECT c.id, c.fullname, e.id AS enrolid, e.customint7, e.customint8, e.enrol, e.customchar1 AS calendarid,".
            " ra.roleid, apc.id AS apsolucourse, ue.status".
            " FROM {course} c".
            " LEFT JOIN {apsolu_courses} apc ON apc.id = c.id".
            " JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = 50".
            " JOIN {role_assignments} ra ON ctx.id = ra.contextid".
            " JOIN {role} r ON r.id = ra.roleid".
            " JOIN {enrol} e ON c.id = e.courseid AND e.status = 0 AND ra.itemid = e.id".
            " JOIN {user_enrolments} ue ON e.id = ue.enrolid AND ue.userid = ra.userid".
            " WHERE ra.userid = :userid".
            " AND r.archetype = :archetype".
            " AND c.visible = 1". // Cours visible.
            " AND e.status = 0". // Méthode d'inscription activée.
            " ORDER BY c.fullname, e.customint7 DESC";
        $parameters = array('userid' => $USER->id, 'archetype' => $archetype);

        $recordset = $DB->get_recordset_sql($sql, $parameters);
        foreach ($recordset as $course) {
            if (isset($courses[$course->id]) === false) {
                $course->{'listname'.$course->status} = enrol_select_plugin::get_enrolment_list_name($course->status);
                $course->viewable = false;
                $course->enrolments = array();
                $course->count_enrolments = 0;
                $courses[$course->id] = $course;

                $countcourses++;
            }

            $startcourse = $course->customint7;
            $endcourse = $course->customint8;

            if (time() >= $startcourse && time() <= (is_null($endcourse) ? time() : $endcourse)) {
                $course->viewable = ($course->status === enrol_select_plugin::ACCEPTED);

                // On force la conservation les données de l'inscription en cours pour trier les cours par statut.
                $courses[$course->id]->status = $course->status;
                $courses[$course->id]->customint7 = $startcourse;
                $courses[$course->id]->{'listname'.$course->status} = enrol_select_plugin::get_enrolment_list_name($course->status);
            }

            $enrolment = new stdClass();
            $enrolment->role = $roles[$course->roleid]->localname;

            $enrolment->calendar = '';
            if ($course->enrol === 'select' && isset($calendartypes[$course->calendarid]) === true) {
                $enrolment->calendar = $calendartypes[$course->calendarid]->name;
            }

            $listname = enrol_select_plugin::get_enrolment_list_name($course->status);
            switch ($course->status) {
                case enrol_select_plugin::ACCEPTED:
                    $enrolment->enrolment_accepted = $listname;
                    break;
                case enrol_select_plugin::MAIN:
                    $enrolment->enrolment_main = $listname;
                    break;
                case enrol_select_plugin::WAIT:
                    $enrolment->enrolment_wait = $listname;
                    break;
                case enrol_select_plugin::DELETED:
                    $enrolment->enrolment_deleted = $listname;
            }

            $courses[$course->id]->enrolments[] = $enrolment;
            $courses[$course->id]->count_enrolments++;
        }
        $recordset->close();

        // Tri les cours par statut, date de début du cours, nom du cours.
        uasort($courses, function($a, $b) {
            if ($a->status !== $b->status) {
                if ($a->status > $b->status) {
                    return 1;
                } else {
                    return -1;
                }
            }

            if ($a->customint7 !== $b->customint7) {
                if ($a->customint7 < $b->customint7) {
                    return 1;
                } else {
                    return -1;
                }
            }

            if ($a->fullname !== $b->fullname) {
                if ($a->fullname > $b->fullname) {
                    return 1;
                } else {
                    return -1;
                }
            }

            return 0;
        });

        return array(array_values($courses), $countcourses);
    }

    /**
     * Retourne la liste des cours où l'utilisateur enseigne.
     *
     * @return array Retourne un tuple de données :
     *      array(liste_des_cours[], nombre de cours, liste_des_autres_cours[], nombre de cours 'autres')
     */
    private function get_teachings() {
        global $DB, $USER;

        $mains = array();
        $countmains = 0;

        $others = array();
        $countothers = 0;

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

        $recordset = $DB->get_recordset_sql($sql, $parameters);
        foreach ($recordset as $course) {
            // Différencie les cours apsolu et les 'autres' cours (meta-cours, etc).
            if ($course->apsolucourse === null) {
                $others[$course->id] = $course;
                $countothers++;
            } else {
                $mains[$course->id] = $course;
                $countmains++;
            }
        }
        $recordset->close();

        return array(array_values($mains), $countmains, array_values($others), $countothers);
    }

    /**
     * Définit la liste des contacts par cours.
     *
     * @return void
     */
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

    /**
     * Définit la liste des lieux de pratique par cours.
     *
     * @return void
     */
    private function set_locations() {
        global $DB;

        $this->locations = array();
        foreach ($DB->get_records('apsolu_locations') as $location) {
            $this->locations[$location->name] = $location;
        }
    }

    /**
     * Retourne une liste de rendez-vous à venir formatés correctement pour l'affichage.
     *
     * - affiche la 1ère session pour les inscriptions sur liste principale (seulement si la session est à venir)
     * - ajoute les informations sur les enseignants
     * - ajoute les informations sur les lieux de pratique
     * - ignore les rendez-vous déjà terminés
     * - ignore les rendez-vous sur liste complémentaire (sauf pour Rennes)
     * - recalcule l'heure de fin d'une session modifiée
     *
     * @return array Liste de rendez-vous.
     */
    public function get_rendez_vous() {
        global $CFG, $DB, $USER;

        $lists = array();
        $sessions = array();

        $sql = "SELECT sess.sessiontime, sess.courseid, sess.locationid, c.fullname, apc.event,".
            " aps.name AS skill, cc.name AS activity, ue.status, ue.timestart, ue.timeend,".
            " apc.numweekday, apc.starttime, apc.endtime, apc.locationid AS defaultlocationid, apl.name AS location".
            " FROM {apsolu_attendance_sessions} sess".
            " JOIN {course} c ON c.id = sess.courseid".
            " JOIN {course_categories} cc ON cc.id = c.category".
            " JOIN {apsolu_courses} apc ON apc.id = c.id".
            " JOIN {apsolu_skills} aps ON aps.id = apc.skillid".
            " JOIN {apsolu_locations} apl ON apl.id = sess.locationid".
            " JOIN {enrol} e ON c.id = e.courseid".
            " JOIN {user_enrolments} ue ON e.id = ue.enrolid".
            " WHERE c.visible = 1".
            // Seulement les méthodes d'inscription actives.
            " AND e.status = 0".
            // Seulement les inscriptions acceptées, sur liste principale ou sur liste complémentaire.
            " AND ue.status IN (0, 2, 3)".
            // Seulement les cours dont l'inscription n'est pas expirée (note: mais peut-être qu'elle n'a pas commencé...).
            " AND (ue.timeend = 0 OR ue.timeend > :currenttime)".
            // Seulement les sessions correspondantes à la période d'inscription au cours.
            " AND (sess.sessiontime BETWEEN ue.timestart AND ue.timeend OR ue.timeend = 0)".
            " AND sess.sessiontime <= :maxtime".
            " AND ue.userid = :userid".
            " ORDER BY sess.sessiontime, c.fullname";
        $params = array('userid' => $USER->id, 'currenttime' => $this->currenttime, 'maxtime' => $this->maxtime);

        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $session) {
            // Traite les inscriptions sur liste principale et sur liste complémentaire.
            if (in_array($session->status, array(enrol_select_plugin::MAIN, enrol_select_plugin::WAIT), $strict = true) === true) {
                if (isset($CFG->is_siuaps_rennes) === false && $session->status === enrol_select_plugin::WAIT) {
                    // Seul le SIUAPS de Rennes souhaite afficher les rendez-vous pour une personne sur liste complémentaire.
                    continue;
                }

                if (isset($lists[$session->courseid]) === true) {
                    // On affiche que la première session pour une personne sur liste principale ou sur liste complémentaire.
                    continue;
                }

                // Variable permettant de savoir si nous avons déjà traité la première session d'un cours.
                $lists[$session->courseid] = true;
            }

            $duration = 0;

            $endtime = explode(':', $session->endtime);
            $starttime = explode(':', $session->starttime);

            if (isset($endtime[1], $starttime[1]) === true) {
                $duration = ($endtime[0] * 60 * 60 + $endtime[1] * 60) - ($starttime[0] * 60 * 60 + $starttime[1] * 60);
            }

            if ($duration <= 0) {
                $duration = 60 * 60;
            }

            if (userdate($session->sessiontime, '%H:%M') !== $session->starttime) {
                // Recalcule l'heure de fin du cours si ce n'est plus l'heure par défaut.
                $session->endtime = userdate($session->sessiontime + $duration, '%H:%M');
            }

            if ($session->sessiontime + $duration < $this->currenttime) {
                // N'affiche pas ce rendez-vous si la session du cours est déjà terminée.
                continue;
            }

            // Vérifie que l'inscription au cours est acceptée et a débuté.
            $session->started = ($session->status === enrol_select_plugin::ACCEPTED && $session->timestart <= time());

            if (isset($this->courses_contacts[$session->courseid]) === false) {
                $this->courses_contacts[$session->courseid] = new stdClass();
                $this->courses_contacts[$session->courseid]->teachers = array();
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
        $recordset->close();

        return $sessions;
    }

    /**
     * Return the content of this block.
     *
     * @return stdClass the content
     */
    public function get_content() {
        global $CFG, $DB, $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        require_once($CFG->dirroot.'/enrol/select/lib.php');

        $this->content = new stdClass;
        $this->content->text = '';

        $this->set_contacts();
        $this->set_locations();

        $attributes = array('class' => 'apsolu-location-markers-img', 'width' => '15px', 'height' => '20px');
        $this->marker_pix = $OUTPUT->pix_icon('a/marker', $alt = '', 'enrol_select', $attributes);

        // Template data.
        $data = new stdClass();
        $data->wwwroot = $CFG->wwwroot;
        $data->is_siuaps_rennes = isset($CFG->is_siuaps_rennes);
        $data->isonwaitlist = false;
        $data->sessions = array();
        $data->count_sessions = 0;
        $data->enrolment_errors = array();
        $data->count_enrolment_errors = 0;
        $data->marker_pix = $this->marker_pix;

        // Rendez-vous à venir (utilisateurs inscrits sur la liste des acceptés).
        foreach ($this->get_rendez_vous() as $session) {
            $data->sessions[] = $this->format_session($session);
            $data->count_sessions++;

            if (isset($CFG->is_siuaps_rennes) === true && $session->status === enrol_select_plugin::WAIT) {
                $data->isonwaitlist = true;
            }
        }

        // Vérifie si l'étudiant peut s'inscrire à ce cours, afin d'afficher un avertissement à l'étudiant.
        if (isset($CFG->is_siuaps_rennes) === true) {
            // TODO: rendre plus flexible.
            $sesame = $DB->get_record('user_info_data', array('userid' => $USER->id, 'fieldid' => 11));
            if ($sesame !== false && $sesame->data === '1') {
                require_once($CFG->dirroot.'/enrol/select/locallib.php');

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
                    $params = array('userid' => $USER->id, 'roleid' => $enrolment->roleid, 'enrolid' => $enrolment->enrolid);
                    $allow = $DB->get_records_sql($sql, $params);
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

        // Récupère les présences de l'utilisateur.
        $data->attendances = $this->get_attendances();
        $data->count_attendances = count($data->attendances);

        // Récupère les cours où l'utilisateur enseigne.
        list($data->main_teachings, $data->count_main_teachings,
            $data->other_teachings, $data->count_other_teachings) = $this->get_teachings();
        $data->count_teachings = $data->count_main_teachings + $data->count_other_teachings;

        if ($data->count_teachings > 0) {
            // TODO: rendre plus flexible.
            $data->shnu = false;
            if (isset($CFG->is_siuaps_rennes) === true) {
                // Note: courseid 320 (2017-2018) ; courseid 423 (2019-2020).
                $shnu = $DB->get_record('role_assignments', array('contextid' => 29119, 'roleid' => 3, 'userid' => $USER->id));
                $data->shnu = ($shnu !== false);
            }

            // Vérifie que la plateforme utilise les éléments de notation.
            $data->grading = count($DB->get_records('apsolu_grade_items'));

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
            $params = array('teacherid' => $USER->id, 'userid' => $USER->id, 'lastlogin' => $USER->lastlogin);
            $data->pendingenrolments = count($DB->get_records_sql($sql, $params));
        }

        // Gestion de l'onglet "mes paiements".
        $paymentsstartdate = get_config('local_apsolu', 'payments_startdate');
        $paymentsenddate = get_config('local_apsolu', 'payments_enddate');
        $data->payments_open = (time() > $paymentsstartdate && time() < $paymentsenddate);
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

                if ($gift === false) {
                    unset($data->images[Payment::GIFT]);
                }

                $data->cards = array_values($cards);
                $data->images = array_values($data->images);
            }
        }

        // Gestion de l'onglet collaboratif.
        $data->collaborative = false;
        if (isset($CFG->is_siuaps_rennes) === true) {
            $sql = "SELECT ue.userid".
                " FROM {user_enrolments} ue".
                " JOIN {enrol} e ON e.id = ue.enrolid".
                " WHERE e.courseid = 284".
                " AND ue.userid = :userid";
            $data->collaborative = $DB->get_record_sql($sql, array('userid' => $USER->id));
        }

        // Gestion de l'onglet "Gestion des étapes".
        $data->manageetape = false;
        // Affiche le lien si le module apsolu_auth existe. Cache le lien aux gestionnaires et administrateurs du site.
        $authmoduleexists = is_dir($CFG->dirroot.'/local/apsolu_auth');
        if ($authmoduleexists === true && has_capability('moodle/site:configview', context_system::instance()) === false) {
            $data->manageetape = has_capability('local/apsolu_auth:manageetape', context_system::instance());
        }

        // Display templates.
        $this->content->text .= $OUTPUT->render_from_template('block_apsolu_dashboard/dashboard', $data);

        $this->page->requires->css(new moodle_url($CFG->wwwroot.'/enrol/select/styles/ol.css'));
        $this->page->requires->js_call_amd('enrol_select/select_mapping', 'initialise');

        return $this->content;
    }
}
