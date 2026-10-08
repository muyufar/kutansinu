<?php

/**
 * Salin file ini menjadi database.production.php lalu isi kredensial server production.
 * File database.production.php tidak di-push ke GitHub.
 *
 * PENTING:
 * - Baris 1 WAJIB: <?php  (jika editor Hostinger mengubah jadi "k?php", perbaiki manual)
 * - Hanya SATU key 'host' — jangan dobel localhost / 127.0.0.1
 * - Password dengan karakter khusus cukup pakai tanda petik tunggal '...'
 */
return [
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'u700125577_keuangan',
    'username' => 'u700125577_keuangan',
    'password' => 'GANTI_DENGAN_PASSWORD_PRODUCTION',
    'charset' => 'utf8mb4',
];
