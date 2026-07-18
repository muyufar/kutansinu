<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Proses tambah akun
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $kode_akun = validateInput($_POST['kode_akun']);
    $nama_akun = validateInput($_POST['nama_akun']);
    $kategori = validateInput($_POST['kategori']);
    $deskripsi = validateInput($_POST['deskripsi']);

    // Mendapatkan id_perusahaan dari user yang sedang login
    $id_perusahaan = $_SESSION['default_company'] ?? null;
    if (!$id_perusahaan) {
        $_SESSION['error'] = 'Anda harus memiliki perusahaan aktif untuk menambahkan akun';
        header('Location: index.php');
        exit();
    }

    try {
        $stmt = $db->prepare("INSERT INTO akun (kode_akun, nama_akun, kategori, deskripsi, id_perusahaan, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$kode_akun, $nama_akun, $kategori, $deskripsi, $id_perusahaan, $_SESSION['user_id']]);
        $_SESSION['success'] = 'Akun berhasil ditambahkan';
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal menambahkan akun: ' . $e->getMessage();
    }
}

// Proses edit akun
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $kode_akun = validateInput($_POST['kode_akun']);
    $nama_akun = validateInput($_POST['nama_akun']);
    $kategori = validateInput($_POST['kategori']);
    $deskripsi = validateInput($_POST['deskripsi']);

    // Mendapatkan id_perusahaan dari user yang sedang login
    $id_perusahaan = $_SESSION['default_company'] ?? null;
    if (!$id_perusahaan) {
        $_SESSION['error'] = 'Anda harus memiliki perusahaan aktif untuk mengedit akun';
        header('Location: index.php');
        exit();
    }

    try {
        // Pastikan akun yang diedit adalah milik perusahaan yang aktif
        $check = $db->prepare("SELECT id FROM akun WHERE id = ? AND id_perusahaan = ?");
        $check->execute([$id, $id_perusahaan]);
        if ($check->rowCount() == 0) {
            $_SESSION['error'] = 'Akun tidak ditemukan atau bukan milik perusahaan Anda';
            header('Location: index.php');
            exit();
        }

        $stmt = $db->prepare("UPDATE akun SET kode_akun = ?, nama_akun = ?, kategori = ?, deskripsi = ? WHERE id = ? AND id_perusahaan = ?");
        $stmt->execute([$kode_akun, $nama_akun, $kategori, $deskripsi, $id, $id_perusahaan]);
        $_SESSION['success'] = 'Akun berhasil diperbarui';
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui akun: ' . $e->getMessage();
    }
}

// Proses hapus akun
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    // Mendapatkan id_perusahaan dari user yang sedang login
    $id_perusahaan = $_SESSION['default_company'] ?? null;
    if (!$id_perusahaan) {
        $_SESSION['error'] = 'Anda harus memiliki perusahaan aktif untuk menghapus akun';
        header('Location: index.php');
        exit();
    }

    try {
        // Pastikan akun yang dihapus adalah milik perusahaan yang aktif
        $check = $db->prepare("SELECT id FROM akun WHERE id = ? AND id_perusahaan = ?");
        $check->execute([$_GET['id'], $id_perusahaan]);
        if ($check->rowCount() == 0) {
            $_SESSION['error'] = 'Akun tidak ditemukan atau bukan milik perusahaan Anda';
            header('Location: index.php');
            exit();
        }

        $stmt = $db->prepare("DELETE FROM akun WHERE id = ? AND id_perusahaan = ?");
        $stmt->execute([$_GET['id'], $id_perusahaan]);
        $_SESSION['success'] = 'Akun berhasil dihapus';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal menghapus akun: ' . $e->getMessage();
    }
    header('Location: index.php');
    exit();
}


// Ambil id_perusahaan dari default_company pengguna
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

// Pagination settings
$items_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $items_per_page;

// Search and filter parameters
$search = isset($_GET['search']) ? validateInput($_GET['search']) : '';
$kategori = isset($_GET['kategori']) ? validateInput($_GET['kategori']) : '';

// Build WHERE clause for search and filter
$where_clause = "WHERE id_perusahaan = ?";
$params = [$id_perusahaan];

if (!empty($search)) {
    $where_clause .= " AND (kode_akun LIKE ? OR nama_akun LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($kategori)) {
    $where_clause .= " AND kategori = ?";
    $params[] = $kategori;
}

// Get total records for pagination
$stmt_count = $db->prepare("SELECT COUNT(*) as total FROM akun " . $where_clause);
$stmt_count->execute($params);
$total_records = $stmt_count->fetch()['total'];
$total_pages = $items_per_page > 0 ? ceil($total_records / $items_per_page) : 1;

// Ambil daftar akun dengan pagination
if ($items_per_page > 0) {
    $stmt = $db->prepare("SELECT * FROM akun " . $where_clause . " ORDER BY kode_akun ASC LIMIT " . (int)$items_per_page . " OFFSET " . (int)$offset);
} else {
    $stmt = $db->prepare("SELECT * FROM akun " . $where_clause . " ORDER BY kode_akun ASC");
}
$stmt->execute($params);
$akun_list = $stmt->fetchAll();

// Header
include '../templates/header.php';
?>

