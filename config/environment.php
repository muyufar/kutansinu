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

    if (in_array($host, $localHosts, true) || str_ends_with($host, '.test')) {
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

    $config = require $configFile;

    foreach (['host', 'dbname', 'username', 'password'] as $key) {
        if (!array_key_exists($key, $config)) {
            throw new RuntimeException("Konfigurasi database.{$environment}.php wajib memiliki key \"{$key}\".");
        }
    }

    $config['charset'] = $config['charset'] ?? 'utf8mb4';

    return $config;
}
