<?php

class MoodleCourseService
{
    private string $moodleRoot;
    private string $phpBinary;
    private array $defaultOptions;
    private string $webserviceBaseUrl;
    private string $webserviceToken;
    private int $httpTimeout;
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cachedCourses = null;
    /**
     * @var array<int, array<int, array<string, mixed>>>
     */
    private array $cachedEnrolments = [];

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

        $this->webserviceBaseUrl = $defaultOptions['webservice_base_url'] ?? moodle_webservice_base_url();
        $this->webserviceToken = $defaultOptions['webservice_token'] ?? moodle_webservice_token();
        $this->httpTimeout = (int) ($defaultOptions['webservice_timeout'] ?? 20);
        if ($this->httpTimeout <= 0) {
            $this->httpTimeout = 20;
        }
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

    public function getWebserviceBaseUrl(): string
    {
        return $this->webserviceBaseUrl;
    }

    public function getWebserviceToken(): string
    {
        return $this->webserviceToken;
    }

    public function isConfigured(): bool
    {
        return $this->moodleRoot !== '';
    }

    public function isWebserviceConfigured(): bool
    {
        return $this->webserviceBaseUrl !== '' && $this->webserviceToken !== '';
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

    public function getWebserviceStatus(): array
    {
        return [
            'configured' => $this->isWebserviceConfigured(),
            'base_url' => $this->webserviceBaseUrl,
            'token_configured' => $this->webserviceToken !== '',
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

    public function fetchCourses(): array
    {
        if ($this->cachedCourses !== null) {
            return $this->cachedCourses;
        }

        $response = $this->performRestCall('core_course_get_courses');

        if (!is_array($response)) {
            return [];
        }

        $courses = [];

        foreach ($response as $course) {
            if (is_object($course)) {
                $course = get_object_vars($course);
            }

            if (!is_array($course)) {
                continue;
            }

            $courses[] = [
                'id' => isset($course['id']) ? (int) $course['id'] : 0,
                'shortname' => (string) ($course['shortname'] ?? ''),
                'fullname' => (string) ($course['fullname'] ?? ''),
                'idnumber' => (string) ($course['idnumber'] ?? ''),
            ];
        }

        $this->cachedCourses = $courses;

        return $this->cachedCourses;
    }

    public function findCourseByShortname(string $shortname): ?array
    {
        $shortname = trim($shortname);
        if ($shortname === '') {
            return null;
        }

        $courses = $this->fetchCourses();
        foreach ($courses as $course) {
            if (!isset($course['shortname'])) {
                continue;
            }

            if (strcasecmp((string) $course['shortname'], $shortname) === 0) {
                return $course;
            }
        }

        return null;
    }

    public function fetchEnrolments(int $courseId): array
    {
        if ($courseId <= 0) {
            return [];
        }

        if (isset($this->cachedEnrolments[$courseId])) {
            return $this->cachedEnrolments[$courseId];
        }

        $response = $this->performRestCall('core_enrol_get_enrolled_users', [
            'courseid' => $courseId,
        ]);

        if (!is_array($response)) {
            return [];
        }

        $enrolments = [];

        foreach ($response as $user) {
            if (is_object($user)) {
                $user = get_object_vars($user);
            }

            if (!is_array($user)) {
                continue;
            }

            $customFields = [];
            if (isset($user['customfields']) && is_array($user['customfields'])) {
                foreach ($user['customfields'] as $field) {
                    if (is_object($field)) {
                        $field = get_object_vars($field);
                    }

                    if (!is_array($field)) {
                        continue;
                    }

                    $shortname = (string) ($field['shortname'] ?? '');
                    if ($shortname === '') {
                        continue;
                    }

                    $customFields[$shortname] = (string) ($field['value'] ?? '');
                }
            }

            $enrolments[] = [
                'id' => isset($user['id']) ? (int) $user['id'] : 0,
                'username' => (string) ($user['username'] ?? ''),
                'firstname' => (string) ($user['firstname'] ?? ''),
                'lastname' => (string) ($user['lastname'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'idnumber' => (string) ($user['idnumber'] ?? ''),
                'customfields' => $customFields,
            ];
        }

        $this->cachedEnrolments[$courseId] = $enrolments;

        return $this->cachedEnrolments[$courseId];
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

    private function performRestCall(string $function, array $parameters = [])
    {
        if (!$this->isWebserviceConfigured()) {
            throw new \RuntimeException('Moodle-Webservice ist nicht konfiguriert.');
        }

        $endpoint = $this->resolveRestEndpoint();

        $query = [
            'wstoken' => $this->webserviceToken,
            'moodlewsrestformat' => 'json',
            'wsfunction' => $function,
        ] + $parameters;

        $request = http_build_query($query, '', '&');

        $handle = curl_init($endpoint);
        if ($handle === false) {
            throw new \RuntimeException('cURL konnte nicht initialisiert werden.');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_TIMEOUT => $this->httpTimeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->httpTimeout),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            $errno = curl_errno($handle);
            curl_close($handle);

            $message = $error !== '' ? $error : ('cURL Fehler #' . $errno);

            throw new \RuntimeException('Anfrage an den Moodle-Webservice fehlgeschlagen: ' . $message);
        }

        $httpStatus = curl_getinfo($handle, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($handle);

        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new \RuntimeException('Moodle-Webservice antwortete mit HTTP-Status ' . $httpStatus . '.');
        }

        $decoded = json_decode($response, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Antwort des Moodle-Webservice konnte nicht verarbeitet werden: ' . json_last_error_msg());
        }

        if (is_array($decoded) && isset($decoded['exception'])) {
            $message = (string) ($decoded['message'] ?? $decoded['exception']);

            throw new \RuntimeException('Moodle-Webservice meldet einen Fehler: ' . $message);
        }

        return $decoded;
    }

    private function resolveRestEndpoint(): string
    {
        $base = rtrim($this->webserviceBaseUrl, '/');
        if ($base === '') {
            throw new \RuntimeException('Basis-URL für Moodle-Webservices ist leer.');
        }

        if (preg_match('#/webservice/rest/server\.php$#', $base)) {
            return $base;
        }

        return $base . '/webservice/rest/server.php';
    }
}
