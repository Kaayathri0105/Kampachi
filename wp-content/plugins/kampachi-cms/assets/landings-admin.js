(function ($) {
    'use strict';

    function showThumbnails(fieldId, items) {
        const list = $('#' + fieldId + '-thumbnails').empty();
        items.forEach(function (item) {
            const url = item.sizes?.medium?.url || item.sizes?.thumbnail?.url || item.url;
            if (!url) {
                return;
            }

            $('<li>').append(
                $('<img>').attr({ src: url, alt: '' }),
                $('<span>').text(item.title || item.filename || 'Image #' + item.id)
            ).appendTo(list);
        });
    }

    $(document).on('click', '.kc-landing-media-picker', function () {
        const button = $(this);
        const fieldId = button.data('field');
        const field = $('#' + fieldId);
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
            $('#' + fieldId + '-preview').text(multiple ? ids.length + ' selected images' : (items[0]?.filename || 'No video'));
            if (type === 'image') {
                showThumbnails(fieldId, items);
            }
        });
        picker.open();
    });

    $(document).on('click', '.kc-landing-media-clear', function () {
        const fieldId = $(this).data('field');
        $('#' + fieldId).val('').trigger('change');
        $('#' + fieldId + '-preview').text('No media selected');
        $('#' + fieldId + '-thumbnails').empty();
    });
})(jQuery);
