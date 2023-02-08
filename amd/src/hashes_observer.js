// Permet d'ouvrir l'onglet "Mes cours" lorsque l'utilisateur est déjà sur son tableau de bord
// et qu'il clique sur le lien "Mes cours" dans la barre de navigation en haut de la page.
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
