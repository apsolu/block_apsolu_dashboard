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
 * Classe le formulaire permettant d'extraire les étudiants inscrits à la FFSU.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Classe le formulaire permettant d'extraire les étudiants inscrits à la FFSU.
 *
 * @package    block_apsolu_dashboard
 * @copyright  2016 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_apsolu_dashboard_federation_export_form extends moodleform {
    /**
     * Définit les champs du formulaire.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        list($defaults, $institutions, $groups, $medicals, $paids, $sexes) = $this->_customdata;

        $mform->addElement('text', 'lastnames', get_string('studentname', 'local_apsolu'), array('size' => '48'));
        $mform->setType('lastnames', PARAM_TEXT);
        $mform->addHelpButton('lastnames', 'studentname', 'local_apsolu');

        $select = $mform->addElement('select', 'institutions', get_string('institution'), $institutions, array('size' => 6));
        $mform->setType('institutions', PARAM_TEXT);
        $mform->addRule('institutions', get_string('required'), 'required', null, 'client');
        $select->setMultiple(true);

        $mform->addElement('text', 'ufrs', get_string('ufrs', 'local_apsolu'), array('size' => '48'));
        $mform->setType('ufrs', PARAM_TEXT);
        $mform->addHelpButton('ufrs', 'ufrs', 'local_apsolu');

        $mform->addElement('text', 'departments', get_string('department'), array('size' => '48'));
        $mform->setType('departments', PARAM_TEXT);
        $mform->addHelpButton('departments', 'departments', 'local_apsolu');

        $select = $mform->addElement('select', 'groups', get_string('group'), $groups, array('size' => 10));
        $mform->setType('groups', PARAM_TEXT);
        $mform->addRule('groups', get_string('required'), 'required', null, 'client');
        $select->setMultiple(true);

        $select = $mform->addElement('select', 'sexes', get_string('sex', 'local_apsolu'), $sexes, array('size' => 4));
        $mform->setType('sexes', PARAM_TEXT);
        $mform->addRule('sexes', get_string('required'), 'required', null, 'client');

        $attributes = array('size' => 4);
        $label = get_string('medical_certificate', 'local_apsolu');
        $select = $mform->addElement('select', 'medicals', $label, $medicals, $attributes);
        $mform->setType('medicals', PARAM_TEXT);
        $mform->addRule('medicals', get_string('required'), 'required', null, 'client');

        $select = $mform->addElement('select', 'paids', get_string('payment', 'local_apsolu'), $paids, array('size' => 4));
        $mform->setType('paids', PARAM_TEXT);
        $mform->addRule('paids', get_string('required'), 'required', null, 'client');

        // Submit buttons.
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('show'));
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('export', 'local_apsolu'));

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

        // Set default values.
        $this->set_data($defaults);
    }
}
