<?php
session_start();
require_once 'config/database.php';
require_once 'config/functions.php';

// Tidak perlu login untuk mengakses halaman ini

// Ambil daftar bus yang tersedia
$stmt = $db->prepare("SELECT * FROM bus WHERE status = 'tersedia' ORDER BY nama_bus ASC");
$stmt->execute();
$bus_list = $stmt->fetchAll();

// Ambil daftar tipe bus untuk dropdown
$stmt = $db->prepare("SELECT DISTINCT tipe FROM bus WHERE status = 'tersedia' ORDER BY tipe ASC");
$stmt->execute();
$tipe_bus_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Inisialisasi variabel pencarian
$tanggal_berangkat = isset($_POST['tanggal_berangkat']) ? $_POST['tanggal_berangkat'] : '';
$tanggal_pulang = isset($_POST['tanggal_pulang']) ? $_POST['tanggal_pulang'] : '';
$tipe_bus = isset($_POST['tipe_bus']) ? $_POST['tipe_bus'] : '';
$kota_asal = isset($_POST['kota_asal']) ? $_POST['kota_asal'] : '';
$kota_tujuan = isset($_POST['kota_tujuan']) ? $_POST['kota_tujuan'] : '';
$jumlah_penumpang = isset($_POST['jumlah_penumpang']) ? (int)$_POST['jumlah_penumpang'] : 0;

// Inisialisasi array untuk hasil pencarian
$available_buses = [];

// Jika form pencarian disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($tanggal_berangkat)) {
    // Query untuk mencari bus yang tersedia berdasarkan kriteria
    $query = "SELECT b.* FROM bus b WHERE b.status = 'tersedia'";
    $params = [];
    
    // Filter berdasarkan tipe bus jika dipilih
    if (!empty($tipe_bus)) {
        $query .= " AND b.tipe = ?";
        $params[] = $tipe_bus;
    }
    
    // Filter berdasarkan kapasitas jika jumlah penumpang diisi
    if ($jumlah_penumpang > 0) {
        $query .= " AND b.kapasitas >= ?";
        $params[] = $jumlah_penumpang;
    }
    
    // Tambahkan kondisi untuk memeriksa jadwal yang sudah ada
    $query .= " AND b.id NOT IN (
        SELECT DISTINCT pb.id_bus FROM pemesanan_bus pb 
        WHERE (pb.status NOT IN ('dibatalkan', 'ditolak')) AND 
            pb.tanggal_berangkat = ?
    )";
    
    $params[] = $tanggal_berangkat;
    
    $query .= " ORDER BY b.nama_bus ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $available_buses = $stmt->fetchAll();
}

// Header
include 'templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Cek Ketersediaan Bus</h2>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Kembali ke Beranda
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Cari Bus yang Tersedia</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tanggal_berangkat" class="form-label">Tanggal Berangkat</label>
                                <input type="date" class="form-control" id="tanggal_berangkat" name="tanggal_berangkat" 
                                       value="<?php echo $tanggal_berangkat; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="tanggal_pulang" class="form-label">Tanggal Pulang</label>
                                <input type="date" class="form-control" id="tanggal_pulang" name="tanggal_pulang" 
                                       value="<?php echo $tanggal_pulang; ?>" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="tipe_bus" class="form-label">Tipe Bus</label>
                                <select class="form-select" id="tipe_bus" name="tipe_bus">
                                    <option value="">Semua Tipe</option>
                                    <?php foreach ($tipe_bus_list as $tipe): ?>
                                        <option value="<?php echo htmlspecialchars($tipe); ?>" <?php echo ($tipe_bus === $tipe) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipe); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="jumlah_penumpang" class="form-label">Jumlah Penumpang</label>
                                <input type="number" class="form-control" id="jumlah_penumpang" name="jumlah_penumpang" 
                                       value="<?php echo $jumlah_penumpang; ?>" min="1">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Cari Bus
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Hasil Pencarian Bus</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_buses)): ?>
                            <div class="row">
                                <?php foreach ($available_buses as $bus): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header bg-light">
                                                <h5 class="mb-0"><?php echo htmlspecialchars($bus['nama_bus']); ?></h5>
                                            </div>
                                            <?php if (!empty($bus['foto'])): ?>
                                                <img src="uploads/bus/<?php echo htmlspecialchars($bus['foto']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($bus['nama_bus']); ?>" style="height: 200px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                                    <i class="fas fa-bus fa-5x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <p class="card-text">
                                                    <strong>Tipe:</strong> <?php echo htmlspecialchars($bus['tipe']); ?><br>
                                                    <strong>Kapasitas:</strong> <?php echo $bus['kapasitas']; ?> Penumpang<br>
                                                    <strong>Fasilitas:</strong> <?php echo htmlspecialchars($bus['fasilitas']); ?><br>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-white text-center">
                                                <?php if (isset($_SESSION['user_id'])): ?>
                                                    <a href="bus/pesan.php?id=<?php echo $bus['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-ticket-alt"></i> Pesan Sekarang
                                                    </a>
                                                <?php else: ?>
                                                    <a href="login.php?redirect=bus/pesan.php?id=<?php echo $bus['id']; ?>" class="btn btn-primary">
                                                        <i class="fas fa-sign-in-alt"></i> Login untuk Memesan
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Tidak ada bus yang tersedia untuk tanggal yang Anda pilih. Silakan coba tanggal lain atau ubah kriteria pencarian Anda.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>