(() => {
    'use strict';

    const panel = document.querySelector('[data-kc-translations]');
    if (!panel) {
        return;
    }

    const buttons = [...panel.querySelectorAll('button[data-kc-language]')];

    async function translate(button, automatic = false) {
        const {kcLanguage, label, target} = button.dataset;
        const replacing = Number(target) > 0;

        if (replacing && !window.confirm(
            'Retranslate ' + label + ' from saved English? This replaces the saved translation, including manual edits. '
                + 'If it is published, the changes will go live.'
        )) {
            return;
        }

        const status = button.parentElement.querySelector('[data-kc-translation-status]');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        status.textContent = 'Translating ' + label + '…';

        try {
            const response = await fetch(panel.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'kc_translate_post_language',
                    nonce: panel.dataset.nonce,
                    source: panel.dataset.source,
                    language: kcLanguage,
                    target,
                    replace: replacing ? '1' : '0',
                }),
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.data?.url) {
                throw new Error(result.data?.message || 'Translation did not finish. Try again.');
            }

            if (replacing) {
                status.textContent = 'Translation updated. Reopen this post to load the updated text.';
            } else {
                const link = document.createElement('a');
                link.href = button.dataset.tabUrl || result.data.url;
                link.className = button.className;
                link.textContent = label;
                const shouldRestoreFocus = document.activeElement === button;
                button.replaceWith(link);
                status.textContent = 'Draft ready.';
                if (shouldRestoreFocus) {
                    link.focus();
                }
            }
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'Translation did not finish. Try again.';
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (automatic) {
                delete button.dataset.pending;
            }
        }
    }

    for (const button of buttons) {
        button.addEventListener('click', () => translate(button));
    }

    async function translateQueued() {
        const pending = buttons.filter(button => button.dataset.pending === '1');
        for (const button of pending) {
            button.disabled = true;
            button.parentElement.querySelector('[data-kc-translation-status]').textContent = 'Queued for translation.';
        }
        for (const button of pending) {
            await translate(button, true);
        }
    }

    translateQueued();
})();
