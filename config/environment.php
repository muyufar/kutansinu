<?php

function appEnvironment(): string
{
    static $environment = null;

    if ($environment !== null) {
        return $environment;
    }

    if (getenv('APP_ENV')) {
        $environment = strtolower(getenv('APP_ENV'));
        return $environment;
    }

    if (PHP_SAPI === 'cli') {
        $environment = file_exists(__DIR__ . '/database.production.php') ? 'production' : 'local';
        return $environment;
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    $host = strtolower(preg_replace('/:\d+$/', '', $host));

    $localHosts = ['localhost', '127.0.0.1', 'keuangan.test'];

    $isLocalTestDomain = (strlen($host) > 5 && substr($host, -5) === '.test');

    if (in_array($host, $localHosts, true) || $isLocalTestDomain) {
        $environment = 'local';
        return $environment;
    }

    $environment = 'production';
    return $environment;
}

function loadDatabaseConfig(): array
{
    $environment = appEnvironment();
    $configFile = __DIR__ . '/database.' . $environment . '.php';

    if (!file_exists($configFile)) {
        $exampleFile = __DIR__ . '/database.' . $environment . '.example.php';
        $message = "Konfigurasi database untuk environment \"{$environment}\" tidak ditemukan.\n";
        $message .= "Buat file: {$configFile}";

        if (file_exists($exampleFile)) {
            $message .= "\nSalin dari: {$exampleFile}";
        }

        throw new RuntimeException($message);
    }

    $head = (string) file_get_contents($configFile, false, null, 0, 32);
    if (strpos($head, '<?php') !== 0) {
        throw new RuntimeException(
            'File ' . basename($configFile) . ' tidak valid: baris pertama harus persis <?php '
            . '(editor online Hostinger sering mengubahnya menjadi k?php — ketik ulang tanda < di awal file).'
        );
    }

    $config = require $configFile;

    foreach (['host', 'dbname', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException("Konfigurasi database.{$environment}.php wajib memiliki key \"{$key}\".");
        }
    }

    $config['charset'] = $config['charset'] ?? 'utf8mb4';

    return $config;
}

function buildMysqlPdoDsn(array $dbConfig, string $hostOverride = null): string
{
    $host = $hostOverride ?? $dbConfig['host'];
    $dbname = $dbConfig['dbname'];
    $charset = $dbConfig['charset'] ?? 'utf8mb4';

    if (!empty($dbConfig['socket'])) {
        return 'mysql:unix_socket=' . $dbConfig['socket'] . ';dbname=' . $dbname . ';charset=' . $charset;
    }

    $dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=' . $charset;
    if (!empty($dbConfig['port'])) {
        $dsn .= ';port=' . (int) $dbConfig['port'];
    }

    return $dsn;
}

/**
 * Beberapa hosting (mis. Hostinger) menolak koneksi socket "localhost" (SQLSTATE 2002).
 */
function mysqlPdoHostCandidates(array $dbConfig): array
{
    $host = trim((string) $dbConfig['host']);
    if ($host === '') {
        return ['127.0.0.1'];
    }
    if (!empty($dbConfig['socket'])) {
        return [$host];
    }
    if (strtolower($host) === 'localhost') {
        return ['127.0.0.1', 'localhost'];
    }

    return [$host];
}
