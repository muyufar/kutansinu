<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Ambil data user
$user_id = $_SESSION['user_id'];

// Cek ID karyawan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID karyawan tidak valid';
    header('Location: karyawan.php');
    exit();
}

$karyawan_id = (int)$_GET['id'];

// Pastikan bukan user sendiri
if ($karyawan_id == $user_id) {
    $_SESSION['error'] = 'Anda tidak dapat mengedit akun Anda sendiri melalui halaman ini. Silakan gunakan halaman profil.';
    header('Location: karyawan.php');
    exit();
}

// Ambil perusahaan default user
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

// Cek role user (hanya admin dan editor yang boleh mengakses halaman edit karyawan)
if (!checkUserRole($db, $user_id, $default_company_id, 'editor')) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk mengedit karyawan. Hanya Admin dan Editor yang dapat mengakses fitur ini.';
    header('Location: karyawan.php');
    exit();
}

// Verifikasi bahwa karyawan ini terkait dengan perusahaan default user
$stmt = $db->prepare("SELECT COUNT(*) FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
$stmt->execute([$karyawan_id, $default_company_id]);
$is_valid = $stmt->fetchColumn() > 0;

if (!$is_valid) {
    $_SESSION['error'] = 'Karyawan tidak ditemukan di perusahaan Anda';
    header('Location: karyawan.php');
    exit();
}

// Cek apakah user saat ini memiliki role admin pada perusahaan ini
$is_admin = checkUserRole($db, $user_id, $default_company_id, 'admin');

// Ambil data karyawan
$stmt = $db->prepare("SELECT u.*, up.role, up.status 
                      FROM users u 
                      JOIN user_perusahaan up ON u.id = up.user_id 
                      WHERE u.id = ? AND up.perusahaan_id = ?");
$stmt->execute([$karyawan_id, $default_company_id]);
$karyawan = $stmt->fetch();

if (!$karyawan) {
    $_SESSION['error'] = 'Karyawan tidak ditemukan';
    header('Location: karyawan.php');
    exit();
}

// Proses update karyawan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_karyawan'])) {
    $nama_lengkap = validateInput($_POST['nama_lengkap']);
    $email = validateInput($_POST['email']);
    $role = validateInput($_POST['role']);
    $status = validateInput($_POST['status']);
    $reset_password = isset($_POST['reset_password']) ? true : false;
    
    // Validasi role
    if (!in_array($role, ['admin', 'editor', 'viewer'])) {
        $_SESSION['error'] = 'Role tidak valid';
        header('Location: karyawan_edit.php?id=' . $karyawan_id);
        exit();
    }
    
    // Validasi status
    if (!in_array($status, ['active', 'inactive'])) {
        $_SESSION['error'] = 'Status tidak valid';
        header('Location: karyawan_edit.php?id=' . $karyawan_id);
        exit();
    }
    
    // Hanya admin yang bisa mengubah role menjadi admin
    if ($role === 'admin' && !$is_admin) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk mengubah role karyawan menjadi Admin';
        header('Location: karyawan_edit.php?id=' . $karyawan_id);
        exit();
    }
    
    try {
        $db->beginTransaction();
        
        // Update data user
        $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
        $stmt->execute([$nama_lengkap, $email, $karyawan_id]);
        
        // Update role dan status di user_perusahaan
        $stmt = $db->prepare("UPDATE user_perusahaan SET role = ?, status = ? WHERE user_id = ? AND perusahaan_id = ?");
        $stmt->execute([$role, $status, $karyawan_id, $default_company_id]);
        
        // Update password jika diminta
        if ($reset_password) {
            // Cek apakah password manual diinput atau generate otomatis
            if (!empty($_POST['new_password'])) {
                $new_password = $_POST['new_password']; // Gunakan password yang diinput user
            } else {
                $new_password = generateRandomPassword(8); // Generate password random
            }
            
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $karyawan_id]);
            
            // TODO: Kirim email dengan password baru ke karyawan
            $_SESSION['new_password'] = $new_password; // Simpan password baru untuk ditampilkan (dalam produksi sebaiknya dikirim via email)
        }
        
        $db->commit();
        $_SESSION['success'] = 'Data karyawan berhasil diperbarui';
        header('Location: karyawan.php');
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Gagal memperbarui data karyawan: ' . $e->getMessage();
    }
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
            
            <?php if (isset($_SESSION['new_password'])): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>Password baru:</strong> <?= $_SESSION['new_password'] ?>
                    <p class="mb-0">Harap catat password ini dan berikan kepada karyawan. Password ini tidak akan ditampilkan lagi.</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['new_password']); ?>
            <?php endif; ?>
            
            <!-- Edit Karyawan -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Karyawan</h5>
                    <a href="karyawan.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($karyawan['nama_lengkap']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($karyawan['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <?php if ($is_admin): ?>
                                <option value="admin" <?= ($karyawan['role'] == 'admin') ? 'selected' : '' ?>>Admin - Akses penuh ke semua fitur</option>
                                <?php endif; ?>
                                <option value="editor" <?= ($karyawan['role'] == 'editor') ? 'selected' : '' ?>>Editor - Dapat mengedit data dan backup</option>
                                <option value="viewer" <?= ($karyawan['role'] == 'viewer') ? 'selected' : '' ?>>Viewer - Hanya dapat melihat data</option>
                            </select>
                            <div class="form-text">Role menentukan hak akses karyawan pada sistem</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= ($karyawan['status'] == 'active') ? 'selected' : '' ?>>Aktif</option>
                                <option value="inactive" <?= ($karyawan['status'] == 'inactive') ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="reset_password" name="reset_password">
                        <label class="form-check-label" for="reset_password">Update Password</label>
                        <div class="form-text">Jika dicentang, password akan diupdate sesuai input atau digenerate otomatis jika kosong.</div>
                    </div>
                    
                    <div class="mb-3" id="password_input_container" style="display: none;">
                        <label for="new_password" class="form-label">Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" placeholder="Kosongkan untuk generate otomatis">
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i> Kosongkan untuk generate password secara otomatis.
                        </div>
                    </div>
                        
                        <button type="submit" name="update_karyawan" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fungsi helper sudah dipindahkan ke config/functions.php

include '../templates/footer.php';
?>

<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('new_password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    
    // Toggle icon
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

// Show/hide password input when reset checkbox is clicked
document.getElementById('reset_password').addEventListener('change', function() {
    const passwordContainer = document.getElementById('password_input_container');
    passwordContainer.style.display = this.checked ? 'block' : 'none';
});
</script>