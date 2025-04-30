-- Menambahkan tabel pengaturan untuk menyimpan preferensi pengguna
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Menambahkan komentar untuk menjelaskan tujuan tabel
ALTER TABLE `pengaturan` COMMENT = 'Tabel untuk menyimpan pengaturan dan preferensi pengguna';

-- Menambahkan indeks untuk mempercepat pencarian
ALTER TABLE `pengaturan` ADD INDEX `idx_preview_transaksi` (`preview_transaksi`);