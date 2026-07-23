<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Cek status login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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

// Ringkasan keuangan semua perusahaan untuk admin
$ringkasan_perusahaan = [];
if ($is_admin) {
    $stmt = $db->prepare("SELECT p.id, p.nama FROM perusahaan p JOIN user_perusahaan up ON p.id = up.perusahaan_id WHERE up.user_id = ? AND up.role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $perusahaan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($perusahaan_list as $perusahaan) {
        $pid = $perusahaan['id'];
        // Total pemasukan
        $stmt_in = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pemasukan' AND id_perusahaan = ?");
        $stmt_in->execute([$pid]);
        $pemasukan = $stmt_in->fetch()['total'] ?? 0;
        // Total pengeluaran
        $stmt_out = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pengeluaran' AND id_perusahaan = ?");
        $stmt_out->execute([$pid]);
        $pengeluaran = $stmt_out->fetch()['total'] ?? 0;
        // Saldo akhir
        $saldo = $pemasukan - $pengeluaran;
        $ringkasan_perusahaan[] = [
            'nama' => $perusahaan['nama'],
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'saldo' => $saldo
        ];
    }
}

// Ambil id_perusahaan dari default_company pengguna
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

/**
 * Bangun data seri arus kas untuk grafik dashboard.
 * @return array{labels:array,pemasukan:array,pengeluaran:array,kas_bersih:array,period_masuk:int,period_keluar:int,kas_akhir:int,subtitle:string}
 */
