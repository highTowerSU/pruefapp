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
            if (!empty($company['is_default'])) {
                $defaultCompany = $company;
                break;
            }
        }

        $content = render_template('company_list.php', [
            'companies' => $companies,
            'stats' => $stats,
            'defaultCompany' => $defaultCompany,
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
                $previousLogoPath = (string) ($company->header_logo_path ?? '');

                $uploadResult = self::handleHeaderLogoUpload($data, $errors);

                if ($errors === []) {
                    if ($uploadResult !== null) {
                        $data['header_logo_path'] = $uploadResult['path'];
                    }

                    $company->name = $data['name'];
                    $company->slug = $data['slug'];
                    $company->app_title = $data['app_title'];
                    $company->nav_brand = $data['nav_brand'];
                    $company->home_headline = $data['home_headline'];
                    $company->home_intro = $data['home_intro'];
                    $company->home_details = $data['home_details'];
                    $company->header_logo_path = $data['header_logo_path'];
                    $company->header_logo_alt = $data['header_logo_alt'] !== '' ? $data['header_logo_alt'] : $data['name'];
                    $company->nav_background_color = $data['nav_background_color'];
                    $company->nav_text_color = $data['nav_text_color'];
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

                        self::finalizeHeaderLogoUpload($uploadResult, $previousLogoPath);

                        return [303, ['Location' => url_for('firmen')], ''];
                    }
                }

                self::rollbackHeaderLogoUpload($uploadResult ?? null);
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
            $companyData['nav_background_color'] = $data['nav_background_color'];
            $companyData['nav_text_color'] = $data['nav_text_color'];
            $companyData['legal_impressum_label'] = $data['legal_impressum_label'];
            $companyData['legal_impressum_url'] = $data['legal_impressum_url'];
            $companyData['legal_privacy_label'] = $data['legal_privacy_label'];
            $companyData['legal_privacy_url'] = $data['legal_privacy_url'];
            $companyData['is_default'] = $data['is_default'];
        }

        $companyData['is_default'] = (bool) ($companyData['is_default'] ?? false);
        $companyData['header_logo_alt'] = $companyData['header_logo_alt'] ?: $companyData['name'];

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
        $data['app_title'] = trim((string) ($input['app_title'] ?? '')) ?: 'Prüfauftragsverwaltung';
        $data['nav_brand'] = trim((string) ($input['nav_brand'] ?? '')) ?: 'Prüfauftragsverwaltung';
        $data['home_headline'] = trim((string) ($input['home_headline'] ?? ''));
        $data['home_intro'] = trim((string) ($input['home_intro'] ?? ''));
        $data['home_details'] = trim((string) ($input['home_details'] ?? ''));
        $data['header_logo_path'] = trim((string) ($input['header_logo_path'] ?? ''));
        $data['header_logo_alt'] = trim((string) ($input['header_logo_alt'] ?? ''));
        $data['nav_background_color'] = self::sanitizeColor((string) ($input['nav_background_color'] ?? ''));
        $data['nav_text_color'] = self::sanitizeColor((string) ($input['nav_text_color'] ?? ''));
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
            'nav_background_color' => $navBackground,
            'nav_text_color' => $navText,
            'legal_impressum_label' => (string) ($company->legal_impressum_label ?? ''),
            'legal_impressum_url' => (string) ($company->legal_impressum_url ?? ''),
            'legal_privacy_label' => (string) ($company->legal_privacy_label ?? ''),
            'legal_privacy_url' => (string) ($company->legal_privacy_url ?? ''),
            'is_default' => (bool) $company->is_default,
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

    private static function handleHeaderLogoUpload(array $data, array &$errors): ?array
    {
        if (!isset($_FILES['header_logo_file']) || !is_array($_FILES['header_logo_file'])) {
            return null;
        }

        $file = $_FILES['header_logo_file'];
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

        $filename = $slug . '-' . date('YmdHis') . '.' . $extension;
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
