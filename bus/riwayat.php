<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/pemesanan_helper.php';

// Cek login
requireLogin();

$user_id = $_SESSION['user_id'];
$is_staff = isBusStaff($db, $user_id);
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Ambil riwayat pemesanan bus
$stmt = $db->prepare("
    SELECT pb.*, b.nama_bus, b.nomor_polisi, b.foto 
    FROM pemesanan_bus pb
    JOIN bus b ON pb.id_bus = b.id
    WHERE pb.id_user = ?
    ORDER BY pb.tanggal_pemesanan DESC, pb.id DESC
");
$stmt->execute([$user_id]);
$pemesanan_list = $stmt->fetchAll();

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Riwayat Pemesanan Bus</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Bus
        </a>
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

    <?php if (empty($pemesanan_list)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Anda belum memiliki riwayat pemesanan bus.
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($pemesanan_list as $pemesanan): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Pemesanan #<?php echo $pemesanan['id']; ?></h5>
                            <span class="badge <?php echo getPemesananStatusBadgeClass($pemesanan['status']); ?>">
                                <?php echo htmlspecialchars(formatPemesananStatus($pemesanan['status'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <?php if (!empty($pemesanan['foto'])): ?>
                                        <img src="../uploads/bus/<?php echo htmlspecialchars($pemesanan['foto']); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($pemesanan['nama_bus']); ?>">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded" style="height: 120px;">
                                            <i class="fas fa-bus fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <h5><?php echo htmlspecialchars($pemesanan['nama_bus']); ?></h5>
                                    <p class="mb-1">
                                        <strong>Nama Pemesan:</strong> <?php echo htmlspecialchars($pemesanan['nama_pemesan']); ?>
                                    </p>
                                    <p class="mb-1"></p>
                                    <strong>Kontak Pemesan:</strong> <?php echo htmlspecialchars($pemesanan['kontak_pemesan']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Nomor Polisi:</strong> <?php echo htmlspecialchars($pemesanan['nomor_polisi']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Tanggal Pemesanan:</strong> <?php echo date('d/m/Y', strtotime($pemesanan['tanggal_pemesanan'])); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Keberangkatan:</strong> <?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?>, <?php echo date('H:i', strtotime($pemesanan['waktu_berangkat'])); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Rute:</strong> <?php echo htmlspecialchars($pemesanan['kota_asal']); ?> - <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Jumlah Penumpang:</strong> <?php echo $pemesanan['jumlah_penumpang']; ?> orang
                                    </p>
                                    <p class="mb-1">
                                        <strong>Total Harga:</strong> <?php echo formatRupiah($pemesanan['total_harga']); ?>
                                    </p>
                                    <div class="mt-3">
                                        <strong>Status:</strong>
                                        <span class="badge <?= getPemesananStatusBadgeClass($pemesanan['status']) ?>"><?= htmlspecialchars(formatPemesananStatus($pemesanan['status'])) ?></span>
                                    </div>

                                    <?php
                                    // Ambil semua bukti pembayaran
                                    $stmt_bukti = $db->prepare("SELECT * FROM bukti_pembayaran_bus WHERE pemesanan_id = ? ORDER BY tanggal_upload ASC");
                                    $stmt_bukti->execute([$pemesanan['id']]);
                                    $bukti_pembayaran_list = $stmt_bukti->fetchAll();

                                    if (!empty($bukti_pembayaran_list)): ?>
                                        <div class="mt-3">
                                            <strong>Bukti Pembayaran:</strong>
                                            <div class="d-flex flex-column gap-2 mt-2">
                                                <?php foreach ($bukti_pembayaran_list as $bukti): ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <a href="../uploads/pembayaran_bus/<?php echo htmlspecialchars($bukti['nama_file']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                            <i class="fas fa-image"></i>
                                                            <?php echo $bukti['jenis_pembayaran'] == 'dp' ? 'Bukti DP' : 'Bukti Lunas'; ?>
                                                            (<?php echo date('d/m/Y H:i', strtotime($bukti['tanggal_upload'])); ?>)
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($pemesanan['catatan'])): ?>
                                <div class="mt-3">
                                    <strong>Catatan:</strong>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($pemesanan['catatan'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($pemesanan['status'] == 'menunggu_pembayaran'): ?>
                                <div class="mt-3">
                                    <a href="upload_bukti.php?id=<?php echo $pemesanan['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                                    </a>
                                </div>
                            <?php elseif ($pemesanan['status'] == 'dibayar'): ?>
                                <div class="mt-3">
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> Pembayaran Anda sedang diverifikasi oleh admin.
                                    </div>
                                    <a href="verifikasi_pesanan.php?id=<?php echo $pemesanan['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-check-circle"></i> Verifikasi Pesanan
                                    </a>
                                </div>
                            <?php elseif ($pemesanan['status'] == 'selesai'): ?>
                                <div class="mt-3">
                                    <a href="cetak_tiket.php?id=<?php echo $pemesanan['id']; ?>" class="btn btn-success" target="_blank">
                                        <i class="fas fa-print"></i> Cetak Tiket
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if (pemesananCanEdit($pemesanan['status'])): ?>
                                <div class="mt-3 d-flex flex-wrap gap-2">
                                    <a href="edit_pesanan.php?id=<?= (int) $pemesanan['id'] ?>&redirect=riwayat.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit Jadwal
                                    </a>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#batalModal<?= (int) $pemesanan['id'] ?>">
                                        <i class="fas fa-times-circle"></i> Batalkan
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light">
                            <small class="text-muted">Pemesanan dibuat pada <?php echo date('d/m/Y H:i', strtotime($pemesanan['tanggal_pemesanan'])); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($pemesanan_list as $pemesanan): ?>
            <?php if (pemesananCanCancel($pemesanan['status'])): ?>
                <div class="modal fade" id="batalModal<?= (int) $pemesanan['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="aksi_pesanan.php">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="pemesanan_id" value="<?= (int) $pemesanan['id'] ?>">
                                <input type="hidden" name="redirect" value="riwayat.php">
                                <div class="modal-header">
                                    <h5 class="modal-title">Batalkan Pemesanan #<?= (int) $pemesanan['id'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Yakin ingin membatalkan pemesanan ini? Jadwal bus akan kembali tersedia.</p>
                                    <label for="catatan_batal<?= (int) $pemesanan['id'] ?>" class="form-label">Alasan pembatalan (opsional)</label>
                                    <textarea class="form-control" id="catatan_batal<?= (int) $pemesanan['id'] ?>" name="catatan_batal" rows="3"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    <button type="submit" class="btn btn-danger">Ya, Batalkan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../templates/footer.php'; ?>