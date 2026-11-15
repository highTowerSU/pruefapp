<?php

declare(strict_types=1);

/** Versioned ladder checklist and canonical interpretation of its observations. */
final class LadderInspectionService
{
    public const CATALOG_CODE = 'ladder-v2';
    public const REFERENCE_URL = 'https://publikationen.dguv.de/widgets/pdf/download/article/450';

    /** @return list<array<string,mixed>> */
    public static function definitions(): array
    {
        $rows = [
            ['identification', 'Zuordnung', 'Ist die Leiter oder der Tritt über die Leiternummer eindeutig zuzuordnen?', 'Kennzeichnung am Arbeitsmittel mit der Gerätenummer im Prüfauftrag abgleichen.', ''],
            ['markings', 'Kennzeichnung', 'Sind Herstellerangaben, zulässige Belastung und Sicherheitshinweise lesbar?', 'Typenschild und angebrachte Benutzungshinweise prüfen.', ''],
            ['rails', 'Holme und Schenkel', 'Sind Holme und Schenkel ohne Risse, Brüche oder bleibende Verformungen?', 'Auch beschädigte Kanten, Korrosion und bei Holz lockere oder gesplitterte Stellen beachten.', ''],
            ['rungs', 'Sprossen und Stufen', 'Sind alle Sprossen oder Stufen fest, unbeschädigt und trittsicher?', 'Auf lose Verbindungen, verdrehte Tritte und glatte oder verschmutzte Trittflächen achten.', ''],
            ['connections', 'Verbindungen', 'Sitzen Schrauben, Nieten und andere tragende Verbindungen fest?', 'Fehlende Befestigungen, ausgeschlagene Verbindungen und unzulässige Reparaturen prüfen.', ''],
            ['feet', 'Standflächen', 'Sind Fußkappen oder rutschhemmende Standflächen vollständig und fest?', 'Verschleiß, Risse und unterschiedliche Auflagehöhen beachten.', ''],
            ['stability', 'Aufstellprobe', 'Steht das Arbeitsmittel bei bestimmungsgemäßem Aufstellen stabil?', 'Auf geeigneter ebener Fläche öffnen bzw. aufstellen und die Funktion ohne gefährliche Belastungsprobe prüfen.', ''],
            ['hinges', 'Gelenke und Verriegelungen', 'Arbeiten Gelenke und Verriegelungen ohne unzulässiges Spiel und rasten sie sicher ein?', 'Bewegung, Befestigung und Verriegelungsfunktion kontrollieren.', 'Bei Arbeitsmitteln mit Gelenken oder Verriegelungen.'],
            ['spreaders', 'Spreizsicherung', 'Begrenzt die Spreizsicherung das Öffnen sicher und sind ihre Befestigungen intakt?', 'Bänder, Ketten oder Gestänge auf Beschädigung und sichere Verbindung prüfen.', 'Bei Bauarten mit Spreizsicherung, insbesondere aufklappbaren Stehleitern.'],
            ['extensions', 'Schiebe- und Steckteile', 'Sitzen Schiebe- oder Steckteile sicher und greifen ihre Halterungen zuverlässig?', 'Haken, Führungen, Anschläge und Sicherungen auf Eingriff, Spiel und Schäden prüfen.', 'Bei Schiebe-, Steck-, Teleskop- und entsprechenden Mehrzweckleitern.'],
            ['rope', 'Seilzug und Umlenkungen', 'Sind Zugseil und Umlenkungen unbeschädigt und lässt sich der Auszug sicher bedienen?', 'Befestigung, Seilverschleiß und Führung kontrollieren.', 'Nur bei einem vorhandenen Seilzug.'],
            ['platform', 'Plattform und Haltevorrichtung', 'Sind Plattform und zugehörige Halte- oder Schutzeinrichtungen fest und funktionsfähig?', 'Auf sichere Arretierung, beschädigte Auflagen und lose Verbindungen achten.', 'Bei Bauarten mit Plattform, Haltebügel oder Geländer.'],
            ['stabilizer', 'Traversen und Abstützungen', 'Sind erforderliche Traversen oder Abstützungen montiert und fest verbunden?', 'Vollständigkeit und Funktion für die vorgesehene Aufstellung prüfen.', 'Wenn die Bauart oder die vorgesehene Verwendung Traversen bzw. Abstützungen erfordert.'],
            ['rollers', 'Rollen und Feststeller', 'Funktionieren Rollen, Absenkmechanik und Feststeller sicher?', 'Sicheren Stand in der vorgesehenen Arbeitsstellung prüfen.', 'Nur bei fahrbaren Leitern oder Tritten mit entsprechenden Einrichtungen.'],
        ];
        $items = [];
        foreach ($rows as $index => [$key, $category, $question, $criterion, $note]) {
            $items[] = ['item_key' => $key, 'category' => $category, 'question' => $question, 'criterion' => $criterion, 'input_type' => 'finding', 'required' => 1, 'sort_order' => ($index + 1) * 10, 'applies_to_json' => json_encode(['allow_na' => $note !== '', 'required_for_kinds' => match ($key) { 'spreaders' => ['stehleiter'], 'extensions' => ['schiebleiter', 'steckleiter', 'teleskopleiter'], 'platform' => ['podestleiter'], default => [] }, 'applicability_note' => $note], JSON_UNESCAPED_UNICODE)];
        }
        return $items;
    }

