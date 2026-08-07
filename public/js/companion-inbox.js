(() => {
  'use strict';
  let stream = null;
  let reconnectTimer = null;
  const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));

  const refresh = (root) => {
    if (!root || !window.htmx) return;
    htmx.ajax('GET', root.dataset.inboxUrl, {target: '#companion-inbox', swap: 'outerHTML'});
  };
  const consume = (root, itemId, target) => {
    if (!root || !itemId || !window.htmx) return;
    htmx.ajax('POST', `${root.dataset.inboxUrl}/${itemId}/uebernehmen`, {
      target: '#companion-inbox', swap: 'outerHTML', values: {target}
    });
  };
  const valuesFor = (root, kind) => [...root.querySelectorAll(`[data-companion-item][data-item-kind="${kind}"]`)];
  const applyValue = (input, value) => {
    if (input.tomselect) input.tomselect.setValue(value, true);
    else { input.value = value; input.dispatchEvent(new Event('input', {bubbles: true})); input.dispatchEvent(new Event('change', {bubbles: true})); }
    input.focus();
  };
  const bindInputs = (root) => {
    document.querySelectorAll('[data-companion-for]').forEach((button) => {
      if (root.dataset.hasActiveConnection !== '1') { button.classList.add('d-none'); return; }
      button.classList.remove('d-none');
      if (button.dataset.companionBound) return;
      button.dataset.companionBound = '1';
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const input = document.getElementById(button.dataset.companionFor);
        const items = valuesFor(root, button.dataset.companionKind || 'barcode');
        if (!input) return;
        const old = window.bootstrap?.Popover.getInstance(button);
        // A field button is a toggle: the second click closes its own menu.
        if (old && button.getAttribute('aria-describedby')) { old.dispose(); return; }
        if (old) old.dispose();
        const content = items.length ? `<div class="d-grid gap-1">${items.map((item) => `<button type="button" class="btn btn-sm btn-primary text-start" data-companion-choose="${item.dataset.itemId}" data-companion-target="${button.dataset.companionFor}"><i class="fa-solid fa-arrow-down me-1"></i>${escapeHtml(item.dataset.itemValue || item.textContent.trim())}</button>`).join('')}</div>` : (root.dataset.hasActiveConnection === '1' ? '<span class="small">Noch keine passenden Werte vom Smartphone empfangen.</span>' : '<span class="small">Noch kein Smartphone verbunden. Im Profil unter „Companion-Geräte“ einen QR-Code erzeugen und auf dem Handy öffnen.</span>');
        const popover = new window.bootstrap.Popover(button, {html: true, sanitize: false, trigger: 'manual', placement: 'bottom', content});
        popover.show();
      });
    });
  };
  document.addEventListener('click', (event) => {
    const choice = event.target.closest('[data-companion-choose]');
    if (!choice) return;
    const root = document.querySelector('[data-companion-inbox]');
    const input = document.getElementById(choice.dataset.companionTarget || '');
    const item = root?.querySelector(`[data-companion-item][data-item-id="${CSS.escape(choice.dataset.companionChoose)}"]`);
    if (!root || !input || !item) return;
    applyValue(input, item.dataset.itemValue || '');
    consume(root, item.dataset.itemId, input.name || input.id || 'Feld');
    document.querySelectorAll('[data-companion-for]').forEach((button) => window.bootstrap?.Popover.getInstance(button)?.dispose());
  });
  const connect = () => {
    const root = document.querySelector('[data-companion-inbox]');
    if (!root || !window.EventSource) return;
    if (stream) stream.close();
    stream = new EventSource(root.dataset.eventsUrl);
    stream.addEventListener('companion-update', () => refresh(root));
    stream.onerror = () => {
      stream?.close(); stream = null;
      clearTimeout(reconnectTimer);
      reconnectTimer = setTimeout(connect, 1700);
    };
    bindInputs(root);
  };
  document.addEventListener('DOMContentLoaded', connect);
  document.body.addEventListener('htmx:afterSwap', (event) => {
    const root = event.target?.matches?.('[data-companion-inbox]') ? event.target : document.querySelector('[data-companion-inbox]');
    if (root) { bindInputs(root); if (!stream) connect(); }
  });
})();
