<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class InspectionController
{
    public static function normalizeManualResult($inspection): void
    {
        // Kept as a compatibility hook. Result repair now happens only in the
        // explicit migration/evaluation flow; reading a page never writes.
    }

    private static function uniqueExternalNumber(string $base, int $ignoreId = 0): string
    {
        $candidate = $base;
        $suffix = 2;
        while (R::count('inspection', ' external_number = ? AND id != ? ', [$candidate, $ignoreId]) > 0) {
            $candidate = $base . '-' . $suffix++;
        }
        return $candidate;
    }

    public static function create(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $user = current_user();
        $selection = InspectionTypeService::permittedTypeForUser($user, (string) ($_REQUEST['inspection_type_code'] ?? InspectionTypeService::ELECTRICAL));
        if ($selection['selected'] === null) {
            $_SESSION['fehlermeldung'] = (string) $selection['requested_permission']['message'];
            return [303, ['Location' => url_for('profil')], ''];
        }
        $typeCode = (string) $selection['selected']['code'];
        if ($selection['used_fallback']) {
            $_SESSION['meldung'] = 'Die gewünschte Prüfart ist aktuell nicht freigegeben. Stattdessen wurde „' . (string) $selection['selected']['name'] . '“ geöffnet.';
        }
        $examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
        $device = R::load('device', (int) ($params['deviceId'] ?? 0));
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Gerät nicht gefunden'];
        $expectedNumber = trim((string) $device->external_number) . '-' . date('y');
        $openImported = R::findOne('inspection', "device_id = ? AND external_number = ?
            AND source_type IN ('csv', 'json')
            AND TRIM(COALESCE(report_path, '')) = ''
            AND (result_status IN ('data_missing', 'in_progress') OR status IN ('data_missing', 'in_progress', 'pending'))
            ORDER BY id DESC", [(int) $device->id, $expectedNumber]);
        if ($openImported !== null) {
            $_SESSION['meldung'] = 'Die bereits vorhandene unvollständige Importprüfung wurde geöffnet, damit keine zweite Prüfung entsteht.';
            return [303, ['Location' => url_for('admin/pruefungen/' . (int) $openImported->id . '/bearbeiten')], ''];
        }
        $inspection = R::dispense('inspection');
        $inspection->device_id = (int) $device->id;
        $inspection->external_number = self::uniqueExternalNumber($expectedNumber);
        $inspection->dedupe_key = hash('sha256', 'manual|' . $device->id . '|' . microtime(true) . '|' . bin2hex(random_bytes(8)));
        $inspection->public_id = 'prf_' . bin2hex(random_bytes(16));
        $inspection->source_type = 'manual';
        $inspection->source_file = null;
        $inspection->test_date = date('Y-m-d');
        $inspection->examiner = $examiner;
        $inspection->next_due_date = date('Y-m-d', strtotime('+1 year'));
        $inspection->status = InspectionEvaluationService::IN_PROGRESS;
        $inspection->result_status = InspectionEvaluationService::IN_PROGRESS;
        $inspection->classification = 'native';
        $inspection->inspection_type_code = $typeCode;
        $inspection->warming_device_snapshot = (int) ($device->warming_device ?? 0);
        $inspection->catalog_version_id = InspectionTypeService::defaultCatalogId($typeCode);
        $inspection->device_attributes_snapshot_json = json_encode(InspectionTypeService::deviceAttributes((int) $device->id, $typeCode), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inspection->raw_json = '{}';
        $inspection->checklist_json = '[]';
        $inspection->measurements_json = '[]';
        $inspection->created_at = date(DATE_ATOM);
        $inspection->updated_at = date(DATE_ATOM);
        R::store($inspection);
        return [303, ['Location' => url_for('admin/pruefungen/' . (int) $inspection->id . '/bearbeiten')], ''];
    }

    public static function edit(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden'];
        $device = R::load('device', (int) $inspection->device_id);
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Prüfung nicht gefunden'];
        $classification = trim((string) ($inspection->classification ?? ''));
        $isLegacy = $classification === 'legacy'
            || ($classification === '' && InspectionMigrationService::classification($inspection->export()) === 'legacy');
        if ($isLegacy) {
            if (!current_user_is_superadmin()) return forbidden_response();
            $error = null;
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $number = trim((string) ($_POST['external_number'] ?? ''));
                if ($number === '') $error = 'Die Prüfnummer darf nicht leer sein.';
                elseif (R::count('inspection', ' external_number = ? AND id != ? ', [$number, (int) $inspection->id]) > 0) $error = 'Diese Prüfnummer ist bereits vergeben.';
                if ($error === null) {
                    foreach (['external_number', 'test_date', 'next_due_date', 'examiner', 'room_snapshot', 'metadata_notes'] as $field) {
                        $inspection->$field = trim((string) ($_POST[$field] ?? ''));
                    }
                    $inspection->classification = 'legacy';
                    $inspection->updated_at = date(DATE_ATOM);
                    R::store($inspection);
                    audit_log('legacy_pruefung_metadaten_aktualisiert', ['id' => (int) $inspection->id, 'device_id' => (int) $device->id]);
                    return [303, ['Location' => url_for('admin/pruefungen/' . (int) $inspection->id)], ''];
                }
            }
            $users = R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id ');
            return [200, [], render_template('layout.php', [
                'title' => 'Legacy-Prüfung bearbeiten',
                'content' => render_template('inspection_legacy_edit.php', compact('inspection', 'device', 'users', 'error')),
            ])];
        }
        if ((string) ($inspection->inspection_type_code ?? InspectionTypeService::ELECTRICAL) === InspectionTypeService::LADDER) {
            return self::editLadder($inspection, $device);
        }
        if (trim((string) ($inspection->examiner ?? '')) === '') {
            $user = current_user();
            $inspection->examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
        }
        if (trim((string) ($inspection->next_due_date ?? '')) === '' && trim((string) ($inspection->test_date ?? '')) !== '') {
            $inspection->next_due_date = date('Y-m-d', strtotime((string) $inspection->test_date . ' +1 year'));
        }
        $error = null;
        $correctionMode = current_user_has_role('admin', 'editor');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Abrechenbarkeit wird ausschließlich in der separaten
            // Abrechnungsansicht gepflegt. Auch manipulierte Formularfelder
            // dürfen sie in der Prüfungsmaske nicht verändern.
            foreach (['protection_class', 'inspection_type', 'examiner', 'test_date', 'next_due_date', 'storage_slot', 'regie_reason', 'metadata_notes', 'customer_hint', 'cable_length_m'] as $field) $inspection->$field = trim((string) ($_POST[$field] ?? ''));
            $submittedNumber = trim((string) ($_POST['external_number'] ?? $inspection->external_number ?? ''));
            $submittedNumber = (string) (preg_replace('/-(?:\d{2}|20\d{2})$/', '', $submittedNumber) ?: $submittedNumber);
            if ($submittedNumber === '') $submittedNumber = (string) $inspection->external_number;
            $testYear = $inspection->test_date !== '' ? date('y', strtotime((string) $inspection->test_date)) : date('y');
            $numberWithYear = $submittedNumber . '-' . $testYear;
            if (R::count('inspection', ' external_number = ? AND id != ? ', [$numberWithYear, (int) $inspection->id]) > 0) $error = 'Diese Prüfnummer ist bereits vergeben.';
            $inspection->external_number = $numberWithYear;
            $cableText = str_replace(',', '.', trim((string) ($inspection->cable_length_m ?? '')));
            $cableLength = $cableText !== '' && is_numeric($cableText) ? (float) $cableText : null;
            $inspection->cable_length_m = $cableLength;
            $warmingDevice = !empty($_POST['warming_device']);
            $inspection->warming_device_snapshot = $warmingDevice ? 1 : 0;
            InspectionTypeService::saveDeviceAttributes((int) $device->id, InspectionTypeService::ELECTRICAL, [
                'cable_length_m' => $cableLength ?? '',
                'warming_device' => $warmingDevice,
            ]);
            R::exec('UPDATE device SET warming_device = ? WHERE id = ?', [$warmingDevice ? 1 : 0, (int) $device->id]);
            $inspection->device_attributes_snapshot_json = json_encode(InspectionTypeService::deviceAttributes((int) $device->id, InspectionTypeService::ELECTRICAL), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $inspection->rsl_limit_ohm = InspectionEvaluationService::rslLimit($cableLength);
            $inspection->inspection_type = ['I' => 'Schutzklasse I', 'II' => 'Schutzklasse II', 'III' => 'Schutzklasse III', 'Kabel' => 'Kabelprüfung'][$inspection->protection_class] ?? $inspection->inspection_type;
            if (!current_user_has_role('admin')) {
                $user = current_user();
                $inspection->examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
            }
            $inspection->regie_minutes = max(0, (int) ($_POST['regie_minutes'] ?? 0));
            $checklist = is_array($_POST['checklist'] ?? null) ? array_map(static fn($value): string => in_array((string) $value, ['ja', 'ok', 'nein'], true) ? ((string) $value === 'ok' ? 'ja' : (string) $value) : '', $_POST['checklist']) : [];
            $inspection->checklist_json = json_encode($checklist, JSON_UNESCAPED_UNICODE);
            $complete = ($_POST['complete'] ?? '') === '1';
            if ($error !== null) {
                // Keep submitted values visible in the correction form.
            } elseif ($complete && ($inspection->protection_class === '' || $inspection->inspection_type === '' || $inspection->examiner === '')) {
                $error = 'Für den Abschluss fehlen Schutzklasse oder Prüfer.';
            } elseif ($complete && !examiner_has_report_signature((string) $inspection->examiner)) {
                $error = 'Der eingetragene Prüfer hat keine hinterlegte Unterschrift. Die Prüfung kann erst danach abgeschlossen werden.';
            } else {
                $inspection->classification = trim((string) ($inspection->classification ?? '')) ?: 'native';
                $inspection->warming_device_snapshot = !empty($_POST['warming_device']) ? 1 : 0;
                $catalogId = (int) ($inspection->catalog_version_id ?? 0);
                if ($catalogId <= 0) $catalogId = InspectionTypeService::defaultCatalogId(InspectionTypeService::ELECTRICAL);
                $inspection->catalog_version_id = $catalogId;
                $inspection->updated_at = date(DATE_ATOM);
                R::store($inspection);
                self::storeChecklistAnswers((int) $inspection->id, $catalogId, $checklist);
                if (InspectionDataService::measurements((int) $inspection->id) === []) {
                    $legacyMeasurements = json_decode((string) ($inspection->measurements_json ?? ''), true);
                    if (is_array($legacyMeasurements) && $legacyMeasurements !== []) {
                        InspectionDataService::replaceMeasurements((int) $inspection->id, $legacyMeasurements, $inspection->export());
                    }
                }
                $evaluation = InspectionEvaluationService::evaluate(
                    $inspection->export(),
                    InspectionDataService::answers((int) $inspection->id),
                    InspectionDataService::measurements((int) $inspection->id),
                    $complete
                );
                $inspection->result_status = $evaluation['status'];
                $inspection->result_reason_code = $evaluation['reason_code'];
                $inspection->result_reason_text = $evaluation['reason'];
                $inspection->status = InspectionEvaluationService::isCompleted($evaluation['status']) ? 'completed' : $evaluation['status'];
                $failedAction = trim((string) ($_POST['failed_action'] ?? ''));
                if ($complete && $evaluation['status'] === InspectionEvaluationService::FAILED && !in_array($failedAction, ['blocked', 'repair', 'disposed'], true)) {
                    $error = 'Bei einer nicht bestandenen Prüfung bitte Sperre, Reparatur oder Entsorgung dokumentieren.';
                }
                $inspection->failed_action = $failedAction;
                R::store($inspection);
                DeviceFindingService::syncCustomerHint((int) $device->id, (int) $inspection->id, InspectionTypeService::ELECTRICAL, (string) $inspection->customer_hint);
                if ($complete && $evaluation['status'] === InspectionEvaluationService::DATA_MISSING) {
                    $error = $evaluation['reason'] . ($evaluation['missing'] !== [] ? ' Fehlend: ' . implode(', ', $evaluation['missing']) . '.' : '');
                } elseif ($error === null && $complete && InspectionEvaluationService::reportAllowed($evaluation['status'], (string) $inspection->classification) && class_exists('ReportController')) {
                    $relativeReport = 'reports/current/' . (int) $inspection->id . '.pdf';
                    $reportPath = app_data_root() . '/' . $relativeReport;
                    if (!is_dir(dirname($reportPath))) mkdir(dirname($reportPath), 0770, true);
                    if (file_put_contents($reportPath, ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . (string) $inspection->external_number, ReportController::inspectionPdfBranding($device)), LOCK_EX) === false) {
                        throw new RuntimeException('Der Prüfbericht konnte nicht gespeichert werden.');
                    }
                    $inspection->report_path = $relativeReport;
                    R::store($inspection);
                    InspectionDataService::registerReportAsset((int) $inspection->id, 'generated', $reportPath, true);
                }
                if ($error !== null) {
                    $users = R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id ');
                    $canChooseOtherExaminer = current_user_has_role('admin');
                    $inspectionMedia = DeviceMediaService::forInspection((int) $inspection->id);
                    $companionSession = InspectionCompanionService::activeForInspection((int) $inspection->id, (int) current_user()->id);
                    if ($companionSession !== []) $companionSession['token'] = (string) ($_SESSION['inspection_companion_tokens'][(int) $inspection->id] ?? '');
                    return [422, [], render_template('layout.php', ['title' => 'Prüfung bearbeiten', 'content' => render_template('inspection_edit.php', compact('inspection', 'device', 'users', 'error', 'canChooseOtherExaminer', 'inspectionMedia', 'companionSession'))])];
                }
                $target = $complete
                    ? 'admin/pruefungen/' . (int) $inspection->id
                    : 'admin/pruefungen/' . (int) $inspection->id . '/bearbeiten';
                return [303, ['Location' => url_for($target)], ''];
            }
        }
        $users = R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id ');
        $canChooseOtherExaminer = current_user_has_role('admin');
        $inspectionMedia = DeviceMediaService::forInspection((int) $inspection->id);
        $companionSession = InspectionCompanionService::activeForInspection((int) $inspection->id, (int) current_user()->id);
        if ($companionSession !== []) $companionSession['token'] = (string) ($_SESSION['inspection_companion_tokens'][(int) $inspection->id] ?? '');
        return [200, [], render_template('layout.php', ['title' => 'Prüfung bearbeiten', 'content' => render_template('inspection_edit.php', compact('inspection', 'device', 'users', 'error', 'canChooseOtherExaminer', 'inspectionMedia', 'companionSession'))])];
    }

    /** @param array<string,string> $checklist */
    private static function storeChecklistAnswers(int $inspectionId, int $catalogId, array $checklist): void
    {
        $catalog = R::getAll("SELECT * FROM inspection_catalog_item WHERE version_id = ? AND input_type = 'boolean' ORDER BY sort_order, id", [$catalogId]);
        $sourceKeys = [
            'identification' => 'stecker',
            'visual_label' => 'label',
            'visual_cable' => 'leitung',
            'visual_housing' => 'gehaeuse',
            'function' => 'funktion',
            'safe_operation' => 'safe_operation',
            'customer_notice' => 'customer_notice',
        ];
        $answers = [];
        foreach ($catalog as $item) {
            $sourceKey = $sourceKeys[(string) $item['item_key']] ?? (string) $item['item_key'];
            $value = trim((string) ($checklist[$sourceKey] ?? ''));
            $answers[] = [
                'item_key' => (string) $item['item_key'],
                'category' => (string) $item['category'],
                'question_snapshot' => (string) $item['question'],
                'criterion_snapshot' => (string) $item['criterion'],
                'answer_value' => $value,
                'outcome' => InspectionEvaluationService::normalizeOutcome($value),
                'required' => (int) $item['required'],
                'sort_order' => (int) $item['sort_order'],
            ];
        }
        InspectionDataService::replaceAnswers($inspectionId, $answers, $catalogId);
    }

    /** Dedicated server-side ladder workflow; device attributes are retained as an inspection snapshot. */
    private static function editLadder(object $inspection, object $device): array
    {
        $user = current_user();
        $error = null;
        $catalogId = (int) ($inspection->catalog_version_id ?: InspectionTypeService::defaultCatalogId(InspectionTypeService::LADDER));
        $inspection->catalog_version_id = $catalogId;
        $attributes = InspectionTypeService::deviceAttributes((int) $device->id, InspectionTypeService::LADDER);
        $answersByKey = [];
        foreach (InspectionDataService::answers((int) $inspection->id) as $answer) $answersByKey[(string) $answer['item_key']] = $answer;
        $catalog = R::getAll('SELECT * FROM inspection_catalog_item WHERE version_id = ? ORDER BY sort_order, id', [$catalogId]);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $permission = InspectionTypeService::permissionForUser($user, InspectionTypeService::LADDER);
            if (!$permission['allowed']) $error = $permission['message'];
            $inspection->test_date = trim((string) ($_POST['test_date'] ?? $inspection->test_date));
            $inspection->next_due_date = trim((string) ($_POST['next_due_date'] ?? $inspection->next_due_date));
            $inspection->examiner = trim((string) (($user->email ?? '') ?: ($user->name ?? '')));
            $attributes = is_array($_POST['attributes'] ?? null) ? $_POST['attributes'] : [];
            $values = is_array($_POST['finding'] ?? null) ? $_POST['finding'] : [];
            $remarks = is_array($_POST['remark'] ?? null) ? $_POST['remark'] : [];
            $answers = [];
            foreach ($catalog as $item) {
                $key = (string) $item['item_key'];
                $value = strtolower(trim((string) ($values[$key] ?? '')));
                $severity = in_array($value, ['green', 'orange', 'red'], true) ? $value : '';
                $answers[] = [
                    'item_key' => $key, 'category' => (string) $item['category'], 'question_snapshot' => (string) $item['question'], 'criterion_snapshot' => (string) $item['criterion'],
                    'answer_value' => $value, 'outcome' => in_array($value, ['ok', 'green'], true) ? 'passed' : (in_array($value, ['orange', 'red'], true) ? 'failed' : 'missing'),
                    'required' => (int) $item['required'], 'sort_order' => (int) $item['sort_order'], 'severity' => $severity, 'remark' => trim((string) ($remarks[$key] ?? '')),
                ];
            }
            $complete = ($_POST['complete'] ?? '') === '1';
            $failedAction = trim((string) ($_POST['failed_action'] ?? ''));
            if ($error === null) {
                InspectionTypeService::saveDeviceAttributes((int) $device->id, InspectionTypeService::LADDER, $attributes);
                $inspection->device_attributes_snapshot_json = json_encode(InspectionTypeService::deviceAttributes((int) $device->id, InspectionTypeService::LADDER), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $inspection->metadata_notes = trim((string) ($_POST['metadata_notes'] ?? ''));
            $inspection->customer_hint = trim((string) ($_POST['customer_hint'] ?? ''));
                $inspection->updated_at = date(DATE_ATOM);
                R::store($inspection);
                InspectionDataService::replaceAnswers((int) $inspection->id, $answers, $catalogId);
                $evaluation = InspectionEvaluationService::evaluate($inspection->export(), $answers, [], $complete);
                if ($complete && $evaluation['status'] === InspectionEvaluationService::FAILED && !in_array($failedAction, ['blocked', 'repair', 'disposed'], true)) {
                    $error = 'Bei einer nicht bestandenen Leiterprüfung bitte Sperre, Reparatur oder Entsorgung dokumentieren.';
                } else {
                    $inspection->result_status = $evaluation['status'];
                    $inspection->result_reason_code = $evaluation['reason_code'];
                    $inspection->result_reason_text = $evaluation['reason'];
                    $inspection->status = InspectionEvaluationService::isCompleted($evaluation['status']) ? 'completed' : $evaluation['status'];
                    $inspection->failed_action = $failedAction;
                    R::store($inspection);
                    DeviceFindingService::syncFromInspection((int) $device->id, (int) $inspection->id, InspectionTypeService::LADDER, $answers, $failedAction);
                    DeviceFindingService::syncCustomerHint((int) $device->id, (int) $inspection->id, InspectionTypeService::LADDER, (string) $inspection->customer_hint);
                    if ($failedAction === 'disposed') R::exec('UPDATE device SET archived_at = ? WHERE id = ?', [date(DATE_ATOM), (int) $device->id]);
                    if ($complete && InspectionEvaluationService::reportAllowed($evaluation['status'], (string) $inspection->classification) && class_exists('ReportController')) {
                        $relativeReport = 'reports/current/' . (int) $inspection->id . '.pdf';
                        $reportPath = app_data_root() . '/' . $relativeReport;
                        if (!is_dir(dirname($reportPath))) mkdir(dirname($reportPath), 0770, true);
                        $pdf = ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . (string) $inspection->external_number, ReportController::inspectionPdfBranding($device));
                        if (file_put_contents($reportPath, $pdf, LOCK_EX) === false) throw new RuntimeException('Der Prüfbericht konnte nicht gespeichert werden.');
                        $inspection->report_path = $relativeReport;
                        R::store($inspection);
                        InspectionDataService::registerReportAsset((int) $inspection->id, 'generated', $reportPath, true);
                    }
                    if ($complete) return [303, ['Location' => url_for('admin/pruefungen/' . (int) $inspection->id)], ''];
                }
            }
        }
        $inspectionMedia = DeviceMediaService::forInspection((int) $inspection->id);
        $companionSession = InspectionCompanionService::activeForInspection((int) $inspection->id, (int) current_user()->id);
        if ($companionSession !== []) $companionSession['token'] = (string) ($_SESSION['inspection_companion_tokens'][(int) $inspection->id] ?? '');
        return [200, [], render_template('layout.php', ['title' => 'Leiterprüfung bearbeiten', 'content' => render_template('inspection_ladder_edit.php', compact('inspection', 'device', 'attributes', 'catalog', 'answersByKey', 'error', 'inspectionMedia', 'companionSession'))])];
    }

    public static function index(array $params, bool $isHx): array
    {
        if (!current_user()) return [303, ['Location' => url_for('login.php')], ''];
        $perPage = (int) ($_GET['per_page'] ?? 50);
        if (!in_array($perPage, [25, 50, 100, 200], true)) $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        [$whereSql, $args, $filters] = self::inspectionFilter($_GET);
        $join = ' JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id ';
        $total = (int) R::getCell('SELECT COUNT(*) FROM inspection i' . $join . $whereSql, $args);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $sortSql = match ((string) ($filters['sort'] ?? 'newest')) {
            'oldest' => "COALESCE(i.test_date, '') ASC, i.id ASC",
            'device' => 'LOWER(d.name), LOWER(d.external_number), i.test_date DESC, i.id DESC',
            'status' => 'i.result_status, i.test_date DESC, i.id DESC',
            default => "COALESCE(i.test_date, '') DESC, i.id DESC",
        };
        $rows = R::getAll(
            'SELECT i.*, d.external_number AS device_number, d.name AS device_name, d.warming_device AS device_warming, c.id AS customer_id, c.name AS customer_name, s.name AS site_name, b.name AS building_name, f.name AS floor_name, r.number AS room_number, '
            . '(SELECT COUNT(*) FROM inspection_answer ia WHERE ia.inspection_id=i.id) AS answer_count, '
            . "(SELECT COUNT(*) FROM inspection_answer ia WHERE ia.inspection_id=i.id AND ia.outcome='failed') AS failed_answer_count, "
            . '(SELECT COUNT(*) FROM inspection_measurement im WHERE im.inspection_id=i.id) AS measurement_count '
            . 'FROM inspection i' . $join . $whereSql . ' ORDER BY ' . $sortSql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $args
        );
        if (!current_user_is_superadmin()) {
            foreach ($rows as &$row) {
                if ((string) ($row['classification'] ?? '') === 'migrated_import'
                    && !str_starts_with(ltrim((string) ($row['report_path'] ?? ''), '/'), 'reports/current/')
                ) {
                    $row['report_path'] = '';
                }
            }
            unset($row);
        }
        $allowedCustomers = current_user_customer_ids();
        $customers = array_values(R::findAll('customer', ' ORDER BY name '));
        if (!current_user_has_role('admin')) $customers = array_values(array_filter($customers, static fn($customer): bool => in_array((int) $customer->id, $allowedCustomers, true)));
        $sites = array_values(R::findAll('site', ' ORDER BY name '));
        if (!current_user_has_role('admin')) $sites = array_values(array_filter($sites, static fn($site): bool => in_array((int) $site->customer_id, $allowedCustomers, true)));
        $siteIds = array_fill_keys(array_map(static fn($site): int => (int) $site->id, $sites), true);
        $buildings = array_values(array_filter(R::findAll('building', ' ORDER BY name '), static fn($building): bool => current_user_has_role('admin') || isset($siteIds[(int) $building->site_id])));
        $buildingIds = array_fill_keys(array_map(static fn($building): int => (int) $building->id, $buildings), true);
        $floors = array_values(array_filter(R::findAll('floor', ' ORDER BY sort_order, name '), static fn($floor): bool => current_user_has_role('admin') || isset($buildingIds[(int) $floor->building_id])));
        $floorIds = array_fill_keys(array_map(static fn($floor): int => (int) $floor->id, $floors), true);
        $rooms = array_values(array_filter(R::findAll('room', ' ORDER BY number, name '), static fn($room): bool => current_user_has_role('admin') || isset($floorIds[(int) $room->floor_id])));
        $examiners = R::getAll("SELECT DISTINCT examiner FROM inspection WHERE TRIM(COALESCE(examiner,'')) <> '' ORDER BY LOWER(examiner)");
        $inspectionTypes = InspectionTypeService::active();
        $content = render_template('inspection_index.php', compact('rows', 'filters', 'customers', 'sites', 'buildings', 'floors', 'rooms', 'examiners', 'inspectionTypes', 'page', 'pages', 'total', 'perPage'));
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => 'Prüfungen', 'content' => $content])];
    }

    public static function bulkAction(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['inspection_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
        if ((string) ($_POST['selection_scope'] ?? '') === 'all') {
            parse_str(ltrim((string) ($_POST['filter_query'] ?? ''), '?'), $filterValues);
            [$where, $args] = self::inspectionFilter(is_array($filterValues) ? $filterValues : []);
            $join = ' JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id ';
            $ids = array_map('intval', R::getCol('SELECT i.id FROM inspection i' . $join . $where . ' ORDER BY i.id', $args));
        }
        if ($ids === []) {
            $_SESSION['fehlermeldung'] = 'Bitte mindestens eine Prüfung auswählen.';
            return [303, ['Location' => url_for('pruefungen')], ''];
        }
        $allowedIds = self::authorizedInspectionIds($ids);
        if ($allowedIds === []) return forbidden_response();
        $action = trim((string) ($_POST['bulk_action'] ?? ''));
        if (in_array($action, ['validate', 'migrate'], true)) {
            if ($action === 'migrate' && !current_user_is_superadmin()) return forbidden_response();
            BackgroundJobService::enqueue(
                'inspection_data_migration',
                ['type' => 'inspection_data_migration', 'owner_user_id' => (int) (current_user()->id ?? 0)],
                ['total' => count($allowedIds), 'checkpoint' => ['inspection_ids' => $allowedIds], 'dedupe_key' => 'inspection-selection:' . hash('sha256', implode(',', $allowedIds) . microtime(true)), 'cancellable' => false]
            );
            $_SESSION['meldung'] = count($allowedIds) . ' Prüfung(en) werden serverseitig validiert.';
        } elseif ($action === 'regenerate_reports') {
            if (!current_user_is_superadmin()) return forbidden_response();
            $marks = implode(',', array_fill(0, count($allowedIds), '?'));
            $eligible = array_map('intval', R::getCol("SELECT id FROM inspection WHERE id IN ($marks) AND COALESCE(classification,'') <> 'legacy' AND result_status IN ('passed','failed') AND " . inspection_report_signature_sql('inspection'), $allowedIds));
            if ($eligible === []) $_SESSION['fehlermeldung'] = 'Die Auswahl enthält keine freigegebenen aktuellen Berichte.';
            else {
                BackgroundJobService::enqueue('pdf_regenerate', ['type' => 'pdf_regenerate', 'inspection_ids' => $eligible, 'owner_user_id' => (int) (current_user()->id ?? 0)], ['total' => count($eligible), 'dedupe_key' => 'inspection-reports:' . hash('sha256', implode(',', $eligible) . microtime(true))]);
                $_SESSION['meldung'] = count($eligible) . ' Bericht(e) wurden zur Neuerzeugung vorgemerkt.';
            }
        } elseif ($action === 'download_reports') {
            $marks = implode(',', array_fill(0, count($allowedIds), '?'));
            $eligible = array_map('intval', R::getCol("SELECT id FROM inspection WHERE id IN ($marks) AND result_status IN ('passed','failed') AND TRIM(COALESCE(report_path,'')) <> ''", $allowedIds));
            if ($eligible === []) $_SESSION['fehlermeldung'] = 'Für die Auswahl sind keine freigegebenen Berichte verfügbar.';
            else {
                BackgroundJobService::enqueue('inspection_pdf_zip', ['type' => 'inspection_pdf_zip', 'inspection_ids' => $eligible, 'owner_user_id' => (int) (current_user()->id ?? 0)], ['total' => count($eligible), 'dedupe_key' => 'inspection-pdf-zip:' . hash('sha256', implode(',', $eligible) . microtime(true))]);
                $_SESSION['meldung'] = 'Der Berichtsexport wird im Hintergrund vorbereitet.';
            }
        } elseif ($action === 'assign_examiner') {
            $examiner = trim((string) ($_POST['examiner'] ?? ''));
            if ($examiner === '') $_SESSION['fehlermeldung'] = 'Bitte einen Prüfer auswählen.';
            else {
                $marks = implode(',', array_fill(0, count($allowedIds), '?'));
                R::exec("UPDATE inspection SET examiner=?, updated_at=? WHERE id IN ($marks) AND result_status IN ('in_progress','data_missing')", array_merge([$examiner, date(DATE_ATOM)], $allowedIds));
                audit_log('pruefungen_pruefer_zugewiesen', ['inspection_ids' => $allowedIds, 'examiner' => $examiner]);
                $_SESSION['meldung'] = 'Prüfer für offene Prüfungen aktualisiert.';
            }
        } else {
            $_SESSION['fehlermeldung'] = 'Bitte eine gültige Sammelaktion auswählen.';
        }
        return [303, ['Location' => url_for('pruefungen')], ''];
    }

    /** @param array<string,mixed> $input @return array{0:string,1:list<mixed>,2:array<string,mixed>} */
    private static function inspectionFilter(array $input): array
    {
        $filters = [
            'q' => trim((string) ($input['q'] ?? '')),
            'result_status' => trim((string) ($input['result_status'] ?? '')),
            'classification' => trim((string) ($input['classification'] ?? '')),
            'inspection_type_code' => trim((string) ($input['inspection_type_code'] ?? '')),
            'customer_id' => (int) ($input['customer_id'] ?? 0),
            'site_id' => (int) ($input['site_id'] ?? 0),
            'building_id' => (int) ($input['building_id'] ?? 0),
            'floor_id' => (int) ($input['floor_id'] ?? 0),
            'room_id' => (int) ($input['room_id'] ?? 0),
            'from' => trim((string) ($input['from'] ?? '')),
            'to' => trim((string) ($input['to'] ?? '')),
            'examiner' => trim((string) ($input['examiner'] ?? '')),
            'protection_class' => trim((string) ($input['protection_class'] ?? '')),
            'source_type' => trim((string) ($input['source_type'] ?? '')),
            'warming_device' => trim((string) ($input['warming_device'] ?? '')),
            'report_status' => trim((string) ($input['report_status'] ?? '')),
            'sort' => trim((string) ($input['sort'] ?? 'newest')),
            'per_page' => (int) ($input['per_page'] ?? 50),
        ];
        // Archived source duplicates remain visible to superadmins through
        // the diagnostic/audit trail, never through normal operational lists.
        $where = ["COALESCE(i.archived_at, '') = ''"];
        $args = [];
        if (!current_user_has_role('admin')) {
            $allowed = current_user_customer_ids();
            if ($allowed === []) $where[] = '1=0';
            else { $where[] = 'c.id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')'; array_push($args, ...$allowed); }
        }
        if (current_user_is_customer()) {
            $where[] = InspectionEvaluationService::sqlStatusExpression('i') . " IN ('passed','failed')";
        }
        if ($filters['customer_id'] > 0) { $where[] = 'c.id=?'; $args[] = $filters['customer_id']; }
        foreach (['site_id' => 's.id', 'building_id' => 'b.id', 'floor_id' => 'f.id', 'room_id' => 'r.id'] as $key => $column) {
            if ($filters[$key] > 0) { $where[] = $column . '=?'; $args[] = $filters[$key]; }
        }
        if ($filters['q'] !== '') { $like = '%' . mb_strtolower($filters['q']) . '%'; $where[] = '(LOWER(i.external_number) LIKE ? OR LOWER(d.external_number) LIKE ? OR LOWER(d.name) LIKE ? OR LOWER(i.result_reason_text) LIKE ?)'; array_push($args, $like, $like, $like, $like); }
        if (in_array($filters['result_status'], array_keys(InspectionEvaluationService::statuses()), true)) {
            $where[] = InspectionEvaluationService::sqlStatusExpression('i') . '=?';
            $args[] = $filters['result_status'];
        } elseif ($filters['result_status'] === 'open') {
            $where[] = InspectionEvaluationService::sqlStatusExpression('i') . " IN ('in_progress','data_missing')";
        }
        if (in_array($filters['classification'], ['legacy','migrated_import','native'], true)) { $where[] = 'i.classification=?'; $args[] = $filters['classification']; }
        if (InspectionTypeService::find($filters['inspection_type_code']) !== null) {
            if ($filters['inspection_type_code'] === InspectionTypeService::ELECTRICAL) {
                $where[] = "(COALESCE(i.inspection_type_code, '') IN ('', 'electrical'))";
            } else {
                $where[] = 'i.inspection_type_code = ?'; $args[] = $filters['inspection_type_code'];
            }
        }
        if ($filters['from'] !== '') { $where[] = 'i.test_date>=?'; $args[] = $filters['from']; }
        if ($filters['to'] !== '') { $where[] = 'i.test_date<=?'; $args[] = $filters['to']; }
        if ($filters['examiner'] !== '') { $where[] = 'i.examiner=?'; $args[] = $filters['examiner']; }
        if (in_array($filters['protection_class'], ['I','II','III','Kabel','Drehstrom'], true)) { $where[] = 'i.protection_class=?'; $args[] = $filters['protection_class']; }
        if (in_array($filters['source_type'], ['manual', 'csv', 'json'], true)) { $where[] = 'i.source_type=?'; $args[] = $filters['source_type']; }
        if (in_array($filters['warming_device'], ['0', '1'], true)) { $where[] = 'COALESCE(i.warming_device_snapshot,d.warming_device,0)=?'; $args[] = (int) $filters['warming_device']; }
        $usableReport = '(' . InspectionEvaluationService::sqlStatusExpression('i')
            . " IN ('passed','failed') AND TRIM(COALESCE(i.report_path,''))<>'' "
            . "AND (i.classification='legacy' OR i.report_path LIKE 'reports/current/%'))";
        if ($filters['report_status'] === 'available') $where[] = $usableReport;
        elseif ($filters['report_status'] === 'missing') $where[] = 'NOT ' . $usableReport;
        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $args, $filters];
    }

    /** @param list<int> $ids @return list<int> */
    private static function authorizedInspectionIds(array $ids): array
    {
        if ($ids === []) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $args = $ids;
        $where = "i.id IN ($marks)";
        if (!current_user_has_role('admin')) {
            $allowed = current_user_customer_ids();
            if ($allowed === []) return [];
            $where .= ' AND c.id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
            array_push($args, ...$allowed);
        }
        return array_map('intval', R::getCol('SELECT i.id FROM inspection i JOIN device d ON d.id=i.device_id LEFT JOIN room r ON r.id=d.room_id LEFT JOIN floor f ON f.id=r.floor_id LEFT JOIN building b ON b.id=f.building_id LEFT JOIN site s ON s.id=b.site_id LEFT JOIN customer c ON c.id=s.customer_id WHERE ' . $where, $args));
    }
    private static function pendingMeasurementsByDate(): array
    {
        $pending = [];
        $pendingExpression = InspectionEvaluationService::sqlStatusExpression('i');
        $inspections = R::getAll("SELECT i.id AS inspection_id, i.device_id, i.external_number AS inspection_number, i.storage_slot, i.test_date, i.measurements_json, i.result_status, i.status, d.external_number AS device_number, d.name AS device_name FROM inspection i LEFT JOIN device d ON d.id = i.device_id WHERE {$pendingExpression} IN ('in_progress','data_missing') ORDER BY CASE WHEN COALESCE(i.test_date, '') = '' THEN 1 ELSE 0 END, i.test_date DESC, i.id DESC");
        foreach ($inspections as $inspection) {
            if ((int) ($inspection['device_id'] ?? 0) <= 0) continue;
            $date = trim((string) ($inspection['test_date'] ?? '')) ?: 'ohne Datum';
            $pending[$date][] = [
                'inspection_id' => (int) $inspection['inspection_id'],
                'device_id' => (int) $inspection['device_id'],
                'number' => trim((string) ($inspection['device_number'] ?? '')) ?: trim((string) ($inspection['inspection_number'] ?? '')),
                'name' => trim((string) ($inspection['device_name'] ?? '')),
                'inspection_number' => trim((string) ($inspection['inspection_number'] ?? '')),
                'storage_slot' => trim((string) ($inspection['storage_slot'] ?? '')),
                'result_status' => InspectionEvaluationService::normalizeStatus((string) ($inspection['result_status'] ?? ''), (string) ($inspection['status'] ?? '')),
            ];
        }
        uksort($pending, static function (string $left, string $right): int {
            if ($left === 'ohne Datum') return 1;
            if ($right === 'ohne Datum') return -1;
            return strcmp($right, $left);
        });
        return $pending;
    }

    public static function import(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();

        $message = null;
        $stats = null;
        $rebuildPreview = null;
        $candidateRunId = max(0, (int) ($_GET['candidate_run'] ?? 0));
        $candidateGroups = [];
        $examinerMigrationStats = null;
        $jobs = self::phoenixJobs();
        $importLogs = self::importLogs();
        foreach ($importLogs as &$historyLog) {
            $existingInspections = [];
            foreach (($historyLog['stats']['updated_inspections'] ?? []) as &$historyInspection) {
                $current = R::load('inspection', (int) ($historyInspection['id'] ?? 0));
                if (!$current->id) continue;
                $historyInspection['number'] = (string) ($current->external_number ?? $historyInspection['number'] ?? '');
                $historyInspection['status'] = (string) ($current->result_status ?? $historyInspection['status'] ?? 'ausstehend');
                $historyInspection['storage_slot'] = (string) ($current->storage_slot ?? '');
                $device = R::load('device', (int) ($current->device_id ?? 0));
                // The rebuild removes legacy placeholder devices (for example
                // “001-23”). Import history must not resurrect them as empty
                // or misleading after-processing entries.
                if (!$device->id || mb_strlen(trim((string) ($device->external_number ?? ''))) < 6) continue;
                $historyInspection['device_number'] = (string) ($device->external_number ?? '');
                $historyInspection['device_name'] = (string) ($device->name ?? '');
                $numberStem = preg_replace('/-\d{2}(?:-\d+)?$/', '', trim((string) $historyInspection['number']));
                $slotStem = ltrim(trim((string) $historyInspection['storage_slot']), '0');
                $historyInspection['number_is_storage_slot'] = $slotStem !== '' && ltrim((string) $numberStem, '0') === $slotStem;
                $existingInspections[] = $historyInspection;
            }
            $historyLog['stats']['updated_inspections'] = $existingInspections;
        }
        unset($historyLog, $historyInspection);
        $cron = self::cronStatus();
        $pendingMeasurementsByDate = self::pendingMeasurementsByDate();
        $examinerUsers = array_map(static fn($user): array => ['id' => (int) $user->id, 'label' => trim((string) ($user->name ?? '')) !== '' ? trim((string) $user->name) : trim((string) ($user->email ?? '')), 'value' => trim((string) ($user->email ?? $user->name ?? ''))], R::findAll('oauthuser', ' ORDER BY LOWER(name), LOWER(email), id '));
        $phoenixJob = trim((string) ($_GET['phoenix_job'] ?? ''));
        $activeJob = null;
        if ($phoenixJob !== '') {
            $job = self::readPhoenixJob($phoenixJob);
            $activeJob = $job;
            $jobLabel = BackgroundJobService::label((string) ($job['type'] ?? 'phoenix_sync'));
            if (($job['state'] ?? '') === 'done') { $stats = $job['stats'] ?? null; $message = $jobLabel . ' abgeschlossen.'; }
            elseif (($job['state'] ?? '') === 'error') $message = $jobLabel . ' fehlgeschlagen: ' . (string) ($job['error'] ?? 'Unbekannter Fehler');
            elseif (($job['state'] ?? '') === 'cancelled' || ($job['state'] ?? '') === 'cancel_requested') $message = $jobLabel . ' wurde abgebrochen.';
            else $message = $jobLabel . ' läuft noch im Hintergrund. Diese Seite aktualisiert sich automatisch.';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_POST['action'] ?? '') === 'migrate_examiner_attribution') {
                if (!current_user_is_superadmin()) return forbidden_response();
                $payload = ['type' => 'examiner_migration', 'owner_user_id' => (int) (current_user()->id ?? 0)];
                $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE test_date IS NOT NULL AND COALESCE(source_type, '') IN ('json', 'csv')");
                BackgroundJobService::enqueue('examiner_migration', $payload, ['total' => $total, 'dedupe_key' => 'examiner-migration:v2', 'cancellable' => false]);
                $message = 'Die Prüferzuordnung wurde als Hintergrund-Migration vorgemerkt.';
            }
            if (($_POST['action'] ?? '') === 'regenerate_new_reports') {
                if (!current_user_is_superadmin()) return forbidden_response();
                $ids = array_map('intval', R::getCol("SELECT id FROM inspection WHERE result_status IN ('passed','failed') AND classification = 'migrated_import' AND " . inspection_report_signature_sql('inspection') . ' ORDER BY id'));
                if ($ids === []) { $message = 'Keine importierten, abgeschlossenen Prüfungen für die Neuerzeugung gefunden.'; }
                else {
                    $job = BackgroundJobService::enqueue('pdf_regenerate', ['type' => 'pdf_regenerate', 'inspection_ids' => $ids, 'owner_user_id' => (int) (current_user()->id ?? 0)], ['total' => count($ids), 'dedupe_key' => 'pdf-regenerate:all-current']);
                    $id = (string) $job['id'];
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
                }
            }
            if (($_POST['action'] ?? '') === 'pending_measurement_import' && isset($_FILES['measurement_csv']) && is_array($_FILES['measurement_csv'])) {
                try {
                    $date = trim((string) ($_POST['measurement_date'] ?? ''));
                    $tmp = (string) ($_FILES['measurement_csv']['tmp_name'] ?? '');
                    if ($tmp === '' || !is_uploaded_file($tmp)) throw new InvalidArgumentException('CSV-Datei fehlt.');
                    $uploadRoot = app_data_root() . '/uploads/pending-measurements';
                    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0770, true) && !is_dir($uploadRoot)) throw new RuntimeException('Uploadverzeichnis konnte nicht angelegt werden.');
                    $storedFile = $uploadRoot . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.csv';
                    if (!move_uploaded_file($tmp, $storedFile)) throw new RuntimeException('CSV-Datei konnte nicht für die Hintergrundverarbeitung gespeichert werden.');
                    $job = BackgroundJobService::enqueue('pending_measurement_import', [
                        'type' => 'pending_measurement_import',
                        'csv_path' => $storedFile,
                        'test_date' => $date,
                        'owner_user_id' => (int) (current_user()->id ?? 0),
                    ], ['cancellable' => true]);
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . rawurlencode((string) $job['id']))], ''];
                } catch (Throwable $exception) { $message = 'Messdatenimport nicht möglich: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'directory_import_job') {
                try {
                    $directoryJob = trim((string) ($_POST['directory'] ?? ''));
                    if ($directoryJob === '') throw new InvalidArgumentException('Bitte ein Importverzeichnis angeben.');
                    $defaults = ['inspection_type' => trim((string) ($_POST['default_inspection_type'] ?? '')), 'examiner' => trim((string) ($_POST['default_examiner'] ?? '')), 'next_due_date' => trim((string) ($_POST['default_next_due_date'] ?? '')), 'next_due_offset_days' => (int) ($_POST['default_next_due_offset_days'] ?? 0), 'test_date' => trim((string) ($_POST['default_test_date'] ?? ''))];
                    $rules = json_decode((string) ($_POST['import_rules'] ?? '[]'), true); if (is_array($rules)) $defaults['import_rules'] = $rules;
                    $job = BackgroundJobService::enqueue('directory_import', ['type' => 'directory_import', 'directory' => $directoryJob, 'reports_directory' => trim((string) ($_POST['reports_directory'] ?? '')), 'defaults' => $defaults, 'owner_user_id' => (int) (current_user()->id ?? 0)]);
                    $id = (string) $job['id'];
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
                } catch (Throwable $exception) { $message = 'Import-Job konnte nicht gestartet werden: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'import_candidate_start') {
                if (!current_user_is_superadmin()) return forbidden_response();
                try {
                    $directory = trim((string) ($_POST['rebuild_directory'] ?? ''));
                    if ($directory === '') throw new InvalidArgumentException('Bitte das kuratierte Quellenverzeichnis angeben.');
                    $rebuildPreview = ['audit' => (new ImportSourceAuditService())->inspect($directory), 'reset' => ImportedInspectionResetService::preview(), 'directory' => realpath($directory) ?: $directory];
                    if (trim((string) ($_POST['rebuild_confirmation'] ?? '')) !== 'KANDIDATEN NEU AUFBAUEN') throw new InvalidArgumentException('Bitte die Bestätigung exakt eingeben.');
                    $job = BackgroundJobService::enqueue('import_candidate_rebuild', ['type' => 'import_candidate_rebuild', 'directory' => $rebuildPreview['directory'], 'owner_user_id' => (int) (current_user()->id ?? 0)], ['total' => 4, 'cancellable' => false]);
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . rawurlencode((string) $job['id']))], ''];
                } catch (Throwable $exception) { $message = 'Quellen-Neuaufbau konnte nicht vorbereitet werden: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'import_candidate_decide') {
                if (!current_user_is_superadmin()) return forbidden_response();
                try {
                    $candidateRunId = max(0, (int) ($_POST['candidate_run_id'] ?? 0));
                    $group = trim((string) ($_POST['group_key'] ?? ''));
                    $action = trim((string) ($_POST['decision'] ?? ''));
                    $fields = is_array($_POST['field_source'] ?? null) ? array_map('strval', $_POST['field_source']) : [];
                    $result = (new ImportCandidateRebuildService())->decide($candidateRunId, $group, $action, $fields, (int) (current_user()->id ?? 0));
                    $message = 'Kandidat entschieden: ' . (int) ($result['imported'] ?? 0) . ' importiert, ' . (int) ($result['updated_manual'] ?? 0) . ' Prüfweb-Prüfung aktualisiert.';
                } catch (Throwable $exception) { $message = 'Kandidatenentscheidung nicht möglich: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'import_candidate_recheck') {
                if (!current_user_is_superadmin()) return forbidden_response();
                try {
                    $candidateRunId = max(0, (int) ($_POST['candidate_run_id'] ?? 0));
                    $result = (new ImportCandidateRebuildService())->recheckReviewed($candidateRunId);
                    $message = 'Sichere Kandidaten erneut bewertet: ' . (int) ($result['manual_kept'] ?? 0) . ' Prüfweb-Prüfung(en) ergänzt, ' . (int) ($result['automatic'] ?? 0) . ' Import(e) übernommen.';
                } catch (Throwable $exception) { $message = 'Kandidaten konnten nicht erneut bewertet werden: ' . $exception->getMessage(); }
            }
            if (in_array((string) ($_POST['action'] ?? ''), ['import_rebuild_preview', 'import_rebuild_start'], true)) {
                $message = 'Der direkte Reset wurde ersetzt. Bitte den Bereich „Importbestand als Kandidaten neu aufbauen“ verwenden.';
            }
            if (($_POST['action'] ?? '') === 'phoenix_sync') {
                try {
                    $credentials = PhoenixSyncService::serverCredentials();
                    if ($credentials['token'] === '' || $credentials['customer_id'] === '') throw new RuntimeException('Phoenix-Zugang ist noch nicht vollständig konfiguriert.');
                    $job = BackgroundJobService::enqueue('phoenix_sync', ['type' => 'phoenix_sync', 'owner_user_id' => (int) (current_user()->id ?? 0)]);
                    $id = (string) $job['id'];
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
                } catch (Throwable $exception) { $message = 'Phoenix-Sync konnte nicht gestartet werden: ' . $exception->getMessage(); }
            }
            if (($_POST['action'] ?? '') === 'phoenix_report_sync') {
                try {
                    $credentials = PhoenixSyncService::serverCredentials();
                    if ($credentials['token'] === '' || $credentials['customer_id'] === '') throw new RuntimeException('Phoenix-Zugang ist noch nicht vollständig konfiguriert.');
                    $total = (int) R::getCell("SELECT COUNT(*) FROM inspection WHERE source_type IN ('csv', 'json') AND result_status IN ('passed', 'failed')");
                    $job = BackgroundJobService::enqueue('phoenix_report_sync', ['type' => 'phoenix_report_sync', 'owner_user_id' => (int) (current_user()->id ?? 0)], ['total' => $total]);
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . rawurlencode((string) $job['id']))], ''];
                } catch (Throwable $exception) { $message = 'Phoenix-Berichts-Sync konnte nicht gestartet werden: ' . $exception->getMessage(); }
            }
            if (isset($_FILES['csv']) && is_array($_FILES['csv'])) {
                try {
                    $odsUpload = is_array($_FILES['ods'] ?? null) ? $_FILES['ods'] : ['error' => UPLOAD_ERR_NO_FILE];
                    $directory = self::savePairUpload($_FILES['csv'], $odsUpload);
                    $defaults = ['inspection_type' => trim((string) ($_POST['default_inspection_type'] ?? '')), 'examiner' => trim((string) ($_POST['default_examiner'] ?? '')), 'next_due_date' => trim((string) ($_POST['default_next_due_date'] ?? '')), 'next_due_offset_days' => (int) ($_POST['default_next_due_offset_days'] ?? 0), 'test_date' => trim((string) ($_POST['default_test_date'] ?? ''))];
                    $rules = json_decode((string) ($_POST['import_rules'] ?? '[]'), true);
                    if (is_array($rules)) $defaults['import_rules'] = $rules;
                    $job = BackgroundJobService::enqueue('directory_import', [
                        'type' => 'directory_import',
                        'directory' => $directory,
                        'defaults' => $defaults,
                        'owner_user_id' => (int) (current_user()->id ?? 0),
                    ]);
                    return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . rawurlencode((string) $job['id']))], ''];
                } catch (Throwable $exception) {
                    $message = 'Upload nicht möglich: ' . $exception->getMessage();
                }
            }
        }

        $pendingMeasurementsByDate = self::pendingMeasurementsByDate();
        $importLogs = self::importLogs();
        if ($candidateRunId <= 0) $candidateRunId = (int) R::getCell("SELECT id FROM importrebuildrun ORDER BY id DESC LIMIT 1");
        $candidateRebuildRunning = false;
        foreach ($jobs as $backgroundJob) {
            if ((string) ($backgroundJob['type'] ?? '') !== 'import_candidate_rebuild') continue;
            if (in_array((string) ($backgroundJob['state'] ?? ''), ['queued', 'running', 'cancel_requested'], true)) { $candidateRebuildRunning = true; break; }
        }
        if ($candidateRunId > 0 && current_user_is_superadmin() && !$candidateRebuildRunning) $candidateGroups = (new ImportCandidateRebuildService())->groups($candidateRunId, 'unresolved', 100);

        return [200, [], render_template('layout.php', [
            'title' => 'Prüfungen importieren',
            'content' => render_template('inspection_import.php', [
                'message' => $message,
                'stats' => $stats,
                'jobs' => $jobs,
                'importLogs' => $importLogs,
                'cron' => $cron,
                'examinerUsers' => $examinerUsers,
                'pendingMeasurementsByDate' => $pendingMeasurementsByDate,
                'rebuildPreview' => $rebuildPreview,
                'candidateRunId' => $candidateRunId,
                'candidateGroups' => $candidateGroups,
                'candidateRebuildRunning' => $candidateRebuildRunning,
                'activeJob' => $activeJob,
            ]),
        ])];
    }

    public static function cancelPhoenixJob(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $id = (string) ($params['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) return [400, [], 'Ungültige Job-ID.'];
        BackgroundJobService::requestCancellation($id);
        return [303, ['Location' => url_for('admin/pruefungen/import?phoenix_job=' . $id)], ''];
    }

    public static function phoenixStatus(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return [403, ['Content-Type' => 'application/json'], '{}'];
        return [200, ['Content-Type' => 'application/json; charset=utf-8'], json_encode(self::readPhoenixJob((string) ($params['id'] ?? '')), JSON_UNESCAPED_UNICODE)];
    }

    public static function archivePhoenixJob(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $id = (string) ($params['id'] ?? '');
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) return [400, [], 'Ungültige Job-ID.'];
        $status = BackgroundJobService::find($id);
        if ($status !== null && in_array((string) ($status['state'] ?? ''), ['queued', 'running', 'cancel_requested'], true)) return [409, [], 'Laufende Aufgaben können nicht archiviert werden.'];
        BackgroundJobService::markRead($id, (int) (current_user()->id ?? 0));
        return [303, ['Location' => url_for('admin/pruefungen/import')], ''];
    }

    private static function readPhoenixJob(string $id): array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $id)) return ['state' => 'error', 'error' => 'Ungültige Job-ID.'];
        return BackgroundJobService::find($id) ?? ['state' => 'error', 'error' => 'Aufgabe nicht gefunden.'];
    }

    private static function phoenixJobs(): array
    {
        return BackgroundJobService::latest(20);
    }

    private static function importLogs(): array
    {
        $root = app_data_root() . '/import-logs'; $logs = [];
        foreach (array_reverse(glob($root . '/*.json') ?: []) as $path) { $log = json_decode((string) file_get_contents($path), true); if (is_array($log)) $logs[] = $log; if (count($logs) >= 20) break; }
        return $logs;
    }

    private static function saveImportLog(string $type, array $stats): void
    {
        $root = dirname(__DIR__) . '/data/' . app_storage_namespace() . '/import-logs';
        if (!is_dir($root)) mkdir($root, 0770, true);
        file_put_contents($root . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json', json_encode(['created_at' => date(DATE_ATOM), 'type' => $type, 'stats' => $stats], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private static function cronStatus(): array
    {
        $path = sys_get_temp_dir() . '/pruefapp-phoenix-jobs/cron-heartbeat.json';
        $data = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $timestamp = isset($data['last_run']) ? strtotime((string) $data['last_run']) : (is_file($path) ? filemtime($path) : 0);
        $displayTime = $timestamp > 0 ? (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('Y-m-d H:i:s T') : null;
        return ['last_run' => $displayTime, 'age' => $timestamp > 0 ? max(0, time() - $timestamp) : null, 'healthy' => $timestamp > 0 && (time() - $timestamp) <= 300];
    }

    private static function savePairUpload(array $csv, array $ods): string
    {
        if (($csv['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Bitte eine CSV-Datei auswählen.');
        $odsPresent = ($ods['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if (!$odsPresent && ($ods['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) throw new RuntimeException('ODS-Datei konnte nicht hochgeladen werden.');
        $csvName = basename((string) ($csv['name'] ?? ''));
        $odsName = basename((string) ($ods['name'] ?? ''));
        if (!preg_match('/\.csv$/i', $csvName)) throw new RuntimeException('Die Messdaten müssen eine CSV-Datei sein.');
        if ($odsPresent && (!preg_match('/\.ods$/i', $odsName) || strcasecmp(pathinfo($csvName, PATHINFO_FILENAME), pathinfo($odsName, PATHINFO_FILENAME)) !== 0)) {
            throw new RuntimeException('CSV und ODS müssen denselben Dateinamen (unterschiedliche Endung) haben.');
        }
        $directory = sys_get_temp_dir() . '/pruefapp-upload-' . bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700, true)) throw new RuntimeException('Temporäres Upload-Verzeichnis konnte nicht angelegt werden.');
        if (!move_uploaded_file((string) $csv['tmp_name'], $directory . '/' . $csvName)) throw new RuntimeException('CSV-Upload konnte nicht gespeichert werden.');
        if ($odsPresent && !move_uploaded_file((string) $ods['tmp_name'], $directory . '/' . $odsName)) throw new RuntimeException('ODS-Upload konnte nicht gespeichert werden.');
        return $directory;
    }

    public static function report(array $params, bool $isHx): array
    {
        if (!current_user()) return [403, [], ''];
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        $reportDevice = $inspection->id ? R::load('device', (int) $inspection->device_id) : null;
        if (!$inspection->id || !$reportDevice || !$reportDevice->id || !current_user_can_access_customer(device_customer_id($reportDevice))) return [404, [], 'Bericht nicht gefunden'];
        $status = InspectionEvaluationService::normalizeStatus((string) $inspection->result_status, (string) $inspection->status);
        $classification = trim((string) ($inspection->classification ?? ''));
        // Source reports are authoritative. Resolve one synchronously here so
        // users never have to wait for a background migration before opening
        // an available Phoenix report.
        $originalPath = InspectionDataService::activateImportedOriginalReport($inspection, $reportDevice);
        $relative = trim((string) ($inspection->report_path ?? ''));
        if (!InspectionEvaluationService::reportPathAllowed($status, $classification, $relative, current_user_is_superadmin())) {
            return [404, [], 'Der neue Prüfbericht wurde noch nicht erzeugt.'];
        }
        $root = app_data_root();
        $path = $originalPath !== '' ? $originalPath : ($relative !== '' ? realpath($root . '/' . ltrim($relative, '/')) : false);
        $rootReal = realpath($root);
        // Importierte Altberichte liegen bewusst außerhalb der Webanwendung.
        // Das Verzeichnis ist ein ausdrücklich erlaubter, nicht beschreibbarer
        // Berichtsspeicher.
        if ($path === false && $relative !== '') {
            $legacyRoot = realpath('/var/www/berichte');
            $legacyPath = realpath('/var/www/berichte/' . basename($relative));
            if ($legacyRoot !== false && $legacyPath !== false && str_starts_with($legacyPath, $legacyRoot . DIRECTORY_SEPARATOR)) $path = $legacyPath;
        }
        $allowedPath = $path !== false && $rootReal !== false && str_starts_with($path, $rootReal . DIRECTORY_SEPARATOR);
        if (!$allowedPath && $path !== false) { $legacyRoot = realpath('/var/www/berichte'); $allowedPath = $legacyRoot !== false && str_starts_with($path, $legacyRoot . DIRECTORY_SEPARATOR); }
        if (!$inspection->id || $path === false || !$allowedPath || !is_file($path)) return [404, [], 'Bericht nicht gefunden'];
        return [200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . basename($path) . '"'], (string) file_get_contents($path)];
    }

    public static function regenerateReport(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        $device = $inspection->id ? R::load('device', (int) $inspection->device_id) : null;
        if (!$inspection->id || !$device || !$device->id) return [404, [], 'Prüfung nicht gefunden.'];
        if ((string) ($inspection->classification ?? '') === 'legacy') return [409, [], 'Legacy-Berichte werden nicht neu erzeugt.'];
        if (InspectionDataService::originalReportPath((int) $inspection->id) !== '') return [409, [], 'Der Originalbericht aus dem Quellsystem ist aktiv und wird nicht überschrieben.'];
        if (!InspectionEvaluationService::reportAllowed((string) $inspection->result_status, (string) $inspection->classification)) return [409, [], 'Nur bestandene oder nicht bestandene Prüfungen erhalten einen Bericht.'];
        if (!examiner_has_report_signature((string) $inspection->examiner)) return [409, [], 'Der eingetragene Prüfer hat keine hinterlegte Unterschrift. Der Bericht wird erst nach dem Speichern der Unterschrift erzeugt.'];
        $relative = 'reports/current/' . (int) $inspection->id . '.pdf';
        $path = app_data_root() . '/' . $relative;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0770, true);
        $pdf = ReportController::renderPdf(ReportController::inspectionPdfRows($inspection, $device), 'Prüfbericht ' . (string) $inspection->external_number, ReportController::inspectionPdfBranding($device));
        if (file_put_contents($path, $pdf, LOCK_EX) === false) return [500, [], 'PDF konnte nicht gespeichert werden.'];
        $inspection->report_path = $relative; $inspection->updated_at = date(DATE_ATOM); R::store($inspection);
        InspectionDataService::registerReportAsset((int) $inspection->id, 'generated', $path, true);
        return [303, ['Location' => url_for('admin/pruefungen/' . (int) $inspection->id)], ''];
    }

    public static function detail(array $params, bool $isHx): array
    {
        if (!current_user()) return [403, [], ''];
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id || (trim((string) ($inspection->archived_at ?? '')) !== '' && !current_user_is_superadmin())) return [404, [], 'Prüfung nicht gefunden'];
        $device = R::load('device', (int) $inspection->device_id);
        if (!$device->id || !current_user_can_access_customer(device_customer_id($device))) return [404, [], 'Prüfung nicht gefunden'];
        if (current_user_is_customer() && !InspectionEvaluationService::isCompleted((string) $inspection->result_status)) return [404, [], 'Prüfung nicht gefunden'];
        if ((int) ($device->room_id ?? 0) > 0) {
            $room = R::load('room', (int) $device->room_id);
            $floor = R::load('floor', (int) ($room->floor_id ?? 0));
            $area = R::load('area', (int) ($room->area_id ?? 0));
            if ($room->id) $inspection->room_snapshot = class_exists('StructureController') ? StructureController::roomIdentifier($room, $floor, $area) : (string) ($room->name ?: $room->number);
        }
        $raw = json_decode((string) ($inspection->raw_json ?? ''), true) ?: [];
        $billingInvoice = R::getRow('SELECT bi.invoice_id, bi.quantity, bi.active, inv.sevdesk_invoice_id, inv.sevdesk_invoice_number, inv.sevdesk_url, inv.invoice_number, inv.invoice_date, inv.status FROM billinginvoiceitem bi JOIN billinginvoice inv ON inv.id=bi.invoice_id WHERE bi.inspection_id = ? AND bi.active = 1 ORDER BY inv.id DESC LIMIT 1', [(int) $inspection->id]);
        $billingHistory = R::getAll('SELECT bi.invoice_id, bi.active, bi.assigned_at, bi.deactivated_at, bi.deactivation_reason, inv.invoice_number, inv.sevdesk_invoice_number FROM billinginvoiceitem bi JOIN billinginvoice inv ON inv.id=bi.invoice_id WHERE bi.inspection_id = ? ORDER BY bi.id DESC', [(int) $inspection->id]);
        $measurements = InspectionDataService::measurements((int) $inspection->id);
        $checklist = InspectionDataService::answers((int) $inspection->id);
        $diagnostics = current_user_is_superadmin() ? InspectionDataService::diagnostics((int) $inspection->id) : [];
        $findings = DeviceFindingService::openForDevice((int) $device->id);
        $inspectionMedia = DeviceMediaService::forInspection((int) $inspection->id);
        $inspectionType = InspectionTypeService::find((string) ($inspection->inspection_type_code ?? InspectionTypeService::ELECTRICAL));
        if ($measurements === [] && trim((string) ($inspection->classification ?? '')) === '') {
            $measurements = self::normalizeImportedMeasurements(json_decode((string) ($inspection->measurements_json ?? ''), true) ?: [], (string) ($inspection->result_status ?? ''));
        }
        return [200, [], render_template('layout.php', ['title' => 'Prüfung ' . (string) $inspection->external_number, 'content' => render_template('inspection_detail.php', compact('inspection', 'device', 'raw', 'measurements', 'checklist', 'diagnostics', 'billingInvoice', 'billingHistory', 'findings', 'inspectionType', 'inspectionMedia'))])];
    }

    /** Repair legacy Benning rows created before decimal-comma columns were fixed. */
    public static function normalizeImportedMeasurements(array $measurements, string $overallResult): array
    {
        $overallResult = trim($overallResult) !== '' ? $overallResult : 'bestanden';
        $normalized = [];
        foreach ($measurements as $measurement) {
            if (!is_array($measurement)) continue;
            $name = strtoupper(trim((string) ($measurement['name'] ?? '')));
            $value = trim((string) ($measurement['value'] ?? ''));
            $unit = trim((string) ($measurement['unit'] ?? ''));
            $result = trim((string) ($measurement['result'] ?? ''));
            if ($name === 'RPE' && preg_match('/^\d+$/', $unit) === 1 && in_array(strtolower($result), ['ohm', 'ω'], true)) {
                $measurement['value'] = $value . '.' . $unit;
                $measurement['unit'] = 'Ohm';
                $measurement['result'] = $overallResult;
            } elseif ($name === 'IEA' && preg_match('/^\d+$/', $unit) === 1 && strtolower($result) === 'ma') {
                $measurement['value'] = $value . '.' . $unit;
                $measurement['unit'] = 'mA';
                $measurement['result'] = $overallResult;
            } elseif ($name === 'IPE' && in_array(strtolower($value), ['bestanden', 'ok', 'gut'], true) && $unit === '') {
                $measurement['value'] = '';
                $measurement['result'] = $value;
            } elseif ($name === 'RISO' && $unit === 'bestanden' && preg_match('/^[<>]?\d+(?:[.,]\d+)?$/', $result) === 1) {
                $measurement['value'] = $result;
                $measurement['unit'] = 'MOhm';
                $measurement['result'] = $overallResult;
            } elseif ($name === 'KABEL' && $value === 'MOhm' && $unit === 'bestanden' && preg_match('/^\d+V$/i', $result) === 1) {
                $measurement['name'] = 'RISO Spannung';
                $measurement['value'] = $result;
                $measurement['unit'] = '';
                $measurement['result'] = '';
            }
            if ($name === 'RISO SPANNUNG' && $normalized !== []) {
                $last = array_key_last($normalized);
                if (is_array($normalized[$last]) && strtoupper((string) ($normalized[$last]['name'] ?? '')) === 'RISO') {
                    $normalized[$last]['voltage'] = $value;
                    continue;
                }
            }
            if (!InspectionEvaluationService::isSupportedMeasurementKey(InspectionEvaluationService::measurementKey($name))) {
                continue;
            }
            $normalized[] = $measurement;
        }
        return $normalized;
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin', 'editor')) return forbidden_response();
        $inspection = R::load('inspection', (int) ($params['id'] ?? 0));
        if (!$inspection->id) return [404, [], 'Prüfung nicht gefunden'];
        if (InspectionEvaluationService::normalizeStatus((string) $inspection->result_status, (string) $inspection->status) !== InspectionEvaluationService::IN_PROGRESS) return [409, [], 'Nur Prüfungen in Bearbeitung können gelöscht werden.'];
        $deviceId = (int) $inspection->device_id;
        R::trash($inspection);
        audit_log('pruefung_geloescht', ['id' => (int) ($params['id'] ?? 0), 'device_id' => $deviceId]);
        return [303, ['Location' => url_for('geraete?device_id=' . $deviceId)], ''];
    }
}
