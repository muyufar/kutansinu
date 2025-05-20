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

// Filter kontak (penanggung jawab)
$kontak = isset($_GET['kontak']) ? $_GET['kontak'] : '';

// Filter tag
$tag = isset($_GET['tag']) ? $_GET['tag'] : '';

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

// Ambil daftar perusahaan jika admin
$daftar_perusahaan = [];
if ($is_admin) {
    $stmt = $db->prepare("SELECT p.id, p.nama FROM perusahaan p JOIN user_perusahaan up ON p.id = up.perusahaan_id WHERE up.user_id = ? AND up.role = 'admin'");
    $stmt->execute([$_SESSION['user_id']]);
    $daftar_perusahaan = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Ambil filter perusahaan dari GET jika admin, jika tidak pakai default
$filter_perusahaan = $id_perusahaan;
if ($is_admin && isset($_GET['perusahaan']) && $_GET['perusahaan']) {
    $filter_perusahaan = $_GET['perusahaan'];
}

// Ambil daftar kontak unik untuk dropdown filter dengan filter perusahaan
$stmt_kontak = $db->prepare("SELECT DISTINCT penanggung_jawab FROM transaksi WHERE penanggung_jawab IS NOT NULL AND penanggung_jawab != '' AND id_perusahaan = ? ORDER BY penanggung_jawab ASC");
$stmt_kontak->execute([$filter_perusahaan]);
$daftar_kontak = $stmt_kontak->fetchAll(PDO::FETCH_COLUMN);

// Ambil daftar tag unik untuk dropdown filter dengan filter perusahaan
try {
    $stmt_tag = $db->prepare("SELECT DISTINCT tag FROM transaksi WHERE tag IS NOT NULL AND tag != '' AND id_perusahaan = ? ORDER BY tag ASC");
    $stmt_tag->execute([$filter_perusahaan]);
    $daftar_tag = $stmt_tag->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $daftar_tag = [];
}

// Buat query dasar dengan filter perusahaan
$sql = "SELECT t.*, 
        ad.kode_akun as kode_akun_debit, ad.nama_akun as nama_akun_debit,
        ak.kode_akun as kode_akun_kredit, ak.nama_akun as nama_akun_kredit
        FROM transaksi t 
        LEFT JOIN akun ad ON t.id_akun_debit = ad.id
        LEFT JOIN akun ak ON t.id_akun_kredit = ak.id
        WHERE t.tanggal BETWEEN :tanggal_awal AND :tanggal_akhir
        AND t.id_perusahaan = :id_perusahaan";

$params = [
    ':tanggal_awal' => $tanggal_awal,
    ':tanggal_akhir' => $tanggal_akhir,
    ':id_perusahaan' => $filter_perusahaan
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

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Daftar Transaksi Terbaru</h2>
        <div>
            <?php if ($is_admin): ?>
                <a href="audit_log.php" class="btn btn-dark me-2">
                    <i class="fas fa-clipboard-list"></i> Log Aktivitas
                </a>
            <?php endif; ?>
            <a href="tambah.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Transaksi
            </a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                    <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                </div>
                <div class="col-md-3">
                    <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                </div>
                <?php if ($is_admin): ?>
                    <div class="col-md-2">
                        <label for="perusahaan" class="form-label">Perusahaan</label>
                        <select class="form-select" id="perusahaan" name="perusahaan">
                            <option value="">Semua Perusahaan</option>
                            <?php foreach ($daftar_perusahaan as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (isset($_GET['perusahaan']) && $_GET['perusahaan'] == $p['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label for="kontak" class="form-label">Pilih Kontak</label>
                    <select class="form-select" id="kontak" name="kontak">
                        <option value="">Semua Kontak</option>
                        <?php foreach ($daftar_kontak as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $kontak === $k ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="tag" class="form-label">Pilih Tag</label>
                    <select class="form-select" id="tag" name="tag">
                        <option value="">Semua Tag</option>
                        <?php foreach ($daftar_tag as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $tag === $t ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ringkasan -->
    <!-- <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Pemasukan</h5>
                    <h3 class="card-text"><?= formatRupiah($total['total_pemasukan'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Pengeluaran</h5>
                    <h3 class="card-text"><?= formatRupiah($total['total_pengeluaran'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Saldo</h5>
                    <h3 class="card-text"><?= formatRupiah(($total['total_pemasukan'] ?? 0) - ($total['total_pengeluaran'] ?? 0)) ?></h3>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Tabel Transaksi -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Transaksi</h5>
            <div>
                <a href="export-excel.php?tanggal_awal=<?= $tanggal_awal ?>&tanggal_akhir=<?= $tanggal_akhir ?>&kontak=<?= urlencode($kontak) ?>&tag=<?= urlencode($tag) ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export-pdf.php?tanggal_awal=<?= $tanggal_awal ?>&tanggal_akhir=<?= $tanggal_akhir ?>&kontak=<?= urlencode($kontak) ?>&tag=<?= urlencode($tag) ?>" class="btn btn-sm btn-danger">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Transaksi</th>
                            <th>Catatan</th>
                            <th>Total</th>
                            <th>Tag</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transaksi_list)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Tidak ada data transaksi</td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1;
                            foreach ($transaksi_list as $transaksi): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d M Y', strtotime($transaksi['tanggal'])) ?><br>
                                        <!-- <small class="text-muted"><?= date('H:i:s', strtotime($transaksi['tanggal'])) ?></small> -->
                                    </td>
                                    <td>
                                        <span class="badge <?= getJenisBadgeClass($transaksi['jenis']) ?>">
                                            <?= ucfirst($transaksi['jenis']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($transaksi['keterangan']) ?></td>
                                    <td class="<?= $transaksi['jenis'] == 'pemasukan' ? 'text-success' : 'text-danger' ?>">
                                        <?= formatRupiah($transaksi['jumlah']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($transaksi['tag'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($transaksi['tag']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../transaksi/index.php?id=<?= $transaksi['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Fungsi untuk mendapatkan kelas badge berdasarkan jenis transaksi
function getJenisBadgeClass($jenis)
{
    switch ($jenis) {
        case 'pemasukan':
            return 'bg-success';
        case 'pengeluaran':
            return 'bg-danger';
        case 'transfer':
            return 'bg-primary';
        case 'tarik_modal':
            return 'bg-warning';
        case 'beli_aset':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

// Footer
include '../templates/footer.php';
?>