<?php

declare(strict_types=1);
?>
<?php foreach (($sources ?? []) as $source): ?>
    <?php
    $candidateRaw = json_decode((string) ($source['raw_json'] ?? '{}'), true) ?: [];
    $odsValues = $candidateRaw['raw']['ods']['values'] ?? [];
    $displayRaw = $candidateRaw;
    if (is_array($displayRaw['raw']['ods']['values'] ?? null)) {
        $displayRaw['raw']['ods']['values'] = array_filter(
            $displayRaw['raw']['ods']['values'],
            static fn(mixed $value): bool => trim((string) $value) !== ''
        );
    }
    ?>
    <div class="small fw-semibold font-monospace">
        <?= htmlspecialchars((string) ($source['source_path'] ?? '')) ?>
        <?= (int) ($source['source_row_no'] ?? 0) > 0 ? ':' . (int) $source['source_row_no'] : '' ?>
    </div>
    <?php if (is_array($odsValues) && $odsValues !== []): ?>
        <div class="small text-body-secondary mt-2"><i class="fa-solid fa-table-cells me-1" aria-hidden="true"></i>Zuordnung aus ODS: <?= htmlspecialchars((string) ($candidateRaw['raw']['ods']['path'] ?? '')) ?></div>
        <div class="table-responsive mb-2"><table class="table table-sm table-striped mb-0"><tbody>
            <?php foreach ($odsValues as $label => $value): ?>
                <?php if (trim((string) $value) === '') continue; ?>
                <tr><th scope="row" class="text-nowrap"><?= htmlspecialchars((string) $label) ?></th><td><?= htmlspecialchars((string) $value) ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
    <pre class="small border rounded bg-body-tertiary p-2 text-break text-wrap"><?= htmlspecialchars((string) json_encode($displayRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
<?php endforeach; ?>
