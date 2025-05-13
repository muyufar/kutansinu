<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek apakah user terhubung dengan perusahaan Nugrosir
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Proses verifikasi pesanan jika ada
if (isset($_POST['verifikasi'])) {
    $pemesanan_id = (int)$_POST['pemesanan_id'];
    $status = validateInput($_POST['status']);
    $catatan_admin = isset($_POST['catatan_admin']) ? validateInput($_POST['catatan_admin']) : '';
    $bukti_transfer = '';
    
    // Proses upload bukti transfer jika ada
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['bukti_transfer']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), $allowed)) {
            $new_filename = time() . '_' . $filename;
            $upload_dir = '../uploads/pembayaran_bus/';
            
            // Pastikan direktori ada
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $destination)) {
                $bukti_transfer = $new_filename;
            } else {
                $_SESSION['error'] = 'Gagal mengupload bukti transfer';
                header('Location: verifikasi_pesanan.php');
                exit();
            }
        } else {
            $_SESSION['error'] = 'Format file tidak diizinkan. Gunakan JPG, JPEG, PNG, atau PDF';
            header('Location: verifikasi_pesanan.php');
            exit();
        }
    }
    
    try {
        // Update query berdasarkan ada tidaknya bukti transfer
        if (!empty($bukti_transfer)) {
            $stmt = $db->prepare("UPDATE pemesanan_bus SET status = ?, catatan_admin = ?, bukti_transfer_admin = ?, tanggal_verifikasi = NOW() WHERE id = ?");
            $stmt->execute([$status, $catatan_admin, $bukti_transfer, $pemesanan_id]);
        } else {
            $stmt = $db->prepare("UPDATE pemesanan_bus SET status = ?, catatan_admin = ?, tanggal_verifikasi = NOW() WHERE id = ?");
            $stmt->execute([$status, $catatan_admin, $pemesanan_id]);
        }

        $_SESSION['success'] = 'Status pemesanan berhasil diperbarui';
        header('Location: verifikasi_pesanan.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui status: ' . $e->getMessage();
    }
}

// Filter status
$status_filter = isset($_GET['status']) ? validateInput($_GET['status']) : '';
$where_clause = '';
$params = [];

if ($status_filter) {
    $where_clause = " WHERE pb.status = ?";
    $params[] = $status_filter;
}

