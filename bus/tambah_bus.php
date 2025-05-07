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
$nama_bus = validateInput($_POST['nama_bus']);
$tipe = validateInput($_POST['tipe']);
$nomor_polisi = validateInput($_POST['nomor_polisi']);
$kapasitas = (int)$_POST['kapasitas'];
$harga_per_km = (int)$_POST['harga_per_km'];
$status = validateInput($_POST['status']);
$fasilitas = validateInput($_POST['fasilitas']);

// Cek apakah nomor polisi sudah ada
$stmt = $db->prepare("SELECT 1 FROM bus WHERE nomor_polisi = ?");
$stmt->execute([$nomor_polisi]);
if ($stmt->fetch()) {
    $_SESSION['error'] = 'Nomor polisi sudah terdaftar';
    header('Location: index.php');
    exit();
}

// Upload foto jika ada
$foto = '';
if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png'];
    $filename = $_FILES['foto']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Validasi ekstensi file
    if (!in_array($ext, $allowed)) {
        $_SESSION['error'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG';
        header('Location: index.php');
        exit();
    }
    
    // Validasi ukuran file (max 2MB)
    if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
        $_SESSION['error'] = 'Ukuran file terlalu besar. Maksimal 2MB';
        header('Location: index.php');
        exit();
    }
    
    // Buat direktori jika belum ada
    $upload_dir = '../uploads/bus/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate nama file unik
    $foto = uniqid() . '_' . $filename;
    $destination = $upload_dir . $foto;
    
    // Upload file
    if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
        $_SESSION['error'] = 'Gagal mengupload foto';
        header('Location: index.php');
        exit();
    }
}

// Simpan data bus baru
try {
    $stmt = $db->prepare("INSERT INTO bus (nama_bus, tipe, nomor_polisi, kapasitas, harga_per_km, status, fasilitas, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nama_bus, $tipe, $nomor_polisi, $kapasitas, $harga_per_km, $status, $fasilitas, $foto]);
    
    $_SESSION['success'] = 'Bus baru berhasil ditambahkan';
    header('Location: index.php');
    exit();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Gagal menambahkan bus: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}