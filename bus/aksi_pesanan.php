<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/pemesanan_helper.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$is_staff = isBusStaff($db, $user_id);
$action = validateInput($_POST['action'] ?? '');
$pemesanan_id = (int) ($_POST['pemesanan_id'] ?? 0);
$redirect_url = $_POST['redirect'] ?? ($is_staff ? 'verifikasi_pesanan.php' : 'riwayat.php');

if (!$pemesanan_id) {
    $_SESSION['error'] = 'ID pemesanan tidak valid.';
    header('Location: ' . $redirect_url);
    exit();
}

$pemesanan = getPemesananById($db, $pemesanan_id);
if (!$pemesanan) {
    $_SESSION['error'] = 'Pemesanan tidak ditemukan.';
    header('Location: ' . $redirect_url);
    exit();
}

if (!userCanAccessPemesanan($db, $user_id, $pemesanan)) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke pemesanan ini.';
    header('Location: index.php');
    exit();
}

switch ($action) {
    case 'cancel':
        $catatan = validateInput($_POST['catatan_batal'] ?? '');
        $result = cancelPemesananBus($db, $pemesanan_id, $catatan, $is_staff);
        break;

    case 'delete':
        if (!$is_staff) {
            $_SESSION['error'] = 'Hanya admin/staff bus yang dapat menghapus pemesanan permanen.';
            header('Location: ' . $redirect_url);
            exit();
        }
        $result = deletePemesananBus($db, $pemesanan_id);
        break;

    default:
        $_SESSION['error'] = 'Aksi tidak valid.';
        header('Location: ' . $redirect_url);
        exit();
}

if ($result['success']) {
    $_SESSION['success'] = $result['message'];
} else {
    $_SESSION['error'] = $result['message'];
}

header('Location: ' . $redirect_url);
exit();
