<?php

require_once __DIR__ . '/environment.php';

$dbConfig = loadDatabaseConfig();

$host = $dbConfig['host'];
$dbname = $dbConfig['dbname'];
$username = $dbConfig['username'];
$password = $dbConfig['password'];
$charset = $dbConfig['charset'];

try {
    $db = new PDO(
        "mysql:host={$host};dbname={$dbname};charset={$charset}",
        $username,
        $password
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Koneksi database gagal: ' . $e->getMessage();
    exit();
}
