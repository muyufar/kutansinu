<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek parameter ID pemesanan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID pemesanan tidak valid';
    header('Location: index.php');
    exit();
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
    $_SESSION['error'] = 'Data pemesanan tidak ditemukan';
    header('Location: index.php');
    exit();
}

// Proses upload bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cek apakah ada file yang diupload
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] == UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = 'Silakan pilih file bukti pembayaran';
    } else {
        $bukti_pembayaran = '';
        
        // Proses upload file
        $file = $_FILES['bukti_pembayaran'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if ($file['size'] > $max_size) {
            $_SESSION['error'] = 'Ukuran file terlalu besar (maksimal 5MB)';
        } elseif (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = 'Tipe file tidak didukung (hanya JPG, PNG, GIF, dan PDF)';
        } else {
            // Buat direktori jika belum ada
            $upload_dir = '../uploads/bukti_pembayaran/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate nama file unik
            $filename = 'bukti_' . $pemesanan_id . '_' . time() . '_' . $user_id . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $bukti_pembayaran = $filename;
                
                // Update status pemesanan
                try {
                    $stmt = $db->prepare("UPDATE pemesanan_bus SET bukti_pembayaran = ?, status = 'dibayar' WHERE id = ?");
                    $stmt->execute([$bukti_pembayaran, $pemesanan_id]);
                    
                    $_SESSION['success'] = 'Bukti pembayaran berhasil diupload. Pembayaran Anda sedang diverifikasi.';
                    header('Location: riwayat.php');
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Gagal mengupload bukti pembayaran: ' . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = 'Gagal mengupload file';
            }
        }
    }
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Upload Bukti Pembayaran</h2>
        <div>
            <a href="cetak_invoice.php?id=<?php echo $pemesanan_id; ?>" class="btn btn-primary me-2" target="_blank">
                <i class="fas fa-print"></i> Cetak Invoice
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Detail Pemesanan</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td width="200"><strong>Nomor Pemesanan</strong></td>
                            <td>: #<?php echo str_pad($pemesanan['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Pemesanan</strong></td>
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
                        <tr>
                            <td><strong>Bus</strong></td>
                            <td>: <?php echo htmlspecialchars($pemesanan['nama_bus']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tanggal Berangkat</strong></td>
                            <td>: <?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Waktu Berangkat</strong></td>
                            <td>: <?php echo date('H:i', strtotime($pemesanan['waktu_berangkat'])); ?> WIB</td>
                        </tr>
                        <tr>
                            <td><strong>Rute</strong></td>
                            <td>: <?php echo htmlspecialchars($pemesanan['kota_asal'] . ' - ' . $pemesanan['kota_tujuan']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Jumlah Penumpang</strong></td>
                            <td>: <?php echo $pemesanan['jumlah_penumpang']; ?> orang</td>
                        </tr>
                        <tr>
                            <td><strong>Total Harga</strong></td>
                            <td>: <?php echo formatRupiah($pemesanan['total_harga']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>: <span class="badge bg-<?php echo $pemesanan['status'] == 'menunggu_pembayaran' ? 'warning' : ($pemesanan['status'] == 'dibayar' ? 'success' : 'info'); ?>">
                                <?php echo ucwords(str_replace('_', ' ', $pemesanan['status'])); ?>
                            </span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <!-- Form upload bukti pembayaran -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Form Upload Bukti Pembayaran</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Informasi Pembayaran</h6>
                            <p class="mb-0">Silakan lakukan pembayaran ke rekening berikut:</p>
                            <ul class="mb-0">
                                <li>Bank BCA: 1234567890 a.n. PT Nugrosir Indonesia</li>
                                <li>Bank Mandiri: 0987654321 a.n. PT Nugrosir Indonesia</li>
                            </ul>
                        </div>
                        <div class="mb-3">
                            <label for="bukti_pembayaran" class="form-label">Bukti Pembayaran</label>
                            <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*,.pdf" required>
                            <small class="text-muted">Format yang didukung: JPG, PNG, GIF, PDF (Maks. 5MB)</small>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>