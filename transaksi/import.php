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

        // Ambil id_perusahaan dari default_company pengguna
        $stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
        $stmt_company->execute([$_SESSION['user_id']]);
        $user_data = $stmt_company->fetch();
        $id_perusahaan = $user_data['default_company'];
        
        // Pastikan pengguna memiliki perusahaan default
        if (!$id_perusahaan) {
            throw new Exception('Anda belum memiliki perusahaan default. Silakan tambahkan perusahaan terlebih dahulu.');
        }

        $stmt = $db->prepare("INSERT INTO transaksi (tanggal, id_akun_debit, id_akun_kredit, keterangan, jenis, jumlah, created_by, id_perusahaan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($rows as $row) {
            if (empty($row[0])) continue; // Skip baris kosong

            // Validasi dan format data
            $tanggal = date('Y-m-d', strtotime($row[0]));
            $id_akun_debit = validateInput($row[1]);
            $id_akun_kredit = validateInput($row[2]);
            $keterangan = validateInput($row[3]);
            $jenis = strtolower(validateInput($row[4]));
            $jumlah = floatval(str_replace([',', '.'], '.', $row[5]));

            // Validasi jenis transaksi
            if (!in_array($jenis, ['pemasukan', 'pengeluaran', 'hutang', 'piutang', 'tanam_modal', 'tarik_modal', 'transfer_uang', 'pemasukan_piutang', 'transfer_hutang'])) {
                throw new Exception('Jenis transaksi tidak valid pada salah satu baris');
            }

            // Validasi akun debit
            $check_debit = $db->prepare("SELECT id FROM akun WHERE id = ?");
            $check_debit->execute([$id_akun_debit]);
            if (!$check_debit->fetch()) {
                throw new Exception('ID Akun Debit tidak valid pada salah satu baris');
            }
            
            // Validasi akun kredit
            $check_kredit = $db->prepare("SELECT id FROM akun WHERE id = ?");
            $check_kredit->execute([$id_akun_kredit]);
            if (!$check_kredit->fetch()) {
                throw new Exception('ID Akun Kredit tidak valid pada salah satu baris');
            }

            // Insert data
            $stmt->execute([
                $tanggal,
                $id_akun_debit,
                $id_akun_kredit,
                $keterangan,
                $jenis,
                $jumlah,
                $_SESSION['user_id'],
                $id_perusahaan
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