<div class="container mt-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5>Daftar Akun</h5>
        <div class="d-flex gap-1">

            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahAkun">
                <i class="fas fa-plus"></i> Tambah Akun
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter Form -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Cari kode atau nama akun..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="kategori" onchange="this.form.submit()">
                        <option value="">Semua Kategori</option>
                        <option value="aktiva" <?php echo $kategori == 'aktiva' ? 'selected' : ''; ?>>Aktiva</option>
                        <option value="pasiva" <?php echo $kategori == 'pasiva' ? 'selected' : ''; ?>>Pasiva</option>
                        <option value="modal" <?php echo $kategori == 'modal' ? 'selected' : ''; ?>>Modal</option>
                        <option value="pendapatan" <?php echo $kategori == 'pendapatan' ? 'selected' : ''; ?>>Pendapatan</option>
                        <option value="beban" <?php echo $kategori == 'beban' ? 'selected' : ''; ?>>Beban</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="index.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Kode Akun</th>
                            <th>Nama Akun</th>
                            <th>Kategori</th>
                            <th>Sub Kategori</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($akun_list as $akun): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($akun['kode_akun']); ?></td>
                                <td><?php echo htmlspecialchars($akun['nama_akun']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($akun['kategori'])); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($akun['sub_kategori'])); ?></td>
                                <td><?php echo htmlspecialchars($akun['deskripsi']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditAkun"
                                        data-id="<?php echo $akun['id']; ?>"
                                        data-kode="<?php echo htmlspecialchars($akun['kode_akun']); ?>"
                                        data-nama="<?php echo htmlspecialchars($akun['nama_akun']); ?>"
                                        data-kategori="<?php echo htmlspecialchars($akun['kategori']); ?>"
                                        data-deskripsi="<?php echo htmlspecialchars($akun['deskripsi']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?action=hapus&id=<?php echo $akun['id']; ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus akun ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center align-items-center">
                    <!-- Items per page selector -->
                    <li class="page-item">
                        <select class="form-select form-select-sm" id="perPageSelect" onchange="changePerPage(this.value)">
                            <option value="10" <?php echo $items_per_page == 10 ? 'selected' : ''; ?>>10 per halaman</option>
                            <option value="20" <?php echo $items_per_page == 20 ? 'selected' : ''; ?>>20 per halaman</option>
                            <option value="50" <?php echo $items_per_page == 50 ? 'selected' : ''; ?>>50 per halaman</option>
                            <option value="0" <?php echo $items_per_page == 0 ? 'selected' : ''; ?>>Semua</option>
                        </select>
                    </li>

                    <?php if ($items_per_page > 0 && $total_pages > 1): ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&per_page=<?php echo $items_per_page; ?>&search=<?php echo urlencode($search); ?>&kategori=<?php echo urlencode($kategori); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modal Tambah Akun -->
<div class="modal fade" id="modalTambahAkun" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Akun Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="tambah">
                    <div class="mb-3">
                        <label for="kode_akun" class="form-label">Kode Akun</label>
                        <input type="text" class="form-control" id="kode_akun" name="kode_akun" required>
                    </div>
                    <div class="mb-3">
                        <label for="nama_akun" class="form-label">Nama Akun</label>
                        <input type="text" class="form-control" id="nama_akun" name="nama_akun" required>
                    </div>
                    <div class="mb-3">
                        <label for="kategori" class="form-label">Kategori</label>
                        <select class="form-select" id="kategori" name="kategori" required>
                            <option value="aktiva">Aktiva</option>
                            <option value="pasiva">Pasiva</option>
                            <option value="modal">Modal</option>
                            <option value="pendapatan">Pendapatan</option>
                            <option value="beban">Beban</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Akun -->
<div class="modal fade" id="modalEditAkun" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_kode_akun" class="form-label">Kode Akun</label>
                        <input type="text" class="form-control" id="edit_kode_akun" name="kode_akun" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_nama_akun" class="form-label">Nama Akun</label>
                        <input type="text" class="form-control" id="edit_nama_akun" name="nama_akun" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_kategori" class="form-label">Kategori</label>
                        <select class="form-select" id="edit_kategori" name="kategori" required>
                            <option value="aktiva">Aktiva</option>
                            <option value="pasiva">Pasiva</option>
                            <option value="modal">Modal</option>
                            <option value="pendapatan">Pendapatan</option>
                            <option value="beban">Beban</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit_deskripsi" name="deskripsi" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Script untuk mengisi modal edit
    document.getElementById('modalEditAkun').addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var kode = button.getAttribute('data-kode');
        var nama = button.getAttribute('data-nama');
        var kategori = button.getAttribute('data-kategori');
        var deskripsi = button.getAttribute('data-deskripsi');

        var modal = this;
        modal.querySelector('#edit_id').value = id;
        modal.querySelector('#edit_kode_akun').value = kode;
        modal.querySelector('#edit_nama_akun').value = nama;
        modal.querySelector('#edit_kategori').value = kategori;
        modal.querySelector('#edit_deskripsi').value = deskripsi;
    });

    // Function to change items per page
    function changePerPage(value) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('per_page', value);
        urlParams.set('page', '1');
        window.location.href = '?' + urlParams.toString();
    }
</script>

<?php
// Footer
include '../templates/footer.php';
?>