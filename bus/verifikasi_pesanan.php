<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek apakah user terhubung dengan perusahaan Nugrosir
$user_id = $_SESSION['user_id'];
$stmt_nugrosir = $db->prepare("SELECT 1 FROM user_perusahaan up
                    JOIN perusahaan p ON up.perusahaan_id = p.id
                    WHERE up.user_id = ? AND UPPER(p.nama) = 'NUGROSIR' AND up.status = 'active'");
$stmt_nugrosir->execute([$user_id]);
$is_nugrosir = $stmt_nugrosir->fetch() ? true : false;

// Verifikasi role user (hanya untuk nugrosir)
if (!$is_nugrosir) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya perusahaan NUGROSIR yang dapat mengakses fitur pemesanan bus.';
    header('Location: /kutansinu/index.php');
    exit();
}

// Proses verifikasi pesanan jika ada
if (isset($_POST['verifikasi'])) {
    $pemesanan_id = (int)$_POST['pemesanan_id'];
    $status = validateInput($_POST['status']);

    try {
        $stmt = $db->prepare("UPDATE pemesanan_bus SET status = ?, tanggal_verifikasi = NOW() WHERE id = ?");
        $stmt->execute([$status, $pemesanan_id]);

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

                                    <?php if ($pemesanan['status'] == 'menunggu_verifikasi'): ?>
                                        <form action="" method="post">
                                            <input type="hidden" name="pemesanan_id" value="<?php echo $pemesanan['id']; ?>">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Ubah Status</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="dikonfirmasi">Konfirmasi Pemesanan</option>
                                                    <option value="ditolak">Tolak Pemesanan</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="catatan_admin" class="form-label">Catatan Admin</label>
                                                <textarea class="form-control" id="catatan_admin" name="catatan_admin" rows="3"></textarea>
                                            </div>
                                            <div class="text-end">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                <button type="submit" name="verifikasi" class="btn btn-primary">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    <?php elseif ($pemesanan['status'] == 'dikonfirmasi'): ?>
                                        <form action="" method="post">
                                            <input type="hidden" name="pemesanan_id" value="<?php echo $pemesanan['id']; ?>">
                                            <div class="mb-3">
                                                <label for="status" class="form-label">Ubah Status</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="selesai">Tandai Selesai</option>
                                                    <option value="dibatalkan">Batalkan Pemesanan</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="catatan_admin" class="form-label">Catatan Admin</label>
                                                <textarea class="form-control" id="catatan_admin" name="catatan_admin" rows="3"><?php echo htmlspecialchars($pemesanan['catatan_admin']); ?></textarea>
                                            </div>
                                            <div class="text-end">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                <button type="submit" name="verifikasi" class="btn btn-primary">Simpan Perubahan</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <div class="text-end">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
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