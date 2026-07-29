<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/pemesanan_helper.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$is_staff = isBusStaff($db, $user_id);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID pemesanan tidak valid.';
    header('Location: ' . ($is_staff ? 'verifikasi_pesanan.php' : 'riwayat.php'));
    exit();
}

$pemesanan_id = (int) $_GET['id'];
$pemesanan = getPemesananById($db, $pemesanan_id);

if (!$pemesanan) {
    $_SESSION['error'] = 'Pemesanan tidak ditemukan.';
    header('Location: ' . ($is_staff ? 'verifikasi_pesanan.php' : 'riwayat.php'));
    exit();
}

if (!userCanAccessPemesanan($db, $user_id, $pemesanan)) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke pemesanan ini.';
    header('Location: index.php');
    exit();
}

if (!pemesananCanEdit($pemesanan['status'])) {
    $_SESSION['error'] = 'Pemesanan dengan status "' . formatPemesananStatus($pemesanan['status']) . '" tidak dapat diubah.';
    header('Location: ' . ($is_staff ? 'verifikasi_pesanan.php?id=' . $pemesanan_id : 'riwayat.php'));
    exit();
}

$redirect_url = $_POST['redirect'] ?? ($_GET['redirect'] ?? ($is_staff ? 'verifikasi_pesanan.php' : 'riwayat.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = updatePemesananBus($db, $pemesanan_id, $_POST, $user_id, $is_staff);

    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
        header('Location: ' . $redirect_url);
        exit();
    }

    $_SESSION['error'] = $result['message'];
    header('Location: edit_pesanan.php?id=' . $pemesanan_id . '&redirect=' . urlencode($redirect_url));
    exit();
}

$bus_list = getBusListForPemesanan($db, $pemesanan['id_perusahaan'] ?? null, (int) $pemesanan['id_bus']);

include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Edit Pemesanan #<?= (int) $pemesanan['id'] ?></h2>
            <p class="text-muted mb-0">
                Status:
                <span class="badge <?= getPemesananStatusBadgeClass($pemesanan['status']) ?>">
                    <?= htmlspecialchars(formatPemesananStatus($pemesanan['status'])) ?>
                </span>
            </p>
        </div>
        <a href="<?= htmlspecialchars($redirect_url) ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="edit_pesanan.php?id=<?= (int) $pemesanan['id'] ?>">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect_url) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="id_bus" class="form-label">Bus</label>
                        <select class="form-select" id="id_bus" name="id_bus" required>
                            <?php foreach ($bus_list as $bus): ?>
                                <option value="<?= (int) $bus['id'] ?>" <?= (int) $bus['id'] === (int) $pemesanan['id_bus'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bus['nama_bus']) ?> — <?= htmlspecialchars($bus['nomor_polisi']) ?>
                                    (Kapasitas: <?= (int) $bus['kapasitas'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="tanggal_berangkat" class="form-label">Tanggal Berangkat</label>
                        <input type="date" class="form-control" id="tanggal_berangkat" name="tanggal_berangkat"
                            value="<?= htmlspecialchars($pemesanan['tanggal_berangkat']) ?>"
                            <?= $is_staff ? '' : 'min="' . date('Y-m-d') . '"' ?> required>
                    </div>
                    <div class="col-md-3">
                        <label for="waktu_berangkat" class="form-label">Waktu Berangkat</label>
                        <input type="time" class="form-control" id="waktu_berangkat" name="waktu_berangkat"
                            value="<?= htmlspecialchars(substr($pemesanan['waktu_berangkat'], 0, 5)) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="kota_asal" class="form-label">Kota Asal</label>
                        <input type="text" class="form-control" id="kota_asal" name="kota_asal"
                            value="<?= htmlspecialchars($pemesanan['kota_asal']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="kota_tujuan" class="form-label">Kota Tujuan</label>
                        <input type="text" class="form-control" id="kota_tujuan" name="kota_tujuan"
                            value="<?= htmlspecialchars($pemesanan['kota_tujuan']) ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label for="nama_pemesan" class="form-label">Nama Pemesan</label>
                        <input type="text" class="form-control" id="nama_pemesan" name="nama_pemesan"
                            value="<?= htmlspecialchars($pemesanan['nama_pemesan'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="kontak_pemesan" class="form-label">Kontak Pemesan</label>
                        <input type="text" class="form-control" id="kontak_pemesan" name="kontak_pemesan"
                            value="<?= htmlspecialchars($pemesanan['kontak_pemesan'] ?? '') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label for="jumlah_penumpang" class="form-label">Jumlah Penumpang</label>
                        <input type="number" class="form-control" id="jumlah_penumpang" name="jumlah_penumpang"
                            min="1" max="<?= (int) $pemesanan['kapasitas'] ?>"
                            value="<?= (int) $pemesanan['jumlah_penumpang'] ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="total_harga" class="form-label">Total Harga (Rp)</label>
                        <input type="text" class="form-control" id="total_harga" name="total_harga"
                            value="<?= number_format((float) $pemesanan['total_harga'], 0, ',', '.') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="titik_jemput" class="form-label">Titik Jemput</label>
                        <input type="text" class="form-control" id="titik_jemput" name="titik_jemput"
                            value="<?= htmlspecialchars($pemesanan['titik_jemput'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label for="catatan" class="form-label">Catatan</label>
                        <textarea class="form-control" id="catatan" name="catatan" rows="3"><?= htmlspecialchars($pemesanan['catatan'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    <small>
                        Perubahan jadwal akan divalidasi agar tidak bentrok dengan pemesanan bus lain pada tanggal yang sama.
                        <?php if (!$is_staff): ?>
                            Tanggal berangkat tidak boleh sebelum hari ini.
                        <?php endif; ?>
                    </small>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <a href="<?= htmlspecialchars($redirect_url) ?>" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('total_harga').addEventListener('input', function () {
    this.value = this.value.replace(/[^\d]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
});
</script>

<?php include '../templates/footer.php'; ?>
