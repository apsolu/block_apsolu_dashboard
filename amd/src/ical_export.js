// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.tr
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Module javascript pour l'offre de formations.
 *
 * @module     block_apsolu_dashboard/ical_export
 * @copyright  2026 Université Rennes 2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/modal', 'core/str', 'core/templates', 'core/notification', 'core/modal_events', 'core/config'],
    function($, Modal, Str, Templates, Notification, ModalEvents, Config) {

    var helpModalPromise = null;
    var currentPage = 0;

    /**
     * Positionne le focus sur le contenu de la modale.
     *
     * @param {Modal} modal la modale d'aide.
     * @return {void}
     */
    var focusContent = function(modal) {
        modal.getRoot().find('.modal-body').attr('tabindex', -1).trigger('focus');
    };

    /**
     * Gestion des boutons page précédente / page suivante / fermer.
     *
     * @param {Modal} modal la modale d'aide.
     * @return {void}
     */
    var updateNavButtons = function(modal) {
        var root = modal.getRoot();
        root.find('[data-action="help-prev"]').prop('hidden', currentPage === 0);
        root.find('[data-action="help-next"]').prop('hidden',  currentPage === 2);
        root.find('.btn-primary[data-action="hide"]').prop('hidden',  currentPage !== 2);
    };

    /**
     * Positionne le focus sur le contenu de la modale.
     *
     * @param {Modal} modal la modale d'aide.
     * @param {Number} index l'index de la page.
     * @return {void}
     */
    var renderPage = function(modal, index) {
        let data = index == 0 ? {isFirstStep: true} : (index === 1 ? {isSecondStep: true} : {isThirdStep: true});
        currentPage = index;
        data.wwwroot = Config.wwwroot;
        Templates.render(
            'block_apsolu_dashboard/ical_export_help_modal', data
        ).then(function(html) {
            modal.setBody(html);
            updateNavButtons(modal);
            focusContent(modal);
            return;
        }).catch(Notification.exception);
    };

    /**
     * Création de la modale pour exporter le lien .ics.
     * Affiche un lien pour obtenir de l'aide sur l'export vers Partage dans une modale au premier plan.
     *
     * @return {Promise} Résolue avec l'instance de la modale d'aide
     */
    var getHelpModal = function() {
        // La modale est créée une seule fois et réaffichée à la demande.
        if (!helpModalPromise) {
            helpModalPromise = Promise.all([
                Templates.render('block_apsolu_dashboard/ical_export_help_modal', {isFirstStep: true}),
                Templates.render('block_apsolu_dashboard/ical_export_help_footer', {}),
                Str.get_string('export_help_partage', 'block_apsolu_dashboard'),
            ]).then(function(results) {
                var bodyHtml = results[0];
                var footerHtml = results[1];
                var titleStr = results[2];

                return Modal.create({
                    title: titleStr,
                    body: bodyHtml,
                    footer: footerHtml,
                    large: true,
                    scrollable: true,
                    removeOnClose: false,
                });
            }).then(function(modal) {
                currentPage = 0;
                updateNavButtons(modal);

                // Navigation (page précédente / suivante).
                modal.getRoot().on('click', '[data-action="help-prev"]', function() {
                    if (currentPage > 0) {
                        renderPage(modal, currentPage - 1);
                    }
                });
                modal.getRoot().on('click', '[data-action="help-next"]', function() {
                    if (currentPage < 2) {
                        renderPage(modal, currentPage + 1);
                    }
                });

                // Ajout de classes dans le header et footer de la modal.
                modal.getRoot().addClass('apsolu-custom-modal apsolu-custom-modal-fg');
                modal.getRoot().find('.modal-header').addClass('text-primary');

                // Récupère le focus automatique pour le placer sur le body de la modal.
                modal.getRoot().on(ModalEvents.shown, function() {
                    focusContent(modal);
                });
                return modal;
            });
        }
        return helpModalPromise;
    };

    /**
     * Création de la modale pour exporter le lien .ics.
     * Affiche un lien pour obtenir de l'aide sur l'export vers Partage dans une modale au premier plan.
     *
     * @param {string} wwwical le lien d'export
     * @return {Modal} la modal pour exporter le lien de l'agenda numérique.
     */
    var showExportModal = function(wwwical) {
        Modal.create({
            title: Str.get_string('export_calendar_url', 'block_apsolu_dashboard'),
            body: Templates.render('block_apsolu_dashboard/ical_export_modal', {wwwical: wwwical}),
            large: true,
            removeOnClose: true,
        }).then(function(modal) {
            modal.getRoot().on('click', '[data-action="show-ical-help"]', function(e) {
                e.preventDefault();
                // Affichage de l'aide au clic sur le lien.
                getHelpModal().then(function(helpModal) {
                    renderPage(helpModal, 0);
                    helpModal.show();
                });
            });

            initialiseCopyButton(modal);

            modal.getRoot().addClass('apsolu-custom-modal apsolu-custom-modal-bg');
            // Récupère le focus automatique pour le placer sur le body de la modal.
            modal.getRoot().on(ModalEvents.shown, function() {
                focusContent(modal);
            });
            modal.getRoot().find('.modal-header').addClass('text-bg-primary');
            modal.getRoot().find('.modal-header .btn-close').addClass('btn-close-white');

            // Affichage de la modale.
            modal.show();

            return modal;
        });
    };

    /**
     * Fonction permettant d'ajouter une bouton "copier" à côté du permalien dans la modal.
     *
     * @param {Object} modal L'instance de modal.
     */
    function initialiseCopyButton(modal) {
        modal.getRoot().on('click', '.apsolu-ical-link-copy', function(e) {
            var button = $(e.currentTarget);
            var icon = button.find('i');
            var value = button.attr('data-value');

            copyToClipboard(value).then(function() {
                // Une fois le texte copié on affiche brièvement une icône de succès.
                icon.removeClass('fa-copy').addClass('fa-check');

                setTimeout(function() {
                    icon.removeClass('fa-check').addClass('fa-copy');
                }, 1500);

                return null;
            });
        });
    }

    /**
     * Fonction permettant de copier le contenu du permalien au clic sur l'icône dans la modal.
     *
     * @param {String} text Le texte à copier.
     * @return {Promise}
     */
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        // Contournement pour anciens navigateurs / contexte http non sécurisé.
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            document.execCommand('copy');
        } finally {
            document.body.removeChild(textarea);
        }

        return Promise.resolve();
    }

    return {
        initialise: function(wwwical) {
            document.addEventListener('click', function(e) {
                if (e.target.closest('[data-action="show-ical-export"]')) {
                    e.preventDefault();
                    showExportModal(wwwical);
                }
            });
        }
    };
});