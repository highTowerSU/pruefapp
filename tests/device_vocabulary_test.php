<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/DeviceVocabularyService.php';

foreach (['n.e.', 'NE', 'n. e.'] as $value) {
    if (DeviceVocabularyService::canonicalize('manufacturer', $value) !== DeviceVocabularyService::NOT_RECOGNIZABLE
        || DeviceVocabularyService::canonicalize('device_model', $value) !== DeviceVocabularyService::NOT_RECOGNIZABLE
        || DeviceVocabularyService::canonicalize('name', $value) !== DeviceVocabularyService::NOT_RECOGNIZABLE
    ) {
        throw new RuntimeException('Nicht-erkennbar-Varianten werden nicht für alle Stammdatenfelder vereinheitlicht.');
    }
}

if (DeviceVocabularyService::normalizeKey('  Epson  EH-2230 ') !== 'epson eh-2230') {
    throw new RuntimeException('Stammdaten-Schlüssel normalisieren Leerraum nicht stabil.');
}

echo "PASS: Geräte-Stammdaten werden zentral normalisiert\n";
