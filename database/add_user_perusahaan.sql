-- Menambahkan tabel user_perusahaan untuk kontrol akses berbasis peran
CREATE TABLE `user_perusahaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `role` enum('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_perusahaan_unique` (`user_id`, `perusahaan_id`),
  KEY `user_id` (`user_id`),
  KEY `perusahaan_id` (`perusahaan_id`),
  CONSTRAINT `user_perusahaan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_perusahaan_ibfk_2` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Menambahkan komentar untuk menjelaskan tujuan tabel
ALTER TABLE `user_perusahaan` COMMENT = 'Tabel untuk menyimpan relasi antara user dan perusahaan dengan role dan status';

-- Menambahkan indeks untuk mempercepat pencarian berdasarkan role
ALTER TABLE `user_perusahaan` ADD INDEX `idx_role` (`role`);

-- Menambahkan indeks untuk mempercepat pencarian berdasarkan status
ALTER TABLE `user_perusahaan` ADD INDEX `idx_status` (`status`);