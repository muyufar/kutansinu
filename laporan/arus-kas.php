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

// Query untuk mendapatkan saldo awal kas
$sql_saldo_awal = "SELECT COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE -jumlah END), 0) as saldo
FROM transaksi
WHERE tanggal < ?";

$stmt_saldo_awal = $db->prepare($sql_saldo_awal);
$stmt_saldo_awal->execute([$tanggal_awal]);
$saldo_awal = $stmt_saldo_awal->fetch()['saldo'];

// Query untuk aktivitas operasi
$sql_operasi = "SELECT 
    a.kode_akun,
    a.nama_akun,
    COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
FROM akun a
LEFT JOIN transaksi t ON a.id = t.id_akun_debit OR a.id = t.id_akun_kredit
    AND t.tanggal BETWEEN ? AND ?
WHERE a.kategori IN ('pendapatan', 'beban')
GROUP BY a.id, a.kode_akun, a.nama_akun
HAVING jumlah != 0
ORDER BY a.kode_akun ASC";

$stmt_operasi = $db->prepare($sql_operasi);
$stmt_operasi->execute([$tanggal_awal, $tanggal_akhir]);
$data_operasi = $stmt_operasi->fetchAll();

// Query untuk aktivitas investasi
$sql_investasi = "SELECT 
    a.kode_akun,
    a.nama_akun,
    COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
FROM akun a
LEFT JOIN transaksi t ON a.id = t.id_akun_debit OR a.id = t.id_akun_kredit
    AND t.tanggal BETWEEN ? AND ?
WHERE a.kategori = 'investasi'
GROUP BY a.id, a.kode_akun, a.nama_akun
HAVING jumlah != 0
ORDER BY a.kode_akun ASC";

$stmt_investasi = $db->prepare($sql_investasi);
$stmt_investasi->execute([$tanggal_awal, $tanggal_akhir]);
$data_investasi = $stmt_investasi->fetchAll();

// Query untuk aktivitas pendanaan
$sql_pendanaan = "SELECT 
    a.kode_akun,
    a.nama_akun,
    COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
FROM akun a
LEFT JOIN transaksi t ON a.id = t.id_akun_debit OR a.id = t.id_akun_kredit
    AND t.tanggal BETWEEN ? AND ?
WHERE a.kategori = 'modal'
GROUP BY a.id, a.kode_akun, a.nama_akun
HAVING jumlah != 0
ORDER BY a.kode_akun ASC";

$stmt_pendanaan = $db->prepare($sql_pendanaan);
$stmt_pendanaan->execute([$tanggal_awal, $tanggal_akhir]);
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