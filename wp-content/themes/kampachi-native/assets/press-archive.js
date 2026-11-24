(() => {
    const form = document.querySelector('.kc-press-filters');
    const results = document.querySelector('#press-archive-results');
    const status = document.querySelector('[data-press-status]');
    if (!form || !results || !status) {
        return;
    }

    let timer;
    let pending;

    async function updateResults() {
        clearTimeout(timer);
        pending?.abort();
        const request = new AbortController();
        pending = request;
        const url = new URL(form.action);
        new FormData(form).forEach((value, key) => url.searchParams.set(key, value));
        url.hash = window.location.hash;
        results.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {signal: request.signal});
            if (!response.ok) {
                throw new Error('Could not load press articles');
            }

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.querySelector('#press-archive-results');
            if (!updated) {
                throw new Error('Missing press archive results');
            }
            if (request.signal.aborted) {
                return;
            }

            results.replaceChildren(...updated.childNodes);
            status.textContent = updated.dataset.resultsMessage;
            history.replaceState(history.state, '', url);
        } catch {
            if (!request.signal.aborted) {
                window.location.assign(url);
            }
        } finally {
            if (pending === request) {
                pending = null;
                results.removeAttribute('aria-busy');
            }
        }
    }

    function queueSearch(event) {
        if (event.isComposing) {
            return;
        }
        clearTimeout(timer);
        pending?.abort();
        timer = setTimeout(updateResults, 300);
    }

    const search = form.querySelector('#press-search');
    search.addEventListener('input', queueSearch);
    search.addEventListener('compositionend', queueSearch);
    form.querySelector('select')?.addEventListener('change', updateResults);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        updateResults();
    });
})();
