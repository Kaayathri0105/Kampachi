(function ($) {
    'use strict';

    const form = $('#kc-home-form');
    let rowCount = Date.now();

    function prepareEditors(scope) {
        scope.find('textarea.kc-rich').each(function () {
            const element = $(this);
            const id = 'kc_rich_' + (++rowCount);
            element.attr('id', id);
            if (window.wp && wp.editor && wp.editor.initialize) {
                wp.editor.initialize(id, {
                    tinymce: { wpautop: true, toolbar1: 'bold,italic,link,unlink,bullist,numlist,undo,redo', toolbar2: '' },
                    quicktags: true,
                    mediaButtons: false
                });
            }
        });
    }

    function syncEditors(scope) {
        if (!window.tinymce) return;
        scope.find('textarea.kc-rich').each(function () {
            const editor = tinymce.get(this.id);
            if (editor) editor.save();
        });
    }

    function destroyEditors(scope) {
        syncEditors(scope);
        if (!window.wp || !wp.editor || !wp.editor.remove) return;
        scope.find('textarea.kc-rich').each(function () {
            if (this.id) wp.editor.remove(this.id);
        });
    }

    form.on('click', '.kc-add', function () {
        const type = $(this).data('target');
        const template = $('#tmpl-kc-' + type).html();
        if (!template) return;
        const unique = ++rowCount;
        const row = $(template.replace(/987654/g, String(unique)));
        $('#kc-' + type + '-list').append(row);
        prepareEditors(row);
    });

    form.on('click', '.kc-remove', function () {
        const row = $(this).closest('.kc-repeat');
        destroyEditors(row);
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

    form.on('click', '.kc-pick-video', function () {
        const picker = wp.media({ title: 'Choose hero video', button: { text: 'Use video' }, library: { type: 'video' }, multiple: false });
        picker.on('select', function () {
            const attachment = picker.state().get('selection').first().toJSON();
            $('.kc-video-id').val(attachment.id);
            $('.kc-video-name').text(attachment.filename);
        });
        picker.open();
    });

    form.on('click', '.kc-clear-video', function () {
        $('.kc-video-id').val('');
        $('.kc-video-name').text('No video selected');
    });

    form.on('change', '.kc-offer-choices input', function () {
        const checked = $('.kc-offer-choices input:checked');
        if (checked.length > 3) {
            $(this).prop('checked', false);
            window.alert('Choose a maximum of three promotions.');
        }
        $('.kc-offer-count').text('Selected: ' + $('.kc-offer-choices input:checked').length + ' / 3');
    });

    form.on('submit', function () {
        if (window.tinymce) tinymce.triggerSave();
    });

    $('.kc-repeat-list .kc-repeat').each(function () { prepareEditors($(this)); });
})(jQuery);