function buildCashFlowSeries(PDO $db, int $idPerusahaan, string $mode): array
{
    $bulanShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $today = new DateTime('today');
    $periods = [];
    $subtitle = '';

    if ($mode === 'harian') {
        for ($i = 13; $i >= 0; $i--) {
            $d = (clone $today)->modify("-{$i} days");
            $key = $d->format('Y-m-d');
            $periods[] = ['start' => $key, 'end' => $key, 'label' => $d->format('d M')];
        }
        $subtitle = '14 hari terakhir';
    } elseif ($mode === 'minggu') {
        $monday = (clone $today)->modify('monday this week');
        for ($i = 7; $i >= 0; $i--) {
            $start = (clone $monday)->modify("-{$i} weeks");
            $end = (clone $start)->modify('+6 days');
            if ($end > $today) {
                $end = clone $today;
            }
            $periods[] = [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'label' => $start->format('d/m') . '–' . $end->format('d/m'),
            ];
        }
        $subtitle = '8 minggu terakhir (Sen–Min)';
    } else {
        $firstOfMonth = new DateTime($today->format('Y-m-01'));
        for ($i = 11; $i >= 0; $i--) {
            $start = (clone $firstOfMonth)->modify("-{$i} months");
            $end = (clone $start)->modify('last day of this month');
            if ($end > $today) {
                $end = clone $today;
            }
            $periods[] = [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
                'label' => $bulanShort[(int)$start->format('n') - 1] . ' ' . $start->format('y'),
            ];
        }
        $subtitle = '12 bulan terakhir';
    }

    $rangeStart = $periods[0]['start'];
    $rangeEnd = $periods[count($periods) - 1]['end'];

    $stmt = $db->prepare("SELECT SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE -jumlah END) AS saldo FROM transaksi WHERE tanggal < ? AND id_perusahaan = ?");
    $stmt->execute([$rangeStart, $idPerusahaan]);
    $kasRunning = (int)($stmt->fetchColumn() ?: 0);

    $stmt = $db->prepare("SELECT tanggal, jenis, jumlah FROM transaksi WHERE id_perusahaan = ? AND tanggal >= ? AND tanggal <= ? ORDER BY tanggal");
    $stmt->execute([$idPerusahaan, $rangeStart, $rangeEnd]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $pemasukan = [];
    $pengeluaran = [];
    $kas_bersih = [];
    $totalMasuk = 0;
    $totalKeluar = 0;

    foreach ($periods as $p) {
        $masuk = 0;
        $keluar = 0;
        foreach ($rows as $row) {
            if ($row['tanggal'] >= $p['start'] && $row['tanggal'] <= $p['end']) {
                if ($row['jenis'] === 'pemasukan') {
                    $masuk += (int)$row['jumlah'];
                } else {
                    $keluar += (int)$row['jumlah'];
                }
            }
        }
        $totalMasuk += $masuk;
        $totalKeluar += $keluar;
        $kasRunning += $masuk - $keluar;
        $labels[] = $p['label'];
        $pemasukan[] = $masuk;
        $pengeluaran[] = -$keluar;
        $kas_bersih[] = $kasRunning;
    }

    return [
        'labels' => $labels,
        'pemasukan' => $pemasukan,
        'pengeluaran' => $pengeluaran,
        'kas_bersih' => $kas_bersih,
        'period_masuk' => $totalMasuk,
        'period_keluar' => $totalKeluar,
        'kas_akhir' => $kasRunning,
        'subtitle' => $subtitle,
    ];
}

$cashflow_series = [
    'harian' => buildCashFlowSeries($db, (int)$id_perusahaan, 'harian'),
    'minggu' => buildCashFlowSeries($db, (int)$id_perusahaan, 'minggu'),
    'bulan' => buildCashFlowSeries($db, (int)$id_perusahaan, 'bulan'),
];
$cashflow_default = 'minggu';
$cf = $cashflow_series[$cashflow_default];

$labels_json = json_encode($cf['labels']);
$pemasukan_json = json_encode($cf['pemasukan']);
$pengeluaran_json = json_encode($cf['pengeluaran']);
$kas_bersih_json = json_encode($cf['kas_bersih']);
$cashflow_all_json = json_encode($cashflow_series);

// Query distribusi pengeluaran per akun debit
$stmt = $db->prepare("SELECT ad.nama_akun as kategori, SUM(t.jumlah) as total FROM transaksi t LEFT JOIN akun ad ON t.id_akun_debit = ad.id WHERE t.jenis = 'pengeluaran' AND t.id_perusahaan = ? GROUP BY ad.nama_akun ORDER BY total DESC");
$stmt->execute([$id_perusahaan]);
$pengeluaran_kategori = $stmt->fetchAll();

$total_pengeluaran = 0;
foreach ($pengeluaran_kategori as $row) {
    $total_pengeluaran += (int)$row['total'];
}

$max_chart_slices = 5;
$palette = ['#059669', '#2563eb', '#d97706', '#dc2626', '#7c3aed', '#64748b'];
$chart_items = [];
$breakdown_items = [];

$top_rows = array_slice($pengeluaran_kategori, 0, $max_chart_slices);
$rest_rows = array_slice($pengeluaran_kategori, $max_chart_slices);
$other_total = 0;
foreach ($rest_rows as $row) {
    $other_total += (int)$row['total'];
}

foreach ($top_rows as $i => $row) {
    $total = (int)$row['total'];
    $label = $row['kategori'] ?: 'Lainnya';
    $pct = $total_pengeluaran > 0 ? round($total / $total_pengeluaran * 100, 1) : 0;
    $chart_items[] = [
        'label' => $label,
        'total' => $total,
        'pct' => $pct,
        'color' => $palette[$i % count($palette)],
    ];
}
if ($other_total > 0) {
    $other_pct = $total_pengeluaran > 0 ? round($other_total / $total_pengeluaran * 100, 1) : 0;
    $chart_items[] = [
        'label' => 'Lainnya (' . count($rest_rows) . ' akun)',
        'total' => $other_total,
        'pct' => $other_pct,
        'color' => $palette[5],
    ];
}

foreach ($chart_items as $i => $item) {
    $breakdown_items[] = [
        'label' => $item['label'],
        'total' => $item['total'],
        'pct' => $item['pct'],
        'color' => $item['color'],
        'chart_index' => $i,
    ];
}

$kategori_labels = array_column($chart_items, 'label');
$kategori_values = array_column($chart_items, 'total');
$color_map = array_column($chart_items, 'color');
$kategori_labels_json = json_encode($kategori_labels);
$kategori_values_json = json_encode($kategori_values);
$color_map_json = json_encode($color_map);
$total_pengeluaran_formatted = number_format($total_pengeluaran, 0, ',', '.');
$jumlah_kategori = count($pengeluaran_kategori);

// ─── Dashboard extended data ───
$stmt = $db->prepare("SELECT nama FROM perusahaan WHERE id = ?");
$stmt->execute([$id_perusahaan]);
$perusahaan_nama = $stmt->fetchColumn() ?: 'Perusahaan';

$stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$username_display = $stmt->fetchColumn() ?: 'Pengguna';

$stmt = $db->prepare("SELECT role FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
$stmt->execute([$_SESSION['user_id'], $id_perusahaan]);
$user_role_home = $stmt->fetchColumn();
$is_viewer_home = ($user_role_home === 'viewer');

function dashboardPeriodTotals(PDO $db, int $pid, string $start, ?string $end = null): array
{
    $sql = "SELECT
        COALESCE(SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END), 0) AS masuk,
        COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END), 0) AS keluar
        FROM transaksi WHERE id_perusahaan = ? AND tanggal >= ?";
    $params = [$pid, $start];
    if ($end !== null) {
        $sql .= " AND tanggal <= ?";
        $params[] = $end;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return ['masuk' => (int)$row['masuk'], 'keluar' => (int)$row['keluar']];
}

