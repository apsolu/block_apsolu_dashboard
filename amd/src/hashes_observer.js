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
 * Permet d'ouvrir l'onglet "Mes cours" lorsque l'utilisateur est déjà sur son tableau de bord
 * et qu'il clique sur le lien "Mes cours" dans la barre de navigation en haut de la page.
 *
 * @module     block_apsolu_dashboard/hashes_observer
 * @copyright  2023 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        initialise: function() {
            // Surveille le changement d'ancre dans l'URL.
            var toto = this;
            window.addEventListener('hashchange', function() {
                toto.showHash();
            });
        },
        showHash: function() {
            const hash = window.location.hash;

            if (!hash) {
                // Il n'y a pas d'ancre dans l'URL appelée.
                return;
            }

            const element = document.querySelector(".nav-tabs a[aria-controls=" + hash.substring(1) + "]");
            if (!element) {
                // L'élément pointé par l'ancre n'existe pas.
                return;
            }

            // Simule le clic de souris sur l'élément.
            const eventElement = new MouseEvent('click', {
                view: window,
                bubbles: true,
                cancelable: true
            });
            element.dispatchEvent(eventElement);
        }
    };
});
