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
 * Enregistre le dernier onglet utilisé sur le tableau de bord dans les préférences de l'utilisateur afin de réafficher
 * automatiquement cet onglet lors de la prochaine visite du tableau de bord.
 *
 * @module     block_apsolu_dashboard/set_active_tab
 * @copyright  2023 Université Rennes 2 <dsi-contact@univ-rennes2.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Note: à remplacer par core_user/repository lorsqu'on sera en Moodle 4.3.
define(["block_apsolu_dashboard/preference", "core/notification"], function(UserRepository, Notification) {
    return {
        initialise: function(activeTab, userid) {
            let tabSelector = "#block-apsolu-dashboard-nav-tabs li a[aria-controls=\"" + activeTab + "\"]";
            let tab = document.querySelector(tabSelector);
            if (tab) {
                // Active l'onglet.
                let currentActiveTab = document.querySelector("#block-apsolu-dashboard-nav-tabs li a.nav-link.active");
                if (currentActiveTab.getAttribute("aria-controls") != activeTab) {
                    // Retire les classes sur l'onglet actuellement actif.
                    let currentActiveTabName = currentActiveTab.getAttribute("aria-controls");
                    currentActiveTab.classList.remove("active");
                    document.querySelector("#apsolu-dashboard-tab-content #" + currentActiveTabName).
                        classList.remove(...["active", "show"]);

                    // Ajoute les classes sur l'onglet à afficher.
                    tab.classList.add("active");
                    document.querySelector("#apsolu-dashboard-tab-content #" + activeTab).classList.add(...["active", "show"]);
                }
            }

            // Ajoute un écouteur sur chaque onglet pour enregistrer les préférences.
            document.querySelectorAll("#block-apsolu-dashboard-nav-tabs li a.nav-link").forEach(function(tab) {
                tab.addEventListener("click", function() {
                    let value = tab.getAttribute("aria-controls");
                    UserRepository.setUserPreference("block_apsolu_dahsboard_active_tab", value, userid)
                        .catch(Notification.exception);
                });
            });
        },
    };
});
