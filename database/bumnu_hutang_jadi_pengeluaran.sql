-- KAS BUMNU: pelunasan hutang tampil sebagai pengeluaran (jenis), akun tetap hutang (834/827)
UPDATE transaksi
SET jenis = 'pengeluaran'
WHERE id_perusahaan = 6
  AND jenis = 'transfer_hutang'
  AND id_akun_kredit = 785
  AND id_akun_debit IN (834, 827, 866);
