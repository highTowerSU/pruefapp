<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4">Neuen Übermittlungslink anlegen</h2>
        <p>Erzeuge individuelle Links für unterschiedliche Auftraggeber oder Firmen. Jeder Link kann nach der Nutzung automatisch deaktiviert werden.</p>

        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="create">
            <div class="col-md-6">
                <label class="form-label" for="neuer-link-name">Bezeichnung / Auftraggeber (optional)</label>
                <input type="text" class="form-control" id="neuer-link-name" name="name" placeholder="z. B. Firma Müller GmbH">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">Link erstellen</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h4">Bestehende Übermittlungslinks</h2>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th style="min-width: 200px;">Bezeichnung</th>
                    <th>Link</th>
                    <th>Status</th>
                    <th class="text-end">Aktionen</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($links as $link): ?>
                    <?php $linkUrl = absolute_url_for('uebermitteln/' . $link->token); ?>
                    <tr>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="link_id" value="<?= (int) $link->id ?>">
                                <input type="text"
                                       name="name"
                                       class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($link->bezeichnung ?? '') ?>"
                                       placeholder="Bezeichnung">
                                <button class="btn btn-outline-secondary btn-sm">Speichern</button>
                            </form>
                        </td>
                        <td>
                            <div class="copy-link-container"
                                 data-link-container
                                 data-link-url="<?= htmlspecialchars($linkUrl, ENT_QUOTES) ?>">
                                <div class="input-group input-group-sm flex-nowrap">
                                    <input type="text"
                                           class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($linkUrl) ?>"
                                           readonly
                                           data-copy-link-input
                                           title="Link kopieren"
                                           style="cursor: pointer;">
                                    <button class="btn btn-outline-secondary"
                                            type="button"
                                            data-open-link
                                            title="In neuem Tab öffnen">
                                        <span class="visually-hidden">In neuem Tab öffnen</span>
                                        <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="form-text small text-success d-none" data-copy-feedback>Link kopiert.</div>
                            </div>
                        </td>
                        <td>
                            <?php if ($link->aktiv): ?>
                                <span class="badge bg-success">aktiv</span>
                            <?php else: ?>
                                <span class="badge bg-danger">deaktiviert</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end flex-wrap gap-2">
                                <form method="post">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="link_id" value="<?= (int) $link->id ?>">
                                    <button class="btn btn-outline-primary btn-sm">
                                        <?php if ($link->aktiv): ?>
                                            Deaktivieren
                                        <?php else: ?>
                                            Aktivieren
                                        <?php endif; ?>
                                    </button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="action" value="regenerate">
                                    <input type="hidden" name="link_id" value="<?= (int) $link->id ?>">
                                    <button class="btn btn-warning btn-sm">Link zurücksetzen</button>
                                </form>
                                <form method="post"
                                      onsubmit="return confirm('Möchten Sie diesen Übermittlungslink wirklich löschen?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="link_id" value="<?= (int) $link->id ?>">
                                    <button class="btn btn-outline-danger btn-sm">Löschen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="<?= htmlspecialchars(url_for('kurse'), ENT_QUOTES) ?>" class="btn btn-link">Zurück zur Kursübersicht</a>
    </div>
</div>

<script>
    (() => {
        'use strict';

        const feedbackTimeouts = new WeakMap();
        const feedbackDuration = 2500;

        const showFeedback = (container, message, isError = false) => {
            const feedback = container.querySelector('[data-copy-feedback]');
            if (!feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.classList.remove('text-success', 'text-danger');
            feedback.classList.add(isError ? 'text-danger' : 'text-success');
            feedback.classList.remove('d-none');

            if (feedbackTimeouts.has(container)) {
                clearTimeout(feedbackTimeouts.get(container));
                feedbackTimeouts.delete(container);
            }

            const timeoutId = window.setTimeout(() => {
                feedback.classList.add('d-none');
                feedbackTimeouts.delete(container);
            }, feedbackDuration);

            feedbackTimeouts.set(container, timeoutId);
        };

        const fallbackCopy = (text, container) => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                const successful = document.execCommand('copy');
                showFeedback(container, successful ? 'Link kopiert.' : 'Kopieren nicht möglich.', !successful);
            } catch (error) {
                showFeedback(container, 'Kopieren nicht möglich.', true);
            } finally {
                document.body.removeChild(textarea);
            }
        };

        const copyLink = (container) => {
            const url = container.dataset.linkUrl;
            if (!url) {
                return;
            }

            const input = container.querySelector('[data-copy-link-input]');
            if (input) {
                input.focus();
                input.select();
            }

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(url)
                    .then(() => showFeedback(container, 'Link kopiert.'))
                    .catch(() => fallbackCopy(url, container));
            } else {
                fallbackCopy(url, container);
            }
        };

        document.addEventListener('click', (event) => {
            const copyInput = event.target.closest('[data-copy-link-input]');
            if (copyInput) {
                event.preventDefault();
                const container = copyInput.closest('[data-link-container]');
                if (container) {
                    copyLink(container);
                }
                return;
            }

            const openButton = event.target.closest('[data-open-link]');
            if (openButton) {
                event.preventDefault();
                const container = openButton.closest('[data-link-container]');
                const url = container ? container.dataset.linkUrl : null;

                if (url) {
                    const newWindow = window.open(url, '_blank');
                    if (newWindow) {
                        newWindow.opener = null;
                    }
                }
            }
        });
    })();
</script>
