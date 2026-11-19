(() => {
    'use strict';

    const list = document.getElementById('kc-footer-link-list');
    const template = document.getElementById('kc-footer-link-template');
    const empty = document.getElementById('kc-footer-links-empty');
    if (!list || !template) return;

    const updateRow = (row) => {
        const isPage = row.querySelector('[data-footer-field="type"]').value === 'page';
        const select = row.querySelector('[data-footer-field="post_id"]');
        const title = row.querySelector('[data-footer-field="label"]');
        const url = row.querySelector('[data-footer-field="url"]');
        row.querySelector('.kc-footer-page-field').hidden = !isPage;
        row.querySelector('.kc-footer-custom-field').hidden = isPage;
        select.required = isPage;
        title.required = !isPage;
        url.required = !isPage;
        title.placeholder = isPage ? (select.selectedOptions[0]?.dataset.title || 'Uses the page title') : 'Link title';
        select.setCustomValidity(isPage && select.value === '0' ? 'Choose a published page or post.' : '');
    };

    const renumber = () => {
        const rows = [...list.children];
        rows.forEach((row, index) => {
            row.querySelectorAll('[data-footer-field]').forEach((field) => {
                field.name = `footer_nav[${index}][${field.dataset.footerField}]`;
            });
            row.querySelector('[data-footer-move="up"]').disabled = index === 0;
            row.querySelector('[data-footer-move="down"]').disabled = index === rows.length - 1;
            updateRow(row);
        });
        if (empty) empty.hidden = rows.length > 0;
    };

    const addRow = (type) => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelector('[data-footer-field="type"]').value = type;
        list.append(row);
        renumber();
        row.querySelector(`[data-footer-field="${type === 'page' ? 'post_id' : 'label'}"]`).focus();
    };

    document.getElementById('kc-footer-add-page')?.addEventListener('click', () => addRow('page'));
    document.getElementById('kc-footer-add-custom')?.addEventListener('click', () => addRow('custom'));
    list.addEventListener('change', (event) => {
        const row = event.target.closest('.kc-footer-link-row');
        if (row) updateRow(row);
    });
    list.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        const row = button?.closest('.kc-footer-link-row');
        if (!row) return;
        if (button.hasAttribute('data-footer-remove')) {
            row.remove();
        } else if (button.dataset.footerMove === 'up' && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (button.dataset.footerMove === 'down' && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        renumber();
    });
    list.closest('form')?.addEventListener('submit', renumber);
    renumber();
})();
