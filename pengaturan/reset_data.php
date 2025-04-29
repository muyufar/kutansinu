<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek role user (hanya admin yang boleh mengakses halaman reset data)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

// Verifikasi role user
if (!checkUserRole($db, $user_id, $default_company_id, 'admin')) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya Admin yang dapat mengakses fitur reset data.';
    header('Location: /kutansinu/index.php');
    exit();
}

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

// Proses reset data transaksi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_transaksi'])) {
    $confirmation_code = validateInput($_POST['confirmation_code']);
    
    // Verifikasi kode konfirmasi
    if ($confirmation_code !== 'RESET') {
        $_SESSION['error'] = 'Kode konfirmasi tidak valid. Masukkan "RESET" untuk melanjutkan.';
    } else {
        try {
            $db->beginTransaction();
            
            // Hapus semua transaksi terkait perusahaan ini
            $stmt = $db->prepare("DELETE FROM transaksi WHERE perusahaan_id = ?");
            $stmt->execute([$default_company_id]);
            
            // Reset saldo akun
            $stmt = $db->prepare("UPDATE akun SET saldo = 0 WHERE perusahaan_id = ?");
            $stmt->execute([$default_company_id]);
            
            $db->commit();
            $_SESSION['success'] = 'Data transaksi berhasil direset';
            header('Location: reset_data.php');
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Gagal mereset data transaksi: ' . $e->getMessage();
        }
    }
}

// Proses reset data kontak
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_kontak'])) {
    $confirmation_code = validateInput($_POST['confirmation_code']);
    
    // Verifikasi kode konfirmasi
    if ($confirmation_code !== 'RESET') {
        $_SESSION['error'] = 'Kode konfirmasi tidak valid. Masukkan "RESET" untuk melanjutkan.';
    } else {
        try {
            // Hapus semua kontak terkait perusahaan ini
            $stmt = $db->prepare("DELETE FROM kontak WHERE perusahaan_id = ?");
            $stmt->execute([$default_company_id]);
            
            $_SESSION['success'] = 'Data kontak berhasil direset';
            header('Location: reset_data.php');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Gagal mereset data kontak: ' . $e->getMessage();
        }
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
                    <a href="karyawan.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> Karyawan
                    </a>
                    <a href="reset_data.php" class="list-group-item list-group-item-action active">
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
            
            <!-- Reset Data Transaksi -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Reset Data Transaksi</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Perhatian!</strong> Tindakan ini akan menghapus semua data transaksi dan mengatur ulang saldo akun menjadi 0. Data yang sudah dihapus tidak dapat dikembalikan.
                    </div>
                    
                    <form method="POST" onsubmit="return confirmReset('transaksi')">
                        <div class="mb-3">
                            <label for="confirmation_code_transaksi" class="form-label">Ketik "RESET" untuk konfirmasi</label>
                            <input type="text" class="form-control" id="confirmation_code_transaksi" name="confirmation_code" required>
                        </div>
                        
                        <button type="submit" name="reset_transaksi" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Reset Data Transaksi
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Reset Data Kontak -->
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">Reset Data Kontak</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> <strong>Perhatian!</strong> Tindakan ini akan menghapus semua data kontak. Data yang sudah dihapus tidak dapat dikembalikan.
                    </div>
                    
                    <form method="POST" onsubmit="return confirmReset('kontak')">
                        <div class="mb-3">
                            <label for="confirmation_code_kontak" class="form-label">Ketik "RESET" untuk konfirmasi</label>
                            <input type="text" class="form-control" id="confirmation_code_kontak" name="confirmation_code" required>
                        </div>
                        
                        <button type="submit" name="reset_kontak" class="btn btn-danger">
                            <i class="fas fa-trash-alt me-1"></i> Reset Data Kontak
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmReset(type) {
        const confirmationCode = document.getElementById(`confirmation_code_${type}`).value;
        if (confirmationCode !== 'RESET') {
            alert('Kode konfirmasi tidak valid. Masukkan "RESET" untuk melanjutkan.');
            return false;
        }
        
        return confirm(`Anda yakin ingin mereset data ${type}? Tindakan ini tidak dapat dibatalkan.`);
    }
</script>

<?php include '../templates/footer.php'; ?>