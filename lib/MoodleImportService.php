<?php

class MoodleImportService
{
    private string $moodleRoot;
    private string $phpBinary;
    private array $defaultOptions;

    public function __construct(?string $moodleRoot = null, ?string $phpBinary = null, array $defaultOptions = [])
    {
        if ($moodleRoot !== null) {
            $resolvedRoot = rtrim($moodleRoot, DIRECTORY_SEPARATOR);
        } elseif (function_exists('moodle_root_path')) {
            $resolvedRoot = moodle_root_path();
        } else {
            $envRoot = getenv('MOODLE_PATH');
            if ($envRoot === false && isset($_ENV['MOODLE_PATH'])) {
                $envRoot = (string) $_ENV['MOODLE_PATH'];
            }
            $resolvedRoot = rtrim((string) ($envRoot ?: ''), DIRECTORY_SEPARATOR);
        }

        $this->moodleRoot = $resolvedRoot;

        $envPhp = getenv('MOODLE_PHP_BIN');
        if ($envPhp === false && isset($_ENV['MOODLE_PHP_BIN'])) {
            $envPhp = (string) $_ENV['MOODLE_PHP_BIN'];
        }

        $this->phpBinary = $phpBinary !== null
            ? $phpBinary
            : $this->detectPhpBinary($envPhp ?: PHP_BINARY);

        $this->defaultOptions = $defaultOptions + [
            // Moodle expects the delimiter option to contain the literal character and not
            // the human readable name (e.g. "comma"). Passing the word would make Moodle
            // interpret the first character ("c") as separator which leads to the
            // "csvfewcolumns" error because the CSV is no longer parsed correctly.
            // Providing both the character and the matching delimiter name keeps backwards
            // compatibility with Moodle's CLI while ensuring the generated CSV is parsed
            // with the intended separator.
            'delimiter' => ',',
            'delimitername' => 'comma',
            'encoding' => 'UTF-8',
            'ignoreerrors' => true,
            'updatemode' => '0',
            'noemail' => true,
            'skipemail' => true,
        ];
    }

    private function detectPhpBinary(string $candidate): string
    {
        $binary = $candidate !== '' ? $candidate : PHP_BINARY;
        $basename = basename($binary);

        if ($basename !== '' && stripos($basename, 'php-fpm') !== false) {
            $cliBinary = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';

            if (is_file($cliBinary) && is_executable($cliBinary)) {
                return $cliBinary;
            }

            return 'php';
        }

        return $binary;
    }

    public function getMoodleRoot(): string
    {
        return $this->moodleRoot;
    }

    public function getPhpBinary(): string
    {
        return $this->phpBinary;
    }

    public function getUploadScriptPath(): string
    {
        if ($this->moodleRoot === '') {
            return '';
        }

        $modernPath = $this->moodleRoot . '/admin/tool/uploaduser/cli/uploaduser.php';
        if (is_file($modernPath)) {
            return $modernPath;
        }

        $legacyPath = $this->moodleRoot . '/admin/cli/uploaduser.php';
        if (is_file($legacyPath)) {
            return $legacyPath;
        }

        return $modernPath;
    }

    public function isConfigured(): bool
    {
        return $this->moodleRoot !== '';
    }

    public function scriptExists(): bool
    {
        $script = $this->getUploadScriptPath();

        return $script !== '' && is_file($script);
    }

    public function canImport(): bool
    {
        return $this->isConfigured() && $this->scriptExists();
    }

