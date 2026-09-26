import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(resolve(dirname(fileURLToPath(import.meta.url)), '../../public/admin/js/admin-confirm-dialog.js'), 'utf8');

function classList() {
  const values = new Set();
  return {
    add: (value) => values.add(value),
    remove: (value) => values.delete(value),
    contains: (value) => values.has(value),
    toggle(value, force) {
      if (force) values.add(value);
      else values.delete(value);
    },
  };
}

function environment() {
  const listeners = new Map();
  const document = {
    activeElement: null,
    body: { classList: classList() },
    addEventListener(type, callback) {
      if (!listeners.has(type)) listeners.set(type, new Set());
      listeners.get(type).add(callback);
    },
    removeEventListener(type, callback) {
      listeners.get(type)?.delete(callback);
    },
    dispatch(type, event) {
      for (const callback of listeners.get(type) || []) callback(event);
    },
  };
  const ids = [
    'adminConfirmModal', 'adminConfirmBackdrop', 'adminConfirmOk', 'adminConfirmCancel',
    'adminConfirmTitle', 'adminConfirmMessage', 'adminConfirmSubtitle',
    'adminConfirmInputWrap', 'adminConfirmInputLabel', 'adminConfirmInput', 'adminConfirmInputError',
  ];
  const elements = Object.fromEntries(ids.map((id) => [id, {
    id,
    textContent: id,
    classList: classList(),
    attributes: {},
    listeners: new Map(),
    isConnected: true,
    setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(type, callback) { this.listeners.set(type, callback); },
    removeEventListener(type) { this.listeners.delete(type); },
    dispatch(type) { this.listeners.get(type)?.(); },
    focus() { document.activeElement = this; },
  }]));
  const app = { inert: false };
  const modal = elements.adminConfirmModal;
  modal.contains = (element) => [modal, elements.adminConfirmInput, elements.adminConfirmCancel, elements.adminConfirmOk].includes(element);
  document.getElementById = (id) => elements[id];
  document.querySelector = (selector) => selector === '.container-scroller' ? app : null;
  const opener = { isConnected: true, focus() { document.activeElement = this; } };
  document.activeElement = opener;
  const window = {};
  vm.runInNewContext(script, { document, window });

  function key(key, shiftKey = false) {
    const event = { key, shiftKey, prevented: false, preventDefault() { this.prevented = true; } };
    document.dispatch('keydown', event);
    return event;
  }

  function form(attributes = {}, field = null) {
    const instance = {
      attributes,
      dataset: {},
      elements: { namedItem: () => field },
      matches: (selector) => selector === '[data-confirm-message]' && Object.hasOwn(attributes, 'data-confirm-message'),
      getAttribute(name) { return attributes[name] ?? null; },
      querySelector: () => null,
      valid: true,
      requests: [],
      requestSubmit(submitter) {
        this.requests.push(submitter);
        if (this.valid) document.dispatch('submit', { target: this, submitter });
      },
    };
    return instance;
  }

  function submit(form, submitter = null) {
    const event = { target: form, submitter, prevented: false, preventDefault() { this.prevented = true; } };
    document.dispatch('submit', event);
    return event;
  }

  return { window, document, elements, app, opener, key, form, submit };
}

test('confirmation owns focus, traps Tab and restores the trigger on cancel', () => {
  const e = environment();
  let called = false;
  e.window.adminConfirmAction(() => { called = true; });
  assert.equal(e.document.activeElement, e.elements.adminConfirmCancel);
  assert.equal(e.app.inert, true);
  assert.equal(e.document.body.classList.contains('admin-confirm-open'), true);

  assert.equal(e.key('Tab', true).prevented, true);
  assert.equal(e.document.activeElement, e.elements.adminConfirmOk);
  assert.equal(e.key('Tab').prevented, true);
  assert.equal(e.document.activeElement, e.elements.adminConfirmCancel);

  e.key('Escape');
  assert.equal(e.app.inert, false);
  assert.equal(e.document.activeElement, e.opener);
  assert.equal(e.elements.adminConfirmModal.attributes['aria-hidden'], 'true');
  assert.equal(called, false);
});

test('required phrase blocks confirmation, announces error and clears it when corrected', () => {
  const e = environment();
  const values = [];
  e.window.adminConfirmAction((value) => values.push(value), { requiredInput: 'DEPLOY' });
  assert.equal(e.document.activeElement, e.elements.adminConfirmInput);
  e.elements.adminConfirmOk.onclick();
  assert.equal(values.length, 0);
  assert.equal(e.elements.adminConfirmInput.attributes['aria-invalid'], 'true');
  assert.equal(e.elements.adminConfirmInputError.classList.contains('d-none'), false);

  e.elements.adminConfirmInput.value = 'DEPLOY';
  e.elements.adminConfirmInput.dispatch('input');
  assert.equal(e.elements.adminConfirmInput.attributes['aria-invalid'], 'false');
  e.elements.adminConfirmOk.onclick();
  assert.deepEqual(values, ['DEPLOY']);
  assert.equal(e.document.activeElement, e.opener);
});

test('form confirmation preserves the submitter and never leaves a validation bypass flag', () => {
  const e = environment();
  const form = e.form({ 'data-confirm-message': 'Delete this record?' });
  const submitter = { form, disabled: false };
  assert.equal(e.submit(form, submitter).prevented, true);
  assert.equal(form.requests.length, 0);
  e.elements.adminConfirmCancel.onclick();
  assert.equal(form.dataset.confirmed, undefined);

  assert.equal(e.submit(form, submitter).prevented, true);
  form.valid = false;
  e.elements.adminConfirmOk.onclick();
  assert.equal(form.requests[0], submitter);
  assert.equal(form.dataset.confirmed, '0');
  assert.equal(e.submit(form, submitter).prevented, true);

  form.valid = true;
  e.elements.adminConfirmOk.onclick();
  assert.equal(form.requests[1], submitter);
  assert.equal(form.dataset.confirmed, '0');
});

test('conditional confirmation follows the active field instead of interrupting inactive saves', () => {
  const e = environment();
  const field = { type: 'checkbox', checked: false, value: '1' };
  const form = e.form({
    'data-confirm-message': 'Publish this coupon?',
    'data-confirm-when-field': 'is_active',
    'data-confirm-when-value': '1',
  }, field);
  assert.equal(e.window.adminFormNeedsConfirmation(form), false);
  assert.equal(e.submit(form).prevented, false);
  field.checked = true;
  assert.equal(e.window.adminFormNeedsConfirmation(form), true);
  assert.equal(e.submit(form).prevented, true);
});
