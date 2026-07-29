<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Set default tanggal jika tidak ada filter
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-t');

// Ambil id_perusahaan dari default_company pengguna
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

// Pastikan pengguna memiliki perusahaan default
if (!$id_perusahaan) {
    $_SESSION['error'] = 'Anda belum memiliki perusahaan default. Silakan tambahkan perusahaan terlebih dahulu.';
    header('Location: ../pengaturan/perusahaan.php');
    exit();
}

// Cek role admin
$is_admin = false;
if (isset($_SESSION['user_id']) && isset($db)) {
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();
    $default_company_id = $user_data['default_company'];
    if (checkUserRole($db, $user_id, $default_company_id, 'admin')) {
        $is_admin = true;
    }
}

// Ambil daftar perusahaan jika admin
$daftar_perusahaan = [];
if ($is_admin) {
    $stmt = $db->prepare("SELECT p.id, p.nama FROM perusahaan p JOIN user_perusahaan up ON p.id = up.perusahaan_id WHERE up.user_id = ? AND up.role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $daftar_perusahaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ambil filter perusahaan dari GET jika admin, jika tidak pakai default
$filter_perusahaan = $id_perusahaan;
if ($is_admin && isset($_GET['perusahaan']) && $_GET['perusahaan']) {
    $filter_perusahaan = $_GET['perusahaan'];
}

$laba_rugi_data = getLabaRugiPeriode($db, $tanggal_awal, $tanggal_akhir, $filter_perusahaan);
$data_pendapatan = $laba_rugi_data['pendapatan'];
$data_beban = $laba_rugi_data['beban'];
$total_pendapatan = $laba_rugi_data['total_pendapatan'];
$total_beban = $laba_rugi_data['total_beban'];
$laba_rugi = $laba_rugi_data['laba_bersih'];

// Include header
require_once '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Laporan Laba Rugi</h2>
            <p class="text-muted">Periode: <?= date('d/m/Y', strtotime($tanggal_awal)) ?> - <?= date('d/m/Y', strtotime($tanggal_akhir)) ?></p>
        </div>
    </div>

    <!-- Form Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                </div>
                <div class="col-md-4">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                </div>
                <?php if ($is_admin): ?>
                    <div class="col-md-4">
                        <label for="perusahaan" class="form-label">Perusahaan</label>
                        <select class="form-select" id="perusahaan" name="perusahaan">
                            <option value="">Semua Perusahaan</option>
                            <?php foreach ($daftar_perusahaan as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($_GET['perusahaan']) && $_GET['perusahaan'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Laporan Laba Rugi -->
    <div class="card">
        <div class="card-body">
            <!-- Pendapatan -->
            <h5 class="mb-3">Pendapatan</h5>
            <table class="table table-striped mb-4">
                <thead>
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_pendapatan as $pendapatan): ?>
                        <tr>
                            <td><?= htmlspecialchars($pendapatan['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($pendapatan['nama_akun']) ?></td>
                            <td class="text-end"><?= number_format($pendapatan['jumlah'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="2">Total Pendapatan</td>
                        <td class="text-end"><?= number_format($total_pendapatan, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Beban -->
            <h5 class="mb-3">Beban</h5>
            <table class="table table-striped mb-4">
                <thead>
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_beban as $beban): ?>
                        <tr>
                            <td><?= htmlspecialchars($beban['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($beban['nama_akun']) ?></td>
                            <td class="text-end"><?= number_format($beban['jumlah'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="2">Total Beban</td>
                        <td class="text-end"><?= number_format($total_beban, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Laba/Rugi Bersih -->
            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title">Laba/Rugi Bersih</h5>
                    <h3 class="<?= $laba_rugi >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format(abs($laba_rugi), 2, ',', '.') ?>
                        <small>(<?= $laba_rugi >= 0 ? 'LABA' : 'RUGI' ?>)</small>
                    </h3>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>