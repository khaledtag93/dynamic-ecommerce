import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), '../../public/js/storefront-cart-actions.js'), 'utf8');

function classes() {
    const values = new Set(['d-none']);
    return {
        add(...names) { for (const name of names) values.add(name); },
        remove(...names) { for (const name of names) values.delete(name); },
        toggle(name, force) {
            if (force) values.add(name);
            else values.delete(name);
        },
        contains(name) { return values.has(name); },
    };
}

function setup(response, { noFetch = false, checkout = false } = {}) {
    const listeners = new Map();
    const events = [];
    const count = { textContent: '3' };
    const feedbackText = { textContent: '' };
    const feedback = {
        dataset: { error: 'Unable to update cart.' },
        classList: classes(),
        querySelector(selector) {
            return selector === '[data-live-cart-feedback-message]' ? feedbackText : null;
        },
    };
    const button = {
        name: checkout ? 'redirect_to' : '',
        value: checkout ? 'checkout' : '',
        dataset: {},
        innerHTML: 'Add to cart',
        disabled: false,
        setAttribute() {},
        removeAttribute() {},
    };
    const form = {
        action: '/cart/add',
        dataset: {},
        classList: classes(),
        closest(selector) { return selector === 'form[data-live-cart-add]' ? this : null; },
        querySelector() { return button; },
        setAttribute() {},
        removeAttribute() {},
    };
    const document = {
        addEventListener(name, callback) { listeners.set(name, callback); },
        querySelector(selector) { return selector === '[data-live-cart-feedback]' ? feedback : null; },
        querySelectorAll(selector) { return selector === '[data-layout-cart-count]' ? [count] : []; },
        dispatchEvent(event) { events.push(event); },
    };
    const send = async () => response;
    const window = { setTimeout: () => 1, ...(noFetch ? {} : { fetch: send }) };
    class FormData {}
    class CustomEvent {
        constructor(type, options) { this.type = type; this.detail = options?.detail; }
    }
    vm.runInNewContext(source, {
        document, window, FormData, CustomEvent, clearTimeout() {},
        ...(noFetch ? {} : { fetch: send }),
    });

    return {
        count, feedback, feedbackText, button, form, events,
        async submit() {
            const event = {
                target: form,
                submitter: button,
                prevented: false,
                preventDefault() { this.prevented = true; },
                stopPropagation() {},
            };
            await listeners.get('submit')(event);
            return event;
        },
    };
}

test('HTTP 200 returning HTML must not report a successful cart mutation', async () => {
    const app = setup({
        ok: true,
        json: async () => { throw new SyntaxError('Unexpected token <'); },
    });

    await app.submit();

    assert.equal(app.count.textContent, '3');
    assert.equal(app.feedback.classList.contains('lc-flash-toast--danger'), true);
    assert.equal(app.events.length, 0);
    assert.equal(app.button.disabled, false);
    assert.equal(app.form.dataset.liveCartPending, undefined);
});

test('HTTP 200 with JSON lacking cart.items_count must not claim success', async () => {
    const app = setup({
        ok: true,
        json: async () => ({ message: 'Sign in first' }),
    });

    await app.submit();

    assert.equal(app.count.textContent, '3');
    assert.equal(app.feedback.classList.contains('lc-flash-toast--danger'), true);
    assert.equal(app.events.length, 0);
});

test('confirmed JSON mutation updates count, including a valid zero count', async () => {
    for (const number of [4, 0]) {
        const app = setup({
            ok: true,
            json: async () => ({ message: 'Added', cart: { items_count: number } }),
        });

        await app.submit();

        assert.equal(app.count.textContent, String(number));
        assert.equal(app.feedback.classList.contains('lc-flash-toast--success'), true);
        assert.equal(app.events.length, 1);
        assert.equal(app.events[0].type, 'storefront:cart-updated');
    }
});

test('checkout submission and no-fetch fallback are not intercepted', async () => {
    for (const options of [{ checkout: true }, { noFetch: true }]) {
        const app = setup(null, options);
        const event = await app.submit();
        assert.equal(event.prevented, false);
        assert.equal(app.events.length, 0);
        assert.equal(app.count.textContent, '3');
    }
});
