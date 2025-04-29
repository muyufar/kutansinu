<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Ambil data user
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama_lengkap = validateInput($_POST['nama_lengkap']);
    $no_hp = validateInput($_POST['no_hp']);
    $email = validateInput($_POST['email']);
    $alamat = validateInput($_POST['alamat']);
    
    // Upload foto profil jika ada
    $foto_profil = $user['foto_profil'];
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $upload_dir = '../uploads/profil/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['foto_profil']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
            $foto_profil = 'uploads/profil/' . $file_name;
            
            // Hapus foto lama jika ada
            if ($user['foto_profil'] && file_exists('../' . $user['foto_profil'])) {
                unlink('../' . $user['foto_profil']);
            }
        }
    }
    
    try {
        $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ?, alamat = ?, foto_profil = ? WHERE id = ?");
        $stmt->execute([$nama_lengkap, $email, $no_hp, $alamat, $foto_profil, $user_id]);
        
        $_SESSION['success'] = 'Profil berhasil diperbarui';
        header('Location: profil.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui profil: ' . $e->getMessage();
    }
}

// Ambil data perusahaan default
$default_company = null;
if ($user['default_company']) {
    $stmt = $db->prepare("SELECT * FROM perusahaan WHERE id = ?");
    $stmt->execute([$user['default_company']]);
    $default_company = $stmt->fetch();
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
                    <a href="profil.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-user me-2"></i> Profil
                    </a>
                    <a href="perusahaan.php" class="list-group-item list-group-item-action">
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
            <!-- Profil User -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Profil</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <?php if ($user['foto_profil']): ?>
                                <img src="../<?= $user['foto_profil'] ?>" alt="Foto Profil" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                                    <span class="fs-1"><?= substr($user['nama_lengkap'], 0, 1) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <label for="foto_profil" class="btn btn-sm btn-outline-primary">Ganti foto profil</label>
                                <input type="file" id="foto_profil" name="foto_profil" class="d-none" accept=".jpg,.jpeg,.png">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="no_hp" class="form-label">No HP</label>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            <?php if ($user['email']): ?>
                                <div class="form-text text-success"><i class="fas fa-check-circle"></i> Terverifikasi</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3"><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary w-100">Simpan</button>
                    </form>
                </div>
            </div>

            <!-- Detail User -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Detail User</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">User ID</label>
                        <input type="text" class="form-control" value="<?= generateUserID($user['id']) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Pendaftaran</label>
                        <input type="text" class="form-control" value="<?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Default Company</label>
                        <input type="text" class="form-control" value="<?= $default_company ? htmlspecialchars($default_company['nama']) : 'Belum diatur' ?>" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview foto profil sebelum upload
    document.getElementById('foto_profil').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.querySelector('.rounded-circle');
                if (img) {
                    img.src = e.target.result;
                } else {
                    const div = document.querySelector('.rounded-circle');
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.alt = 'Foto Profil';
                    newImg.classList.add('rounded-circle');
                    newImg.style.width = '100px';
                    newImg.style.height = '100px';
                    newImg.style.objectFit = 'cover';
                    div.parentNode.replaceChild(newImg, div);
                }
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php
// Helper function untuk generate User ID
function generateUserID($id) {
    return 'MTM' . strtoupper(substr(md5($id), 0, 4));
}

// Footer
include '../templates/footer.php';
?>