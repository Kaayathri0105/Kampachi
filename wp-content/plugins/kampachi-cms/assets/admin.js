(function ($) {
    'use strict';

    const form = $('#kc-home-form');
    let rowCount = Date.now();

    form.on('click', '.kc-add', function () {
        const type = $(this).data('target');
        const template = $('#tmpl-kc-' + type).html();
        if (!template) return;
        const unique = ++rowCount;
        const row = $(template.replace(/987654/g, String(unique)));
        $('#kc-' + type + '-list').append(row);
    });

    form.on('click', '.kc-remove', function () {
        const row = $(this).closest('.kc-repeat');
        row.remove();
    });

    form.on('click', '.kc-pick-media', function () {
        const field = $(this).closest('.kc-media');
        const picker = wp.media({ title: 'Choose image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
        picker.on('select', function () {
            const attachment = picker.state().get('selection').first().toJSON();
            field.find('.kc-media-id').val(attachment.id);
            const src = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
            field.find('.kc-image-preview').html($('<img>', { src: src, alt: '' }));
        });
        picker.open();
    });

    form.on('click', '.kc-clear-media', function () {
        const field = $(this).closest('.kc-media');
        field.find('.kc-media-id').val('');
        field.find('.kc-image-preview').empty();
    });

    function updateVideoPreview(id, filename, url) {
        const preview = form.find('.kc-home-video-preview')[0];
        form.find('.kc-video-id').val(id);
        form.find('.kc-video-name').text(filename);
        preview.pause();
        if (url) {
            preview.src = url;
        } else {
            preview.removeAttribute('src');
        }
        preview.hidden = !url;
        preview.load();
    }

    form.on('click', '.kc-pick-video', function () {
        const picker = wp.media({
            title: 'Choose hero video',
            button: { text: 'Use video' },
            library: { type: 'video' },
            multiple: false
        });
        picker.on('select', function () {
            const attachment = picker.state().get('selection').first().toJSON();
            updateVideoPreview(attachment.id, attachment.filename, attachment.url);
        });
        picker.open();
    });

    form.on('click', '.kc-clear-video', function () {
        updateVideoPreview('', 'No video selected', '');
    });

    form.on('change', '.kc-offer-choices input', function () {
        const checked = $('.kc-offer-choices input:checked');
        if (checked.length > 3) {
            $(this).prop('checked', false);
            window.alert('Choose a maximum of three promotions.');
        }
        $('.kc-offer-count').text('Selected: ' + $('.kc-offer-choices input:checked').length + ' / 3');
    });
})(jQuery);
