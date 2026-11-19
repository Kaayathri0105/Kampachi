(function ($) {
    'use strict';

    const form = $('#kc-offers-landing-form');
    if (!form.length) {
        return;
    }

    const posts = form.find('[data-kc-offers-order]');
    const categoryOrder = form.find('[data-kc-offers-category-order]');
    const tabs = form.find('[data-kc-offers-tabs]');
    const search = form.find('[data-kc-offers-search]');
    const empty = form.find('[data-kc-offers-empty]');
    let orderedPosts = posts.children().get();
    let activeCategory = 'all';

    function writeField(key, values) {
        form.find('[data-kc-offers-field="' + key + '"]').val(values.join(','));
    }

    function updatePosts() {
        writeField('order', orderedPosts.map((post) => post.dataset.offer));
        const hiddenPosts = orderedPosts.filter((post) => !post.querySelector('[data-kc-offers-visible]').checked);
        writeField('hidden', hiddenPosts.map((post) => post.dataset.offer));
    }

    function updateCategories() {
        const keys = categoryOrder.children().get().map((chip) => chip.dataset.category);
        writeField('categories', keys);
        const buttons = new Map(tabs.children().get().map((button) => [button.dataset.category, button]));
        keys.forEach((key) => tabs.append(buttons.get(key)));
    }

    function filterPosts() {
        const term = search.val().trim().toLocaleLowerCase();
        let matches = 0;

        orderedPosts.forEach((post) => {
            const inCategory = activeCategory === 'all' || post.dataset.categories.split(' ').includes(activeCategory);
            const title = post.querySelector('strong').textContent.toLocaleLowerCase();
            post.hidden = !inCategory || !title.includes(term);
            matches += Number(!post.hidden);
        });
        empty.prop('hidden', matches > 0);
        posts.sortable('refresh');
    }

    function sortPosts() {
        const visible = posts.children('li:not([hidden])').get();
        const visiblePosts = new Set(visible);
        let next = 0;
        orderedPosts = orderedPosts.map((post) => visiblePosts.has(post) ? visible[next++] : post);
        posts.append(orderedPosts);
        updatePosts();
    }

    posts.sortable({
        axis: 'y',
        items: '> li:not([hidden])',
        handle: '.kc-offers-landing-handle',
        update: sortPosts
    });
    categoryOrder.sortable({
        items: '> li',
        handle: '.kc-offers-landing-handle',
        update: updateCategories
    });

    tabs.on('click', 'button', function () {
        activeCategory = this.dataset.category;
        tabs.children().removeClass('nav-tab-active').attr('aria-pressed', 'false');
        $(this).addClass('nav-tab-active').attr('aria-pressed', 'true');
        search.val('').attr('placeholder', 'Search ' + this.textContent);
        filterPosts();
    });
    search.on('input', filterPosts);
    posts.on('change', '[data-kc-offers-visible]', updatePosts);

    form.on('click', '[data-kc-landing-move]', function () {
        const row = $(this).closest('li');
        const direction = this.dataset.kcLandingMove;
        const siblings = row.parent().children('li:not([hidden])');
        const index = siblings.index(row);
        const destination = index + (direction === 'up' ? -1 : 1);
        if (destination < 0 || destination >= siblings.length) {
            return;
        }

        const sibling = siblings.eq(destination);
        if (direction === 'up') {
            row.insertBefore(sibling);
        } else {
            row.insertAfter(sibling);
        }
        if (row.is('[data-offer]')) {
            sortPosts();
        } else {
            updateCategories();
        }
        this.focus();
    });

    form.on('change', '#kc-landing-video, #kc-landing-images', function () {
        form.find('#kc-offers-hero-touched').val('1');
    });
    form.on('submit', function () {
        const editor = window.tinymce?.get('kc_offers_intro_body');
        if (editor && !editor.isHidden()) {
            editor.save();
        }
    });

    const imageId = form.find('[data-kc-offers-og-id]');
    const imagePreview = form.find('[data-kc-offers-og-preview]');
    const clearImage = form.find('[data-kc-offers-og-clear]');
    let mediaFrame;

    form.find('[data-kc-offers-og-choose]').on('click', function () {
        if (!mediaFrame) {
            mediaFrame = wp.media({
                title: 'Choose a social sharing image',
                button: { text: 'Use this image' },
                library: { type: 'image' },
                multiple: false
            });
            mediaFrame.on('select', function () {
                const image = mediaFrame.state().get('selection').first().toJSON();
                imageId.val(image.id);
                imagePreview.attr('src', image.sizes?.medium_large?.url || image.url).prop('hidden', false);
                clearImage.prop('hidden', false);
            });
        }
        mediaFrame.open();
    });

    clearImage.on('click', function () {
        imageId.val('');
        imagePreview.removeAttr('src').prop('hidden', true);
        clearImage.prop('hidden', true);
        form.find('[data-kc-offers-og-choose]').trigger('focus');
    });
})(jQuery);
