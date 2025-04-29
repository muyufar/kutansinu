<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek role user (hanya admin dan editor yang boleh mengakses halaman backup)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

// Verifikasi role user
if (!checkUserRole($db, $user_id, $default_company_id, 'editor')) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya Admin dan Editor yang dapat mengakses fitur backup.';
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

// Ambil data perusahaan default
$stmt = $db->prepare("SELECT * FROM perusahaan WHERE id = ?");
$stmt->execute([$default_company_id]);
$perusahaan = $stmt->fetch();

// Proses backup data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['backup_data'])) {
    $backup_type = validateInput($_POST['backup_type']);
    
    // Buat direktori backup jika belum ada
    $backup_dir = '../backup/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // Generate nama file backup
    $timestamp = date('Y-m-d_H-i-s');
    $company_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $perusahaan['nama']));
    $backup_filename = $company_slug . '_' . $timestamp;
    
    // Proses backup berdasarkan tipe
    switch ($backup_type) {
        case 'sql':
            // Dalam implementasi nyata, ini akan menggunakan mysqldump atau fungsi PHP untuk mengekspor database
            // Untuk contoh ini, kita hanya akan membuat file teks sederhana
            $backup_file = $backup_dir . $backup_filename . '.sql';
            $backup_content = "-- Backup database untuk " . $perusahaan['nama'] . " pada " . date('Y-m-d H:i:s') . "\n";
            $backup_content .= "-- Ini adalah contoh file backup SQL\n";
            
            file_put_contents($backup_file, $backup_content);
            $_SESSION['success'] = 'Backup SQL berhasil dibuat: ' . basename($backup_file);
            break;
            
        case 'excel':
            // Dalam implementasi nyata, ini akan menggunakan library seperti PhpSpreadsheet
            // Untuk contoh ini, kita hanya akan membuat file CSV sederhana
            $backup_file = $backup_dir . $backup_filename . '.csv';
            $backup_content = "Nama Perusahaan," . $perusahaan['nama'] . "\n";
            $backup_content .= "Tanggal Backup," . date('Y-m-d H:i:s') . "\n";
            $backup_content .= "\n";
            $backup_content .= "ID,Tanggal,Deskripsi,Jumlah\n";
            
            file_put_contents($backup_file, $backup_content);
            $_SESSION['success'] = 'Backup Excel berhasil dibuat: ' . basename($backup_file);
            break;
            
        case 'pdf':
            // Dalam implementasi nyata, ini akan menggunakan library seperti TCPDF atau FPDF
            // Untuk contoh ini, kita hanya akan membuat file teks dengan ekstensi .pdf
            $backup_file = $backup_dir . $backup_filename . '.pdf';
            $backup_content = "Backup PDF untuk " . $perusahaan['nama'] . " pada " . date('Y-m-d H:i:s') . "\n";
            $backup_content .= "Ini adalah contoh file backup PDF\n";
            
            file_put_contents($backup_file, $backup_content);
            $_SESSION['success'] = 'Backup PDF berhasil dibuat: ' . basename($backup_file);
            break;
            
        default:
            $_SESSION['error'] = 'Tipe backup tidak valid';
    }
    
    header('Location: backup.php');
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
                    <a href="karyawan.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> Karyawan
                    </a>
                    <a href="reset_data.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-trash-alt me-2"></i> Reset Data
                    </a>
                    <a href="backup.php" class="list-group-item list-group-item-action active">
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
            
            <!-- Backup Data -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Backup Data</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Backup data akan menyimpan semua data perusahaan Anda saat ini. Anda dapat menggunakan file backup ini untuk memulihkan data jika terjadi kehilangan data.
                    </div>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">Perusahaan</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($perusahaan['nama']) ?>" readonly>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Format Backup</label>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-database fa-3x text-primary mb-3"></i>
                                            <h5>SQL</h5>
                                            <p class="text-muted">Format database SQL</p>
                                            <button type="submit" name="backup_data" value="sql" class="btn btn-primary w-100" onclick="document.getElementById('backup_type').value='sql'">
                                                <i class="fas fa-download me-1"></i> Download SQL
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-file-excel fa-3x text-success mb-3"></i>
                                            <h5>Excel</h5>
                                            <p class="text-muted">Format spreadsheet Excel</p>
                                            <button type="submit" name="backup_data" value="excel" class="btn btn-success w-100" onclick="document.getElementById('backup_type').value='excel'">
                                                <i class="fas fa-download me-1"></i> Download Excel
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-file-pdf fa-3x text-danger mb-3"></i>
                                            <h5>PDF</h5>
                                            <p class="text-muted">Format dokumen PDF</p>
                                            <button type="submit" name="backup_data" value="pdf" class="btn btn-danger w-100" onclick="document.getElementById('backup_type').value='pdf'">
                                                <i class="fas fa-download me-1"></i> Download PDF
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <input type="hidden" id="backup_type" name="backup_type" value="sql">
                    </form>
                    
                    <!-- Riwayat Backup -->
                    <div class="mt-4">
                        <h5>Riwayat Backup</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Nama File</th>
                                        <th>Format</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $backup_dir = '../backup/';
                                    $backup_files = [];
                                    
                                    if (file_exists($backup_dir)) {
                                        $files = scandir($backup_dir);
                                        foreach ($files as $file) {
                                            if ($file != '.' && $file != '..' && !is_dir($backup_dir . $file)) {
                                                $company_slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $perusahaan['nama']));
                                                if (strpos($file, $company_slug) === 0) {
                                                    $backup_files[] = $file;
                                                }
                                            }
                                        }
                                        
                                        // Sort files by modification time (newest first)
                                        usort($backup_files, function($a, $b) use ($backup_dir) {
                                            return filemtime($backup_dir . $b) - filemtime($backup_dir . $a);
                                        });
                                    }
                                    
                                    if (empty($backup_files)):
                                    ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Belum ada riwayat backup</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($backup_files as $file): ?>
                                            <?php
                                            $file_path = $backup_dir . $file;
                                            $file_time = filemtime($file_path);
                                            $file_ext = pathinfo($file, PATHINFO_EXTENSION);
                                            $format_class = '';
                                            $format_icon = '';
                                            
                                            switch ($file_ext) {
                                                case 'sql':
                                                    $format_class = 'primary';
                                                    $format_icon = 'database';
                                                    break;
                                                case 'csv':
                                                case 'xlsx':
                                                    $format_class = 'success';
                                                    $format_icon = 'file-excel';
                                                    break;
                                                case 'pdf':
                                                    $format_class = 'danger';
                                                    $format_icon = 'file-pdf';
                                                    break;
                                                default:
                                                    $format_class = 'secondary';
                                                    $format_icon = 'file';
                                            }
                                            ?>
                                            <tr>
                                                <td><?= date('d M Y H:i', $file_time) ?></td>
                                                <td><?= htmlspecialchars($file) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $format_class ?>">
                                                        <i class="fas fa-<?= $format_icon ?> me-1"></i>
                                                        <?= strtoupper($file_ext) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="../backup/<?= $file ?>" download class="btn btn-sm btn-outline-primary" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>