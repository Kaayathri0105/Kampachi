(function ($) {
    'use strict';

    const $nav = $('#kc-header-nav-list');
    const $booking = $('#kc-header-booking-list');

    if (!$nav.length || !$booking.length) {
        return;
    }

    function addTemplate(templateId, $target) {
        const template = document.getElementById(templateId);
        if (!template) {
            return;
        }
        const fragment = template.content.cloneNode(true);
        $target.append(fragment);
        renumber();
    }

    function enableSort() {
        $nav.sortable({
            items: '> .kc-header-nav-item',
            handle: '> .kc-header-row .kc-header-handle',
            placeholder: 'kc-header-placeholder',
            update: renumber
        });
        $booking.sortable({
            items: '> .kc-header-booking-item',
            handle: '> .kc-header-handle',
            placeholder: 'kc-header-placeholder',
            update: renumber
        });
        $nav.find('.kc-header-child-list').each(function () {
            const $list = $(this);
            if ($list.data('ui-sortable')) {
                return;
            }
            $list.sortable({
                items: '> .kc-header-child-item',
                handle: '> .kc-header-handle',
                placeholder: 'kc-header-placeholder',
                update: renumber
            });
        });
    }

    function renumber() {
        $nav.children('.kc-header-nav-item').each(function (index) {
            const $item = $(this);
            $item.children('.kc-header-row').find('[data-field]').each(function () {
                this.name = `header_nav[${index}][${this.dataset.field}]`;
            });
            $item.find('.kc-header-child-list > .kc-header-child-item').each(function (childIndex) {
                $(this).find('[data-field]').each(function () {
                    this.name = `header_nav[${index}][children][${childIndex}][${this.dataset.field}]`;
                });
            });
        });
        $booking.children('.kc-header-booking-item').each(function (index) {
            $(this).find('[data-field]').each(function () {
                this.name = `header_booking[${index}][${this.dataset.field}]`;
            });
        });
    }

    $('#kc-header-add-menu').on('click', function () {
        addTemplate('kc-header-menu-template', $nav);
        enableSort();
        $nav.children().last().find('[data-field="title"]').trigger('focus');
    });
    $('#kc-header-add-booking').on('click', function () {
        addTemplate('kc-header-booking-template', $booking);
        $booking.children().last().find('select').trigger('focus');
    });
    $nav.on('click', '.kc-header-add-child', function () {
        addTemplate('kc-header-child-template', $(this).siblings('.kc-header-child-list'));
        enableSort();
    });
    $('.wrap').on('click', '.kc-header-remove', function () {
        $(this).closest('.kc-header-sortable').remove();
        renumber();
    });
    $booking.on('change', '.kc-header-outlet-select', function () {
        const $row = $(this).closest('.kc-header-booking-item');
        const $label = $row.find('[data-field="title"]');
        const $url = $row.find('[data-field="url"]');
        const option = this.selectedOptions[0];

        if (option && !$label.val()) {
            $label.attr('placeholder', option.textContent.trim());
        }
        if (option) {
            $url.attr('placeholder', option.dataset.defaultUrl || 'Enter outlet booking URL');
        }
    });

    $nav.closest('form').on('submit', renumber);
    enableSort();
    renumber();
})(jQuery);
