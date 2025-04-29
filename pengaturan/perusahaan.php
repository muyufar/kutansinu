<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek role user (hanya admin dan editor yang boleh mengakses halaman perusahaan)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

// Jika user memiliki perusahaan default, verifikasi role user
if ($default_company_id) {
    if (!checkUserRole($db, $user_id, $default_company_id, 'editor')) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk mengelola perusahaan. Hanya Admin dan Editor yang dapat mengakses fitur ini.';
        header('Location: /kutansinu/index.php');
        exit();
    }
}

// Ambil data user
$user_id = $_SESSION['user_id'];

// Ambil semua perusahaan yang terkait dengan user
$stmt = $db->prepare("SELECT p.* FROM perusahaan p 
                      JOIN user_perusahaan up ON p.id = up.perusahaan_id 
                      WHERE up.user_id = ?");
$stmt->execute([$user_id]);
$perusahaan_list = $stmt->fetchAll();

// Proses tambah perusahaan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_perusahaan'])) {
    $nama = validateInput($_POST['nama']);
    $alamat = validateInput($_POST['alamat'] ?? '');
    $telepon = validateInput($_POST['telepon'] ?? '');
    $email = validateInput($_POST['email'] ?? '');
    $website = validateInput($_POST['website'] ?? '');
    $jenis = validateInput($_POST['jenis'] ?? 'regular');
    
    // Upload logo jika ada
    $logo = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = '../uploads/perusahaan/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['logo']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
            $logo = 'uploads/perusahaan/' . $file_name;
        }
    }
    
    try {
        $db->beginTransaction();
        
        // Insert perusahaan
        $stmt = $db->prepare("INSERT INTO perusahaan (nama, alamat, telepon, email, website, logo, jenis, created_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nama, $alamat, $telepon, $email, $website, $logo, $jenis]);
        $perusahaan_id = $db->lastInsertId();
        
        // Hubungkan user dengan perusahaan
        $stmt = $db->prepare("INSERT INTO user_perusahaan (user_id, perusahaan_id, role, created_at) 
                             VALUES (?, ?, 'admin', NOW())");
        $stmt->execute([$user_id, $perusahaan_id]);
        
        // Jika user belum punya default company, set ini sebagai default
        $stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();
        
        if (!$user_data['default_company']) {
            $stmt = $db->prepare("UPDATE users SET default_company = ? WHERE id = ?");
            $stmt->execute([$perusahaan_id, $user_id]);
        }
        
        $db->commit();
        $_SESSION['success'] = 'Perusahaan berhasil ditambahkan';
        header('Location: perusahaan.php');
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Gagal menambahkan perusahaan: ' . $e->getMessage();
    }
}

// Proses set default perusahaan
if (isset($_GET['set_default']) && is_numeric($_GET['set_default'])) {
    $perusahaan_id = (int)$_GET['set_default'];
    
    // Verifikasi bahwa perusahaan ini terkait dengan user
    $stmt = $db->prepare("SELECT COUNT(*) FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
    $stmt->execute([$user_id, $perusahaan_id]);
    $is_valid = $stmt->fetchColumn() > 0;
    
    if ($is_valid) {
        try {
            $stmt = $db->prepare("UPDATE users SET default_company = ? WHERE id = ?");
            $stmt->execute([$perusahaan_id, $user_id]);
            
            $_SESSION['success'] = 'Default perusahaan berhasil diubah';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Gagal mengubah default perusahaan: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Perusahaan tidak valid';
    }
    
    header('Location: perusahaan.php');
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
                    <a href="perusahaan.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-building me-2"></i> Perusahaan
                    </a>
                    <a href="pengaturan_utama.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cog me-2"></i> Pengaturan Utama
                    </a>
                    <a href="karyawan.php" class="list-group-item list-group-item-action">
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
            
            <!-- Tombol Tambah Perusahaan -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Daftar Perusahaan</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahPerusahaanModal">
                    <i class="fas fa-plus me-1"></i> Tambah Perusahaan
                </button>
            </div>
            
            <!-- Daftar Perusahaan -->
            <div class="row">
                <?php if (empty($perusahaan_list)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            Belum ada perusahaan. Silakan tambahkan perusahaan baru.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($perusahaan_list as $perusahaan): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <?php if ($perusahaan['logo']): ?>
                                            <img src="../<?= $perusahaan['logo'] ?>" alt="<?= htmlspecialchars($perusahaan['nama']) ?>" class="img-fluid" style="max-height: 100px;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                                <i class="fas fa-building fa-3x text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="card-title"><?= htmlspecialchars($perusahaan['nama']) ?></h5>
                                    <p class="card-text text-muted">
                                        <span class="badge bg-<?= $perusahaan['jenis'] == 'premium' ? 'warning' : 'secondary' ?>">
                                            <?= ucfirst($perusahaan['jenis']) ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="card-footer bg-white d-flex justify-content-between">
                                    <a href="perusahaan_edit.php?id=<?= $perusahaan['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="perusahaan.php?set_default=<?= $perusahaan['id'] ?>" class="btn btn-sm btn-outline-success">Set Default</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Perusahaan -->
<div class="modal fade" id="tambahPerusahaanModal" tabindex="-1" aria-labelledby="tambahPerusahaanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="tambahPerusahaanModalLabel">Tambah Perusahaan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telepon" class="form-label">Telepon</label>
                            <input type="text" class="form-control" id="telepon" name="telepon">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="website" class="form-label">Website</label>
                        <input type="url" class="form-control" id="website" name="website" placeholder="https://">
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo Perusahaan</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept=".jpg,.jpeg,.png">
                        <div class="form-text">Format: JPG, JPEG, PNG. Maks. 2MB</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="jenis" class="form-label">Jenis Akun</label>
                        <select class="form-select" id="jenis" name="jenis">
                            <option value="regular">Regular</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_perusahaan" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>