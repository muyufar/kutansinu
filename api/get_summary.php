<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Ambil id_perusahaan dari default_company pengguna
    $stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
    $stmt_company->execute([$_SESSION['user_id']]);
    $user_data = $stmt_company->fetch();
    $id_perusahaan = $user_data['default_company'];
    
    // Query untuk mendapatkan total pemasukan dengan filter perusahaan
    $sql_pemasukan = "SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE jenis = 'pemasukan' AND id_perusahaan = ?";
    $stmt_pemasukan = $db->prepare($sql_pemasukan);
    $stmt_pemasukan->execute([$id_perusahaan]);
    $total_pemasukan = $stmt_pemasukan->fetch()['total'];

    // Query untuk mendapatkan total pengeluaran dengan filter perusahaan
    $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE jenis = 'pengeluaran' AND id_perusahaan = ?";
    $stmt_pengeluaran = $db->prepare($sql_pengeluaran);
    $stmt_pengeluaran->execute([$id_perusahaan]);
    $total_pengeluaran = $stmt_pengeluaran->fetch()['total'];


    // Hitung saldo
    $saldo = $total_pemasukan - $total_pengeluaran;

    // Format angka ke format rupiah
    $formatted_pemasukan = 'Rp ' . number_format($total_pemasukan, 0, ',', '.');
    $formatted_pengeluaran = 'Rp ' . number_format($total_pengeluaran, 0, ',', '.');
    $formatted_saldo = 'Rp ' . number_format($saldo, 0, ',', '.');

    // Kirim response
    echo json_encode([
        'total_pemasukan' => $formatted_pemasukan,
        'total_pengeluaran' => $formatted_pengeluaran,
        'saldo' => $formatted_saldo
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}