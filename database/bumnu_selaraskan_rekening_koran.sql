-- KAS BUMNU (id_perusahaan = 6) — selaraskan Bank BNU (785) dengan rekening koran
-- BACKUP database dulu. Sesuaikan @saldo_koran dan tanggal sebelum APPLY.
-- Alternatif: php scripts/bumnu_selaraskan_rekening.php --apply --saldo-koran=... --per=...

SET @id_perusahaan = 6;
SET @bank = 785;
SET @saldo_awal = 849;
SET @hutang_bnu = 834;
SET @hutang_nugo = 827;
SET @beban_bank = 866;
SET @created_by = 9;
SET @tag = 'BUMNU-SYNC-RK';

-- Isi dari rekening koran (contoh: saldo per 2026-10-08)
SET @saldo_koran = 55345807.00;
SET @tanggal_koran = '2026-10-08';

START TRANSACTION;

-- 1) Pemasukan terbalik: jenis pemasukan tapi bank di kredit → tukar
UPDATE transaksi t
INNER JOIN (
    SELECT id, id_akun_debit AS d, id_akun_kredit AS k
    FROM transaksi
    WHERE id_perusahaan = @id_perusahaan
      AND jenis = 'pemasukan'
      AND id_akun_kredit = @bank
      AND id_akun_debit <> @bank
) x ON t.id = x.id
SET t.id_akun_debit = x.k,
    t.id_akun_kredit = x.d;

-- 2) Pelunasan RK (salah ke Beban Bank)
UPDATE transaksi SET id_akun_debit = @hutang_nugo, jenis = 'transfer_hutang'
WHERE id = 661 AND id_perusahaan = @id_perusahaan AND id_akun_debit = @beban_bank;

UPDATE transaksi SET id_akun_debit = @hutang_bnu, jenis = 'transfer_hutang'
WHERE id IN (687, 2166) AND id_perusahaan = @id_perusahaan AND id_akun_debit = @beban_bank;

UPDATE transaksi SET total = jumlah
WHERE id = 699 AND id_perusahaan = @id_perusahaan;

-- 3) Saldo awal rekening (opsional — hapus comment jika perlu)
-- INSERT INTO transaksi (tanggal, id_akun_debit, id_akun_kredit, keterangan, jenis, jumlah, pajak, bunga, total, file_lampiran, penanggung_jawab, tag, created_by, id_perusahaan)
-- SELECT '2025-12-14', @bank, @saldo_awal, 'Saldo awal rekening Bank BNU (RK/koran)', 'tanam_modal', 552084689.00, 0, 0, 552084689.00, '', 'Sistem', CONCAT(@tag, '-SALDO-AWAL'), @created_by, @id_perusahaan
-- FROM DUAL
-- WHERE NOT EXISTS (SELECT 1 FROM transaksi WHERE id_perusahaan = @id_perusahaan AND tag = CONCAT(@tag, '-SALDO-AWAL'));

-- 4) Hutang RK awal — neraca saja (500jt BNU + 100jt NUGO)
-- INSERT ... lihat script PHP --catat-hutang-rk-awal

-- 5) Penyesuaian ke saldo koran (setelah langkah 1–2, hitung ulang di SiKeu lalu sesuaikan @saldo_koran)
-- @saldo_jurnal = SUM(debit 785) - SUM(kredit 785) per tanggal <= @tanggal_koran
-- @selisih = @saldo_koran - @saldo_jurnal

COMMIT;

-- Verifikasi:
-- SELECT
--   SUM(CASE WHEN id_akun_debit = 785 THEN jumlah ELSE 0 END) AS debit,
--   SUM(CASE WHEN id_akun_kredit = 785 THEN jumlah ELSE 0 END) AS kredit,
--   SUM(CASE WHEN id_akun_debit = 785 THEN jumlah ELSE 0 END)
--     - SUM(CASE WHEN id_akun_kredit = 785 THEN jumlah ELSE 0 END) AS saldo
-- FROM transaksi
-- WHERE id_perusahaan = 6 AND tanggal <= @tanggal_koran;
