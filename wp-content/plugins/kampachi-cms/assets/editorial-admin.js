(function ($) {
    'use strict';

    const gallery = $('[data-kc-about-gallery]');
    const galleryInput = $('#kc-about-ids');

    function syncGallery() {
        galleryInput.val(gallery.find('[data-id]').map(function () {
            return $(this).attr('data-id');
        }).get().join(','));
    }

    if (gallery.length && window.wp && wp.media) {
        gallery.sortable({ items: '.kc-editorial-photo', update: syncGallery });
        $('[data-kc-add-photos]').on('click', function () {
            const picker = wp.media({ title: 'Select About Kampachi Photos', multiple: true, library: { type: 'image' } });
            picker.on('select', function () {
                picker.state().get('selection').each(function (attachment) {
                    const data = attachment.toJSON();
                    if (gallery.find('[data-id="' + data.id + '"]').length) {
                        return;
                    }
                    const src = (data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url) || '';
                    const row = $('<div class="kc-editorial-photo">').attr('data-id', data.id);
                    $('<img alt="">').attr('src', src).appendTo(row);
                    $('<button type="button" class="button-link-delete" data-kc-remove-photo>Remove</button>').appendTo(row);
                    gallery.append(row);
                });
                syncGallery();
            });
            picker.open();
        });
        gallery.on('click', '[data-kc-remove-photo]', function () {
            $(this).closest('[data-id]').remove();
            syncGallery();
        });
        $('[data-kc-clear-photos]').on('click', function () {
            gallery.empty();
            syncGallery();
        });
    }

    const editor = $('[data-kc-privacy-editor]');
    const list = editor.find('[data-kc-privacy-list]');
    if (! editor.length) {
        return;
    }

    function renumber() {
        list.find('[data-kc-privacy-row]').each(function (index) {
            $(this).find('[data-kc-section-title]').attr('name', 'kc_privacy_sections[' + index + '][title]');
            $(this).find('[data-kc-section-description]').attr('name', 'kc_privacy_sections[' + index + '][description]');
        });
    }

    list.sortable({ items: '[data-kc-privacy-row]', handle: '.dashicons-move', update: renumber });
    editor.on('click', '[data-kc-add-section]', function () {
        const template = editor.find('[data-kc-privacy-template]')[0];
        list.append($(template.content.cloneNode(true)));
        renumber();
    });
    editor.on('click', '[data-kc-remove-section]', function () {
        $(this).closest('[data-kc-privacy-row]').remove();
        renumber();
    });
    renumber();
})(jQuery);
