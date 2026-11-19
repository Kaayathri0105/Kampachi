(function ($) {
    'use strict';

    function syncGallery($gallery) {
        const ids = $gallery.find('[data-kc-preview] .kc-catering-thumb').map(function () {
            return String($(this).attr('data-id'));
        }).get();
        $gallery.find('input[type="hidden"]').first().val(ids.join(','));
        $gallery.find('[data-kc-media-dirty]').val('1');
    }

    function galleryThumb(item) {
        const id = Number(item.id);
        if (!Number.isSafeInteger(id) || id <= 0) {
            return null;
        }

        const thumbnail = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
        const $thumb = $('<div class="kc-catering-thumb"></div>').attr('data-id', id);
        $('<img alt="">').attr('src', thumbnail).appendTo($thumb);
        $('<button type="button" class="button-link-delete" data-kc-remove-media aria-label="Remove image">×</button>').appendTo($thumb);
        return $thumb;
    }

    $(document).on('click', '[data-kc-media-pick]', function () {
        if (!window.wp || !wp.media) {
            return;
        }

        const $gallery = $(this).closest('[data-kc-gallery]');
        const frame = wp.media({
            title: 'Select images for Kampachi',
            button: { text: 'Use selected images' },
            library: { type: 'image' },
            multiple: true
        });

        frame.on('open', function () {
            const ids = String($gallery.find('input[type="hidden"]').first().val() || '').split(',');
            const selection = frame.state().get('selection');
            ids.forEach(function (value) {
                const id = parseInt(value, 10);
                if (!Number.isSafeInteger(id) || id <= 0) {
                    return;
                }
                const attachment = wp.media.attachment(id);
                attachment.fetch();
                selection.add(attachment);
            });
        });

        frame.on('select', function () {
            const $preview = $gallery.find('[data-kc-preview]');
            $preview.empty();
            frame.state().get('selection').toJSON().forEach(function (item) {
                const $thumb = galleryThumb(item);
                if ($thumb) {
                    $preview.append($thumb);
                }
            });
            syncGallery($gallery);
        });
        frame.open();
    });

    $(document).on('click', '[data-kc-clear-media]', function () {
        const $gallery = $(this).closest('[data-kc-gallery]');
        $gallery.find('[data-kc-preview]').empty();
        syncGallery($gallery);
    });

    $(document).on('click', '[data-kc-remove-media]', function () {
        const $gallery = $(this).closest('[data-kc-gallery]');
        $(this).closest('.kc-catering-thumb').remove();
        syncGallery($gallery);
    });

    function syncHeroMedia($editor) {
        const ids = $editor.find('[data-kc-hero-item]').map(function () {
            return String($(this).attr('data-id'));
        }).get();

        $editor.find('[data-kc-hero-ids]').val(ids.join(','));
    }

    function heroMediaRow(item) {
        const id = Number(item.id);

        if (!Number.isSafeInteger(id) || id <= 0) {
            return null;
        }

        const mime = String(item.mime || '');
        const isImage = item.type === 'image' || mime.startsWith('image/');
        const isVideo = item.type === 'video' || mime.startsWith('video/');

        if (!isImage && !isVideo) {
            return null;
        }

        const $row = $('<div class="kc-catering-hero-item" data-kc-hero-item></div>').attr('data-id', String(id));
        $('<span class="dashicons dashicons-move kc-catering-hero-handle" aria-hidden="true"></span>').appendTo($row);
        const thumbnail = item.sizes && item.sizes.medium
            ? item.sizes.medium.url
            : item.sizes && item.sizes.thumbnail
                ? item.sizes.thumbnail.url
                : isImage ? item.url : '';

        if (thumbnail) {
            $('<img class="kc-catering-hero-preview" alt="">').attr('src', thumbnail).appendTo($row);
        } else {
            const $preview = $('<span class="kc-catering-hero-preview kc-catering-video-placeholder"></span>');
            $('<span class="dashicons dashicons-format-video" aria-hidden="true"></span>').appendTo($preview);
            $preview.appendTo($row);
        }

        const $info = $('<div class="kc-catering-hero-info"></div>');
        $('<strong></strong>').text(item.title || 'Media #' + id).appendTo($info);
        $('<small></small>').text(isImage ? 'Image banner' : 'Video: ' + mime).appendTo($info);
        $info.appendTo($row);
        $('<button type="button" class="button-link-delete" data-kc-hero-remove></button>')
            .attr('aria-label', isImage ? 'Remove image' : 'Remove video')
            .text('Remove')
            .appendTo($row);

        return $row;
    }

    $(document).on('click', '[data-kc-hero-add]', function () {
        if (!window.wp || !wp.media) {
            return;
        }

        const $editor = $(this).closest('[data-kc-hero-editor]');
        const frame = wp.media({
            title: 'Add hero images and videos',
            button: { text: 'Add Selected Media' },
            library: { type: ['image', 'video'] },
            multiple: true
        });

        frame.on('select', function () {
            const $list = $editor.find('[data-kc-hero-list]');
            const existingIds = new Set($list.find('[data-kc-hero-item]').map(function () {
                return String($(this).attr('data-id'));
            }).get());

            frame.state().get('selection').toJSON().forEach(function (item) {
                const id = String(item.id);

                if (existingIds.has(id)) {
                    return;
                }

                const $row = heroMediaRow(item);

                if ($row) {
                    $list.append($row);
                    existingIds.add(id);
                }
            });

            syncHeroMedia($editor);
        });

        frame.open();
    });

    $(document).on('click', '[data-kc-hero-remove]', function () {
        const $editor = $(this).closest('[data-kc-hero-editor]');
        $(this).closest('[data-kc-hero-item]').remove();
        syncHeroMedia($editor);
    });

    $(document).on('click', '[data-kc-hero-clear]', function () {
        const $editor = $(this).closest('[data-kc-hero-editor]');
        $editor.find('[data-kc-hero-list]').empty();
        syncHeroMedia($editor);
    });

    function normalizeValueNames() {
        $('[data-kc-values-list] [data-kc-value]').each(function (index) {
            $(this).find('[name]').each(function () {
                $(this).attr('name', $(this).attr('name').replace(/kampachi_catering_values\[[^\]]+\]/, 'kampachi_catering_values[' + index + ']'));
            });
        });
    }

    $(document).on('click', '[data-kc-add-value]', function () {
        const $template = $('#kc-catering-value-template');
        if (!$template.length) {
            return;
        }
        const $item = $($template.html().replaceAll('__INDEX__', String(Date.now())));
        $('[data-kc-values-list]').append($item);
        normalizeValueNames();
    });

    $(document).on('click', '[data-kc-remove-value]', function () {
        $(this).closest('[data-kc-value]').remove();
        normalizeValueNames();
    });

    $(function () {
        $('[data-kc-hero-list]').sortable({
            items: '[data-kc-hero-item]',
            handle: '.kc-catering-hero-handle',
            tolerance: 'pointer',
            update: function () {
                syncHeroMedia($(this).closest('[data-kc-hero-editor]'));
            }
        });

        $('[data-kc-preview]').sortable({
            items: '.kc-catering-thumb',
            tolerance: 'pointer',
            update: function () {
                syncGallery($(this).closest('[data-kc-gallery]'));
            }
        });

        $('[data-kc-values-list]').sortable({
            items: '[data-kc-value]',
            handle: '.kc-catering-value-heading',
            update: normalizeValueNames
        });

        $('[data-kc-event-sort]').sortable({
            items: '> li',
            handle: '.dashicons-move',
            tolerance: 'pointer'
        });

        const $pageForm = $('[data-kc-values-list]').closest('form');
        $pageForm.on('submit', normalizeValueNames);
    });
}(jQuery));
