<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek role user (hanya nugrosir yang boleh mengakses halaman pemesanan bus)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Cek apakah user terhubung dengan perusahaan Nugrosir
$stmt_nugrosir = $db->prepare("SELECT 1 FROM user_perusahaan up
                    JOIN perusahaan p ON up.perusahaan_id = p.id
                    WHERE up.user_id = ? AND UPPER(p.nama) = 'NUGROSIR' AND up.status = 'active'");
$stmt_nugrosir->execute([$user_id]);
$is_nugrosir = $stmt_nugrosir->fetch() ? true : false;

// Verifikasi role user (hanya untuk nugrosir)
if (!$is_nugrosir) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya perusahaan NUGROSIR yang dapat mengakses fitur pemesanan bus.';
    header('Location: /kutansinu/index.php');
    exit();
}

// Cek apakah form sudah disubmit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Metode request tidak valid';
    header('Location: index.php');
    exit();
}

// Validasi input
$tipe_bus = validateInput($_POST['tipe_bus']);
$jumlah_penumpang = (int)$_POST['jumlah_penumpang'];
$kota_asal = validateInput($_POST['kota_asal']);
$kota_tujuan = validateInput($_POST['kota_tujuan']);
$tanggal_berangkat = validateInput($_POST['tanggal_berangkat']);
$tanggal_kembali = validateInput($_POST['tanggal_kembali']);
$waktu_berangkat = validateInput($_POST['waktu_berangkat']);
$fasilitas = isset($_POST['fasilitas']) ? $_POST['fasilitas'] : [];
$catatan = validateInput($_POST['catatan']);

// Validasi tanggal
$today = date('Y-m-d');
if ($tanggal_berangkat < $today) {
    $_SESSION['error'] = 'Tanggal keberangkatan tidak boleh kurang dari hari ini';
    header('Location: index.php');
    exit();
}

if ($tanggal_kembali < $tanggal_berangkat) {
    $_SESSION['error'] = 'Tanggal kembali tidak boleh kurang dari tanggal keberangkatan';
    header('Location: index.php');
    exit();
}

// Cari bus yang sesuai dengan tipe
$stmt = $db->prepare("SELECT * FROM bus WHERE tipe = ? AND status = 'tersedia' ORDER BY kapasitas DESC LIMIT 1");
$stmt->execute([$tipe_bus]);
$bus = $stmt->fetch();

if (!$bus) {
    $_SESSION['error'] = 'Tidak ada bus dengan tipe tersebut yang tersedia';
    header('Location: index.php');
    exit();
}

// Hitung durasi dalam hari
$date1 = new DateTime($tanggal_berangkat);
$date2 = new DateTime($tanggal_kembali);
$interval = $date1->diff($date2);
$durasi = $interval->days + 1; // +1 karena termasuk hari keberangkatan

// Hitung perkiraan jarak (contoh sederhana)
$jarak_perkiraan = 100; // km, ini hanya contoh, bisa diganti dengan API jarak

// Hitung total harga
$harga_per_km = $bus['harga_per_km'];
$total_harga = $harga_per_km * $jarak_perkiraan * $durasi;

// Tambahkan biaya tambahan untuk fasilitas
$biaya_tambahan = 0;
if (in_array('WiFi', $fasilitas)) $biaya_tambahan += 100000;
if (in_array('Toilet', $fasilitas)) $biaya_tambahan += 150000;
if (in_array('TV', $fasilitas)) $biaya_tambahan += 200000;
if (in_array('Karaoke', $fasilitas)) $biaya_tambahan += 250000;

$total_harga += $biaya_tambahan;

// Simpan data pemesanan
try {
    // Gabungkan fasilitas menjadi string
    $fasilitas_str = implode(', ', $fasilitas);
    
    // Tambahkan informasi durasi dan tanggal kembali ke catatan
    $catatan_lengkap = "Durasi: $durasi hari (Kembali: $tanggal_kembali). " . $catatan;
    
    $stmt = $db->prepare("INSERT INTO pemesanan_bus (id_user, id_bus, tanggal_pemesanan, tanggal_berangkat, waktu_berangkat, kota_asal, kota_tujuan, jumlah_penumpang, total_harga, status, catatan) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $user_id, 
        $bus['id'], 
        $tanggal_berangkat, 
        $waktu_berangkat, 
        $kota_asal, 
        $kota_tujuan, 
        $jumlah_penumpang, 
        $total_harga, 
        'menunggu_pembayaran', 
        $catatan_lengkap
    ]);
    
    $pemesanan_id = $db->lastInsertId();
    
    $_SESSION['success'] = 'Pemesanan bus berhasil dibuat. Silakan lakukan pembayaran.';
    header('Location: upload_bukti.php?id=' . $pemesanan_id);
    exit();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Gagal membuat pemesanan: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}