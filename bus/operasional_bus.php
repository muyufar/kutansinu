<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_report_helper.php';
require_once 'includes/bus_transaksi_sync.php';

requireLogin();
requireBusReportAccess($db, (int) $_SESSION['user_id']);

ensureBusReportSchema($db);

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$idPerusahaan = getNugoCompanyId($db);
$userId = (int) $_SESSION['user_id'];
$tanggalAwal = date('Y-m-01', strtotime($bulan . '-01'));
$tanggalAkhir = date('Y-m-t', strtotime($bulan . '-01'));
// Secara default laporan operasional hanya disimpan. Form mengirim penanda
// preferensi agar melepas ceklis dapat secara eksplisit mengaktifkan sinkronisasi.
$skipSync = !isset($_POST['sync_preference_present']) || isset($_POST['skip_sync']);
$autoSync = !$skipSync;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_trip') {
            $stmt = $db->prepare("INSERT INTO bus_laporan_trip
                (id_bus, id_pemesanan, tanggal, nama_order, tujuan, harga_sewa, bbm, um, driver, co_driver, toll, parkir, pogah, crew)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $idPemesanan = !empty($_POST['id_pemesanan']) ? (int) $_POST['id_pemesanan'] : null;
            $stmt->execute([
                (int) $_POST['id_bus'],
                $idPemesanan,
                $_POST['tanggal'],
                validateInput($_POST['nama_order'] ?? ''),
                validateInput($_POST['tujuan'] ?? ''),
                (float) str_replace('.', '', $_POST['harga_sewa'] ?? '0'),
                (float) str_replace('.', '', $_POST['bbm'] ?? '0'),
                (float) str_replace('.', '', $_POST['um'] ?? '0'),
                (float) str_replace('.', '', $_POST['driver'] ?? '0'),
                (float) str_replace('.', '', $_POST['co_driver'] ?? '0'),
                (float) str_replace('.', '', $_POST['toll'] ?? '0'),
                (float) str_replace('.', '', $_POST['parkir'] ?? '0'),
                (float) str_replace('.', '', $_POST['pogah'] ?? '0'),
                validateInput($_POST['crew'] ?? ''),
            ]);
            $tripId = (int) $db->lastInsertId();
            if ($autoSync) {
                syncTripToTransaksi($db, $tripId, $userId);
            }
            $_SESSION['success'] = 'Trip disimpan' . ($autoSync ? ' dan disinkronkan ke laporan transaksi.' : '.');
        }

        if ($action === 'save_maintenance') {
            $stmt = $db->prepare("INSERT INTO bus_laporan_maintenance (id_bus, tanggal, keterangan, biaya) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                (int) $_POST['id_bus'],
                $_POST['tanggal'],
                validateInput($_POST['keterangan'] ?? ''),
                (float) str_replace('.', '', $_POST['biaya'] ?? '0'),
            ]);
            $maintId = (int) $db->lastInsertId();
            if ($autoSync) {
                syncMaintenanceToTransaksi($db, $maintId, $userId);
            }
            $_SESSION['success'] = 'Maintenance disimpan' . ($autoSync ? ' dan disinkronkan ke laporan transaksi.' : '.');
        }

        if ($action === 'update_bus_meta') {
            $stmt = $db->prepare("UPDATE bus SET kode_armada = ?, lembar_saham = ? WHERE id = ? AND id_perusahaan = ?");
            $stmt->execute([
                validateInput($_POST['kode_armada'] ?? ''),
                (int) ($_POST['lembar_saham'] ?? 0),
                (int) $_POST['bus_id'],
                $idPerusahaan,
            ]);
            $_SESSION['success'] = 'Data armada berhasil diperbarui.';
        }

        if ($action === 'delete_trip') {
            $tripId = (int) $_POST['id'];
            unsyncTripFromTransaksi($db, $tripId);
            $stmt = $db->prepare("DELETE t FROM bus_laporan_trip t JOIN bus b ON t.id_bus = b.id WHERE t.id = ? AND b.id_perusahaan = ?");
            $stmt->execute([$tripId, $idPerusahaan]);
            $_SESSION['success'] = 'Trip dihapus beserta transaksi terkait.';
        }

        if ($action === 'delete_maintenance') {
            $maintId = (int) $_POST['id'];
            unsyncMaintenanceFromTransaksi($db, $maintId);
            $stmt = $db->prepare("DELETE m FROM bus_laporan_maintenance m JOIN bus b ON m.id_bus = b.id WHERE m.id = ? AND b.id_perusahaan = ?");
            $stmt->execute([$maintId, $idPerusahaan]);
            $_SESSION['success'] = 'Maintenance dihapus beserta transaksi terkait.';
        }

        if ($action === 'sync_period') {
            $stats = syncBusReportPeriod($db, $idPerusahaan, $bulan, $userId);
            $msg = "Sinkronisasi selesai: {$stats['trips']} trip, {$stats['maintenance']} maintenance, {$stats['transaksi']} baris transaksi.";
            if (!empty($stats['errors'])) {
                $msg .= ' Peringatan: ' . implode('; ', array_slice($stats['errors'], 0, 3));
            }
            $_SESSION['success'] = $msg;
        }

        if ($action === 'sync_trip') {
            syncTripToTransaksi($db, (int) $_POST['id'], $userId);
            $_SESSION['success'] = 'Trip disinkronkan ke laporan transaksi.';
        }

        if ($action === 'sync_maintenance') {
            syncMaintenanceToTransaksi($db, (int) $_POST['id'], $userId);
            $_SESSION['success'] = 'Maintenance disinkronkan ke laporan transaksi.';
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = 'Gagal: ' . $e->getMessage();
    }

    header('Location: operasional_bus.php?bulan=' . urlencode($bulan));
    exit();
}

