<?php

declare(strict_types=1);

use Ceneos\PhpBase\Tenant\TenantRepository;

class TenantController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) {
            return forbidden_response();
        }

        $repository = new TenantRepository();
        $companies = array_map(static function (\RedBeanPHP\OODBBean $company): array {
            return self::mapCompany($company);
        }, $repository->all());

        $stats = [
            'total' => count($companies),
            'withLogo' => array_reduce(
                $companies,
                static function (int $carry, array $company): int {
                    return $carry + (!empty($company['header_logo_path']) ? 1 : 0);
                },
                0
            ),
        ];

        $defaultCompany = null;
        foreach ($companies as $company) {
            if (($company['slug'] ?? '') === 'ceneos') {
                $defaultCompany = $company;
                break;
            }
        }

        $content = render_template('company_list.php', [
            'companies' => $companies,
            'stats' => $stats,
            'defaultCompany' => $defaultCompany,
            'publicUrls' => public_base_urls(),
        ]);

        $body = render_template('layout.php', [
            'title' => 'Mandanten & Branding',
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    public static function create(array $params, bool $isHx): array
    {
        return self::handleForm(null);
    }

    public static function edit(array $params, bool $isHx): array
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $company = (new TenantRepository())->find($id);

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Mandant nicht gefunden</h1>'];
        }

        return self::handleForm($company);
    }

    public static function store(array $params, bool $isHx): array
    {
        return self::handleForm(null, true);
    }

    public static function update(array $params, bool $isHx): array
    {
        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $company = (new TenantRepository())->find($id);

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Mandant nicht gefunden</h1>'];
        }

        return self::handleForm($company, true);
    }

    /** HTMX fragment: reads possible invoice contact persons from SevDesk. */
    public static function sevDeskUsers(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        $company = (new TenantRepository())->find((int) ($params['id'] ?? 0));
        if ($company === null || !$company->id) return [404, [], 'Mandant nicht gefunden.'];

        try {
            $client = new SevDeskClient((string) ($company->sevdesk_api_url ?? ''), (string) ($company->sevdesk_api_token ?? ''));
            if (!$client->configured()) throw new RuntimeException('Bitte zuerst SevDesk-API-URL und API-Token speichern.');
            $users = array_values(array_filter(array_map(static function (array $user): array {
                $id = trim((string) ($user['id'] ?? ''));
                $name = trim(implode(' ', array_filter([(string) ($user['firstName'] ?? ''), (string) ($user['lastName'] ?? '')])));
                $email = trim((string) ($user['email'] ?? ''));
                return ['id' => $id, 'label' => $name !== '' ? $name . ($email !== '' ? ' · ' . $email : '') : ($email !== '' ? $email : 'SevDesk-Benutzer #' . $id)];
            }, $client->users()), static fn(array $user): bool => $user['id'] !== ''));
            return [200, ['Content-Type' => 'text/html; charset=utf-8'], render_template('company_sevdesk_contact_person.php', ['users' => $users, 'selectedId' => (string) ($company->sevdesk_contact_person_id ?? ''), 'error' => null])];
        } catch (Throwable $error) {
            return [200, ['Content-Type' => 'text/html; charset=utf-8'], render_template('company_sevdesk_contact_person.php', ['users' => [], 'selectedId' => (string) ($company->sevdesk_contact_person_id ?? ''), 'error' => $error->getMessage()])];
        }
    }

    public static function makeDefault(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) {
            return forbidden_response();
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $repository = new TenantRepository();
        $company = $repository->find($id);

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Mandant nicht gefunden</h1>'];
        }

        if ((string) ($company->slug ?? '') !== 'ceneos') {
            $_SESSION['fehlermeldung'] = 'CENEOS ist als Fallback-Mandant festgelegt. Die öffentliche Mandantenauswahl erfolgt über den jeweiligen Hostnamen.';
            return [303, ['Location' => url_for('mandanten')], ''];
        }

        try {
            $company->is_default = 1;
            $company->updated_at = date('c');
            $repository->applySelections($company, true, false);
        } catch (\Throwable $throwable) {
            $_SESSION['fehlermeldung'] = 'Standardmandant konnte nicht gesetzt werden: ' . $throwable->getMessage();

            return [303, ['Location' => url_for('mandanten')], ''];
        }

        audit_log('firma_standard_geaendert', [
            'firma_id' => (int) $company->id,
            'slug' => (string) $company->slug,
        ]);

        $_SESSION['meldung'] = 'Der ausgewählte Mandant wurde als Standard gespeichert.';

        return [303, ['Location' => url_for('mandanten')], ''];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) {
            return forbidden_response();
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $repository = new TenantRepository();
        $company = $repository->find($id);

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Mandant nicht gefunden</h1>'];
        }

        if ($repository->isProtected($company)) {
            $_SESSION['fehlermeldung'] = 'Ein Standard- oder Login-Mandant kann nicht gelöscht werden.';

            return [303, ['Location' => url_for('mandanten')], ''];
        }

        $details = self::mapCompany($company);

        $repository->delete($company);

        audit_log('firma_geloescht', [
            'firma_id' => $details['id'],
            'slug' => $details['slug'],
        ]);

        $_SESSION['meldung'] = 'Der Mandant wurde gelöscht.';

        return [303, ['Location' => url_for('mandanten')], ''];
    }

    private static function handleForm(?\RedBeanPHP\OODBBean $company, bool $isPost = false): array
    {
        if (!current_user_is_superadmin()) {
            return forbidden_response();
        }

        $isNew = $company === null;
        $repository = new TenantRepository();
        $company = $company ?? $repository->dispense();
        $errors = [];

        $data = null;

        if ($isPost) {
            $data = self::sanitizeInput($_POST);
            $errors = self::validateInput($data, $company);

            if ($errors === []) {
                $previousSlug = (string) ($company->slug ?? '');
                $previousLogoPath = (string) ($company->header_logo_path ?? '');
                $previousLightLogoPath = (string) ($company->logo_light_path ?? '');
                $previousDarkLogoPath = (string) ($company->logo_dark_path ?? '');
                $previousLongLogoPath = (string) ($company->logo_long_path ?? '');

                $uploadResult = self::handleHeaderLogoUpload($data, $errors);
                $lightUploadResult = self::handleHeaderLogoUpload($data, $errors, 'logo_light_file', 'light');
                $darkUploadResult = self::handleHeaderLogoUpload($data, $errors, 'logo_dark_file', 'dark');
                $longUploadResult = self::handleHeaderLogoUpload($data, $errors, 'logo_long_file', 'long');

                if ($errors === []) {
                    if ($uploadResult !== null) {
                        $previousSubmittedLogo = $data['header_logo_path'];
                        $data['header_logo_path'] = $uploadResult['path'];
                        if ($data['logo_light_path'] === '' || $data['logo_light_path'] === $previousSubmittedLogo) $data['logo_light_path'] = $uploadResult['path'];
                        if ($data['logo_dark_path'] === '' || $data['logo_dark_path'] === $previousSubmittedLogo) $data['logo_dark_path'] = $uploadResult['path'];
                    }
                    if ($lightUploadResult !== null) $data['logo_light_path'] = $lightUploadResult['path'];
                    if ($darkUploadResult !== null) $data['logo_dark_path'] = $darkUploadResult['path'];
                    if ($longUploadResult !== null) $data['logo_long_path'] = $longUploadResult['path'];

                    $company->name = $data['name'];
                    $company->slug = $data['slug'];
                    $company->app_title = $data['app_title'];
                    $company->nav_brand = $data['nav_brand'];
                    $company->home_headline = $data['home_headline'];
                    $company->home_intro = $data['home_intro'];
                    $company->home_details = $data['home_details'];
                    $company->header_logo_path = $data['header_logo_path'];
                    $company->header_logo_alt = $data['header_logo_alt'] !== '' ? $data['header_logo_alt'] : $data['name'];
                    $company->logo_light_path = $data['logo_light_path'];
                    $company->logo_dark_path = $data['logo_dark_path'];
                    $company->logo_long_path = $data['logo_long_path'];
                    $company->primary_color = $data['primary_color'];
                    $company->primary_text_color = $data['primary_text_color'];
                    $company->light_color = $data['light_color'];
                    $company->dark_color = $data['dark_color'];
                    $company->nav_background_color = $data['nav_background_color'];
                    $company->nav_text_color = $data['nav_text_color'];
                    $company->sevdesk_api_url = $data['sevdesk_api_url'];
                    if ($data['sevdesk_api_token'] !== '') $company->sevdesk_api_token = $data['sevdesk_api_token'];
                    $company->sevdesk_inspection_rate = $data['sevdesk_inspection_rate'];
                    $company->sevdesk_regie_rate = $data['sevdesk_regie_rate'];
                    $company->sevdesk_tax_rule = $data['sevdesk_tax_rule'];
                    $company->sevdesk_tax_rate = $data['sevdesk_tax_rate'];
                    $company->sevdesk_contact_person_id = $data['sevdesk_contact_person_id'];
                    $company->legal_impressum_label = $data['legal_impressum_label'];
                    $company->legal_impressum_url = $data['legal_impressum_url'];
                    $company->legal_privacy_label = $data['legal_privacy_label'];
                    $company->legal_privacy_url = $data['legal_privacy_url'];
                    $company->updated_at = date('c');

                    if (!$company->created_at) {
                        $company->created_at = date('c');
                    }

                    $isDefault = $data['is_default'];
                    $isLoginBrand = $data['is_login_brand'];
                    $company->is_default = $isDefault ? 1 : (int) $company->is_default;
                    $company->is_login_brand = $isLoginBrand ? 1 : (int) ($company->is_login_brand ?? 0);

                    try {
                        $repository->applySelections($company, $isDefault, $isLoginBrand);
                    } catch (\Throwable $throwable) {
                        $errors[] = 'Speichern fehlgeschlagen: ' . $throwable->getMessage();
                    }

                    if ($errors === []) {
                        $logKey = $isNew ? 'firma_erstellt' : 'firma_aktualisiert';
                        audit_log($logKey, [
                            'firma_id' => (int) $company->id,
                            'slug_alt' => $isNew ? null : $previousSlug,
                            'slug_neu' => (string) $company->slug,
                            'standard' => (bool) $company->is_default,
                            'login_branding' => (bool) $company->is_login_brand,
                        ]);

                        $_SESSION['meldung'] = 'Die Mandantendaten wurden gespeichert.';

                        self::finalizeHeaderLogoUpload($uploadResult, $previousLogoPath);
                        self::finalizeHeaderLogoUpload($lightUploadResult, $previousLightLogoPath);
                        self::finalizeHeaderLogoUpload($darkUploadResult, $previousDarkLogoPath);

                        return [303, ['Location' => url_for('mandanten')], ''];
                    }
                }

                self::rollbackHeaderLogoUpload($uploadResult ?? null);
                self::rollbackHeaderLogoUpload($lightUploadResult ?? null);
                self::rollbackHeaderLogoUpload($darkUploadResult ?? null);
                self::rollbackHeaderLogoUpload($longUploadResult ?? null);
            }
        }
        $companyData = self::mapCompany($company);

        if ($isPost && is_array($data)) {
            $companyData['name'] = $data['name'];
            $companyData['slug'] = $data['slug'];
            $companyData['app_title'] = $data['app_title'];
            $companyData['nav_brand'] = $data['nav_brand'];
            $companyData['home_headline'] = $data['home_headline'];
            $companyData['home_intro'] = $data['home_intro'];
            $companyData['home_details'] = $data['home_details'];
            $companyData['header_logo_path'] = $data['header_logo_path'];
            $companyData['header_logo_url'] = self::resolveAssetPath($data['header_logo_path']);
            $companyData['header_logo_alt'] = $data['header_logo_alt'] ?: $data['name'];
            foreach (['logo_light_path', 'logo_dark_path', 'logo_long_path', 'primary_color', 'primary_text_color', 'light_color', 'dark_color'] as $field) {
                $companyData[$field] = $data[$field];
            }
            $companyData['nav_background_color'] = $data['nav_background_color'];
            $companyData['nav_text_color'] = $data['nav_text_color'];
            $companyData['sevdesk_api_url'] = $data['sevdesk_api_url'];
            $companyData['sevdesk_api_token'] = '';
            $companyData['sevdesk_inspection_rate'] = $data['sevdesk_inspection_rate'];
            $companyData['sevdesk_regie_rate'] = $data['sevdesk_regie_rate'];
            $companyData['sevdesk_tax_rule'] = $data['sevdesk_tax_rule'];
            $companyData['sevdesk_tax_rate'] = $data['sevdesk_tax_rate'];
            $companyData['sevdesk_contact_person_id'] = $data['sevdesk_contact_person_id'];
            $companyData['legal_impressum_label'] = $data['legal_impressum_label'];
            $companyData['legal_impressum_url'] = $data['legal_impressum_url'];
            $companyData['legal_privacy_label'] = $data['legal_privacy_label'];
            $companyData['legal_privacy_url'] = $data['legal_privacy_url'];
            $companyData['is_default'] = $data['is_default'];
            $companyData['is_login_brand'] = $data['is_login_brand'];
        }

        $companyData['is_default'] = (bool) ($companyData['is_default'] ?? false);
        $companyData['header_logo_alt'] = $companyData['header_logo_alt'] ?: $companyData['name'];

        $content = render_template('company_form.php', [
            'company' => $companyData,
            'is_new' => $isNew,
            'errors' => $errors,
        ]);

        $title = $isNew ? 'Neuer Mandant anlegen' : 'Mandant bearbeiten – ' . $companyData['name'];

        $body = render_template('layout.php', [
            'title' => $title,
            'content' => $content,
        ]);

        return [200, [], $body];
    }

    private static function sanitizeInput(array $input): array
    {
        $data = [];
        $data['name'] = trim((string) ($input['name'] ?? ''));
        $data['slug'] = strtolower(trim((string) ($input['slug'] ?? '')));
        $data['app_title'] = trim((string) ($input['app_title'] ?? '')) ?: 'Prüfauftragsverwaltung';
        $data['nav_brand'] = trim((string) ($input['nav_brand'] ?? '')) ?: 'Prüfauftragsverwaltung';
        $data['home_headline'] = trim((string) ($input['home_headline'] ?? ''));
        $data['home_intro'] = trim((string) ($input['home_intro'] ?? ''));
        $data['home_details'] = trim((string) ($input['home_details'] ?? ''));
        $data['header_logo_path'] = trim((string) ($input['header_logo_path'] ?? ''));
        $data['header_logo_alt'] = trim((string) ($input['header_logo_alt'] ?? ''));
        $data['logo_light_path'] = trim((string) ($input['logo_light_path'] ?? $data['header_logo_path']));
        $data['logo_dark_path'] = trim((string) ($input['logo_dark_path'] ?? $data['header_logo_path']));
        $data['logo_long_path'] = trim((string) ($input['logo_long_path'] ?? $data['logo_light_path']));
        $data['nav_background_color'] = self::sanitizeColor((string) ($input['nav_background_color'] ?? ''));
        $data['nav_text_color'] = self::sanitizeColor((string) ($input['nav_text_color'] ?? ''));
        $data['sevdesk_api_url'] = trim((string) ($input['sevdesk_api_url'] ?? 'https://my.sevdesk.de/api/v1')) ?: 'https://my.sevdesk.de/api/v1';
        $data['sevdesk_api_token'] = trim((string) ($input['sevdesk_api_token'] ?? ''));
        $data['sevdesk_inspection_rate'] = max(0, (float) str_replace(',', '.', (string) ($input['sevdesk_inspection_rate'] ?? '0')));
        $data['sevdesk_regie_rate'] = max(0, (float) str_replace(',', '.', (string) ($input['sevdesk_regie_rate'] ?? '0')));
        $data['sevdesk_tax_rule'] = (int) ($input['sevdesk_tax_rule'] ?? 1);
        $data['sevdesk_tax_rate'] = max(0, (float) str_replace(',', '.', (string) ($input['sevdesk_tax_rate'] ?? '19')));
        $allowedTaxRules = [1, 2, 3, 4, 5, 11, 17, 18, 19, 20, 21];
        if (!in_array($data['sevdesk_tax_rule'], $allowedTaxRules, true)) $data['sevdesk_tax_rule'] = 1;
        if (in_array($data['sevdesk_tax_rule'], [2, 4, 5, 11, 17, 21], true)) $data['sevdesk_tax_rate'] = 0;
        $data['sevdesk_contact_person_id'] = trim((string) ($input['sevdesk_contact_person_id'] ?? ''));
        if ($data['sevdesk_contact_person_id'] !== '' && !preg_match('/^\d+$/', $data['sevdesk_contact_person_id'])) $data['sevdesk_contact_person_id'] = '';
        foreach (['primary_color', 'primary_text_color', 'light_color', 'dark_color'] as $field) {
            $data[$field] = self::sanitizeColor((string) ($input[$field] ?? ''));
        }
        $data['legal_impressum_label'] = trim((string) ($input['legal_impressum_label'] ?? ''));
        $data['legal_impressum_url'] = trim((string) ($input['legal_impressum_url'] ?? ''));
        $data['legal_privacy_label'] = trim((string) ($input['legal_privacy_label'] ?? ''));
        $data['legal_privacy_url'] = trim((string) ($input['legal_privacy_url'] ?? ''));
        // Both selections are legacy flags. Public and login branding are now
        // derived from the hostname, with CENEOS as the fixed fallback.
        $data['is_default'] = $data['slug'] === 'ceneos';
        $data['is_login_brand'] = false;

        return $data;
    }

    private static function validateInput(array $data, \RedBeanPHP\OODBBean $company): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Bitte einen Anzeigenamen für den Mandanten angeben.';
        }

        if ($data['slug'] === '') {
            $errors[] = 'Bitte einen eindeutigen Kurznamen (Slug) festlegen.';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            $errors[] = 'Der Kurznamen darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.';
        } else {
            $existing = (new TenantRepository())->findBySlug($data['slug'], (int) $company->id);
            if ($existing !== null) {
                $errors[] = 'Es existiert bereits ein Mandant mit diesem Kurznamen.';
            }
        }

        if ($data['header_logo_path'] !== '' && !self::isValidRelativeOrUrl($data['header_logo_path'])) {
            $errors[] = 'Der Pfad zum Header-Logo muss eine relative URL oder eine vollständige Adresse sein.';
        }
        foreach (['logo_light_path', 'logo_dark_path', 'logo_long_path'] as $field) {
            if ($data[$field] !== '' && !self::isValidRelativeOrUrl($data[$field])) $errors[] = 'Die Logo-Pfade sind ungültig.';
        }

        if ($data['legal_impressum_url'] !== '' && !filter_var($data['legal_impressum_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Die Impressums-URL ist ungültig.';
        }

        if ($data['legal_privacy_url'] !== '' && !filter_var($data['legal_privacy_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Die Datenschutz-URL ist ungültig.';
        }

        if ($data['nav_background_color'] !== '' && !self::isValidHexColor($data['nav_background_color'])) {
            $errors[] = 'Die Hintergrundfarbe der Navigation muss als Hex-Wert angegeben werden (z. B. #123ABC).';
        }

        if ($data['nav_text_color'] !== '' && !self::isValidHexColor($data['nav_text_color'])) {
            $errors[] = 'Die Textfarbe der Navigation muss als Hex-Wert angegeben werden (z. B. #FFFFFF).';
        }
        foreach (['primary_color', 'primary_text_color', 'light_color', 'dark_color'] as $field) {
            if ($data[$field] !== '' && !self::isValidHexColor($data[$field])) $errors[] = 'Alle Theme-Farben müssen als Hex-Wert angegeben werden.';
        }

        return $errors;
    }

    private static function mapCompany(\RedBeanPHP\OODBBean $company): array
    {
        $updatedAt = null;
        $rawUpdated = (string) ($company->updated_at ?? '');
        if ($rawUpdated !== '') {
            try {
                $updatedAt = new DateTimeImmutable($rawUpdated);
            } catch (\Exception) {
                $updatedAt = null;
            }
        }

        $headerLogoPath = (string) ($company->header_logo_path ?? '');
        $headerLogoAlt = (string) ($company->header_logo_alt ?? '');
        if ($headerLogoAlt === '') {
            $headerLogoAlt = (string) ($company->name ?? '');
        }

        $navBackground = self::sanitizeColor((string) ($company->nav_background_color ?? ''));
        $navText = self::sanitizeColor((string) ($company->nav_text_color ?? ''));


        return [
            'id' => (int) $company->id,
            'name' => (string) ($company->name ?? ''),
            'slug' => (string) ($company->slug ?? ''),
            'app_title' => (string) ($company->app_title ?? ''),
            'nav_brand' => (string) ($company->nav_brand ?? ''),
            'home_headline' => (string) ($company->home_headline ?? ''),
            'home_intro' => (string) ($company->home_intro ?? ''),
            'home_details' => (string) ($company->home_details ?? ''),
            'header_logo_path' => $headerLogoPath,
            'header_logo_url' => self::resolveAssetPath($headerLogoPath),
            'header_logo_alt' => $headerLogoAlt,
            'logo_light_path' => (string) ($company->logo_light_path ?? '') ?: $headerLogoPath,
            'logo_dark_path' => (string) ($company->logo_dark_path ?? '') ?: $headerLogoPath,
            'logo_long_path' => (string) ($company->logo_long_path ?? '') ?: ((string) ($company->logo_light_path ?? '') ?: $headerLogoPath),
            'primary_color' => self::sanitizeColor((string) ($company->primary_color ?? '')),
            'primary_text_color' => self::sanitizeColor((string) ($company->primary_text_color ?? '')),
            'light_color' => self::sanitizeColor((string) ($company->light_color ?? '')),
            'dark_color' => self::sanitizeColor((string) ($company->dark_color ?? '')),
            'nav_background_color' => $navBackground,
            'nav_text_color' => $navText,
            'sevdesk_api_url' => (string) ($company->sevdesk_api_url ?? 'https://my.sevdesk.de/api/v1'),
            'sevdesk_api_token' => '',
            'sevdesk_inspection_rate' => (float) ($company->sevdesk_inspection_rate ?? 0),
            'sevdesk_regie_rate' => (float) ($company->sevdesk_regie_rate ?? 0),
            'sevdesk_tax_rule' => (int) ($company->sevdesk_tax_rule ?? 1),
            'sevdesk_tax_rate' => (float) ($company->sevdesk_tax_rate ?? 19),
            'sevdesk_contact_person_id' => (string) ($company->sevdesk_contact_person_id ?? ''),
            'legal_impressum_label' => (string) ($company->legal_impressum_label ?? ''),
            'legal_impressum_url' => (string) ($company->legal_impressum_url ?? ''),
            'legal_privacy_label' => (string) ($company->legal_privacy_label ?? ''),
            'legal_privacy_url' => (string) ($company->legal_privacy_url ?? ''),
            'is_default' => (bool) $company->is_default,
            'is_login_brand' => (bool) ($company->is_login_brand ?? false),
            'updated_at' => $updatedAt,
        ];
    }

    private static function isValidRelativeOrUrl(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        if (preg_match('#^https?://#i', $value)) {
            return filter_var($value, FILTER_VALIDATE_URL) !== false;
        }

        return preg_match('#^[A-Za-z0-9_./\-]+$#', $value) === 1;
    }

    private static function resolveAssetPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return url_for($path);
    }

    private static function sanitizeColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($value[0] !== '#') {
            $value = '#' . $value;
        }

        return strtoupper($value);
    }

    private static function isValidHexColor(string $value): bool
    {
        return (bool) preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6})$/', strtoupper($value));
    }

    private static function handleHeaderLogoUpload(array $data, array &$errors, string $inputName = 'header_logo_file', string $suffix = 'header'): ?array
    {
        if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return null;
        }

        $file = $_FILES[$inputName];
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Das Header-Logo konnte nicht hochgeladen werden (Fehlercode ' . $error . ').';

            return null;
        }

        $tmpName = $file['tmp_name'] ?? '';
        if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
            $errors[] = 'Ungültiger Datei-Upload für das Header-Logo.';

            return null;
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        $maxSize = 2 * 1024 * 1024; // 2 MB
        if ($size > $maxSize) {
            $errors[] = 'Das Header-Logo darf maximal 2 MB groß sein.';

            return null;
        }

        $allowedMimeTypes = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mimeType = finfo_file($finfo, $tmpName) ?: null;
                finfo_close($finfo);
            }
        }

        if ($mimeType === null && function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($tmpName) ?: null;
        }

        if ($mimeType === null || !isset($allowedMimeTypes[$mimeType])) {
            $errors[] = 'Nur PNG, JPG, SVG, GIF oder WebP Dateien sind als Header-Logo erlaubt.';

            return null;
        }

        $extension = $allowedMimeTypes[$mimeType];
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = trim((string) ($data['name'] ?? ''));
        }
        if ($slug === '') {
            $slug = 'logo';
        }
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?: 'logo';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'logo';
        }

        $uploadDir = self::getLogoUploadDirectory();
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                $errors[] = 'Upload-Verzeichnis für Logos konnte nicht erstellt werden.';

                return null;
            }
        }

        $filename = $slug . '-' . $suffix . '-' . date('YmdHis') . '.' . $extension;
        $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            $errors[] = 'Das Header-Logo konnte nicht gespeichert werden.';

            return null;
        }

        $relativePath = 'public/uploads/logos/' . $filename;

        return [
            'path' => $relativePath,
        ];
    }

    private static function finalizeHeaderLogoUpload(?array $uploadResult, string $previousPath): void
    {
        if ($uploadResult === null) {
            return;
        }

        if ($previousPath !== '' && str_starts_with($previousPath, 'public/uploads/logos/')) {
            $fullPath = self::getProjectRoot() . '/' . $previousPath;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    private static function rollbackHeaderLogoUpload(?array $uploadResult): void
    {
        if ($uploadResult === null) {
            return;
        }

        $path = $uploadResult['path'] ?? '';
        if ($path === '') {
            return;
        }

        $fullPath = self::getProjectRoot() . '/' . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private static function getLogoUploadDirectory(): string
    {
        return self::getProjectRoot() . '/public/uploads/logos';
    }

    private static function getProjectRoot(): string
    {
        return dirname(__DIR__);
    }
}
