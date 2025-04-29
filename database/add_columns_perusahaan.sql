-- Menambahkan kolom website dan jenis ke tabel perusahaan
ALTER TABLE `perusahaan` ADD COLUMN `website` varchar(255) DEFAULT NULL AFTER `email`;
ALTER TABLE `perusahaan` ADD COLUMN `jenis` enum('regular','premium') NOT NULL DEFAULT 'regular' AFTER `logo`;

-- Menambahkan indeks untuk mempercepat pencarian berdasarkan jenis
ALTER TABLE `perusahaan` ADD INDEX `idx_jenis` (`jenis`);

-- Menambahkan komentar untuk menjelaskan tujuan kolom
ALTER TABLE `perusahaan` MODIFY COLUMN `website` varchar(255) DEFAULT NULL COMMENT 'URL website perusahaan';
ALTER TABLE `perusahaan` MODIFY COLUMN `jenis` enum('regular','premium') NOT NULL DEFAULT 'regular' COMMENT 'Jenis akun perusahaan';