$stmt = $db->prepare("SELECT * FROM bus WHERE id_perusahaan = ? ORDER BY nama_bus ASC");
$stmt->execute([$idPerusahaan]);
$buses = $stmt->fetchAll();

$stmtTrips = $db->prepare("SELECT t.*, b.nama_bus, b.kode_armada FROM bus_laporan_trip t JOIN bus b ON t.id_bus = b.id WHERE b.id_perusahaan = ? AND t.tanggal BETWEEN ? AND ? ORDER BY t.tanggal DESC, t.id DESC");
$stmtTrips->execute([$idPerusahaan, $tanggalAwal, $tanggalAkhir]);
$trips = $stmtTrips->fetchAll();

$stmtMaint = $db->prepare("SELECT m.*, b.nama_bus FROM bus_laporan_maintenance m JOIN bus b ON m.id_bus = b.id WHERE b.id_perusahaan = ? AND m.tanggal BETWEEN ? AND ? ORDER BY m.tanggal DESC, m.id DESC");
$stmtMaint->execute([$idPerusahaan, $tanggalAwal, $tanggalAkhir]);
$maintenanceRows = $stmtMaint->fetchAll();

$summary = fetchOperasionalSummary($db, $idPerusahaan, $bulan);
$accounts = resolveBusTransaksiAccounts($db, $idPerusahaan);

$prefill = [];
if (!empty($_GET['pemesanan_id'])) {
    $stmtOrder = $db->prepare("SELECT p.*, b.nama_bus FROM pemesanan_bus p JOIN bus b ON p.id_bus = b.id WHERE p.id = ?");
    $stmtOrder->execute([(int) $_GET['pemesanan_id']]);
    $prefill = $stmtOrder->fetch() ?: [];
}

$transaksiUrl = '/laporan/transaksi.php?tanggal_awal=' . urlencode($tanggalAwal)
    . '&tanggal_akhir=' . urlencode($tanggalAkhir)
    . '&tags[]=bus';

include '../templates/header.php';
?>

