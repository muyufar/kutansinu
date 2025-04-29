<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Ambil data user
$user_id = $_SESSION['user_id'];

// Ambil perusahaan default user
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

// Jika tidak ada perusahaan default, redirect ke halaman perusahaan
if (!$default_company_id) {
    $_SESSION['error'] = 'Anda belum memiliki perusahaan default. Silakan tambahkan perusahaan terlebih dahulu.';
    header('Location: perusahaan.php');
    exit();
}

// Ambil data perusahaan default
$stmt = $db->prepare("SELECT * FROM perusahaan WHERE id = ?");
$stmt->execute([$default_company_id]);
$perusahaan = $stmt->fetch();

// Ambil semua perusahaan yang terkait dengan user untuk dropdown
$stmt = $db->prepare("SELECT p.* FROM perusahaan p 
                      JOIN user_perusahaan up ON p.id = up.perusahaan_id 
                      WHERE up.user_id = ?");
$stmt->execute([$user_id]);
$perusahaan_list = $stmt->fetchAll();

// Ambil semua karyawan dari perusahaan default
$stmt = $db->prepare("SELECT u.*, up.role, up.status 
                      FROM users u 
                      JOIN user_perusahaan up ON u.id = up.user_id 
                      WHERE up.perusahaan_id = ?
                      ORDER BY u.nama_lengkap ASC");
                      
// Cek apakah user saat ini memiliki role admin pada perusahaan ini
$is_admin = checkUserRole($db, $user_id, $default_company_id, 'admin');
$stmt->execute([$default_company_id]);
$karyawan_list = $stmt->fetchAll();

// Proses tambah karyawan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_karyawan'])) {
    $nama_lengkap = validateInput($_POST['nama_lengkap']);
    $email = validateInput($_POST['email']);
    $role = validateInput($_POST['role']);
    
    // Validasi role
    if (!in_array($role, ['admin', 'editor', 'viewer'])) {
        $_SESSION['error'] = 'Role tidak valid';
        header('Location: karyawan.php');
        exit();
    }
    
    // Hanya admin yang bisa menambahkan karyawan dengan role admin
    if ($role === 'admin' && !$is_admin) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk menambahkan karyawan dengan role Admin';
        header('Location: karyawan.php');
        exit();
    }
    
    // Cek apakah password manual diinput atau generate otomatis
    if (!empty($_POST['password'])) {
        $password = $_POST['password']; // Gunakan password yang diinput user
    } else {
        $password = generateRandomPassword(8); // Generate password random
    }
    
    try {
        $db->beginTransaction();
        
        // Cek apakah email sudah terdaftar
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $email_exists = $stmt->fetchColumn() > 0;
        
        if ($email_exists) {
            // Jika email sudah terdaftar, cek apakah sudah terkait dengan perusahaan ini
            $stmt = $db->prepare("SELECT u.id FROM users u 
                                  JOIN user_perusahaan up ON u.id = up.user_id 
                                  WHERE u.email = ? AND up.perusahaan_id = ?");
            $stmt->execute([$email, $default_company_id]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                throw new Exception('Email sudah terdaftar sebagai karyawan di perusahaan ini');
            }
            
            // Ambil user_id dari email yang sudah ada
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user_id = $stmt->fetchColumn();
            
            // Hubungkan user yang sudah ada dengan perusahaan
            $stmt = $db->prepare("INSERT INTO user_perusahaan (user_id, perusahaan_id, role, status, created_at) 
                                 VALUES (?, ?, ?, 'active', NOW())");
            $stmt->execute([$existing_user_id, $default_company_id, $role]);
        } else {
            // Buat user baru
            $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, email, created_at) 
                                 VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $nama_lengkap, $email]);
            $new_user_id = $db->lastInsertId();
            
            // Hubungkan user baru dengan perusahaan
            $stmt = $db->prepare("INSERT INTO user_perusahaan (user_id, perusahaan_id, role, status, created_at) 
                                 VALUES (?, ?, ?, 'active', NOW())");
            $stmt->execute([$new_user_id, $default_company_id, $role]);
            
            // TODO: Kirim email dengan password ke user baru
        }
        
        $db->commit();
        $_SESSION['success'] = 'Karyawan berhasil ditambahkan';
        header('Location: karyawan.php');
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Gagal menambahkan karyawan: ' . $e->getMessage();
    }
}

