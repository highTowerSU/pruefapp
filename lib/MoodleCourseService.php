<?php

class MoodleCourseService
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
            'visible' => 1,
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

    public function getDuplicateScriptPath(): string
    {
        if ($this->moodleRoot === '') {
            return '';
        }

        return $this->moodleRoot . '/course/management/cli/duplicate_course.php';
    }

    public function isConfigured(): bool
    {
        return $this->moodleRoot !== '';
    }

    public function scriptExists(): bool
    {
        $script = $this->getDuplicateScriptPath();

        return $script !== '' && is_file($script);
    }

    public function canDuplicate(): bool
    {
        return $this->isConfigured() && $this->scriptExists();
    }

    public function getStatus(): array
    {
        $script = $this->getDuplicateScriptPath();

        return [
            'configured' => $this->isConfigured(),
            'moodle_root' => $this->moodleRoot,
            'script_path' => $script,
            'script_exists' => $this->scriptExists(),
            'php_binary' => $this->phpBinary,
            'php_exists' => $this->phpBinary !== '' && is_file($this->phpBinary),
        ];
    }

    public function duplicateCourse(string $sourceShortname, string $newFullname, string $newShortname, array $options = []): array
    {
        if (!$this->canDuplicate()) {
            throw new \RuntimeException('Moodle-Kurskopie ist nicht konfiguriert oder das Skript wurde nicht gefunden.');
        }

        $sourceShortname = trim($sourceShortname);
        $newShortname = trim($newShortname);
        $newFullname = trim($newFullname);

        if ($sourceShortname === '') {
            throw new \InvalidArgumentException('Der Shortname des Quellkurses darf nicht leer sein.');
        }

        if ($newShortname === '') {
            throw new \InvalidArgumentException('Der Shortname des neuen Moodle-Kurses darf nicht leer sein.');
        }

        if ($newFullname === '') {
            $newFullname = $newShortname;
        }

        $command = $this->buildCommand([
            'courseshortname' => $sourceShortname,
            'fullname' => $newFullname,
            'shortname' => $newShortname,
        ] + $options);

        $output = [];
        $exitCode = 1;
        exec($command . ' 2>&1', $output, $exitCode);

        return [
            'exit_code' => $exitCode,
            'output' => $output,
            'command' => $command,
            'course_id' => $this->parseCourseIdFromOutput($output),
        ];
    }

    private function buildCommand(array $options): string
    {
        $parts = [
            escapeshellarg($this->phpBinary),
            escapeshellarg($this->getDuplicateScriptPath()),
        ];

        $options = $this->defaultOptions + $options;

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

    private function parseCourseIdFromOutput(array $output): ?int
    {
        foreach ($output as $line) {
            if (preg_match('/New course id\s*:\s*(\d+)/i', $line, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