    public function importParticipants(array $teilnehmer, ?string $courseShortname = null, ?string $roleShortname = 'student'): array
    {
        if (!$this->canImport()) {
            throw new \RuntimeException('Moodle-Import ist nicht konfiguriert oder das Upload-Skript wurde nicht gefunden.');
        }

        if (count($teilnehmer) === 0) {
            return [
                'exit_code' => 0,
                'output' => ['Keine Teilnehmer zum Import vorhanden.'],
                'command' => null,
            ];
        }

        $tempFile = $this->createCsvFile($teilnehmer, $courseShortname, $roleShortname);

        try {
            $command = $this->buildCommand($tempFile);
            $output = [];
            $exitCode = 1;

//            echo $command; die();
            if (function_exists('audit_log')) {
                audit_log('moodle_import_ausgefuehrt', [
                    'befehl' => $command,
                    'csv_datei' => $tempFile,
                ]);
            }

            exec($command . ' 2>&1', $output, $exitCode);

            $result = [
                'exit_code' => $exitCode,
                'output' => $output,
                'command' => $command,
            ];

            if (function_exists('audit_log')) {
                $logContext = [
                    'befehl' => $command,
                    'exit_code' => $exitCode,
                    'ausgabe' => array_map(static fn ($line) => trim((string) $line), $output),
                ];

                if ($exitCode === 0) {
                    audit_log('moodle_import_erfolgreich', $logContext);
                } else {
                    audit_log('moodle_import_fehlgeschlagen', $logContext);
                }
            }

            return $result;
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function getStatus(): array
    {
        $script = $this->getUploadScriptPath();

        return [
            'configured' => $this->isConfigured(),
            'moodle_root' => $this->moodleRoot,
            'script_path' => $script,
            'script_exists' => $this->scriptExists(),
            'php_binary' => $this->phpBinary,
            'php_exists' => $this->phpBinaryExists(),
        ];
    }

    private function createCsvFile(array $teilnehmer, ?string $courseShortname, ?string $roleShortname): string
    {
        $file = tempnam(sys_get_temp_dir(), 'moodle_import_');
        if ($file === false) {
            throw new \RuntimeException('Temporäre Datei für Moodle-Import konnte nicht erzeugt werden.');
        }

        $handle = fopen($file, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Temporäre Datei für Moodle-Import konnte nicht beschrieben werden.');
        }

        try {
            $header = ['username', 'password', 'firstname', 'lastname', 'email', 'profile_field_birthdate', 'profile_field_birthplace'];
            $shouldAddCourse = $courseShortname !== null && $courseShortname !== '';
            $shouldAddRole = $shouldAddCourse && $roleShortname !== null && $roleShortname !== '';

            if ($shouldAddCourse) {
                $header[] = 'course1';
                if ($shouldAddRole) {
                    $header[] = 'role1';
                }
            }
            fputcsv($handle, $header);

            foreach ($teilnehmer as $row) {
                if (!$row instanceof \RedBeanPHP\OODBBean) {
                    continue;
                }

                $username = (string) ($row->benutzername ?? '');
                $password = (string) ($row->passwort ?? '');
                $firstname = (string) ($row->vorname ?? '');
                $lastname = (string) ($row->nachname ?? '');
                $email = (string) ($row->email ?? '');
                $birthdate = (string) ($row->geburtsdatum ?? '');
                $birthplace = (string) ($row->geburtsort ?? '');

                $csvRow = [
                    $username,
                    $password,
                    $firstname,
                    $lastname,
                    $email,
                    $birthdate,
                    $birthplace,
                ];

                if ($shouldAddCourse) {
                    $csvRow[] = $courseShortname;
                    if ($shouldAddRole) {
                        $csvRow[] = $roleShortname;
                    }
                }

                fputcsv($handle, $csvRow);
            }
        } finally {
            fclose($handle);
        }

        return $file;
    }

    private function buildCommand(string $csvFile): string
    {
        $parts = [
            escapeshellarg($this->phpBinary),
            escapeshellarg($this->getUploadScriptPath()),
        ];

        $options = $this->defaultOptions;
        $options['file'] = $csvFile;

        foreach ($options as $option => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            $flag = '--' . $option;

            if ($value === true) {
                $parts[] = $flag . '=1';
                continue;
            }

            $stringValue = (string) $value;

            if (
                in_array($option, ['delimiter', 'encoding', 'updatemode'], true)
                && preg_match('/^[A-Za-z0-9_-]+$/', $stringValue)
            ) {
                $parts[] = $flag . '=' . $stringValue;
                continue;
            }

            $parts[] = $flag . '=' . escapeshellarg($stringValue);
        }

        return implode(' ', $parts);
    }

    private function phpBinaryExists(): bool
    {
        if ($this->phpBinary === '') {
            return false;
        }

        if (strpbrk($this->phpBinary, '/\\') !== false) {
            return is_file($this->phpBinary) && is_executable($this->phpBinary);
        }

        $paths = explode(PATH_SEPARATOR, (string) getenv('PATH'));

        foreach ($paths as $path) {
            $candidate = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->phpBinary;
            if ($candidate === DIRECTORY_SEPARATOR . $this->phpBinary) {
                continue;
            }

            if (is_file($candidate) && is_executable($candidate)) {
                return true;
            }
        }

        return false;
    }
}
