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
 * Language strings.
 *
 * @package   block_apsolu_dashboard
 * @copyright 2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'APSOLU: Tableau de bord';
$string['title'] = 'APSOLU: Tableau de bord';

$string['apsolu_dashboard:addinstance'] = 'Ajouter un bloc '.$string['pluginname'];
$string['apsolu_dashboard:myaddinstance'] = 'Ajouter un nouveau bloc '.$string['pluginname'].' au tableau de bord';

$string['download_all_my_attendances'] = 'Télécharger toutes mes présences';
$string['no_description'] = 'Session régulière de cours';
$string['no_upcoming_events'] = 'Aucun rendez-vous à venir';

$string['pre-sessions'] = 'Séances de pré-rentrée';
$string['pre-sessions_notice'] = '<div class="alert alert-info">'.
    '<p><strong>Si vous n’avez pu vous pré-inscrire que sur liste complémentaire :</strong></p>'.
    '<p>Tout n’est pas perdu ! 2 possiblités :</p>'.
    '<ul>'.
    '<li>Vous pouvez tenter de vous présenter au 1er cours (sans garantie d’obtenir une place).</li>'.
    '<li>Vous attendez ; l’enseignant pourra vous notifier par mail si une place s’est libérée et s’il peut vous accueillir. N’oubliez pas de consulter votre adresse mail universitaire !</li>'.
    '</ul>'.
    '</div>';
$string['sessions'] = 'Séances hebdomadaires';

$string['important'] = 'Important :';
$string['unallowed_enrolment'] = 'D’après votre contrat pédagogique, vous ne pouvez pas :';
$string['unallowed_enrolment_to'] = 'être inscrit en <strong>{$a->rolename}</strong> au cours <strong>{$a->coursename}</strong>';
$string['unallowed_enrolment_contact'] = 'Merci de contacter votre enseignant pour plus d’information.';

$string['my_attendances'] = 'Mes présences';
$string['my_courses'] = 'Mes cours';
$string['my_payments'] = 'Mes paiements';
$string['my_rendez-vous'] = 'Mes rendez-vous';
$string['my_teachings'] = 'Mes enseignements';
$string['no_courses'] = 'Aucun cours';
$string['enrolment_type'] = 'Type d’inscription';
$string['courses_signup'] = 'S’inscrire à une activité';

$string['my_students'] = 'Liste de mes étudiants';
$string['my_ffsu'] = 'Liste FFSU';
$string['my_shnu'] = 'Liste SHNU';
$string['pending_enrolments'] = 'Inscription en attente d’approbation';
$string['last_enrolment'] = 'Date de la dernière inscription';
$string['pay'] = 'Payer';
$string['my_main_teachings'] = 'Mes créneaux';
$string['my_other_teachings'] = 'Mes autres enseignements';
$string['no_payment_required'] = 'Aucune inscription nécessitant un paiement.';

$string['my_graded_students'] = 'Mes étudiants à évaluer';
$string['contact_your_teacher'] = 'Contacter votre enseignant :';

$string['enrol_users'] = 'Gérer les inscriptions';
$string['export'] = 'Exporter la liste des inscrits';

$string['your_course_registration_is_not_yet_complete_you_must_request_your_license_number'] = 'Votre inscription à la FFSU n’est pas encore terminée. Vous devez aller au bout de la démarche pour finaliser votre <a href="{$a}/local/apsolu/federation/adhesion/index.php">demande de licence FFSU</a>.';
