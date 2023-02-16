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
 * Test block_apsolu_dashboard class.
 *
 * @package   block_apsolu_dashboard
 * @copyright 2021 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_apsolu_dashboard;

use advanced_testcase;
use block_apsolu_dashboard;
use enrol_select_plugin;
use local_apsolu\core\attendancesession;
use local_apsolu\core\course;
use local_apsolu\core\location;
use local_apsolu\core\period;
use local_apsolu\core\skill;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot.'/blocks/moodleblock.class.php');
require_once($CFG->dirroot.'/blocks/apsolu_dashboard/block_apsolu_dashboard.php');
require_once($CFG->dirroot.'/course/lib.php');

/**
 * Classe PHPUnit permettant de tester la classe block_apsolu_dashboard.
 *
 * @copyright 2021 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_apsolu_dashboard_test extends advanced_testcase {
    /**
     * Initialise un environnement de test.
     *
     * @return void
     */
    protected function setUp() : void {
        parent::setUp();

        $this->resetAfterTest();
    }

    /**
     * Teste la méthode init().
     *
     * @covers \block_apsolu_dashboard::init()
     *
     * @return void
     */
    public function test_init() {
        $block = new block_apsolu_dashboard();
        $block->init();

        $this->assertSame(get_string('title', 'block_apsolu_dashboard'), $block->title);
    }

    /**
     * Teste la méthode format_rendez_vous().
     *
     * @covers \block_apsolu_dashboard::format_rendez_vous()
     *
     * @return void
     */
    public function test_format_rendez_vous() {

    }

    /**
     * Teste la méthode get_rendez_vous().
     *
     * @covers \block_apsolu_dashboard::get_rendez_vous()
     *
     * @return void
     */
    public function test_get_rendez_vous() {
        global $CFG, $DB, $USER;

        $backupuser = clone $USER;

        $block = new block_apsolu_dashboard();

        // Configure la période (ajoute 3 semaines, dont une passée).
        $countsessions = 3;
        $lastmonday = strtotime('last Monday');

        $weeks = array();
        for ($i = 0; $i < $countsessions; $i++) {
            $weeks[] = userdate($lastmonday, '%F');
            $lastmonday += WEEKSECS;
        }

        $period = new period();
        $data = (object) ['name' => 'period', 'generic_name' => 'period', 'weeks' => implode(',', $weeks)];
        $period->save($data);

        // Configure le niveau de pratique.
        $skill = new skill();
        $skill->name = 'skill 1';
        $skill->save();

        // Configure le lieu de pratique.
        $location = new location();
        $location->name = 'location 1';
        $location->save();

        // Configure le cours.
        $course = new course();
        $data = advanced_testcase::getDataGenerator()->get_plugin_generator('local_apsolu')->get_course_data();
        $data->periodid = $period->id;
        $data->skillid = $skill->id;
        $data->locationid = $location->id;
        $data->numweekday = '1';
        $data->weekday = 'monday';
        $course->save($data);

        // Ajoute la session passée qui n'est pas automatiquement créée.
        $session = new attendancesession();
        $session->name = 'session passée';
        list($year, $month, $day) = explode('-', $weeks[0]);
        $sessiontime = make_timestamp($year, $month, $day);
        $sessiontime += $course->get_session_offset();
        $session->sessiontime = $sessiontime;
        $session->courseid = $course->id;
        $session->activityid = $course->category;
        $session->locationid = $course->locationid;
        $session->timecreated = time();
        $session->timemodified = time();
        $session->save();

        // Configure la méthode d'inscription.
        $plugin = enrol_get_plugin('select');
        $default = $plugin->get_instance_defaults();
        $default['customint7'] = $lastmonday - YEARSECS;
        $default['customint8'] = $lastmonday + YEARSECS;

        $instanceid = $plugin->add_instance($course, $plugin->get_instance_defaults());

        $instance = $DB->get_record('enrol', array('id' => $instanceid));

        $roleid = '5';
        $timestart = 0;
        $timeend = 0;
        $USER = advanced_testcase::getDataGenerator()->create_user();

        // Contrôle qu'il y a bien 3 sessions pour ce cours.
        $this->assertSame($countsessions, $DB->count_records('apsolu_attendance_sessions', array('courseid' => $course->id)));

        // Teste une inscription sur la liste des acceptés. On ne doit pas voir que les 2 sessions à venir.
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend, enrol_select_plugin::ACCEPTED);

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame($countsessions - 1, $countrendezvous);

        // Teste une inscription sur liste principale. La 1ère session est déjà passée. Aucun rendez-vous à venir.
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend, enrol_select_plugin::MAIN);

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame(0, $countrendezvous);

        // Teste une inscription sur liste complémentaire. La 1ère session est déjà passée. Aucun rendez-vous à venir.
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend, enrol_select_plugin::WAIT);

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame(0, $countrendezvous);

        // Supprime la première session passée.
        $session->delete();

        // Teste une inscription sur liste principale. On doit voir un seul rendez-vous à venir.
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend, enrol_select_plugin::MAIN);
        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame(1, $countrendezvous);

        // Teste une inscription sur liste complémentaire. On ne doit pas voir de rendez-vous à venir.
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend, enrol_select_plugin::WAIT);

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame(0, $countrendezvous);

        // Teste le cas particulier de Rennes.
        $CFG->is_siuaps_rennes = true;

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame(1, $countrendezvous);

        // Teste l'affichage des sessions inférieures à 45 jours.
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend, enrol_select_plugin::ACCEPTED);

        unset($session->id);
        $session->sessiontime = ($block->maxtime - DAYSECS);
        $session->save();

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame($countsessions, $countrendezvous);

        // Teste l'absence d'affichage d'une session positionnée à plus de 45 jours.
        unset($session->id);
        $session->sessiontime = ($block->maxtime + DAYSECS);
        $session->save();

        $countrendezvous = count($block->get_rendez_vous());
        $this->assertSame($countsessions, $countrendezvous);

        // Restaure l'utilisateur.
        $USER = $backupuser;
    }
}
