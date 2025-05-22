<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: /kutansinu/login.php');
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

// Query untuk mendapatkan saldo awal kas dengan filter perusahaan
$sql_saldo_awal = "SELECT COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE -jumlah END), 0) as saldo
FROM transaksi
WHERE tanggal < ? AND id_perusahaan = ?";

$stmt_saldo_awal = $db->prepare($sql_saldo_awal);
$stmt_saldo_awal->execute([$tanggal_awal, $filter_perusahaan]);
$saldo_awal = $stmt_saldo_awal->fetch()['saldo'];

// Query untuk aktivitas operasi dengan filter perusahaan
$sql_operasi = "SELECT 
    a.kode_akun,
    a.nama_akun,
    COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
FROM akun a
LEFT JOIN transaksi t ON a.id = t.id_akun_debit OR a.id = t.id_akun_kredit
    AND t.tanggal BETWEEN ? AND ?
    AND t.id_perusahaan = ?
WHERE a.kategori IN ('pendapatan', 'beban')
  AND a.id_perusahaan = ?
GROUP BY a.id, a.kode_akun, a.nama_akun
HAVING jumlah != 0
ORDER BY a.kode_akun ASC";

$stmt_operasi = $db->prepare($sql_operasi);
$stmt_operasi->execute([$tanggal_awal, $tanggal_akhir, $filter_perusahaan, $filter_perusahaan]);
$data_operasi = $stmt_operasi->fetchAll();

// Query untuk aktivitas investasi dengan filter perusahaan
$sql_investasi = "SELECT 
    a.kode_akun,
    a.nama_akun,
    COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
FROM akun a
LEFT JOIN transaksi t ON a.id = t.id_akun_debit OR a.id = t.id_akun_kredit
    AND t.tanggal BETWEEN ? AND ?
    AND t.id_perusahaan = ?
WHERE a.kategori = 'investasi'
  AND a.id_perusahaan = ?
GROUP BY a.id, a.kode_akun, a.nama_akun
HAVING jumlah != 0
ORDER BY a.kode_akun ASC";

$stmt_investasi = $db->prepare($sql_investasi);
$stmt_investasi->execute([$tanggal_awal, $tanggal_akhir, $filter_perusahaan, $filter_perusahaan]);
$data_investasi = $stmt_investasi->fetchAll();

// Query untuk aktivitas pendanaan dengan filter perusahaan
$sql_pendanaan = "SELECT 
    a.kode_akun,
    a.nama_akun,
    COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
FROM akun a
LEFT JOIN transaksi t ON a.id = t.id_akun_debit OR a.id = t.id_akun_kredit
    AND t.tanggal BETWEEN ? AND ?
    AND t.id_perusahaan = ?
WHERE a.kategori = 'modal'
  AND a.id_perusahaan = ?
GROUP BY a.id, a.kode_akun, a.nama_akun
HAVING jumlah != 0
ORDER BY a.kode_akun ASC";

$stmt_pendanaan = $db->prepare($sql_pendanaan);
$stmt_pendanaan->execute([$tanggal_awal, $tanggal_akhir, $filter_perusahaan, $filter_perusahaan]);
$data_pendanaan = $stmt_pendanaan->fetchAll();

// Hitung total untuk setiap aktivitas
$total_operasi = 0;
foreach ($data_operasi as $operasi) {
    $total_operasi += $operasi['jumlah'];
}

$total_investasi = 0;
foreach ($data_investasi as $investasi) {
    $total_investasi += $investasi['jumlah'];
}

$total_pendanaan = 0;
foreach ($data_pendanaan as $pendanaan) {
    $total_pendanaan += $pendanaan['jumlah'];
}

// Hitung perubahan kas bersih
$perubahan_kas = $total_operasi + $total_investasi + $total_pendanaan;

// Hitung saldo akhir
$saldo_akhir = $saldo_awal + $perubahan_kas;

// Include header
require_once '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2>Laporan Arus Kas</h2>
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

    <!-- Laporan Arus Kas -->
    <div class="card">
        <div class="card-body">
            <!-- Saldo Awal -->
            <div class="mb-4">
                <h5>Saldo Awal Kas</h5>
                <div class="alert alert-info">
                    <strong><?= number_format($saldo_awal, 2, ',', '.') ?></strong>
                </div>
            </div>

            <!-- Aktivitas Operasi -->
            <h5 class="mb-3">Arus Kas dari Aktivitas Operasi</h5>
            <table class="table table-striped mb-4">
                <thead>
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_operasi as $operasi): ?>
                        <tr>
                            <td><?= htmlspecialchars($operasi['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($operasi['nama_akun']) ?></td>
                            <td class="text-end"><?= number_format($operasi['jumlah'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="2">Total Arus Kas dari Aktivitas Operasi</td>
                        <td class="text-end"><?= number_format($total_operasi, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Aktivitas Investasi -->
            <h5 class="mb-3">Arus Kas dari Aktivitas Investasi</h5>
            <table class="table table-striped mb-4">
                <thead>
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_investasi as $investasi): ?>
                        <tr>
                            <td><?= htmlspecialchars($investasi['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($investasi['nama_akun']) ?></td>
                            <td class="text-end"><?= number_format($investasi['jumlah'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="2">Total Arus Kas dari Aktivitas Investasi</td>
                        <td class="text-end"><?= number_format($total_investasi, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Aktivitas Pendanaan -->
            <h5 class="mb-3">Arus Kas dari Aktivitas Pendanaan</h5>
            <table class="table table-striped mb-4">
                <thead>
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data_pendanaan as $pendanaan): ?>
                        <tr>
                            <td><?= htmlspecialchars($pendanaan['kode_akun']) ?></td>
                            <td><?= htmlspecialchars($pendanaan['nama_akun']) ?></td>
                            <td class="text-end"><?= number_format($pendanaan['jumlah'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="2">Total Arus Kas dari Aktivitas Pendanaan</td>
                        <td class="text-end"><?= number_format($total_pendanaan, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- Perubahan Kas Bersih dan Saldo Akhir -->
            <div class="card bg-light mb-3">
                <div class="card-body">
                    <h5 class="card-title">Perubahan Kas Bersih</h5>
                    <h3 class="<?= $perubahan_kas >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= number_format(abs($perubahan_kas), 2, ',', '.') ?>
                        <small>(<?= $perubahan_kas >= 0 ? 'KENAIKAN' : 'PENURUNAN' ?>)</small>
                    </h3>
                </div>
            </div>

            <div class="card bg-light">
                <div class="card-body">
                    <h5 class="card-title">Saldo Akhir Kas</h5>
                    <h3><?= number_format($saldo_akhir, 2, ',', '.') ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../templates/footer.php'; ?>