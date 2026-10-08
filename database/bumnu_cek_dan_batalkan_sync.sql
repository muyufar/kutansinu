-- KAS BUMNU: cek jurnal sinkron & batalkan penyesuaian yang mengosongkan bank
-- Jalankan di phpMyAdmin (Hostinger). BACKUP dulu.

SET @pid = 6;
SET @bank = 785;

-- 1) Saldo jurnal Bank BNU (harus ≈ rekening koran, BUKAN "Net jenis" dashboard)
SELECT
  SUM(CASE WHEN id_akun_debit = @bank THEN jumlah ELSE 0 END) AS total_debit,
  SUM(CASE WHEN id_akun_kredit = @bank THEN jumlah ELSE 0 END) AS total_kredit,
  SUM(CASE WHEN id_akun_debit = @bank THEN jumlah ELSE 0 END)
    - SUM(CASE WHEN id_akun_kredit = @bank THEN jumlah ELSE 0 END) AS saldo_jurnal
FROM transaksi
WHERE id_perusahaan = @pid;

-- 2) Semua jurnal otomatis sync (tag BUMNU-SYNC-RK)
SELECT id, tanggal, id_akun_debit, id_akun_kredit, jenis, jumlah, keterangan, tag
FROM transaksi
WHERE id_perusahaan = @pid AND tag LIKE 'BUMNU-SYNC-RK%'
ORDER BY id;

-- 3) Total pengeluaran dashboard (termasuk bayar hutang)
SELECT
  SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END) AS pemasukan,
  SUM(CASE WHEN jenis IN ('pengeluaran', 'transfer_hutang') THEN jumlah ELSE 0 END) AS pengeluaran,
  SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END)
    - SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END) AS net_jenis
FROM transaksi
WHERE id_perusahaan = @pid;

-- 4) HAPUS hanya jurnal penyesuaian/saldo awal otomatis (setelah review baris #2)
-- UNCOMMENT jika yakin — lalu jalankan ulang sync dengan saldo_koran yang benar:
-- DELETE FROM transaksi
-- WHERE id_perusahaan = @pid AND tag LIKE 'BUMNU-SYNC-RK%';
