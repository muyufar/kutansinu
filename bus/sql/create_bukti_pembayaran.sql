CREATE TABLE IF NOT EXISTS bukti_pembayaran_bus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pemesanan_id INT NOT NULL,
    nama_file VARCHAR(255) NOT NULL,
    jenis_pembayaran ENUM('dp', 'lunas') NOT NULL,
    tanggal_upload DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pemesanan_id) REFERENCES pemesanan_bus(id) ON DELETE CASCADE
); 