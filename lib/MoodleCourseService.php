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

    public function getImportScriptPath(): string
    {
        if ($this->moodleRoot === '') {
            return '';
        }

        return $this->moodleRoot . '/admin/cli/import.php';
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
        return $this->legacyScriptExists() || $this->importScriptExists();
    }

    public function canDuplicate(): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        if ($this->legacyScriptExists()) {
            return true;
        }

        return $this->importScriptExists() && $this->isWebserviceConfigured();
    }

    public function getStatus(): array
    {
        $legacyScript = $this->getDuplicateScriptPath();
        $importScript = $this->getImportScriptPath();

        return [
            'configured' => $this->isConfigured(),
            'moodle_root' => $this->moodleRoot,
            'script_path' => $this->legacyScriptExists() ? $legacyScript : $importScript,
            'script_exists' => $this->scriptExists(),
            'legacy_script_path' => $legacyScript,
            'legacy_script_exists' => $this->legacyScriptExists(),
            'import_script_path' => $importScript,
            'import_script_exists' => $this->importScriptExists(),
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
            throw new \RuntimeException('Moodle-Kurskopie ist nicht konfiguriert. Benötigt wird entweder course/management/cli/duplicate_course.php oder admin/cli/import.php (mit Webservice-Zugang).');
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

        if ($this->legacyScriptExists()) {
            return $this->duplicateWithLegacyScript($sourceShortname, $newFullname, $newShortname, $options);
        }

        return $this->duplicateWithImportScript($sourceShortname, $newFullname, $newShortname, $options);
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
                'categoryid' => isset($course['categoryid']) ? (int) $course['categoryid'] : 0,
                'visible' => isset($course['visible']) ? (int) $course['visible'] : 1,
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

    private function buildScriptCommand(string $scriptPath, array $options): string
    {
        $parts = [
            escapeshellarg($this->phpBinary),
            escapeshellarg($scriptPath),
        ];

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

    private function legacyScriptExists(): bool
    {
        $script = $this->getDuplicateScriptPath();

        return $script !== '' && is_file($script);
    }

    private function importScriptExists(): bool
    {
        $script = $this->getImportScriptPath();

        return $script !== '' && is_file($script);
    }

    private function duplicateWithLegacyScript(string $sourceShortname, string $newFullname, string $newShortname, array $options): array
    {
        $command = $this->buildScriptCommand(
            $this->getDuplicateScriptPath(),
            $this->defaultOptions + [
                'courseshortname' => $sourceShortname,
                'fullname' => $newFullname,
                'shortname' => $newShortname,
            ] + $options
        );

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

    private function duplicateWithImportScript(string $sourceShortname, string $newFullname, string $newShortname, array $options): array
    {
        $sourceCourse = $this->findCourseByShortname($sourceShortname);
        if ($sourceCourse === null || empty($sourceCourse['id'])) {
            throw new \RuntimeException('Quellkurs mit dem angegebenen Shortname wurde in Moodle nicht gefunden.');
        }

        $targetCourse = $this->findCourseByShortname($newShortname);
        if ($targetCourse === null || empty($targetCourse['id'])) {
            $targetCourse = $this->createCourseViaWebservice($newFullname, $newShortname, [
                'categoryid' => (int) ($sourceCourse['categoryid'] ?? 0),
                'visible' => isset($options['visible']) ? (int) $options['visible'] : (int) ($sourceCourse['visible'] ?? 1),
            ]);
        }

        $targetCourseId = (int) ($targetCourse['id'] ?? 0);
        if ($targetCourseId <= 0) {
            throw new \RuntimeException('Zielkurs konnte nicht ermittelt oder angelegt werden.');
        }

        $command = $this->buildScriptCommand(
            $this->getImportScriptPath(),
            [
                'srccourseid' => (int) $sourceCourse['id'],
                'dstcourseid' => $targetCourseId,
            ]
        );

        $output = [];
        $exitCode = 1;
        exec($command . ' 2>&1', $output, $exitCode);

        return [
            'exit_code' => $exitCode,
            'output' => $output,
            'command' => $command,
            'course_id' => $targetCourseId,
        ];
    }

    private function createCourseViaWebservice(string $fullname, string $shortname, array $options = []): array
    {
        $categoryId = (int) ($options['categoryid'] ?? 0);
        if ($categoryId <= 0) {
            $categoryId = 1;
        }

        $visible = isset($options['visible']) ? (int) $options['visible'] : 1;

        $response = $this->performRestCall('core_course_create_courses', [
            'courses' => [[
                'fullname' => $fullname,
                'shortname' => $shortname,
                'categoryid' => $categoryId,
                'visible' => $visible,
            ]],
        ]);

        if (!is_array($response) || !isset($response[0]) || !is_array($response[0])) {
            throw new \RuntimeException('Moodle hat beim Anlegen des Zielkurses keine verwertbare Antwort geliefert.');
        }

        $created = $response[0];
        $this->cachedCourses = null;

        return [
            'id' => isset($created['id']) ? (int) $created['id'] : 0,
            'shortname' => (string) ($created['shortname'] ?? $shortname),
            'fullname' => (string) ($created['fullname'] ?? $fullname),
            'idnumber' => (string) ($created['idnumber'] ?? ''),
            'categoryid' => isset($created['categoryid']) ? (int) $created['categoryid'] : $categoryId,
            'visible' => isset($created['visible']) ? (int) $created['visible'] : $visible,
        ];
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
