<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_helper.php';

// Cek login
requireLogin();

// Gunakan fungsi helper untuk mendapatkan data user dan cek akses Nugrosir
$user_id = $_SESSION['user_id'];
$user = getUserData($db, $user_id);
$is_nugrosir = checkNugrosirAccess($db, $user_id);

if (!$is_nugrosir) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini.';
    header('Location: /kutansinu/index.php');
    exit();
}
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
    // Validasi input
    $bus_id = (int)$_GET['id']; // Pastikan bus_id diambil dari parameter GET
    $tanggal_berangkat = date('Y-m-d', strtotime($_POST['tanggal_berangkat']));
    $waktu_berangkat = validateInput($_POST['waktu_berangkat']);
    $kota_asal = validateInput($_POST['kota_asal']);
    $nama_pemesan = validateInput($_POST['nama_pemesan']);
    $kontak_pemesan = validateInput($_POST['kontak_pemesan']);
    $kota_tujuan = validateInput($_POST['kota_tujuan']);
    $titik_jemput = validateInput($_POST['titik_jemput']);
    $latitude = validateInput($_POST['latitude']);
    $longitude = validateInput($_POST['longitude']);
    $jumlah_penumpang = (int)validateInput($_POST['jumlah_penumpang']);
    $total_harga = preg_replace('/[^\d]/', '', $_POST['total_harga']);
    $total_harga = (float)$total_harga;
    $catatan = validateInput($_POST['catatan']);

    // Validasi kapasitas
    if ($jumlah_penumpang > $bus['kapasitas']) {
        $_SESSION['error'] = 'Jumlah penumpang melebihi kapasitas bus';
        header('Location: pesan.php?id=' . $bus_id);
        exit();
    }

    // Validasi tanggal menggunakan helper
    $dateError = validateBookingDate($tanggal_berangkat);
    if ($dateError) {
        $_SESSION['error'] = $dateError;
        header('Location: pesan.php?id=' . $bus_id);
        exit();
    }

    try {
        // Upload bukti pembayaran
        $bukti_pembayaran = [];
        if (isset($_FILES['bukti_pembayaran']) && !empty($_FILES['bukti_pembayaran']['name'][0])) {
            $bukti_pembayaran = uploadBuktiPembayaran($_FILES['bukti_pembayaran']);
            if ($bukti_pembayaran === false) {
                $_SESSION['error'] = 'Gagal mengunggah bukti pembayaran';
                header('Location: pesan.php?id=' . $bus_id);
                exit();
            }
        }

        // Validasi parameter sebelum pengecekan ketersediaan bus
        if (!$bus_id) {
            throw new Exception('ID Bus tidak valid');
        }

        if (!$tanggal_berangkat) {
            throw new Exception('Tanggal keberangkatan tidak valid');
        }

        // Cek ketersediaan bus
        $existing_booking = checkBusAvailability($db, $bus_id, $tanggal_berangkat);
        if ($existing_booking) {
            $_SESSION['error'] = 'Bus sudah dipesan pada tanggal ' . date('d/m/Y', strtotime($tanggal_berangkat));
            header('Location: pesan.php?id=' . $bus_id);
            exit();
        }

        // Proses pembayaran
        // Validasi jenis pembayaran & pastikan jumlah_bayar sesuai
        $jenis_pembayaran = validateInput($_POST['jenis_pembayaran']);
        $total_harga = preg_replace('/[^\d]/', '', $_POST['total_harga']);
        $total_harga = (float)$total_harga;

        $jumlah_bayar = preg_replace('/[^\d]/', '', $_POST['jumlah_bayar']);
        $jumlah_bayar = (float)$jumlah_bayar;

        // Jika pembayaran lunas, paksa jumlah_bayar = total_harga
        if ($jenis_pembayaran === 'lunas') {
            $jumlah_bayar = $total_harga;
        }

        // Validasi jumlah pembayaran
        if ($jenis_pembayaran === 'dp' && $jumlah_bayar >= $total_harga) {
            $_SESSION['error'] = 'Untuk pembayaran DP, jumlah yang dibayar harus kurang dari total harga';
            header('Location: pesan.php?id=' . $bus_id);
            exit();
        }

        if ($jenis_pembayaran === 'lunas' && $jumlah_bayar != $total_harga) {
            $_SESSION['error'] = 'Untuk pembayaran lunas, jumlah yang dibayar harus sama dengan total harga';
            header('Location: pesan.php?id=' . $bus_id);
            exit();
        }

        // Set initial status based on payment type
        $initial_status = ($jenis_pembayaran == 'dp') ? 'dibayar_dp' : 'dibayar';

        // Hitung sisa pembayaran untuk DP
        $sisa_pembayaran = ($jenis_pembayaran == 'dp') ? ($total_harga - $jumlah_bayar) : 0;

        // Set jumlah_bayar based on payment type
        if ($jenis_pembayaran == 'lunas') {
            $jumlah_bayar = $total_harga; // For full payment, jumlah_bayar equals total_harga
        }

        // Debug final values
        error_log("Final Values - Status: " . $initial_status);
        error_log("Final Values - Jumlah Bayar: " . $jumlah_bayar);
        error_log("Final Values - Pembayaran DP: " . ($jenis_pembayaran == 'dp' ? $jumlah_bayar : 0));
        error_log("Final Values - Sisa Pembayaran: " . $sisa_pembayaran);

        // Simpan pemesanan
        $stmt = $db->prepare("INSERT INTO pemesanan_bus (
            id_user, id_bus, tanggal_pemesanan, tanggal_berangkat, waktu_berangkat, 
            kota_asal, nama_pemesan, kontak_pemesan, kota_tujuan, jumlah_penumpang, 
            total_harga, status, catatan, jenis_pembayaran, 
            jumlah_bayar, pembayaran_dp, sisa_pembayaran, dp_created_at, 
            titik_jemput, latitude, longitude, 
            id_jadwal_bus, bukti_transfer_admin, catatan_admin, tanggal_verifikasi
        ) VALUES (
            ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, '', NULL
        )");

        $params = [
            $user_id,
            $bus_id,
            $tanggal_berangkat,
            $waktu_berangkat,
            $kota_asal,
            $nama_pemesan,
            $kontak_pemesan,
            $kota_tujuan,
            $jumlah_penumpang,
            $total_harga,
            $initial_status,
            $catatan,
            $jenis_pembayaran,
            $jumlah_bayar,
            ($jenis_pembayaran == 'dp') ? $jumlah_bayar : null,
            $sisa_pembayaran,
            ($jenis_pembayaran == 'dp') ? date('Y-m-d H:i:s') : null,
            $titik_jemput,
            $latitude,
            $longitude
        ];

        $stmt->execute($params);
        $pemesanan_id = $db->lastInsertId();

        // Simpan bukti pembayaran
        if (!empty($bukti_pembayaran)) {
            $stmt_bukti = $db->prepare("INSERT INTO bukti_pembayaran_bus (pemesanan_id, nama_file, jenis_pembayaran) VALUES (?, ?, ?)");
            foreach ($bukti_pembayaran as $file) {
                $stmt_bukti->execute([$pemesanan_id, $file, $jenis_pembayaran]);
            }
        }

        $_SESSION['success'] = '<div class="text-success">Bus berhasil dipesan untuk tanggal ' . date('d/m/Y', strtotime($tanggal_berangkat)) . '. ' .
            ($jenis_pembayaran == 'dp' ?
                'Silakan lakukan pembayaran DP sebesar ' . formatRupiah($jumlah_bayar) . '. Sisa pembayaran sebesar ' . formatRupiah($sisa_pembayaran) . ' dapat dibayar sebelum keberangkatan.' :
                'Silakan lakukan pembayaran lunas sebesar ' . formatRupiah($jumlah_bayar)) .
            '</div>';
        header('Location: riwayat.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Terjadi kesalahan: ' . $e->getMessage();
        header('Location: pesan.php?id=' . $bus_id);
        exit();
    }
}

