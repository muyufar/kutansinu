<?php
// Script untuk memeriksa koneksi database dan data pengguna

// Koneksi ke database
$host = 'localhost';
$dbname = 'kutansinu_db';
$username = 'root';
$password = '';

echo "<h2>Pemeriksaan Database dan Data Pengguna</h2>";

try {
    // Koneksi ke database
    $db = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<p style='color:green'>Koneksi ke database berhasil!</p>";
    
    // Cek tabel users
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color:green'>Tabel users ditemukan.</p>";
        
        // Cek struktur tabel users
        $stmt = $db->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        
        echo "<h3>Struktur Tabel Users:</h3>";
        echo "<ul>";
        $required_columns = ['nama_lengkap', 'email', 'no_hp', 'alamat', 'foto_profil'];
        $missing_columns = $required_columns;
        
        foreach ($columns as $column) {
            echo "<li>{$column['Field']} - {$column['Type']}";
            if ($column['Null'] === 'NO') echo " (Required)";
            echo "</li>";
            
            // Hapus kolom dari daftar missing jika ditemukan
            if (in_array($column['Field'], $missing_columns)) {
                $key = array_search($column['Field'], $missing_columns);
                unset($missing_columns[$key]);
            }
        }
        echo "</ul>";
        
        // Tampilkan kolom yang hilang
        if (!empty($missing_columns)) {
            echo "<p style='color:red'>Kolom yang hilang: " . implode(", ", $missing_columns) . "</p>";
        } else {
            echo "<p style='color:green'>Semua kolom yang diperlukan tersedia.</p>";
        }
        
        // Cek data users
        $stmt = $db->query("SELECT * FROM users LIMIT 5");
        $users = $stmt->fetchAll();
        
        echo "<h3>Data Users:</h3>";
        if (count($users) > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr>";
            foreach (array_keys($users[0]) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            
            foreach ($users as $user) {
                echo "<tr>";
                foreach ($user as $value) {
                    // Jangan tampilkan password
                    if ($value === $user['password']) {
                        echo "<td>[HIDDEN]</td>";
                    } else {
                        echo "<td>" . (empty($value) ? "<span style='color:red'>KOSONG</span>" : $value) . "</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color:red'>Tidak ada data pengguna.</p>";
        }
    } else {
        echo "<p style='color:red'>Tabel users tidak ditemukan!</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color:red'>Error koneksi database: " . $e->getMessage() . "</p>";
}
?>