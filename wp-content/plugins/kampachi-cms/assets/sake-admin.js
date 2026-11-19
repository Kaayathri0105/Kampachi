(function ($) {
    'use strict';

    function normalize($editor) {
        const $rows = $editor.find('[data-kc-bottles-list] > [data-kc-bottle]');

        $rows.each(function (index) {
            const $row = $(this);
            const prefix = 'kc-sake-bottle-' + index + '-';

            $row.attr('aria-labelledby', prefix + 'heading');
            $row.find('[data-kc-bottle-number]').text(index + 1);
            $row.find('[name]').each(function () {
                this.name = this.name.replace(/kampachi_sake_bottles\[[^\]]+\]/, 'kampachi_sake_bottles[' + index + ']');
            });
            $row.find('[id]').each(function () {
                this.id = this.id.replace(/^kc-sake-bottle-[^-]+-/, prefix);
            });
            $row.find('label[for]').each(function () {
                $(this).attr('for', $(this).attr('for').replace(/^kc-sake-bottle-[^-]+-/, prefix));
            });
            $row.find('[data-kc-bottle-up]').prop('disabled', index === 0);
            $row.find('[data-kc-bottle-down]').prop('disabled', index === $rows.length - 1);
        });

        $editor.find('[data-kc-bottles-empty]').prop('hidden', $rows.length > 0);
    }

    function announce($editor, message) {
        $editor.find('[data-kc-bottles-status]').text(message);
    }

    function bottleId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        return 'bottle-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    $(document).on('click', '[data-kc-add-bottle]', function () {
        const $editor = $(this).closest('[data-kc-bottles-editor]');
        const template = $editor.find('#kc-sake-bottle-template')[0];

        if (!template) {
            return;
        }

        const $row = $(template.innerHTML.replaceAll('__INDEX__', 'new').replaceAll('__BOTTLE_ID__', bottleId()));
        $editor.find('[data-kc-bottles-list]').append($row);
        normalize($editor);
        $row.find('input[name$="[name]"]').trigger('focus');
        announce($editor, 'Bottle added. Update or publish the brewery to save.');
    });

    $(document).on('click', '[data-kc-remove-bottle]', function () {
        const $editor = $(this).closest('[data-kc-bottles-editor]');
        const $row = $(this).closest('[data-kc-bottle]');
        const $next = $row.next('[data-kc-bottle]');
        const $previous = $row.prev('[data-kc-bottle]');
        $row.remove();
        normalize($editor);

        if ($next.length || $previous.length) {
            ($next.length ? $next : $previous).find('input[name$="[name]"]').trigger('focus');
        } else {
            $editor.find('[data-kc-add-bottle]').trigger('focus');
        }

        announce($editor, 'Bottle removed. Update or publish the brewery to save.');
    });

    $(document).on('click', '[data-kc-bottle-up], [data-kc-bottle-down]', function () {
        const $button = $(this);
        const $row = $button.closest('[data-kc-bottle]');
        const $editor = $row.closest('[data-kc-bottles-editor]');

        if ($button.is('[data-kc-bottle-up]')) {
            $row.insertBefore($row.prev('[data-kc-bottle]'));
        } else {
            $row.insertAfter($row.next('[data-kc-bottle]'));
        }

        normalize($editor);
        $row.find('input[name$="[name]"]').trigger('focus');
        announce($editor, 'Bottle order changed. Update or publish the brewery to save.');
    });

    $(document).on('click', '[data-kc-bottle-image-pick]', function () {
        if (!window.wp || !window.wp.media) {
            return;
        }

        const $row = $(this).closest('[data-kc-bottle]');
        const frame = window.wp.media({
            title: 'Choose bottle image',
            button: { text: 'Use Bottle Image' },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('open', function () {
            const id = Number($row.find('[data-kc-bottle-image-id]').val());

            if (Number.isSafeInteger(id) && id > 0) {
                const attachment = window.wp.media.attachment(id);
                attachment.fetch();
                frame.state().get('selection').add(attachment);
            }
        });

        frame.on('select', function () {
            const selected = frame.state().get('selection').first();

            if (!selected) {
                return;
            }

            const item = selected.toJSON();
            const id = Number(item.id);

            if (!Number.isSafeInteger(id) || id <= 0 || item.type !== 'image') {
                return;
            }

            const url = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
            $row.find('[data-kc-bottle-image-id]').val(id);
            $row.find('[data-kc-bottle-image-preview]').attr('src', url).prop('hidden', false);
        });

        frame.open();
    });

    $(document).on('click', '[data-kc-bottle-image-clear]', function () {
        const $row = $(this).closest('[data-kc-bottle]');
        $row.find('[data-kc-bottle-image-id]').val('0');
        $row.find('[data-kc-bottle-image-preview]').removeAttr('src').prop('hidden', true);
    });

    $(function () {
        $('[data-kc-bottles-editor]').each(function () {
            const $editor = $(this);

            $editor.find('[data-kc-bottles-list]').sortable({
                items: '> [data-kc-bottle]',
                handle: '.kc-sake-bottle-handle',
                tolerance: 'pointer',
                update: function () {
                    normalize($editor);
                    announce($editor, 'Bottle order changed. Update or publish the brewery to save.');
                }
            });

            normalize($editor);
            $editor.closest('form').on('submit', function () {
                normalize($editor);
            });
        });
    });
}(jQuery));
