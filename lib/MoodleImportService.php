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
            : ($envPhp ?: PHP_BINARY);

        $this->defaultOptions = $defaultOptions + [
            'delimiter' => 'comma',
            'encoding' => 'utf-8',
            'ignoreerrors' => true,
            'updatemode' => '0',
            'noemail' => true,
            'skipemail' => true,
        ];
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

        return $this->moodleRoot . '/admin/cli/uploaduser.php';
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
            exec($command . ' 2>&1', $output, $exitCode);

            return [
                'exit_code' => $exitCode,
                'output' => $output,
                'command' => $command,
            ];
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
            'php_exists' => $this->phpBinary !== '' && is_file($this->phpBinary),
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

            $parts[] = $flag . '=' . escapeshellarg((string) $value);
        }

        return implode(' ', $parts);
    }
}