<div class="container mt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h2>Operasional Bus</h2>
            <p class="text-muted mb-0">Satu tempat input pendapatan & pengeluaran armada — otomatis masuk laporan transaksi</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="laporan_bulanan.php?bulan=<?= urlencode($bulan) ?>" class="btn btn-outline-primary">Laporan Bulanan PDF</a>
            <a href="<?= htmlspecialchars($transaksiUrl) ?>" class="btn btn-outline-secondary">Laporan Transaksi</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong>Cara kerja:</strong> Input trip & maintenance di halaman ini sesuai format laporan bulanan (harga sewa, BBM, UM, driver, toll, parkir, P.Ogah, maintenance).
        Data otomatis tercatat di <strong>Laporan Transaksi</strong> dengan tag <code>bus</code>.
        Laporan bagi hasil & fee management tetap di <a href="laporan_bulanan.php?bulan=<?= urlencode($bulan) ?>">Laporan Bulanan</a>.
    </div>

    <?php if (!$accounts['kas'] || !$accounts['pendapatan']): ?>
        <div class="alert alert-warning">Akun Kas/Pendapatan perusahaan NUGO belum lengkap. Sinkronisasi transaksi mungkin gagal sampai akun diatur di menu Akun.</div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12">
                            <label class="form-label">Bulan</label>
                            <input type="month" name="bulan" class="form-control" value="<?= htmlspecialchars($bulan) ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                        </div>
                    </form>
                </div>
                <div class="col-md-8 text-md-end">
                    <form method="POST" class="d-inline" onsubmit="return confirm('Sinkronkan semua data bulan ini ke laporan transaksi?');">
                        <input type="hidden" name="action" value="sync_period">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-sync me-1"></i> Sinkronkan Bulan Ini ke Transaksi
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body">
                    <div class="text-muted small">Pendapatan Sewa</div>
                    <div class="h4 mb-0 text-success"><?= formatReportCurrency($summary['pemasukan']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="text-muted small">Beban Operasional</div>
                    <div class="h4 mb-0 text-danger"><?= formatReportCurrency($summary['pengeluaran_ops']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="text-muted small">Maintenance</div>
                    <div class="h4 mb-0 text-warning"><?= formatReportCurrency($summary['maintenance']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="text-muted small">Laba Kotor Bulan Ini</div>
                    <div class="h4 mb-0"><?= formatReportCurrency($summary['laba_kotor']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Metadata Armada</div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Bus</th>
                            <th>Tipe</th>
                            <th>Kode Armada</th>
                            <th>Lembar Saham</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buses as $bus): ?>
                            <tr>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_bus_meta">
                                    <input type="hidden" name="bus_id" value="<?= $bus['id'] ?>">
                                    <td><?= htmlspecialchars($bus['nama_bus']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($bus['tipe'] ?? '-') ?></span></td>
                                    <td><input type="text" name="kode_armada" class="form-control form-control-sm" value="<?= htmlspecialchars($bus['kode_armada'] ?? '') ?>"></td>
                                    <td><input type="number" name="lembar_saham" class="form-control form-control-sm" value="<?= (int) ($bus['lembar_saham'] ?? 0) ?>"></td>
                                    <td><button type="submit" class="btn btn-sm btn-outline-primary">Simpan</button></td>
                                </form>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Tambah Trip Operasional</div>
                <div class="card-body">
                    <?php if ($prefill): ?>
                        <div class="alert alert-light border mb-3">Prefill dari pemesanan #<?= (int) $prefill['id'] ?> — lengkapi biaya operasional di bawah.</div>
                    <?php endif; ?>
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="action" value="save_trip">
                        <?php if ($prefill): ?>
                            <input type="hidden" name="id_pemesanan" value="<?= (int) $prefill['id'] ?>">
                        <?php endif; ?>
                        <div class="col-12">
                            <label class="form-label">Armada</label>
                            <select name="id_bus" class="form-select" required>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= $bus['id'] ?>" <?= ($prefill && (int)$prefill['id_bus'] === (int)$bus['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bus['nama_bus']) ?> (<?= htmlspecialchars($bus['tipe'] ?? '-') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" min="<?= $tanggalAwal ?>" max="<?= $tanggalAkhir ?>" value="<?= htmlspecialchars($prefill['tanggal_berangkat'] ?? '') ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Crew</label><input type="text" name="crew" class="form-control" placeholder="ibnu/eko"></div>
                        <div class="col-12"><label class="form-label">Nama Order</label><input type="text" name="nama_order" class="form-control" value="<?= htmlspecialchars($prefill['nama_pemesan'] ?? '') ?>"></div>
                        <div class="col-12"><label class="form-label">Tujuan</label><input type="text" name="tujuan" class="form-control" value="<?= htmlspecialchars(isset($prefill['kota_asal']) ? $prefill['kota_asal'] . ' → ' . $prefill['kota_tujuan'] : '') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Harga Sewa (Pendapatan)</label><input type="number" name="harga_sewa" class="form-control" min="0" step="1" value="<?= (int) ($prefill['total_harga'] ?? 0) ?>"></div>
                        <div class="col-md-6"><label class="form-label">BBM</label><input type="number" name="bbm" class="form-control" min="0" step="1"></div>
                        <div class="col-md-4"><label class="form-label">UM</label><input type="number" name="um" class="form-control" min="0" step="1"></div>
                        <div class="col-md-4"><label class="form-label">Driver</label><input type="number" name="driver" class="form-control" min="0" step="1"></div>
                        <div class="col-md-4"><label class="form-label">Co Driver</label><input type="number" name="co_driver" class="form-control" min="0" step="1"></div>
                        <div class="col-md-4"><label class="form-label">Toll</label><input type="number" name="toll" class="form-control" min="0" step="1"></div>
                        <div class="col-md-4"><label class="form-label">Parkir</label><input type="number" name="parkir" class="form-control" min="0" step="1"></div>
                        <div class="col-md-4"><label class="form-label">P.Ogah</label><input type="number" name="pogah" class="form-control" min="0" step="1"></div>
                        <div class="col-12">
                            <input type="hidden" name="sync_preference_present" value="1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="skip_sync" id="skip_sync" value="1" checked>
                                <label class="form-check-label" for="skip_sync">Simpan saja, jangan sinkronkan ke transaksi</label>
                            </div>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-success">Simpan Trip</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">Tambah Maintenance</div>
                <div class="card-body">
                    <form method="POST" class="row g-2">
                        <input type="hidden" name="action" value="save_maintenance">
                        <div class="col-12">
                            <label class="form-label">Armada</label>
                            <select name="id_bus" class="form-select" required>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= $bus['id'] ?>"><?= htmlspecialchars($bus['nama_bus']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Tanggal</label><input type="date" name="tanggal" class="form-control" min="<?= $tanggalAwal ?>" max="<?= $tanggalAkhir ?>" required></div>
                        <div class="col-md-6"><label class="form-label">Biaya</label><input type="number" name="biaya" class="form-control" min="0" step="1" required></div>
                        <div class="col-12"><label class="form-label">Keterangan</label><textarea name="keterangan" class="form-control" rows="3" placeholder="oli mesin, filter solar, semir ban, ..."></textarea></div>
                        <div class="col-12">
                            <input type="hidden" name="sync_preference_present" value="1">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="skip_sync" id="skip_sync_m" value="1" checked>
                                <label class="form-check-label" for="skip_sync_m">Simpan saja, jangan sinkronkan ke transaksi</label>
                            </div>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-success">Simpan Maintenance</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Daftar Trip — <?= getMonthNameId($bulan) ?> <?= date('Y', strtotime($bulan . '-01')) ?></span>
            <span class="small text-muted"><?= count($trips) ?> trip</span>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Bus</th>
                        <th>Order / Tujuan</th>
                        <th class="text-end">Sewa</th>
                        <th class="text-end">Beban Ops</th>
                        <th>Transaksi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trips as $trip): ?>
                        <?php $synced = getSourceSyncStatus($db, 'bus_laporan_trip', (int) $trip['id']); ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($trip['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($trip['nama_bus']) ?></td>
                            <td>
                                <div><?= htmlspecialchars($trip['nama_order'] ?? '-') ?></div>
                                <small class="text-muted"><?= htmlspecialchars($trip['tujuan'] ?? '') ?></small>
                                <?php if ($trip['id_pemesanan']): ?>
                                    <br><small class="badge bg-info text-dark">Pemesanan #<?= (int) $trip['id_pemesanan'] ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= formatReportCurrency((float) $trip['harga_sewa']) ?></td>
                            <td class="text-end"><?= formatReportCurrency(getTripOperationalCost($trip)) ?></td>
                            <td>
                                <?php if ($synced): ?>
                                    <span class="badge bg-success">Tersinkron</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (!$synced): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="sync_trip">
                                        <input type="hidden" name="id" value="<?= $trip['id'] ?>">
                                        <button class="btn btn-sm btn-outline-success">Sync</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus trip dan transaksi terkait?')">
                                    <input type="hidden" name="action" value="delete_trip">
                                    <input type="hidden" name="id" value="<?= $trip['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($trips)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">Belum ada trip bulan ini</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Daftar Maintenance</div>
        <div class="table-responsive">
            <table class="table table-striped table-sm mb-0">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Bus</th>
                        <th>Keterangan</th>
                        <th class="text-end">Biaya</th>
                        <th>Transaksi</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maintenanceRows as $item): ?>
                        <?php $synced = getSourceSyncStatus($db, 'bus_laporan_maintenance', (int) $item['id']); ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($item['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($item['nama_bus']) ?></td>
                            <td><?= htmlspecialchars($item['keterangan'] ?? '-') ?></td>
                            <td class="text-end"><?= formatReportCurrency((float) $item['biaya']) ?></td>
                            <td>
                                <?php if ($synced): ?>
                                    <span class="badge bg-success">Tersinkron</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if (!$synced): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="sync_maintenance">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-success">Sync</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus maintenance dan transaksi terkait?')">
                                    <input type="hidden" name="action" value="delete_maintenance">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($maintenanceRows)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">Belum ada maintenance bulan ini</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>
