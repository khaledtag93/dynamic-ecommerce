(function () {
    const activeSections = new Map();

    function activate(root, key, focusTab = false) {
        if (!root) return;
        const tabs = Array.from(root.querySelectorAll('[data-admin-section-tab]'));
        const tab = tabs.find((item) => item.dataset.adminSectionTab === key);
        if (!tab) return;

        const groupId = root.dataset.adminSectionTabs;
        activeSections.set(groupId, key);
        if (root.dataset.adminSectionHistory === 'true') {
            const url = new URL(window.location.href);
            url.searchParams.set('section', key);
            window.history.replaceState({}, '', url);
        }
        tabs.forEach((item) => {
            const selected = item === tab;
            item.setAttribute('aria-selected', selected ? 'true' : 'false');
            item.tabIndex = selected ? 0 : -1;
        });
        root.querySelectorAll('[data-admin-section-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.adminSectionPanel !== key;
        });
        root.querySelectorAll('.admin-section-columns > [class*="col-"]').forEach((column) => {
            column.hidden = !column.querySelector('[data-admin-section-panel]:not([hidden])');
        });
        root.classList.add('admin-sections-ready');
        if (focusTab) tab.focus();
    }

    function init(root = document) {
        const groups = root.matches?.('[data-admin-section-tabs]')
            ? [root]
            : root.querySelectorAll('[data-admin-section-tabs]');
        groups.forEach((group) => {
            const first = group.querySelector('[data-admin-section-tab]');
            if (!first) return;
            const invalid = group.querySelector('[data-admin-section-panel] .is-invalid, [data-admin-section-panel] .product-error-field');
            let serverErrorPanel = null;
            try {
                const errors = JSON.parse(group.dataset.adminErrorFields || '[]');
                serverErrorPanel = Array.from(group.querySelectorAll('[data-admin-section-panel]')).find((panel) =>
                    Array.from(panel.querySelectorAll('[name]')).some((field) =>
                        errors.some((error) => {
                            const name = field.name.replace(/\[([^\]]+)\]/g, '.$1');
                            return error === name || error.startsWith(name + '.');
                        })));
            } catch (_) {
                // The form still opens its first section if error metadata is unavailable.
            }
            const requestedSection = group.dataset.adminSectionHistory === 'true'
                ? new URLSearchParams(window.location.search).get('section')
                : null;
            const requestedTab = requestedSection
                ? group.querySelector('[data-admin-section-tab="' + CSS.escape(requestedSection) + '"]')
                : null;
            const key = invalid?.closest('[data-admin-section-panel]')?.dataset.adminSectionPanel
                || serverErrorPanel?.dataset.adminSectionPanel
                || requestedTab?.dataset.adminSectionTab
                || activeSections.get(group.dataset.adminSectionTabs)
                || first.dataset.adminSectionTab;
            activate(group, key);
        });
    }

    document.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-admin-section-tab]');
        if (tab) activate(tab.closest('[data-admin-section-tabs]'), tab.dataset.adminSectionTab);
    });

    document.addEventListener('keydown', (event) => {
        const tab = event.target.closest('[data-admin-section-tab]');
        if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        const root = tab.closest('[data-admin-section-tabs]');
        const tabs = Array.from(root.querySelectorAll('[data-admin-section-tab]'));
        const index = tabs.indexOf(tab);
        const direction = document.documentElement.dir === 'rtl' ? -1 : 1;
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
            : (index + (event.key === 'ArrowRight' ? direction : -direction) + tabs.length) % tabs.length;
        event.preventDefault();
        activate(root, tabs[next].dataset.adminSectionTab, true);
    });

    window.AdminSectionTabs = { init, activate };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
    else init();
})();
