<?php $moodleCourseOptions = $moodleCourseOptions ?? []; ?>
<?php $moodleCourseError = $moodleCourseError ?? null; ?>
<?php if (current_user_can_manage_courses()): ?>
  <form method="post"
        class="mb-4"
        hx-post="kurse"
        hx-target="#kurs-tabelle"
        hx-swap="outerHTML"
        hx-on::after-request="if (event.detail.successful) { this.reset(); const input = this.querySelector('[name=kursname]'); if (input) { input.focus(); } }">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label visually-hidden" for="kursname">Neuer Kursname</label>
        <input type="text" id="kursname" name="kursname" class="form-control" placeholder="Neuer Kursname" required>
      </div>
      <div class="col-md-2">
        <button class="btn btn-primary w-100">Kurs anlegen</button>
      </div>
    </div>

    <div class="card mt-3" id="moodle-options-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Moodle-Optionen</h5>
        <button class="btn btn-outline-secondary btn-sm collapsed"
                type="button"
                id="moodle-options-toggle"
                data-bs-toggle="collapse"
                data-bs-target="#moodle-options-collapse"
                aria-expanded="false"
                aria-controls="moodle-options-collapse">
          <span data-label-collapsed>Optionen anzeigen</span>
          <span data-label-expanded class="d-none">Optionen verbergen</span>
        </button>
      </div>
      <div class="collapse" id="moodle-options-collapse">
        <div class="card-body">
          <p class="card-text small text-muted">
            Optional kannst du hier einen bestehenden Moodle-Kurs kopieren. Der Shortname wird außerdem beim Teilnehmerimport
            verwendet, damit die Nutzer automatisch im richtigen Kurs landen.
          </p>

          <?php if (!empty($moodleCourseError)): ?>
            <div class="alert alert-warning" role="alert">
              Moodle-Kursliste konnte nicht geladen werden: <?= htmlspecialchars($moodleCourseError, ENT_QUOTES) ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($moodleCourseOptions)): ?>
            <div class="mb-3">
              <label for="moodle-course-suggestions" class="form-label">Vorhandenen Moodle-Kurs auswählen</label>
              <select class="form-select" id="moodle-course-suggestions">
                <option value="">Bitte Kurs auswählen …</option>
                <?php foreach ($moodleCourseOptions as $option): ?>
                  <option value="<?= htmlspecialchars($option['shortname'], ENT_QUOTES) ?>"
                          data-shortname="<?= htmlspecialchars($option['shortname'], ENT_QUOTES) ?>"
                          data-fullname="<?= htmlspecialchars($option['fullname'], ENT_QUOTES) ?>"
                          data-name="<?= htmlspecialchars($option['name'] ?? $option['fullname'] ?? $option['shortname'], ENT_QUOTES) ?>"
                          data-origin="<?= htmlspecialchars($option['origin'] ?? 'local', ENT_QUOTES) ?>"
                          <?php if (!empty($option['id'])): ?>data-course-id="<?= (int) $option['id'] ?>"<?php endif; ?>>
                    <?= htmlspecialchars($option['display'], ENT_QUOTES) ?>
                    <?php if (!empty($option['origin']) && $option['origin'] === 'remote'): ?>
                      (Moodle)
                    <?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Die Felder unten werden automatisch vorausgefüllt.</div>
            </div>
          <?php endif; ?>

          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="moodle-copy" name="moodle_copy" value="1">
            <label class="form-check-label" for="moodle-copy">Bestehenden Moodle-Kurs kopieren</label>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label for="moodle-template" class="form-label">Quellkurs (Shortname)</label>
              <input type="text" class="form-control" id="moodle-template" name="moodle_template_shortname" placeholder="z. B. KURS-VORLAGE">
            </div>
            <div class="col-md-4">
              <label for="moodle-shortname" class="form-label">Neuer Moodle-Shortname</label>
              <input type="text" class="form-control" id="moodle-shortname" name="moodle_new_shortname" placeholder="z. B. KURS-2024">
            </div>
            <div class="col-md-4">
              <label for="moodle-fullname" class="form-label">Neuer Moodle-Name</label>
              <input type="text" class="form-control" id="moodle-fullname" name="moodle_new_fullname" placeholder="z. B. Kurs Wintersemester 2024">
            </div>
          </div>

          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="moodle-visible" name="moodle_visible" value="1" checked>
            <label class="form-check-label" for="moodle-visible">Neuen Moodle-Kurs direkt sichtbar schalten</label>
          </div>

          <p class="small text-muted mb-0">
            Wenn du keinen Shortname angibst, wird beim Moodle-Import keine Kurszuordnung vorgenommen.
          </p>
        </div>
      </div>
    </div>
  </form>
