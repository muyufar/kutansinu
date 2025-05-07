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

    try {
        $stmt = $db->prepare("INSERT INTO akun (kode_akun, nama_akun, kategori, deskripsi) VALUES (?, ?, ?, ?)");
        $stmt->execute([$kode_akun, $nama_akun, $kategori, $deskripsi]);
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

    try {
        $stmt = $db->prepare("UPDATE akun SET kode_akun = ?, nama_akun = ?, kategori = ?, deskripsi = ? WHERE id = ?");
        $stmt->execute([$kode_akun, $nama_akun, $kategori, $deskripsi, $id]);
        $_SESSION['success'] = 'Akun berhasil diperbarui';
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui akun: ' . $e->getMessage();
    }
}

// Proses hapus akun
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM akun WHERE id = ?");
        $stmt->execute([$_GET['id']]);
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


// Ambil daftar akun
$stmt = $db->prepare("SELECT * FROM akun WHERE id_perusahaan = ? ORDER BY kode_akun ASC");
$stmt->execute([$id_perusahaan]);
$akun_list = $stmt->fetchAll();

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Daftar Akun</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahAkun">
            <i class="fas fa-plus"></i> Tambah Akun
        </button>
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
                                <td><?php echo htmlspecialchars(ucfirst($akun['sub_kategori']));?></td>
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
document.getElementById('modalEditAkun').addEventListener('show.bs.modal', function (event) {
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
</script>

<?php
// Footer
include '../templates/footer.php';
?>