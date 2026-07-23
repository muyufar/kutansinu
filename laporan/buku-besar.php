<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-01-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-12-31');
$id_akun = isset($_GET['id_akun']) ? (int)$_GET['id_akun'] : 0;

$filter_perusahaan = getReportFilterPerusahaan(
    $db,
    $user_id,
    isset($_GET['perusahaan']) ? (int)$_GET['perusahaan'] : null
);

$is_admin = checkUserRole($db, $user_id, $filter_perusahaan, 'admin');
$daftar_perusahaan = [];
if ($is_admin) {
    $stmt = $db->prepare('SELECT p.id, p.nama FROM perusahaan p JOIN user_perusahaan up ON p.id = up.perusahaan_id WHERE up.user_id = ? AND up.role = ?');
    $stmt->execute([$user_id, 'admin']);
    $daftar_perusahaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt_perusahaan = $db->prepare('SELECT nama FROM perusahaan WHERE id = ?');
$stmt_perusahaan->execute([$filter_perusahaan]);
$nama_perusahaan = $stmt_perusahaan->fetchColumn() ?: '-';

$daftar_akun = getDaftarAkun($db, $filter_perusahaan);
$buku_besar = null;

if ($id_akun > 0) {
    $buku_besar = getBukuBesarData($db, $id_akun, $tanggal_awal, $tanggal_akhir, $filter_perusahaan);
    if (!$buku_besar) {
        $_SESSION['error'] = 'Akun tidak ditemukan untuk perusahaan ini.';
        $id_akun = 0;
    }
}

$export_params = http_build_query([
    'id_akun' => $id_akun,
    'tanggal_awal' => $tanggal_awal,
    'tanggal_akhir' => $tanggal_akhir,
    'perusahaan' => $filter_perusahaan,
]);

include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1">Buku Besar</h2>
            <p class="text-muted mb-0">Lacak mutasi debit/kredit per akun beserta saldo berjalan.</p>
        </div>
        <?php if ($buku_besar): ?>
            <div class="d-flex gap-2">
                <a href="export-buku-besar-excel.php?<?= $export_params ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel"></i> Download Excel
                </a>
                <a href="export-buku-besar-pdf.php?<?= $export_params ?>" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?= $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <?php if ($is_admin && !empty($daftar_perusahaan)): ?>
                    <div class="col-md-3">
                        <label for="perusahaan" class="form-label">Perusahaan</label>
                        <select class="form-select" id="perusahaan" name="perusahaan" onchange="this.form.submit()">
                            <?php foreach ($daftar_perusahaan as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filter_perusahaan == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-4">
                    <label for="id_akun" class="form-label">Akun</label>
                    <select class="form-select" id="id_akun" name="id_akun" required>
                        <option value="">Pilih Akun</option>
                        <?php foreach ($daftar_akun as $akun): ?>
                            <option value="<?= $akun['id'] ?>" <?= $id_akun == $akun['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($akun['kode_akun'] . ' - ' . $akun['nama_akun']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= htmlspecialchars($tanggal_awal) ?>" required>
                </div>

                <div class="col-md-2">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= htmlspecialchars($tanggal_akhir) ?>" required>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$id_akun): ?>
        <div class="alert alert-info">
            Pilih akun dan periode tanggal untuk menampilkan buku besar.
        </div>
    <?php elseif ($buku_besar): ?>
        <?php $akun = $buku_besar['akun']; ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Perusahaan:</strong> <?= htmlspecialchars($nama_perusahaan) ?></p>
                        <p class="mb-1"><strong>Akun:</strong> <?= htmlspecialchars($akun['kode_akun'] . ' - ' . $akun['nama_akun']) ?></p>
                        <p class="mb-0"><strong>Kategori:</strong> <?= htmlspecialchars(ucfirst($akun['kategori'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Periode:</strong> <?= date('d/m/Y', strtotime($tanggal_awal)) ?> s/d <?= date('d/m/Y', strtotime($tanggal_akhir)) ?></p>
                        <p class="mb-1"><strong>Saldo Awal:</strong> <?= formatRupiah($buku_besar['saldo_awal']) ?></p>
                        <p class="mb-0"><strong>Saldo Akhir:</strong> <?= formatRupiah($buku_besar['saldo_akhir']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Mutasi Buku Besar</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>No. Ref</th>
                                <th>Keterangan</th>
                                <th>Akun Lawan</th>
                                <th>Jenis</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Kredit</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-light">
                                <td><?= date('d/m/Y', strtotime($tanggal_awal)) ?></td>
                                <td>-</td>
                                <td><em>Saldo Awal</em></td>
                                <td>-</td>
                                <td>-</td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                                <td class="text-end fw-bold"><?= formatRupiah($buku_besar['saldo_awal']) ?></td>
                            </tr>

                            <?php if (empty($buku_besar['rows'])): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Tidak ada mutasi pada periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($buku_besar['rows'] as $row): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                        <td>#<?= (int)$row['transaksi_id'] ?></td>
                                        <td><?= htmlspecialchars($row['keterangan']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($row['lawan_kode'] . ' - ' . $row['lawan_nama']) ?>
                                            <small class="text-muted">(<?= $row['posisi'] === 'D' ? 'Kredit' : 'Debit' ?>)</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars(str_replace('_', ' ', $row['jenis'])) ?></span>
                                        </td>
                                        <td class="text-end"><?= $row['debit'] > 0 ? formatRupiah($row['debit']) : '-' ?></td>
                                        <td class="text-end"><?= $row['kredit'] > 0 ? formatRupiah($row['kredit']) : '-' ?></td>
                                        <td class="text-end fw-bold"><?= formatRupiah($row['saldo']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <tr class="table-primary">
                                <td colspan="5" class="text-end fw-bold">TOTAL MUTASI PERIODE</td>
                                <td class="text-end fw-bold"><?= formatRupiah($buku_besar['total_debit']) ?></td>
                                <td class="text-end fw-bold"><?= formatRupiah($buku_besar['total_kredit']) ?></td>
                                <td class="text-end fw-bold"><?= formatRupiah($buku_besar['saldo_akhir']) ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../templates/footer.php'; ?>
