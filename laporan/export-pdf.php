<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user sudah login
requireLogin();

// Ambil parameter filter
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-t');
$kontak = isset($_GET['kontak']) ? $_GET['kontak'] : '';
$tag = isset($_GET['tag']) ? $_GET['tag'] : '';
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : '';


// Buat query dasar
$sql = "SELECT t.*, 
        ad.kode_akun as kode_akun_debit, ad.nama_akun as nama_akun_debit,
        ak.kode_akun as kode_akun_kredit, ak.nama_akun as nama_akun_kredit
        FROM transaksi t 
        LEFT JOIN akun ad ON t.id_akun_debit = ad.id
        LEFT JOIN akun ak ON t.id_akun_kredit = ak.id
        WHERE t.tanggal BETWEEN :tanggal_awal AND :tanggal_akhir";

$params = [
    ':tanggal_awal' => $tanggal_awal,
    ':tanggal_akhir' => $tanggal_akhir
];

// Tambahkan filter kontak jika ada
if (!empty($kontak)) {
    $sql .= " AND t.penanggung_jawab = :kontak";
    $params[':kontak'] = $kontak;
}

// Tambahkan filter tag jika ada
if (!empty($tag)) {
    $sql .= " AND t.tag = :tag";
    $params[':tag'] = $tag;
}

// Tambahkan pengurutan
$sql .= " ORDER BY t.tanggal DESC, t.id DESC";

// Eksekusi query
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transaksi_list = $stmt->fetchAll();

// Jika tipe adalah jurnal, gunakan query khusus untuk jurnal umum
if ($tipe == 'jurnal') {
    $sql_jurnal = "SELECT t.*, 
            t.tanggal as tanggal_transaksi,
            t.jenis as jenis_transaksi,
            ad.kode_akun as kode_akun_debit, 
            ad.nama_akun as nama_akun_debit,
            ak.kode_akun as kode_akun_kredit, 
            ak.nama_akun as nama_akun_kredit
            FROM transaksi t 
            LEFT JOIN akun ad ON t.id_akun_debit = ad.id
            LEFT JOIN akun ak ON t.id_akun_kredit = ak.id
            WHERE t.tanggal BETWEEN :tanggal_awal AND :tanggal_akhir
            ORDER BY t.tanggal ASC, t.id ASC";

    $params_jurnal = [
        ':tanggal_awal' => $tanggal_awal,
        ':tanggal_akhir' => $tanggal_akhir
    ];

    $stmt_jurnal = $db->prepare($sql_jurnal);
    $stmt_jurnal->execute($params_jurnal);
    $jurnal_list = $stmt_jurnal->fetchAll();
    
    // Hitung total debit dan kredit
    $total_debit = 0;
    $total_kredit = 0;
    foreach ($jurnal_list as $jurnal) {
        $total_debit += $jurnal['jumlah'];
        $total_kredit += $jurnal['jumlah'];
    }
}

// Hitung total pemasukan dan pengeluaran berdasarkan filter
$sql_total = "SELECT 
    SUM(CASE WHEN jenis = 'pemasukan' THEN jumlah ELSE 0 END) as total_pemasukan,
    SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END) as total_pengeluaran
    FROM transaksi
    WHERE tanggal BETWEEN :tanggal_awal AND :tanggal_akhir";

$params_total = [
    ':tanggal_awal' => $tanggal_awal,
    ':tanggal_akhir' => $tanggal_akhir
];

// Tambahkan filter kontak jika ada
if (!empty($kontak)) {
    $sql_total .= " AND penanggung_jawab = :kontak";
    $params_total[':kontak'] = $kontak;
}

// Tambahkan filter tag jika ada
if (!empty($tag)) {
    $sql_total .= " AND tag = :tag";
    $params_total[':tag'] = $tag;
}

$stmt_total = $db->prepare($sql_total);
$stmt_total->execute($params_total);
$total = $stmt_total->fetch();

