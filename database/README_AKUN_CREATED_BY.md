# Panduan Menghubungkan Akun dengan Perusahaan dan Menambahkan Created By

## Latar Belakang
Dalam sistem akuntansi Kutansinu, setiap akun perlu terhubung dengan perusahaan yang memilikinya dan perlu diketahui siapa yang membuat akun tersebut. Hal ini penting untuk:

1. Memastikan data akun terorganisir berdasarkan perusahaan
2. Melacak siapa yang membuat akun untuk keperluan audit
3. Memudahkan pengelolaan akun berdasarkan perusahaan

## Solusi

### Perubahan yang Dilakukan

File SQL `update_akun_created_by.sql` akan melakukan perubahan berikut:

1. Memastikan kolom `created_by` sudah ada di tabel `akun`
2. Menambahkan foreign key untuk menghubungkan akun dengan user yang membuatnya
3. Mengupdate akun yang belum memiliki id_perusahaan berdasarkan default_company dari user
4. Menambahkan indeks untuk mempercepat query

### Struktur Tabel Setelah Perubahan

Tabel `akun` akan memiliki struktur sebagai berikut:

```sql
CREATE TABLE `akun` (
  `id` int(11) NOT NULL,
  `kode_akun` varchar(10) NOT NULL,
  `nama_akun` varchar(100) NOT NULL,
  `kategori` enum('aktiva','pasiva','modal','pendapatan','beban') NOT NULL,
  `sub_kategori` varchar(250) NOT NULL,
  `tipe_akun` enum('debit','kredit') NOT NULL,
  `saldo` decimal(15,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_perusahaan` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
)
```

## Cara Penggunaan

### Menjalankan Script SQL

1. Buka phpMyAdmin melalui browser (http://localhost/phpmyadmin)
2. Pilih database `kutansinu_db`
3. Klik tab "SQL" di bagian atas
4. Salin dan tempel isi file `update_akun_created_by.sql` ke dalam kotak teks
5. Klik tombol "Go" untuk menjalankan script

Atau, jalankan melalui command line:

```bash
mysql -u root kutansinu_db < h:/ucup/software/XAMPP/htdocs/kutansinu/database/update_akun_created_by.sql
```

### Hasil yang Diharapkan

Setelah menjalankan script:

1. Semua akun akan terhubung dengan perusahaan melalui kolom `id_perusahaan`
2. Semua akun akan memiliki informasi pembuat akun melalui kolom `created_by`
3. Foreign key akan memastikan integritas data antara akun, perusahaan, dan user

## Catatan Penting

- Script ini mengasumsikan bahwa akun yang belum memiliki id_perusahaan dibuat oleh Admin (user_id = 1)
- Jika ada akun yang dibuat oleh user lain, perlu dilakukan update manual atau dengan script tambahan
- Pastikan untuk melakukan backup database sebelum menjalankan script ini