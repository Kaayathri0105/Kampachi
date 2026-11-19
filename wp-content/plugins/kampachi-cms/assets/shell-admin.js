(function ($) {
    'use strict';
    if (!window.wp || !wp.media) {
        return;
    }
    $('.kc-shell-pick-image').on('click', function () {
        const input = $('#' + $(this).data('target'));
        const preview = input.siblings('.kc-shell-preview');
        const picker = wp.media({ title: 'Select Logo', multiple: false, library: { type: 'image' } });
        picker.on('select', function () {
            const selected = picker.state().get('selection').first().toJSON();
            input.val(selected.id);
            preview.attr('src', selected.sizes && selected.sizes.thumbnail ? selected.sizes.thumbnail.url : selected.url).prop('hidden', false);
        });
        picker.open();
    });
    $('.kc-shell-clear-image').on('click', function () {
        const input = $('#' + $(this).data('target'));
        input.val('');
        input.siblings('.kc-shell-preview').attr('src', '').prop('hidden', true);
    });
})(jQuery);
