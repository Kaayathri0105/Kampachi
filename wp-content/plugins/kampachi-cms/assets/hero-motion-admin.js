(() => {
    'use strict';

    const choices = {
        'zoom-in': 'Zoom in',
        'zoom-out': 'Zoom out',
        left: 'Pan left',
        right: 'Pan right',
        up: 'Pan up',
        down: 'Pan down',
        none: 'No animation'
    };

    function allMedia(editor) {
        const name = editor.dataset.sourceName;
        const input = Array.from(document.querySelectorAll('input[name]'))
            .find((candidate) => candidate.name === name || (name === 'kampachi_landing[hero_image_ids]' && candidate.name === name));
        if (!input) {
            return [];
        }

        const ids = input.value.split(',').map((value) => Number.parseInt(value, 10))
            .filter((id) => Number.isSafeInteger(id) && id > 0);
        const uniqueIds = Array.from(new Set(ids));
        const explicitImages = [
            'kampachi_landing[hero_image_ids]',
            'kampachi_sake_images',
            'kampachi_catering_event_images'
        ].includes(name);

        return uniqueIds.filter((id) => {
            if (explicitImages) {
                return true;
            }
            const matchingRow = Array.from(document.querySelectorAll('[data-kc-hero-item], [data-ko-hero-item]'))
                .find((row) => Number(row.dataset.id) === id);
            return Boolean(matchingRow && (
                matchingRow.querySelector('img')
                || /image/i.test(matchingRow.querySelector('small')?.textContent || '')
            ));
        });
    }

    function initialise(editor) {
        let saved = {};
        try {
            saved = JSON.parse(editor.dataset.saved || '{}');
        } catch {
            saved = {};
        }

        const container = editor.querySelector('[data-kc-motion-rows]');
        let previous = '';

        function redraw() {
            const ids = allMedia(editor);
            const signature = ids.join(',');
            if (signature === previous) {
                return;
            }
            previous = signature;

            container.querySelectorAll('select[data-motion-id]').forEach((select) => {
                saved[select.dataset.motionId] = select.value;
            });
            container.replaceChildren();

            if (ids.length === 0) {
                const message = document.createElement('p');
                message.className = 'description';
                message.textContent = 'Add hero images above to choose their Ken Burns directions.';
                container.appendChild(message);
                return;
            }

            ids.forEach((id, index) => {
                const label = document.createElement('label');
                label.style.cssText = 'display:flex;align-items:center;gap:12px;margin:10px 0;flex-wrap:wrap;';
                const text = document.createElement('strong');
                text.textContent = `Hero image ${index + 1} (#${id})`;
                const select = document.createElement('select');
                select.name = `kampachi_hero_directions[${id}]`;
                select.dataset.motionId = String(id);
                Object.entries(choices).forEach(([value, caption]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = caption;
                    select.appendChild(option);
                });
                select.value = choices[saved[id]] ? saved[id] : 'zoom-in';
                select.addEventListener('change', () => { saved[id] = select.value; });
                label.append(text, select);
                container.appendChild(label);
            });
        }

        redraw();
        const observer = new MutationObserver(redraw);
        const area = editor.closest('.kc-outlet-editor, .kc-catering-hero-editor, .kc-landing-media, form');
        if (area) {
            observer.observe(area, { childList: true, subtree: true });
        }
        document.addEventListener('change', redraw);
        // Media picker plugins update hidden input.value without firing input/change.
        // Low-frequency sync ensures newly added/reordered items can be configured.
        window.setInterval(redraw, 1200);
    }

    document.querySelectorAll('[data-kc-motion-editor]').forEach(initialise);
})();
