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
 * Classe pour le formulaire permettant d'extraire les sportifs de haut niveau universitaires.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Classe pour le formulaire permettant d'extraire les sportifs de haut niveau universitaires.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_apsolu_dashboard_shnu_export_form extends moodleform {
    /**
     * Définit les champs du formulaire.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        list($defaults, $institutions, $groups, $sexes) = $this->_customdata;

        $mform->addElement('text', 'lastnames', get_string('studentname', 'local_apsolu'), ['size' => '48']);
        $mform->setType('lastnames', PARAM_TEXT);
        $mform->addHelpButton('lastnames', 'studentname', 'local_apsolu');

        $select = $mform->addElement('select', 'institutions', get_string('institution'), $institutions, ['size' => 6]);
        $mform->setType('institutions', PARAM_TEXT);
        $mform->addRule('institutions', get_string('required'), 'required', null, 'client');
        $select->setMultiple(true);

        $mform->addElement('text', 'ufrs', get_string('fields_apsoluufr', 'local_apsolu'), ['size' => '48']);
        $mform->setType('ufrs', PARAM_TEXT);
        $mform->addHelpButton('ufrs', 'ufrs', 'local_apsolu');

        $mform->addElement('text', 'departments', get_string('department'), ['size' => '48']);
        $mform->setType('departments', PARAM_TEXT);
        $mform->addHelpButton('departments', 'departments', 'local_apsolu');

        $select = $mform->addElement('select', 'groups', get_string('group'), $groups, ['size' => 10]);
        $mform->setType('groups', PARAM_TEXT);
        $mform->addRule('groups', get_string('required'), 'required', null, 'client');
        $select->setMultiple(true);

        $select = $mform->addElement('select', 'sexes', get_string('sex', 'local_apsolu'), $sexes, ['size' => 4]);
        $mform->setType('sexes', PARAM_TEXT);
        $mform->addRule('sexes', get_string('required'), 'required', null, 'client');

        // Submit buttons.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('display', 'local_apsolu'));
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('export', 'local_apsolu'));

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);

        // Set default values.
        $this->set_data($defaults);
    }
}
