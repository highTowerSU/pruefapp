(() => {
  'use strict';
  let stream = null;
  let reconnectTimer = null;

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
  const bindInputs = (root) => {
    document.querySelectorAll('[data-companion-for]').forEach((button) => {
      if (button.dataset.companionBound) return;
      button.dataset.companionBound = '1';
      button.addEventListener('click', (event) => {
        event.preventDefault();
        const input = document.getElementById(button.dataset.companionFor);
        const items = valuesFor(root, button.dataset.companionKind || 'barcode');
        if (!input || items.length === 0) return;
        const item = items[0];
        input.value = item.dataset.itemValue || '';
        input.dispatchEvent(new Event('input', {bubbles: true}));
        input.dispatchEvent(new Event('change', {bubbles: true}));
        consume(root, item.dataset.itemId, input.name || input.id || 'Feld');
        input.focus();
      });
    });
  };
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
