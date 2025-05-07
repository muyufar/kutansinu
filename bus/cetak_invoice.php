<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek parameter ID pemesanan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID pemesanan tidak valid');
}

$pemesanan_id = (int)$_GET['id'];

// Ambil data pemesanan
$stmt = $db->prepare("
    SELECT pb.*, jb.*, b.*, u.nama_lengkap, u.email, u.no_hp
    FROM pemesanan_bus pb
    JOIN jadwal_bus jb ON pb.id_jadwal_bus = jb.id
    JOIN bus b ON pb.id_bus = b.id
    JOIN users u ON pb.id_user = u.id
    WHERE pb.id = ?
");
$stmt->execute([$pemesanan_id]);
$pemesanan = $stmt->fetch();

if (!$pemesanan) {
    die('Data pemesanan tidak ditemukan');
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo str_pad($pemesanan['id'], 5, '0', STR_PAD_LEFT); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }

        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .company-info {
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .total-section {
            text-align: right;
            margin-top: 30px;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="invoice-header">
        <div class="invoice-title">INVOICE PEMESANAN BUS</div>
        <div>Nomor: #<?php echo str_pad($pemesanan['id'], 5, '0', STR_PAD_LEFT); ?></div>
    </div>

    <div class="company-info">
        <strong>NUGROSIR TRANSPORT</strong><br>
        Jl. Raya Utama No. 123<br>
        Telp: (021) 1234567<br>
        Email: info@nugrosirtransport.com
    </div>

    <table>
        <tr>
            <td width="200"><strong>Tanggal Pemesanan</strong></td>
            <td>: <?php echo date('d/m/Y', strtotime($pemesanan['tanggal_pemesanan'])); ?></td>
        </tr>
        <tr>
            <td><strong>Nama Pemesan</strong></td>
            <td>: <?php echo htmlspecialchars($pemesanan['nama_lengkap']); ?></td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td>: <?php echo htmlspecialchars($pemesanan['email']); ?></td>
        </tr>
        <tr>
            <td><strong>Telepon</strong></td>
            <td>: <?php echo htmlspecialchars($pemesanan['no_hp']); ?></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Bus</td>
                <td><?php echo htmlspecialchars($pemesanan['nama_bus']); ?> (<?php echo htmlspecialchars($pemesanan['tipe']); ?>)</td>
            </tr>
            <tr>
                <td>Tanggal & Waktu Berangkat</td>
                <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?> <?php echo date('H:i', strtotime($pemesanan['waktu_berangkat'])); ?> WIB</td>
            </tr>
            <tr>
                <td>Rute</td>
                <td><?php echo htmlspecialchars($pemesanan['kota_asal'] . ' - ' . $pemesanan['kota_tujuan']); ?></td>
            </tr>
            <tr>
                <td>Jumlah Penumpang</td>
                <td><?php echo $pemesanan['jumlah_penumpang']; ?> orang</td>
            </tr>
        </tbody>
    </table>

    <div class="total-section">
        <h3>Total Pembayaran: <?php echo formatRupiah($pemesanan['total_harga']); ?></h3>
        <p>Status: <?php echo ucwords(str_replace('_', ' ', $pemesanan['status'])); ?></p>
    </div>

    <div class="footer">
        <p>Terima kasih telah menggunakan layanan kami.<br>
            Invoice ini adalah bukti pembayaran yang sah.</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Cetak Invoice</button>
    </div>
</body>

</html>