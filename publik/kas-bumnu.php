<?php

declare(strict_types=1);

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

$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = date('Y-m');
}

$tanggal_awal = $bulan . '-01';
$tanggal_akhir = date('Y-m-t', strtotime($tanggal_awal));
$hari_sebelum_awal = date('Y-m-d', strtotime($tanggal_awal . ' -1 day'));

$token_hidden = '';
if (!empty($config['access_token'])) {
    $token_hidden = (string) ($_GET['token'] ?? '');
}

$perusahaan = null;
$error = null;
$saldo_akhir = 0.0;
$saldo_awal = 0.0;
$mutasi = ['masuk' => 0.0, 'keluar' => 0.0, 'by_jenis' => []];
$rekening = [];
$transaksi = [];
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
        $mutasi = bumnuKasMutasiPeriode($db, $kasIds, $id_perusahaan, $tanggal_awal, $tanggal_akhir);
        $transaksi = bumnuKasTransaksiList(
            $db,
            $kasIds,
            $id_perusahaan,
            $tanggal_awal,
            $tanggal_akhir,
            (int) ($config['max_transaksi_rows'] ?? 120)
        );

        if (!empty($perusahaan['logo']) && is_file(__DIR__ . '/../' . $perusahaan['logo'])) {
            $logo_url = '/' . ltrim($perusahaan['logo'], '/');
        }
    }
} catch (Throwable $e) {
    $error = 'Gagal memuat data laporan. Pastikan database dan file aplikasi sudah lengkap.';
}

$bulan_label = bumnuFormatBulanIndonesia($bulan);
$generated_at = date('d/m/Y H:i');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Laporan kas BUMNU PCNU Kabupaten Magelang — ringkasan untuk mitra dan masyarakat.">
    <title><?= htmlspecialchars($config['page_title'] ?? 'Laporan Kas BUMNU') ?> — <?= htmlspecialchars($bulan_label) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/publik-bumnu-kas.css?v=<?= (int) $cssVer ?>">
</head>
<body class="bumnu-publik">
<div class="bumnu-wrap">

    <header class="bumnu-masthead">
        <?php if ($logo_url): ?>
            <img src="<?= htmlspecialchars($logo_url) ?>" alt="" class="bumnu-logo" width="72" height="72">
        <?php endif; ?>
        <div>
            <h1><?= htmlspecialchars($config['page_title'] ?? 'Laporan Kas BUMNU') ?></h1>
            <p class="bumnu-org"><?= htmlspecialchars($config['org_line'] ?? '') ?></p>
        </div>
    </header>

    <form class="bumnu-period-form" method="get" action="">
        <?php if ($token_hidden !== ''): ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token_hidden) ?>">
        <?php endif; ?>
        <div>
            <label for="bulan">Periode laporan</label>
            <input type="month" id="bulan" name="bulan" value="<?= htmlspecialchars($bulan) ?>" max="<?= date('Y-m') ?>">
        </div>
        <button type="submit">Tampilkan</button>
    </form>

    <?php if ($error): ?>
        <p class="bumnu-empty"><?= htmlspecialchars($error) ?></p>
    <?php else: ?>

        <section class="bumnu-lead" aria-labelledby="judul-ringkasan">
            <p id="judul-ringkasan">Periode: <strong><?= htmlspecialchars($bulan_label) ?></strong></p>
            <p class="bumnu-big"><?= htmlspecialchars(formatRupiah($saldo_akhir)) ?></p>
            <p>Total uang tersedia di kas dan rekening bank BUMNU per akhir periode.</p>
        </section>

        <dl class="bumnu-stats">
            <div class="bumnu-stat">
                <dt>Saldo awal bulan</dt>
                <dd><?= htmlspecialchars(formatRupiah($saldo_awal)) ?></dd>
            </div>
            <div class="bumnu-stat">
                <dt>Uang masuk (periode ini)</dt>
                <dd class="bumnu-masuk">+ <?= htmlspecialchars(formatRupiah($mutasi['masuk'])) ?></dd>
            </div>
            <div class="bumnu-stat">
                <dt>Uang keluar (periode ini)</dt>
                <dd class="bumnu-keluar">− <?= htmlspecialchars(formatRupiah($mutasi['keluar'])) ?></dd>
            </div>
        </dl>

        <?php if ($rekening !== []): ?>
        <section class="bumnu-section">
            <h2>Posisi per rekening</h2>
            <p class="bumnu-note">Rincian saldo di setiap tempat penyimpanan uang.</p>
            <table class="bumnu-table">
                <thead>
                    <tr>
                        <th>Rekening / kas</th>
                        <th class="num">Saldo akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekening as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['nama']) ?></td>
                        <td class="num"><?= htmlspecialchars(formatRupiah($r['saldo'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <?php if ($mutasi['by_jenis'] !== []): ?>
        <section class="bumnu-section">
            <h2>Ringkasan menurut jenis transaksi</h2>
            <p class="bumnu-note">Pengelompokan sederhana agar mudah dibaca; angka hanya yang mempengaruhi kas.</p>
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
                        <td class="num bumnu-masuk"><?= $row['masuk'] > 0 ? htmlspecialchars(formatRupiah($row['masuk'])) : '—' ?></td>
                        <td class="num bumnu-keluar"><?= $row['keluar'] > 0 ? htmlspecialchars(formatRupiah($row['keluar'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <section class="bumnu-section">
            <h2>Riwayat transaksi kas</h2>
            <p class="bumnu-note">Daftar pergerakan uang yang tercatat masuk atau keluar dari kas/rekening BUMNU.</p>
            <?php if ($transaksi === []): ?>
                <p class="bumnu-empty">Belum ada transaksi kas pada periode ini.</p>
            <?php else: ?>
            <table class="bumnu-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Uraian</th>
                        <th>Jenis</th>
                        <th class="num">Masuk</th>
                        <th class="num">Keluar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transaksi as $t): ?>
                    <tr>
                        <td><?= htmlspecialchars(bumnuFormatTanggalIndonesia($t['tanggal'])) ?></td>
                        <td><?= htmlspecialchars($t['keterangan']) ?></td>
                        <td><?= htmlspecialchars($t['jenis_label']) ?></td>
                        <td class="num bumnu-masuk"><?= $t['arah'] === 'masuk' ? htmlspecialchars(formatRupiah($t['total'])) : '—' ?></td>
                        <td class="num bumnu-keluar"><?= $t['arah'] === 'keluar' ? htmlspecialchars(formatRupiah($t['total'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($transaksi) >= (int) ($config['max_transaksi_rows'] ?? 120)): ?>
                <p class="bumnu-note">Menampilkan <?= (int) $config['max_transaksi_rows'] ?> transaksi terbaru. Untuk periode penuh, hubungi pengurus.</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>

    <?php endif; ?>

    <footer class="bumnu-foot">
        <p><?= htmlspecialchars($config['footnote'] ?? '') ?></p>
        <p>Halaman dimuat: <?= htmlspecialchars($generated_at) ?> WIB · Sumber: sistem pelaporan keuangan internal.</p>
    </footer>

</div>
</body>
</html>
