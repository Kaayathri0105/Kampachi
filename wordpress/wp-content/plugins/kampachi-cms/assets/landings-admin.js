(function ($) {
    'use strict';

    $(document).on('click', '.kc-landing-media-picker', function () {
        const button = $(this);
        const field = $('#' + button.data('field'));
        const multiple = button.data('multiple') === 1 || button.data('multiple') === '1';
        const type = button.data('type');
        const picker = wp.media({
            title: multiple ? 'Choose hero images' : 'Choose a hero video',
            button: { text: 'Use selected media' },
            library: { type: type },
            multiple: multiple
        });

        picker.on('select', function () {
            const items = picker.state().get('selection').toJSON();
            const ids = items.map((item) => item.id);
            field.val(multiple ? ids.join(',') : (ids[0] || '')).trigger('change');
            $('#' + button.data('field') + '-preview').text(multiple ? ids.length + ' selected images' : (items[0]?.filename || 'No video'));
        });
        picker.open();
    });

    $(document).on('click', '.kc-landing-media-clear', function () {
        const field = $('#' + $(this).data('field'));
        field.val('').trigger('change');
        $('#' + $(this).data('field') + '-preview').text('No media selected');
    });
})(jQuery);
