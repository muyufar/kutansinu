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

// Cek parameter ID pemesanan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID pemesanan tidak valid';
    header('Location: riwayat.php');
    exit();
}

$pemesanan_id = (int)$_GET['id'];

// Ambil data pemesanan
$stmt = $db->prepare("
    SELECT pb.*, b.nama_bus, b.nomor_polisi, b.kapasitas, b.fasilitas, u.nama_lengkap as nama_admin
    FROM pemesanan_bus pb
    JOIN bus b ON pb.id_bus = b.id
    JOIN users u ON pb.id_user = u.id
    WHERE pb.id = ? AND pb.id_user = ?
");
$stmt->execute([$pemesanan_id, $user_id]);
$pemesanan = $stmt->fetch();

if (!$pemesanan) {
    $_SESSION['error'] = 'Pemesanan tidak ditemukan atau Anda tidak memiliki akses';
    header('Location: riwayat.php');
    exit();
}

// Cek status pemesanan
if ($pemesanan['status'] != 'selesai' && $pemesanan['status'] != 'dikonfirmasi') {
    $_SESSION['error'] = 'Tiket hanya dapat dicetak untuk pemesanan yang sudah dikonfirmasi atau selesai';
    header('Location: riwayat.php');
    exit();
}

// Generate kode tiket
$kode_tiket = 'BUS' . str_pad($pemesanan['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tiket Bus - <?php echo $kode_tiket; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f8f9fa;
        }
        .ticket-container {
            max-width: 800px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .ticket-header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .ticket-body {
            padding: 20px;
        }
        .ticket-footer {
            background-color: #f8f9fa;
            padding: 15px;
            text-align: center;
            border-top: 1px dashed #dee2e6;
        }
        .ticket-qr {
            text-align: center;
            margin: 20px 0;
        }
        .ticket-info {
            margin-bottom: 20px;
        }
        .ticket-info h5 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .print-button {
            margin: 20px 0;
            text-align: center;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                background-color: white;
            }
            .ticket-container {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-button">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Cetak Tiket
            </button>
            <a href="riwayat.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
            </a>
        </div>
        
        <div class="ticket-container">
            <div class="ticket-header">
                <h2>E-TIKET BUS</h2>
                <h4><?php echo $kode_tiket; ?></h4>
            </div>
            
            <div class="ticket-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="ticket-info">
                            <h5>Informasi Bus</h5>
                            <p><strong>Nama Bus:</strong> <?php echo htmlspecialchars($pemesanan['nama_bus']); ?></p>
                            <p><strong>Nomor Polisi:</strong> <?php echo htmlspecialchars($pemesanan['nomor_polisi']); ?></p>
                            <p><strong>Kapasitas:</strong> <?php echo $pemesanan['kapasitas']; ?> Penumpang</p>
                            <p><strong>Fasilitas:</strong> <?php echo htmlspecialchars($pemesanan['fasilitas']); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="ticket-info">
                            <h5>Detail Perjalanan</h5>
                            <p><strong>Tanggal Keberangkatan:</strong> <?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?></p>
                            <p><strong>Waktu Keberangkatan:</strong> <?php echo date('H:i', strtotime($pemesanan['waktu_berangkat'])); ?></p>
                            <p><strong>Rute:</strong> <?php echo htmlspecialchars($pemesanan['kota_asal']); ?> - <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?></p>
                            <p><strong>Jumlah Penumpang:</strong> <?php echo $pemesanan['jumlah_penumpang']; ?> orang</p>
                        </div>
                    </div>
                </div>
                
                <div class="ticket-info">
                    <h5>Informasi Pemesan</h5>
                    <p><strong>CS Pemesan:</strong> <?php echo htmlspecialchars($pemesanan['nama_admin']); ?></p>
                    <p><strong>ID Pemesanan:</strong> #<?php echo $pemesanan['id']; ?></p>
                    <p><strong>Tanggal Pemesanan:</strong> <?php echo date('d/m/Y H:i', strtotime($pemesanan['tanggal_pemesanan'])); ?></p>
                    <p><strong>Total Harga:</strong> <?php echo formatRupiah($pemesanan['total_harga']); ?></p>
                    <p><strong>Nama Pemesan:</strong> <?php echo htmlspecialchars($pemesanan['nama_pemesan']); ?></p>
                </div>
                
                <div class="ticket-qr">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($kode_tiket); ?>" alt="QR Code">
                    <p class="mt-2">Scan QR Code ini untuk validasi tiket</p>
                </div>
            </div>
            
            <div class="ticket-footer">
                <p class="mb-0">Tiket ini adalah bukti sah perjalanan. Harap tiba 30 menit sebelum keberangkatan.</p>
                <p class="mb-0">Untuk informasi lebih lanjut, hubungi +6281320221926</p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>