// Header
include '../templates/header.php';
?>

<head>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
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
                    </p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Daftar Pemesanan Bus Ini</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Ambil data pemesanan untuk bus ini
                    $stmt = $db->prepare("SELECT pb.*, u.username as nama_pemesan 
                                        FROM pemesanan_bus pb 
                                        JOIN users u ON pb.id_user = u.id 
                                        WHERE pb.id_bus = ? 
                                        ORDER BY pb.tanggal_berangkat DESC");
                    $stmt->execute([$bus_id]);
                    $pemesanan_list = $stmt->fetchAll();

                    if (count($pemesanan_list) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($pemesanan_list as $pemesanan):
                                // Tentukan warna status
                                $status_class = '';
                                $status_text = '';
                                switch ($pemesanan['status']) {
                                    case 'pending':
                                        $status_class = 'info';
                                        $status_text = 'Menunggu Verifikasi';
                                        break;
                                    case 'dibayar_dp':
                                        $status_class = 'success';
                                        $status_text = 'Pembayaran DP';
                                        break;
                                    case 'dibayar':
                                        $status_class = 'success';
                                        $status_text = 'Lunas';
                                        break;
                                    case 'ditolak':
                                        $status_class = 'danger';
                                        $status_text = 'Ditolak';
                                        break;
                                    case 'dibatalkan':
                                        $status_class = 'danger';
                                        $status_text = 'Dibatalkan';
                                        break;
                                    case 'selesai':
                                        $status_class = 'secondary';
                                        $status_text = 'Selesai';
                                        break;
                                }
                            ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <?php echo date('Y-m-d', strtotime($pemesanan['tanggal_berangkat'])); ?>
                                                <small class="text-muted">(<?php echo $pemesanan['waktu_berangkat']; ?>)</small>
                                            </h6>
                                            <p class="mb-1">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($pemesanan['nama_pemesan']); ?> •
                                                <i class="fas fa-users"></i> <?php echo $pemesanan['jumlah_penumpang']; ?> orang
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($pemesanan['kota_asal']); ?> →
                                                <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Belum ada pemesanan untuk bus ini.</p>
                    <?php endif; ?>
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
                                <label for="nama_pemesan" class="form-label">Nama Pemesan</label>
                                <input type="text" class="form-control" id="nama_pemesan" name="nama_pemesan" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="kontak_pemesan" class="form-label">Kontak Pemesan</label>
                                <input type="text" class="form-control" id="kontak_pemesan" name="kontak_pemesan" required>
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
                                <label for="tanggal_berangkat" class="form-label">Tanggal Keberangkatan</label>
                                <input type="date"
                                    class="form-control"
                                    id="tanggal_berangkat"
                                    name="tanggal_berangkat"
                                    value="<?php echo date('Y-m-d'); ?>"
                                    min="<?php echo date('Y-m-d'); ?>"
                                    onchange="formatTanggal(this)"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="waktu_berangkat" class="form-label">Waktu Keberangkatan</label>
                                <input type="time" class="form-control" id="waktu_berangkat" name="waktu_berangkat" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <div class="mb-3">
                                    <label for="titik_jemput" class="form-label">Alamat Titik Jemput</label>
                                    <textarea class="form-control" id="titik_jemput" name="titik_jemput" rows="2" required></textarea>
                                    <small class="text-muted">Alamat lengkap titik penjemputan</small>
                                </div>
                                <label class="form-label">Pilih Lokasi di Maps</label>
                                <div class="input-group mb-3">
                                    <input type="text" class="form-control" id="search-location" placeholder="Cari lokasi...">
                                    <button class="btn btn-outline-secondary" type="button" id="search-button">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                </div>
                                <div id="map" style="height: 300px;" class="rounded"></div>
                                <input type="hidden" id="latitude" name="latitude">
                                <input type="hidden" id="longitude" name="longitude">
                                <small class="text-muted">Klik pada peta atau cari lokasi untuk menentukan titik jemput</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jumlah_penumpang" class="form-label">Jumlah Penumpang</label>
                                <input type="number" class="form-control" id="jumlah_penumpang" name="jumlah_penumpang" required min="1" max="<?php echo $bus['kapasitas']; ?>">
                                <small class="text-muted">Maksimal <?php echo $bus['kapasitas']; ?> penumpang</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="total_harga" class="form-label">Total Harga</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="total_harga" name="total_harga" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="jenis_pembayaran" class="form-label">Jenis Pembayaran</label>
                                <select class="form-control" id="jenis_pembayaran" name="jenis_pembayaran" required>
                                    <option value="lunas">Pembayaran Lunas</option>
                                    <option value="dp">Uang Muka (DP)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="jumlah_bayar" class="form-label">Jumlah yang dibayar</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="jumlah_bayar" name="jumlah_bayar" required>
                                </div>
                                <small class="text-muted">Isi jumlah yang akan dibayar sesuai dengan jenis pembayaran yang dipilih</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="bukti_pembayaran" class="form-label">Bukti Pembayaran (Opsional)</label>
                            <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran[]" accept="image/*,.pdf" multiple>
                            <small class="text-muted">Upload bukti pembayaran untuk mempercepat proses verifikasi. Untuk pembayaran DP, Anda dapat mengupload bukti DP dan bukti pelunasan.</small>
                        </div>
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan Tambahan</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Informasi Pembayaran</h6>
                            <p class="mb-0">Silakan lakukan pembayaran ke rekening berikut:</p>
                            <ul class="mb-0">
                                <li>Bank BSI: 3320221926 a.n. PT NUGO INTL</li>
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


<?php include '../templates/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inisialisasi map
        var mapElement = document.getElementById('map');
        if (mapElement) {
            var map = L.map('map').setView([-7.479, 110.217], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            var marker;

            // Fungsi untuk menambah marker dan mengisi alamat otomatis
            function addMarker(lat, lng) {
                if (marker) {
                    map.removeLayer(marker);
                }
                marker = L.marker([lat, lng]).addTo(map);
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;

                // Reverse geocoding untuk mendapatkan alamat
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.display_name) {
                            document.getElementById('titik_jemput').value = data.display_name;

                            if (data.address) {
                                let kota = data.address.city || data.address.town || data.address.village || '';
                                if (kota) {
                                    document.getElementById('kota_asal').value = kota;
                                }
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }

            // Event ketika map diklik
            map.on('click', function(e) {
                addMarker(e.latlng.lat, e.latlng.lng);
            });

            // Pencarian lokasi
            document.getElementById('search-button').addEventListener('click', function() {
                var query = document.getElementById('search-location').value;
                if (!query) return;

                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            var lat = parseFloat(data[0].lat);
                            var lon = parseFloat(data[0].lon);
                            map.setView([lat, lon], 16);
                            addMarker(lat, lon);
                        } else {
                            alert('Lokasi tidak ditemukan');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat mencari lokasi');
                    });
            });

            // Tambahkan event untuk input pencarian
            document.getElementById('search-location').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('search-button').click();
                }
            });
        }
    });

    // Format rupiah function
    function formatRupiah(angka) {
        let value = angka.toString().replace(/[^\d]/g, '');
        if (value === '') return '';
        value = parseInt(value);
        return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Format input fields
    const totalHargaInput = document.getElementById('total_harga');
    const jumlahBayarInput = document.getElementById('jumlah_bayar');
    const jenisPembayaranSelect = document.getElementById('jenis_pembayaran');

    // Format total harga input
    totalHargaInput.addEventListener('input', function(e) {
        const cursorPos = this.selectionStart;
        let value = this.value.replace(/[^\d]/g, '');
        const formattedValue = formatRupiah(value);
        this.value = formattedValue;
        const newCursorPos = cursorPos + (formattedValue.length - value.length);
        this.setSelectionRange(newCursorPos, newCursorPos);
    });

    // Format jumlah bayar input
    jumlahBayarInput.addEventListener('input', function(e) {
        const cursorPos = this.selectionStart;
        let value = this.value.replace(/[^\d]/g, '');
        const formattedValue = formatRupiah(value);
        this.value = formattedValue;
        const newCursorPos = cursorPos + (formattedValue.length - value.length);
        this.setSelectionRange(newCursorPos, newCursorPos);
    });

    // Handle jenis pembayaran change
    jenisPembayaranSelect.addEventListener('change', function() {
        const totalHarga = parseInt(totalHargaInput.value.replace(/[^\d]/g, ''));
        if (this.value === 'lunas') {
            jumlahBayarInput.value = formatRupiah(totalHarga);
            jumlahBayarInput.readOnly = true;
        } else {
            jumlahBayarInput.value = '';
            jumlahBayarInput.readOnly = false;
        }
    });

    // Set initial state
    if (jenisPembayaranSelect.value === 'lunas') {
        const totalHarga = parseInt(totalHargaInput.value.replace(/[^\d]/g, ''));
        jumlahBayarInput.value = formatRupiah(totalHarga);
        jumlahBayarInput.readOnly = true;
    }

    function formatDate(date) {
        if (!date) return '';
        return date; // Tanggal sudah dalam format Y-m-d dari input type="date"
    }

    document.getElementById('jenis_pembayaran').addEventListener('change', function() {
        const totalHarga = document.getElementById('total_harga').value.replace(/[^\d]/g, '');
        if (this.value === 'lunas') {
            document.getElementById('jumlah_bayar').value = formatRupiah(totalHarga);
            document.getElementById('jumlah_bayar').readOnly = true;
        } else {
            document.getElementById('jumlah_bayar').value = '';
            document.getElementById('jumlah_bayar').readOnly = false;
        }
    });
</script>
<script>
    function formatTanggal(input) {
        let date = new Date(input.value);
        let year = date.getFullYear();
        let month = String(date.getMonth() + 1).padStart(2, '0');
        let day = String(date.getDate()).padStart(2, '0');
        input.value = `${year}-${month}-${day}`;
    }

    // Set format tanggal saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        let tanggalInput = document.getElementById('tanggal_berangkat');
        if (tanggalInput.value) {
            formatTanggal(tanggalInput);
        }
    });
</script>