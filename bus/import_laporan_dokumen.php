<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_report_helper.php';
require_once 'includes/bus_report_importer.php';
require_once 'includes/bus_transaksi_sync.php';

requireLogin();
requireBusReportAccess($db, (int) $_SESSION['user_id']);

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $replace = isset($_POST['replace_existing']);
        $syncTransaksi = isset($_POST['sync_transaksi']);
        $result = importJuni2026Document($db, $replace);
        $syncMsg = '';
        if ($syncTransaksi && !empty($result['id_perusahaan']) && !empty($result['bulan'])) {
            $syncStats = syncBusReportPeriod($db, (int) $result['id_perusahaan'], $result['bulan'], (int) $_SESSION['user_id']);
            $syncMsg = " Sinkron transaksi: {$syncStats['transaksi']} baris.";
        }
        $_SESSION['success'] = 'Data laporan Juni 2026 dari dokumen berhasil dimasukkan ke sistem.' . $syncMsg;
        header('Location: operasional_bus.php?bulan=2026-06');
        exit();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

include '../templates/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2>Import Laporan dari Dokumen</h2>
            <p class="text-muted mb-0">Memasukkan data armada & laporan Juni 2026 dari PDF resmi</p>
        </div>
        <a href="laporan_bulanan.php?bulan=2026-06" class="btn btn-primary">Lihat Laporan Juni 2026</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Dokumen: LAPORAN BULANAN JUNI 2026</div>
        <div class="card-body">
            <p>Import ini akan:</p>
            <ul>
                <li>Menambah / memperbarui <strong>7 armada bus</strong> (NUGO 01 – NUGO 07)</li>
                <li>Mengisi <strong>tipe bus</strong>: KELUARGA (grup 1-5) dan VIP (grup 6-7)</li>
                <li>Mengisi <strong>kode armada</strong> (NG001–NG007), nomor polisi, dan lembar saham</li>
                <li>Memasukkan data trip & maintenance Juni 2026 sesuai dokumen</li>
            </ul>

            <table class="table table-sm table-bordered">
                <thead>
                    <tr>
                        <th>Armada</th>
                        <th>Nama Bus</th>
                        <th>Tipe</th>
                        <th>Grup Fee</th>
                        <th>Plat</th>
                        <th>Saham</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $preview = require __DIR__ . '/seed/juni_2026_data.php'; ?>
                    <?php foreach ($preview['buses'] as $bus): ?>
                        <tr>
                            <td><?= htmlspecialchars($bus['kode_armada']) ?></td>
                            <td><?= htmlspecialchars($bus['nama_bus']) ?></td>
                            <td><?= htmlspecialchars($bus['tipe']) ?></td>
                            <td><?= htmlspecialchars($bus['grup_armada']) ?> (<?= $bus['grup_armada'] === '1-5' ? '20% management' : '5%+5%+15%' ?>)</td>
                            <td><?= htmlspecialchars($bus['nomor_polisi']) ?></td>
                            <td><?= (int) $bus['lembar_saham'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <form method="POST" onsubmit="return confirm('Import data laporan Juni 2026 dari dokumen?');">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="sync_transaksi" id="sync_transaksi" checked>
                    <label class="form-check-label" for="sync_transaksi">
                        Sinkronkan ke laporan transaksi setelah import
                    </label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="replace_existing" id="replace_existing" checked>
                    <label class="form-check-label" for="replace_existing">
                        Ganti data laporan Juni 2026 yang sudah ada (disarankan)
                    </label>
                </div>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-file-import me-1"></i> Import Data Dokumen
                </button>
            </form>
        </div>
    </div>

    <?php if ($result): ?>
        <div class="alert alert-info">
            Import selesai: <?= (int) $result['buses'] ?> bus, <?= (int) $result['trips'] ?> trip, <?= (int) $result['maintenance'] ?> maintenance.
        </div>
    <?php endif; ?>
</div>

<?php include '../templates/footer.php'; ?>
