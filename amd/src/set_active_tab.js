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
 * @copyright  2023 Université Rennes 2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Note: à remplacer par core_user/repository lorsqu'on sera en Moodle 4.3.
define(["block_apsolu_dashboard/preference", "core/notification"], function(UserRepository, Notification) {
    return {
        initialise: function(activeTab, userid) {
            var tab;
            // On redirige l'ancre "#main_courses" (navbar "Mes cours") vers #teachings ou #courses.
            const hash = window.location.hash;
            if (hash && hash == "#main_courses") {
                // Si l'utilisateur a des enseignements, l'ancre pointe vers cet onglet
                const teachTab = document.querySelector("#block-apsolu-dashboard-nav-tabs[role='tablist'] [href='#teachings']");
                if(teachTab) {
                    window.location.hash = '#teachings';
                    tab = teachTab;
                } else {
                    // Si l'utilisateur n'a pas l'onglet "Mes enseignements", l'ancre pointe vers "Mes activités".
                    window.location.hash = '#courses';
                    tab = document.querySelector("#block-apsolu-dashboard-nav-tabs[role='tablist'] [href='#courses']");
                }
            } else {
                // S'il n'y a pas d'ancre l'onglet à activer est le dernier onglet consulté par l'utilisateur (préférences).
                let tabSelector = "#block-apsolu-dashboard-nav-tabs li a[aria-controls=\"" + activeTab + "\"]";
                tab = document.querySelector(tabSelector);
            }
            if (tab) {
                // Active l'onglet.
                let currentActiveTab = document.querySelector("#block-apsolu-dashboard-nav-tabs li a.nav-link.active");
                if (currentActiveTab.getAttribute("aria-controls") != activeTab) {
                    // Retire les classes sur l'onglet actuellement actif.
                    let currentActiveTabName = currentActiveTab.getAttribute("aria-controls");
                    currentActiveTab.classList.remove("active");
                    currentActiveTab.setAttribute("tabindex", "-1");
                    currentActiveTab.setAttribute("aria-selected", "false");
                    document.querySelector("#apsolu-dashboard-tab-content #" + currentActiveTabName).
                        classList.remove(...["active", "show"]);

                    // Ajoute les classes sur l'onglet à afficher.
                    tab.classList.add("active");
                    tab.setAttribute("tabindex", "0");
                    tab.setAttribute("aria-selected", "true");
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
