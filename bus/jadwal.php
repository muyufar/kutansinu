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

// Ambil jadwal bus
$stmt = $db->prepare("SELECT * FROM pemesanan_bus WHERE id_bus = ? AND tanggal_berangkat >= CURDATE() ORDER BY tanggal_berangkat ASC, waktu_berangkat ASC");
$stmt->execute([$bus_id]);
$jadwal_list = $stmt->fetchAll();

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Jadwal Bus: <?php echo htmlspecialchars($bus['nama_bus']); ?></h2>
        <div>
            <a href="index.php" class="btn btn-secondary me-2">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
            <a href="pesan.php?id=<?php echo $bus_id; ?>" class="btn btn-primary">
                <i class="fas fa-ticket-alt"></i> Pesan Langsung
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

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
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
                        <!-- <strong>Harga per KM:</strong> <?php echo formatRupiah($bus['harga_per_km']); ?> -->
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Jadwal Terbooking</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($jadwal_list)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Waktu</th>
                                        <th>Rute</th>
                                        <th>Durasi</th>
                                        <th>Harga</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jadwal_list as $jadwal): ?>
                                        <tr>
                                            <td><?php echo date('d M Y', strtotime($jadwal['tanggal_berangkat'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($jadwal['waktu_berangkat'])); ?></td>
                                            <td><?php echo htmlspecialchars($jadwal['kota_asal'] . ' - ' . $jadwal['kota_tujuan']); ?></td>
                                            <td><?php echo formatDurasi($jadwal['estimasi_durasi']); ?></td>
                                            <td><?php echo formatRupiah($jadwal['harga']); ?></td>
                                            <td>
                                                <?php if ($jadwal['status'] == 'penuh'): ?>
                                                    <span class="badge bg-danger">Tidak Tersedia</span>
                                                <?php else: ?>
                                                    <a href="pesan_jadwal.php?id=<?php echo $jadwal['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-ticket-alt"></i> Pesan
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            Tidak ada jadwal tersedia untuk bus ini. Silakan gunakan fitur "Pesan Langsung" untuk memesan bus sesuai kebutuhan Anda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Fungsi untuk memformat durasi
function formatDurasi($menit)
{
    $jam = floor($menit / 60);
    $sisa_menit = $menit % 60;

    if ($jam > 0) {
        return $jam . ' jam ' . ($sisa_menit > 0 ? $sisa_menit . ' menit' : '');
    } else {
        return $sisa_menit . ' menit';
    }
}

include '../templates/footer.php';
?>