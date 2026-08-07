<?php

declare(strict_types=1);

use RedBeanPHP\R;

final class CustomerInfoController
{
    public static function index(array $params, bool $isHx): array
    {
        $customer = self::customer($params);
        if ($customer === null || !current_user_can_access_customer((int) $customer->id)) return [404, [], 'Kunde nicht gefunden'];
        $infos = array_values(R::findAll('customerinfo', ' customer_id = ? ORDER BY updated_at DESC, title ', [(int) $customer->id]));
        return self::page('Kundeninfos – ' . (string) $customer->name, render_template('customer_info_index.php', [
            'customer' => $customer,
            'infos' => $infos,
            'canManage' => current_user_has_role('admin'),
        ]), $isHx);
    }

    public static function edit(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $customer = self::customer($params);
        $info = R::load('customerinfo', (int) ($params['infoId'] ?? 0));
        if ($customer === null || !$info->id || (int) $info->customer_id !== (int) $customer->id) return [404, [], 'Kundeninfo nicht gefunden'];
        return self::page('Kundeninfo bearbeiten', render_template('customer_info_edit.php', ['customer' => $customer, 'info' => $info]), $isHx);
    }

    public static function view(array $params, bool $isHx): array
    {
        $info = R::load('customerinfo', (int) ($params['id'] ?? 0));
        if (!$info->id) return [404, [], 'Kundeninfo nicht gefunden'];
        $customer = R::load('customer', (int) $info->customer_id);
        if (!$customer->id || !current_user_can_access_customer((int) $customer->id)) return [404, [], 'Kundeninfo nicht gefunden'];
        return self::page((string) $info->title, render_template('customer_info_view.php', [
            'customer' => $customer,
            'info' => $info,
            'markdown' => self::markdown((string) $info->markdown),
            'canManage' => current_user_has_role('admin'),
        ]), $isHx);
    }

    public static function save(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $customer = self::customer($params);
        if ($customer === null) return [404, [], 'Kunde nicht gefunden'];
        $id = (int) ($_POST['id'] ?? 0);
        $info = $id > 0 ? R::load('customerinfo', $id) : R::dispense('customerinfo');
        if ($id > 0 && (!$info->id || (int) $info->customer_id !== (int) $customer->id)) return [404, [], 'Kundeninfo nicht gefunden'];
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') return [422, [], 'Bitte einen Titel angeben.'];
        $info->customer_id = (int) $customer->id;
        $info->title = $title;
        $info->slug = self::slug($title);
        $info->markdown = (string) ($_POST['markdown'] ?? '');
        $info->updated_at = date('c');
        if (!$info->created_at) $info->created_at = date('c');
        $upload = self::upload($_FILES['attachment'] ?? null, (int) $customer->id);
        if ($upload !== null) {
            self::removeFile((string) ($info->file_path ?? ''));
            $info->file_path = $upload['path'];
            $info->file_name = $upload['name'];
            $info->file_mime = $upload['mime'];
        }
        R::store($info);
        audit_log('kundeninfo_gespeichert', ['id' => (int) $info->id, 'customer_id' => (int) $customer->id]);
        return [303, ['Location' => url_for('kunden/' . (int) $customer->id . '/infos')], ''];
    }

    public static function uploadMultiple(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $customer = self::customer($params);
        if ($customer === null) return [404, [], 'Kunde nicht gefunden'];
        $files = $_FILES['attachments'] ?? null;
        $created = 0;
        $errors = [];
        if (is_array($files) && is_array($files['name'] ?? null)) {
            foreach ($files['name'] as $index => $originalName) {
                $file = ['name' => $originalName, 'type' => $files['type'][$index] ?? '', 'tmp_name' => $files['tmp_name'][$index] ?? '', 'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$index] ?? 0];
                if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) continue;
                try {
                    $upload = self::upload($file, (int) $customer->id);
                    if ($upload === null) throw new \RuntimeException('Datei wurde nicht übertragen.');
                    $info = R::dispense('customerinfo');
                    $info->customer_id = (int) $customer->id;
                    $info->title = pathinfo((string) $originalName, PATHINFO_FILENAME);
                    $info->slug = self::slug((string) $info->title);
                    $info->markdown = '';
                    $info->file_path = $upload['path'];
                    $info->file_name = $upload['name'];
                    $info->file_mime = $upload['mime'];
                    $info->created_at = $info->updated_at = date('c');
                    R::store($info);
                    audit_log('kundeninfo_datei_hochgeladen', ['id' => (int) $info->id, 'customer_id' => (int) $customer->id, 'file_name' => (string) $info->file_name]);
                    $created++;
                } catch (\Throwable $error) {
                    $label = trim((string) $originalName) !== '' ? (string) $originalName : 'Unbenannte Datei';
                    $errors[] = $label . ': ' . $error->getMessage();
                }
            }
        }
        $infos = array_values(R::findAll('customerinfo', ' customer_id = ? ORDER BY updated_at DESC, title ', [(int) $customer->id]));
        $message = $created > 0 ? $created . ' Datei(en) hochgeladen.' : 'Keine Datei ausgewählt.';
        if ($errors !== []) $message .= ' ' . implode(' ', $errors);
        return [200, ['Content-Type' => 'text/html; charset=utf-8'], render_template('customer_info_cards.php', ['customer' => $customer, 'infos' => $infos, 'canManage' => true, 'uploadMessage' => $message])];
    }

