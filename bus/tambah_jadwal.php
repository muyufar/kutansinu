<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek apakah user terhubung dengan perusahaan Nugrosir
$user_id = $_SESSION['user_id'];
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
$id_bus = (int)$_POST['id_bus'];
$tanggal_berangkat = validateInput($_POST['tanggal_berangkat']);
$waktu_berangkat = validateInput($_POST['waktu_berangkat']);
$kota_asal = validateInput($_POST['kota_asal']);
$kota_tujuan = validateInput($_POST['kota_tujuan']);
$harga = (int)$_POST['harga'];
$estimasi_durasi = (int)$_POST['estimasi_durasi'];

// Validasi tanggal (tidak boleh kurang dari hari ini)
if (strtotime($tanggal_berangkat) < strtotime(date('Y-m-d'))) {
    $_SESSION['error'] = 'Tanggal keberangkatan tidak boleh kurang dari hari ini';
    header('Location: index.php');
    exit();
}

// Cek apakah bus tersedia
$stmt = $db->prepare("SELECT status FROM bus WHERE id = ?");
$stmt->execute([$id_bus]);
$bus = $stmt->fetch();

if (!$bus || $bus['status'] !== 'tersedia') {
    $_SESSION['error'] = 'Bus tidak tersedia atau tidak ditemukan';
    header('Location: index.php');
    exit();
}

// Cek apakah jadwal bentrok dengan jadwal lain
$stmt = $db->prepare("
    SELECT COUNT(*) FROM jadwal_bus 
    WHERE id_bus = ? 
    AND tanggal_berangkat = ? 
    AND status = 'tersedia'
    AND (
        (waktu_berangkat <= ? AND ADDTIME(waktu_berangkat, SEC_TO_TIME(estimasi_durasi * 60)) > ?)
        OR
        (waktu_berangkat >= ? AND waktu_berangkat < ADDTIME(?, SEC_TO_TIME(? * 60)))
    )
");
$stmt->execute([
    $id_bus,
    $tanggal_berangkat,
    $waktu_berangkat,
    $waktu_berangkat,
    $waktu_berangkat,
    $waktu_berangkat,
    $estimasi_durasi
]);

if ($stmt->fetchColumn() > 0) {
    $_SESSION['error'] = 'Jadwal bentrok dengan jadwal lain untuk bus yang sama';
    header('Location: index.php');
    exit();
}

// Simpan jadwal bus baru
try {
    $stmt = $db->prepare("
        INSERT INTO jadwal_bus (
            id_bus, tanggal_berangkat, waktu_berangkat, 
            kota_asal, kota_tujuan, harga, 
            estimasi_durasi, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'tersedia', NOW())
    ");
    
    $stmt->execute([
        $id_bus,
        $tanggal_berangkat,
        $waktu_berangkat,
        $kota_asal,
        $kota_tujuan,
        $harga,
        $estimasi_durasi
    ]);
    
    $_SESSION['success'] = 'Jadwal bus berhasil ditambahkan';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Gagal menambahkan jadwal bus: ' . $e->getMessage();
}

header('Location: index.php');
exit();