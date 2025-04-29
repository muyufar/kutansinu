<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validasi file
        if (!isset($_FILES['file_csv']) || $_FILES['file_csv']['error'] != 0) {
            throw new Exception('File tidak ditemukan atau rusak');
        }

        if ($_FILES['file_csv']['type'] !== 'text/csv' && !preg_match('/\.csv$/i', $_FILES['file_csv']['name'])) {
            throw new Exception('Format file tidak valid. Gunakan file CSV');
        }

        // Baca file CSV
        $rows = array_map('str_getcsv', file($_FILES['file_csv']['tmp_name']));
        if (!$rows) {
            throw new Exception('File CSV kosong atau tidak valid');
        }

        // Skip baris header
        array_shift($rows);

        // Mulai transaksi database
        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO transaksi (tanggal, id_akun, keterangan, jenis, jumlah, created_by) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($rows as $row) {
            if (empty($row[0])) continue; // Skip baris kosong

            // Validasi dan format data
            $tanggal = date('Y-m-d', strtotime($row[0]));
            $id_akun = validateInput($row[1]);
            $keterangan = validateInput($row[2]);
            $jenis = strtolower(validateInput($row[3]));
            $jumlah = floatval(str_replace([',', '.'], '.', $row[4]));

            // Validasi jenis transaksi
            if (!in_array($jenis, ['pemasukan', 'pengeluaran'])) {
                throw new Exception('Jenis transaksi tidak valid pada salah satu baris');
            }

            // Validasi akun
            $check = $db->prepare("SELECT id FROM akun WHERE id = ?");
            $check->execute([$id_akun]);
            if (!$check->fetch()) {
                throw new Exception('ID Akun tidak valid pada salah satu baris');
            }

            // Insert data
            $stmt->execute([
                $tanggal,
                $id_akun,
                $keterangan,
                $jenis,
                $jumlah,
                $_SESSION['user_id']
            ]);
        }

        $db->commit();
        $_SESSION['success'] = 'Data transaksi berhasil diimport';

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = 'Gagal import data: ' . $e->getMessage();
    }
}

header('Location: tambah.php');
exit();