<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Ambil data user
$user_id = $_SESSION['user_id'];

// Cek ID perusahaan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID perusahaan tidak valid';
    header('Location: perusahaan.php');
    exit();
}

$perusahaan_id = (int)$_GET['id'];

// Verifikasi bahwa perusahaan ini terkait dengan user
$stmt = $db->prepare("SELECT COUNT(*) FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
$stmt->execute([$user_id, $perusahaan_id]);
$is_valid = $stmt->fetchColumn() > 0;

if (!$is_valid) {
    $_SESSION['error'] = 'Anda tidak memiliki akses ke perusahaan ini';
    header('Location: perusahaan.php');
    exit();
}

// Ambil data perusahaan
$stmt = $db->prepare("SELECT * FROM perusahaan WHERE id = ?");
$stmt->execute([$perusahaan_id]);
$perusahaan = $stmt->fetch(PDO::FETCH_ASSOC);

// if ($perusahaan) {
//     echo "<pre>Query OK, data diterima:</pre>";
// } else {
//     echo "<pre>Data tidak ditemukan!</pre>";
//     exit;
// }
// echo "<pre>";
// print_r($perusahaan);
// echo "</pre>";
// Debug: Cek apakah data perusahaan berhasil diambil
if (!$perusahaan) {
    $_SESSION['error'] = 'Perusahaan tidak ditemukan';
    header('Location: perusahaan.php');
    exit();
}

// Proses update perusahaan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_perusahaan'])) {
    $nama = validateInput($_POST['nama']);
    $alamat = validateInput($_POST['alamat']);
    $telepon = validateInput($_POST['telepon']);
    $email = validateInput($_POST['email']);
    $website = validateInput($_POST['website'] ?? '');
    $jenis = validateInput($_POST['jenis'] ?? 'regular');

    // Upload logo jika ada
    $logo = isset($perusahaan['logo']) ? $perusahaan['logo'] : null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $upload_dir = '../uploads/perusahaan/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['logo']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
            $logo = 'uploads/perusahaan/' . $file_name;

            // Hapus logo lama jika ada
            if (isset($perusahaan['logo']) && $perusahaan['logo'] && file_exists('../' . $perusahaan['logo'])) {
                unlink('../' . $perusahaan['logo']);
            }
        }
    }

    try {
        $stmt = $db->prepare("UPDATE perusahaan SET nama = ?, alamat = ?, telepon = ?, email = ?, website = ?, logo = ?, jenis = ? WHERE id = ?");
        $stmt->execute([$nama, $alamat, $telepon, $email, $website, $logo, $jenis, $perusahaan_id]);

        $_SESSION['success'] = 'Perusahaan berhasil diperbarui';
        header('Location: perusahaan_edit.php?id=' . $perusahaan_id);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui perusahaan: ' . $e->getMessage();
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

            <!-- Edit Perusahaan -->
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Edit Perusahaan</h5>
                    <a href="perusahaan.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="text-center mb-4">
                            <?php if (isset($perusahaan['logo']) && $perusahaan['logo']): ?>
                                <img src="../<?= $perusahaan['logo'] ?>" alt="<?= htmlspecialchars($perusahaan['nama']) ?>" class="img-fluid" style="max-height: 150px;">
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                    <i class="fas fa-building fa-4x text-secondary"></i>
                                </div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <label for="logo" class="btn btn-sm btn-outline-primary">Ganti Logo</label>
                                <input type="file" id="logo" name="logo" class="d-none" accept=".jpg,.jpeg,.png">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($perusahaan['nama'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="2"><?= htmlspecialchars($perusahaan['alamat'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="telepon" class="form-label">Telepon</label>
                                <input type="text" class="form-control" id="telepon" name="telepon" value="<?= htmlspecialchars($perusahaan['telepon'] ?? '') ?>">
                                <!-- Debug -->
                                <!-- Nilai telepon: <?= $perusahaan['alamat'] ?? 'kosong' ?> -->

                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($perusahaan['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="website" class="form-label">Website</label>
                            <input type="url" class="form-control" id="website" name="website" placeholder="https://" value="<?= htmlspecialchars($perusahaan['website'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label for="jenis" class="form-label">Jenis Akun</label>
                            <select class="form-select" id="jenis" name="jenis">
                                <option value="regular" <?= ($perusahaan['jenis'] ?? '') == 'regular' ? 'selected' : '' ?>>Regular</option>
                                <option value="premium" <?= ($perusahaan['jenis'] ?? '') == 'premium' ? 'selected' : '' ?>>Premium</option>
                            </select>
                        </div>

                        <button type="submit" name="update_perusahaan" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview logo sebelum upload
    document.getElementById('logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.querySelector('.card-body img');
                if (img) {
                    img.src = e.target.result;
                } else {
                    const div = document.querySelector('.bg-light.rounded');
                    const newImg = document.createElement('img');
                    newImg.src = e.target.result;
                    newImg.alt = 'Logo Perusahaan';
                    newImg.classList.add('img-fluid');
                    newImg.style.maxHeight = '150px';
                    div.parentNode.replaceChild(newImg, div);
                }
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<?php include '../templates/footer.php'; ?>