// Fungsi untuk mendapatkan kelas badge berdasarkan jenis transaksi
function getJenisBadgeClass($jenis) {
    switch ($jenis) {
        case 'pemasukan':
            return 'success';
        case 'pengeluaran':
            return 'danger';
        case 'transfer':
            return 'primary';
        case 'tarik_modal':
            return 'warning';
        case 'beli_aset':
            return 'info';
        default:
            return 'secondary';
    }
}

// Buat HTML untuk PDF
$html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>' . ($tipe == 'jurnal' ? 'Jurnal Umum' : 'Laporan Transaksi') . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 3px 6px;
            font-size: 10px;
            font-weight: bold;
            color: white;
            border-radius: 3px;
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .badge-primary {
            background-color: #007bff;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-info {
            background-color: #17a2b8;
        }
        .badge-secondary {
            background-color: #6c757d;
        }
        .text-success {
            color: #28a745;
        }
        .text-danger {
            color: #dc3545;
        }
        .summary {
            margin-bottom: 20px;
        }
        .summary-item {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . ($tipe == 'jurnal' ? 'JURNAL UMUM' : 'LAPORAN TRANSAKSI') . '</h1>
        <p>Periode: ' . date('d/m/Y', strtotime($tanggal_awal)) . ' - ' . date('d/m/Y', strtotime($tanggal_akhir)) . '</p>
    </div>
    
    ' . ($tipe != 'jurnal' ? '<div class="summary">
        <div class="summary-item">
            <strong>Total Pemasukan:</strong> ' . formatRupiah($total['total_pemasukan'] ?? 0) . '
        </div>
        <div class="summary-item">
            <strong>Total Pengeluaran:</strong> ' . formatRupiah($total['total_pengeluaran'] ?? 0) . '
        </div>
        <div class="summary-item">
            <strong>Saldo:</strong> ' . formatRupiah(($total['total_pemasukan'] ?? 0) - ($total['total_pengeluaran'] ?? 0)) . '
        </div>
    </div>' : '') . '
    
    <table>
        <thead>
            <tr>' . 
    ($tipe == 'jurnal' ? 
    '
                <th>Tanggal</th>
                <th>Transaksi</th>
                <th>Kode</th>
                <th>Akun</th>
                <th>Debit</th>
                <th>Kredit</th>
                <th>Catatan</th>' 
    : 
    '
                <th>No</th>
                <th>Tanggal</th>
                <th>Transaksi</th>
                <th>Catatan</th>
                <th>Total</th>
                <th>Tag</th>
                <th>Penanggung Jawab</th>'
    ) . '
            </tr>
        </thead>
        <tbody>';

// Tampilkan data berdasarkan tipe laporan
if ($tipe == 'jurnal') {
    // Tampilkan data jurnal umum
    $current_date = null;
    $current_transaction_id = null;
    
    if (empty($jurnal_list)) {
        $html .= '<tr><td colspan="7" class="text-center">Tidak ada data jurnal</td></tr>';
    } else {
        foreach ($jurnal_list as $index => $jurnal) {
            $show_date = ($current_date != $jurnal['tanggal_transaksi']);
            $show_transaction = ($current_transaction_id != $jurnal['id']);
            $current_date = $jurnal['tanggal_transaksi'];
            $current_transaction_id = $jurnal['id'];
            $jenisBadge = getJenisBadgeClass($jurnal['jenis_transaksi']);
            
            // Baris untuk akun debit
            $html .= '<tr>';
            if ($show_date) {
                $html .= '<td rowspan="2">' . date('d M Y', strtotime($jurnal['tanggal_transaksi'])) . '<br>
                    <small>' . date('H:i:s', strtotime($jurnal['tanggal_transaksi'])) . '</small></td>';
            }
            
            if ($show_transaction) {
                $html .= '<td rowspan="2"><span class="badge badge-' . $jenisBadge . '">' . ucfirst($jurnal['jenis_transaksi']) . '</span></td>';
            }
            
            $html .= '<td>' . $jurnal['kode_akun_debit'] . '</td>
                <td>' . $jurnal['nama_akun_debit'] . '</td>
                <td class="text-right">' . formatRupiah($jurnal['jumlah']) . '</td>
                <td></td>';
            
            if ($show_transaction) {
                $html .= '<td rowspan="2">' . htmlspecialchars($jurnal['keterangan']) . '</td>';
            }
            
            $html .= '</tr>';
            
            // Baris untuk akun kredit
            $html .= '<tr>';
            $html .= '<td>' . $jurnal['kode_akun_kredit'] . '</td>
                <td>' . $jurnal['nama_akun_kredit'] . '</td>
                <td></td>
                <td class="text-right">' . formatRupiah($jurnal['jumlah']) . '</td>';
            $html .= '</tr>';
        }
        
        // Baris total
        $html .= '<tr style="background-color: #f2f2f2; font-weight: bold;">
            <td colspan="4" class="text-right">TOTAL</td>
            <td class="text-right">' . formatRupiah($total_debit) . '</td>
            <td class="text-right">' . formatRupiah($total_kredit) . '</td>
            <td></td>
        </tr>';
    }
} else {
    // Tampilkan data transaksi biasa
    $no = 1;
    foreach ($transaksi_list as $transaksi) {
        $jenisBadge = getJenisBadgeClass($transaksi['jenis']);
        $html .= '<tr>
            <td class="text-center">' . $no++ . '</td>
            <td>' . date('d M Y', strtotime($transaksi['tanggal'])) . '<br>
                <small>' . date('H:i:s', strtotime($transaksi['tanggal'])) . '</small>
            </td>
            <td><span class="badge badge-' . $jenisBadge . '">' . ucfirst($transaksi['jenis']) . '</span></td>
            <td>' . htmlspecialchars($transaksi['keterangan']) . '</td>
            <td class="text-right ' . ($transaksi['jenis'] == 'pemasukan' ? 'text-success' : 'text-danger') . '">' . formatRupiah($transaksi['jumlah']) . '</td>
            <td>' . (!empty($transaksi['tag']) ? $transaksi['tag'] : '') . '</td>
            <td>' . (!empty($transaksi['penanggung_jawab']) ? $transaksi['penanggung_jawab'] : '') . '</td>
        </tr>';
    }

    if (empty($transaksi_list)) {
        $html .= '<tr><td colspan="7" class="text-center">Tidak ada data transaksi</td></tr>';
    }
}

$html .= '</tbody>
    </table>
    
    <div class="footer">
        <p>Dicetak pada: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

// Gunakan mPDF untuk membuat PDF
require_once '../vendor/autoload.php';

// Cek apakah mPDF tersedia, jika tidak, tampilkan pesan error
if (!class_exists('\Mpdf\Mpdf')) {
    echo '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">
            <h3>Error: mPDF Library Not Found</h3>
            <p>Untuk menggunakan fitur ekspor PDF, Anda perlu menginstal library mPDF.</p>
            <p>Silakan jalankan perintah berikut di terminal:</p>
            <pre>composer require mpdf/mpdf</pre>
            <p>Atau download library secara manual dari <a href="https://github.com/mpdf/mpdf" target="_blank">https://github.com/mpdf/mpdf</a></p>
          </div>';
    exit;
}

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 15,
        'margin_bottom' => 15,
    ]);
    
    $mpdf->SetTitle('Laporan Transaksi');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Laporan_Transaksi_' . date('Ymd') . '.pdf', 'D');
} catch (\Mpdf\MpdfException $e) {
    echo '<div style="padding: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">
            <h3>Error: ' . $e->getMessage() . '</h3>
            <p>Terjadi kesalahan saat membuat file PDF.</p>
          </div>';
}
?>