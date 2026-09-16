(function ($) {
    'use strict';
    $(function () {
        var ready = false;
        function updateShade(row, colour) {
            if (!row.length) { return; }
            var mode = row.find('select').val();
            var shade = mode === 'inherit' ? row.attr('data-global-shade') : (colour === undefined ? row.find('.bactive-colour-picker').val() : colour);
            var text = row.attr('data-held') === '1' ? bactiveCatalogueEditor.held :
                mode === 'none' ? bactiveCatalogueEditor.nameOnly :
                /^#[a-f0-9]{6}$/i.test(shade) ? bactiveCatalogueEditor.shade.replace('%s', shade) :
                mode === 'inherit' ? bactiveCatalogueEditor.missingGlobal : bactiveCatalogueEditor.missingCustom;
            row.find('.bactive-shade-status').text(text);
        }
        $('.bactive-colour-picker').wpColorPicker({ change: function (event, ui) {
            if (!ready) { return; }
            var row = $(this).closest('.bactive-colour-row');
            row.find('select').val('custom');
            row.find('.bactive-review input').prop('checked', false);
            updateShade(row, ui.color.toString());
        }, clear: function () {
            if (!ready) { return; }
            var row = $(this).closest('.bactive-colour-row');
            row.find('.bactive-review input').prop('checked', false);
            updateShade(row, '');
        } });
        ready = true;
        $('.bactive-colour-row select').on('change', function () {
            var row = $(this).closest('.bactive-colour-row');
            row.find('.bactive-review input').prop('checked', false);
            updateShade(row);
        });
        $('.bactive-select-preview').on('click', function () {
            var row = $(this).closest('.bactive-colour-row');
            var frame = wp.media({ title: bactiveCatalogueEditor.title, button: { text: bactiveCatalogueEditor.button }, library: { type: 'image' }, multiple: false });
            frame.on('select', function () {
                var image = frame.state().get('selection').first().toJSON();
                row.find('.bactive-preview-id').val(image.id);
                row.find('.bactive-preview img').attr('src', image.url).prop('hidden', false);
                row.find('.bactive-review input').prop('checked', false);
            });
            frame.open();
        });
        $('.bactive-clear-preview').on('click', function () {
            var row = $(this).closest('.bactive-colour-row');
            row.find('.bactive-preview-id').val('0');
            row.find('.bactive-preview img').removeAttr('src').prop('hidden', true);
            row.find('.bactive-review input').prop('checked', false);
        });
        $('.bactive-edit-variation').on('click', function (event) {
            event.preventDefault();
            $('.product_data_tabs .variations_tab a').trigger('click');
        });
    });
})(jQuery);
