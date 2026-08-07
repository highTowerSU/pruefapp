<?php

declare(strict_types=1);

use RedBeanPHP\R;

require_once dirname(__DIR__) . '/lib/InspectionReportWriter.php';

final class ReportController
{
    public static function export(array $params, bool $isHx): array
    {
        if (!current_user()) return [401, [], 'Nicht angemeldet'];
        $format = strtolower(trim((string) ($_POST['format'] ?? 'csv')));
        $reportType = (string) ($_POST['report'] ?? '');
        $report = $reportType === 'rooms';
        $scope = trim((string) ($_POST['scope'] ?? 'selection'));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['device_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ($scope === 'page') $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['page_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ($scope === 'all') $ids = self::filteredIds((string) ($_POST['filter_query'] ?? ''));
        if ($format === 'bundle_pdf') return self::queuePdfBundle($ids, (string) ($_POST['filter_query'] ?? ''), (int) ($_POST['invoice_id'] ?? 0), max(0, (int) ($_POST['bundle_max_pages'] ?? 500)));
        if (in_array($format, ['zip_latest', 'zip_all'], true)) return self::queuePdfZip($format === 'zip_all', $ids, (string) ($_POST['filter_query'] ?? ''), isset($_POST['zip_index_csv']), isset($_POST['zip_index_pdf']), isset($_POST['zip_index_ods']));
        if ($ids === []) return [422, [], 'Bitte mindestens ein Gerät auswählen.'];
        $devices = self::devices($ids);
        if ($devices === []) return [422, [], 'Keine Datensätze für den gewählten Filter gefunden. Bitte Filter und Auswahl prüfen.'];
        if ($reportType === 'daily' || $reportType === 'weekly') {
            $date = trim((string) ($_POST['daily_date'] ?? '')); $toDate = '';
            if ($reportType === 'weekly' && $date !== '') { $dateObject = new DateTimeImmutable($date); $date = $dateObject->modify('monday this week')->format('Y-m-d'); $toDate = $dateObject->modify('sunday this week')->format('Y-m-d'); }
            $rows = self::dailyRows($ids, $date, trim((string) ($_POST['daily_examiner'] ?? '')), 0, $toDate);
        }
        elseif ($report) $rows = self::roomRows($devices);
        else $rows = self::deviceRows($devices);
        // Tabellarische Exporte bleiben übersichtlich: redundante
        // Hierarchieebenen werden nur ausgeblendet, wenn sie im aktuellen
        // Ergebnis keinen zusätzlichen Informationswert liefern.
        $rows = self::compactStructureColumns($rows);
        $name = $reportType === 'daily' ? 'Tagesreport' : ($reportType === 'weekly' ? 'Wochenreport' : ($report ? 'Raum-Ampelreport' : 'Geräteexport'));
        if ($format === 'json') {
            $full = [];
            foreach ($devices as $device) {
                $deviceData = $device;
                $deviceData['inspections'] = [];
                foreach (R::findAll('inspection', ' device_id = ? ORDER BY test_date DESC, id DESC ', [(int) $device['id']]) as $inspection) {
                    $inspectionData = $inspection->export();
                    foreach (['measurements_json' => 'measurements', 'checklist_json' => 'checklist', 'raw_json' => 'raw'] as $source => $target) {
                        $decoded = json_decode((string) ($inspectionData[$source] ?? ''), true);
                        $inspectionData[$target] = is_array($decoded) ? $decoded : [];
                        unset($inspectionData[$source]);
                    }
                    $deviceData['inspections'][] = $inspectionData;
                }
                $full[] = $deviceData;
            }
            return [200, ['Content-Type' => 'application/json; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $name . '.json"'], json_encode(['title' => $name, 'generated_at' => date(DATE_ATOM), 'rows' => $rows, 'devices' => $full], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE)];
        }
        $branding = function_exists('get_branding') ? get_branding() : [];
        if ($format === 'ods') return self::ods($rows, $name, $branding);
        if ($format === 'xlsx') return self::xlsx($rows, $name, $branding);
        if ($format === 'pdf') return self::pdf($rows, $name, $branding);
        return self::csv($rows, $reportType === 'daily' ? 'tagesreport.csv' : ($reportType === 'weekly' ? 'wochenreport.csv' : ($report ? 'raum-ampelreport.csv' : 'geraete-export.csv')));
    }

    private static function queuePdfZip(bool $allReports, array $ids, string $filterQuery, bool $indexCsv, bool $indexPdf, bool $indexOds): array
    {
        if ($ids === []) $ids = self::filteredIds($filterQuery);
        if ($ids === []) return [422, [], 'Bitte mindestens ein Gerät auswählen.'];
        $user = current_user(); $ownerId = (int) ($user->id ?? 0);
        $payload = ['type' => 'pdf_zip', 'device_ids' => $ids, 'all_reports' => $allReports, 'index_csv' => $indexCsv, 'index_pdf' => $indexPdf, 'index_ods' => $indexOds, 'owner_user_id' => $ownerId];
        $job = BackgroundJobService::enqueue('pdf_zip', $payload, ['owner_user_id' => $ownerId, 'total' => count($ids), 'message' => 'Der ZIP-Export wird im Hintergrund vorbereitet.']);
        $id = (string) ($job['id'] ?? '');
        return [303, ['Location' => url_for('geraete?zip_job=' . $id)], ''];
    }

    private static function queuePdfBundle(array $ids, string $filterQuery, int $invoiceId, int $maxPages): array
    {
        if ($invoiceId > 0) $ids = array_map('intval', R::getCol('SELECT DISTINCT device_id FROM billing_invoice_item WHERE invoice_id = ?', [$invoiceId]));
        if ($ids === []) $ids = self::filteredIds($filterQuery);
        if ($ids === []) return [422, [], 'Keine Geräte für die Sammel-PDF gefunden.'];
        $ownerId = (int) (current_user()->id ?? 0);
        $job = BackgroundJobService::enqueue('pdf_bundle', ['type' => 'pdf_bundle', 'device_ids' => $ids, 'invoice_id' => $invoiceId, 'max_pages' => max(10, min(5000, $maxPages ?: 500)), 'owner_user_id' => $ownerId], ['owner_user_id' => $ownerId, 'total' => count($ids), 'message' => 'Das Sammel-PDF wird im Hintergrund vorbereitet.']);
        $id = (string) ($job['id'] ?? '');
        return [303, ['Location' => url_for('geraete?zip_job=' . $id)], ''];
    }

    public static function renderOds(array $rows, string $title, ?array $branding = null): string { return (string) (self::ods($rows, $title, $branding ?? (function_exists('get_branding') ? get_branding() : []))[2] ?? ''); }
    public static function renderPdf(array $rows, string $title, ?array $branding = null): string { return (string) (self::pdf($rows, $title, $branding ?? (function_exists('get_report_branding') ? get_report_branding() : (function_exists('get_branding') ? get_branding() : [])))[2] ?? ''); }
    public static function inspectionPdfRows(\RedBeanPHP\OODBBean $inspection, \RedBeanPHP\OODBBean $device): array
    {
        $canonicalMeasurements = InspectionDataService::measurements((int) $inspection->id);
        $canonicalAnswers = InspectionDataService::answers((int) $inspection->id);
        $measurements = $canonicalMeasurements !== [] ? array_map(static function (array $measurement): array {
            return [
                'name' => (string) ($measurement['name_snapshot'] ?: $measurement['measurement_key']),
                'value' => (string) ($measurement['text_value'] !== '' ? $measurement['text_value'] : $measurement['numeric_value']),
                'unit' => (string) $measurement['unit'],
                'result' => InspectionEvaluationService::presentation((string) $measurement['outcome'])['label'],
                'limit' => $measurement['limit_value'] !== null ? (string) $measurement['limit_value'] . ' ' . (string) $measurement['limit_unit'] : '',
                'voltage' => (string) $measurement['voltage'],
            ];
        }, $canonicalMeasurements) : (json_decode((string) ($inspection->measurements_json ?? ''), true) ?: []);
        $checklist = $canonicalAnswers !== [] ? array_map(static function (array $answer): array {
            return [
                'category' => (string) $answer['category'],
                'step' => (string) $answer['question_snapshot'],
                'criterion' => (string) $answer['criterion_snapshot'],
                'result' => (string) ($answer['skip_reason'] !== '' ? $answer['skip_reason'] : $answer['answer_value']),
                'check' => (string) $answer['outcome'] === 'passed' ? true : ((string) $answer['outcome'] === 'failed' ? false : null),
            ];
        }, $canonicalAnswers) : (json_decode((string) ($inspection->checklist_json ?? ''), true) ?: []);
        $raw = json_decode((string) ($inspection->raw_json ?? ''), true) ?: [];
        $room = (int) ($device->room_id ?? 0) > 0 ? R::load('room', (int) $device->room_id) : null;
        $floor = $room && $room->id ? R::load('floor', (int) $room->floor_id) : null;
        $building = $floor && $floor->id ? R::load('building', (int) $floor->building_id) : null;
        $site = $building && $building->id ? R::load('site', (int) $building->site_id) : null;
        $customer = $site && $site->id ? R::load('customer', (int) $site->customer_id) : null;
        $rows = [['Prüfung', 'Wert']];
        $scalar = static function ($value): string {
            if (is_scalar($value)) return (string) $value;
            if (is_array($value)) return trim((string) ($value['name'] ?? $value['email'] ?? $value['value'] ?? ''));
            return '';
        };
        $examinerValue = $scalar($inspection->examiner ?: ($raw['created_by'] ?? ''));
        $resultLabel = InspectionEvaluationService::presentation((string) $inspection->result_status, (string) $inspection->status)['label'];
        foreach ([['Prüfnummer', $inspection->external_number], ['Datum', $inspection->test_date], ['Prüfart', InspectionEvaluationService::canonicalInspectionType((string) ($inspection->inspection_type ?: ($raw['type'] ?? '')), (string) $inspection->protection_class)], ['Prüfer', display_examiner_name($examinerValue)], ['Gerät', $scalar($device->external_number) . ' · ' . $scalar($device->name)], ['Inventarnummer', $device->inventory_number], ['Geräteart', $device->name], ['Hersteller', $device->manufacturer], ['Typ', $device->device_model], ['Schutzklasse', $inspection->protection_class], ['Kabellänge', $inspection->cable_length_m !== null && $inspection->cable_length_m !== '' ? (string) $inspection->cable_length_m . ' m' : ''], ['Wärmegerät', !empty($inspection->warming_device_snapshot ?? $device->warming_device) ? 'Ja' : 'Nein'], ['Auftraggeber', $customer ? $customer->name : ''], ['Liegenschaft', $site ? $site->name : ''], ['Gebäude', $building ? $building->name : ''], ['Etage', $floor ? $floor->name : ''], ['Raum-Nr.', $device->room_snapshot ?: ($room ? $room->number : '')], ['Ergebnis', $resultLabel], ['Ergebnisbegründung', $inspection->result_reason_text], ['Nächste Prüfung', $inspection->next_due_date], ['Regiezeit', ((int) ($inspection->regie_minutes ?? 0)) . ' Minuten'], ['Regiebegründung', $inspection->regie_reason]] as [$label, $value]) $rows[] = [(string) $label, $scalar($value)];
        foreach ($measurements as $measurement) if (is_array($measurement)) $rows[] = [(string) ($measurement['name'] ?? 'Messung'), trim((string) ($measurement['value'] ?? '') . ' ' . (string) ($measurement['unit'] ?? '') . ' · ' . (string) ($measurement['result'] ?? ''))];
        if ($measurements !== []) $rows[] = ['__measurements_json', json_encode($measurements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        if ($checklist !== []) $rows[] = ['__checklist_json', json_encode($checklist, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        if ($raw !== []) $rows[] = ['__raw_json', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        $examiner = $scalar($inspection->examiner ?: ($raw['created_by'] ?? ''));
        if (function_exists('examiner_signature_data_uri')) {
            $profileSignature = examiner_signature_data_uri($examiner);
            if ($profileSignature !== '') $rows[] = ['__profile_signature', $profileSignature];
        }
        if (function_exists('absolute_url_for')) {
            $rows[] = ['__inspection_url', absolute_url_for('pruefungen/' . (int) $inspection->id)];
            $rows[] = ['__device_url', absolute_url_for('geraete?device_id=' . (int) $device->id . '#geraet-' . (int) $device->id)];
        }
        $rows[] = ['Prüfungsabschluss', (string) ($raw['end_time'] ?? $inspection->updated_at ?? $inspection->test_date ?? '')];
        if ($checklist !== []) {
            $rows[] = ['Prüfschritte', ''];
            foreach ($checklist as $key => $step) {
                if (is_array($step)) $rows[] = [(string) ($step['step'] ?? $step['label'] ?? ('Prüfschritt ' . ((int) $key + 1))), trim((string) ($step['result'] ?? '') . ((string) ($step['criterion'] ?? '') !== '' ? ' · ' . $step['criterion'] : ''))];
                else $rows[] = ['Prüfschritt ' . ((int) $key + 1), (string) $step];
            }
        } elseif ($raw !== []) {
            $phoenixSteps = [];
            foreach ($raw as $key => $value) if (preg_match('/^step(\d+)$/', (string) $key, $match)) $phoenixSteps[(int) $match[1]] = [(string) $value, (string) ($raw['result' . $match[1]] ?? ''), (string) ($raw['criterion' . $match[1]] ?? '')];
            if ($phoenixSteps !== []) { $rows[] = ['Prüfschritte', '']; foreach ($phoenixSteps as [$step, $result, $criterion]) $rows[] = [$step, trim($result . ($criterion !== '' ? ' · ' . $criterion : ''))]; }
        }
        return $rows;
    }

    public static function zipStatus(array $params, bool $isHx): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params['id'] ?? ''));
        $status = BackgroundJobService::find($id) ?? ['state' => 'error', 'error' => 'Aufgabe nicht gefunden'];
        $user = current_user(); $allowed = current_user_is_superadmin() || current_user_has_role('admin') || ((int) ($status['owner_user_id'] ?? 0) > 0 && (int) ($status['owner_user_id'] ?? 0) === (int) ($user->id ?? 0));
        if (!$allowed) return [403, ['Content-Type' => 'application/json'], '{}'];
        $status['can_cancel'] = in_array((string) ($status['state'] ?? ''), ['queued', 'running'], true);
        return [200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode($status, JSON_UNESCAPED_UNICODE)];
    }

    public static function cancelPdfJob(array $params, bool $isHx): array
    {
        $result = self::cancelBackgroundJob($params);
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params['id'] ?? ''));
        return $result[0] >= 400 ? $result : [303, ['Location' => url_for('geraete?zip_job=' . $id)], ''];
    }

    public static function cancelCronJob(array $params, bool $isHx): array
    {
        $result = self::cancelBackgroundJob($params);
        if ($result[0] >= 400) return $result;
        return $isHx ? [200, ['HX-Trigger' => 'audit-tasks-refresh'], ''] : [303, ['Location' => url_for('admin/audit-log')], ''];
    }

    private static function cancelBackgroundJob(array $params): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params['id'] ?? ''));
        $status = BackgroundJobService::find($id); $user = current_user();
        if ($status === null) return [404, [], 'Hintergrundaufgabe nicht gefunden.'];
        if (!current_user_is_superadmin() && !current_user_has_role('admin') && (int) ($status['owner_user_id'] ?? 0) !== (int) ($user->id ?? 0)) return forbidden_response();
        $type = (string) ($status['type'] ?? 'background');
        if (empty($status['cancellable'])) return [409, [], 'Diese Aufgabe kann nicht abgebrochen werden.'];
        if (!BackgroundJobService::requestCancellation((string) $status['id'])) return [409, [], 'Die Aufgabe ist bereits beendet.'];
        return [200, [], ''];
    }

