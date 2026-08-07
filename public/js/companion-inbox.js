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
  const closePopovers = (except = null) => document.querySelectorAll('[data-companion-for]').forEach((button) => { if (button !== except) window.bootstrap?.Popover.getInstance(button)?.dispose(); });
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
        if (!input) return;
        const old = window.bootstrap?.Popover.getInstance(button);
        // A field button is a toggle: the second click closes its own menu.
        if (old && button.getAttribute('aria-describedby')) { old.dispose(); return; }
        if (old) old.dispose();
        closePopovers(button);
        const content = root.dataset.hasActiveConnection === '1'
          ? `<div hx-get="${root.dataset.inboxUrl}?field=${encodeURIComponent(button.dataset.companionFor)}" hx-trigger="load" hx-swap="innerHTML"><span class="small"><span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Companion-Werte laden …</span></div>`
          : '<span class="small">Noch kein Smartphone verbunden. Im Profil unter „Companion-Geräte“ einen QR-Code erzeugen und auf dem Handy öffnen.</span>';
        const popover = new window.bootstrap.Popover(button, {html: true, sanitize: false, trigger: 'manual', placement: 'bottom', content});
        popover.show();
        window.htmx?.process(document.getElementById(button.getAttribute('aria-describedby') || ''));
      });
    });
  };
  document.addEventListener('click', (event) => {
    const choice = event.target.closest('[data-companion-choose]');
    if (!choice) return;
    const root = document.querySelector('[data-companion-inbox]');
    const input = document.getElementById(choice.dataset.companionTarget || '');
    if (!root || !input) return;
    applyValue(input, choice.dataset.companionValue || '');
    consume(root, choice.dataset.companionChoose, input.name || input.id || 'Feld');
    closePopovers();
  });
  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-companion-for], .popover')) closePopovers();
  });
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closePopovers(); });
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
