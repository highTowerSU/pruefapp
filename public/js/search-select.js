(() => {
  'use strict';

  const initializeSearchSelects = root => {
    if (typeof window.TomSelect !== 'function') {
      return;
    }

    // Alle normalen Bootstrap-Auswahllisten erhalten dieselbe touch- und
    // tastaturfreundliche Suche; data-search-select bleibt als explizite
    // Markierung für Sonderfälle bestehen.
    root.querySelectorAll('select:not([data-no-search])').forEach(select => {
      if (select.tomselect) {
        return;
      }

      const externalLabel = (select.id && document.querySelector(`label[for="${CSS.escape(select.id)}"]`))
        || (select.previousElementSibling && select.previousElementSibling.tagName === 'LABEL' ? select.previousElementSibling : null);
      const placeholder = externalLabel
        ? ''
        : (Object.prototype.hasOwnProperty.call(select.dataset, 'placeholder') ? select.dataset.placeholder : 'Auswählen und suchen');

      new window.TomSelect(select, {
        allowEmptyOption: true,
        closeAfterSelect: true,
        create: false,
        maxOptions: null,
        openOnFocus: true,
        placeholder,
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
