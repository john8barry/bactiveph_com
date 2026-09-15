(function ($) {
    'use strict';
    $(function () {
        $('.bactive-colour-picker').wpColorPicker({ change: function () { $(this).closest('.bactive-colour-row').find('.bactive-review input').prop('checked', false); }, clear: function () { $(this).closest('.bactive-colour-row').find('.bactive-review input').prop('checked', false); } });
        $('.bactive-colour-row select').on('change', function () { $(this).closest('.bactive-colour-row').find('.bactive-review input').prop('checked', false); });
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
