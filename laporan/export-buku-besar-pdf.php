<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$id_akun = isset($_GET['id_akun']) ? (int)$_GET['id_akun'] : 0;
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-01-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-12-31');

if ($id_akun <= 0) {
    $_SESSION['error'] = 'Pilih akun terlebih dahulu.';
    header('Location: buku-besar.php');
    exit();
}

$filter_perusahaan = getReportFilterPerusahaan(
    $db,
    $user_id,
    isset($_GET['perusahaan']) ? (int)$_GET['perusahaan'] : null
);

$buku_besar = getBukuBesarData($db, $id_akun, $tanggal_awal, $tanggal_akhir, $filter_perusahaan);
if (!$buku_besar) {
    $_SESSION['error'] = 'Data buku besar tidak ditemukan.';
    header('Location: buku-besar.php');
    exit();
}

$stmt_perusahaan = $db->prepare('SELECT nama FROM perusahaan WHERE id = ?');
$stmt_perusahaan->execute([$filter_perusahaan]);
$nama_perusahaan = $stmt_perusahaan->fetchColumn() ?: '-';

$akun = $buku_besar['akun'];
$filename = 'Buku_Besar_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $akun['kode_akun']) . '_' . date('Ymd');

$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Buku Besar</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { margin-bottom: 16px; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f0f0f0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { font-weight: bold; background: #eef3ff; }
        .footer { margin-top: 16px; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <h1>BUKU BESAR</h1>
    <div class="meta">
        <div><strong>Perusahaan:</strong> ' . htmlspecialchars($nama_perusahaan) . '</div>
        <div><strong>Akun:</strong> ' . htmlspecialchars($akun['kode_akun'] . ' - ' . $akun['nama_akun']) . '</div>
        <div><strong>Periode:</strong> ' . date('d/m/Y', strtotime($tanggal_awal)) . ' s/d ' . date('d/m/Y', strtotime($tanggal_akhir)) . '</div>
        <div><strong>Saldo Awal:</strong> ' . formatRupiah($buku_besar['saldo_awal']) . '</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>No. Ref</th>
                <th>Keterangan</th>
                <th>Akun Lawan</th>
                <th>Jenis</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>' . date('d/m/Y', strtotime($tanggal_awal)) . '</td>
                <td>-</td>
                <td><em>Saldo Awal</em></td>
                <td>-</td>
                <td>-</td>
                <td class="text-right">-</td>
                <td class="text-right">-</td>
                <td class="text-right">' . formatRupiah($buku_besar['saldo_awal']) . '</td>
            </tr>';

if (empty($buku_besar['rows'])) {
    $html .= '<tr><td colspan="8" class="text-center">Tidak ada mutasi pada periode ini.</td></tr>';
} else {
    foreach ($buku_besar['rows'] as $row) {
        $html .= '<tr>
            <td>' . date('d/m/Y', strtotime($row['tanggal'])) . '</td>
            <td>#' . (int)$row['transaksi_id'] . '</td>
            <td>' . htmlspecialchars($row['keterangan']) . '</td>
            <td>' . htmlspecialchars($row['lawan_kode'] . ' - ' . $row['lawan_nama']) . '</td>
            <td>' . htmlspecialchars($row['jenis']) . '</td>
            <td class="text-right">' . ($row['debit'] > 0 ? formatRupiah($row['debit']) : '-') . '</td>
            <td class="text-right">' . ($row['kredit'] > 0 ? formatRupiah($row['kredit']) : '-') . '</td>
            <td class="text-right">' . formatRupiah($row['saldo']) . '</td>
        </tr>';
    }
}

$html .= '<tr class="summary">
            <td colspan="5" class="text-right">TOTAL MUTASI PERIODE</td>
            <td class="text-right">' . formatRupiah($buku_besar['total_debit']) . '</td>
            <td class="text-right">' . formatRupiah($buku_besar['total_kredit']) . '</td>
            <td class="text-right">' . formatRupiah($buku_besar['saldo_akhir']) . '</td>
        </tr>
        </tbody>
    </table>
    <div class="footer">Dicetak pada: ' . date('d/m/Y H:i:s') . '</div>
</body>
</html>';

require_once '../vendor/autoload.php';

if (!class_exists('\Mpdf\Mpdf')) {
    echo '<div style="padding:20px;background:#f8d7da;color:#721c24;">Library mPDF belum terinstall. Jalankan: composer require mpdf/mpdf</div>';
    exit;
}

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);

    $mpdf->SetTitle('Buku Besar');
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
} catch (\Mpdf\MpdfException $e) {
    echo '<div style="padding:20px;background:#f8d7da;color:#721c24;">Error PDF: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