    /** @param list<array<string,mixed>> $catalog @return list<array<string,mixed>> */
    public static function decorateCatalog(array $catalog, array $attributes = []): array
    {
        foreach ($catalog as &$item) {
            $rules = json_decode((string) ($item['applies_to_json'] ?? '{}'), true) ?: [];
            $requiredByKind = in_array((string) ($attributes['ladder_kind'] ?? ''), $rules['required_for_kinds'] ?? [], true);
            $item['allow_na'] = !$requiredByKind && (bool) ($rules['allow_na'] ?? in_array($item['item_key'], ['spreaders', 'extensions'], true));
            $item['applicability_note'] = (string) ($rules['applicability_note'] ?? ($item['allow_na'] ? 'Nur bei entsprechender Bauart oder vorhandenen Bauteilen.' : ''));
        }
        unset($item);
        return $catalog;
    }

    /** @param list<array<string,mixed>> $catalog @return list<array<string,mixed>> */
    public static function answers(array $catalog, array $values, array $remarks, array $attributes = []): array
    {
        $answers = [];
        foreach (self::decorateCatalog($catalog, $attributes) as $item) {
            $key = (string) $item['item_key'];
            $value = is_string($values[$key] ?? null) ? strtolower(trim($values[$key])) : '';
            $remark = is_string($remarks[$key] ?? null) ? trim($remarks[$key]) : '';
            $outcome = match ($value) {
                'ok', 'green' => 'passed',
                'orange', 'red' => 'failed',
                'na' => $item['allow_na'] && $remark !== '' ? 'skipped' : 'missing',
                default => 'missing',
            };
            $answers[] = ['item_key' => $key, 'category' => (string) $item['category'], 'question_snapshot' => (string) $item['question'], 'criterion_snapshot' => (string) $item['criterion'], 'answer_value' => $value, 'outcome' => $outcome, 'required' => (int) $item['required'], 'sort_order' => (int) $item['sort_order'], 'severity' => in_array($value, ['green', 'orange', 'red'], true) ? $value : '', 'remark' => $remark, 'skip_reason' => $outcome === 'skipped' ? $remark : ''];
        }
        return $answers;
    }

    /** @param list<array<string,mixed>> $answers @return list<string> */
    public static function missingDescriptions(array $answers): array
    {
        $missing = [];
        foreach ($answers as $answer) {
            if (($answer['severity'] ?? '') !== '' && ($answer['remark'] ?? '') === '') {
                $missing[] = $answer['category'] . ': Hinweis oder Mangel beschreiben.';
            }
        }
        return $missing;
    }
}
