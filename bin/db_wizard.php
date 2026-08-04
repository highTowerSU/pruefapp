#!/usr/bin/env php
<?php
declare(strict_types=1);

// Erstellt Datenbank und einen separaten Prüfapp-Benutzer. Das Admin-Passwort
// wird nur für diesen Prozess verwendet und niemals gespeichert.
$options = getopt('', ['admin-dsn:', 'admin-user:', 'admin-password:', 'database::', 'app-user::']);
$dsn = (string) ($options['admin-dsn'] ?? '');
$adminUser = (string) ($options['admin-user'] ?? '');
$adminPassword = (string) ($options['admin-password'] ?? '');
$database = (string) ($options['database'] ?? 'pruefapp');
$appUser = (string) ($options['app-user'] ?? 'pruefapp');
if ($dsn === '' || $adminUser === '') {
    fwrite(STDERR, "Verwendung: php bin/db_wizard.php --admin-dsn=mysql:host=localhost --admin-user=root [--admin-password=...] [--database=pruefapp] [--app-user=pruefapp]\n");
    exit(2);
}
if ($adminPassword === '' && function_exists('shell_exec')) {
    fwrite(STDERR, 'Admin-Passwort: ');
    shell_exec('stty -echo'); $adminPassword = trim((string) fgets(STDIN)); shell_exec('stty echo'); fwrite(STDERR, PHP_EOL);
}
$appPassword = bin2hex(random_bytes(24));
$quote = static fn(string $value): string => '`' . str_replace('`', '``', $value) . '`';
try {
    $pdo = new PDO($dsn, $adminUser, $adminPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . $quote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec("CREATE USER IF NOT EXISTS '" . str_replace("'", "''", $appUser) . "'@'localhost' IDENTIFIED BY '" . str_replace("'", "''", $appPassword) . "'");
    $pdo->exec("GRANT ALL PRIVILEGES ON " . $quote($database) . ".* TO '" . str_replace("'", "''", $appUser) . "'@'localhost'");
    $pdo->exec('FLUSH PRIVILEGES');
    echo "Datenbank und App-Benutzer wurden eingerichtet.\n\nKonfiguration in /var/www/config/pruefapp.php ergänzen:\n\n";
    echo "    'APP_DATABASE_DSN' => 'mysql:host=127.0.0.1;dbname=" . addslashes($database) . ";charset=utf8mb4',\n";
    echo "    'APP_DATABASE_USER' => '" . addslashes($appUser) . "',\n";
    echo "    'APP_DATABASE_PASSWORD' => '" . addslashes($appPassword) . "',\n";
    echo "\nDas Admin-Passwort wurde nicht gespeichert.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Fehler: ' . $exception->getMessage() . PHP_EOL); exit(1);
}
