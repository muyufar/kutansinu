<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_report_helper.php';

requireLogin();
requireBusReportAccess($db, (int) $_SESSION['user_id']);

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$busId = isset($_GET['bus_id']) ? (int) $_GET['bus_id'] : 0;
$idPerusahaan = getNugoCompanyId($db);

if (!$busId || !$idPerusahaan) {
    header('Location: laporan_bulanan.php');
    exit();
}

$report = fetchBusMonthlyReport($db, $idPerusahaan, $bulan);
$bus = null;
foreach ($report['buses'] as $item) {
    if ($item['id'] === $busId) {
        $bus = $item;
        break;
    }
}

if (!$bus) {
    $_SESSION['error'] = 'Data armada tidak ditemukan.';
    header('Location: laporan_bulanan.php?bulan=' . urlencode($bulan));
    exit();
}

$monthLabel = getMonthNameId($bulan);
$yearLabel = date('Y', strtotime($bulan . '-01'));

include '../templates/header.php';
?>

<style>
.report-sheet { background: #fff; border: 1px solid #ddd; padding: 24px; }
.report-table th, .report-table td { border: 1px solid #333; padding: 6px 8px; font-size: 13px; }
.report-table th { background: #f3f3f3; }
@media print { .no-print { display: none !important; } .report-sheet { border: none; padding: 0; } }
</style>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h2>Laporan Armada <?= htmlspecialchars($bus['kode_armada']) ?></h2>
            <p class="text-muted mb-0"><?= htmlspecialchars($bus['nama_bus']) ?> | NO.PLAT <?= htmlspecialchars($bus['nomor_polisi']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="laporan_bulanan.php?bulan=<?= urlencode($bulan) ?>" class="btn btn-outline-secondary">Kembali</a>
            <button onclick="window.print()" class="btn btn-secondary">Cetak</button>
        </div>
    </div>

    <div class="report-sheet mb-4">
        <h5 class="text-center mb-1">LAPORAN BULANAN PT. BARAKA NUGO INTERNATIONAL</h5>
        <p class="text-center mb-3">ARMADA <?= htmlspecialchars($bus['kode_armada']) ?> | BULAN : <?= $monthLabel ?> | NO.PLAT <?= htmlspecialchars($bus['nomor_polisi']) ?></p>

        <div class="table-responsive">
            <table class="table report-table table-sm">
                <thead>
                    <tr>
                        <th>TGL</th>
                        <th>ORDER</th>
                        <th>TUJUAN</th>
                        <th class="text-end">HARGA SEWA</th>
                        <th class="text-end">BBM</th>
                        <th class="text-end">UM</th>
                        <th class="text-end">DRIVER</th>
                        <th class="text-end">CO. DRIVER</th>
                        <th class="text-end">TOLL</th>
                        <th class="text-end">PARKIR</th>
                        <th class="text-end">P.OGAH</th>
                        <th class="text-end">PENGELUARAN</th>
                        <th class="text-end">SISA PENDAPATAN</th>
                        <th>CREW</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bus['trips'])): ?>
                        <tr><td colspan="14" class="text-center text-muted">Belum ada data trip untuk bulan ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bus['trips'] as $trip): ?>
                            <?php $ops = getTripOperationalCost($trip); ?>
                            <tr>
                                <td><?= date('d', strtotime($trip['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($trip['nama_order'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($trip['tujuan'] ?? '-') ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['harga_sewa']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['bbm']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['um']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['driver']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['co_driver']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['toll']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['parkir']) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['pogah']) ?></td>
                                <td class="text-end"><?= formatReportCurrency($ops) ?></td>
                                <td class="text-end"><?= formatReportCurrency((float) $trip['harga_sewa'] - $ops) ?></td>
                                <td><?= htmlspecialchars($trip['crew'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="table-primary fw-bold">
                        <td colspan="3">TOTAL</td>
                        <td class="text-end"><?= formatReportCurrency($bus['pemasukan']) ?></td>
                        <td colspan="7"></td>
                        <td class="text-end"><?= formatReportCurrency($bus['pengeluaran']) ?></td>
                        <td class="text-end"><?= formatReportCurrency($bus['pemasukan'] - $bus['pengeluaran']) ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-sheet mb-4">
        <h6 class="mb-3">MAINTENANCE & LAIN LAIN</h6>
        <table class="table report-table table-sm">
            <thead>
                <tr>
                    <th>TGL</th>
                    <th>KETERANGAN</th>
                    <th class="text-end">BIAYA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bus['maintenance_rows'])): ?>
                    <tr><td colspan="3" class="text-muted">Tidak ada biaya maintenance.</td></tr>
                <?php else: ?>
                    <?php foreach ($bus['maintenance_rows'] as $item): ?>
                        <tr>
                            <td><?= date('d', strtotime($item['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                            <td class="text-end"><?= formatReportCurrency((float) $item['biaya']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="table-warning fw-bold">
                    <td colspan="2">JUMLAH TOTAL MAINTENANCE</td>
                    <td class="text-end"><?= formatReportCurrency($bus['maintenance']) ?></td>
                </tr>
                <tr class="table-success fw-bold">
                    <td colspan="2">TOTAL SETORAN</td>
                    <td class="text-end"><?= formatReportCurrency($bus['total']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
