<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user sudah login
requireLogin();

// Set default tanggal jika tidak ada filter
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-t');

// Ambil id_perusahaan dari default_company pengguna
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

// Pastikan pengguna memiliki perusahaan default
if (!$id_perusahaan) {
    $_SESSION['error'] = 'Anda belum memiliki perusahaan default. Silakan tambahkan perusahaan terlebih dahulu.';
    header('Location: ../pengaturan/perusahaan.php');
    exit();
}

// Buat query untuk mengambil data jurnal dengan filter perusahaan
$sql = "SELECT t.*, 
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
        AND t.id_perusahaan = :id_perusahaan
        ORDER BY t.tanggal ASC, t.id ASC";

$params = [
    ':tanggal_awal' => $tanggal_awal,
    ':tanggal_akhir' => $tanggal_akhir,
    ':id_perusahaan' => $id_perusahaan
];

// Eksekusi query
$stmt = $db->prepare($sql);
$stmt->execute($params);
$jurnal_list = $stmt->fetchAll();

// Hitung total debit dan kredit
$total_debit = 0;
$total_kredit = 0;
foreach ($jurnal_list as $jurnal) {
    $total_debit += $jurnal['jumlah'];
    $total_kredit += $jurnal['jumlah'];
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Jurnal Umum</h2>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                </div>
                <div class="col-md-4">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Jurnal -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Jurnal Umum</h5>
            <div>
                <a href="export-excel.php?tanggal_awal=<?= $tanggal_awal ?>&tanggal_akhir=<?= $tanggal_akhir ?>&tipe=jurnal" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export-pdf.php?tanggal_awal=<?= $tanggal_awal ?>&tanggal_akhir=<?= $tanggal_akhir ?>&tipe=jurnal" class="btn btn-sm btn-danger">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>Kode</th>
                            <th>Akun</th>
                            <th>Debit</th>
                            <th>Kredit</th>
                            <th>Catatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jurnal_list)): ?>
                            <tr>
                                <td colspan="8" class="text-center">Tidak ada data jurnal</td>
                            </tr>
                        <?php else: ?>
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
                                <tr>
                                    <?php if ($show_date): ?>
                                    <td rowspan="2">
                                        <?= date('d M Y', strtotime($jurnal['tanggal_transaksi'])) ?><br>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($jurnal['tanggal_transaksi'])) ?></small>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <?php if ($show_transaction): ?>
                                    <td rowspan="2">
                                        <span class="badge <?= getJenisBadgeClass($jurnal['jenis_transaksi']) ?>">
                                            <?= ucfirst($jurnal['jenis_transaksi']) ?>
                                        </span>
                                    </td>
                                    <?php endif; ?>
                                    
                                    <td><?= $jurnal['kode_akun_debit'] ?></td>
                                    <td><?= $jurnal['nama_akun_debit'] ?></td>
                                    <td class="text-end"><?= formatRupiah($jurnal['jumlah']) ?></td>
                                    <td></td>
                                    
                                    <?php if ($show_transaction): ?>
                                    <td rowspan="2"><?= htmlspecialchars($jurnal['keterangan']) ?></td>
                                    <td rowspan="2">
                                        <a href="../transaksi/index.php?id=<?= $jurnal['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                
                                <!-- Baris untuk akun kredit -->
                                <tr>
                                    <td><?= $jurnal['kode_akun_kredit'] ?></td>
                                    <td><?= $jurnal['nama_akun_kredit'] ?></td>
                                    <td></td>
                                    <td class="text-end"><?= formatRupiah($jurnal['jumlah']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <!-- Baris total -->
                            <tr class="table-primary">
                                <td colspan="4" class="text-end fw-bold">TOTAL</td>
                                <td class="text-end fw-bold"><?= formatRupiah($total_debit) ?></td>
                                <td class="text-end fw-bold"><?= formatRupiah($total_kredit) ?></td>
                                <td colspan="2"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Fungsi untuk mendapatkan kelas badge berdasarkan jenis transaksi
function getJenisBadgeClass($jenis) {
    switch ($jenis) {
        case 'pemasukan':
            return 'bg-success';
        case 'pengeluaran':
            return 'bg-danger';
        case 'transfer':
            return 'bg-primary';
        case 'tanam_modal':
            return 'bg-info';
        case 'tarik_modal':
            return 'bg-warning';
        case 'beli_aset':
            return 'bg-secondary';
        default:
            return 'bg-secondary';
    }
}

// Footer
include '../templates/footer.php';
?>