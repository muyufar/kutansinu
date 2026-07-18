<?php

/**
 * Salin file ini menjadi database.production.php lalu isi kredensial server production.
 * File database.production.php tidak di-push ke GitHub.
 *
 * PENTING:
 * - Wajib diawali <?php dan return [ ... ];
 * - Jangan pakai variabel $host / $dbname di sini (bukan format lama database.php)
 * - Password dengan karakter khusus cukup pakai tanda petik tunggal '...'
 */
return [
    'host' => 'localhost',
    'dbname' => 'u700125577_keuangan',
    'username' => 'u700125577_user',
    'password' => 'GANTI_DENGAN_PASSWORD_PRODUCTION',
    'charset' => 'utf8mb4',
];
