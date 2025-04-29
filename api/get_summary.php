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
    // Query untuk mendapatkan total pemasukan
    $sql_pemasukan = "SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE jenis = 'pemasukan'";
    $stmt_pemasukan = $db->query($sql_pemasukan);
    $total_pemasukan = $stmt_pemasukan->fetch()['total'];

    // Query untuk mendapatkan total pengeluaran
    $sql_pengeluaran = "SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE jenis = 'pengeluaran'";
    $stmt_pengeluaran = $db->query($sql_pengeluaran);
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