    public static function zipDownload(array $params, bool $isHx): array
    {
        $id = preg_replace('/[^a-f0-9]/', '', (string) ($params['id'] ?? ''));
        $status = BackgroundJobService::find($id) ?? [];
        $user = current_user();
        if (!current_user_is_superadmin() && !current_user_has_role('admin') && (int) ($status['owner_user_id'] ?? 0) !== (int) ($user->id ?? 0)) return forbidden_response();
        $file = (string) ($status['output'] ?? '');
        if (($status['state'] ?? '') !== 'done' || $file === '' || !is_file($file)) return [404, [], 'Der Export ist noch nicht verfügbar.'];
        BackgroundJobService::markRead($id, (int) ($user->id ?? 0));
        $isPdf = strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'pdf';
        $mime = $isPdf ? 'application/pdf' : 'application/zip';
        $filename = basename($file);
        // The legacy array router expects a string body. Stream here directly
        // so large ZIP/PDF exports never get copied into PHP memory.
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($file));
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, no-store');
        }
        while (ob_get_level() > 0) ob_end_clean();
        readfile($file);
        exit;
    }

    private static function filteredIds(string $query): array
    {
        parse_str(ltrim($query, '?'), $q);
        $where = [];
        $args = [];
        $join = ' LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id ';
        if (!current_user_has_role('admin')) {
            $allowed = current_user_customer_ids();
            if ($allowed === []) return [];
            $where[] = 'c.id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
            array_push($args, ...$allowed);
        }
        foreach (['customer_id' => 'c.id', 'site_id' => 's.id', 'building_id' => 'b.id', 'floor_id' => 'f.id', 'room_id' => 'r.id'] as $key => $column) {
            $value = (int) ($q[$key] ?? 0);
            if ($value > 0) { $where[] = $column . ' = ?'; $args[] = $value; }
        }
        $term = trim((string) ($q['q'] ?? ''));
        if ($term !== '') { $where[] = '(LOWER(d.name) LIKE ? OR LOWER(d.external_number) LIKE ? OR LOWER(d.manufacturer) LIKE ? OR LOWER(d.description) LIKE ?)'; $like = '%' . strtolower($term) . '%'; array_push($args, $like, $like, $like, $like); }
        $year = trim((string) ($q['year'] ?? ''));
        if (preg_match('/^\d{4}$/', $year)) { $where[] = 'EXISTS (SELECT 1 FROM inspection iy WHERE iy.device_id=d.id AND iy.test_date >= ? AND iy.test_date < ?)'; $args[] = $year . '-01-01'; $args[] = ((int) $year + 1) . '-01-01'; }
        if (trim((string) ($q['from'] ?? '')) !== '') { $where[] = 'EXISTS (SELECT 1 FROM inspection ifr WHERE ifr.device_id=d.id AND ifr.test_date >= ?)'; $args[] = trim((string) $q['from']); }
        if (trim((string) ($q['to'] ?? '')) !== '') { $where[] = 'EXISTS (SELECT 1 FROM inspection ito WHERE ito.device_id=d.id AND ito.test_date <= ?)'; $args[] = trim((string) $q['to']); }
        $inspectionStatus = trim((string) ($q['inspection_status'] ?? ''));
        $latestStatus = '(SELECT ' . InspectionEvaluationService::sqlStatusExpression('i2') . ' FROM inspection i2 WHERE i2.device_id=d.id ORDER BY i2.test_date DESC, i2.id DESC LIMIT 1)';
        if (in_array($inspectionStatus, ['failed', 'passed', 'in_progress', 'data_missing', 'legacy'], true)) {
            $where[] = $latestStatus . ' = ?';
            $args[] = $inspectionStatus;
        } elseif ($inspectionStatus === 'pending') $where[] = $latestStatus . " IN ('in_progress','data_missing')";
        elseif ($inspectionStatus === 'completed') $where[] = $latestStatus . " IN ('passed','failed')";
        $examiner = trim((string) ($q['examiner'] ?? ''));
        if ($examiner !== '') {
            $latestExaminer = InspectionFilterService::latestValueExpression('examiner');
            $where[] = "LOWER(TRIM(COALESCE({$latestExaminer}, ''))) = LOWER(?)";
            $args[] = $examiner;
        }
        $dueCondition = InspectionFilterService::dueCondition(
            trim((string) ($q['due_status'] ?? '')),
            InspectionFilterService::latestValueExpression('next_due_date')
        );
        if ($dueCondition['sql'] !== '') {
            $where[] = '(' . $dueCondition['sql'] . ')';
            array_push($args, ...$dueCondition['params']);
        }
        $where[] = "(d.archived_at IS NULL OR TRIM(d.archived_at) = '')";
        $sql = 'SELECT DISTINCT d.id FROM device d' . $join . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
        return array_map('intval', R::getCol($sql, $args));
    }

    private static function devices(array $ids): array
    {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = R::getAll("SELECT d.*, r.name AS room_name, r.number AS room_number, a.name AS area_name, f.name AS floor_name, b.name AS building_name, s.name AS site_name, c.name AS customer_name FROM device d LEFT JOIN room r ON r.id=d.room_id LEFT JOIN area a ON a.id=r.area_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE d.id IN ($marks) ORDER BY CASE WHEN d.external_number GLOB '[0-9]*' THEN 0 ELSE 1 END, CAST(d.external_number AS INTEGER), LOWER(d.external_number), d.id", $ids);
        $result = [];
        foreach ($rows as $row) {
            $latest = R::getRow('SELECT external_number AS inspection_number, test_date, next_due_date, result_status FROM inspection WHERE device_id = ? ORDER BY test_date DESC, id DESC LIMIT 1', [(int) $row['id']]);
            $row['inspection_number'] = $latest['inspection_number'] ?? '';
            $row['test_date'] = $latest['test_date'] ?? '';
            $row['next_due_date'] = $latest['next_due_date'] ?? '';
            $row['result_status'] = InspectionEvaluationService::presentation((string) ($latest['result_status'] ?? ''))['label'];
            $result[] = $row;
        }
        return $result;
    }

    private static function deviceRows(array $devices): array
    {
        $rows = [['Gerätenummer', 'Bezeichnung', 'Hersteller', 'Typ/Modell', 'Kunde', 'Standort', 'Gebäude', 'Etage', 'Bereich', 'Raum', 'Letzte Prüfnummer', 'Letzte Prüfung', 'Nächste Prüfung', 'Ergebnis']];
        foreach ($devices as $d) $rows[] = [(string) $d['external_number'], (string) $d['name'], (string) $d['manufacturer'], (string) $d['device_model'], (string) $d['customer_name'], (string) $d['site_name'], (string) $d['building_name'], (string) $d['floor_name'], (string) $d['area_name'], (string) ($d['room_number'] ?: $d['room_name']), (string) $d['inspection_number'], (string) $d['test_date'], (string) $d['next_due_date'], (string) $d['result_status']];
        return $rows;
    }

    private static function dailyRows(array $deviceIds, string $date, string $examiner, int $customerId, string $toDate = ''): array
    {
        if ($deviceIds === []) return [['Prüfnummer', 'Datum', 'Prüfer', 'Kunde', 'Gerät', 'Raum', 'Ergebnis', 'Regiezeit (Min.)', 'Regiebegründung']];
        $marks = implode(',', array_fill(0, count($deviceIds), '?')); $args = $deviceIds; $where = ["i.device_id IN ($marks)"]; if ($date !== '') { if ($toDate !== '') { $where[] = 'i.test_date >= ? AND i.test_date <= ?'; $args[] = $date; $args[] = $toDate; } else { $where[] = 'i.test_date = ?'; $args[] = $date; } } if ($examiner !== '') { $where[] = 'LOWER(i.examiner) LIKE ?'; $args[] = '%' . strtolower($examiner) . '%'; } if ($customerId > 0) { $where[] = 'c.id = ?'; $args[] = $customerId; }
        $rows = [['Prüfnummer', 'Datum', 'Prüfer', 'Kunde', 'Gerät', 'Raum', 'Ergebnis', 'Regiezeit (Min.)', 'Regiebegründung']];
        foreach (R::getAll("SELECT i.external_number, i.test_date, i.examiner, i.result_status, i.status, i.regie_minutes, i.regie_reason, d.external_number AS device_number, d.name AS device_name, c.name AS customer_name, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE " . implode(' AND ', $where) . ' ORDER BY c.name, i.examiner, i.test_date, i.id', $args) as $row) $rows[] = [(string) $row['external_number'], (string) $row['test_date'], display_examiner_name((string) $row['examiner']), (string) $row['customer_name'], (string) $row['device_number'] . ' · ' . (string) $row['device_name'], trim(implode(' · ', array_filter([$row['site_name'], $row['building_name'], $row['floor_name'], $row['room_number']]))) ?: '—', InspectionEvaluationService::presentation((string) $row['result_status'], (string) $row['status'])['label'], (int) $row['regie_minutes'], (string) $row['regie_reason']];
        return $rows;
    }

    private static function roomRows(array $devices): array
    {
        $groups = [];
        $today = new DateTimeImmutable('today');
        $yellowLimit = $today->modify('+2 months');
        foreach ($devices as $d) {
            $key = implode("\x1f", [(string) $d['customer_name'], (string) $d['site_name'], (string) $d['building_name'], (string) $d['floor_name'], (string) $d['area_name'], (string) ($d['room_number'] ?: $d['room_name'])]);
            $groups[$key][] = $d;
        }
        $rows = [['Kunde', 'Standort', 'Gebäude', 'Etage', 'Bereich', 'Raum', 'Geräte', 'Fällig/überfällig', 'Quote']];
        foreach ($groups as $room => $items) {
            $due = 0; $overdue = 0;
            foreach ($items as $d) {
                $status = InspectionEvaluationService::normalizeStatus((string) $d['result_status']);
                $date = trim((string) $d['next_due_date']);
                $isDue = in_array($status, [InspectionEvaluationService::FAILED, InspectionEvaluationService::IN_PROGRESS, InspectionEvaluationService::DATA_MISSING], true);
                if ($status === InspectionEvaluationService::FAILED) $overdue++;
                if ($date !== '') try { $dueDate = new DateTimeImmutable($date); $isDue = $isDue || $dueDate <= $yellowLimit; if ($dueDate < $today) $overdue++; } catch (Throwable) {}
                if ($isDue) $due++;
            }
            $percent = count($items) > 0 ? round($due * 100 / count($items), 1) : 0;
            $parts = explode("\x1f", $room) + ['', '', '', '', '', ''];
            $rows[] = [$parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], count($items), $due, number_format($percent, 1, ',', '') . ' %'];
        }
        return $rows;
    }

    private static function csv(array $rows, string $filename): array
    {
        $stream = fopen('php://temp', 'w+');
        foreach ($rows as $row) fputcsv($stream, $row, ';');
        rewind($stream);
        $body = "\xEF\xBB\xBF" . stream_get_contents($stream);
        return [200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Language' => self::exportLanguage(), 'Content-Disposition' => 'attachment; filename="' . $filename . '"'], $body];
    }

    private static function compactStructureColumns(array $rows): array
    {
        if (count($rows) < 1 || !is_array($rows[0])) return $rows;
        $headers = array_values($rows[0]);
        // Periodische Prüfberichte (Tages-/Wochenreport) müssen unabhängig
        // von der Anzahl der Treffer dasselbe Spaltenschema behalten. Sonst
        // wird ein Wochenreport mit zwei Einträgen anders gekürzt als ein
        // Tagesreport und wirkt im Export unnötig „kürzer“.
        if (in_array('Prüfnummer', $headers, true)) return $rows;
        $data = array_slice($rows, 1);
        $index = static function (string $name) use ($headers): int|false { return array_search($name, $headers, true); };
        $values = static function (int $column) use ($data): array {
            return array_values(array_unique(array_filter(array_map(static fn($row): string => trim((string) ($row[$column] ?? '')), $data), static fn(string $v): bool => $v !== '')));
        };
        $remove = [];
        if (($i = $index('Bereich')) !== false && $values($i) === []) $remove[] = $i;
        if (($i = $index('Kunde')) !== false && count($values($i)) <= 1) $remove[] = $i;
        $groupHasAtMostOne = static function (int $parent, int $child) use ($data): bool {
            $groups = [];
            foreach ($data as $row) {
                $p = trim((string) ($row[$parent] ?? '')); $c = trim((string) ($row[$child] ?? ''));
                if ($p === '' || $c === '') continue;
                $groups[$p][$c] = true;
            }
            return $groups !== [] && !array_filter($groups, static fn(array $items): bool => count($items) > 1);
        };
        $customer = $index('Kunde'); $site = $index('Standort'); $building = $index('Gebäude'); $floor = $index('Etage');
        if ($site !== false && ($customer === false || $groupHasAtMostOne($customer, $site))) $remove[] = $site;
        if ($building !== false && ($site === false || $groupHasAtMostOne($site, $building))) $remove[] = $building;
        if ($floor !== false && ($building === false || $groupHasAtMostOne($building, $floor))) $remove[] = $floor;
        $remove = array_values(array_unique($remove));
        if ($remove === []) return $rows;
        sort($remove);
        $keep = array_values(array_diff(array_keys($headers), $remove));
        $result = [array_map(static fn(int $i): string => (string) $headers[$i], $keep)];
        foreach ($data as $row) $result[] = array_map(static fn(int $i): string => (string) ($row[$i] ?? ''), $keep);
        return $result;
    }

    private static function ods(array $rows, string $title, array $branding = []): array
    {
        if (!class_exists('ZipArchive')) return [500, [], 'ODS-Export ist auf diesem Server nicht verfügbar.'];
        $primary = self::brandColor($branding, 'primary', '#1F4E78');
        $primaryText = self::brandColor($branding, 'primary_text', '#FFFFFF');
        $tmp = tempnam(sys_get_temp_dir(), 'ods-');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [500, [], 'ODS-Datei konnte nicht erstellt werden.'];
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
        $rows = self::compactStructureColumns($rows);
        $headers = $rows[0] ?? [];
        $isInspectionRows = $headers === ['Prüfung', 'Wert'];
        $companyName = (string) ($branding['company_name'] ?? 'CENEOS');
        // ODS uses a light sheet background, therefore prefer the dark-text
        // logo variant intended for light surfaces.
        $logoPath = (string) (($branding['logos']['long'] ?? '') ?: (($branding['logos']['light'] ?? '') ?: (($branding['logos']['dark'] ?? '') ?: ($branding['header_logo']['path'] ?? ''))));
        if ($logoPath !== '' && !str_starts_with($logoPath, '/')) $logoPath = dirname(__DIR__) . '/' . ltrim($logoPath, '/');
        $logoData = $logoPath !== '' && is_file($logoPath) ? (string) file_get_contents($logoPath) : '';
        $logoMime = $logoPath !== '' && str_ends_with(strtolower($logoPath), '.svg') ? 'image/svg+xml' : 'image/png';
        $headerKeys = array_map(static fn($v): string => mb_strtolower((string) $v), $headers);
        $today = new DateTimeImmutable('today');
        $cell = static function ($value, int $index, bool $header) use ($headerKeys, $today): string {
            $text = (string) $value; $style = $header ? 'Header' : '';
            if (!$header && str_contains($headerKeys[$index] ?? '', 'ergebnis')) { $lower = mb_strtolower($text); $style = str_contains($lower, 'nicht') || str_contains($lower, 'durch') ? 'Bad' : (str_contains($lower, 'bestanden') ? 'Good' : 'Warn'); }
            if (!$header && str_contains($headerKeys[$index] ?? '', 'quote')) { $percent = (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $text)); $style = $percent <= 10 ? 'Q1' : ($percent <= 20 ? 'Q2' : ($percent <= 40 ? 'Q3' : ($percent <= 60 ? 'Q4' : ($percent <= 80 ? 'Q5' : 'Q6')))); }
            if (!$header && str_contains($headerKeys[$index] ?? '', 'nächste prüfung')) {
                try { $date = new DateTimeImmutable($text); $style = $date < $today ? 'Bad' : ($date <= $today->modify('+2 months') ? 'Warn' : 'Good'); }
                catch (Throwable) {}
            }
            $styleAttr = $style !== '' ? ' table:style-name="' . $style . '"' : '';
            return '<table:table-cell' . $styleAttr . ' office:value-type="string"><text:p>' . htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</text:p></table:table-cell>';
        };
        $render = static function (array $row, bool $header) use ($cell): string {
            return '<table:table-row>' . implode('', array_map(static fn($v, $i): string => $cell($v, $i, $header), $row, array_keys($row))) . '</table:table-row>';
        };
        $columnCount = max(1, count($headers));
        $headerXml = $render($headers, true);
        $titleText = htmlspecialchars($companyName . ' · ' . $title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $titleCell = '<table:table-cell table:number-columns-spanned="' . $columnCount . '" table:style-name="Title"><text:p>' . $titleText . '</text:p></table:table-cell>';
        if ($logoData !== '' && $isInspectionRows) {
            $logoCell = '<table:table-cell table:number-columns-spanned="1" table:style-name="Title" style:vertical-align="middle"><draw:frame draw:name="Logo" text:anchor-type="as-char" svg:width="3.6cm" svg:height="0.9cm"><draw:image xlink:href="Pictures/logo" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"><office:binary-data>' . base64_encode($logoData) . '</office:binary-data></draw:image></draw:frame></table:table-cell>';
            $titleXml = '<table:table-row style:row-height="1.15cm" style:use-optimal-row-height="false">' . $logoCell . $titleCell . '</table:table-row>';
        } elseif ($logoData !== '' && $columnCount >= 3) {
            $titleSpan = $columnCount - 2;
            $covered = static fn(int $count): string => str_repeat('<table:covered-table-cell/>', max(0, $count));
            $logoCell = '<table:table-cell table:number-columns-spanned="2" table:style-name="Title" style:vertical-align="middle"><draw:frame draw:name="Logo" text:anchor-type="as-char" svg:width="2.35cm" svg:height="0.62cm"><draw:image xlink:href="Pictures/logo" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"><office:binary-data>' . base64_encode($logoData) . '</office:binary-data></draw:image></draw:frame></table:table-cell>';
            $titleCell = '<table:table-cell table:number-columns-spanned="' . $titleSpan . '" table:style-name="Title"><text:p>' . $titleText . '</text:p></table:table-cell>';
            $titleXml = '<table:table-row style:row-height="0.82cm" style:use-optimal-row-height="false">' . $logoCell . $covered(1) . $titleCell . $covered($titleSpan - 1) . '</table:table-row>';
        } else {
            $titleXml = '<table:table-row style:row-height="0.82cm" style:use-optimal-row-height="false">' . $titleCell . str_repeat('<table:covered-table-cell/>', max(0, $columnCount - 1)) . '</table:table-row>';
        }
        $subtitle = htmlspecialchars('Erstellt am ' . (new DateTimeImmutable())->format('d.m.Y H:i') . ' · ' . max(0, count($rows) - 1) . ' Datensätze · Filter und Sortierung aus der aktuellen Ansicht', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $subtitleXml = '<table:table-row><table:table-cell table:number-columns-spanned="' . $columnCount . '" table:style-name="Subtitle"><text:p>' . $subtitle . '</text:p></table:table-cell></table:table-row>';
        $bodyXml = '';
        foreach (array_slice($rows, 1) as $row) { if (($row[0] ?? '') === '__raw_json') continue; $bodyXml .= $render($row, false); }
        $lastColumn = '';
        $n = $columnCount;
        while ($n > 0) { $n--; $lastColumn = chr(65 + ($n % 26)) . $lastColumn; $n = intdiv($n, 26); }
        $widthMap = ['Gerätenummer' => '3.0cm', 'Bezeichnung' => '4.8cm', 'Hersteller' => '3.8cm', 'Typ/Modell' => '4.2cm', 'Kunde' => '4.5cm', 'Standort' => '4.2cm', 'Gebäude' => '4.2cm', 'Etage' => '3.0cm', 'Bereich' => '3.0cm', 'Raum' => '3.5cm', 'Letzte Prüfnummer' => '3.6cm', 'Letzte Prüfung' => '3.2cm', 'Nächste Prüfung' => '3.2cm', 'Ergebnis' => '3.4cm', 'Quote' => '2.8cm', 'Prüfung' => '6.2cm', 'Wert' => '11.5cm'];
        $widths = array_map(static fn($header): string => $widthMap[(string) $header] ?? '3.5cm', $headers);
        $columns = ''; foreach (array_slice($widths, 0, $columnCount) as $index => $width) $columns .= '<table:table-column table:style-name="Col' . ($index + 1) . '"/>';
        $columnStyles = ''; foreach (array_slice($widths, 0, $columnCount) as $index => $width) $columnStyles .= '<style:style style:name="Col' . ($index + 1) . '" style:family="table-column"><style:table-column-properties style:column-width="' . $width . '"/></style:style>';
        $quoteColors = ['#d1e7dd', '#b7e4c7', '#fff3cd', '#ffe69c', '#ffda6a', '#ffb86b'];
        $quoteStyles = ''; foreach ($quoteColors as $index => $color) $quoteStyles .= '<style:style style:name="Q' . ($index + 1) . '" style:family="table-cell"><style:table-cell-properties fo:background-color="' . $color . '"/></style:style>';
        $content = '<?xml version="1.0" encoding="UTF-8"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svg="http://www.w3.org/2000/svg" office:version="1.2"><office:automatic-styles>' . $columnStyles . '<style:style style:name="Title" style:family="table-cell"><style:table-cell-properties fo:background-color="#eef2f6"/><style:text-properties fo:font-size="16pt" fo:font-weight="bold"/></style:style><style:style style:name="Subtitle" style:family="table-cell"><style:table-cell-properties fo:background-color="#f7f9fb"/><style:text-properties fo:font-size="9pt" fo:color="#52606d"/></style:style><style:style style:name="Header" style:family="table-cell"><style:table-cell-properties fo:background-color="' . $primary . '"/><style:text-properties fo:color="' . $primaryText . '" fo:font-weight="bold"/></style:style><style:style style:name="Good" style:family="table-cell"><style:table-cell-properties fo:background-color="#d1e7dd"/></style:style><style:style style:name="Warn" style:family="table-cell"><style:table-cell-properties fo:background-color="#fff3cd"/></style:style><style:style style:name="Bad" style:family="table-cell"><style:table-cell-properties fo:background-color="#f8d7da"/></style:style>' . $quoteStyles . '</office:automatic-styles><office:body><office:spreadsheet><table:table table:name="Export" style:master-page-name="Default" table:print-ranges="$Export.$A$1:$' . $lastColumn . '$' . (count($rows) + 2) . '" table:print-title-rows="1:3">' . $columns . $titleXml . $subtitleXml . '<table:table-header-rows>' . $headerXml . '</table:table-header-rows>' . $bodyXml . '</table:table><table:database-ranges><table:database-range table:name="ExportFilter" table:target-range-address="Export.A3:' . $lastColumn . (count($rows) + 2) . '" table:display-filter-buttons="true" table:contains-header="true"/></table:database-ranges></office:spreadsheet></office:body></office:document-content>';
        $zip->addFromString('content.xml', $content);
        $pageLayout = $isInspectionRows ? '<style:page-layout style:name="Portrait"><style:page-layout-properties fo:page-width="21cm" fo:page-height="29.7cm" fo:margin-top="1.2cm" fo:margin-right="1.2cm" fo:margin-bottom="1.2cm" fo:margin-left="2cm" style:print-orientation="portrait" style:scale-to-X="1"/></style:page-layout>' : '<style:page-layout style:name="Landscape"><style:page-layout-properties fo:page-width="29.7cm" fo:page-height="21.001cm" fo:margin-top="0.5cm" fo:margin-right="0.5cm" fo:margin-bottom="0.5cm" fo:margin-left="1.8cm" style:print-orientation="landscape" style:scale-to-X="1"/></style:page-layout>';
        $masterLayout = $isInspectionRows ? 'Portrait' : 'Landscape';
        $zip->addFromString('styles.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" office:version="1.2"><office:automatic-styles>' . $pageLayout . '</office:automatic-styles><office:master-styles><style:master-page style:name="Default" style:page-layout-name="' . $masterLayout . '"/></office:master-styles><office:styles/></office:document-styles>');
        $zip->addFromString('meta.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:meta><dc:language xmlns:dc="http://purl.org/dc/elements/1.1/">' . self::exportLanguage() . '</dc:language></office:meta></office:document-meta>');
        $zip->addFromString('settings.xml', '<?xml version="1.0" encoding="UTF-8"?><office:document-settings xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" office:version="1.2"><office:settings/></office:document-settings>');
        $manifest = '<?xml version="1.0" encoding="UTF-8"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0"><manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.spreadsheet" manifest:full-path="/"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/><manifest:file-entry manifest:media-type="text/xml" manifest:full-path="settings.xml"/>' . ($logoData !== '' ? '<manifest:file-entry manifest:media-type="' . $logoMime . '" manifest:full-path="Pictures/logo"/>' : '') . '</manifest:manifest>';
        $zip->addFromString('META-INF/manifest.xml', $manifest);
        if ($logoData !== '') $zip->addFromString('Pictures/logo', $logoData);
        $zip->close();
        $body = file_get_contents($tmp); @unlink($tmp);
        return [200, ['Content-Type' => 'application/vnd.oasis.opendocument.spreadsheet', 'Content-Language' => self::exportLanguage(), 'Content-Disposition' => 'attachment; filename="' . $title . '.ods"'], $body];
    }

    private static function xlsx(array $rows, string $title, array $branding = []): array
    {
        if (!class_exists('ZipArchive')) return [500, [], 'XLSX-Export ist auf diesem Server nicht verfügbar.'];
        // LibreOffice übernimmt hier denselben gebrandeten Tabellenaufbau wie
        // beim ODS-Export, inklusive Logo in A:B und Titel ab C.
        $office = is_executable('/usr/bin/libreoffice') ? '/usr/bin/libreoffice' : (is_executable('/usr/bin/soffice') ? '/usr/bin/soffice' : '');
        if ($office !== '') {
            $odsResponse = self::ods($rows, $title, $branding);
            $odsBody = $odsResponse[2] ?? '';
            if (is_string($odsBody) && $odsBody !== '') {
                $dir = sys_get_temp_dir() . '/pruefapp-xlsx'; if (!is_dir($dir)) mkdir($dir, 0700, true);
                $token = bin2hex(random_bytes(8)); $odsPath = $dir . '/' . $token . '.ods'; $outDir = $dir . '/' . $token;
                if (!is_dir($outDir)) mkdir($outDir, 0700, true); $profile = $dir . '/profile-' . $token;
                file_put_contents($odsPath, $odsBody, LOCK_EX);
                $cmd = 'timeout 30s ' . escapeshellarg($office) . ' -env:UserInstallation=' . escapeshellarg('file://' . $profile) . ' --headless --convert-to xlsx --outdir ' . escapeshellarg($outDir) . ' ' . escapeshellarg($odsPath) . ' 2>/dev/null';
                shell_exec($cmd); $converted = glob($outDir . '/*.xlsx') ?: []; $xlsxPath = $converted[0] ?? '';
                $body = $xlsxPath !== '' && is_file($xlsxPath) ? file_get_contents($xlsxPath) : false;
                @unlink($odsPath); if ($xlsxPath !== '') @unlink($xlsxPath); @rmdir($outDir); @rmdir($profile);
                if (is_string($body) && $body !== '') return [200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Language' => self::exportLanguage(), 'Content-Disposition' => 'attachment; filename="' . $title . '.xlsx"'], $body];
            }
        }
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx-');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return [500, [], 'XLSX-Datei konnte nicht erstellt werden.'];
        $esc = static fn($value): string => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $primary = self::brandColor($branding, 'primary', '#1F4E78');
        $primaryRgb = 'FF' . ltrim($primary, '#');
        $col = static function (int $index): string { $name = ''; do { $name = chr(65 + ($index % 26)) . $name; $index = intdiv($index, 26) - 1; } while ($index >= 0); return $name; };
        $headers = $rows[0] ?? [];
        $sheet = '';
        foreach ($rows as $rowIndex => $row) {
            $sheet .= '<row r="' . ($rowIndex + 1) . '">';
            foreach (array_values($row) as $columnIndex => $value) {
                $text = (string) $value;
                $style = $rowIndex === 0 ? 1 : 0;
                $header = mb_strtolower((string) ($headers[$columnIndex] ?? ''));
                $lower = mb_strtolower($text);
                if ($rowIndex > 0 && str_contains($header, 'ergebnis')) $style = str_contains($lower, 'bestanden') && !str_contains($lower, 'nicht') ? 2 : (str_contains($lower, 'durch') || str_contains($lower, 'nicht') ? 3 : 0);
                if ($rowIndex > 0 && str_contains($header, 'nächste prüfung')) { try { $style = (new DateTimeImmutable($text)) < new DateTimeImmutable('today') ? 3 : 2; } catch (Throwable) {} }
                $sheet .= '<c r="' . $col($columnIndex) . ($rowIndex + 1) . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . $esc($text) . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $lastColumn = $col(max(0, count($headers) - 1)); $lastRow = max(1, count($rows));
        $widths = array_map(static fn($header): string => (string) max(12, min(34, mb_strlen((string) $header) + 5)), $headers);
        $cols = ''; foreach ($widths as $i => $width) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $esc(mb_substr($title, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="' . $primaryRgb . '"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF8D7DA"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders><cellXfs count="4"><xf/><xf fontId="1" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" wrapText="1"/></xf><xf fillId="2" borderId="1" applyFill="1" applyBorder="1"><alignment wrapText="1"/></xf><xf fillId="3" borderId="1" applyFill="1" applyBorder="1"><alignment wrapText="1"/></xf></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $sheet . '</sheetData><autoFilter ref="A1:' . $lastColumn . $lastRow . '"/><pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/></worksheet>');
        $zip->close(); $body = file_get_contents($tmp); @unlink($tmp);
        return [200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Language' => self::exportLanguage(), 'Content-Disposition' => 'attachment; filename="' . $title . '.xlsx"'], $body];
    }

    private static function pdf(array $rows, string $title, array $branding = []): array
    {
        $rows = self::compactStructureColumns($rows);
        if (($rows[0] ?? []) === ['Prüfung', 'Wert']) {
            $inspectionPdf = self::inspectionPdf($rows, $title, $branding);
            if ($inspectionPdf !== null) return $inspectionPdf;
        }
        // Chromium applies the CSS page width reliably for the generic table
        // export. The LibreOffice route remains as a fallback for servers
        // without Chromium.
        // LibreOffice rendert die bereits formatierte Tabellenstruktur zuverlässig
        // und vermeidet die leeren/abgeschnittenen Browser-PDFs.
        if ($rows !== [] && class_exists('ZipArchive') && (is_executable('/usr/bin/libreoffice') || is_executable('/usr/bin/soffice'))) {
            $odsResponse = self::ods($rows, $title, $branding);
            $odsBody = $odsResponse[2] ?? '';
            if (is_string($odsBody) && $odsBody !== '') {
                $dir = sys_get_temp_dir() . '/pruefapp-pdf'; if (!is_dir($dir)) mkdir($dir, 0700, true); $token = bin2hex(random_bytes(8)); $odsPath = $dir . '/' . $token . '.ods'; $outDir = $dir . '/' . $token; if (!is_dir($outDir)) mkdir($outDir, 0700, true); $pdfPath = $outDir . '/' . $title . '.pdf'; file_put_contents($odsPath, $odsBody, LOCK_EX); $binary = is_executable('/usr/bin/libreoffice') ? '/usr/bin/libreoffice' : '/usr/bin/soffice'; $profile = $dir . '/profile-' . $token; $cmd = 'timeout 30s ' . escapeshellarg($binary) . ' -env:UserInstallation=' . escapeshellarg('file://' . $profile) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outDir) . ' ' . escapeshellarg($odsPath) . ' 2>/dev/null'; shell_exec($cmd); $converted = glob($outDir . '/*.pdf') ?: []; $pdfPath = $converted[0] ?? $pdfPath; $body = is_file($pdfPath) ? file_get_contents($pdfPath) : false; $text = is_file($pdfPath) && function_exists('shell_exec') ? trim((string) shell_exec('pdftotext ' . escapeshellarg($pdfPath) . ' - 2>/dev/null')) : ''; @unlink($odsPath); if (is_file($pdfPath)) @unlink($pdfPath); @rmdir($outDir); if (is_dir($profile)) { foreach (glob($profile . '/*') ?: [] as $child) if (is_file($child)) @unlink($child); @rmdir($profile); } if (is_string($body) && $body !== '' && $text !== '') return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $body];
            }
        }
        // Chromium provides the branded UTF-8 fallback if LibreOffice is not installed.
        if (is_executable('/usr/bin/chromium') && $rows !== []) {
            $primary = self::brandColor($branding, 'primary', '#1F4E78'); $nav = self::brandColor($branding, 'nav', '#F5C242'); $primaryText = self::brandColor($branding, 'primary_text', '#FFFFFF'); $companyName = (string) ($branding['company_name'] ?? 'CENEOS'); $logoPath = (string) (($branding['logos']['long'] ?? '') ?: (($branding['logos']['dark'] ?? '') ?: ($branding['header_logo']['path'] ?? ''))); if ($logoPath !== '' && !preg_match('#^/#', $logoPath)) $logoPath = dirname(__DIR__) . '/' . ltrim($logoPath, '/'); $logo = is_file($logoPath) ? 'data:' . (str_ends_with(strtolower($logoPath), '.svg') ? 'image/svg+xml' : 'image/png') . ';base64,' . base64_encode((string) file_get_contents($logoPath)) : '';
            $subtitle = 'Erstellt am ' . (new DateTimeImmutable())->format('d.m.Y H:i') . ' · ' . max(0, count($rows) - 1) . ' Datensätze · Tabellenübersicht aus der aktuellen Filter- und Sortierauswahl';
            $html = '<!doctype html><meta charset="utf-8"><style>@page{size:A4 landscape;margin:12mm 12mm 12mm 20mm}body{font-family:Arial,sans-serif;color:#202124;font-size:9px}header{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid ' . $nav . ';padding-bottom:8px;margin-bottom:8px}header img{max-width:150px;max-height:42px}h1{font-size:18px;margin:0;color:' . $primary . '}table{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:8px}thead{display:table-header-group}th{background:' . $primary . ';color:' . $primaryText . ';text-align:left;padding:7px 6px;font-size:8px;line-height:1.2;overflow-wrap:anywhere}td{border:1px solid #ccd2d8;padding:5px;vertical-align:top;overflow-wrap:anywhere;line-height:1.25}tr{break-inside:avoid}tr:nth-child(even) td{background:#f4f6f8}.muted{color:#6c757d;font-size:8px}.subtitle{color:#52606d;font-size:9px;margin:0 0 5px}</style><header><h1>' . htmlspecialchars($companyName . ' - ' . $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>' . ($logo !== '' ? '<img src="' . $logo . '" alt="' . htmlspecialchars($companyName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' : '') . '</header><div class="subtitle">' . htmlspecialchars($subtitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div><table><thead><tr>';
            foreach (($rows[0] ?? []) as $header) $html .= '<th>' . htmlspecialchars((string) $header, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</th>';
            $html .= '</tr></thead><tbody>';
            foreach (array_slice($rows, 1) as $row) { $html .= '<tr>'; foreach (($rows[0] ?? []) as $index => $_) $html .= '<td>' . nl2br(htmlspecialchars((string) ($row[$index] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</td>'; $html .= '</tr>'; }
            if (count($rows) <= 1) $html .= '<tr><td colspan="' . max(1, count($rows[0] ?? [])) . '">Keine Datensätze für diesen Export.</td></tr>';
            $html .= '</tbody></table>';
            $dir = sys_get_temp_dir() . '/pruefapp-pdf'; if (!is_dir($dir)) mkdir($dir, 0700, true); $token = bin2hex(random_bytes(8)); $htmlPath = $dir . '/' . $token . '.html'; $pdfPath = $dir . '/' . $token . '.pdf'; $profile = $dir . '/' . $token . '-profile'; file_put_contents($htmlPath, $html, LOCK_EX); $command = '/usr/bin/chromium --headless --no-sandbox --disable-gpu --disable-dev-shm-usage --user-data-dir=' . escapeshellarg($profile) . ' --print-to-pdf=' . escapeshellarg($pdfPath) . ' ' . escapeshellarg('file://' . $htmlPath) . ' 2>/dev/null'; shell_exec($command); $body = is_file($pdfPath) ? file_get_contents($pdfPath) : false; $visibleText = is_file($pdfPath) && function_exists('shell_exec') ? trim((string) shell_exec('pdftotext ' . escapeshellarg($pdfPath) . ' - 2>/dev/null')) : ''; @unlink($htmlPath); @unlink($pdfPath); if (is_dir($profile)) { foreach (glob($profile . '/*') ?: [] as $child) { if (is_file($child)) @unlink($child); } @rmdir($profile); } if (is_string($body) && $body !== '' && $visibleText !== '') return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $body];
        }
        $headers = $rows[0] ?? []; $count = max(1, count($headers)); $widths = $count === 14 ? [62, 75, 65, 65, 65, 65, 65, 58, 58, 70, 64, 58, 58, 64] : ($count === 9 ? [76, 76, 76, 62, 62, 84, 48, 68, 68] : array_fill(0, $count, 794 / $count));
        $left = 24; $top = 565; $rowHeight = 19; $headerHeight = 28; $pageRows = 25; $streams = []; $stream = '';
        $page = static function (string &$stream) use (&$streams): void { $streams[] = $stream; $stream = ''; };
        $brandPrimary = self::brandColor($branding, 'primary', '#1F4E78'); $brandNav = self::brandColor($branding, 'nav', '#F5C242'); $brandText = self::brandColor($branding, 'primary_text', '#FFFFFF'); $brandName = (string) ($branding['company_name'] ?? 'CENEOS'); $hexRgb = static function (string $hex): array { return [hexdec(substr($hex, 1, 2)) / 255, hexdec(substr($hex, 3, 2)) / 255, hexdec(substr($hex, 5, 2)) / 255]; }; [$pr, $pg, $pb] = $hexRgb($brandPrimary); [$nr, $ng, $nb] = $hexRgb($brandNav); [$tr, $tg, $tb] = $hexRgb($brandText);
        $drawPageHeader = static function (string &$stream) use ($title, $brandName, $left, $top, $headers, $widths, $headerHeight, $pr, $pg, $pb, $nr, $ng, $nb, $tr, $tg, $tb): float {
            $stream .= sprintf("%.3f %.3f %.3f rg %d %d 794 30 re f\n%.3f %.3f %.3f rg %d %d 794 4 re f\nBT /F1 16 Tf %.3f %.3f %.3f rg %d %d Td (%s - %s) Tj ET\n", $pr, $pg, $pb, $left, $top - 30, $nr, $ng, $nb, $left, $top - 4, $tr, $tg, $tb, $left, $top - 20, self::pdfEscape(self::pdfText($brandName)), self::pdfEscape(self::pdfText($title)));
            $x = $left; $y = $top - 58; foreach ($headers as $i => $header) { $w = $widths[$i]; $stream .= "0.20 0.23 0.27 rg {$x} {$y} {$w} {$headerHeight} re f\nBT /F1 7 Tf 1 1 1 rg " . ($x + 3) . ' ' . ($y + 9) . " Td (" . self::pdfEscape(self::pdfText(mb_strimwidth((string) $header, 0, 24, '…'))) . ") Tj ET\n"; $x += $w; } return $y - $headerHeight;
        };
        $y = $drawPageHeader($stream); $rowIndex = 0; $quoteIndex = array_search('Quote', $headers, true);
        foreach (array_slice($rows, 1) as $row) {
            if ($rowIndex > 0 && $rowIndex % $pageRows === 0) { $page($stream); $y = $drawPageHeader($stream); }
            $x = $left; $quote = $quoteIndex !== false ? (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', (string) ($row[$quoteIndex] ?? ''))) : 0;
            foreach ($headers as $i => $header) { $rawValue = (string) ($row[$i] ?? ''); $value = self::pdfText(mb_strimwidth(preg_replace('/\s+/', ' ', $rawValue), 0, 28, '…')); $headerText = mb_strtolower((string) $header); $valueText = mb_strtolower($rawValue); [$r, $g, $b] = $quoteIndex === $i ? self::quoteRgb($quote) : [255, 255, 255]; if ($quoteIndex !== $i && str_contains($headerText, 'ergebnis')) { if (str_contains($valueText, 'durch') || str_contains($valueText, 'nicht')) [$r, $g, $b] = [248, 215, 218]; elseif (str_contains($valueText, 'bestanden')) [$r, $g, $b] = [209, 231, 221]; } if ($quoteIndex !== $i && str_contains($headerText, 'nächste prüfung')) { try { $due = new DateTimeImmutable($rawValue); [$r, $g, $b] = $due < new DateTimeImmutable('today') ? [248, 215, 218] : ($due <= (new DateTimeImmutable('today'))->modify('+2 months') ? [255, 243, 205] : [209, 231, 221]); } catch (Throwable) {} } $stream .= sprintf("%0.2f %0.2f %0.2f rg %.2f %.2f %.2f %.2f re f 0.70 0.70 0.70 RG %.2f %.2f %.2f %.2f re S\n", $r / 255, $g / 255, $b / 255, $x, $y, $widths[$i], $rowHeight, $x, $y, $widths[$i], $rowHeight); $stream .= "BT /F1 6 Tf 0.10 0.10 0.10 rg " . ($x + 3) . ' ' . ($y + 7) . " Td (" . self::pdfEscape($value) . ") Tj ET\n"; $x += $widths[$i]; }
            $y -= $rowHeight; $rowIndex++;
        }
        if ($stream !== '') $page($stream);
        $pdf = self::buildPdf($streams); return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $pdf];
    }

    private static function inspectionPdf(array $rows, string $title, array $branding): ?array
    {
        $writerBody = InspectionReportWriter::render($rows, $title, $branding);
        if (is_string($writerBody) && $writerBody !== '') {
            return [
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"',
                ],
                $writerBody,
            ];
        }

        $values = [];
        foreach (array_slice($rows, 1) as $row) {
            $key = trim((string) ($row[0] ?? ''));
            if ($key !== '') $values[$key] = trim((string) ($row[1] ?? ''));
        }
        $primary = self::brandColor($branding, 'primary', '#1F4E78');
        $nav = self::brandColor($branding, 'nav', '#F5C242');
        $company = (string) ($branding['company_name'] ?? 'CENEOS');
        $logoPath = (string) (($branding['logos']['long'] ?? '') ?: (($branding['logos']['dark'] ?? '') ?: ($branding['header_logo']['path'] ?? '')));
        if ($logoPath !== '' && !str_starts_with($logoPath, '/')) $logoPath = dirname(__DIR__) . '/' . ltrim($logoPath, '/');
        $logo = is_file($logoPath) ? 'data:' . (str_ends_with(strtolower($logoPath), '.svg') ? 'image/svg+xml' : 'image/png') . ';base64,' . base64_encode((string) file_get_contents($logoPath)) : '';
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $result = mb_strtolower($values['Ergebnis'] ?? '');
        $resultClass = str_contains($result, 'durch') || str_contains($result, 'nicht') ? 'bad' : ($result === 'bestanden' ? 'good' : 'pending');
        $raw = [];
        foreach (array_slice($rows, 1) as $row) { $label = (string) ($row[0] ?? ''); if ($label !== '') $raw[$label] = (string) ($row[1] ?? ''); }
        $known = ['Prüfnummer','Datum','Prüfart','Prüfungstyp','Prüfer','Nächste Prüfung','Gerät','Ergebnis','Regiezeit','Regiebegründung','Prüfungsabschluss','Inventarnummer','Geräteart','Hersteller','Typ','Wärmegerät','Auftraggeber','Liegenschaft','Gebäude','Etage','Raum-Nr.','__raw_json','__measurements_json','__checklist_json','__profile_signature','__inspection_url','__device_url'];
        $items = [];
        foreach (array_slice($rows, 1) as $row) { $label = (string) ($row[0] ?? ''); if ($label !== '' && !in_array($label, $known, true) && $label !== 'Prüfschritte') $items[] = ['Messung', $label, (string) ($row[1] ?? ''), '', '']; }
        $rawJson = json_decode((string) ($values['__raw_json'] ?? ''), true) ?: [];
        $metadata = [['Prüfungs-Nr.', $values['Prüfnummer'] ?? ''], ['Datum', $values['Datum'] ?? ''], ['Art der Prüfung', $values['Prüfart'] ?? ($values['Prüfungstyp'] ?? '')], ['Prüfer', $values['Prüfer'] ?? ''], ['Nächste Prüfung', $values['Nächste Prüfung'] ?? ''], ['Gerät', $values['Gerät'] ?? ''], ['Inventar-Nr.', $values['Inventarnummer'] ?? ''], ['Geräteart', $values['Geräteart'] ?? ''], ['Hersteller', $values['Hersteller'] ?? ''], ['Typ', $values['Typ'] ?? ''], ['Wärmegerät', $values['Wärmegerät'] ?? ''], ['Auftraggeber', $values['Auftraggeber'] ?? ''], ['Liegenschaft', $values['Liegenschaft'] ?? ''], ['Gebäude', $values['Gebäude'] ?? ''], ['Etage', $values['Etage'] ?? ''], ['Raum-Nr.', $values['Raum-Nr.'] ?? '']];
        $html = '<!doctype html><meta charset="utf-8"><style>@page{size:A4 portrait;margin:12mm 13mm 14mm 20mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#202124;font-size:10px;margin:0}header{display:grid;grid-template-columns:1fr 1.7fr 1fr;align-items:start;gap:22px;padding-bottom:18px;margin-bottom:48px}header img{max-width:145px;max-height:68px;object-fit:contain}header .company{line-height:1.45}header .report{text-align:right;font-weight:bold}.report span{display:block;font-weight:normal;margin-top:6px}h1{font-size:18px;color:' . $primary . ';margin:0 0 4px}.meta{display:grid;grid-template-columns:1.15fr 1fr 1fr;gap:12px 26px;margin:0 10px 38px}.meta div{display:grid;grid-template-columns:92px 1fr;min-height:19px;line-height:1.3}.meta strong{font-weight:bold}.result{display:inline-block;padding:4px 12px;border-radius:999px;font-weight:bold}.good{background:#d1e7dd;color:#0f5132}.bad{background:#f8d7da;color:#842029}.pending{background:#fff3cd;color:#664d03}h2{font-size:14px;color:#202124;margin:0 0 9px}.measurements{width:100%;border-collapse:collapse;table-layout:fixed}.measurements th{background:#ddd;color:#111;text-align:left;padding:7px;border:1px solid #222}.measurements td{border:1px solid #222;padding:7px;vertical-align:top;line-height:1.35}.measurements tr:nth-child(even) td{background:#fafafa}.ok{font-weight:bold;text-align:center}.footer{display:grid;grid-template-columns:1fr 1fr;margin:42px 5px 0;gap:50px}.line{border-bottom:1px solid #666;height:34px}.muted{color:#6c757d;font-size:9px}.footnote{position:fixed;bottom:-7mm;left:0;right:0;display:flex;justify-content:space-between;font-size:8px;color:#555}</style><header>' . ($logo !== '' ? '<img src="' . $logo . '" alt="' . $esc($company) . '">' : '<div></div>') . '<div class="company"><h1>' . $esc($company) . '</h1><div>Prüfbericht</div></div><div class="report">Prüfberichts-Nr.:<span>' . $esc($values['Prüfnummer'] ?? '') . '</span></div></header><div class="meta">';
        foreach ($metadata as [$label, $value]) if ($value !== '') $html .= '<div><strong>' . $esc($label) . '</strong><span>' . nl2br($esc($value)) . '</span></div>';
        $html .= '<div><strong>Ergebnis</strong><span class="result ' . $resultClass . '">' . $esc($values['Ergebnis'] ?? 'ausstehend') . '</span></div></div><h2>Prüfergebnisse</h2><table class="measurements"><thead><tr><th style="width:14%">Fragentyp</th><th style="width:43%">Prüffrage</th><th style="width:35%">Kriterium</th><th style="width:8%">Ergebnis</th></tr></thead><tbody>';
        $auditOk = ($rawJson['audit_ok'] ?? false) === true;
        $resultCell = static function (string $result) use ($esc, $auditOk): string { $lower = mb_strtolower($result); $class = str_contains($lower, 'nicht') || str_contains($lower, 'durch') || str_contains($lower, 'fail') ? 'bad' : (str_contains($lower, 'bestanden') || in_array($lower, ['ok','ja','gut'], true) || ($result === '' && $auditOk) ? 'good' : 'pending'); return '<td class="ok"><span class="result ' . $class . '">' . $esc($result !== '' ? $result : 'OK') . '</span></td>'; };
        if (isset($rawJson['step0'])) { foreach ($rawJson as $key => $question) if (preg_match('/^step(\d+)$/', (string) $key, $m)) { $n=(int)$m[1]; $category=$n===0?'Inventarisierung':($n<=3?'Sichtprüfung':($n<=6?'Messung':($n===7?'Funktionsprüfung':'Organisatorische Hinweise'))); $result=(string)($rawJson['result'.$n]??''); $criterion=(string)($rawJson['criterion'.$n]??''); $html.='<tr><td>'.$esc($category).'</td><td>'.$esc((string)$question).'</td><td>'.$esc($criterion).'</td>'.$resultCell($result).'</tr>'; } } else foreach ($items as [$category,$question,$result,$criterion,$unused]) $html.='<tr><td>'.$esc($category).'</td><td>'.$esc($question).'</td><td>'.$esc($criterion).'</td>'.$resultCell($result).'</tr>';
        $onlineLink = trim((string) ($values['__inspection_url'] ?? ''));
        $deviceLink = trim((string) ($values['__device_url'] ?? ''));
        $onlineNote = $onlineLink !== '' ? '<div class="muted" style="margin-top:8px">Prüfung online: <a href="' . $esc($onlineLink) . '">' . $esc($onlineLink) . '</a>' . ($deviceLink !== '' ? '<br>Gerät und Fotos: <a href="' . $esc($deviceLink) . '">' . $esc($deviceLink) . '</a>' : '') . '</div>' : '';
        $html .= '</tbody></table><div class="footer"><div><strong>Regiezeit:</strong> ' . $esc($values['Regiezeit'] ?? '0 Minuten') . '<div class="muted">' . $esc($values['Regiebegründung'] ?? '') . '</div>' . $onlineNote . '</div><div><strong>Unterschrift:</strong><div class="line"></div></div></div><div class="footnote"><span>' . $esc($company) . '</span><span>' . $esc((new DateTimeImmutable())->format('d.m.Y')) . '</span></div>';
        $dir = sys_get_temp_dir() . '/pruefapp-inspection-pdf'; if (!is_dir($dir)) mkdir($dir, 0700, true);
        $token = bin2hex(random_bytes(8)); $htmlPath = $dir . '/' . $token . '.html'; $pdfPath = $dir . '/' . $token . '.pdf'; $profile = $dir . '/' . $token . '-profile';
        file_put_contents($htmlPath, $html, LOCK_EX);
        if (is_executable('/usr/bin/chromium')) { $command = '/usr/bin/chromium --headless --no-sandbox --disable-gpu --disable-dev-shm-usage --no-pdf-header-footer --user-data-dir=' . escapeshellarg($profile) . ' --print-to-pdf=' . escapeshellarg($pdfPath) . ' ' . escapeshellarg('file://' . $htmlPath) . ' 2>/dev/null'; shell_exec($command); }
        $body = is_file($pdfPath) ? file_get_contents($pdfPath) : false;
        if ((!is_string($body) || $body === '') && (is_executable('/usr/bin/libreoffice') || is_executable('/usr/bin/soffice'))) {
            $office = is_executable('/usr/bin/libreoffice') ? '/usr/bin/libreoffice' : '/usr/bin/soffice'; $outDir = $dir . '/' . $token . '-writer'; if (!is_dir($outDir)) mkdir($outDir, 0700, true); $officeProfile = $dir . '/' . $token . '-office';
            $command = 'timeout 30s ' . escapeshellarg($office) . ' -env:UserInstallation=' . escapeshellarg('file://' . $officeProfile) . ' --headless --convert-to pdf --outdir ' . escapeshellarg($outDir) . ' ' . escapeshellarg($htmlPath) . ' 2>/dev/null'; shell_exec($command); $converted = glob($outDir . '/*.pdf') ?: []; if ($converted !== []) $body = file_get_contents($converted[0]); foreach ($converted as $file) @unlink($file); @rmdir($outDir);
        }
        @unlink($htmlPath); @unlink($pdfPath); if (is_dir($profile)) { foreach (glob($profile . '/*') ?: [] as $child) if (is_file($child)) @unlink($child); @rmdir($profile); }
        return is_string($body) && $body !== '' ? [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $title . '.pdf"'], $body] : null;
    }

    private static function exportLanguage(): string
    {
        $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        return $header === '' || str_starts_with($header, 'de') ? 'de-DE' : (str_contains($header, 'en') ? 'en-US' : 'de-DE');
    }
    private static function pdfText(string $value): string { return function_exists('iconv') ? (string) iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $value) : $value; }
    private static function brandColor(array $branding, string $key, string $fallback): string
    {
        $value = $key === 'nav' ? ($branding['nav_colors']['background'] ?? '') : ($branding['theme_colors'][$key] ?? '');
        $value = strtoupper(trim((string) $value));
        if ($value !== '' && $value[0] !== '#') $value = '#' . $value;
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallback;
    }
    private static function pdfEscape(string $value): string { return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $value); }
    private static function buildPdf(array $streams): string { $objects = ['1 0 obj<< /Type /Catalog /Pages 2 0 R>>endobj', '2 0 obj<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($i): string => (string) (4 + $i) . ' 0 R', array_keys($streams))) . '] /Count ' . count($streams) . '>>endobj', '3 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>endobj']; foreach ($streams as $i => $body) $objects[] = (4 + $i) . ' 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources<< /Font<< /F1 3 0 R>>>> /Contents ' . (4 + count($streams) + $i) . ' 0 R>>endobj'; foreach ($streams as $i => $body) $objects[] = (4 + count($streams) + $i) . ' 0 obj<< /Length ' . strlen($body) . ">>stream\n" . $body . "endstream endobj"; $pdf = "%PDF-1.4\n"; $offsets = []; foreach ($objects as $object) { $offsets[] = strlen($pdf); $pdf .= $object . "\n"; } $xref = strlen($pdf); $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n"; foreach ($offsets as $offset) $pdf .= sprintf("%010d 00000 n \n", $offset); return $pdf . "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R>>\nstartxref\n" . $xref . "\n%%EOF"; }
    private static function quoteRgb(float $value): array { return $value <= 10 ? [209, 231, 221] : ($value <= 20 ? [183, 228, 199] : ($value <= 40 ? [255, 243, 205] : ($value <= 60 ? [255, 230, 156] : ($value <= 80 ? [255, 218, 106] : [255, 184, 107])))); }
}
