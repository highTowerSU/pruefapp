<?php

declare(strict_types=1);

use RedBeanPHP\R as R;

class CompanyController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $companies = array_map(static function (\RedBeanPHP\OODBBean $company): array {
            return self::mapCompany($company);
        }, array_values(R::findAll('company', ' ORDER BY name ')));

        $content = render_template('company_list.php', [
            'companies' => $companies,
        ]);

        $body = render_template('layout.php', [
            'title' => 'Firmenverwaltung',
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
        $company = $id > 0 ? R::load('company', $id) : null;

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Firma nicht gefunden</h1>'];
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
        $company = $id > 0 ? R::load('company', $id) : null;

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Firma nicht gefunden</h1>'];
        }

        return self::handleForm($company, true);
    }

    public static function makeDefault(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $company = $id > 0 ? R::load('company', $id) : null;

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Firma nicht gefunden</h1>'];
        }

        R::begin();
        try {
            $company->is_default = 1;
            $company->updated_at = date('c');
            R::store($company);
            R::exec('UPDATE company SET is_default = 0 WHERE id != ?', [$company->id]);
            R::commit();
        } catch (\Throwable $throwable) {
            R::rollback();
            $_SESSION['fehlermeldung'] = 'Standardfirma konnte nicht gesetzt werden: ' . $throwable->getMessage();

            return [303, ['Location' => url_for('firmen')], ''];
        }

        audit_log('firma_standard_geaendert', [
            'firma_id' => (int) $company->id,
            'slug' => (string) $company->slug,
        ]);

        $_SESSION['meldung'] = 'Die ausgewählte Firma wurde als Standard gespeichert.';

        return [303, ['Location' => url_for('firmen')], ''];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $id = isset($params['id']) ? (int) $params['id'] : 0;
        $company = $id > 0 ? R::load('company', $id) : null;

        if ($company === null || !$company->id) {
            return [404, [], '<h1>404 – Firma nicht gefunden</h1>'];
        }

        if ((int) $company->is_default === 1) {
            $_SESSION['fehlermeldung'] = 'Die Standardfirma kann nicht gelöscht werden.';

            return [303, ['Location' => url_for('firmen')], ''];
        }

        $details = self::mapCompany($company);

        R::trash($company);

        audit_log('firma_geloescht', [
            'firma_id' => $details['id'],
            'slug' => $details['slug'],
        ]);

        $_SESSION['meldung'] = 'Die Firma wurde gelöscht.';

        return [303, ['Location' => url_for('firmen')], ''];
    }

    private static function handleForm(?\RedBeanPHP\OODBBean $company, bool $isPost = false): array
    {
        if (!current_user_has_role('admin')) {
            return forbidden_response();
        }

        $isNew = $company === null;
        $company = $company ?? R::dispense('company');
        $errors = [];

        $data = null;

        if ($isPost) {
            $data = self::sanitizeInput($_POST);
            $errors = self::validateInput($data, $company);

            if ($errors === []) {
                $previousSlug = (string) ($company->slug ?? '');

                $company->name = $data['name'];
                $company->slug = $data['slug'];
                $company->app_title = $data['app_title'];
                $company->nav_brand = $data['nav_brand'];
                $company->home_headline = $data['home_headline'];
                $company->home_intro = $data['home_intro'];
                $company->home_details = $data['home_details'];
                $company->primary_client = $data['primary_client'];
                $company->project_owner = $data['project_owner'];
                $company->group_reference = $data['group_reference'];
                $company->header_logo_path = $data['header_logo_path'];
                $company->header_logo_alt = $data['header_logo_alt'];
                $company->footer_logos_json = json_encode($data['footer_logos'], JSON_UNESCAPED_UNICODE);
                $company->legal_impressum_label = $data['legal_impressum_label'];
                $company->legal_impressum_url = $data['legal_impressum_url'];
                $company->legal_privacy_label = $data['legal_privacy_label'];
                $company->legal_privacy_url = $data['legal_privacy_url'];
                $company->updated_at = date('c');

                if (!$company->created_at) {
                    $company->created_at = date('c');
                }

                $isDefault = $data['is_default'];
                $company->is_default = $isDefault ? 1 : (int) $company->is_default;

                R::begin();
                try {
                    R::store($company);

                    if ($isDefault) {
                        R::exec('UPDATE company SET is_default = 0 WHERE id != ?', [$company->id]);
                    }

                    R::commit();
                } catch (\Throwable $throwable) {
                    R::rollback();
                    $errors[] = 'Speichern fehlgeschlagen: ' . $throwable->getMessage();
                }

                if ($errors === []) {
                    $logKey = $isNew ? 'firma_erstellt' : 'firma_aktualisiert';
                    audit_log($logKey, [
                        'firma_id' => (int) $company->id,
                        'slug_alt' => $isNew ? null : $previousSlug,
                        'slug_neu' => (string) $company->slug,
                        'standard' => (bool) $company->is_default,
                    ]);

                    $_SESSION['meldung'] = 'Die Firmendaten wurden gespeichert.';

                    return [303, ['Location' => url_for('firmen')], ''];
                }
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
            $companyData['primary_client'] = $data['primary_client'];
            $companyData['project_owner'] = $data['project_owner'];
            $companyData['group_reference'] = $data['group_reference'];
            $companyData['header_logo_path'] = $data['header_logo_path'];
            $companyData['header_logo_url'] = self::resolveAssetPath($data['header_logo_path']);
            $companyData['header_logo_alt'] = $data['header_logo_alt'];
            $companyData['footer_logos'] = $data['footer_logos'];
            $companyData['legal_impressum_label'] = $data['legal_impressum_label'];
            $companyData['legal_impressum_url'] = $data['legal_impressum_url'];
            $companyData['legal_privacy_label'] = $data['legal_privacy_label'];
            $companyData['legal_privacy_url'] = $data['legal_privacy_url'];
            $companyData['is_default'] = $data['is_default'];
        }

        $companyData['footer_logos_text'] = self::formatFooterLogos($companyData['footer_logos']);
        $companyData['is_default'] = (bool) ($companyData['is_default'] ?? false);

        $content = render_template('company_form.php', [
            'company' => $companyData,
            'is_new' => $isNew,
            'errors' => $errors,
        ]);

        $title = $isNew ? 'Neue Firma anlegen' : 'Firma bearbeiten – ' . $companyData['name'];

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
        $data['app_title'] = trim((string) ($input['app_title'] ?? '')) ?: 'Kursverwaltung';
        $data['nav_brand'] = trim((string) ($input['nav_brand'] ?? '')) ?: 'Kursverwaltung';
        $data['home_headline'] = trim((string) ($input['home_headline'] ?? ''));
        $data['home_intro'] = trim((string) ($input['home_intro'] ?? ''));
        $data['home_details'] = trim((string) ($input['home_details'] ?? ''));
        $data['primary_client'] = trim((string) ($input['primary_client'] ?? ''));
        $data['project_owner'] = trim((string) ($input['project_owner'] ?? ''));
        $data['group_reference'] = trim((string) ($input['group_reference'] ?? ''));
        $data['header_logo_path'] = trim((string) ($input['header_logo_path'] ?? ''));
        $data['header_logo_alt'] = trim((string) ($input['header_logo_alt'] ?? ''));
        $data['footer_logos'] = self::parseFooterLogos((string) ($input['footer_logos'] ?? ''));
        $data['legal_impressum_label'] = trim((string) ($input['legal_impressum_label'] ?? ''));
        $data['legal_impressum_url'] = trim((string) ($input['legal_impressum_url'] ?? ''));
        $data['legal_privacy_label'] = trim((string) ($input['legal_privacy_label'] ?? ''));
        $data['legal_privacy_url'] = trim((string) ($input['legal_privacy_url'] ?? ''));
        $data['is_default'] = isset($input['is_default']);

        return $data;
    }

    private static function validateInput(array $data, \RedBeanPHP\OODBBean $company): array
    {
        $errors = [];

        if ($data['name'] === '') {
            $errors[] = 'Bitte einen Anzeigenamen für die Firma angeben.';
        }

        if ($data['slug'] === '') {
            $errors[] = 'Bitte einen eindeutigen Kurznamen (Slug) festlegen.';
        } elseif (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
            $errors[] = 'Der Kurznamen darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.';
        } else {
            $existing = R::findOne('company', ' LOWER(slug) = ? AND id != ? ', [$data['slug'], (int) $company->id]);
            if ($existing !== null) {
                $errors[] = 'Es existiert bereits eine Firma mit diesem Kurznamen.';
            }
        }

        if ($data['header_logo_path'] !== '' && !self::isValidRelativeOrUrl($data['header_logo_path'])) {
            $errors[] = 'Der Pfad zum Header-Logo muss eine relative URL oder eine vollständige Adresse sein.';
        }

        foreach ($data['footer_logos'] as $logo) {
            if (!self::isValidRelativeOrUrl($logo['path'])) {
                $errors[] = 'Einer der Pfade für die Fußzeilen-Logos ist ungültig.';
                break;
            }
        }

        if ($data['legal_impressum_url'] !== '' && !filter_var($data['legal_impressum_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Die Impressums-URL ist ungültig.';
        }

        if ($data['legal_privacy_url'] !== '' && !filter_var($data['legal_privacy_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Die Datenschutz-URL ist ungültig.';
        }

        return $errors;
    }

    private static function mapCompany(\RedBeanPHP\OODBBean $company): array
    {
        $logos = [];
        $raw = (string) ($company->footer_logos_json ?? '');
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $logo) {
                    if (!is_array($logo)) {
                        continue;
                    }

                    $path = trim((string) ($logo['path'] ?? ''));
                    if ($path === '') {
                        continue;
                    }

                    $logos[] = [
                        'path' => $path,
                        'alt' => (string) ($logo['alt'] ?? ''),
                    ];
                }
            }
        }

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

        return [
            'id' => (int) $company->id,
            'name' => (string) ($company->name ?? ''),
            'slug' => (string) ($company->slug ?? ''),
            'app_title' => (string) ($company->app_title ?? ''),
            'nav_brand' => (string) ($company->nav_brand ?? ''),
            'home_headline' => (string) ($company->home_headline ?? ''),
            'home_intro' => (string) ($company->home_intro ?? ''),
            'home_details' => (string) ($company->home_details ?? ''),
            'primary_client' => (string) ($company->primary_client ?? ''),
            'project_owner' => (string) ($company->project_owner ?? ''),
            'group_reference' => (string) ($company->group_reference ?? ''),
            'header_logo_path' => $headerLogoPath,
            'header_logo_url' => self::resolveAssetPath($headerLogoPath),
            'header_logo_alt' => (string) ($company->header_logo_alt ?? ''),
            'footer_logos' => $logos,
            'legal_impressum_label' => (string) ($company->legal_impressum_label ?? ''),
            'legal_impressum_url' => (string) ($company->legal_impressum_url ?? ''),
            'legal_privacy_label' => (string) ($company->legal_privacy_label ?? ''),
            'legal_privacy_url' => (string) ($company->legal_privacy_url ?? ''),
            'is_default' => (bool) $company->is_default,
            'updated_at' => $updatedAt,
        ];
    }

    private static function formatFooterLogos(array $logos): string
    {
        if ($logos === []) {
            return '';
        }

        $lines = array_map(static function (array $logo): string {
            $alt = trim($logo['alt'] ?? '');
            $path = trim($logo['path'] ?? '');
            if ($path === '') {
                return '';
            }

            return $alt === '' ? $path : $path . ' | ' . $alt;
        }, $logos);

        $lines = array_filter($lines, static function (string $line): bool {
            return $line !== '';
        });

        return implode(PHP_EOL, $lines);
    }

    private static function parseFooterLogos(string $input): array
    {
        $logos = [];
        $lines = preg_split('/\r\n|\r|\n/', $input) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $path = $line;
            $alt = '';
            if (strpos($line, '|') !== false) {
                [$path, $alt] = array_map('trim', explode('|', $line, 2));
            }

            if ($path === '') {
                continue;
            }

            $logos[] = [
                'path' => $path,
                'alt' => $alt,
            ];
        }

        return $logos;
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
}

