(() => {
    'use strict';

    let feedbackTimer = null;

    function feedbackNode() {
        return document.querySelector('[data-live-cart-feedback]');
    }

    function showFeedback(message, isError) {
        const node = feedbackNode();
        if (!node) return;

        clearTimeout(feedbackTimer);
        const messageNode = node.querySelector('[data-live-cart-feedback-message]');
        const icon = node.querySelector('[data-live-cart-feedback-icon]');
        if (messageNode) messageNode.textContent = message || node.dataset.error || '';
        node.classList.toggle('lc-flash-toast--success', !isError);
        node.classList.toggle('lc-flash-toast--danger', Boolean(isError));
        if (icon) icon.className = isError ? 'bi bi-exclamation-circle-fill' : 'bi bi-check-circle-fill';
        node.classList.remove('d-none', 'is-leaving');

        const hide = () => {
            node.classList.add('is-leaving');
            window.setTimeout(() => node.classList.add('d-none'), 180);
        };

        node.querySelector('[data-live-cart-feedback-close]')?.addEventListener('click', hide, { once: true });
        feedbackTimer = window.setTimeout(hide, 4200);
    }

    function updateCartCount(count) {
        document.querySelectorAll('[data-layout-cart-count]').forEach((node) => {
            node.textContent = String(count ?? 0);
        });
    }

    function restoreButton(button) {
        if (!button) return;
        button.disabled = false;

        if (button.dataset.liveCartOriginalHtml !== undefined) {
            button.innerHTML = button.dataset.liveCartOriginalHtml;
            delete button.dataset.liveCartOriginalHtml;
        }
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-live-cart-add]');
        if (!form || !window.fetch) return;

        const submitter = event.submitter || form.querySelector('button[type="submit"]');
        if (submitter?.name === 'redirect_to' && submitter.value === 'checkout') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        if (form.dataset.liveCartPending === '1') return;
        form.dataset.liveCartPending = '1';

        if (submitter) {
            submitter.dataset.liveCartOriginalHtml = submitter.innerHTML;
            const loadingText = submitter.dataset.loadingText;
            if (loadingText) {
                submitter.innerHTML = '<span class="lc-loading-spinner"></span>' + loadingText;
            }
            submitter.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Cart-Add-Live': '1',
                },
                body: new FormData(form),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const errors = payload.errors || {};
                const firstError = Object.values(errors).flat()[0];
                throw new Error(firstError || payload.message || feedbackNode()?.dataset.error || 'Unable to update cart.');
            }

            updateCartCount(payload.cart?.items_count);
            showFeedback(payload.message || '', false);

            document.dispatchEvent(new CustomEvent('storefront:cart-updated', {
                detail: payload,
            }));
        } catch (error) {
            showFeedback(error.message || feedbackNode()?.dataset.error || 'Unable to update cart.', true);
        } finally {
            restoreButton(submitter);
            delete form.dataset.liveCartPending;
        }
    }, true);
})();