    public static function delete(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $customer = self::customer($params);
        $info = R::load('customerinfo', (int) ($params['infoId'] ?? 0));
        if ($customer === null || !$info->id || (int) $info->customer_id !== (int) $customer->id) return [404, [], 'Kundeninfo nicht gefunden'];
        self::removeFile((string) ($info->file_path ?? ''));
        R::trash($info);
        audit_log('kundeninfo_geloescht', ['id' => (int) $params['infoId'], 'customer_id' => (int) $customer->id]);
        return [303, ['Location' => url_for('kunden/' . (int) $customer->id . '/infos')], ''];
    }

    public static function updateTitle(array $params, bool $isHx): array
    {
        if (!current_user_has_role('admin')) return forbidden_response();
        $customer = self::customer($params);
        $info = R::load('customerinfo', (int) ($params['infoId'] ?? 0));
        if ($customer === null || !$info->id || (int) $info->customer_id !== (int) $customer->id) return [404, [], 'Kundeninfo nicht gefunden'];
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') return [422, [], 'Bitte einen Titel angeben.'];
        $info->title = $title;
        $info->slug = self::slug($title);
        $info->updated_at = date('c');
        R::store($info);
        audit_log('kundeninfo_titel_geaendert', ['id' => (int) $info->id, 'customer_id' => (int) $customer->id]);
        $infos = array_values(R::findAll('customerinfo', ' customer_id = ? ORDER BY updated_at DESC, title ', [(int) $customer->id]));
        return [200, ['Content-Type' => 'text/html; charset=utf-8'], render_template('customer_info_cards.php', ['customer' => $customer, 'infos' => $infos, 'canManage' => true])];
    }

    public static function file(array $params, bool $isHx): array
    {
        $info = R::load('customerinfo', (int) ($params['id'] ?? 0));
        $customer = $info->id ? R::load('customer', (int) $info->customer_id) : null;
        if (!$info->id || !$customer || !$customer->id || !current_user_can_access_customer((int) $customer->id)) return [404, [], 'Datei nicht gefunden'];
        $root = realpath(self::storageRoot());
        $path = realpath(self::storageRoot() . '/' . ltrim((string) $info->file_path, '/'));
        if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) return [404, [], 'Datei nicht gefunden'];
        $mime = (string) ($info->file_mime ?: 'application/octet-stream');
        return [200, ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="' . addslashes((string) ($info->file_name ?: basename($path))) . '"'], (string) file_get_contents($path)];
    }

    private static function customer(array $params): ?\RedBeanPHP\OODBBean
    {
        $customer = R::load('customer', (int) ($params['id'] ?? 0));
        return $customer->id ? $customer : null;
    }

    private static function page(string $title, string $content, bool $isHx = false): array
    {
        if ($isHx) return [200, ['Content-Type' => 'text/html; charset=utf-8'], $content];
        return [200, [], render_template('layout.php', ['title' => $title, 'content' => $content])];
    }

    private static function storageRoot(): string
    {
        return dirname(__DIR__) . '/data/' . app_storage_namespace() . '/customer-info';
    }

    private static function upload($file, int $customerId): ?array
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'Datei überschreitet das Serverlimit.',
                UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet das Formularlimit.',
                UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise übertragen.',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Uploadverzeichnis fehlt.',
                UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht auf dem Server gespeichert werden.',
                UPLOAD_ERR_EXTENSION => 'Upload wurde durch eine Servererweiterung gestoppt.',
            ];
            throw new \RuntimeException($messages[$uploadError] ?? 'Datei konnte nicht hochgeladen werden.');
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) throw new \RuntimeException('Datei wurde nicht korrekt übertragen.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: 'application/octet-stream';
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        if (!isset($allowed[$mime])) throw new \InvalidArgumentException('Erlaubt sind PDF-, JPG-, PNG-, WEBP- und GIF-Dateien.');
        $dir = self::storageRoot() . '/' . $customerId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new \RuntimeException('Ablageverzeichnis konnte nicht angelegt werden.');
        $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $filename)) throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        return ['path' => $customerId . '/' . $filename, 'name' => basename((string) ($file['name'] ?? $filename)), 'mime' => $mime];
    }

    private static function removeFile(string $relative): void
    {
        if ($relative === '') return;
        $root = realpath(self::storageRoot());
        $path = realpath(self::storageRoot() . '/' . ltrim($relative, '/'));
        if ($root && $path && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
    }

    private static function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii)), '-') ?: 'info';
    }

    private static function markdown(string $markdown): string
    {
        $escaped = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', static fn($m): string => '<a href="' . htmlspecialchars($m[2], ENT_QUOTES) . '" target="_blank" rel="noopener">' . $m[1] . '</a>', $escaped);
        $escaped = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $escaped);
        $escaped = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $escaped);
        $escaped = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $escaped);
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/^[-*] (.+)$/m', '<li>$1</li>', $escaped);
        $escaped = preg_replace('/(?:<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $escaped);
        return nl2br($escaped, false);
    }
}
