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

// Ambil data jadwal bus untuk kalender (termasuk yang sudah lewat)
$stmt = $db->prepare("SELECT jb.id, jb.id_bus, jb.tanggal_berangkat, jb.waktu_berangkat, jb.kota_asal, jb.kota_tujuan, jb.status, b.nama_bus, b.tipe 
                    FROM pemesanan_bus jb 
                    JOIN bus b ON jb.id_bus = b.id 
                    ORDER BY jb.tanggal_berangkat, jb.waktu_berangkat");
$stmt->execute();
$jadwal_bus_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format data jadwal untuk kalender
$events = [];
foreach ($jadwal_bus_list as $jadwal) {
    $datetime = $jadwal['tanggal_berangkat'] . 'T' . $jadwal['waktu_berangkat'];
    $color = '';
    $status_text = '';

    // Set warna berdasarkan status
    if ($jadwal['status'] == 'tersedia') {
        $color = '#28a745'; // hijau
        $status_text = 'Tersedia';
    } elseif ($jadwal['status'] == 'penuh') {
        $color = '#dc3545'; // merah
        $status_text = 'Penuh';
    } elseif ($jadwal['status'] == 'dibatalkan') {
        $color = '#ffc107'; // kuning
        $status_text = 'Dibatalkan';
    } else {
        $color = '#ffc107'; // kuning untuk status lainnya
        $status_text = ucfirst($jadwal['status']);
    }

    // Cek apakah jadwal sudah lewat
    $isPast = strtotime($datetime) < time();
    if ($isPast) {
        $color = '#6c757d'; // abu-abu untuk jadwal yang sudah lewat
    }

    $events[] = [
        'id' => $jadwal['id'],
        'title' => $jadwal['nama_bus'] . ' (' . $jadwal['tipe'] . '): ' . $jadwal['kota_asal'] . ' - ' . $jadwal['kota_tujuan'],
        'start' => $datetime,
        'color' => $color,
        'url' => 'jadwal.php?id=' . $jadwal['id_bus'],
        'extendedProps' => [
            'status' => $jadwal['status'],
            'statusText' => $status_text,
            'isPast' => $isPast,
            'bus' => $jadwal['nama_bus'],
            'tipe' => $jadwal['tipe'],
            'rute' => $jadwal['kota_asal'] . ' - ' . $jadwal['kota_tujuan'],
            'waktu' => $jadwal['waktu_berangkat']
        ]
    ];
}

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
            <button type="button" class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#jadwalBusModal">
                <i class="fas fa-calendar-alt"></i> Lihat Jadwal Bus
            </button>
            <a href="verifikasi_pesanan.php" class="btn btn-warning me-2">
                <i class="fas fa-check-circle"></i> Verifikasi Pesanan
            </a>
            <!-- <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#pesanManualModal">
                <i class="fas fa-plus"></i> Pesan Manual
            </button> -->
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
                                        <div class="position-relative">
                                            <img src="../uploads/bus/<?php echo htmlspecialchars($bus['foto']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($bus['nama_bus']); ?>">
                                            <div class="position-absolute top-0 end-0 p-2">
                                                <a href="edit_bus.php?id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-light bg-opacity-75 me-1 hover-full-opacity">
                                                    <i class="fas fa-edit text-primary"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-light bg-opacity-75 hover-full-opacity" onclick="hapusBus(<?php echo $bus['id']; ?>)">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="position-relative">
                                            <div class="bg-light d-flex align-items-center justify-content-center mb-3 rounded" style="height: 200px;">
                                                <i class="fas fa-bus fa-5x text-muted"></i>
                                            </div>
                                            <div class="position-absolute top-0 end-0 p-2">
                                                <a href="edit_bus.php?id=<?php echo $bus['id']; ?>" class="btn btn-sm btn-light bg-opacity-75 me-1 hover-full-opacity">
                                                    <i class="fas fa-edit text-primary"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-light bg-opacity-75 hover-full-opacity" onclick="hapusBus(<?php echo $bus['id']; ?>)">
                                                    <i class="fas fa-trash text-danger"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($bus['nama_bus']); ?></h5>
                                        <p class="card-text">
                                            <strong>Nomor Polisi:</strong> <?php echo htmlspecialchars($bus['nomor_polisi']); ?><br>
                                            <strong>Kapasitas:</strong> <?php echo $bus['kapasitas']; ?> Penumpang<br>
                                            <strong>Fasilitas:</strong> <?php echo htmlspecialchars($bus['fasilitas']); ?>
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

    <!-- Kalender Jadwal Bus -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Kalender Jadwal Bus</h5>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
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

<!-- jQuery untuk tooltip -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css" rel="stylesheet">

<!-- CSS untuk tooltip kalender -->
<style>
    .tooltip-jadwal {
        font-size: 14px;
        line-height: 1.5;
    }

    .fc-event {
        cursor: pointer;
    }

    .tooltip {
        z-index: 10000;
    }
</style>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/locales/id.js"></script>

<!-- Bootstrap Tooltip -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script untuk validasi tanggal dan kalender -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validasi tanggal
        const tanggalBerangkat = document.getElementById('tanggal_berangkat');
        const tanggalKembali = document.getElementById('tanggal_kembali');

        if (tanggalBerangkat && tanggalKembali) {
            tanggalBerangkat.addEventListener('change', function() {
                tanggalKembali.min = this.value;
                if (tanggalKembali.value && tanggalKembali.value < this.value) {
                    tanggalKembali.value = this.value;
                }
            });
        }

        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
        // Inisialisasi kalender
        const calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                locale: 'id',
                events: <?php echo json_encode($events); ?>,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventClick: function(info) {
                    const props = info.event.extendedProps;
                    if (props.isPast) {
                        info.jsEvent.preventDefault();
                        alert('Jadwal ini sudah lewat.');
                        return;
                    }
                    if (props.status === 'dibatalkan') {
                        info.jsEvent.preventDefault();
                        alert('Jadwal ini telah dibatalkan.');
                        return;
                    }
                },
                eventDidMount: function(info) {
                    const props = info.event.extendedProps;
                    let color = '#6c757d'; // abu-abu default
                    if (props.status === 'tersedia' && !props.isPast) {
                        color = '#28a745'; // hijau
                    } else if (props.status === 'penuh') {
                        color = '#dc3545'; // merah
                    } else if (props.status === 'dibatalkan') {
                        color = '#ffc107'; // kuning
                    }

                    // Atur warna latar belakang event
                    info.el.style.backgroundColor = color;

                    // Tooltip
                    const isPast = props.isPast;
                    let statusText = isPast ? 'Sudah lewat' : props.statusText || ucfirst(props.status);
                    let tooltipContent = `
        <div class="tooltip-jadwal">
            <strong>Bus:</strong> ${props.bus} (${props.tipe})<br>
            <strong>Rute:</strong> ${props.rute}<br>
            <strong>Waktu:</strong> ${props.waktu}<br>
            <strong>Status:</strong> ${statusText}
        </div>
    `;

                    $(info.el).tooltip({
                        title: tooltipContent,
                        placement: 'top',
                        trigger: 'hover',
                        container: 'body',
                        html: true
                    });
                }
            });
            calendar.render();
        }
    });
