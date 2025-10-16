<div class="card shadow-sm">
    <div class="card-body">
        <p>Gib diesen Link an deine Kunden weiter:</p>
        <div class="mb-3"><code><?= htmlspecialchars($link) ?></code></div>

        <p>Status:
            <?php if ($kurs->uebermittlung_aktiv): ?>
                <span class="badge bg-success">Link aktiv</span>
            <?php else: ?>
                <span class="badge bg-danger">Link deaktiviert</span>
            <?php endif; ?>
        </p>

        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="toggle">
            <button class="btn btn-outline-primary">
                <?php if ($kurs->uebermittlung_aktiv): ?>
                    Link deaktivieren
                <?php else: ?>
                    Link aktivieren
                <?php endif; ?>
            </button>
        </form>

        <form method="post" class="d-inline ms-2">
            <input type="hidden" name="action" value="regenerate">
            <button class="btn btn-warning">Neuen Link erzeugen</button>
        </form>

        <a href="/kurse" class="btn btn-link ms-2">Zurück</a>
    </div>
</div>
