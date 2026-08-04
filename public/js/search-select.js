(() => {
  'use strict';

  const initializeSearchSelects = root => {
    if (typeof window.TomSelect !== 'function') {
      return;
    }

    root.querySelectorAll('select[data-search-select], select[name="tenant_id"], select[name="sevdesk_customer_id"]').forEach(select => {
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
