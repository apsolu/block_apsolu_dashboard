define(['jquery'], function($) {
    return {
        initialise : function() {
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
    }
});
