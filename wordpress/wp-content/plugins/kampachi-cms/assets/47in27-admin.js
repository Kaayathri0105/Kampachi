(function ($) {
    'use strict';

    $(document).on('click', '.kc47-pick', function () {
        const wrap = $(this).closest('.kc47-media');
        const kind = wrap.data('kind') === 'video' ? 'video' : 'image';
        const picker = wp.media({
            title: kind === 'video' ? 'Choose hero video' : 'Choose image',
            library: { type: kind },
            button: { text: 'Use this file' },
            multiple: false
        });
        picker.on('select', function () {
            const file = picker.state().get('selection').first().toJSON();
            wrap.find('.kc47-media-id').first().val(file.id);
            const preview = wrap.find('.kc47-media-preview').first();
            preview.empty();
            if (kind === 'image') {
                $('<img>', { src: file.sizes?.medium?.url || file.url, alt: '' }).appendTo(preview);
            } else {
                $('<span>').text(file.filename).appendTo(preview);
            }
        });
        picker.open();
    });

    $(document).on('click', '.kc47-clear', function () {
        const wrap = $(this).closest('.kc47-media');
        wrap.find('.kc47-media-id').first().val('0');
        wrap.find('.kc47-media-preview').first().empty();
    });

    $(document).on('click', '.kc47-pick-pdf', function () {
        const target = document.getElementById($(this).data('target'));
        if (!target) return;
        const picker = wp.media({
            title: 'Choose menu PDF',
            library: { type: 'application/pdf' },
            button: { text: 'Use PDF URL' },
            multiple: false
        });
        picker.on('select', function () {
            const file = picker.state().get('selection').first().toJSON();
            target.value = file.url;
        });
        picker.open();
    });

    $('#kc47-add-slide').on('click', function () {
        const container = $('#kc47-slides');
        const index = parseInt(container.attr('data-next-index'), 10) || 0;
        const html = $('#kc47-slide-template').html().replaceAll('[999999]', '[' + index + ']');
        container.append(html);
        container.attr('data-next-index', index + 1);
    });

    $(document).on('click', '.kc47-delete-slide', function () {
        $(this).closest('.kc47-slide').remove();
    });
})(jQuery);