</script>

<?php include '../templates/footer.php'; ?>


<!-- Modal Tambah Bus -->
<div class="modal fade" id="tambahBusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
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
                            <label class="form-label">Tipe Bus</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_ekonomi" value="Ekonomi" required>
                                    <label class="form-check-label" for="tipe_ekonomi">
                                        Bus Ekonomi
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_vip" value="VIP" required>
                                    <label class="form-check-label" for="tipe_vip">
                                        Bus VIP
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="tipe" id="tipe_executive" value="Executive" required>
                                    <label class="form-check-label" for="tipe_executive">
                                        Bus Executive
                                    </label>
                                </div>
                            </div>
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
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="tersedia">Tersedia</option>
                            <option value="tidak tersedia">Tidak Tersedia</option>
                            <option value="dalam perbaikan">Dalam Perbaikan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fasilitas Bus</label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fasilitas[]" value="AC" id="fasilitas_ac">
                                    <label class="form-check-label" for="fasilitas_ac">AC</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fasilitas[]" value="WiFi" id="fasilitas_wifi">
                                    <label class="form-check-label" for="fasilitas_wifi">WiFi</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fasilitas[]" value="Toilet" id="fasilitas_toilet">
                                    <label class="form-check-label" for="fasilitas_toilet">Toilet</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fasilitas[]" value="Reclining Seat" id="fasilitas_seat">
                                    <label class="form-check-label" for="fasilitas_seat">Reclining Seat</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fasilitas[]" value="TV/Video" id="fasilitas_tv">
                                    <label class="form-check-label" for="fasilitas_tv">TV/Video</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="fasilitas[]" value="Bagasi" id="fasilitas_bagasi">
                                    <label class="form-check-label" for="fasilitas_bagasi">Bagasi</label>
                                </div>
                            </div>
                        </div>
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

