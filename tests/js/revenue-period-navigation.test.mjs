import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import vm from 'node:vm';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const template = readFileSync(resolve(root, 'resources/views/admin/analytics/index.blade.php'), 'utf8');
const script = readFileSync(resolve(root, 'public/admin/js/live-list.js'), 'utf8');

function simulateAnalyticsNavigation(kind) {
    const shell = template.match(/<div class="analytics-shell"[^>]*>/)?.[0] ?? '';
    const hasLiveList = shell.includes('data-live-list');
    const listeners = new Map();
    let fetches = 0;
    const document = {
        readyState: 'complete',
        querySelectorAll(selector) {
            return selector === '[data-live-list]' && hasLiveList ? [analytics] : [];
        },
    };
    const form = {
        action: 'https://example.test/admin/analytics',
        elements: [],
        addEventListener(name, callback) { listeners.set('form:' + name, callback); },
        querySelectorAll() { return []; },
    };
    const results = { innerHTML: 'Today metrics', setAttribute() {}, addEventListener() {} };
    const status = { dataset: { loading: 'Loading', updated: 'Updated', error: 'Error' }, textContent: '' };
    const fallback = { hidden: true, href: '' };
    const analytics = {
        querySelector(selector) {
            return ({
                '[data-live-filter]': form,
                '[data-live-results]': results,
                '[data-live-status]': status,
                '[data-live-fallback]': fallback,
            })[selector] ?? null;
        },
        querySelectorAll() { return []; },
        addEventListener(name, callback) { listeners.set('root:' + name, callback); },
    };
    const window = {
        fetch: async () => {
            fetches += 1;
            return { ok: true, redirected: false, url: 'https://example.test/admin/analytics?range=7d', text: async () => '<div data-live-results>7d metrics</div>' };
        },
        AbortController,
        location: { href: 'https://example.test/admin/analytics?range=today', origin: 'https://example.test', pathname: '/admin/analytics', search: '?range=today' },
        history: { pushState() {}, replaceState() {} },
        addEventListener() {},
    };
    class DOMParser {
        parseFromString() {
            return { querySelector: () => ({ innerHTML: '7d metrics' }) };
        }
    }
    class FormData {
        *entries() { yield ['range', '7d']; }
    }
    vm.runInNewContext(script, { document, window, URL, FormData, DOMParser, AbortController, setTimeout, clearTimeout });

    const event = {
        button: 0,
        defaultPrevented: false,
        preventDefault() { this.defaultPrevented = true; },
        target: {
            closest(selector) {
                return kind === 'link' && selector.includes('[data-live-link]')
                    ? { href: 'https://example.test/admin/analytics?range=7d' }
                    : null;
            },
        },
    };
    if (kind === 'link') listeners.get('root:click')?.(event);
    if (kind === 'form') listeners.get('form:submit')?.(event);

    return { event, fetches, hasLiveList };
}

test('revenue range pills use full navigation when period heading is outside partial results', () => {
    assert.match(template, /Reporting window/);
    assert.ok(template.indexOf('Reporting window') < template.indexOf('<div data-live-results>'));
    const result = simulateAnalyticsNavigation('link');
    assert.equal(result.event.defaultPrevented, false,
        'Partial range navigation can show new metrics beside the old period label and active pill.');
    assert.equal(result.fetches, 0);
});

test('revenue date filter keeps the full-page period label and filters consistent', () => {
    assert.ok(template.indexOf('data-live-filter') < template.indexOf('<div data-live-results>'));
    const result = simulateAnalyticsNavigation('form');
    assert.equal(result.event.defaultPrevented, false,
        'The reporting window is outside partial results; date changes must replace the complete page.');
    assert.equal(result.fetches, 0);
});
