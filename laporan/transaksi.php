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

// Filter tag (multiple)
$tags = isset($_GET['tags']) ? $_GET['tags'] : [];
if (!is_array($tags)) {
    $tags = [];
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
    // Ambil semua tag dari transaksi, lalu pisahkan dan ambil yang unik
    $stmt_tag = $db->prepare("SELECT tag FROM transaksi WHERE tag IS NOT NULL AND tag != '' AND id_perusahaan = ?");
    $stmt_tag->execute([$filter_perusahaan]);
    $all_tags = $stmt_tag->fetchAll(PDO::FETCH_COLUMN);

    // Pisahkan tag dan simpan ke array
    $daftar_tag = [];
    foreach ($all_tags as $tag_string) {
        $tag_parts = array_map('trim', explode(',', $tag_string));
        foreach ($tag_parts as $tag_part) {
            if (!empty($tag_part)) {
                $daftar_tag[] = $tag_part;
            }
        }
    }

    // Hapus duplikat dan urutkan
    $daftar_tag = array_unique($daftar_tag);
    sort($daftar_tag);
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

// Tambahkan filter tag jika ada (multiple) - Filter yang lebih akurat
if (!empty($tags)) {
    $tag_conditions = [];
    foreach ($tags as $index => $tag_value) {
        // Gunakan pattern yang lebih akurat untuk mencari tag yang tepat
        // Cari tag yang diawali dengan koma, diakhiri dengan koma, atau tepat sama
        $tag_conditions[] = "(t.tag = :tag" . $index . " 
                            OR t.tag LIKE :tag" . $index . "_start 
                            OR t.tag LIKE :tag" . $index . "_end 
                            OR t.tag LIKE :tag" . $index . "_middle)";

        $params[':tag' . $index] = $tag_value;
        $params[':tag' . $index . '_start'] = $tag_value . ',%';
        $params[':tag' . $index . '_end'] = '%,' . $tag_value;
        $params[':tag' . $index . '_middle'] = '%,' . $tag_value . ',%';
    }
    $sql .= " AND (" . implode(' OR ', $tag_conditions) . ")";
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
    WHERE tanggal BETWEEN :tanggal_awal AND :tanggal_akhir
    AND id_perusahaan = :id_perusahaan_total";

$params_total = [
    ':tanggal_awal' => $tanggal_awal,
    ':tanggal_akhir' => $tanggal_akhir,
    ':id_perusahaan_total' => $filter_perusahaan
];

// Tambahkan filter kontak jika ada
if (!empty($kontak)) {
    $sql_total .= " AND penanggung_jawab = :kontak";
    $params_total[':kontak'] = $kontak;
}

// Tambahkan filter tag jika ada (multiple) - Filter yang lebih akurat untuk total
if (!empty($tags)) {
    $tag_conditions = [];
    foreach ($tags as $index => $tag_value) {
        // Gunakan pattern yang sama dengan query utama
        $tag_conditions[] = "(tag = :tag_total" . $index . " 
                            OR tag LIKE :tag_total" . $index . "_start 
                            OR tag LIKE :tag_total" . $index . "_end 
                            OR tag LIKE :tag_total" . $index . "_middle)";

        $params_total[':tag_total' . $index] = $tag_value;
        $params_total[':tag_total' . $index . '_start'] = $tag_value . ',%';
        $params_total[':tag_total' . $index . '_end'] = '%,' . $tag_value;
        $params_total[':tag_total' . $index . '_middle'] = '%,' . $tag_value . ',%';
    }
    $sql_total .= " AND (" . implode(' OR ', $tag_conditions) . ")";
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
            <a href="../transaksi/tambah.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Tambah Transaksi
            </a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <!-- Kolom Kiri: Tanggal Awal dan Akhir (atas bawah) -->
                <div class="col-md-3">
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                            <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?= $tanggal_awal ?>">
                        </div>
                        <div class="col-12">
                            <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?= $tanggal_akhir ?>">
                        </div>
                    </div>
                </div>

                <!-- Kolom Tengah: Perusahaan dan Kontak (atas bawah) -->
                <div class="col-md-3">
                    <div class="row g-2">
                        <?php if ($is_admin): ?>
                            <div class="col-12">
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
                        <div class="col-12">
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
                    </div>
                </div>

                <!-- Kolom Kanan: Pilih Tag -->
                <div class="col-md-4">
                    <label for="tags" class="form-label">Pilih Tag <span id="tagCount" class="badge bg-primary"><?= count($tags) ?></span></label>
                    <select class="form-select" id="tags" name="tags[]" multiple size="4">
                        <?php foreach ($daftar_tag as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= in_array($t, $tags) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-1">
                        <small class="text-muted">
                            <strong>Tips:</strong> Ctrl+Click untuk pilih multiple, Double-click untuk toggle, atau gunakan tombol Clear
                        </small>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="clearTagSelection()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>

            <!-- Tampilkan tag yang dipilih -->
            <?php if (!empty($tags)): ?>
                <div class="mt-3">
                    <strong>Tag yang dipilih:</strong>
                    <?php foreach ($tags as $selected_tag): ?>
                        <span class="badge bg-info me-1"><?= htmlspecialchars($selected_tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Debug info (hapus setelah testing) -->
            <!-- <div class="mt-2">
                <small class="text-muted">
                    <strong>Debug:</strong><br>
                    Raw tags: <?= htmlspecialchars(print_r($_GET['tags'] ?? 'none', true)) ?><br>
                    Processed tags: <?= htmlspecialchars(print_r($tags, true)) ?><br>
                    Total records found: <?= count($transaksi_list) ?><br>
                    SQL Query: <?= htmlspecialchars($sql) ?><br>
                    Parameters: <?= htmlspecialchars(print_r($params, true)) ?>
                </small>
            </div> -->
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
                <a href="export-excel.php?tanggal_awal=<?= $tanggal_awal ?>&tanggal_akhir=<?= $tanggal_akhir ?>&kontak=<?= urlencode($kontak) ?>&tags=<?= urlencode(implode(',', $tags)) ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="export-pdf.php?tanggal_awal=<?= $tanggal_awal ?>&tanggal_akhir=<?= $tanggal_akhir ?>&kontak=<?= urlencode($kontak) ?>&tags=<?= urlencode(implode(',', $tags)) ?>" class="btn btn-sm btn-danger">
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
                                            <?php
                                            // Pisahkan tag berdasarkan koma dan tampilkan sebagai badge terpisah
                                            $tag_array = array_map('trim', explode(',', $transaksi['tag']));
                                            foreach ($tag_array as $single_tag): ?>
                                                <span class="badge bg-info me-1"><?= htmlspecialchars($single_tag) ?></span>
                                            <?php endforeach; ?>
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
                    <tfoot>
                        <tr class="table-dark fw-bold">
                            <td colspan="4" class="text-end">TOTAL TRANSAKSI</td>
                            <td class="text-warning">
                                <?= formatRupiah(
                                    ($total['total_pemasukan'] ?? 0) +
                                    ($total['total_pengeluaran'] ?? 0)
                                ) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
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
?>

<script>
    // Fungsi untuk clear selection tag
    function clearTagSelection() {
        const tagSelect = document.getElementById('tags');
        for (let option of tagSelect.options) {
            option.selected = false;
        }
        // Update count setelah clear
        updateTagCount();
    }

    // Fungsi untuk memudahkan pemilihan multiple tags
    document.addEventListener('DOMContentLoaded', function() {
        const tagSelect = document.getElementById('tags');
        const tagCount = document.getElementById('tagCount');

        // Fungsi untuk update jumlah tag yang dipilih
        function updateTagCount() {
            const selectedCount = Array.from(tagSelect.selectedOptions).length;
            tagCount.textContent = selectedCount;
            tagCount.className = selectedCount > 0 ? 'badge bg-primary' : 'badge bg-secondary';
        }

        // Update count saat halaman dimuat
        updateTagCount();

        // Update count saat ada perubahan selection
        tagSelect.addEventListener('change', updateTagCount);

        // Tambahkan event listener untuk memudahkan pemilihan (double click)
        tagSelect.addEventListener('dblclick', function(e) {
            if (e.target.tagName === 'OPTION') {
                // Toggle selection dengan double click
                e.target.selected = !e.target.selected;
                updateTagCount();
                e.preventDefault();
            }
        });

        // Tambahkan keyboard shortcut
        tagSelect.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const focusedOption = this.options[this.selectedIndex];
                if (focusedOption) {
                    focusedOption.selected = !focusedOption.selected;
                    updateTagCount();
                }
            }
        });
    });
</script>

<?php
// Footer
include '../templates/footer.php';
?>