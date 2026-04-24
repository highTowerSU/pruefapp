<?php
/** @var \RedBeanPHP\OODBBean $kurs */
/** @var array $headers */
/** @var array $previewRows */
/** @var int $rowCount */
/** @var array $fieldLabels */
/** @var array $initialMapping */
/** @var string $rowsPayload */
/** @var string $headerPayload */
?>

<h4>Spalten zuordnen</h4>
<p class="text-muted">
    Wir haben <?= count($headers) ?> Spalten in der CSV-Datei gefunden. Ordnen Sie die gewünschten Spalten den Import-Feldern zu.
    Nicht benötigte Spalten können ignoriert werden.
</p>

<form method="post" class="mb-4">
    <input type="hidden" name="rows_payload" value="<?= htmlspecialchars($rowsPayload, ENT_QUOTES) ?>">
    <input type="hidden" name="header_payload" value="<?= htmlspecialchars($headerPayload, ENT_QUOTES) ?>">

    <?php foreach ($fieldLabels as $fieldKey => $label): ?>
        <div class="mb-3">
            <label class="form-label" for="mapping-<?= htmlspecialchars($fieldKey) ?>"><?= htmlspecialchars($label) ?></label>
            <select
                id="mapping-<?= htmlspecialchars($fieldKey) ?>"
                name="mapping[<?= htmlspecialchars($fieldKey) ?>]"
                class="form-select"
            >
                <option value="">Ignorieren</option>
                <?php foreach ($headers as $header): ?>
                    <?php $selected = ($initialMapping[$fieldKey] ?? '') === $header ? ' selected' : ''; ?>
                    <option value="<?= htmlspecialchars($header, ENT_QUOTES) ?>"<?= $selected ?>>
                        <?= htmlspecialchars($header) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endforeach; ?>

    <button class="btn btn-primary" name="mapping_submitted" value="1">Import starten</button>
    <a href="<?= htmlspecialchars(url_for('kurse/' . (int) $kurs->id . '/teilnehmer/import'), ENT_QUOTES) ?>" class="btn btn-link">Zurück</a>
</form>

<?php if ($rowCount > 0): ?>
    <h5>Vorschau (<?= $rowCount ?> Datensatz<?= $rowCount === 1 ? '' : 'e' ?>)</h5>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($previewRows as $row): ?>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <td><?= htmlspecialchars((string) ($row[$header] ?? '')) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rowCount > count($previewRows)): ?>
                    <tr>
                        <td colspan="<?= count($headers) ?>" class="text-muted text-center">
                            … weitere <?= $rowCount - count($previewRows) ?> Zeil<?= ($rowCount - count($previewRows)) === 1 ? 'e' : 'en' ?> …
                        </td>
                    </tr>
                <?php elseif (empty($previewRows)): ?>
                    <tr>
                        <td colspan="<?= count($headers) ?>" class="text-muted text-center">Keine Datenzeilen gefunden.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
