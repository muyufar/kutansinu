<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek role user (hanya admin dan editor yang boleh mengakses halaman pengaturan utama)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

// Jika user memiliki perusahaan default, verifikasi role user
if ($default_company_id) {
    if (!checkUserRole($db, $user_id, $default_company_id, 'editor')) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk mengubah pengaturan utama. Hanya Admin dan Editor yang dapat mengakses fitur ini.';
        header('Location: /kutansinu/index.php');
        exit();
    }
}

// Ambil data user
$user_id = $_SESSION['user_id'];

// Ambil pengaturan user
try {
    // Cek apakah tabel pengaturan sudah ada
    $tableExists = false;
    try {
        $checkTable = $db->query("SHOW TABLES LIKE 'pengaturan'");
        $tableExists = ($checkTable->rowCount() > 0);
    } catch (PDOException $e) {
        // Tabel tidak ada, lanjutkan dengan pengaturan default
    }
    
    if ($tableExists) {
        $stmt = $db->prepare("SELECT * FROM pengaturan WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $pengaturan = $stmt->fetch();
        
        // Jika belum ada pengaturan, buat default
        if (!$pengaturan) {
            $stmt = $db->prepare("INSERT INTO pengaturan (user_id, preview_transaksi, format_angka, created_at) 
                                 VALUES (?, 1, '1.000.000,00', NOW())");
            $stmt->execute([$user_id]);
            
            // Ambil pengaturan yang baru dibuat
            $stmt = $db->prepare("SELECT * FROM pengaturan WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $pengaturan = $stmt->fetch();
        }
    } else {
        // Tabel belum ada, gunakan pengaturan default
        $pengaturan = [
            'preview_transaksi' => 1,
            'format_angka' => '1.000.000,00'
        ];
        $_SESSION['warning'] = 'Tabel pengaturan belum tersedia. Silakan jalankan file SQL untuk membuat tabel pengaturan.';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Gagal mengakses pengaturan: ' . $e->getMessage();
    // Gunakan pengaturan default
    $pengaturan = [
        'preview_transaksi' => 1,
        'format_angka' => '1.000.000,00'
    ];
}

// Proses update pengaturan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_pengaturan'])) {
    $preview_transaksi = isset($_POST['preview_transaksi']) ? 1 : 0;
    $format_angka = validateInput($_POST['format_angka']);
    
    try {
        // Cek apakah tabel pengaturan sudah ada
        $checkTable = $db->query("SHOW TABLES LIKE 'pengaturan'");
        $tableExists = ($checkTable->rowCount() > 0);
        
        if ($tableExists) {
            // Cek apakah data pengaturan untuk user ini sudah ada
            $checkData = $db->prepare("SELECT id FROM pengaturan WHERE user_id = ?");
            $checkData->execute([$user_id]);
            
            if ($checkData->rowCount() > 0) {
                // Update data yang sudah ada
                $stmt = $db->prepare("UPDATE pengaturan SET preview_transaksi = ?, format_angka = ? WHERE user_id = ?");
                $stmt->execute([$preview_transaksi, $format_angka, $user_id]);
            } else {
                // Insert data baru jika belum ada
                $stmt = $db->prepare("INSERT INTO pengaturan (user_id, preview_transaksi, format_angka, created_at) 
                                     VALUES (?, ?, ?, NOW())");
                $stmt->execute([$user_id, $preview_transaksi, $format_angka]);
            }
            
            $_SESSION['success'] = 'Pengaturan berhasil diperbarui';
        } else {
            // Tabel belum ada, simpan pengaturan ke session sementara
            $_SESSION['temp_pengaturan'] = [
                'preview_transaksi' => $preview_transaksi,
                'format_angka' => $format_angka
            ];
            $_SESSION['warning'] = 'Tabel pengaturan belum tersedia. Pengaturan disimpan sementara di session.';
        }
        
        header('Location: pengaturan_utama.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui pengaturan: ' . $e->getMessage();
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
                    <a href="pengaturan_utama.php" class="list-group-item list-group-item-action active">
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
            
            <!-- Pengaturan Utama -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Pengaturan Utama</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <h6 class="mb-3">Menu Transaksi</h6>
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="preview_transaksi" name="preview_transaksi" <?= ($pengaturan && $pengaturan['preview_transaksi'] == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="preview_transaksi">Preview Transaksi</label>
                            </div>
                            <div class="form-text">Sistem akan menampilkan preview data transaksi sebelum disimpan ke server</div>
                        </div>
                        
                        <h6 class="mb-3">Format Penulisan Angka</h6>
                        <div class="mb-4">
                            <p class="form-text mb-2">Format penulisan akan ditampilkan sebagai standar di semua menu transaksi, laporan dll</p>
                            <select class="form-select" name="format_angka">
                                <option value="1.000.000,00" <?= ($pengaturan && $pengaturan['format_angka'] == '1.000.000,00') ? 'selected' : '' ?>>1.000.000,00</option>
                                <option value="1,000,000.00" <?= ($pengaturan && $pengaturan['format_angka'] == '1,000,000.00') ? 'selected' : '' ?>>1,000,000.00</option>
                                <option value="1 000 000.00" <?= ($pengaturan && $pengaturan['format_angka'] == '1 000 000.00') ? 'selected' : '' ?>>1 000 000.00</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="update_pengaturan" class="btn btn-primary w-100">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>