<?php else: ?>
  <div class="alert alert-info mb-4">
    Du kannst vorhandene Kurse einsehen, aber keine neuen Kurse anlegen.
  </div>
<?php endif; ?>

<?php if (current_user_can_manage_courses()): ?>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      'use strict';

      const toggleButton = document.getElementById('moodle-options-toggle');
      const collapseElement = document.getElementById('moodle-options-collapse');
      const suggestionSelect = document.getElementById('moodle-course-suggestions');
      const templateInput = document.getElementById('moodle-template');
      const shortnameInput = document.getElementById('moodle-shortname');
      const fullnameInput = document.getElementById('moodle-fullname');
      const copySwitch = document.getElementById('moodle-copy');

      let collapseInstance = null;
      const collapsedLabel = toggleButton ? toggleButton.querySelector('[data-label-collapsed]') : null;
      const expandedLabel = toggleButton ? toggleButton.querySelector('[data-label-expanded]') : null;

      if (collapseElement && typeof bootstrap !== 'undefined') {
        collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });

        if (toggleButton) {
          collapseElement.addEventListener('shown.bs.collapse', () => {
            if (collapsedLabel) {
              collapsedLabel.classList.add('d-none');
            }
            if (expandedLabel) {
              expandedLabel.classList.remove('d-none');
            }
            toggleButton.classList.remove('collapsed');
            toggleButton.setAttribute('aria-expanded', 'true');
          });

          collapseElement.addEventListener('hidden.bs.collapse', () => {
            if (collapsedLabel) {
              collapsedLabel.classList.remove('d-none');
            }
            if (expandedLabel) {
              expandedLabel.classList.add('d-none');
            }
            toggleButton.classList.add('collapsed');
            toggleButton.setAttribute('aria-expanded', 'false');
          });
        }
      }

      if (suggestionSelect) {
        suggestionSelect.addEventListener('change', () => {
          const selectedOption = suggestionSelect.selectedOptions[0];
          if (!selectedOption || selectedOption.value === '') {
            return;
          }

          const selectedShortname = selectedOption.dataset.shortname ?? '';
          const selectedFullname = selectedOption.dataset.fullname ?? '';
          const selectedName = selectedOption.dataset.name ?? '';

          const incrementValue = (value, type) => {
            if (!value) {
              return '';
            }

            const match = value.match(/(\d+)(?!.*\d)/);
            if (!match) {
              const separator = type === 'shortname' ? '-' : ' ';
              return value + separator + '2';
            }

            const digits = match[0];
            const incremented = String(Number(digits) + 1).padStart(digits.length, '0');
            return value.slice(0, match.index) + incremented + value.slice(match.index + digits.length);
          };

          const applySuggestion = (input, suggestion) => {
            if (!input || suggestion === '') {
              return;
            }

            const previousSuggestion = input.dataset.autofillValue ?? '';
            if (input.value.trim() === '' || input.value === previousSuggestion) {
              input.value = suggestion;
            }
            input.dataset.autofillValue = suggestion;
          };

          if (templateInput && selectedShortname !== '') {
            templateInput.value = selectedShortname;
          }

          if (copySwitch) {
            copySwitch.checked = true;
          }

          if (collapseInstance) {
            collapseInstance.show();
          } else if (collapseElement) {
            collapseElement.classList.add('show');
            if (toggleButton) {
              toggleButton.classList.remove('collapsed');
              toggleButton.setAttribute('aria-expanded', 'true');
            }
            if (collapsedLabel) {
              collapsedLabel.classList.add('d-none');
            }
            if (expandedLabel) {
              expandedLabel.classList.remove('d-none');
            }
          }

          const suggestedShortname = incrementValue(selectedShortname || selectedName, 'shortname');
          const suggestedFullname = incrementValue(selectedFullname || selectedName, 'fullname');

          applySuggestion(shortnameInput, suggestedShortname);
          applySuggestion(fullnameInput, suggestedFullname);
        });
      }
    });
  </script>
<?php endif; ?>

<?= render_template('kurs_table.php', [
    'kurse' => $kurse,
    'message' => $message ?? null,
    'error' => $error ?? null,
]) ?>
