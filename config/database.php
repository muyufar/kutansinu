<?php

try {
    require_once __DIR__ . '/environment.php';

    $dbConfig = loadDatabaseConfig();
    $username = $dbConfig['username'];
    $password = $dbConfig['password'];

    $lastError = null;
    $db = null;

    foreach (mysqlPdoHostCandidates($dbConfig) as $hostTry) {
        try {
            $db = new PDO(
                buildMysqlPdoDsn($dbConfig, $hostTry),
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
            break;
        } catch (PDOException $e) {
            $lastError = $e;
        }
    }

    if (!$db instanceof PDO) {
        throw $lastError ?? new RuntimeException('Koneksi database gagal.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    if (function_exists('appEnvironment') && appEnvironment() === 'production') {
        echo 'Koneksi database gagal. Periksa config/database.production.php (host MySQL dari hPanel Hostinger).';
    } else {
        echo 'Koneksi database gagal: ' . $e->getMessage();
    }
    exit();
}
