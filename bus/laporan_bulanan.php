<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_report_helper.php';

requireLogin();
requireBusReportAccess($db, (int) $_SESSION['user_id']);

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$idPerusahaan = getNugoCompanyId($db);

if (!$idPerusahaan) {
    die('Perusahaan NUGO tidak ditemukan.');
}

$report = fetchBusMonthlyReport($db, $idPerusahaan, $bulan);
$summary = buildPendapatanSummary($report, $db, $idPerusahaan, $report['tanggal_awal'], $report['tanggal_akhir']);
$group15 = array_values(array_filter($report['buses'], fn($b) => $b['grup'] === '1-5'));
$group67 = array_values(array_filter($report['buses'], fn($b) => $b['grup'] === '6-7'));
$monthLabel = getMonthNameId($bulan);
$yearLabel = date('Y', strtotime($bulan . '-01'));

include '../templates/header.php';
?>

<style>
.report-sheet { background: #fff; border: 1px solid #ddd; padding: 24px; }
.report-title { text-align: center; font-weight: 700; margin-bottom: 4px; }
.report-subtitle { text-align: center; margin-bottom: 20px; }
.report-table th, .report-table td { border: 1px solid #333; padding: 8px 10px; vertical-align: top; }
.report-table th { background: #f3f3f3; }
.report-note { font-size: 12px; color: #666; }
.report-total { font-weight: 700; background: #eef6ff; }
@media print {
    .no-print { display: none !important; }
    .report-sheet { border: none; padding: 0; }
}
</style>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <div>
            <h2>Laporan Bulanan Bus</h2>
            <p class="text-muted mb-0">Format laporan PT. BARAKA NUGO INTERNATIONAL</p>
        </div>
        <div class="d-flex gap-2">
            <a href="operasional_bus.php?bulan=<?= urlencode($bulan) ?>" class="btn btn-outline-primary">Operasional Bus</a>
            <button onclick="window.print()" class="btn btn-secondary">Cetak</button>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Bulan Laporan</label>
                    <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="report-sheet mb-4">
        <div class="report-title">PT. BARAKA NUGO INTERNATIONAL</div>
        <div class="report-subtitle">BULAN : <?= $monthLabel ?> &nbsp;|&nbsp; TAHUN : <?= $yearLabel ?></div>
        <h5 class="mb-3">PENDAPATAN</h5>
        <table class="table report-table mb-0">
            <tbody>
                <tr>
                    <td>KAS PT NUGO 1-5</td>
                    <td class="text-end" style="width: 180px;"><?= formatReportCurrency($summary['kas_pt_nugo_1_5']) ?></td>
                    <td class="report-note">( 5% Cadangan )</td>
                </tr>
                <tr>
                    <td>KAS PT NUGO 6-7</td>
                    <td class="text-end"><?= formatReportCurrency($summary['kas_pt_nugo_6_7']) ?></td>
                    <td class="report-note">( 5% Cadangan )</td>
                </tr>
                <tr>
                    <td>Beban Administrasi Bank</td>
                    <td class="text-end"><?= formatReportCurrency($summary['beban_admin_bank']) ?></td>
                    <td></td>
                </tr>
                <tr class="report-total">
                    <td>Total Kas PT yang diterima</td>
                    <td class="text-end"><?= formatReportCurrency($summary['total_kas_pt']) ?></td>
                    <td></td>
                </tr>
                <tr>
                    <td>KAS PCNU MAGELANG 1-5</td>
                    <td class="text-end"><?= formatReportCurrency($summary['kas_pcnu_1_5']) ?></td>
                    <td class="report-note">( 5% Dari total bus NUGO 1-5 )</td>
                </tr>
                <tr>
                    <td>KAS PCNU MAGELANG 6-7</td>
                    <td class="text-end"><?= formatReportCurrency($summary['kas_pcnu_6_7']) ?></td>
                    <td class="report-note">( 5% Dari total bus NUGO 6-7 )</td>
                </tr>
                <tr>
                    <td colspan="3"><strong>PENDAPATAN BUS PCNU</strong></td>
                </tr>
                <?php if (empty($summary['pendapatan_bus_pcnu'])): ?>
                    <tr><td colspan="3" class="text-muted">Belum ada data pendapatan bus.</td></tr>
                <?php else: ?>
                    <?php foreach ($summary['pendapatan_bus_pcnu'] as $item): ?>
                        <tr>
                            <td>(<?= htmlspecialchars($item['nama_bus']) ?>)</td>
                            <td class="text-end"><?= formatReportCurrency($item['jumlah']) ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="report-total">
                    <td>JUMLAH TOTAL PENDAPATAN</td>
                    <td class="text-end"><?= formatReportCurrency($summary['grand_total']) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="report-sheet mb-4">
        <h5 class="mb-3">LAPORAN MANAGEMENT BUS NUGO INTERNATIONAL (ARMADA 1-5)</h5>
        <div class="table-responsive">
            <table class="table report-table table-sm">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>BUS</th>
                        <th class="text-end">PEMASUKAN</th>
                        <th class="text-end">PENGELUARAN OPERASIONAL</th>
                        <th class="text-end">MAINTENANCE</th>
                        <th class="text-end">TOTAL</th>
                        <th class="text-end">20% Fee Management</th>
                        <th class="text-end">SALDO PENDAPATAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group15 as $index => $bus): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($bus['nama_bus']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['pemasukan']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['pengeluaran']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['maintenance']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['total']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['fee_management']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['saldo_pendapatan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-sheet mb-4">
        <h5 class="mb-3">LAPORAN BUS NUGO - BUMNU PCNU (ARMADA 1-5)</h5>
        <div class="table-responsive">
            <table class="table report-table table-sm">
                <thead>
                    <tr>
                        <th>BUS</th>
                        <th class="text-end">LEMBAR SAHAM</th>
                        <th class="text-end">SALDO PENDAPATAN</th>
                        <th class="text-end">BAGI HASIL SESUAI SAHAM</th>
                        <th class="text-end">5% UNTUK NU</th>
                        <th class="text-end">PENDAPATAN PCNU</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group15 as $bus): ?>
                        <tr>
                            <td><?= htmlspecialchars($bus['nama_bus']) ?></td>
                            <td class="text-end"><?= (int) $bus['lembar_saham'] ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['saldo_pendapatan']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['bagi_hasil_saham'] ?? 0) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['nu_5pct'] ?? 0) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['pendapatan_pcnu'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="report-sheet mb-4">
        <h5 class="mb-3">LAPORAN MANAGEMENT BUS NUGO INTERNATIONAL (ARMADA 6-7)</h5>
        <div class="table-responsive">
            <table class="table report-table table-sm">
                <thead>
                    <tr>
                        <th>NO</th>
                        <th>BUS</th>
                        <th class="text-end">PEMASUKAN</th>
                        <th class="text-end">PENGELUARAN</th>
                        <th class="text-end">MAINTENANCE</th>
                        <th class="text-end">TOTAL</th>
                        <th class="text-end">5% KAS PC</th>
                        <th class="text-end">5% KAS PT</th>
                        <th class="text-end">15% Fee Management</th>
                        <th class="text-end">SALDO PENDAPATAN</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group67 as $index => $bus): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($bus['nama_bus']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['pemasukan']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['pengeluaran']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['maintenance']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['total']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['kas_pc']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['kas_pt']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['fee_management']) ?></td>
                            <td class="text-end"><?= formatReportCurrency($bus['saldo_pendapatan']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card no-print">
        <div class="card-header">Detail Armada per Bus</div>
        <div class="card-body d-flex flex-wrap gap-2">
            <?php foreach ($report['buses'] as $bus): ?>
                <a class="btn btn-outline-secondary btn-sm" href="laporan_armada.php?bus_id=<?= $bus['id'] ?>&bulan=<?= urlencode($bulan) ?>">
                    <?= htmlspecialchars($bus['kode_armada']) ?> - <?= htmlspecialchars($bus['nama_bus']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
