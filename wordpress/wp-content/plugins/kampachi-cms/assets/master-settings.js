(function ($) {
    'use strict';
    $(function () {
        $('.kc-color').wpColorPicker();
        $('.kc-pick-font').on('click', function () {
            const row = $(this).closest('td');
            const media = wp.media({
                title: 'Choose or upload TTF font',
                button: { text: 'Use this font' },
                library: { type: 'font/ttf' },
                multiple: false
            });
            media.on('select', function () {
                const item = media.state().get('selection').first().toJSON();
                if (!item.filename || !/\.ttf$/i.test(item.filename)) {
                    window.alert('Please select a TTF (.ttf) font file.');
                    return;
                }
                row.find('.kc-font-id').val(String(item.id));
                row.find('.kc-font-label').text(item.filename);
            });
            media.open();
        });
        $('.kc-clear-font').on('click', function () {
            const row = $(this).closest('td');
            row.find('.kc-font-id').val('0');
            row.find('.kc-font-label').text('No TTF selected');
        });
    });
}(jQuery));
