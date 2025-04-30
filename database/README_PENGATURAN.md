# Panduan Mengatasi Error Tabel Pengaturan

## Masalah
Error yang terjadi: `Fatal error : Uncaught PDOException: SQLSTATE[42S02]: Base table or view not found: 1146 Table 'kutansinu_db.pengaturan' doesn't exist`

Error ini terjadi karena tabel `pengaturan` belum dibuat di database `kutansinu_db`.

## Solusi

### 1. Menjalankan File SQL
Untuk mengatasi masalah ini, jalankan file SQL yang telah disediakan untuk membuat tabel pengaturan:

```bash
mysql -u root kutansinu_db < h:/ucup/software/XAMPP/htdocs/kutansinu/database/add_pengaturan_table.sql
```

Atau buka phpMyAdmin dan impor file `add_pengaturan_table.sql` ke database `kutansinu_db`.

### 2. Struktur Tabel Pengaturan
File SQL akan membuat tabel dengan struktur berikut:

```sql
CREATE TABLE `pengaturan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preview_transaksi` tinyint(1) NOT NULL DEFAULT 1,
  `format_angka` varchar(20) NOT NULL DEFAULT '1.000.000,00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `pengaturan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);
```

## Perubahan yang Dilakukan

1. File `pengaturan_utama.php` telah dimodifikasi untuk menangani kasus ketika tabel belum ada:
   - Memeriksa keberadaan tabel sebelum melakukan query
   - Menggunakan pengaturan default jika tabel belum ada
   - Menampilkan pesan peringatan kepada pengguna

2. File SQL `add_pengaturan_table.sql` telah dibuat untuk membuat tabel pengaturan.

Setelah menjalankan file SQL, aplikasi akan berfungsi normal dan pengaturan pengguna akan disimpan dengan benar di database.