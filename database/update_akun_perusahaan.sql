-- File: update_akun_perusahaan.sql
-- Deskripsi: File SQL untuk menghubungkan akun dengan perusahaan masing-masing

-- 1. Memastikan kolom id_perusahaan sudah ada di tabel akun
-- (Jika belum ada, tambahkan kolom dan foreign key)
ALTER TABLE `akun` ADD COLUMN IF NOT EXISTS `id_perusahaan` int(11) DEFAULT NULL AFTER `created_at`;

-- Tambahkan foreign key jika belum ada
-- Menggunakan pendekatan yang kompatibel dengan MariaDB
-- Mencoba menambahkan constraint, jika gagal karena sudah ada maka akan diabaikan
SET @query = 'ALTER TABLE `akun` ADD CONSTRAINT `akun_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE';
SET @check = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'akun_ibfk_1' AND TABLE_NAME = 'akun');
SET @query = IF(@check = 0, @query, 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Update akun yang belum memiliki id_perusahaan
-- Jika user memiliki default_company, gunakan itu untuk akun-akun yang belum terhubung
UPDATE `akun` a
JOIN `users` u ON a.created_by = u.id
SET a.id_perusahaan = u.default_company
WHERE a.id_perusahaan IS NULL AND u.default_company IS NOT NULL;

-- 3. Tambahkan indeks untuk mempercepat query
ALTER TABLE `akun` ADD INDEX IF NOT EXISTS `idx_id_perusahaan` (`id_perusahaan`);

-- 4. Tambahkan kolom created_by jika belum ada (untuk mengetahui siapa yang membuat akun)
ALTER TABLE `akun` ADD COLUMN IF NOT EXISTS `created_by` int(11) DEFAULT NULL AFTER `id_perusahaan`;

-- Tambahkan foreign key untuk created_by jika belum ada
SET @query = 'ALTER TABLE `akun` ADD CONSTRAINT `akun_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL';
SET @check = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'akun_ibfk_2' AND TABLE_NAME = 'akun');
SET @query = IF(@check = 0, @query, 'SELECT 1');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Tambahkan indeks untuk mempercepat query berdasarkan created_by
ALTER TABLE `akun` ADD INDEX IF NOT EXISTS `idx_created_by` (`created_by`);

-- Panduan Penggunaan:
-- 1. Jalankan file SQL ini melalui phpMyAdmin atau command line:
--    mysql -u root kutansinu_db < h:/ucup/software/XAMPP/htdocs/kutansinu/database/update_akun_perusahaan.sql
-- 2. Setelah menjalankan file ini, akun-akun akan terhubung dengan perusahaan masing-masing
--    berdasarkan default_company dari user yang membuat akun tersebut.