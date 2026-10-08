<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

$bumnuHelper = __DIR__ . '/../includes/bumnu_kas_public.php';
$bumnuConfigFile = __DIR__ . '/../config/public_report.php';

if (!is_file($bumnuHelper)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>File <code>includes/bumnu_kas_public.php</code> belum ada di server. Upload folder <code>includes/</code> dari project lokal.</p>';
    exit;
}
if (!is_file($bumnuConfigFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>File <code>config/public_report.php</code> belum ada di server.</p>';
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../config/functions.php';
    require_once $bumnuHelper;
    $config = bumnuPublicConfig();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<p>Laporan sementara tidak dapat dimuat. Periksa konfigurasi server.</p>';
    exit;
}

$cssVer = @filemtime(__DIR__ . '/../assets/css/publik-bumnu-kas.css') ?: time();

if (!bumnuPublicCheckToken()) {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Akses ditolak</title>
    <link rel="stylesheet" href="/assets/css/publik-bumnu-kas.css?v=<?= (int) $cssVer ?>">
</head>
<body class="bumnu-publik">
    <div class="bumnu-denied">
        <h1>Akses ditolak</h1>
        <p>Halaman ini memerlukan tautan dengan kode akses yang valid dari pengurus BUMNU.</p>
    </div>
</body>
</html>
    <?php
    exit;
}

$periode = bumnuResolvePeriode($_GET);
$tanggal_awal = $periode['tanggal_awal'];
$tanggal_akhir = $periode['tanggal_akhir'];
$hari_sebelum_awal = $periode['hari_sebelum_awal'];
$periode_label = $periode['label'];
$filter_mode = $periode['mode'];
$filter_form = $periode['form'];

$token_hidden = !empty($config['access_token']) ? trim((string) ($_GET['token'] ?? '')) : '';

$perusahaan = null;
$error = null;
$saldo_akhir = 0.0;
$saldo_awal = 0.0;
$mutasi = ['masuk' => 0.0, 'keluar' => 0.0, 'by_jenis' => []];
$rekening = [];
$transaksi = [];
$tx_count = 0;
$tx_count_all = 0;
$tag_options = [];
$tag_filter = bumnuParseTagFilter($_GET);
$tag_filter_label = null;
$logo_url = null;

try {
    $perusahaan = bumnuResolveCompany($db);
    if (!$perusahaan) {
        $error = 'Data perusahaan Kas BUMNU belum ditemukan di sistem.';
    } else {
        $id_perusahaan = (int) $perusahaan['id'];
        $kasAccounts = bumnuGetKasAccounts($db, $id_perusahaan);
        $kasIds = array_map(static fn ($a) => (int) $a['id'], $kasAccounts);

        $saldo_awal = bumnuTotalSaldoKas($db, $kasAccounts, $hari_sebelum_awal, $id_perusahaan);
        $saldo_akhir = bumnuTotalSaldoKas($db, $kasAccounts, $tanggal_akhir, $id_perusahaan);
        $rekening = bumnuSaldoPerRekening($db, $kasAccounts, $tanggal_akhir, $id_perusahaan);
        $mutasi = bumnuKasMutasiPeriode($db, $kasIds, $id_perusahaan, $tanggal_awal, $tanggal_akhir, null);
        $tag_options = bumnuKasTagsInPeriode($db, $kasIds, $id_perusahaan, $tanggal_awal, $tanggal_akhir);
        $tx_count_all = bumnuKasTransaksiCount($db, $kasIds, $id_perusahaan, $tanggal_awal, $tanggal_akhir, null);
        $tx_count = bumnuKasTransaksiCount($db, $kasIds, $id_perusahaan, $tanggal_awal, $tanggal_akhir, $tag_filter);
        $tx_fetch = bumnuPublicTransaksiFetchLimit($config, $periode, $tx_count);
        $transaksi = bumnuKasTransaksiList(
            $db,
            $kasIds,
            $id_perusahaan,
            $tanggal_awal,
            $tanggal_akhir,
            $tx_fetch['limit'],
            $tx_fetch['order'],
            $tag_filter
        );

        if ($tag_filter !== null) {
            foreach ($tag_options as $opt) {
                if ($opt['key'] === $tag_filter || ($tag_filter !== '__kosong__' && $opt['key'] === $tag_filter)) {
                    $tag_filter_label = $opt['label'];
                    break;
                }
            }
            if ($tag_filter_label === null) {
                $tag_filter_label = $tag_filter === '__kosong__' ? '(Tanpa tag)' : $tag_filter;
            }
        }

        if (!empty($perusahaan['logo']) && is_file(__DIR__ . '/../' . $perusahaan['logo'])) {
            $logo_url = '/' . ltrim($perusahaan['logo'], '/');
        }
    }
} catch (Throwable $e) {
    $error = 'Gagal memuat data laporan. Pastikan database dan file aplikasi sudah lengkap.';
}

$net_periode = $mutasi['masuk'] - $mutasi['keluar'];
$generated_at = date('d/m/Y H:i');
$tahun_min = 2024;
$tahun_max = (int) date('Y');
$tahun_pilih = (int) ($filter_form['tahun'] ?? date('Y'));

$presets = [
    'bulan_ini' => 'Bulan ini',
    'bulan_lalu' => 'Bulan lalu',
    'kuartal_ini' => 'Kuartal berjalan',
    'tahun_ini' => 'Tahun ini',
    '12_bulan' => '12 bulan',
    'semua' => 'Semua data',
];
$active_preset = ($filter_mode === 'preset' && isset($filter_form['preset'])) ? $filter_form['preset'] : '';
$tx_fetch = $tx_fetch ?? ['limit' => 120, 'order' => 'desc', 'full_list' => false, 'cap' => 500];
$period_query = bumnuPublicPeriodQueryParams($periode);
$tag_query_param = static function (?string $filter): array {
    if ($filter === null) {
        return [];
    }

    return ['tag' => $filter === '__kosong__' ? '__kosong__' : $filter];
};

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Laporan kas BUMNU PCNU Kabupaten Magelang — ringkasan untuk mitra dan masyarakat.">
    <title><?= htmlspecialchars($config['page_title'] ?? 'Laporan Kas BUMNU') ?> — <?= htmlspecialchars($periode_label) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/publik-bumnu-kas.css?v=<?= (int) $cssVer ?>">
</head>
<body class="bumnu-publik">
<div class="bumnu-shell">

    <header class="bumnu-top">
        <div class="bumnu-brand">
            <?php if ($logo_url): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="" class="bumnu-logo" width="56" height="56">
            <?php else: ?>
                <span class="bumnu-logo-fallback" aria-hidden="true">BNU</span>
            <?php endif; ?>
            <div>
                <h1><?= htmlspecialchars($config['page_title'] ?? 'Laporan Kas BUMNU') ?></h1>
                <p><?= htmlspecialchars($config['org_line'] ?? '') ?></p>
            </div>
        </div>
        <p class="bumnu-updated">Diperbarui <?= htmlspecialchars($generated_at) ?> WIB</p>
    </header>

    <section class="bumnu-filter" aria-labelledby="filter-heading">
        <h2 id="filter-heading" class="bumnu-filter-title">Periode laporan</h2>

        <nav class="bumnu-presets" aria-label="Periode cepat">
            <?php foreach ($presets as $key => $label): ?>
                <?php
                $qs = bumnuPublicQueryString(array_merge(['mode' => 'preset', 'preset' => $key], $tag_query_param($tag_filter)), $token_hidden);
                $isActive = $active_preset === $key;
                ?>
                <a class="bumnu-preset<?= $isActive ? ' is-active' : '' ?>" href="?<?= htmlspecialchars($qs) ?>"><?= htmlspecialchars($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <details class="bumnu-filter-advanced" <?= in_array($filter_mode, ['bulan', 'triwulan', 'semester', 'tahun', 'rentang'], true) ? 'open' : '' ?>>
            <summary>Atur periode manual</summary>
            <form class="bumnu-filter-form" method="get" action="">
                <?php if ($token_hidden !== ''): ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token_hidden) ?>">
                <?php endif; ?>
                <?php if ($tag_filter !== null): ?>
                    <input type="hidden" name="tag" value="<?= htmlspecialchars($tag_filter === '__kosong__' ? '__kosong__' : $tag_filter) ?>">
                <?php endif; ?>

                <div class="bumnu-field">
                    <label for="mode">Jenis periode</label>
                    <select id="mode" name="mode" data-bumnu-mode-select>
                        <option value="bulan"<?= $filter_mode === 'bulan' ? ' selected' : '' ?>>Per bulan</option>
                        <option value="triwulan"<?= $filter_mode === 'triwulan' ? ' selected' : '' ?>>Triwulan (3 bulan)</option>
                        <option value="semester"<?= $filter_mode === 'semester' ? ' selected' : '' ?>>Semester</option>
                        <option value="tahun"<?= $filter_mode === 'tahun' ? ' selected' : '' ?>>Tahun penuh</option>
                        <option value="rentang"<?= $filter_mode === 'rentang' ? ' selected' : '' ?>>Tanggal mulai – selesai</option>
                    </select>
                </div>

                <div class="bumnu-mode-panel" data-mode-panel="bulan"<?= $filter_mode === 'bulan' ? '' : ' hidden' ?>>
                    <div class="bumnu-field">
                        <label for="bulan">Bulan</label>
                        <input type="month" id="bulan" name="bulan" value="<?= htmlspecialchars($filter_form['bulan'] ?? date('Y-m')) ?>" max="<?= date('Y-m') ?>">
                    </div>
                </div>

                <div class="bumnu-mode-panel" data-mode-panel="triwulan"<?= $filter_mode === 'triwulan' ? '' : ' hidden' ?>>
                    <div class="bumnu-field-row">
                        <div class="bumnu-field">
                            <label for="triwulan">Triwulan</label>
                            <select id="triwulan" name="triwulan">
                                <?php for ($q = 1; $q <= 4; $q++): ?>
                                    <option value="<?= $q ?>"<?= (int) ($filter_form['triwulan'] ?? 0) === $q ? ' selected' : '' ?>>TW <?= $q ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="bumnu-field">
                            <label for="tahun-tw">Tahun</label>
                            <select id="tahun-tw" name="tahun">
                                <?php for ($y = $tahun_max; $y >= $tahun_min; $y--): ?>
                                    <option value="<?= $y ?>"<?= $tahun_pilih === $y ? ' selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bumnu-mode-panel" data-mode-panel="semester"<?= $filter_mode === 'semester' ? '' : ' hidden' ?>>
                    <div class="bumnu-field-row">
                        <div class="bumnu-field">
                            <label for="semester">Semester</label>
                            <select id="semester" name="semester">
                                <option value="1"<?= (int) ($filter_form['semester'] ?? 0) === 1 ? ' selected' : '' ?>>Semester 1 (Jan–Jun)</option>
                                <option value="2"<?= (int) ($filter_form['semester'] ?? 0) === 2 ? ' selected' : '' ?>>Semester 2 (Jul–Des)</option>
                            </select>
                        </div>
                        <div class="bumnu-field">
                            <label for="tahun-sem">Tahun</label>
                            <select id="tahun-sem" name="tahun">
                                <?php for ($y = $tahun_max; $y >= $tahun_min; $y--): ?>
                                    <option value="<?= $y ?>"<?= $tahun_pilih === $y ? ' selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="bumnu-mode-panel" data-mode-panel="tahun"<?= $filter_mode === 'tahun' ? '' : ' hidden' ?>>
                    <div class="bumnu-field">
                        <label for="tahun-full">Tahun</label>
                        <select id="tahun-full" name="tahun">
                            <?php for ($y = $tahun_max; $y >= $tahun_min; $y--): ?>
                                <option value="<?= $y ?>"<?= $tahun_pilih === $y ? ' selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="bumnu-mode-panel" data-mode-panel="rentang"<?= $filter_mode === 'rentang' ? '' : ' hidden' ?>>
                    <div class="bumnu-field-row">
                        <div class="bumnu-field">
                            <label for="dari">Dari tanggal</label>
                            <input type="date" id="dari" name="dari" value="<?= htmlspecialchars($filter_form['dari'] ?? $tanggal_awal) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="bumnu-field">
                            <label for="sampai">Sampai tanggal</label>
                            <input type="date" id="sampai" name="sampai" value="<?= htmlspecialchars($filter_form['sampai'] ?? $tanggal_akhir) ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>

                <div class="bumnu-filter-actions">
                    <button type="submit" class="bumnu-btn-primary">Terapkan</button>
                    <?php
                    $resetQs = bumnuPublicQueryString(['mode' => 'preset', 'preset' => 'bulan_ini'], $token_hidden);
                    ?>
                    <a class="bumnu-btn-ghost" href="?<?= htmlspecialchars($resetQs) ?>">Reset</a>
                </div>
            </form>
        </details>

        <p class="bumnu-period-active">
            <span class="bumnu-period-label">Menampilkan</span>
            <strong><?= htmlspecialchars($periode_label) ?></strong>
            <span class="bumnu-period-range"><?= htmlspecialchars(bumnuFormatTanggalIndonesia($tanggal_awal)) ?> – <?= htmlspecialchars(bumnuFormatTanggalIndonesia($tanggal_akhir)) ?></span>
        </p>

        <?php if (!$error && $tag_options !== []): ?>
        <form class="bumnu-tag-form" method="get" action="">
            <?php if ($token_hidden !== ''): ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token_hidden) ?>">
            <?php endif; ?>
            <?php foreach ($period_query as $pk => $pv): ?>
                <input type="hidden" name="<?= htmlspecialchars($pk) ?>" value="<?= htmlspecialchars((string) $pv) ?>">
            <?php endforeach; ?>
            <div class="bumnu-field bumnu-field-tag">
                <label for="tag">Filter tag transaksi</label>
                <div class="bumnu-tag-row">
                    <select id="tag" name="tag">
                        <option value="__all__"<?= $tag_filter === null ? ' selected' : '' ?>>Semua tag (<?= (int) $tx_count_all ?>)</option>
                        <?php foreach ($tag_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['key']) ?>"<?= $tag_filter === $opt['key'] ? ' selected' : '' ?>>
                                <?= htmlspecialchars($opt['label']) ?> (<?= (int) $opt['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="bumnu-btn-primary">Filter</button>
                </div>
            </div>
            <?php if ($tag_filter !== null): ?>
                <?php
                $clearTagQs = bumnuPublicQueryString(array_merge($period_query, ['tag' => '__all__']), $token_hidden);
                ?>
                <p class="bumnu-tag-active">Tag aktif: <strong><?= htmlspecialchars($tag_filter_label ?? '') ?></strong>
                    · <a href="?<?= htmlspecialchars($clearTagQs) ?>">Hapus filter tag</a></p>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </section>

    <?php if ($error): ?>
        <p class="bumnu-alert"><?= htmlspecialchars($error) ?></p>
    <?php else: ?>

        <section class="bumnu-hero" aria-labelledby="saldo-heading">
            <div class="bumnu-hero-text">
                <h2 id="saldo-heading">Saldo kas &amp; bank</h2>
                <p>Posisi uang tersedia per <strong><?= htmlspecialchars(bumnuFormatTanggalIndonesia($tanggal_akhir)) ?></strong></p>
            </div>
            <p class="bumnu-hero-amount"><?= htmlspecialchars(formatRupiah($saldo_akhir)) ?></p>
        </section>

        <div class="bumnu-flow" role="group" aria-label="Pergerakan saldo periode">
            <article class="bumnu-flow-item">
                <span class="bumnu-flow-k">Saldo awal periode</span>
                <span class="bumnu-flow-v"><?= htmlspecialchars(formatRupiah($saldo_awal)) ?></span>
            </article>
            <span class="bumnu-flow-op" aria-hidden="true">+</span>
            <article class="bumnu-flow-item is-in">
                <span class="bumnu-flow-k">Uang masuk</span>
                <span class="bumnu-flow-v"><?= htmlspecialchars(formatRupiah($mutasi['masuk'])) ?></span>
            </article>
            <span class="bumnu-flow-op" aria-hidden="true">−</span>
            <article class="bumnu-flow-item is-out">
                <span class="bumnu-flow-k">Uang keluar</span>
                <span class="bumnu-flow-v"><?= htmlspecialchars(formatRupiah($mutasi['keluar'])) ?></span>
            </article>
            <span class="bumnu-flow-op" aria-hidden="true">=</span>
            <article class="bumnu-flow-item is-result">
                <span class="bumnu-flow-k">Saldo akhir</span>
                <span class="bumnu-flow-v"><?= htmlspecialchars(formatRupiah($saldo_akhir)) ?></span>
            </article>
        </div>
        <p class="bumnu-flow-note">Netto periode (masuk − keluar): <strong><?= htmlspecialchars(formatRupiah($net_periode)) ?></strong>
            · <?= (int) $tx_count_all ?> transaksi kas<?= $tag_filter !== null ? ' · riwayat difilter: ' . (int) $tx_count : '' ?></p>

        <?php if ($rekening !== []): ?>
        <section class="bumnu-block">
            <header class="bumnu-block-head">
                <h3>Per rekening</h3>
                <p>Saldo akhir masing-masing rekening kas/bank.</p>
            </header>
            <ul class="bumnu-rek-list">
                <?php foreach ($rekening as $r): ?>
                <li>
                    <span><?= htmlspecialchars($r['nama']) ?></span>
                    <strong><?= htmlspecialchars(formatRupiah($r['saldo'])) ?></strong>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if ($mutasi['by_jenis'] !== []): ?>
        <section class="bumnu-block">
            <header class="bumnu-block-head">
                <h3>Menurut jenis transaksi</h3>
                <p>Hanya transaksi yang memengaruhi kas/rekening.</p>
            </header>
            <div class="bumnu-table-wrap">
                <table class="bumnu-table">
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th class="num">Masuk</th>
                            <th class="num">Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mutasi['by_jenis'] as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['label']) ?></td>
                            <td class="num is-in"><?= $row['masuk'] > 0 ? htmlspecialchars(formatRupiah($row['masuk'])) : '—' ?></td>
                            <td class="num is-out"><?= $row['keluar'] > 0 ? htmlspecialchars(formatRupiah($row['keluar'])) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <section class="bumnu-block">
            <header class="bumnu-block-head">
                <h3>Riwayat transaksi</h3>
                <?php if ($tag_filter !== null): ?>
                    <p>Tag: <strong><?= htmlspecialchars($tag_filter_label ?? '') ?></strong> · urutan terbaru → terlama</p>
                <?php elseif (!empty($tx_fetch['full_list'])): ?>
                    <p>Urutan terbaru → terlama · semua <?= (int) $tx_count ?> transaksi periode ini</p>
                <?php else: ?>
                    <p>Urutan terbaru · maks. <?= (int) ($config['max_transaksi_rows'] ?? 120) ?> baris (pilih preset <strong>Semua data</strong> untuk riwayat lengkap)</p>
                <?php endif; ?>
            </header>
            <?php if ($transaksi === []): ?>
                <p class="bumnu-empty">Tidak ada pergerakan kas pada periode ini.</p>
            <?php else: ?>
            <div class="bumnu-tx-list">
                <?php foreach ($transaksi as $t): ?>
                <article class="bumnu-tx">
                    <div class="bumnu-tx-meta">
                        <time datetime="<?= htmlspecialchars($t['tanggal']) ?>"><?= htmlspecialchars(bumnuFormatTanggalIndonesia($t['tanggal'])) ?></time>
                        <span class="bumnu-tx-tag"><?= htmlspecialchars($t['jenis_label']) ?></span>
                        <?php if (trim((string) ($t['tag'] ?? '')) !== ''): ?>
                            <span class="bumnu-tx-tag is-label"><?= htmlspecialchars(trim((string) $t['tag'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="bumnu-tx-desc"><?= htmlspecialchars($t['keterangan']) ?></p>
                    <p class="bumnu-tx-amt <?= $t['arah'] === 'masuk' ? 'is-in' : 'is-out' ?>">
                        <?= $t['arah'] === 'masuk' ? '+' : '−' ?> <?= htmlspecialchars(formatRupiah($t['total'])) ?>
                    </p>
                </article>
                <?php endforeach; ?>
            </div>
            <?php if ($tx_count > count($transaksi)): ?>
                <p class="bumnu-footnote">Menampilkan <?= count($transaksi) ?> dari <?= (int) $tx_count ?> transaksi (batas <?= (int) $tx_fetch['cap'] ?> baris). Hubungi pengurus untuk ekspor lengkap.</p>
            <?php elseif ($tx_count > 0 && count($transaksi) === $tx_count): ?>
                <p class="bumnu-footnote"><?= (int) $tx_count ?> transaksi ditampilkan.</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>

    <?php endif; ?>

    <footer class="bumnu-foot">
        <p><?= htmlspecialchars($config['footnote'] ?? '') ?></p>
        <p>Sumber: sistem pelaporan keuangan internal BUMNU.</p>
    </footer>

</div>
<script>
(function () {
    var sel = document.querySelector('[data-bumnu-mode-select]');
    if (!sel) return;
    var panels = document.querySelectorAll('[data-mode-panel]');
    function sync() {
        var mode = sel.value;
        panels.forEach(function (p) {
            var show = p.getAttribute('data-mode-panel') === mode;
            p.hidden = !show;
            p.querySelectorAll('input, select').forEach(function (el) {
                el.disabled = !show;
            });
        });
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
</body>
</html>
