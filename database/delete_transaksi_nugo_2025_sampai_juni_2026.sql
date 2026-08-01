-- =============================================================================
-- HAPUS TRANSAKSI NUGO (user bus: nugo@gmail.com / id_perusahaan = 3)
-- Periode: 1 Januari 2025 s/d 30 Juni 2026 (inklusif)
-- =============================================================================
--
-- PERINGATAN:
-- 1. BACKUP database dulu (export full atau minimal tabel transaksi).
-- 2. Jalankan bagian PREVIEW dulu, pastikan jumlah data sesuai harapan.
-- 3. Baru jalankan bagian DELETE di dalam transaksi.
-- 4. File lampiran di uploads/transaksi/ TIDAK otomatis terhapus dari disk.
--
-- Target:
--   perusahaan NUGO (id = 3)
--   user     nugo@gmail.com (id = 4, default_company = 3)
-- =============================================================================

SET @id_perusahaan = 3;
SET @tanggal_awal   = '2025-01-01';
SET @tanggal_akhir  = '2026-06-30';

-- =============================================================================
-- A. PREVIEW — cek jumlah sebelum hapus
-- =============================================================================

SELECT 'transaksi_nugo' AS tabel, COUNT(*) AS jumlah, COALESCE(SUM(jumlah), 0) AS total_jumlah
FROM transaksi
WHERE id_perusahaan = @id_perusahaan
  AND tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

SELECT jenis, COUNT(*) AS jumlah, COALESCE(SUM(jumlah), 0) AS total_jumlah
FROM transaksi
WHERE id_perusahaan = @id_perusahaan
  AND tanggal BETWEEN @tanggal_awal AND @tanggal_akhir
GROUP BY jenis
ORDER BY jumlah DESC;

SELECT YEAR(tanggal) AS tahun, MONTH(tanggal) AS bulan, COUNT(*) AS jumlah
FROM transaksi
WHERE id_perusahaan = @id_perusahaan
  AND tanggal BETWEEN @tanggal_awal AND @tanggal_akhir
GROUP BY YEAR(tanggal), MONTH(tanggal)
ORDER BY tahun, bulan;

SELECT 'bus_transaksi_sync_terkait' AS tabel, COUNT(*) AS jumlah
FROM bus_transaksi_sync bts
INNER JOIN transaksi t ON t.id = bts.transaksi_id
WHERE t.id_perusahaan = @id_perusahaan
  AND t.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

-- Sample 20 transaksi yang akan dihapus (review manual)
SELECT t.id, t.tanggal, t.jenis, t.jumlah, t.keterangan, t.created_by, t.tag
FROM transaksi t
WHERE t.id_perusahaan = @id_perusahaan
  AND t.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir
ORDER BY t.tanggal ASC, t.id ASC
LIMIT 20;

-- =============================================================================
-- B. DELETE — jalankan hanya setelah preview OK
-- =============================================================================

START TRANSACTION;

-- 1) Hapus mapping sync bus ↔ transaksi (harus dulu sebelum hapus transaksi)
DELETE bts
FROM bus_transaksi_sync bts
INNER JOIN transaksi t ON t.id = bts.transaksi_id
WHERE t.id_perusahaan = @id_perusahaan
  AND t.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

-- 2) Hapus transaksi NUGO periode target
DELETE FROM transaksi
WHERE id_perusahaan = @id_perusahaan
  AND tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

-- Verifikasi setelah delete (harus 0)
SELECT COUNT(*) AS sisa_transaksi_periode
FROM transaksi
WHERE id_perusahaan = @id_perusahaan
  AND tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

-- Jika sudah yakin benar:
COMMIT;

-- Jika ada yang salah, ganti COMMIT dengan:
-- ROLLBACK;

-- =============================================================================
-- C. OPSIONAL — hapus data operasional bus periode yang sama
--     (trip/maintenance Juni 2026 ke bawah, TIDAK wajib)
--     Uncomment jika ingin sekalian bersihkan laporan operasional bus.
-- =============================================================================

/*
START TRANSACTION;

DELETE bts
FROM bus_transaksi_sync bts
INNER JOIN bus_laporan_trip trip ON trip.id = bts.source_id AND bts.source_module = 'bus_laporan_trip'
INNER JOIN bus b ON b.id = trip.id_bus
WHERE b.id_perusahaan = @id_perusahaan
  AND trip.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

DELETE trip
FROM bus_laporan_trip trip
INNER JOIN bus b ON b.id = trip.id_bus
WHERE b.id_perusahaan = @id_perusahaan
  AND trip.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

DELETE bts
FROM bus_transaksi_sync bts
INNER JOIN bus_laporan_maintenance m ON m.id = bts.source_id AND bts.source_module = 'bus_laporan_maintenance'
INNER JOIN bus b ON b.id = m.id_bus
WHERE b.id_perusahaan = @id_perusahaan
  AND m.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

DELETE m
FROM bus_laporan_maintenance m
INNER JOIN bus b ON b.id = m.id_bus
WHERE b.id_perusahaan = @id_perusahaan
  AND m.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

COMMIT;
*/

-- =============================================================================
-- D. OPSIONAL — filter alternatif by user pembuat (created_by = 4)
--     Gunakan HANYA jika ingin hapus transaksi yang dibuat user nugo@gmail.com
--     meskipun id_perusahaan berbeda (umumnya TIDAK disarankan).
-- =============================================================================

/*
DELETE bts
FROM bus_transaksi_sync bts
INNER JOIN transaksi t ON t.id = bts.transaksi_id
WHERE t.created_by = 4
  AND t.tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;

DELETE FROM transaksi
WHERE created_by = 4
  AND tanggal BETWEEN @tanggal_awal AND @tanggal_akhir;
*/
