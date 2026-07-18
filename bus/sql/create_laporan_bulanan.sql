-- Laporan bulanan bus: trip harian dan maintenance
CREATE TABLE IF NOT EXISTS bus_laporan_trip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_bus INT NOT NULL,
    tanggal DATE NOT NULL,
    nama_order VARCHAR(255) DEFAULT NULL,
    tujuan TEXT DEFAULT NULL,
    harga_sewa DECIMAL(15,2) NOT NULL DEFAULT 0,
    bbm DECIMAL(15,2) NOT NULL DEFAULT 0,
    um DECIMAL(15,2) NOT NULL DEFAULT 0,
    driver DECIMAL(15,2) NOT NULL DEFAULT 0,
    co_driver DECIMAL(15,2) NOT NULL DEFAULT 0,
    toll DECIMAL(15,2) NOT NULL DEFAULT 0,
    parkir DECIMAL(15,2) NOT NULL DEFAULT 0,
    pogah DECIMAL(15,2) NOT NULL DEFAULT 0,
    crew VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bus_tanggal (id_bus, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bus_laporan_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_bus INT NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT DEFAULT NULL,
    biaya DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bus_tanggal (id_bus, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE bus ADD COLUMN IF NOT EXISTS kode_armada VARCHAR(20) DEFAULT NULL AFTER nomor_polisi;
ALTER TABLE bus ADD COLUMN IF NOT EXISTS lembar_saham INT NOT NULL DEFAULT 0 AFTER kode_armada;