function dashboardTrendPct(int $current, int $previous): ?float
{
    if ($previous === 0) {
        return $current > 0 ? 100.0 : null;
    }
    return round(($current - $previous) / $previous * 100, 1);
}

function fmtRp($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

$bulan_ini_start = date('Y-m-01');
$bulan_lalu_start = date('Y-m-01', strtotime('first day of last month'));
$bulan_lalu_end = date('Y-m-t', strtotime('last day of last month'));
$bulan_nama_id = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$periode_label = $bulan_nama_id[(int)date('n') - 1] . ' ' . date('Y');

$totals_all = dashboardPeriodTotals($db, (int)$id_perusahaan, '1970-01-01');
$totals_bulan_ini = dashboardPeriodTotals($db, (int)$id_perusahaan, $bulan_ini_start);
$totals_bulan_lalu = dashboardPeriodTotals($db, (int)$id_perusahaan, $bulan_lalu_start, $bulan_lalu_end);

$summary_pemasukan = $totals_all['masuk'];
$summary_pengeluaran = $totals_all['keluar'];
$summary_saldo = $summary_pemasukan - $summary_pengeluaran;
$net_bulan_ini = $totals_bulan_ini['masuk'] - $totals_bulan_ini['keluar'];

$trend_pemasukan = dashboardTrendPct($totals_bulan_ini['masuk'], $totals_bulan_lalu['masuk']);
$trend_pengeluaran = dashboardTrendPct($totals_bulan_ini['keluar'], $totals_bulan_lalu['keluar']);

$stmt = $db->prepare("SELECT COUNT(*) FROM transaksi WHERE id_perusahaan = ? AND tanggal >= ?");
$stmt->execute([$id_perusahaan, $bulan_ini_start]);
$tx_bulan_ini = (int)$stmt->fetchColumn();

$period_masuk = $cf['period_masuk'];
$period_keluar = $cf['period_keluar'];
$kas_akhir_periode = $cf['kas_akhir'];
$cashflow_subtitle = $cf['subtitle'];

$stmt = $db->prepare("
    SELECT t.id, t.tanggal, t.jenis, t.jumlah, t.keterangan, t.tag,
           ad.nama_akun AS akun_debit, ak.nama_akun AS akun_kredit
    FROM transaksi t
    LEFT JOIN akun ad ON t.id_akun_debit = ad.id
    LEFT JOIN akun ak ON t.id_akun_kredit = ak.id
    WHERE t.id_perusahaan = ?
    ORDER BY t.tanggal DESC, t.id DESC
    LIMIT 8
");
$stmt->execute([$id_perusahaan]);
$transaksi_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header
include 'templates/header.php';

// Content
?>
<div class="container dashboard-home py-3 py-md-4">

    <section class="dashboard-hero mb-4">
        <div class="dashboard-hero-main">
            <p class="dashboard-hero-eyebrow"><i class="fas fa-chart-line me-1"></i> Dashboard Keuangan</p>
            <h1 class="dashboard-hero-title">Assalamualaikum, <?= htmlspecialchars($username_display) ?></h1>
            <p class="dashboard-hero-subtitle mb-0">
                Ringkasan keuangan <strong><?= htmlspecialchars($perusahaan_nama) ?></strong> · <?= $periode_label ?>
            </p>
        </div>
        <?php if (!$is_viewer_home): ?>
            <div class="dashboard-hero-actions">
                <a href="transaksi/tambah.php" class="btn btn-primary dashboard-btn-action">
                    <i class="fas fa-plus"></i> Tambah Transaksi
                </a>
                <a href="laporan/transaksi.php" class="btn btn-outline-secondary dashboard-btn-action">
                    <i class="fas fa-file-alt"></i> Laporan
                </a>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="dashboard-kpi-card income">
                <div class="dashboard-kpi-top">
                    <div class="dashboard-kpi-icon"><i class="fas fa-arrow-up"></i></div>
                    <?php if ($trend_pemasukan !== null): ?>
                        <span class="dashboard-kpi-trend <?= $trend_pemasukan >= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-<?= $trend_pemasukan >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                            <?= abs($trend_pemasukan) ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <div class="dashboard-kpi-label">Total Pemasukan</div>
                <div class="dashboard-kpi-value" id="total-pemasukan"><?= fmtRp($summary_pemasukan) ?></div>
                <div class="dashboard-kpi-meta">Bulan ini: <strong><?= fmtRp($totals_bulan_ini['masuk']) ?></strong></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-kpi-card expense">
                <div class="dashboard-kpi-top">
                    <div class="dashboard-kpi-icon"><i class="fas fa-arrow-down"></i></div>
                    <?php if ($trend_pengeluaran !== null): ?>
                        <span class="dashboard-kpi-trend <?= $trend_pengeluaran <= 0 ? 'up' : 'down' ?>">
                            <i class="fas fa-<?= $trend_pengeluaran <= 0 ? 'arrow-down' : 'arrow-up' ?>"></i>
                            <?= abs($trend_pengeluaran) ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <div class="dashboard-kpi-label">Total Pengeluaran</div>
                <div class="dashboard-kpi-value" id="total-pengeluaran"><?= fmtRp($summary_pengeluaran) ?></div>
                <div class="dashboard-kpi-meta">Bulan ini: <strong><?= fmtRp($totals_bulan_ini['keluar']) ?></strong></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-kpi-card balance">
                <div class="dashboard-kpi-top">
                    <div class="dashboard-kpi-icon"><i class="fas fa-wallet"></i></div>
                    <span class="dashboard-kpi-chip"><?= $tx_bulan_ini ?> transaksi</span>
                </div>
                <div class="dashboard-kpi-label">Saldo Bersih</div>
                <div class="dashboard-kpi-value" id="saldo"><?= fmtRp($summary_saldo) ?></div>
                <div class="dashboard-kpi-meta">Net bulan ini: <strong class="<?= $net_bulan_ini >= 0 ? 'text-success' : 'text-danger' ?>"><?= fmtRp($net_bulan_ini) ?></strong></div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card dashboard-panel h-100">
                <div class="card-body">
                    <div class="dashboard-panel-head">
                        <div>
                            <h5 class="dashboard-panel-title mb-0">Arus Kas</h5>
                            <small class="text-muted" id="cashflowSubtitle"><?= htmlspecialchars($cashflow_subtitle) ?></small>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="cashflow-period-toggle" role="group" aria-label="Periode arus kas">
                                <button type="button" class="cashflow-period-btn" data-cashflow-period="harian">Harian</button>
                                <button type="button" class="cashflow-period-btn is-active" data-cashflow-period="minggu">Minggu</button>
                                <button type="button" class="cashflow-period-btn" data-cashflow-period="bulan">Bulan</button>
                            </div>
                            <div class="dashboard-panel-stats">
                                <span class="dashboard-stat-pill in" id="cashflowStatIn"><i class="fas fa-circle"></i> Masuk <?= fmtRp($period_masuk) ?></span>
                                <span class="dashboard-stat-pill out" id="cashflowStatOut"><i class="fas fa-circle"></i> Keluar <?= fmtRp($period_keluar) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-chart-wrap">
                        <canvas id="financeChart" aria-label="Grafik arus kas"></canvas>
                    </div>
                    <div class="dashboard-chart-foot">
                        <span>Kas akhir periode</span>
                        <strong id="cashflowEndBalance"><?= fmtRp($kas_akhir_periode) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card dashboard-panel expense-distribution-card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="dashboard-panel-title mb-0">Distribusi Pengeluaran</h5>
                            <small class="text-muted"><?= $jumlah_kategori ?> kategori akun</small>
                        </div>
                        <?php if (!empty($breakdown_items)): ?>
                            <span class="badge expense-top-badge">
                                Top: <?= htmlspecialchars($breakdown_items[0]['label']) ?> (<?= $breakdown_items[0]['pct'] ?>%)
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($total_pengeluaran > 0): ?>
                        <div class="expense-chart-wrap">
                            <canvas id="expenseChart" aria-label="Grafik distribusi pengeluaran"></canvas>
                            <div class="expense-chart-center" aria-hidden="true">
                                <span class="expense-chart-center-label">Total</span>
                                <span class="expense-chart-center-value">Rp <?= $total_pengeluaran_formatted ?></span>
                            </div>
                        </div>

                        <ul class="expense-breakdown-list list-unstyled mb-0 mt-2" id="expenseBreakdownList">
                            <?php foreach ($breakdown_items as $item): ?>
                                <li class="expense-breakdown-item" data-index="<?= (int)$item['chart_index'] ?>">
                                    <div class="expense-breakdown-head">
                                        <span class="expense-breakdown-dot" style="background: <?= htmlspecialchars($item['color']) ?>"></span>
                                        <span class="expense-breakdown-name" title="<?= htmlspecialchars($item['label']) ?>"><?= htmlspecialchars($item['label']) ?></span>
                                        <span class="expense-breakdown-pct"><?= $item['pct'] ?>%</span>
                                    </div>
                                    <div class="expense-breakdown-bar-track">
                                        <div class="expense-breakdown-bar-fill" style="width: <?= min(100, $item['pct']) ?>%; background: <?= htmlspecialchars($item['color']) ?>"></div>
                                    </div>
                                    <div class="expense-breakdown-amount"><?= fmtRp($item['total']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($jumlah_kategori > $max_chart_slices): ?>
                            <div class="text-center mt-2">
                                <a href="laporan/transaksi.php" class="small text-decoration-none">Lihat semua transaksi →</a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="expense-empty-state text-center py-4 text-muted">
                            <i class="fas fa-chart-pie fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0 small">Belum ada data pengeluaran untuk ditampilkan.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card dashboard-panel">
                <div class="card-body">
                    <div class="dashboard-panel-head mb-3">
                        <div>
                            <h5 class="dashboard-panel-title mb-0">Transaksi Terbaru</h5>
                            <small class="text-muted">8 transaksi terakhir</small>
                        </div>
                        <a href="transaksi/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>

                    <?php if (!empty($transaksi_terbaru)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover dashboard-recent-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Jenis</th>
                                        <th>Akun</th>
                                        <th class="text-end">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksi_terbaru as $tx): ?>
                                        <?php
                                        $akun_label = $tx['jenis'] === 'pemasukan'
                                            ? ($tx['akun_kredit'] ?: '-')
                                            : ($tx['akun_debit'] ?: '-');
                                        if (!empty($tx['keterangan'])) {
                                            $akun_label = mb_strimwidth($tx['keterangan'], 0, 42, '…');
                                        }
                                        ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($tx['tanggal'])) ?></td>
                                            <td>
                                                <span class="badge dashboard-tx-badge <?= $tx['jenis'] === 'pemasukan' ? 'income' : 'expense' ?>">
                                                    <?= $tx['jenis'] === 'pemasukan' ? 'Masuk' : 'Keluar' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="dashboard-tx-akun" title="<?= htmlspecialchars($akun_label) ?>"><?= htmlspecialchars($akun_label) ?></span>
                                                <?php if (!empty($tx['tag'])): ?>
                                                    <span class="dashboard-tx-tag"><?= htmlspecialchars($tx['tag']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-bold <?= $tx['jenis'] === 'pemasukan' ? 'text-success' : 'text-danger' ?>">
                                                <?= fmtRp($tx['jumlah']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="dashboard-empty-state text-center py-5 text-muted">
                            <i class="fas fa-receipt fa-2x mb-2 opacity-50"></i>
                            <p class="mb-2">Belum ada transaksi tercatat.</p>
                            <?php if (!$is_viewer_home): ?>
                                <a href="transaksi/tambah.php" class="btn btn-sm btn-primary">Catat Transaksi Pertama</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dashboard-panel h-100">
                <div class="card-body">
                    <h5 class="dashboard-panel-title mb-3">Akses Cepat</h5>
                    <div class="dashboard-quick-links">
                        <?php if (!$is_viewer_home): ?>
                            <a href="transaksi/tambah.php" class="dashboard-quick-link">
                                <span class="dashboard-quick-icon bg-primary-soft"><i class="fas fa-plus"></i></span>
                                <span><strong>Tambah Transaksi</strong><small>Catat pemasukan / pengeluaran</small></span>
                            </a>
                            <a href="akun/index.php" class="dashboard-quick-link">
                                <span class="dashboard-quick-icon bg-secondary-soft"><i class="fas fa-list"></i></span>
                                <span><strong>Daftar Akun</strong><small>Kelola chart of accounts</small></span>
                            </a>
                        <?php endif; ?>
                        <a href="laporan/laba-rugi.php" class="dashboard-quick-link">
                            <span class="dashboard-quick-icon bg-success-soft"><i class="fas fa-chart-line"></i></span>
                            <span><strong>Laba Rugi</strong><small>Laporan performa keuangan</small></span>
                        </a>
                        <a href="laporan/neraca.php" class="dashboard-quick-link">
                            <span class="dashboard-quick-icon bg-info-soft"><i class="fas fa-balance-scale"></i></span>
                            <span><strong>Neraca</strong><small>Posisi keuangan saat ini</small></span>
                        </a>
                        <a href="laporan/transaksi.php" class="dashboard-quick-link">
                            <span class="dashboard-quick-icon bg-warning-soft"><i class="fas fa-search"></i></span>
                            <span><strong>Cari Transaksi</strong><small>Filter &amp; export data</small></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_admin && !empty($ringkasan_perusahaan)): ?>
        <section class="mb-4">
            <div class="dashboard-panel-head mb-3">
                <div>
                    <h5 class="dashboard-panel-title mb-0">Ringkasan Perusahaan</h5>
                    <small class="text-muted">Tampilan admin — semua entitas yang Anda kelola</small>
                </div>
            </div>
            <div class="row g-3">
                <?php foreach ($ringkasan_perusahaan as $r): ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="dashboard-company-card">
                            <div class="dashboard-company-icon"><i class="fas fa-building"></i></div>
                            <h6 class="dashboard-company-name"><?= htmlspecialchars($r['nama']) ?></h6>
                            <div class="dashboard-company-rows">
                                <div><span>Masuk</span><strong class="text-primary"><?= fmtRp($r['pemasukan']) ?></strong></div>
                                <div><span>Keluar</span><strong class="text-warning"><?= fmtRp($r['pengeluaran']) ?></strong></div>
                                <div><span>Saldo</span><strong class="<?= $r['saldo'] < 0 ? 'text-danger' : 'text-success' ?>"><?= fmtRp($r['saldo']) ?></strong></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const cashFlowData = <?php echo $cashflow_all_json; ?>;
        let cashFlowPeriod = localStorage.getItem('cashflowPeriod') || '<?= $cashflow_default ?>';
        if (!cashFlowData[cashFlowPeriod]) {
            cashFlowPeriod = 'minggu';
        }
        const cfInit = cashFlowData[cashFlowPeriod];
        const labels = cfInit.labels;
        const pemasukan = cfInit.pemasukan;
        const pengeluaran = cfInit.pengeluaran;
        const kasBersih = cfInit.kas_bersih;

        const formatRpFull = (n) => 'Rp ' + Number(n).toLocaleString('id-ID');

        const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
        const gridColor = () => isDark() ? 'rgba(148,163,184,0.12)' : 'rgba(148,163,184,0.25)';
        const tickColor = () => isDark() ? '#94a3b8' : '#64748b';
        const formatRpShort = (value) => {
            const abs = Math.abs(value);
            if (abs >= 1e9) return (value / 1e9).toFixed(1) + ' M';
            if (abs >= 1e6) return (value / 1e6).toFixed(1) + ' jt';
            if (abs >= 1e3) return (value / 1e3).toFixed(0) + ' rb';
            return value.toLocaleString('id-ID');
        };

        const financeChart = new Chart(document.getElementById('financeChart').getContext('2d'), {
            data: {
                labels: labels,
                datasets: [{
                        type: 'bar',
                        label: 'Kas Masuk',
                        data: pemasukan,
                        backgroundColor: 'rgba(5, 150, 105, 0.75)',
                        borderRadius: 8,
                        borderSkipped: false,
                        order: 2
                    },
                    {
                        type: 'bar',
                        label: 'Kas Keluar',
                        data: pengeluaran,
                        backgroundColor: 'rgba(220, 38, 38, 0.72)',
                        borderRadius: 8,
                        borderSkipped: false,
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'Kas Bersih',
                        data: kasBersih,
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.12)',
                        borderWidth: 2.5,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#d97706',
                        fill: true,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 16,
                            color: tickColor
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (ctx) => {
                                const v = ctx.parsed.y ?? ctx.parsed;
                                return ' ' + ctx.dataset.label + ': Rp ' + Math.abs(v).toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: tickColor, font: { size: 11, weight: '600' } }
                    },
                    y: {
                        beginAtZero: false,
                        grid: { color: gridColor() },
                        ticks: {
                            color: tickColor,
                            callback: (value) => formatRpShort(value)
                        }
                    }
                }
            }
        });

        function loadTransactionSummary() {
            fetch('api/get_summary.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) return;
                    document.getElementById('total-pemasukan').textContent = data.total_pemasukan;
                    document.getElementById('total-pengeluaran').textContent = data.total_pengeluaran;
                    document.getElementById('saldo').textContent = data.saldo;
                })
                .catch(error => console.error('Error:', error));
        }

        document.addEventListener('DOMContentLoaded', loadTransactionSummary);

        document.addEventListener('DOMContentLoaded', function() {
            function applyCashFlowPeriod(period) {
                const d = cashFlowData[period];
                if (!d) return;
                cashFlowPeriod = period;
                localStorage.setItem('cashflowPeriod', period);

                financeChart.data.labels = d.labels;
                financeChart.data.datasets[0].data = d.pemasukan;
                financeChart.data.datasets[1].data = d.pengeluaran;
                financeChart.data.datasets[2].data = d.kas_bersih;
                financeChart.update();

                document.getElementById('cashflowSubtitle').textContent = d.subtitle;
                document.getElementById('cashflowStatIn').innerHTML = '<i class="fas fa-circle"></i> Masuk ' + formatRpFull(d.period_masuk);
                document.getElementById('cashflowStatOut').innerHTML = '<i class="fas fa-circle"></i> Keluar ' + formatRpFull(d.period_keluar);
                document.getElementById('cashflowEndBalance').textContent = formatRpFull(d.kas_akhir);

                document.querySelectorAll('[data-cashflow-period]').forEach(function(btn) {
                    btn.classList.toggle('is-active', btn.dataset.cashflowPeriod === period);
                });
            }

            document.querySelectorAll('[data-cashflow-period]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    applyCashFlowPeriod(this.dataset.cashflowPeriod);
                });
            });

            applyCashFlowPeriod(cashFlowPeriod);

            const observer = new MutationObserver(function() {
                financeChart.options.scales.y.grid.color = gridColor();
                financeChart.options.scales.x.ticks.color = tickColor();
                financeChart.options.scales.y.ticks.color = tickColor();
                financeChart.options.plugins.legend.labels.color = tickColor();
                financeChart.update('none');
            });
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        });
        <?php if ($total_pengeluaran > 0): ?>
        (function initExpenseChart() {
            const pengeluaranLabels = <?php echo $kategori_labels_json; ?>;
            const pengeluaranData = <?php echo $kategori_values_json; ?>;
            const pengeluaranColors = <?php echo $color_map_json; ?>;
            const totalPengeluaranNum = <?php echo (int)$total_pengeluaran; ?>;
            const ctxPie = document.getElementById('expenseChart');
            if (!ctxPie) return;

            const formatRp = (n) => 'Rp ' + Number(n).toLocaleString('id-ID');

            const expenseChart = new Chart(ctxPie.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: pengeluaranLabels,
                    datasets: [{
                        data: pengeluaranData,
                        backgroundColor: pengeluaranColors,
                        borderWidth: 2,
                        borderColor: 'transparent',
                        hoverBorderColor: '#ffffff',
                        hoverBorderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    cutout: '68%',
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed || 0;
                                    const pct = totalPengeluaranNum > 0
                                        ? ((value / totalPengeluaranNum) * 100).toFixed(1)
                                        : '0.0';
                                    return ' ' + context.label + ': ' + formatRp(value) + ' (' + pct + '%)';
                                }
                            }
                        }
                    },
                    onHover: (event, elements) => {
                        event.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                        document.querySelectorAll('.expense-breakdown-item').forEach(el => el.classList.remove('is-active'));
                        if (elements.length) {
                            const idx = elements[0].index;
                            const listItem = document.querySelector('.expense-breakdown-item[data-index="' + idx + '"]');
                            if (listItem) listItem.classList.add('is-active');
                        }
                    }
                }
            });

            document.querySelectorAll('.expense-breakdown-item').forEach(function(item) {
                item.addEventListener('mouseenter', function() {
                    const idx = parseInt(this.dataset.index, 10);
                    expenseChart.setActiveElements([{ datasetIndex: 0, index: idx }]);
                    expenseChart.tooltip.setActiveElements([{ datasetIndex: 0, index: idx }]);
                    expenseChart.update();
                    document.querySelectorAll('.expense-breakdown-item').forEach(el => el.classList.remove('is-active'));
                    this.classList.add('is-active');
                });
                item.addEventListener('mouseleave', function() {
                    expenseChart.setActiveElements([]);
                    expenseChart.tooltip.setActiveElements([]);
                    expenseChart.update();
                    this.classList.remove('is-active');
                });
            });
        })();
        <?php endif; ?>

    </script>

    <?php
    // Footer
    include 'templates/footer.php';
    ?>