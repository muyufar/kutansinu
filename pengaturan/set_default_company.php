<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Ambil data user
$user_id = $_SESSION['user_id'];

// Cek parameter
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID perusahaan tidak valid';
    header('Location: perusahaan.php');
    exit();
}

$perusahaan_id = (int)$_GET['id'];
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'perusahaan.php';

// Verifikasi bahwa perusahaan ini terkait dengan user
$stmt = $db->prepare("SELECT COUNT(*) FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
$stmt->execute([$user_id, $perusahaan_id]);
$is_valid = $stmt->fetchColumn() > 0;

if (!$is_valid) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke perusahaan ini';
    header('Location: ' . $redirect);
    exit();
}

// Update default company
try {
    $stmt = $db->prepare("UPDATE users SET default_company = ? WHERE id = ?");
    $stmt->execute([$perusahaan_id, $user_id]);
    
    $_SESSION['success'] = 'Default perusahaan berhasil diubah';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Gagal mengubah default perusahaan: ' . $e->getMessage();
}

// Redirect kembali ke halaman sebelumnya
header('Location: ' . $redirect);
exit();