// Ambil daftar pemesanan
$query = "SELECT pb.*, b.nama_bus, b.nomor_polisi, u.username as nama_pemesan 
          FROM pemesanan_bus pb
          JOIN bus b ON pb.id_bus = b.id
          JOIN users u ON pb.id_user = u.id
          $where_clause
          ORDER BY pb.tanggal_pemesanan DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pemesanan_list = $stmt->fetchAll();

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Verifikasi Pemesanan Bus</h2>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Filter Pemesanan</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Status Pemesanan</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="menunggu_pembayaran" <?php echo $status_filter == 'menunggu_pembayaran' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                        <option value="menunggu_verifikasi" <?php echo $status_filter == 'menunggu_verifikasi' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                        <option value="dikonfirmasi" <?php echo $status_filter == 'dikonfirmasi' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                        <option value="ditolak" <?php echo $status_filter == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                        <option value="selesai" <?php echo $status_filter == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="dibatalkan" <?php echo $status_filter == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Daftar Pemesanan</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pemesanan_list)): ?>
                <div class="alert alert-info">
                    Tidak ada data pemesanan yang ditemukan.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal Pemesanan</th>
                                <th>Pemesan</th>
                                <th>Bus</th>
                                <th>Rute</th>
                                <th>Tanggal Berangkat</th>
                                <th>Total Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pemesanan_list as $pemesanan): ?>
                                <tr>
                                    <td><?php echo $pemesanan['id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_pemesanan'])); ?></td>
                                    <td><?php echo htmlspecialchars($pemesanan['nama_pemesan']); ?></td>
                                    <td><?php echo htmlspecialchars($pemesanan['nama_bus']); ?> (<?php echo htmlspecialchars($pemesanan['nomor_polisi']); ?>)</td>
                                    <td><?php echo htmlspecialchars($pemesanan['kota_asal']); ?> - <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?></td>
                                    <td><?php echo formatRupiah($pemesanan['total_harga']); ?></td>
                                    <td>
                                        <span class="badge <?php
                                                            switch ($pemesanan['status']) {
                                                                case 'menunggu_pembayaran':
                                                                    echo 'bg-warning';
                                                                    break;
                                                                case 'menunggu_verifikasi':
                                                                    echo 'bg-info';
                                                                    break;
                                                                case 'dikonfirmasi':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'ditolak':
                                                                    echo 'bg-danger';
                                                                    break;
                                                                case 'selesai':
                                                                    echo 'bg-primary';
                                                                    break;
                                                                case 'dibatalkan':
                                                                    echo 'bg-secondary';
                                                                    break;
                                                                default:
                                                                    echo 'bg-light text-dark';
                                                            }
                                                            ?>">
                                            <?php
                                            switch ($pemesanan['status']) {
                                                case 'menunggu_pembayaran':
                                                    echo 'Menunggu Pembayaran';
                                                    break;
                                                case 'menunggu_verifikasi':
                                                    echo 'Menunggu Verifikasi';
                                                    break;
                                                case 'dikonfirmasi':
                                                    echo 'Dikonfirmasi';
                                                    break;
                                                case 'ditolak':
                                                    echo 'Ditolak';
                                                    break;
                                                case 'selesai':
                                                    echo 'Selesai';
                                                    break;
                                                case 'dibatalkan':
                                                    echo 'Dibatalkan';
                                                    break;
                                                default:
                                                    echo $pemesanan['status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $pemesanan['id']; ?>">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Modal Detail dan Verifikasi -->
                <?php foreach ($pemesanan_list as $pemesanan): ?>
                    <div class="modal fade" id="detailModal<?php echo $pemesanan['id']; ?>" tabindex="-1" aria-labelledby="detailModalLabel<?php echo $pemesanan['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title" id="detailModalLabel<?php echo $pemesanan['id']; ?>">Detail Pemesanan #<?php echo $pemesanan['id']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <h5>Informasi Pemesanan</h5>
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th>ID Pemesanan</th>
                                                    <td><?php echo $pemesanan['id']; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Tanggal Pemesanan</th>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($pemesanan['tanggal_pemesanan'])); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Status</th>
                                                    <td>
                                                        <?php
                                                        switch ($pemesanan['status']) {
                                                            case 'menunggu_pembayaran':
                                                                echo 'Menunggu Pembayaran';
                                                                break;
                                                            case 'menunggu_verifikasi':
                                                                echo 'Menunggu Verifikasi';
                                                                break;
                                                            case 'dikonfirmasi':
                                                                echo 'Dikonfirmasi';
                                                                break;
                                                            case 'ditolak':
                                                                echo 'Ditolak';
                                                                break;
                                                            case 'selesai':
                                                                echo 'Selesai';
                                                                break;
                                                            case 'dibatalkan':
                                                                echo 'Dibatalkan';
                                                                break;
                                                            default:
                                                                echo $pemesanan['status'];
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Total Harga</th>
                                                    <td><?php echo formatRupiah($pemesanan['total_harga']); ?></td>
                                                </tr>
                                                <?php if (!empty($pemesanan['bukti_pembayaran'])): ?>
                                                    <tr>
                                                        <th>Bukti Pembayaran</th>
                                                        <td>
                                                            <a href="../uploads/bukti_pembayaran/<?php echo htmlspecialchars($pemesanan['bukti_pembayaran']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                                <i class="fas fa-image"></i> Lihat Bukti
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($pemesanan['bukti_transfer_admin'])): ?>
                                                    <tr>
                                                        <th>Bukti Transfer Admin</th>
                                                        <td>
                                                            <a href="../uploads/pembayaran_bus/<?php echo htmlspecialchars($pemesanan['bukti_transfer_admin']); ?>" target="_blank" class="btn btn-sm btn-success">
                                                                <i class="fas fa-file-invoice-dollar"></i> Lihat Bukti Transfer
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($pemesanan['catatan'])): ?>
                                                    <tr>
                                                        <th>Catatan Pemesan</th>
                                                        <td><?php echo nl2br(htmlspecialchars($pemesanan['catatan'])); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($pemesanan['catatan_admin'])): ?>
                                                    <tr>
                                                        <th>Catatan Admin</th>
                                                        <td><?php echo nl2br(htmlspecialchars($pemesanan['catatan_admin'])); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                                <?php if (!empty($pemesanan['catatan_admin'])): ?>
                                                    <tr>
                                                        <th>Catatan Admin</th>
                                                        <td><?php echo nl2br(htmlspecialchars($pemesanan['catatan_admin'])); ?></td>
                                                    </tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Detail Perjalanan</h5>
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th>Bus</th>
                                                    <td><?php echo htmlspecialchars($pemesanan['nama_bus']); ?> (<?php echo htmlspecialchars($pemesanan['nomor_polisi']); ?>)</td>
                                                </tr>
                                                <tr>
                                                    <th>Rute</th>
                                                    <td><?php echo htmlspecialchars($pemesanan['kota_asal']); ?> - <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Tanggal Berangkat</th>
                                                    <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Waktu Berangkat</th>
                                                    <td><?php echo date('H:i', strtotime($pemesanan['waktu_berangkat'])); ?> WIB</td>
                                                </tr>
                                                <tr>
                                                    <th>Jumlah Penumpang</th>
                                                    <td><?php echo $pemesanan['jumlah_penumpang']; ?> orang</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <?php if ($pemesanan['status'] == 'menunggu_pembayaran'): ?>
                                        <form action="" method="post">
                                            <input type="hidden" name="pemesanan_id" value="<?php echo $pemesanan['id']; ?>">
                                            <input type="hidden" name="status" value="dibayar">
                                            <div class="text-end">
                                                <button type="submit" name="verifikasi" class="btn btn-success">
                                                    <i class="fas fa-check"></i> Terima Pemesanan
                                                </button>
                                            </div>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Form Verifikasi Pesanan -->
                                    <?php if ($pemesanan['status'] != 'selesai'): ?>
                                    <div class="card mt-3">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="mb-0">Verifikasi Pesanan</h5>
                                        </div>
                                        <div class="card-body">
                                            <form action="" method="post" enctype="multipart/form-data">
                                                <input type="hidden" name="pemesanan_id" value="<?php echo $pemesanan['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="status<?php echo $pemesanan['id']; ?>" class="form-label">Status Pesanan</label>
                                                    <select class="form-select" id="status<?php echo $pemesanan['id']; ?>" name="status" required>
                                                        <option value="">Pilih Status</option>
                                                        <option value="menunggu_pembayaran" <?php echo $pemesanan['status'] == 'menunggu_pembayaran' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                                                        <option value="menunggu_verifikasi" <?php echo $pemesanan['status'] == 'menunggu_verifikasi' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                                                        <option value="dikonfirmasi" <?php echo $pemesanan['status'] == 'dikonfirmasi' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                                                        <option value="ditolak" <?php echo $pemesanan['status'] == 'ditolak' ? 'selected' : ''; ?>>Ditolak</option>
                                                        <option value="selesai" <?php echo $pemesanan['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                        <option value="dibatalkan" <?php echo $pemesanan['status'] == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="catatan_admin<?php echo $pemesanan['id']; ?>" class="form-label">Catatan Admin</label>
                                                    <textarea class="form-control" id="catatan_admin<?php echo $pemesanan['id']; ?>" name="catatan_admin" rows="3"><?php echo htmlspecialchars($pemesanan['catatan_admin'] ?? ''); ?></textarea>
                                                    <div class="form-text">Tambahkan catatan atau keterangan untuk pesanan ini.</div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="bukti_transfer<?php echo $pemesanan['id']; ?>" class="form-label">Upload Bukti Transfer Pelunasan</label>
                                                    <input type="file" class="form-control" id="bukti_transfer<?php echo $pemesanan['id']; ?>" name="bukti_transfer">
                                                    <div class="form-text">Format yang diizinkan: JPG, JPEG, PNG, PDF. Maksimal 2MB.</div>
                                                </div>
                                                
                                                <div class="d-grid">
                                                    <button type="submit" name="verifikasi" class="btn btn-primary">
                                                        <i class="fas fa-check-circle"></i> Konfirmasi & Simpan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-success mt-3">
                                        <i class="fas fa-check-circle"></i> Pesanan ini telah selesai dan tidak dapat diubah lagi.
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>