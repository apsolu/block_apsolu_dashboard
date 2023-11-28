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
 * This file contains the moodle hooks for the apsolu_dashboard block.
 *
 * @package   block_apsolu_dashboard
 * @copyright 2023 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return a list of all the user preferences used by apsolu_dashboard.
 *
 * @return array
 */
function block_apsolu_dashboard_user_preferences() {
    $preferences = [];
    $preferences['block_apsolu_dahsboard_active_tab'] = [
        'type' => PARAM_ALPHA,
        'null' => NULL_NOT_ALLOWED,
        'default' => 'rendez-vous'
    ];

    return $preferences;
}
