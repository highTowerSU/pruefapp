<style>
    .pw-char { font-family: monospace; font-size: 1.2rem; text-align: center; }
    .letter { color: black; }
    .digit  { color: red; }
    .symbol { color: green; }
    @media print {
        .noprint { display: none; }
        .break-page { break-after: page; }
        .break-page:last-of-type { break-after: auto; }
    }
</style>

<div class="mb-3 noprint">
    <button onclick="window.print()" class="btn btn-primary">Drucken</button>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer'), ENT_QUOTES) ?>" class="btn btn-link">Zurück</a>
</div>

<?php if (empty($nutzer)): ?>
    <div class="alert alert-warning">Keine Teilnehmer.</div>
<?php else: ?>
    <?php $index = 1; foreach (array_chunk($nutzer, 3) as $gruppe): ?>
        <div class="mb-4 break-page">
            <h4 class="text-center mb-3"><?= htmlspecialchars($kurs->name ?? '') ?></h4>
            <div class="row g-4">
                <?php foreach ($gruppe as $n): ?>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">Benutzer <?= $index++ ?></h5>
                                <table class="table table-bordered table-sm">
                                    <tr><th>Vorname</th><td><?= htmlspecialchars($n->vorname) ?></td></tr>
                                    <tr><th>Nachname</th><td><?= htmlspecialchars($n->nachname) ?></td></tr>
                                    <tr><th>Geburtsdatum</th><td><?= htmlspecialchars(format_birthdate_for_display((string) $n->geburtsdatum)) ?></td></tr>
                                    <tr><th>Geburtsort</th><td><?= htmlspecialchars($n->geburtsort) ?></td></tr>
                                    <tr><th>Benutzername</th><td><?= htmlspecialchars($n->benutzername) ?></td></tr>
                                    <tr><th>Passwort</th><td>
                                        <?php foreach (mb_str_split($n->passwort) as $c): ?>
                                            <?php $cls = ctype_digit($c) ? 'digit' : (ctype_alpha($c) ? 'letter' : 'symbol'); ?>
                                            <span class="pw-char <?= $cls ?>"><?= htmlspecialchars($c) ?></span>
                                        <?php endforeach; ?>
                                    </td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
