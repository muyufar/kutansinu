<?php
// Script untuk memperbaiki masalah profil yang tidak menampilkan data

// Koneksi ke database
$host = 'localhost';
$dbname = 'kutansinu_db';
$username = 'root';
$password = '';

echo "<h2>Perbaikan Database Profil</h2>";

try {
    // Koneksi ke database
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green'>Koneksi ke database berhasil!</p>";
    
    // Cek apakah kolom yang diperlukan sudah ada
    $columns_to_check = ['nama_lengkap', 'email', 'no_hp', 'alamat', 'foto_profil', 'default_company'];
    $missing_columns = [];
    
    // Dapatkan daftar kolom yang ada di tabel users
    $stmt = $db->query("DESCRIBE users");
    $existing_columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }
    
    // Cek kolom yang hilang
    foreach ($columns_to_check as $column) {
        if (!in_array($column, $existing_columns)) {
            $missing_columns[] = $column;
        }
    }
    
    // Tambahkan kolom yang hilang
    if (!empty($missing_columns)) {
        echo "<p>Menambahkan kolom yang hilang: " . implode(", ", $missing_columns) . "</p>";
        
        foreach ($missing_columns as $column) {
            $sql = "";
            switch ($column) {
                case 'nama_lengkap':
                    $sql = "ALTER TABLE `users` ADD COLUMN `nama_lengkap` varchar(100) DEFAULT NULL AFTER `password`";
                    break;
                case 'email':
                    $sql = "ALTER TABLE `users` ADD COLUMN `email` varchar(100) DEFAULT NULL AFTER `nama_lengkap`";
                    break;
                case 'no_hp':
                    $sql = "ALTER TABLE `users` ADD COLUMN `no_hp` varchar(20) DEFAULT NULL AFTER `email`";
                    break;
                case 'alamat':
                    $sql = "ALTER TABLE `users` ADD COLUMN `alamat` text DEFAULT NULL AFTER `no_hp`";
                    break;
                case 'foto_profil':
                    $sql = "ALTER TABLE `users` ADD COLUMN `foto_profil` varchar(255) DEFAULT NULL AFTER `alamat`";
                    break;
                case 'default_company':
                    $sql = "ALTER TABLE `users` ADD COLUMN `default_company` int(11) DEFAULT NULL AFTER `foto_profil`";
                    break;
            }
            
            if (!empty($sql)) {
                try {
                    $db->exec($sql);
                    echo "<p style='color:green'>Berhasil menambahkan kolom $column</p>";
                } catch (PDOException $e) {
                    echo "<p style='color:orange'>Gagal menambahkan kolom $column: " . $e->getMessage() . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color:green'>Semua kolom yang diperlukan sudah ada.</p>";
    }
    
    // Periksa apakah ada data pengguna yang nama_lengkap-nya kosong
    $stmt = $db->query("SELECT id, username FROM users WHERE nama_lengkap IS NULL OR nama_lengkap = ''");
    $users_without_name = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($users_without_name) > 0) {
        echo "<p>Ditemukan " . count($users_without_name) . " pengguna tanpa nama lengkap. Mengupdate data...</p>";
        
        foreach ($users_without_name as $user) {
            // Update nama_lengkap dengan nilai username jika kosong
            $update_stmt = $db->prepare("UPDATE users SET nama_lengkap = ? WHERE id = ?");
            $update_stmt->execute([$user['username'], $user['id']]);
            echo "<p style='color:green'>Berhasil mengupdate nama lengkap untuk user ID " . $user['id'] . " (" . $user['username'] . ")</p>";
        }
    } else {
        echo "<p style='color:green'>Semua pengguna sudah memiliki nama lengkap.</p>";
    }
    
    echo "<p style='color:green'>Proses perbaikan database selesai!</p>";
    echo "<p>Silakan kembali ke <a href='pengaturan/profil.php'>halaman profil</a> untuk melihat hasilnya.</p>";
    
} catch(PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>