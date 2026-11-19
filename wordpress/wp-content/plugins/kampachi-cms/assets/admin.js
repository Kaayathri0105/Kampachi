(function ($) {
    'use strict';

    const form = $('#kc-home-form, [data-kc-offer-hero-form]');
    const descriptionEditor = window.kampachiDescriptionEditor;
    let rowCount = Date.now();

    function initializeDescriptions(scope) {
        scope.find('.kc-home-description-editor').each(function () {
            descriptionEditor.initialize(this);
        });
    }

    $(function () { initializeDescriptions(form); });

    form.on('submit', function () {
        form.find('.kc-home-description-editor').each(function () {
            descriptionEditor.save(this);
        });
    });

    form.on('click', '.kc-add', function () {
        const type = $(this).data('target');
        const template = $('#tmpl-kc-' + type).html();
        if (!template) return;
        const unique = ++rowCount;
        const row = $(template.replace(/987654/g, String(unique)));
        $('#kc-' + type + '-list').append(row);
        initializeDescriptions(row);
    });

    form.on('click', '.kc-remove', function () {
        const row = $(this).closest('.kc-repeat');
        row.find('.kc-home-description-editor').each(function () {
            descriptionEditor.remove(this);
        });
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

    form.on('change', '[data-kc-hero-source]', function () {
        const selectedSource = this.value;
        $(this).closest('[data-kc-offer-hero-form]').find('[data-kc-hero-source-panel]').each(function () {
            this.hidden = this.dataset.kcHeroSourcePanel !== selectedSource;
        });
    });

    function refreshPostPicker(picker) {
        const ids = picker.find('.kc-home-selected-posts li').map(function () {
            return this.getAttribute('data-post-id');
        }).get();
        const selected = new Set(ids);
        const select = picker.find('.kc-home-post-select');
        const options = select.find('option').filter(function () { return this.value !== ''; });

        options.each(function () {
            this.disabled = this.hidden = selected.has(this.value);
        });
        select.prop('disabled', options.length === ids.length);
        picker.find('.kc-home-post-add').prop('disabled', !select.val());
        picker.find('.kc-home-post-ids').val(ids.join(','));
        picker.find('.kc-home-post-empty').prop('hidden', ids.length !== 0);
    }

    form.on('change', '.kc-home-post-select', function () {
        $(this).closest('.kc-home-post-picker').find('.kc-home-post-add').prop('disabled', !$(this).val());
    });

    form.on('click', '.kc-home-post-add', function () {
        const picker = $(this).closest('.kc-home-post-picker');
        const select = picker.find('.kc-home-post-select');
        const option = select.find(':selected')[0];
        if (!option || !option.value || option.disabled) return;

        const item = $('<li>').attr('data-post-id', option.value);
        item.append($('<span>').text(option.textContent));
        item.append($('<button>', {
            type: 'button',
            class: 'button-link-delete kc-home-post-remove',
            'aria-label': 'Remove ' + option.textContent,
            text: 'Remove'
        }));
        picker.find('.kc-home-selected-posts').append(item);
        select.val('');
        refreshPostPicker(picker);
        if (select.prop('disabled')) {
            item.find('button').trigger('focus');
        } else {
            select.trigger('focus');
        }
    });

    form.on('click', '.kc-home-post-remove', function () {
        const picker = $(this).closest('.kc-home-post-picker');
        $(this).closest('li').remove();
        refreshPostPicker(picker);
        picker.find('.kc-home-post-select').trigger('focus');
    });
})(jQuery);
