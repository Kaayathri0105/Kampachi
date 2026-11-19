(function ($) {
    'use strict';

    const $root = $('[data-offer-editor]');
    if (!$root.length) return;

    const descriptionEditor = window.kampachiDescriptionEditor;
    let unique = Date.now();
    const rowSelector = '[data-offer-hero], [data-offer-menu], [data-offer-detail]';

    function prepareEditors($scope) {
        $scope.find('textarea.kc-offers-rich').each(function () {
            descriptionEditor.initialize(this);
        });
    }

    function removeEditors($scope) {
        $scope.find('textarea.kc-offers-rich').each(function () {
            descriptionEditor.remove(this);
        });
    }

    function renumber() {
        ['menus', 'details'].forEach(function (group) {
            const selector = group === 'menus' ? '[data-offer-menu]' : '[data-offer-detail]';
            $root.find(selector).each(function (index) {
                $(this).find('[name]').each(function () {
                    this.name = this.name.replace(new RegExp('kampachi_offer\\[' + group + '\\]\\[[^\\]]+\\]'), 'kampachi_offer[' + group + '][' + index + ']');
                });
            });
        });
    }

    function syncHero() {
        const ids = $root.find('[data-offer-hero]').map(function () { return $(this).attr('data-id'); }).get();
        $root.find('[name="kampachi_offer_hero_ids"]').val(ids.join(',')).trigger('change');
    }

    function addRow(kind) {
        const template = $root.find('[data-offer-' + kind + '-template]').get(0);
        const $row = $(template.innerHTML.replace(/__INDEX__/g, String(++unique)));
        $root.find(kind === 'menu' ? '[data-offer-menus]' : '[data-offer-details]').append($row);
        renumber();
        prepareEditors($row);
        return $row;
    }

    function rowActions($row) {
        $('<div class="kc-offers-row-actions"><button type="button" class="button" data-offer-up aria-label="Move up">↑</button><button type="button" class="button" data-offer-down aria-label="Move down">↓</button><button type="button" class="button-link-delete" data-offer-remove>Remove</button></div>').appendTo($row);
    }

    function heroRow(item) {
        const image = item.type === 'image';
        const $row = $('<div class="kc-offers-media" data-offer-hero></div>').attr('data-id', item.id);
        $('<span class="dashicons dashicons-move" aria-hidden="true"></span>').appendTo($row);
        if (image) {
            const url = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
            $('<img alt="">').attr('src', url).appendTo($row);
        } else {
            $('<span class="dashicons dashicons-format-video" aria-hidden="true"></span>').appendTo($row);
        }
        $('<strong></strong>').text(item.title || item.filename || 'Media #' + item.id).appendTo($row);
        $('<small></small>').text(image ? 'Image' : 'Video').appendTo($row);
        rowActions($row);
        return $row;
    }

    function mediaPicker(title, types, multiple, onSelect) {
        if (!window.wp || !wp.media) return;
        const frame = wp.media({title: title, button: {text: 'Use Selected Media'}, library: {type: types}, multiple: multiple});
        frame.on('select', function () { onSelect(frame.state().get('selection').toJSON()); });
        frame.open();
    }

    function fillMenu($row, item) {
        if (item.mime !== 'application/pdf' && item.type !== 'image') return;
        $row.find('[data-offer-menu-id]').val(item.id);
        $row.find('[data-offer-menu-url]').val(item.url);
        if (!$row.find('[data-offer-menu-title]').val()) $row.find('[data-offer-menu-title]').val(item.title || item.filename || 'View Menu');
    }

    $root.on('click', '[data-offer-pick-thumbnail]', function () {
        mediaPicker('Choose Card Thumbnail Image', ['image'], false, function (items) {
            const item = items[0];
            if (!item || item.type !== 'image') return;
            const url = item.sizes && item.sizes.medium ? item.sizes.medium.url : item.url;
            $root.find('[data-offer-thumbnail-id]').val(item.id);
            $root.find('[data-offer-thumbnail-changed]').val('1');
            $root.find('[data-offer-thumbnail-preview]').attr('src', url).prop('hidden', false);
        });
    });
    $root.on('click', '[data-offer-clear-thumbnail]', function () {
        $root.find('[data-offer-thumbnail-id]').val('0');
        $root.find('[data-offer-thumbnail-changed]').val('1');
        $root.find('[data-offer-thumbnail-preview]').removeAttr('src').prop('hidden', true);
    });

    $root.on('click', '[data-offer-add-hero]', function () {
        mediaPicker('Add Banner Images / Videos', ['image', 'video'], true, function (items) {
            const existing = new Set($root.find('[data-offer-hero]').map(function () { return $(this).attr('data-id'); }).get());
            items.forEach(function (item) {
                if (!['image', 'video'].includes(item.type) || existing.has(String(item.id))) return;
                $root.find('[data-offer-hero-list]').append(heroRow(item));
                existing.add(String(item.id));
            });
            syncHero();
        });
    });
    $root.on('click', '[data-offer-add-menu]', function () { addRow('menu'); });
    $root.on('click', '[data-offer-add-detail]', function () { addRow('detail'); });
    $root.on('click', '[data-offer-add-menu-files]', function () {
        mediaPicker('Add Menu PDFs / Images', ['application/pdf', 'image'], true, function (items) {
            items.forEach(function (item) {
                if (item.mime === 'application/pdf' || item.type === 'image') fillMenu(addRow('menu'), item);
            });
        });
    });
    $root.on('click', '[data-offer-pick-menu]', function () {
        const $row = $(this).closest('[data-offer-menu]');
        mediaPicker('Choose Menu PDF / Image', ['application/pdf', 'image'], false, function (items) {
            if (items[0]) fillMenu($row, items[0]);
        });
    });
    $root.on('input', '[data-offer-menu-url]', function () { $(this).closest('[data-offer-menu]').find('[data-offer-menu-id]').val('0'); });
    $root.on('click', '[data-offer-remove]', function () {
        const $row = $(this).closest(rowSelector);
        removeEditors($row);
        $row.remove();
        renumber();
        syncHero();
    });
    $root.on('click', '[data-offer-up], [data-offer-down]', function () {
        const $row = $(this).closest(rowSelector);
        const up = $(this).is('[data-offer-up]');
        const $sibling = up ? $row.prev() : $row.next();
        if (!$sibling.length) return;
        removeEditors($row);
        if (up) $row.insertBefore($sibling); else $row.insertAfter($sibling);
        prepareEditors($row);
        renumber();
        syncHero();
        $row.find(up ? '[data-offer-up]' : '[data-offer-down]').trigger('focus');
    });

    $root.find('[data-offer-hero-list], [data-offer-menus], [data-offer-details]').sortable({
        items: '> div',
        handle: '.dashicons-move',
        start: function (event, ui) { ui.placeholder.height(ui.item.outerHeight()); removeEditors(ui.item); },
        stop: function (event, ui) { prepareEditors(ui.item); renumber(); syncHero(); }
    });

    const start = document.getElementById('kc-offer-date_start');
    const end = document.getElementById('kc-offer-date_end');
    const startTime = document.getElementById('kc-offer-time_start');
    const endTime = document.getElementById('kc-offer-time_end');
    function validateDates() {
        if (!start || !end) return;
        end.min = start.value;
        end.setCustomValidity(start.value && end.value && end.value < start.value
            ? 'Expiry date must be on or after the start date.' : '');
        if (startTime && endTime) {
            const sameDay = start.value !== '' && start.value === end.value;
            endTime.min = sameDay ? startTime.value : '';
            endTime.setCustomValidity(sameDay && startTime.value && endTime.value && endTime.value < startTime.value
                ? 'Expiry time must be on or after the start time for a same-day offer.' : '');
        }
    }
    if (start && end) {
        start.addEventListener('change', validateDates);
        end.addEventListener('change', validateDates);
        if (startTime) startTime.addEventListener('change', validateDates);
        if (endTime) endTime.addEventListener('change', validateDates);
        validateDates();
    }
    $root.closest('form').on('submit', function () {
        $root.find('textarea.kc-offers-rich').each(function () {
            descriptionEditor.save(this);
        });
        renumber();
        syncHero();
    });
    prepareEditors($root);
})(jQuery);
