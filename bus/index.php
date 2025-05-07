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

// Ambil daftar bus yang tersedia
$stmt = $db->prepare("SELECT * FROM bus WHERE status = 'tersedia' ORDER BY nama_bus ASC");
$stmt->execute();
$bus_list = $stmt->fetchAll();

// Ambil daftar tipe bus untuk dropdown
$stmt = $db->prepare("SELECT DISTINCT tipe FROM bus WHERE status = 'tersedia' ORDER BY tipe ASC");
$stmt->execute();
$tipe_bus_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pemesanan Bus</h2>
        <div>
            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#tambahBusModal">
                <i class="fas fa-plus"></i> Tambah Bus
            </button>
            <a href="verifikasi_pesanan.php" class="btn btn-warning me-2">
                <i class="fas fa-check-circle"></i> Verifikasi Pesanan
            </a>
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#pesanManualModal">
                <i class="fas fa-plus"></i> Pesan Manual
            </button>
            <a href="riwayat.php" class="btn btn-info">
                <i class="fas fa-history"></i> Riwayat Pemesanan
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

    <!-- Modal Pemesanan Manual -->
    <div class="modal fade" id="pesanManualModal" tabindex="-1" aria-labelledby="pesanManualModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="pesanManualModalLabel">Pemesanan Bus Manual</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="pesan_manual.php" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipe_bus" class="form-label">Tipe Bus</label>
                                <select class="form-select" id="tipe_bus" name="tipe_bus" required>
                                    <option value="">Pilih Tipe Bus</option>
                                    <?php foreach ($tipe_bus_list as $tipe): ?>
                                        <option value="<?php echo htmlspecialchars($tipe); ?>"><?php echo htmlspecialchars($tipe); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="jumlah_penumpang" class="form-label">Jumlah Penumpang</label>
                                <input type="number" class="form-control" id="jumlah_penumpang" name="jumlah_penumpang" min="1" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="kota_asal" class="form-label">Kota Asal</label>
                                <input type="text" class="form-control" id="kota_asal" name="kota_asal" required>
                            </div>
                            <div class="col-md-6">
                                <label for="kota_tujuan" class="form-label">Kota Tujuan</label>
                                <input type="text" class="form-control" id="kota_tujuan" name="kota_tujuan" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tanggal_berangkat" class="form-label">Tanggal Berangkat</label>
                                <input type="date" class="form-control" id="tanggal_berangkat" name="tanggal_berangkat" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="tanggal_kembali" class="form-label">Tanggal Kembali</label>
                                <input type="date" class="form-control" id="tanggal_kembali" name="tanggal_kembali" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="waktu_berangkat" class="form-label">Waktu Berangkat</label>
                                <input type="time" class="form-control" id="waktu_berangkat" name="waktu_berangkat" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fasilitas" class="form-label">Fasilitas Tambahan</label>
                                <select class="form-select" id="fasilitas" name="fasilitas[]" multiple>
                                    <option value="AC">AC</option>
                                    <option value="WiFi">WiFi</option>
                                    <option value="Toilet">Toilet</option>
                                    <option value="TV">TV</option>
                                    <option value="Karaoke">Karaoke</option>
                                </select>
                                <small class="text-muted">Tekan Ctrl untuk memilih beberapa</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan Tambahan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"></textarea>
                        </div>
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Kirim Permintaan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Pilih Bus</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($bus_list as $bus): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <?php if (!empty($bus['foto'])): ?>
                                        <img src="../uploads/bus/<?php echo htmlspecialchars($bus['foto']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($bus['nama_bus']); ?>" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                            <i class="fas fa-bus fa-5x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($bus['nama_bus']); ?></h5>
                                        <p class="card-text">
                                            <strong>Nomor Polisi:</strong> <?php echo htmlspecialchars($bus['nomor_polisi']); ?><br>
                                            <strong>Kapasitas:</strong> <?php echo $bus['kapasitas']; ?> Penumpang<br>
                                            <strong>Fasilitas:</strong> <?php echo htmlspecialchars($bus['fasilitas']); ?><br>
                                            <strong>Harga per KM:</strong> <?php echo formatRupiah($bus['harga_per_km']); ?>
                                        </p>
                                    </div>
                                    <div class="card-footer">
                                        <a href="jadwal.php?id=<?php echo $bus['id']; ?>" class="btn btn-primary w-100">Lihat Jadwal & Pesan</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($bus_list)): ?>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    Tidak ada bus yang tersedia saat ini. Silakan coba lagi nanti.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Rekomendasi Perjalanan</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Paket Wisata Keluarga</h5>
                                    <p class="card-text">Nikmati perjalanan wisata bersama keluarga dengan bus nyaman dan fasilitas lengkap.</p>
                                    <ul class="list-group list-group-flush mb-3">
                                        <li class="list-group-item">Durasi: 3 hari</li>
                                        <li class="list-group-item">Kapasitas: 30 orang</li>
                                        <li class="list-group-item">Termasuk makan 3x sehari</li>
                                    </ul>
                                    <a href="pesan_paket.php?tipe=keluarga" class="btn btn-success w-100">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Paket Study Tour</h5>
                                    <p class="card-text">Ideal untuk sekolah atau kampus yang ingin mengadakan study tour dengan harga terjangkau.</p>
                                    <ul class="list-group list-group-flush mb-3">
                                        <li class="list-group-item">Durasi: 2-5 hari</li>
                                        <li class="list-group-item">Kapasitas: 40-100 orang</li>
                                        <li class="list-group-item">Termasuk tiket masuk objek wisata</li>
                                    </ul>
                                    <a href="pesan_paket.php?tipe=study" class="btn btn-success w-100">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Paket Ziarah</h5>
                                    <p class="card-text">Perjalanan ziarah ke tempat-tempat religius dengan bus yang nyaman dan aman.</p>
                                    <ul class="list-group list-group-flush mb-3">
                                        <li class="list-group-item">Durasi: 1-7 hari</li>
                                        <li class="list-group-item">Kapasitas: 30-45 orang</li>
                                        <li class="list-group-item">Termasuk pemandu ziarah</li>
                                    </ul>
                                    <a href="pesan_paket.php?tipe=ziarah" class="btn btn-success w-100">Lihat Detail</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Script untuk validasi tanggal -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tanggalBerangkat = document.getElementById('tanggal_berangkat');
        const tanggalKembali = document.getElementById('tanggal_kembali');
        
        tanggalBerangkat.addEventListener('change', function() {
            tanggalKembali.min = this.value;
            if (tanggalKembali.value && tanggalKembali.value < this.value) {
                tanggalKembali.value = this.value;
            }
        });
    });