<!-- Modal Jadwal Bus -->
<div class="modal fade" id="lihatJadwalBusModal" tabindex="-1" aria-labelledby="lihatJadwalBusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="lihatJadwalBusModalLabel">Lihat Jadwal Bus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="tambah_jadwal.php" method="post">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_bus" class="form-label">Pilih Bus</label>
                            <select class="form-select" id="id_bus" name="id_bus" required>
                                <option value="">Pilih Bus</option>
                                <?php foreach ($bus_list as $bus): ?>
                                    <option value="<?php echo $bus['id']; ?>"><?php echo htmlspecialchars($bus['nama_bus']); ?> - <?php echo htmlspecialchars($bus['nomor_polisi']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal_berangkat" class="form-label">Tanggal Berangkat</label>
                            <input type="date" class="form-control" id="tanggal_berangkat" name="tanggal_berangkat" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="waktu_berangkat" class="form-label">Waktu Berangkat</label>
                            <input type="time" class="form-control" id="waktu_berangkat" name="waktu_berangkat" required>
                        </div>
                        <!-- <div class="col-md-6">
                            <label for="estimasi_durasi" class="form-label">Estimasi Durasi (Jam)</label>
                            <input type="number" class="form-control" id="estimasi_durasi" name="estimasi_durasi" min="1" required>
                        </div> -->
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
                            <label for="harga" class="form-label">Harga Tiket (Rp)</label>
                            <input type="number" class="form-control" id="harga" name="harga" min="1000" required>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="tersedia">Tersedia</option>
                                <option value="penuh">Penuh</option>
                                <option value="dibatalkan">Dibatalkan</option>
                            </select>
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info">Simpan Jadwal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="jadwalBusModal" tabindex="-1" aria-labelledby="jadwalBusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="jadwalBusModalLabel">Jadwal Bus</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Bus</th>
                                <th>Tanggal Berangkat</th>
                                <th>Waktu Berangkat</th>
                                <th>Kota Asal</th>
                                <th>Kota Tujuan</th>
                                <!-- <th>Estimasi Durasi</th> -->
                                <th>Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Ambil data jadwal bus (semua jadwal, tidak hanya milik user yang login)
                            $stmt = $db->query("SELECT j.*, b.nama_bus, b.tipe, b.kapasitas 
                                              FROM pemesanan_bus j 
                                              JOIN bus b ON j.id_bus = b.id 
                                              WHERE j.status != 'dibatalkan' AND j.status != 'ditolak'
                                              ORDER BY j.tanggal_berangkat ASC, j.waktu_berangkat ASC");
                            $jadwal_list = $stmt->fetchAll();
                            $no = 1;

                            foreach ($jadwal_list as $jadwal):
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo htmlspecialchars($jadwal['nama_bus']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($jadwal['tanggal_berangkat'])); ?></td>
                                    <td><?php echo date('H:i', strtotime($jadwal['waktu_berangkat'])); ?></td>
                                    <td><?php echo htmlspecialchars($jadwal['kota_asal']); ?></td>
                                    <td><?php echo htmlspecialchars($jadwal['kota_tujuan']); ?></td>
                                    <td><?php echo formatRupiah($jadwal['total_harga']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $jadwal['status'] == 'tersedia' ? 'success' : ($jadwal['status'] == 'penuh' ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($jadwal['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($jadwal['status'] == 'tersedia'): ?>
                                            <a href="pesan.php?jadwal_id=<?php echo $jadwal['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-ticket-alt"></i> Pesan
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($jadwal_list)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">Tidak ada jadwal bus yang tersedia</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>

    </div>

</div>