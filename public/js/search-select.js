(() => {
  'use strict';

  const initializeSearchSelects = root => {
    if (typeof window.TomSelect !== 'function') {
      return;
    }

    // Alle normalen Bootstrap-Auswahllisten erhalten dieselbe touch- und
    // tastaturfreundliche Suche; data-search-select bleibt als explizite
    // Markierung für Sonderfälle bestehen.
    root.querySelectorAll('select.form-select:not([data-no-search]), select[data-search-select]:not([data-no-search]), select[name="tenant_id"]:not([data-no-search]), select[name="sevdesk_customer_id"]:not([data-no-search])').forEach(select => {
      if (select.tomselect) {
        return;
      }

      new window.TomSelect(select, {
        allowEmptyOption: true,
        closeAfterSelect: true,
        create: false,
        maxOptions: null,
        openOnFocus: true,
        placeholder: select.dataset.placeholder || 'Auswählen und suchen',
        searchField: ['text'],
        selectOnTab: true,
        render: {
          no_results: () => '<div class="no-results">Keine passenden Einträge</div>',
        },
      });
    });
  };

  document.addEventListener('DOMContentLoaded', () => initializeSearchSelects(document));
  document.body.addEventListener('htmx:afterSwap', event => initializeSearchSelects(event.target));
})();
