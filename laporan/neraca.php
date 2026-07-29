<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-01-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-t');

$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

if (!$id_perusahaan) {
    $_SESSION['error'] = 'Anda belum memiliki perusahaan default. Silakan tambahkan perusahaan terlebih dahulu.';
    header('Location: ../pengaturan/perusahaan.php');
    exit();
}

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

$daftar_perusahaan = [];
if ($is_admin) {
    $stmt = $db->prepare("SELECT p.id, p.nama FROM perusahaan p JOIN user_perusahaan up ON p.id = up.perusahaan_id WHERE up.user_id = ? AND up.role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $daftar_perusahaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$filter_perusahaan = $id_perusahaan;
if ($is_admin && isset($_GET['perusahaan']) && $_GET['perusahaan']) {
    $filter_perusahaan = $_GET['perusahaan'];
}

$neraca = getNeracaData($db, $tanggal_akhir, $filter_perusahaan, $tanggal_awal);

include '../templates/header.php';

function renderNeracaGroup($title, $groups, $total_label, $total_value)
{
    ?>
    <div class="neraca-section mb-4">
        <h5 class="neraca-section-title"><?= htmlspecialchars($title) ?></h5>
        <?php if (empty($groups)): ?>
            <p class="text-muted small mb-2">Tidak ada saldo.</p>
        <?php else: ?>
            <?php foreach ($groups as $sub_kategori => $accounts): ?>
                <div class="neraca-subgroup mb-3">
                    <div class="neraca-subgroup-title"><?= htmlspecialchars($sub_kategori) ?></div>
                    <table class="table table-sm table-borderless neraca-table mb-0">
                        <tbody>
                            <?php foreach ($accounts as $account): ?>
                                <tr>
                                    <td class="neraca-kode"><?= htmlspecialchars($account['kode_akun']) ?></td>
                                    <td><?= htmlspecialchars($account['nama_akun']) ?></td>
                                    <td class="text-end"><?= formatRupiah($account['saldo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="neraca-total-row d-flex justify-content-between fw-bold">
            <span><?= htmlspecialchars($total_label) ?></span>
            <span><?= formatRupiah($total_value) ?></span>
        </div>
    </div>
    <?php
}
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Laporan Neraca</h2>
            <p class="text-muted mb-0">
                Per tanggal <?= date('d/m/Y', strtotime($tanggal_akhir)) ?>
                &middot; Laba periode <?= date('d/m/Y', strtotime($tanggal_awal)) ?> – <?= date('d/m/Y', strtotime($tanggal_akhir)) ?>
            </p>
        </div>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>

    <div class="alert alert-info py-2">
        <small>
            Neraca menampilkan posisi <strong>Aktiva</strong> dan <strong>Pasiva</strong> (Kewajiban &amp; Ekuitas).
            Akun <strong>Pendapatan</strong> dan <strong>Beban</strong> hanya tampil di
            <a href="laba-rugi.php">Laporan Laba Rugi</a>.
        </small>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="tanggal_awal" class="form-label">Awal Periode Laba</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal"
                        value="<?= htmlspecialchars($tanggal_awal) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="tanggal_akhir" class="form-label">Per Tanggal</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir"
                        value="<?= htmlspecialchars($tanggal_akhir) ?>" required>
                </div>
                <?php if ($is_admin): ?>
                    <div class="col-md-3">
                        <label for="perusahaan" class="form-label">Perusahaan</label>
                        <select class="form-select" id="perusahaan" name="perusahaan">
                            <option value="">Perusahaan Default</option>
                            <?php foreach ($daftar_perusahaan as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($_GET['perusahaan']) && $_GET['perusahaan'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Tampilkan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100 neraca-card">
                <div class="card-header bg-primary text-white fw-bold">AKTIVA</div>
                <div class="card-body">
                    <?php renderNeracaGroup('Aktiva', $neraca['aktiva_grouped'], 'Total Aktiva', $neraca['total_aktiva']); ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100 neraca-card">
                <div class="card-header bg-success text-white fw-bold">PASIVA</div>
                <div class="card-body">
                    <?php renderNeracaGroup('Kewajiban', $neraca['kewajiban_grouped'], 'Total Kewajiban', $neraca['total_kewajiban']); ?>

                    <div class="neraca-section mb-4">
                        <h5 class="neraca-section-title">Ekuitas</h5>
                        <?php if (!empty($neraca['ekuitas_grouped'])): ?>
                            <?php foreach ($neraca['ekuitas_grouped'] as $sub_kategori => $accounts): ?>
                                <div class="neraca-subgroup mb-3">
                                    <div class="neraca-subgroup-title"><?= htmlspecialchars($sub_kategori) ?></div>
                                    <table class="table table-sm table-borderless neraca-table mb-0">
                                        <tbody>
                                            <?php foreach ($accounts as $account): ?>
                                                <tr>
                                                    <td class="neraca-kode"><?= htmlspecialchars($account['kode_akun']) ?></td>
                                                    <td><?= htmlspecialchars($account['nama_akun']) ?></td>
                                                    <td class="text-end"><?= formatRupiah($account['saldo']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if ($neraca['laba_bersih'] != 0): ?>
                            <table class="table table-sm table-borderless neraca-table mb-3">
                                <tbody>
                                    <tr>
                                        <td class="neraca-kode">—</td>
                                        <td>
                                            Laba/Rugi Periode Berjalan
                                            <span class="text-muted small">(<?= $neraca['laba_bersih'] >= 0 ? 'Laba' : 'Rugi' ?>)</span>
                                        </td>
                                        <td class="text-end <?= $neraca['laba_bersih'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= formatRupiah($neraca['laba_bersih']) ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <div class="neraca-total-row d-flex justify-content-between fw-bold">
                            <span>Total Ekuitas</span>
                            <span><?= formatRupiah($neraca['total_ekuitas']) ?></span>
                        </div>
                    </div>

                    <div class="neraca-total-row d-flex justify-content-between fw-bold fs-5 border-top pt-3">
                        <span>Total Pasiva</span>
                        <span><?= formatRupiah($neraca['total_pasiva']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$neraca['seimbang']): ?>
        <div class="alert alert-warning mt-4">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Neraca belum seimbang. Selisih:
            <strong><?= formatRupiah(abs($neraca['total_aktiva'] - $neraca['total_pasiva'])) ?></strong>.
            Periksa pemetaan akun dan transaksi pendapatan/beban di
            <a href="laba-rugi.php">Laba Rugi</a>.
        </div>
    <?php else: ?>
        <div class="alert alert-success mt-4 mb-0">
            <i class="fas fa-check-circle me-1"></i> Neraca seimbang — Total Aktiva = Total Pasiva.
        </div>
    <?php endif; ?>
</div>

<style>
    .neraca-section-title {
        font-size: 0.95rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--bs-secondary);
        margin-bottom: 0.75rem;
    }

    .neraca-subgroup-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--bs-body-color);
        margin-bottom: 0.35rem;
        padding-left: 0.25rem;
    }

    .neraca-table td {
        padding: 0.2rem 0.35rem;
        vertical-align: top;
    }

    .neraca-kode {
        width: 5.5rem;
        color: var(--bs-secondary);
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .neraca-total-row {
        border-top: 2px solid var(--bs-border-color);
        padding-top: 0.65rem;
        margin-top: 0.5rem;
    }

    .neraca-card .card-header {
        letter-spacing: 0.04em;
    }

    @media print {
        .navbar,
        .btn,
        form,
        .alert-info {
            display: none !important;
        }

        .card {
            border: 1px solid #dee2e6 !important;
            break-inside: avoid;
        }

        .col-lg-6 {
            width: 50%;
            float: left;
        }
    }
</style>

<?php include '../templates/footer.php'; ?>
