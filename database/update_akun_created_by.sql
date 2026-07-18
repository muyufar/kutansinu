-- Script SQL untuk menambahkan kolom created_by di tabel akun dan menghubungkan akun dengan perusahaan
-- Dibuat pada: 2 Mei 2025

-- Mulai transaksi
START TRANSACTION;

-- 1. Memastikan kolom created_by sudah ada di tabel akun
ALTER TABLE `akun` ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL AFTER `id_perusahaan`;

-- 2. Tambahkan foreign key untuk created_by jika belum ada
SET @query = 'ALTER TABLE `akun` ADD CONSTRAINT `akun_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL';
SET @check = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'akun_ibfk_2' AND TABLE_NAME = 'akun');
SET @query = IF(@check = 0, @query, 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Tambahkan indeks untuk mempercepat query berdasarkan created_by
ALTER TABLE `akun` ADD INDEX IF NOT EXISTS `idx_created_by` (`created_by`);

-- 4. Update akun yang belum memiliki id_perusahaan dengan nilai default_company dari user
-- Asumsi: Admin (user_id = 1) adalah pembuat akun yang belum memiliki id_perusahaan
UPDATE `akun` SET 
    `id_perusahaan` = (SELECT `default_company` FROM `users` WHERE `id` = 1),
    `created_by` = 1
WHERE `id_perusahaan` IS NULL;

-- Commit transaksi
COMMIT;

-- Panduan Penggunaan:
-- 1. Jalankan file SQL ini melalui phpMyAdmin atau command line:
--    mysql -u root kutansinu_db < h:/ucup/software/XAMPP/htdocs/kutansinu/database/update_akun_created_by.sql
-- 2. Setelah menjalankan file ini, akun-akun akan terhubung dengan perusahaan masing-masing
--    berdasarkan default_company dari user yang membuat akun tersebut.
-- 3. Kolom created_by akan diisi dengan ID user yang membuat akun tersebut.

-- Catatan:
-- Script ini mengasumsikan bahwa akun yang belum memiliki id_perusahaan dibuat oleh Admin (user_id = 1).
-- Jika ada akun yang dibuat oleh user lain, perlu dilakukan update manual atau dengan script tambahan.