-- Menambahkan tabel perusahaan
CREATE TABLE `perusahaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Menambahkan tabel karyawan
CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `id_perusahaan` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `role` enum('admin','manager','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'pending',
  `type` enum('internal','external') NOT NULL DEFAULT 'internal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `id_perusahaan` (`id_perusahaan`),
  KEY `id_user` (`id_user`),
  CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `karyawan_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Menambahkan kolom id_perusahaan pada tabel users
ALTER TABLE `users` ADD COLUMN `id_perusahaan` int(11) DEFAULT NULL AFTER `nama_lengkap`;
ALTER TABLE `users` ADD COLUMN `email` varchar(100) DEFAULT NULL AFTER `nama_lengkap`;
ALTER TABLE `users` ADD COLUMN `no_hp` varchar(20) DEFAULT NULL AFTER `email`;
ALTER TABLE `users` ADD COLUMN `alamat` text DEFAULT NULL AFTER `no_hp`;
ALTER TABLE `users` ADD COLUMN `foto_profil` varchar(255) DEFAULT NULL AFTER `alamat`;
ALTER TABLE `users` ADD COLUMN `default_company` int(11) DEFAULT NULL AFTER `id_perusahaan`;
ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL;
ALTER TABLE `users` ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`default_company`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL;

-- Menambahkan kolom id_perusahaan pada tabel transaksi
ALTER TABLE `transaksi` ADD COLUMN `id_perusahaan` int(11) DEFAULT NULL AFTER `created_by`;
ALTER TABLE `transaksi` ADD CONSTRAINT `transaksi_ibfk_4` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

-- Menambahkan kolom id_perusahaan pada tabel akun
ALTER TABLE `akun` ADD COLUMN `id_perusahaan` int(11) DEFAULT NULL AFTER `created_at`;
ALTER TABLE `akun` ADD CONSTRAINT `akun_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

-- Menambahkan tabel untuk backup data
CREATE TABLE `backup` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_file` varchar(255) NOT NULL,
  `ukuran_file` int(11) NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user` int(11) NOT NULL,
  `id_perusahaan` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_user` (`id_user`),
  KEY `id_perusahaan` (`id_perusahaan`),
  CONSTRAINT `backup_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `backup_ibfk_2` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;