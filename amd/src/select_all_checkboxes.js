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
 * Permet de sélectionner/désélectionner tous les utilisateurs sur la page des notifications.
 *
 * @module     block_apsolu_dashboard/select_all_checkboxes
 * @copyright  2021 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        initialise: function() {
            // Gère les liens permettant de cocher toutes les checkboxes.
            $('.checkall').click(function() {
                var form = $(this).parents(':eq(5)');
                form.find("input[type='checkbox']").prop('checked', true);
                form.find('select[name="actions"]').prop('disabled', false);
            });

            // Gère les liens permettant de décocher toutes les checkboxes.
            $('.uncheckall').click(function() {
                var form = $(this).parents(':eq(5)');
                form.find("input[type='checkbox']").prop('checked', false);
                form.find('select[name="actions"]').prop('disabled', true);
            });
        }
    };
});
