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

// Set header untuk download file Excel
header('Content-Type: application/vnd.ms-excel');

// Set nama file berdasarkan tipe laporan
if ($tipe == 'jurnal') {
    header('Content-Disposition: attachment;filename="Jurnal_Umum_' . date('Ymd') . '.xls"');
} else {
    header('Content-Disposition: attachment;filename="Laporan_Transaksi_' . date('Ymd') . '.xls"');
}

header('Cache-Control: max-age=0');

// Mulai output Excel
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<?mso-application progid=\"Excel.Sheet\"?>\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
  <Author>Sistem Keuangan</Author>
  <LastAuthor>Sistem Keuangan</LastAuthor>
  <Created><?php echo date(DATE_W3C); ?></Created>
  <Version>16.00</Version>
 </DocumentProperties>
 <OfficeDocumentSettings xmlns="urn:schemas-microsoft-com:office:office">
  <AllowPNG/>
 </OfficeDocumentSettings>
 <ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
  <WindowHeight>7920</WindowHeight>
  <WindowWidth>21570</WindowWidth>
  <WindowTopX>32767</WindowTopX>
  <WindowTopY>32767</WindowTopY>
  <ProtectStructure>False</ProtectStructure>
  <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Bottom"/>
   <Borders/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Header">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/>
   </Borders>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000" ss:Bold="1"/>
   <Interior ss:Color="#D9D9D9" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Title">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="18" ss:Color="#000000" ss:Bold="1"/>
  </Style>
  <Style ss:ID="Pemasukan">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#006100"/>
  </Style>
  <Style ss:ID="Pengeluaran">
   <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FF0000"/>
  </Style>
 </Styles>
 <Worksheet ss:Name="<?php echo ($tipe == 'jurnal' ? 'Jurnal Umum' : 'Laporan Transaksi'); ?>">
  <?php if ($tipe == 'jurnal'): ?>
  <Table ss:ExpandedColumnCount="7" ss:ExpandedRowCount="<?php echo (count($jurnal_list) * 2) + 4; ?>" x:FullColumns="1" x:FullRows="1">
   <Column ss:Width="80"/>
   <Column ss:Width="100"/>
   <Column ss:Width="60"/>
   <Column ss:Width="150"/>
   <Column ss:Width="100"/>
   <Column ss:Width="100"/>
   <Column ss:Width="200"/>
   
   <!-- Judul Laporan -->
   <Row ss:Height="30">
    <Cell ss:MergeAcross="6" ss:StyleID="Title"><Data ss:Type="String">JURNAL UMUM</Data></Cell>
   </Row>
   <Row ss:Height="20">
    <Cell ss:MergeAcross="6" ss:StyleID="Default"><Data ss:Type="String">Periode: <?php echo date('d/m/Y', strtotime($tanggal_awal)); ?> - <?php echo date('d/m/Y', strtotime($tanggal_akhir)); ?></Data></Cell>
   </Row>
   
   <!-- Header Tabel -->
   <Row ss:Height="25">
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Transaksi</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Kode</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Akun</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Debit</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Kredit</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Catatan</Data></Cell>
   </Row>
   
   <!-- Data Jurnal -->
   <?php 
   $current_date = null;
   $current_transaction_id = null;
   foreach ($jurnal_list as $index => $jurnal): 
       $show_date = ($current_date != $jurnal['tanggal_transaksi']);
       $show_transaction = ($current_transaction_id != $jurnal['id']);
       $current_date = $jurnal['tanggal_transaksi'];
       $current_transaction_id = $jurnal['id'];
   ?>
   <!-- Baris untuk akun debit -->
   <Row>
    <?php if ($show_date): ?>
    <Cell ss:MergeDown="1"><Data ss:Type="String"><?php echo date('d/m/Y H:i:s', strtotime($jurnal['tanggal_transaksi'])); ?></Data></Cell>
    <?php else: ?>
    <Cell ss:Index="2"></Cell>
    <?php endif; ?>
    
    <?php if ($show_transaction): ?>
    <Cell ss:MergeDown="1"><Data ss:Type="String"><?php echo ucfirst($jurnal['jenis_transaksi']); ?></Data></Cell>
    <?php else: ?>
    <Cell ss:Index="3"></Cell>
    <?php endif; ?>
    
    <Cell><Data ss:Type="String"><?php echo $jurnal['kode_akun_debit']; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $jurnal['nama_akun_debit']; ?></Data></Cell>
    <Cell><Data ss:Type="Number"><?php echo $jurnal['jumlah']; ?></Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    
    <?php if ($show_transaction): ?>
    <Cell ss:MergeDown="1"><Data ss:Type="String"><?php echo $jurnal['keterangan']; ?></Data></Cell>
    <?php endif; ?>
   </Row>
   
   <!-- Baris untuk akun kredit -->
   <Row>
    <Cell><Data ss:Type="String"><?php echo $jurnal['kode_akun_kredit']; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $jurnal['nama_akun_kredit']; ?></Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
    <Cell><Data ss:Type="Number"><?php echo $jurnal['jumlah']; ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
   
   <!-- Baris total -->
   <Row ss:StyleID="Header">
    <Cell ss:MergeAcross="3"><Data ss:Type="String">TOTAL</Data></Cell>
    <Cell><Data ss:Type="Number"><?php echo $total_debit; ?></Data></Cell>
    <Cell><Data ss:Type="Number"><?php echo $total_kredit; ?></Data></Cell>
    <Cell><Data ss:Type="String"></Data></Cell>
   </Row>
  <?php else: ?>
  <Table ss:ExpandedColumnCount="7" ss:ExpandedRowCount="<?php echo count($transaksi_list) + 3; ?>" x:FullColumns="1" x:FullRows="1">
   <Column ss:Width="30"/>
   <Column ss:Width="80"/>
   <Column ss:Width="100"/>
   <Column ss:Width="200"/>
   <Column ss:Width="100"/>
   <Column ss:Width="80"/>
   <Column ss:Width="100"/>
   
   <!-- Judul Laporan -->
   <Row ss:Height="30">
    <Cell ss:MergeAcross="6" ss:StyleID="Title"><Data ss:Type="String">LAPORAN TRANSAKSI</Data></Cell>
   </Row>
   <Row ss:Height="20">
    <Cell ss:MergeAcross="6" ss:StyleID="Default"><Data ss:Type="String">Periode: <?php echo date('d/m/Y', strtotime($tanggal_awal)); ?> - <?php echo date('d/m/Y', strtotime($tanggal_akhir)); ?></Data></Cell>
   </Row>
   
   <!-- Header Tabel -->
   <Row ss:Height="25">
    <Cell ss:StyleID="Header"><Data ss:Type="String">No</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tanggal</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Transaksi</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Catatan</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Total</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tag</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Penanggung Jawab</Data></Cell>
   </Row>
   
   <!-- Data Transaksi -->
   <?php $no = 1; foreach ($transaksi_list as $transaksi): ?>
   <Row>
    <Cell><Data ss:Type="Number"><?php echo $no++; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo date('d/m/Y', strtotime($transaksi['tanggal'])); ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo ucfirst($transaksi['jenis']); ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $transaksi['keterangan']; ?></Data></Cell>
    <Cell ss:StyleID="<?php echo $transaksi['jenis'] == 'pemasukan' ? 'Pemasukan' : 'Pengeluaran'; ?>"><Data ss:Type="Number"><?php echo $transaksi['jumlah']; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $transaksi['tag'] ?? ''; ?></Data></Cell>
    <Cell><Data ss:Type="String"><?php echo $transaksi['penanggung_jawab'] ?? ''; ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  <?php endif; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <PageSetup>
    <Header x:Margin="0.3"/>
    <Footer x:Margin="0.3"/>
    <PageMargins x:Bottom="0.75" x:Left="0.7" x:Right="0.7" x:Top="0.75"/>
   </PageSetup>
   <Print>
    <ValidPrinterInfo/>
    <PaperSizeIndex>9</PaperSizeIndex>
    <HorizontalResolution>600</HorizontalResolution>
    <VerticalResolution>600</VerticalResolution>
   </Print>
   <Selected/>
   <Panes>
    <Pane>
     <Number>3</Number>
     <ActiveRow>1</ActiveRow>
     <ActiveCol>1</ActiveCol>
    </Pane>
   </Panes>
   <ProtectObjects>False</ProtectObjects>
   <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>