// Proses ubah status karyawan
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $karyawan_id = (int)$_GET['toggle_status'];
    
    // Pastikan bukan user sendiri
    if ($karyawan_id == $user_id) {
        $_SESSION['error'] = 'Anda tidak dapat mengubah status akun Anda sendiri';
        header('Location: karyawan.php');
        exit();
    }
    
    try {
        // Ambil status saat ini
        $stmt = $db->prepare("SELECT status FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
        $stmt->execute([$karyawan_id, $default_company_id]);
        $current_status = $stmt->fetchColumn();
        
        // Toggle status
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';
        
        $stmt = $db->prepare("UPDATE user_perusahaan SET status = ? WHERE user_id = ? AND perusahaan_id = ?");
        $stmt->execute([$new_status, $karyawan_id, $default_company_id]);
        
        $_SESSION['success'] = 'Status karyawan berhasil diubah';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal mengubah status karyawan: ' . $e->getMessage();
    }
    
    header('Location: karyawan.php');
    exit();
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Pengaturan</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="profil.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-2"></i> Profil
                    </a>
                    <a href="perusahaan.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-building me-2"></i> Perusahaan
                    </a>
                    <a href="pengaturan_utama.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i> Pengaturan Utama
                    </a>
                    <a href="karyawan.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users me-2"></i> Karyawan
                    </a>
                    <a href="reset_data.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-trash-alt me-2"></i> Reset Data
                    </a>
                    <a href="backup.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-download me-2"></i> Backup Data
                    </a>
                    <a href="../logout.php" class="list-group-item list-group-item-action text-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        <div class="col-md-9">
            <!-- Tampilkan pesan sukses/error -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Pilih Perusahaan -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Karyawan</h5>
                            <p class="text-muted mb-0">Perusahaan: <?= htmlspecialchars($perusahaan['nama']) ?></p>
                        </div>
                        <div class="d-flex">
                            <div class="me-2">
                                <select class="form-select" id="perusahaan_selector">
                                    <?php foreach ($perusahaan_list as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= ($p['id'] == $default_company_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nama']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahKaryawanModal">
                                <i class="fas fa-plus me-1"></i> Tambah Karyawan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Daftar Karyawan -->
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="karyawanTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="semua-tab" data-bs-toggle="tab" data-bs-target="#semua" type="button" role="tab" aria-controls="semua" aria-selected="true">Semua Karyawan</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tambah-tab" data-bs-toggle="tab" data-bs-target="#tambah" type="button" role="tab" aria-controls="tambah" aria-selected="false">Tambah Karyawan</button>
                        </li>
                    </ul>
                    <div class="tab-content" id="karyawanTabContent">
                        <div class="tab-pane fade show active" id="semua" role="tabpanel" aria-labelledby="semua-tab">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>NO.</th>
                                            <th>NAMA</th>
                                            <th>EMAIL</th>
                                            <th>ROLES</th>
                                            <th>STATUS</th>
                                            <th>TYPE</th>
                                            <th>AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($karyawan_list)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Tidak ada data karyawan</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1; foreach ($karyawan_list as $karyawan): ?>
                                                <tr>
                                                    <td><?= $no++ ?></td>
                                                    <td><?= htmlspecialchars($karyawan['nama_lengkap']) ?></td>
                                                    <td><?= htmlspecialchars($karyawan['email']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= getRoleBadgeClass($karyawan['role']) ?>">
                                                            <?= ucfirst($karyawan['role']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= ($karyawan['status'] == 'active') ? 'success' : 'danger' ?>">
                                                            <?= ($karyawan['status'] == 'active') ? 'Aktif' : 'Nonaktif' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?= ($karyawan['id'] == $user_id) ? 'Owner' : 'Staff' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($karyawan['id'] != $user_id): ?>
                                                            <a href="karyawan.php?toggle_status=<?= $karyawan['id'] ?>" class="btn btn-sm btn-<?= ($karyawan['status'] == 'active') ? 'warning' : 'success' ?>" title="<?= ($karyawan['status'] == 'active') ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                                <i class="fas fa-<?= ($karyawan['status'] == 'active') ? 'ban' : 'check' ?>"></i>
                                                            </a>
                                                            <a href="karyawan_edit.php?id=<?= $karyawan['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tambah" role="tabpanel" aria-labelledby="tambah-tab">
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i> Sebelum menambahkan karyawan, mohon lakukan verifikasi pada email karyawan.
                            </div>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="nama_lengkap" class="form-label">Nama <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <?php if ($is_admin): ?>
                                        <option value="admin">Admin - Akses penuh ke semua fitur</option>
                                        <?php endif; ?>
                                        <option value="editor">Editor - Dapat mengedit data dan backup</option>
                                        <option value="viewer">Viewer - Hanya dapat melihat data</option>
                                    </select>
                                    <div class="form-text">Role menentukan hak akses karyawan pada sistem</div>
                                </div>
                                
                                <button type="submit" name="tambah_karyawan" class="btn btn-primary">Simpan</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Karyawan -->
<div class="modal fade" id="tambahKaryawanModal" tabindex="-1" aria-labelledby="tambahKaryawanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahKaryawanModalLabel">Tambah Karyawan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i> Sebelum menambahkan karyawan, mohon lakukan verifikasi pada email karyawan.
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_nama_lengkap" class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_nama_lengkap" name="nama_lengkap" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="modal_email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_role" class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" id="modal_role" name="role" required>
                            <?php if ($is_admin): ?>
                            <option value="admin">Admin - Akses penuh ke semua fitur</option>
                            <?php endif; ?>
                            <option value="editor">Editor - Dapat mengedit data dan backup</option>
                            <option value="viewer">Viewer - Hanya dapat melihat data</option>
                        </select>
                        <div class="form-text">Role menentukan hak akses karyawan pada sistem</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_password" class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="modal_password" name="password" placeholder="Kosongkan untuk generate otomatis">
                            <button class="btn btn-outline-secondary" type="button" id="toggleModalPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i> Kosongkan untuk generate password secara otomatis.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_karyawan" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle password visibility for modal
document.getElementById('toggleModalPassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('modal_password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    // Toggle icon
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});
</script>

<script>
    // Redirect ke perusahaan yang dipilih
    document.getElementById('perusahaan_selector').addEventListener('change', function() {
        const perusahaanId = this.value;
        window.location.href = `set_default_company.php?id=${perusahaanId}&redirect=karyawan.php`;
    });
    
    // Aktifkan tab tambah karyawan jika ada error
    <?php if (isset($_SESSION['error']) && strpos($_SESSION['error'], 'karyawan') !== false): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const tambahTab = new bootstrap.Tab(document.getElementById('tambah-tab'));
        tambahTab.show();
    });
    <?php endif; ?>
</script>

<?php
// Fungsi helper sudah dipindahkan ke config/functions.php

include '../templates/footer.php';
?>