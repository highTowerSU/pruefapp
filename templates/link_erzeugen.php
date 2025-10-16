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
                            <code class="d-block text-wrap" style="word-break: break-all;">
                                <?= htmlspecialchars($linkUrl) ?>
                            </code>
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
