<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Set periode default
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

// Ambil data neraca saldo dengan filter perusahaan
$neraca_saldo = getNeracaSaldo($db, $tanggal_awal, $tanggal_akhir, $id_perusahaan);

// Filter hanya akun yang memiliki transaksi
$neraca_saldo = array_filter($neraca_saldo, function($item) {
    return $item['debit'] != 0 || $item['kredit'] != 0;
});

// Hitung total
$total_debit = 0;
$total_kredit = 0;
foreach ($neraca_saldo as $item) {
    $total_debit += $item['debit'];
    $total_kredit += $item['kredit'];
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Neraca Saldo</h2>
        <button onclick="window.print()" class="btn btn-secondary">
            <i class="fas fa-print"></i> Cetak
        </button>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" 
                           value="<?php echo $tanggal_awal; ?>" required>
                </div>
                <div class="col-md-4">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" 
                           value="<?php echo $tanggal_akhir; ?>" required>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Akun</th>
                            <th>Nama Akun</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($neraca_saldo as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['kode_akun']); ?></td>
                                <td><?php echo htmlspecialchars($item['nama_akun']); ?></td>
                                <td class="text-end"><?php echo formatRupiah($item['debit']); ?></td>
                                <td class="text-end"><?php echo formatRupiah($item['kredit']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Total</td>
                            <td class="text-end"><?php echo formatRupiah($total_debit); ?></td>
                            <td class="text-end"><?php echo formatRupiah($total_kredit); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .navbar, .btn, form {
        display: none !important;
    }
    .card {
        border: none !important;
    }
    .card-body {
        padding: 0 !important;
    }
    @page {
        size: landscape;
    }
}
</style>

<?php
// Footer
include '../templates/footer.php';
?>