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

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="' . $filename . '.xls"');
header('Cache-Control: max-age=0');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>
  <Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#DDDDDD" ss:Pattern="Solid"/></Style>
  <Style ss:ID="Money"><NumberFormat ss:Format="#,##0"/></Style>
  <Style ss:ID="Bold"><Font ss:Bold="1"/></Style>
 </Styles>
 <Worksheet ss:Name="Buku Besar">
  <Table>
   <Row><Cell ss:StyleID="Title" ss:MergeAcross="7"><Data ss:Type="String">BUKU BESAR</Data></Cell></Row>
   <Row><Cell ss:MergeAcross="7"><Data ss:Type="String">Perusahaan: <?= htmlspecialchars($nama_perusahaan) ?></Data></Cell></Row>
   <Row><Cell ss:MergeAcross="7"><Data ss:Type="String">Akun: <?= htmlspecialchars($akun['kode_akun'] . ' - ' . $akun['nama_akun']) ?></Data></Cell></Row>
   <Row><Cell ss:MergeAcross="7"><Data ss:Type="String">Periode: <?= date('d/m/Y', strtotime($tanggal_awal)) ?> s/d <?= date('d/m/Y', strtotime($tanggal_akhir)) ?></Data></Cell></Row>
   <Row></Row>
   <Row>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">No. Ref</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Keterangan</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Akun Lawan</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Jenis</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Debit</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Kredit</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Saldo</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String"><?= date('d/m/Y', strtotime($tanggal_awal)) ?></Data></Cell>
    <Cell><Data ss:Type="String">-</Data></Cell>
    <Cell><Data ss:Type="String">Saldo Awal</Data></Cell>
    <Cell><Data ss:Type="String">-</Data></Cell>
    <Cell><Data ss:Type="String">-</Data></Cell>
    <Cell></Cell>
    <Cell></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= $buku_besar['saldo_awal'] ?></Data></Cell>
   </Row>
   <?php foreach ($buku_besar['rows'] as $row): ?>
   <Row>
    <Cell><Data ss:Type="String"><?= date('d/m/Y', strtotime($row['tanggal'])) ?></Data></Cell>
    <Cell><Data ss:Type="String">#<?= (int)$row['transaksi_id'] ?></Data></Cell>
    <Cell><Data ss:Type="String"><?= htmlspecialchars($row['keterangan']) ?></Data></Cell>
    <Cell><Data ss:Type="String"><?= htmlspecialchars($row['lawan_kode'] . ' - ' . $row['lawan_nama']) ?></Data></Cell>
    <Cell><Data ss:Type="String"><?= htmlspecialchars($row['jenis']) ?></Data></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= (float)$row['debit'] ?></Data></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= (float)$row['kredit'] ?></Data></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= (float)$row['saldo'] ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
   <Row>
    <Cell ss:StyleID="Bold" ss:MergeAcross="4"><Data ss:Type="String">TOTAL MUTASI PERIODE</Data></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= $buku_besar['total_debit'] ?></Data></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= $buku_besar['total_kredit'] ?></Data></Cell>
    <Cell ss:StyleID="Money"><Data ss:Type="Number"><?= $buku_besar['saldo_akhir'] ?></Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
