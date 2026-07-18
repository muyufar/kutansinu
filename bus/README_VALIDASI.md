# Sistem Validasi Pemesanan Bus

## Overview

Sistem validasi ini mencegah pemesanan ganda bus pada tanggal yang sama dan memastikan setiap bus hanya bisa dipesan jika tidak ada jadwal yang konflik.

## Fitur Validasi

### 1. Validasi Ketersediaan Bus

- **Fungsi**: `checkBusAvailability()`
- **Tujuan**: Memastikan bus tidak sudah dipesan pada tanggal yang sama
- **Parameter**:
  - `$bus_id`: ID bus yang akan dipesan
  - `$tanggal_berangkat`: Tanggal keberangkatan
  - `$waktu_berangkat`: Waktu keberangkatan (opsional)
  - `$exclude_pemesanan_id`: ID pemesanan yang dikecualikan (untuk edit)

### 2. Validasi Komprehensif

- **Fungsi**: `validateBusBooking()`
- **Tujuan**: Validasi lengkap sebelum pemesanan disimpan
- **Validasi yang dilakukan**:
  1. **Ketersediaan Bus**: Bus tidak sudah dipesan pada tanggal yang sama
  2. **Konflik Waktu**: Tidak ada konflik jadwal dengan pemesanan lain
  3. **Jenis Bus Unik**: Setiap jenis bus hanya bisa dipesan satu kali per tanggal
  4. **Status Bus**: Bus harus dalam status aktif
  5. **Waktu Keberangkatan**: Antara pukul 05:00 - 23:00

### 3. Jadwal Bus yang Tersedia

- **Fungsi**: `getAvailableBusSchedule()`
- **Tujuan**: Menampilkan daftar bus yang tersedia untuk tanggal tertentu
- **Fitur**:
  - Menghitung sisa kapasitas
  - Filter berdasarkan status bus
  - Exclude bus yang sudah dipesan

## Implementasi di Frontend

### Halaman Pemesanan (`pesan.php`)

- **Card Jadwal Tersedia**: Menampilkan bus yang tersedia untuk tanggal yang dipilih
- **Real-time Update**: Jadwal diupdate otomatis saat user memilih tanggal
- **Highlight Bus Dipilih**: Bus yang dipilih ditandai dengan warna hijau

### AJAX Endpoint (`get_available_schedule.php`)

- **Endpoint**: `/bus/get_available_schedule.php`
- **Parameter**:
  - `tanggal`: Tanggal yang dipilih
  - `bus_id`: ID bus yang sedang dipilih
- **Response**: HTML table dengan jadwal bus yang tersedia

## Struktur Database

### Tabel `bus`

- `id`: Primary key
- `nama_bus`: Nama bus
- `jenis_bus`: Jenis bus (VIP, Standard, dll)
- `kapasitas`: Kapasitas maksimal penumpang
- `status_bus`: Status bus (aktif, maintenance, rusak)

### Tabel `pemesanan_bus`

- `id`: Primary key
- `id_bus`: Foreign key ke tabel bus
- `tanggal_berangkat`: Tanggal keberangkatan
- `waktu_berangkat`: Waktu keberangkatan
- `status`: Status pemesanan
- `jumlah_penumpang`: Jumlah penumpang yang dipesan

## Status Pemesanan yang Dianggap Aktif

- `pending`: Menunggu verifikasi
- `dibayar_dp`: Sudah bayar DP
- `dibayar`: Lunas
- `diverifikasi`: Sudah diverifikasi admin
- `dikonfirmasi`: Sudah dikonfirmasi

## Contoh Penggunaan

### Validasi Sebelum Pemesanan

```php
$validation_result = validateBusBooking($db, $bus_id, $tanggal_berangkat, $waktu_berangkat);
if (!$validation_result['valid']) {
    $_SESSION['error'] = $validation_result['message'];
    // Redirect atau tampilkan error
}
```

### Cek Ketersediaan Bus

```php
$is_available = checkBusAvailability($db, $bus_id, $tanggal_berangkat);
if ($is_available) {
    // Bus sudah dipesan
}
```

### Dapatkan Jadwal Tersedia

```php
$available_buses = getAvailableBusSchedule($db, $tanggal_berangkat);
foreach ($available_buses as $bus) {
    echo "Bus: " . $bus['nama_bus'] . " - Sisa: " . $bus['sisa_kapasitas'];
}
```

## Keuntungan Sistem Validasi

1. **Mencegah Double Booking**: Bus tidak bisa dipesan ganda pada tanggal yang sama
2. **Jenis Bus Unik**: Setiap jenis bus hanya bisa dipesan satu kali per tanggal
3. **Validasi Waktu**: Mencegah konflik jadwal yang terlalu dekat
4. **Real-time Info**: User bisa melihat ketersediaan bus secara langsung
5. **Keamanan**: Validasi di backend dan frontend
6. **User Experience**: Pesan error yang jelas dan informatif

## Troubleshooting

### Error "Bus sudah dipesan"

- Cek apakah ada pemesanan lain dengan status aktif
- Pastikan tanggal dan waktu tidak konflik
- Cek apakah jenis bus sudah dipesan pada tanggal yang sama

### Error "Konflik jadwal"

- Ada pemesanan lain dengan waktu yang sama atau terlalu dekat (< 2 jam)
- Cek jadwal bus lain pada tanggal yang sama

### Error "Jenis bus sudah dipesan"

- Setiap jenis bus hanya bisa dipesan satu kali per tanggal
- Pilih tanggal lain atau jenis bus yang berbeda
