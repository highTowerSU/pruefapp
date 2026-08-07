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
        plugins: ['dropdown_input'],
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

      // TomSelect normally applies this class itself. Explicitly preserving
      // it makes the source control safe across outerHTML swaps as well.
      select.classList.add('ts-hidden-accessible');
      const hiddenStyle = {
        position: 'absolute', width: '1px', height: '1px', overflow: 'hidden',
        clip: 'rect(0 0 0 0)', clipPath: 'inset(50%)', margin: '-1px', padding: '0', border: '0',
      };
      Object.entries(hiddenStyle).forEach(([property, value]) => {
        select.style.setProperty(property.replace(/[A-Z]/g, letter => '-' + letter.toLowerCase()), value, 'important');
      });
    });
  };

  document.addEventListener('DOMContentLoaded', () => initializeSearchSelects(document));

  // htmx:load is emitted after a fragment has been inserted and processed.
  // That matters for outerHTML swaps of configuration cards.
  document.body.addEventListener('htmx:load', event => initializeSearchSelects(event.detail.elt));
  document.body.addEventListener('htmx:afterSettle', () => initializeSearchSelects(document));
})();
