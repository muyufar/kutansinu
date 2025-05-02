# Panduan Menghubungkan Akun dengan Perusahaan

## Masalah
Saat ini, akun-akun dalam sistem tidak terhubung dengan perusahaan masing-masing. Hal ini menyebabkan semua akun terlihat oleh semua perusahaan, padahal seharusnya setiap perusahaan memiliki daftar akun dan saldo terpisah.

## Solusi

### 1. Menjalankan File SQL
Untuk mengatasi masalah ini, jalankan file SQL yang telah disediakan untuk menghubungkan akun dengan perusahaan:

```bash
mysql -u root kutansinu_db < h:/ucup/software/XAMPP/htdocs/kutansinu/database/update_akun_perusahaan.sql
```

Atau buka phpMyAdmin dan impor file `update_akun_perusahaan.sql` ke database `kutansinu_db`.

### 2. Perubahan yang Dilakukan

File SQL akan melakukan perubahan berikut:

1. Memastikan kolom `id_perusahaan` sudah ada di tabel `akun`
2. Menambahkan foreign key untuk menghubungkan akun dengan perusahaan
3. Mengupdate akun yang belum memiliki id_perusahaan berdasarkan default_company dari user
4. Menambahkan indeks untuk mempercepat query
5. Menambahkan kolom `created_by` untuk mengetahui siapa yang membuat akun

### 3. Modifikasi Kode PHP

File-file PHP yang mengelola akun telah dimodifikasi untuk:

1. Hanya menampilkan akun milik perusahaan yang sedang aktif
2. Menyimpan id_perusahaan saat membuat akun baru
3. Memastikan saat edit atau hapus akun, hanya akun milik perusahaan yang bisa dimodifikasi

## Cara Kerja

Setelah implementasi, sistem akan bekerja sebagai berikut:

1. Saat user login, sistem akan menggunakan `default_company` dari user tersebut
2. Saat menampilkan daftar akun, hanya akun milik perusahaan aktif yang ditampilkan
3. Saat membuat akun baru, akun akan otomatis terhubung dengan perusahaan aktif
4. Setiap perusahaan akan memiliki daftar akun dan saldo terpisah

## Pengujian

Untuk memastikan perubahan berfungsi dengan baik:

1. Login dengan user yang memiliki akses ke beberapa perusahaan
2. Pastikan saat beralih antar perusahaan, daftar akun yang ditampilkan berubah sesuai
3. Buat akun baru dan pastikan terhubung dengan perusahaan yang sedang aktif
4. Pastikan saldo akun terpisah antar perusahaan

Dengan implementasi ini, setiap perusahaan akan memiliki daftar akun dan saldo terpisah, sehingga data keuangan antar perusahaan tidak tercampur.