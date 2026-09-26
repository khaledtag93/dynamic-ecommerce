(function () {
  'use strict';

  const modal = document.getElementById('adminConfirmModal');
  const backdrop = document.getElementById('adminConfirmBackdrop');
  const ok = document.getElementById('adminConfirmOk');
  const cancel = document.getElementById('adminConfirmCancel');
  const title = document.getElementById('adminConfirmTitle');
  const message = document.getElementById('adminConfirmMessage');
  const subtitle = document.getElementById('adminConfirmSubtitle');
  const inputWrap = document.getElementById('adminConfirmInputWrap');
  const inputLabel = document.getElementById('adminConfirmInputLabel');
  const input = document.getElementById('adminConfirmInput');
  const inputError = document.getElementById('adminConfirmInputError');
  const app = document.querySelector('.container-scroller');

  if (!modal || !backdrop || !ok || !cancel || !input || !inputWrap || !inputError) return;

  const defaults = {
    title: title.textContent,
    message: message.textContent,
    subtitle: subtitle.textContent,
    confirmLabel: ok.textContent,
    cancelLabel: cancel.textContent,
    requiredInputLabel: inputLabel.textContent,
  };

  function needsConfirmation(form) {
    if (!form.matches('[data-confirm-message]')) return false;

    const name = form.getAttribute('data-confirm-when-field');
    if (!name) return true;

    const field = form.elements.namedItem(name);
    if (!field) return true;

    const expected = form.getAttribute('data-confirm-when-value') ?? '1';
    const current = field.type === 'checkbox'
      ? (field.checked ? (field.value || '1') : '0')
      : field.value;

    return String(current ?? '') === String(expected);
  }

  function confirmAction(callback, options = {}) {
    if (modal.classList.contains('show')) return false;

    const expected = (options.requiredInput || '').trim();
    const opener = document.activeElement;
    const wasInert = app ? app.inert : false;
    let attempted = false;
    let completed = false;

    title.textContent = options.title || defaults.title;
    message.textContent = options.message || defaults.message;
    subtitle.textContent = options.subtitle || defaults.subtitle;
    ok.textContent = options.confirmLabel || defaults.confirmLabel;
    cancel.textContent = options.cancelLabel || defaults.cancelLabel;
    inputLabel.textContent = options.requiredInputLabel || defaults.requiredInputLabel;
    inputWrap.hidden = !expected;
    input.value = '';
    input.placeholder = expected ? (options.requiredInputPlaceholder || expected) : '';
    inputError.classList.add('d-none');
    input.setAttribute('aria-invalid', 'false');

    function showInputError(show) {
      inputError.classList.toggle('d-none', !show);
      input.setAttribute('aria-invalid', String(show));
    }

    function handleInput() {
      if (attempted) showInputError(input.value.trim() !== expected);
    }

    function cleanup() {
      if (!modal.classList.contains('show')) return;

      modal.classList.remove('show');
      modal.setAttribute('aria-hidden', 'true');
      backdrop.classList.remove('show');
      document.body.classList.remove('admin-confirm-open');
      if (app) app.inert = wasInert;
      ok.onclick = null;
      cancel.onclick = null;
      backdrop.onclick = null;
      document.removeEventListener('keydown', handleKeydown);
      input.removeEventListener('input', handleInput);

      if (opener && opener.isConnected) opener.focus({ preventScroll: true });
    }

    function handleKeydown(event) {
      if (event.key === 'Escape') {
        event.preventDefault();
        cleanup();
        return;
      }
      if (event.key !== 'Tab') return;

      const first = expected ? input : cancel;
      const last = ok;
      if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) {
        event.preventDefault();
        last.focus({ preventScroll: true });
      } else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) {
        event.preventDefault();
        first.focus({ preventScroll: true });
      }
    }

    cancel.onclick = cleanup;
    backdrop.onclick = cleanup;
    ok.onclick = function () {
      if (completed) return;
      if (expected && input.value.trim() !== expected) {
        attempted = true;
        showInputError(true);
        input.focus({ preventScroll: true });
        return;
      }

      completed = true;
      const typedValue = input.value.trim();
      cleanup();
      if (callback) callback(typedValue);
    };

    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    backdrop.classList.add('show');
    document.body.classList.add('admin-confirm-open');
    (expected ? input : cancel).focus({ preventScroll: true });
    if (app) app.inert = true;

    if (expected) input.addEventListener('input', handleInput);
    document.addEventListener('keydown', handleKeydown);
    return true;
  }

  window.adminFormNeedsConfirmation = needsConfirmation;
  window.adminConfirmAction = confirmAction;

  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!form.matches('[data-confirm-message]')) return;
    if (form.dataset.confirmed === '1') {
      form.dataset.confirmed = '0';
      return;
    }
    if (!needsConfirmation(form)) return;

    event.preventDefault();
    const submitter = event.submitter;
    confirmAction(function (typedValue) {
      const targetSelector = form.getAttribute('data-confirm-input-target');
      if (targetSelector) {
        const targetField = form.querySelector(targetSelector);
        if (targetField) targetField.value = typedValue;
      }

      form.dataset.confirmed = '1';
      try {
        if (submitter && submitter.form === form && !submitter.disabled) form.requestSubmit(submitter);
        else form.requestSubmit();
      } finally {
        // Native validation can block requestSubmit before a second submit event fires.
        form.dataset.confirmed = '0';
      }
    }, {
      title: form.getAttribute('data-confirm-title'),
      message: form.getAttribute('data-confirm-message'),
      subtitle: form.getAttribute('data-confirm-subtitle'),
      confirmLabel: form.getAttribute('data-confirm-ok'),
      cancelLabel: form.getAttribute('data-confirm-cancel'),
      requiredInput: form.getAttribute('data-confirm-input-expected'),
      requiredInputLabel: form.getAttribute('data-confirm-input-label'),
      requiredInputPlaceholder: form.getAttribute('data-confirm-input-placeholder'),
    });
  });
})();
