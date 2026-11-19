(function ($) {
    'use strict';

    const $root = $('.kc-outlet-editor');
    if (!$root.length) {
        return;
    }

    function syncHero() {
        const ids = $('[data-ko-hero-item]').map(function () {
            return String($(this).attr('data-id'));
        }).get();
        $('[name="kampachi_outlet_hero_ids"]').val(ids.join(','));
    }

    function renumber(selector, group) {
        $(selector).each(function (index) {
            $(this).find('[name]').each(function () {
                const name = $(this).attr('name');
                $(this).attr('name', name.replace(new RegExp('kampachi_outlet\\[' + group + '\\]\\[[^\\]]+\\]'), 'kampachi_outlet[' + group + '][' + index + ']'));
            });
        });
    }

    function heroRow(item) {
        const image = item.type === 'image';
        const $row = $('<div class="kc-outlet-media-row" data-ko-hero-item></div>').attr('data-id', item.id);
        $('<span class="dashicons dashicons-move" aria-hidden="true"></span>').appendTo($row);
        const thumb = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : image ? item.url : '';
        if (thumb) {
            $('<img alt="">').attr('src', thumb).appendTo($row);
        } else {
            $('<span class="dashicons dashicons-format-video kc-outlet-video-icon" aria-hidden="true"></span>').appendTo($row);
        }
        $('<strong></strong>').text(item.title || 'Media #' + item.id).appendTo($row);
        $('<small></small>').text(image ? 'Image' : 'Video').appendTo($row);
        $('<button type="button" class="button-link-delete" data-ko-remove>Remove</button>').appendTo($row);
        return $row;
    }

    function newDish() {
        const $row = $('<div class="kc-outlet-repeat-item" data-ko-dish-item></div>');
        $('<span class="dashicons dashicons-move" aria-hidden="true"></span>').appendTo($row);
        const $body = $('<div class="kc-outlet-repeat-body"></div>').appendTo($row);
        const $photo = $('<div class="kc-outlet-picture"></div>').appendTo($body);
        $('<img data-ko-dish-preview alt="" hidden>').appendTo($photo);
        $('<input type="hidden" data-ko-dish-image value="0" name="kampachi_outlet[dishes][new][image_id]">').appendTo($photo);
        $('<button type="button" class="button" data-ko-pick-dish>Choose Photo</button>').appendTo($photo);
        $('<label>Dish title (optional)<input class="widefat" data-ko-dish-title name="kampachi_outlet[dishes][new][title]"></label>').appendTo($body);
        $('<label>Photo description<textarea class="widefat" rows="3" data-ko-dish-description name="kampachi_outlet[dishes][new][description]"></textarea></label>').appendTo($body);
        $('<button type="button" class="button-link-delete" data-ko-remove>Remove</button>').appendTo($row);
        return $row;
    }

    function newMenu() {
        const $row = $('<div class="kc-outlet-repeat-item" data-ko-menu-item></div>');
        $('<span class="dashicons dashicons-move" aria-hidden="true"></span>').appendTo($row);
        const $body = $('<div class="kc-outlet-repeat-body"></div>').appendTo($row);
        $('<label>Menu title<input class="widefat" data-ko-menu-title name="kampachi_outlet[menus][new][title]"></label>').appendTo($body);
        $('<label>Menu PDF / file URL (or external menu URL)<input type="url" class="widefat" data-ko-menu-url name="kampachi_outlet[menus][new][url]"></label>').appendTo($body);
        $('<input type="hidden" data-ko-menu-attachment name="kampachi_outlet[menus][new][attachment_id]" value="0">').appendTo($body);
        $('<button type="button" class="button" data-ko-pick-menu>Choose PDF / File</button>').appendTo($body);
        $('<button type="button" class="button-link-delete" data-ko-remove>Remove</button>').appendTo($row);
        return $row;
    }

    $root.on('click', '[data-ko-add-hero]', function () {
        if (!window.wp || !wp.media) {
            return;
        }
        const frame = wp.media({
            title: 'Add outlet hero images/videos',
            button: { text: 'Add to hero' },
            library: { type: ['image', 'video'] },
            multiple: true
        });
        frame.on('select', function () {
            const existing = new Set($('[data-ko-hero-item]').map(function () {
                return String($(this).attr('data-id'));
            }).get());
            frame.state().get('selection').toJSON().forEach(function (item) {
                if (!['image', 'video'].includes(item.type) || existing.has(String(item.id))) {
                    return;
                }
                $('[data-ko-hero-list]').append(heroRow(item));
                existing.add(String(item.id));
            });
            syncHero();
        });
        frame.open();
    });

    $root.on('click', '[data-ko-add-dish]', function () {
        $('[data-ko-dishes-list]').append(newDish());
        renumber('[data-ko-dish-item]', 'dishes');
    });

    $root.on('click', '[data-ko-pick-dish]', function () {
        if (!window.wp || !wp.media) {
            return;
        }
        const $row = $(this).closest('[data-ko-dish-item]');
        const frame = wp.media({
            title: 'Choose signature dish photo',
            button: { text: 'Use photo' },
            library: { type: 'image' },
            multiple: false
        });
        frame.on('select', function () {
            const item = frame.state().get('selection').first();
            if (!item) {
                return;
            }
            const data = item.toJSON();
            $row.find('[data-ko-dish-image]').val(data.id);
            const url = data.sizes && data.sizes.thumbnail ? data.sizes.thumbnail.url : data.url;
            $row.find('[data-ko-dish-preview]').attr('src', url).prop('hidden', false);
        });
        frame.open();
    });

    $root.on('click', '[data-ko-add-menu]', function () {
        $('[data-ko-menus-list]').append(newMenu());
        renumber('[data-ko-menu-item]', 'menus');
    });

    $root.on('click', '[data-ko-pick-menu]', function () {
        if (!window.wp || !wp.media) {
            return;
        }
        const $row = $(this).closest('[data-ko-menu-item]');
        const frame = wp.media({
            title: 'Choose menu PDF or image',
            button: { text: 'Use menu file' },
            library: { type: ['application/pdf', 'image'] },
            multiple: false
        });
        frame.on('select', function () {
            const item = frame.state().get('selection').first();
            if (!item) {
                return;
            }
            const data = item.toJSON();
            $row.find('[data-ko-menu-attachment]').val(data.id);
            $row.find('[data-ko-menu-url]').val(data.url);
            if (!$row.find('[data-ko-menu-title]').val()) {
                $row.find('[data-ko-menu-title]').val(data.title || 'Restaurant Menu');
            }
        });
        frame.open();
    });

    $root.on('input', '[data-ko-menu-url]', function () {
        $(this).closest('[data-ko-menu-item]').find('[data-ko-menu-attachment]').val('0');
    });

    $root.on('click', '[data-ko-remove]', function () {
        $(this).closest('[data-ko-hero-item], [data-ko-dish-item], [data-ko-menu-item]').remove();
        syncHero();
        renumber('[data-ko-dish-item]', 'dishes');
        renumber('[data-ko-menu-item]', 'menus');
    });

    $('[data-ko-hero-list]').sortable({
        items: '[data-ko-hero-item]',
        handle: '.dashicons-move',
        update: syncHero
    });
    $('[data-ko-dishes-list]').sortable({
        items: '[data-ko-dish-item]',
        handle: '.dashicons-move',
        update: function () { renumber('[data-ko-dish-item]', 'dishes'); }
    });
    $('[data-ko-menus-list]').sortable({
        items: '[data-ko-menu-item]',
        handle: '.dashicons-move',
        update: function () { renumber('[data-ko-menu-item]', 'menus'); }
    });

    // A hidden input must be present even if the hero list has been cleared.
    syncHero();
}(jQuery));
