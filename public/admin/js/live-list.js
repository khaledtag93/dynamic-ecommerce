/* Progressive enhancement for server-rendered GET filter lists. */
(() => {
    'use strict';

    function mount(root) {
        const form = root.querySelector('[data-live-filter]');
        const results = root.querySelector('[data-live-results]');
        const status = root.querySelector('[data-live-status]');
        const fallback = root.querySelector('[data-live-fallback]');
        if (!form || !results || !status || !fallback || !window.fetch || !window.AbortController) return;

        const base = new URL(form.action, window.location.href);
        if (base.origin !== window.location.origin || base.pathname !== window.location.pathname) return;

        let pending = null;
        let timer = null;
        let revision = 0;

        function stopPending() {
            clearTimeout(timer);
            if (pending) pending.abort();
            pending = null;
            revision += 1;
        }

        function formUrl() {
            const url = new URL(base.href);
            url.search = '';
            for (const [key, value] of new FormData(form).entries()) {
                if (typeof value === 'string' && value.trim()) url.searchParams.append(key, value.trim());
            }
            return url;
        }

        function syncForm(url) {
            for (const field of form.elements) {
                if (!field.name || !('value' in field)) continue;
                field.value = url.searchParams.get(field.name)
                    ?? field.dataset.liveDefault
                    ?? (field.tagName === 'SELECT' ? field.options[0]?.value ?? '' : '');
            }
        }

        async function load(url, historyMode) {
            if (url.origin !== base.origin || url.pathname !== base.pathname) return;
            stopPending();
            const current = revision;
            const controller = new AbortController();
            pending = controller;
            results.setAttribute('aria-busy', 'true');
            status.textContent = status.dataset.loading;
            fallback.hidden = true;

            try {
                const response = await fetch(url.href, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'text/html', 'X-Live-List': '1' },
                    signal: controller.signal,
                });
                if (!response.ok || response.redirected || new URL(response.url).pathname !== base.pathname) {
                    throw new Error('Unexpected list response');
                }
                const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
                const next = documentFragment.querySelector('[data-live-results]');
                if (!next) throw new Error('Missing list results');
                if (current !== revision) return;

                results.innerHTML = next.innerHTML;
                syncForm(url);
                if (historyMode === 'push' && url.href !== window.location.href) {
                    window.history.pushState(null, '', url.href);
                } else if (historyMode === 'replace') {
                    window.history.replaceState(null, '', url.href);
                }
                status.textContent = status.dataset.updated;
            } catch (error) {
                if (current !== revision || error.name === 'AbortError') return;
                status.textContent = status.dataset.error;
                fallback.href = url.href;
                fallback.hidden = false;
            } finally {
                if (current === revision) {
                    pending = null;
                    results.setAttribute('aria-busy', 'false');
                }
            }
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            load(formUrl(), 'push');
        });

        for (const input of form.querySelectorAll('[data-live-search]')) {
            input.addEventListener('input', () => {
                stopPending();
                results.setAttribute('aria-busy', 'false');
                status.textContent = '';
                fallback.hidden = true;
                timer = setTimeout(() => load(formUrl(), 'replace'), 300);
            });
        }

        for (const control of form.querySelectorAll('[data-live-filter-control]')) {
            control.addEventListener('change', () => load(formUrl(), 'push'));
        }

        root.querySelector('[data-live-reset]')?.addEventListener('click', (event) => {
            event.preventDefault();
            for (const field of form.elements) {
                if (field.name && 'value' in field) field.value = '';
            }
            load(new URL(base.href), 'push');
        });

        root.addEventListener('click', (event) => {
            const link = event.target.closest('.pagination a[href], a[data-live-link][href]');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            const url = new URL(link.href, window.location.href);
            if (url.origin !== base.origin || url.pathname !== base.pathname) return;
            event.preventDefault();
            load(url, 'push');
        });

        results.addEventListener('submit', (event) => {
            if (!event.target.matches('form[data-submit-loading]')) return;
            const button = event.submitter;
            if (!button || button.dataset.loadingApplied === '1') return;
            button.dataset.loadingApplied = '1';
            button.disabled = true;
            event.target.setAttribute('aria-busy', 'true');
            status.textContent = button.dataset.loadingText || status.dataset.loading;
        });

        window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);
            if (url.origin !== base.origin || url.pathname !== base.pathname) return;
            syncForm(url);
            load(url, 'none');
        });
    }

    function initialize() {
        document.querySelectorAll('[data-live-list]').forEach(mount);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
