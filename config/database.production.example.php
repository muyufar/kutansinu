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
    // Hostinger hPanel → Websites → Databases → hostname MySQL (sering localhost).
    // Jika muncul SQLSTATE[2002] Operation not permitted, ganti ke 127.0.0.1 + port 3306.
    'host' => '127.0.0.1',
    'port' => 3306,
    'dbname' => 'u700125577_keuangan',
    'username' => 'u700125577_user',
    'password' => 'GANTI_DENGAN_PASSWORD_PRODUCTION',
    'charset' => 'utf8mb4',
];
