<script>
(() => {
  'use strict';

  const table = document.getElementById('teilnehmer-tabelle');
  if (!table) {
    return;
  }

  const rowsContainer = document.getElementById('teilnehmer-rows');
  if (!rowsContainer) {
    return;
  }

  const htmxLib = window.htmx;
  if (!htmxLib) {
    console.error('htmx konnte nicht geladen werden.');
    return;
  }

  const confirmMessage = 'Eine andere Zeile wird bereits bearbeitet. Sollen die Änderungen verworfen werden?';
  const columnCount = table.querySelectorAll('thead th').length || 1;

  const findEditingRow = () => rowsContainer.querySelector('tr.editing');

  const updatePlaceholderState = () => {
    const hasDataRows = rowsContainer.querySelector('tr[data-teilnehmer-id]') !== null;
    const hasEditingRows = rowsContainer.querySelector('tr.editing') !== null;
    const placeholder = rowsContainer.querySelector('tr[data-empty-row]');

    if (!hasDataRows && !hasEditingRows) {
      if (!placeholder) {
        const row = document.createElement('tr');
        row.setAttribute('data-empty-row', 'true');
        const cell = document.createElement('td');
        cell.colSpan = columnCount;
        cell.className = 'text-center text-muted py-4';
        cell.textContent = 'Keine Teilnehmer vorhanden.';
        row.appendChild(cell);
        rowsContainer.appendChild(row);
      }
    } else if (placeholder) {
      placeholder.remove();
    }
  };

  const schedulePlaceholderUpdate = () => {
    window.setTimeout(updatePlaceholderState, 0);
  };

  const cancelEditingRow = (row) => {
    if (!row) {
      return;
    }

    htmxLib.trigger(row, 'cancel');
    schedulePlaceholderUpdate();
  };

  const ensureEditingCleared = (currentRow) => {
    const activeRow = findEditingRow();
    if (!activeRow || activeRow === currentRow) {
      return true;
    }

    if (!window.confirm(confirmMessage)) {
      return false;
    }

    cancelEditingRow(activeRow);

    return true;
  };

  document.addEventListener('click', (event) => {
    const editButton = event.target.closest('[data-action="edit-participant"]');
    if (!editButton) {
      return;
    }

    event.preventDefault();

    const currentRow = editButton.closest('tr');
    if (!ensureEditingCleared(currentRow)) {
      return;
    }

    htmxLib.trigger(editButton, 'edit');
  });

  const addRowButton = document.getElementById('btn-add-row');
  if (addRowButton) {
    addRowButton.addEventListener('click', (event) => {
      event.preventDefault();

      if (!ensureEditingCleared(null)) {
        return;
      }

      htmxLib.trigger(addRowButton, 'addRow');
      schedulePlaceholderUpdate();
    });
  }

  document.addEventListener('click', (event) => {
    const cancelButton = event.target.closest('[data-action="cancel-edit"]');
    if (!cancelButton) {
      return;
    }

    event.preventDefault();

    const row = cancelButton.closest('tr');
    cancelEditingRow(row);
  });

  document.body.addEventListener('htmx:afterSwap', (event) => {
    const target = event.detail && event.detail.target ? event.detail.target : null;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (!table.contains(target)) {
      return;
    }

    updatePlaceholderState();

    const editingRow = target.matches('tr.editing')
      ? target
      : target.querySelector('tr.editing');

    if (!editingRow) {
      return;
    }

    const focusable = editingRow.querySelector('input, select, textarea');
    if (focusable && typeof focusable.focus === 'function') {
      focusable.focus();
      if (typeof focusable.select === 'function') {
        focusable.select();
      }
    }
  });

  updatePlaceholderState();
})();
</script>
