<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek role user (hanya nugrosir yang boleh mengakses halaman pemesanan bus)
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Cek parameter ID bus
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID bus tidak valid';
    header('Location: index.php');
    exit();
}

$bus_id = (int)$_GET['id'];

// Ambil data bus
$stmt = $db->prepare("SELECT * FROM bus WHERE id = ?");
$stmt->execute([$bus_id]);
$bus = $stmt->fetch();

if (!$bus) {
    $_SESSION['error'] = 'Bus tidak ditemukan';
    header('Location: index.php');
    exit();
}

// Proses pemesanan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggal_berangkat = validateInput($_POST['tanggal_berangkat']);
    $waktu_berangkat = validateInput($_POST['waktu_berangkat']);
    $kota_asal = validateInput($_POST['kota_asal']);
    $kota_tujuan = validateInput($_POST['kota_tujuan']);
    $jumlah_penumpang = (int)validateInput($_POST['jumlah_penumpang']);
    $total_harga = (float)validateInput($_POST['total_harga']);
    $catatan = validateInput($_POST['catatan']);
    
    // Validasi kapasitas
    if ($jumlah_penumpang > $bus['kapasitas']) {
        $_SESSION['error'] = 'Jumlah penumpang melebihi kapasitas bus';
        header('Location: pesan.php?id=' . $bus_id);
        exit();
    }
    
    // Validasi tanggal
    if (strtotime($tanggal_berangkat) < strtotime(date('Y-m-d'))) {
        $_SESSION['error'] = 'Tanggal keberangkatan tidak boleh kurang dari hari ini';
        header('Location: pesan.php?id=' . $bus_id);
        exit();
    }
    
    // Upload bukti pembayaran jika ada
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/pembayaran_bus/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['bukti_pembayaran']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $target_file)) {
            $bukti_pembayaran = 'uploads/pembayaran_bus/' . $file_name;
        } else {
            $_SESSION['error'] = 'Gagal mengunggah bukti pembayaran';
            header('Location: pesan.php?id=' . $bus_id);
            exit();
        }
    }
    
    try {
        // Simpan data pemesanan
        $stmt = $db->prepare("INSERT INTO pemesanan_bus (id_user, id_bus, tanggal_pemesanan, tanggal_berangkat, waktu_berangkat, kota_asal, kota_tujuan, jumlah_penumpang, total_harga, status, catatan, bukti_pembayaran) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $status = !empty($bukti_pembayaran) ? 'dibayar' : 'menunggu_pembayaran';
        
        $stmt->execute([
            $user_id, 
            $bus_id, 
            $tanggal_berangkat, 
            $waktu_berangkat, 
            $kota_asal, 
            $kota_tujuan, 
            $jumlah_penumpang, 
            $total_harga, 
            $status, 
            $catatan, 
            $bukti_pembayaran
        ]);
        
        $_SESSION['success'] = 'Pemesanan bus berhasil dibuat. ' . ($status == 'dibayar' ? 'Pembayaran Anda sedang diverifikasi.' : 'Silakan lakukan pembayaran.');
        header('Location: riwayat.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal membuat pemesanan: ' . $e->getMessage();
        header('Location: pesan.php?id=' . $bus_id);
        exit();
    }
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pemesanan Bus</h2>
        <a href="jadwal.php?id=<?php echo $bus_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
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
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Detail Bus</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($bus['foto'])): ?>
                        <img src="../uploads/bus/<?php echo htmlspecialchars($bus['foto']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($bus['nama_bus']); ?>">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded" style="height: 200px;">
                            <i class="fas fa-bus fa-5x text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <h5><?php echo htmlspecialchars($bus['nama_bus']); ?></h5>
                    <p>
                        <strong>Nomor Polisi:</strong> <?php echo htmlspecialchars($bus['nomor_polisi']); ?><br>
                        <strong>Kapasitas:</strong> <?php echo $bus['kapasitas']; ?> Penumpang<br>
                        <strong>Fasilitas:</strong> <?php echo htmlspecialchars($bus['fasilitas']); ?><br>
                        <strong>Harga per KM:</strong> <?php echo formatRupiah($bus['harga_per_km']); ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Form Pemesanan</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tanggal_berangkat" class="form-label">Tanggal Keberangkatan</label>
                                <input type="date" class="form-control" id="tanggal_berangkat" name="tanggal_berangkat" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="waktu_berangkat" class="form-label">Waktu Keberangkatan</label>
                                <input type="time" class="form-control" id="waktu_berangkat" name="waktu_berangkat" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="kota_asal" class="form-label">Kota Asal</label>
                                <input type="text" class="form-control" id="kota_asal" name="kota_asal" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="kota_tujuan" class="form-label">Kota Tujuan</label>
                                <input type="text" class="form-control" id="kota_tujuan" name="kota_tujuan" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jumlah_penumpang" class="form-label">Jumlah Penumpang</label>
                                <input type="number" class="form-control" id="jumlah_penumpang" name="jumlah_penumpang" required min="1" max="<?php echo $bus['kapasitas']; ?>">
                                <small class="text-muted">Maksimal <?php echo $bus['kapasitas']; ?> penumpang</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jarak" class="form-label">Estimasi Jarak (KM)</label>
                                <input type="number" class="form-control" id="jarak" name="jarak" required min="1" step="0.1">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="total_harga" class="form-label">Total Harga</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control" id="total_harga" name="total_harga" readonly>
                            </div>
                            <small class="text-muted">Harga dihitung otomatis berdasarkan jarak dan harga per KM</small>
                        </div>
                        <div class="mb-3">
                            <label for="bukti_pembayaran" class="form-label">Bukti Pembayaran (Opsional)</label>
                            <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*,.pdf">
                            <small class="text-muted">Upload bukti pembayaran untuk mempercepat proses verifikasi</small>
                        </div>
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan Tambahan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Informasi Pembayaran</h6>
                            <p class="mb-0">Silakan lakukan pembayaran ke rekening berikut:</p>
                            <ul class="mb-0">
                                <li>Bank BCA: 1234567890 a.n. PT Nugrosir Indonesia</li>
                                <li>Bank Mandiri: 0987654321 a.n. PT Nugrosir Indonesia</li>
                            </ul>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Pesan Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script untuk menghitung total harga -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const hargaPerKm = <?php echo $bus['harga_per_km']; ?>;
        const jarakInput = document.getElementById('jarak');
        const totalHargaInput = document.getElementById('total_harga');
        
        // Fungsi untuk menghitung total harga
        function hitungTotalHarga() {
            const jarak = parseFloat(jarakInput.value) || 0;
            const totalHarga = jarak * hargaPerKm;
            
            // Format sebagai mata uang
            totalHargaInput.value = new Intl.NumberFormat('id-ID').format(totalHarga);
        }
        
        // Hitung total harga saat nilai jarak berubah
        jarakInput.addEventListener('input', hitungTotalHarga);
        
        // Hitung total harga saat halaman dimuat
        hitungTotalHarga();
    });
</script>

<?php include '../templates/footer.php'; ?>