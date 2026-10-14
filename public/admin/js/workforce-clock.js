/* Workforce live durations are display-only. All payroll totals remain server-derived. */
(() => {
    'use strict';

    const duration = (node) => {
        const start = Number(node.dataset.start);
        const closedBreak = Number(node.dataset.closedBreak || 0);
        const breakStart = Number(node.dataset.openBreak || 0);
        if (!Number.isFinite(start) || start <= 0) return null;

        const now = Math.floor(Date.now() / 1000);
        const gross = Math.max(0, Math.floor((now - start) / 60));
        const openBreak = breakStart > 0 ? Math.max(0, Math.floor((now - breakStart) / 60)) : 0;
        return { net: Math.max(0, gross - closedBreak - openBreak), breaks: closedBreak + openBreak };
    };

    const update = () => {
        document.querySelectorAll('[data-workforce-live-net], [data-workforce-live-break]').forEach((node) => {
            const minutes = duration(node);
            if (!minutes) return;

            if (node.hasAttribute('data-workforce-live-break')) {
                const template = node.dataset.breakTemplate || ':minutes min';
                node.textContent = template.replace(':minutes', String(minutes.breaks));
            } else {
                const template = node.dataset.netTemplate || ':hours h :minutes m';
                node.textContent = template
                    .replace(':hours', String(Math.floor(minutes.net / 60)))
                    .replace(':minutes', String(minutes.net % 60));
            }
        });
    };

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-workforce-void-form]');
        if (!form) return;
        const question = form.dataset.confirmMessage || 'Void this attendance session? This cannot be undone.';
        if (!window.confirm(question)) event.preventDefault();
    });

    update();
    window.setInterval(update, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) update(); });
})();