</script>

<?php include '../templates/footer.php'; ?>


<!-- Modal Tambah Bus -->
<div class="modal fade" id="tambahBusModal" tabindex="-1" aria-labelledby="tambahBusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="tambahBusModalLabel">Tambah Bus Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="tambah_bus.php" method="post" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nama_bus" class="form-label">Nama Bus</label>
                            <input type="text" class="form-control" id="nama_bus" name="nama_bus" required>
                        </div>
                        <div class="col-md-6">
                            <label for="tipe" class="form-label">Tipe Bus</label>
                            <input type="text" class="form-control" id="tipe" name="tipe" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nomor_polisi" class="form-label">Nomor Polisi</label>
                            <input type="text" class="form-control" id="nomor_polisi" name="nomor_polisi" required>
                        </div>
                        <div class="col-md-6">
                            <label for="kapasitas" class="form-label">Kapasitas</label>
                            <input type="number" class="form-control" id="kapasitas" name="kapasitas" min="1" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="harga_per_km" class="form-label">Harga per KM (Rp)</label>
                            <input type="number" class="form-control" id="harga_per_km" name="harga_per_km" min="1000" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="tersedia">Tersedia</option>
                                <option value="tidak tersedia">Tidak Tersedia</option>
                                <option value="dalam perbaikan">Dalam Perbaikan</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="fasilitas" class="form-label">Fasilitas</label>
                        <input type="text" class="form-control" id="fasilitas" name="fasilitas" placeholder="Contoh: AC, WiFi, Toilet, TV, Karaoke" required>
                    </div>
                    <div class="mb-3">
                        <label for="foto" class="form-label">Foto Bus</label>
                        <input type="file" class="form-control" id="foto" name="foto" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, JPEG. Maks: 2MB</small>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>