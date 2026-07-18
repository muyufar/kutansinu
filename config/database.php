<?php
$host = 'localhost';
$dbname = 'u700125577_keuangan';
$username = 'root';
$password = '';
// Koneksi ke database
try {
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    echo "Koneksi database gagal: " . $e->getMessage();
    exit();
}
?>