<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_report_helper.php';

// Cek login
requireLogin();

ensureBusReportSchema($db);
$idPerusahaan = getNugoCompanyId($db);

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$busSql = "SELECT * FROM bus WHERE status = 'tersedia'";
$busParams = [];
if ($idPerusahaan) {
    $busSql .= " AND id_perusahaan = ?";
    $busParams[] = $idPerusahaan;
}
$busSql .= " ORDER BY nama_bus ASC";
$stmt = $db->prepare($busSql);
$stmt->execute($busParams);
$bus_list = $stmt->fetchAll();

$stmt = $db->prepare("SELECT DISTINCT tipe FROM bus WHERE status = 'tersedia'" . ($idPerusahaan ? " AND id_perusahaan = ?" : "") . " ORDER BY tipe ASC");
$stmt->execute($idPerusahaan ? [$idPerusahaan] : []);
$tipe_bus_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

$calendarData = fetchBusCalendarEvents($db, $idPerusahaan);
$events = $calendarData['events'];
$calendarStats = $calendarData['stats'];
$calendarTotal = $calendarData['total'];

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

    <!-- Kalender Operasional Bus -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="calendar-modern-card">
                <div class="calendar-modern-header">
                    <div class="calendar-modern-title">
                        <div class="calendar-icon-wrap">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">Kalender Operasional Bus</h5>
                            <p class="mb-0 text-muted small">Sinkron trip, maintenance & pemesanan — <?= (int) $calendarTotal ?> kegiatan</p>
                        </div>
                    </div>
                    <div class="calendar-stat-chips">
                        <span class="stat-chip stat-trip"><i class="fas fa-route"></i> <?= (int) $calendarStats['trip'] ?> Trip</span>
                        <span class="stat-chip stat-maint"><i class="fas fa-tools"></i> <?= (int) $calendarStats['maintenance'] ?> Maintenance</span>
                        <span class="stat-chip stat-order"><i class="fas fa-ticket-alt"></i> <?= (int) $calendarStats['pemesanan'] ?> Pemesanan</span>
                        <a href="operasional_bus.php" class="btn btn-sm btn-light border ms-1">Kelola Data</a>
                    </div>
                </div>

                <div class="calendar-legend">
                    <span class="legend-item"><span class="legend-dot legend-trip"></span> Trip Operasional</span>
                    <span class="legend-item"><span class="legend-dot legend-maint"></span> Maintenance</span>
                    <span class="legend-item"><span class="legend-dot legend-order"></span> Pemesanan Online</span>
                    <span class="legend-item"><span class="legend-dot legend-past"></span> Sudah Lewat</span>
                </div>

                <div class="calendar-body-wrap">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

<!--    <div class="row">-->
<!--        <div class="col-md-12">-->
<!--            <div class="card">-->
<!--                <div class="card-header bg-success text-white">-->
<!--                    <h5 class="mb-0">Rekomendasi Perjalanan</h5>-->
<!--                </div>-->
<!--                <div class="card-body">-->
<!--                    <div class="row">-->
<!--                        <div class="col-md-4 mb-3">-->
<!--                            <div class="card h-100">-->
<!--                                <div class="card-body">-->
<!--                                    <h5 class="card-title">Paket Wisata Keluarga</h5>-->
<!--                                    <p class="card-text">Nikmati perjalanan wisata bersama keluarga dengan bus nyaman dan fasilitas lengkap.</p>-->
<!--                                    <ul class="list-group list-group-flush mb-3">-->
<!--                                        <li class="list-group-item">Durasi: 3 hari</li>-->
<!--                                        <li class="list-group-item">Kapasitas: 30 orang</li>-->
<!--                                        <li class="list-group-item">Termasuk makan 3x sehari</li>-->
<!--                                    </ul>-->
<!--                                    <a href="pesan_paket.php?tipe=keluarga" class="btn btn-success w-100">Lihat Detail</a>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                        <div class="col-md-4 mb-3">-->
<!--                            <div class="card h-100">-->
<!--                                <div class="card-body">-->
<!--                                    <h5 class="card-title">Paket Study Tour</h5>-->
<!--                                    <p class="card-text">Ideal untuk sekolah atau kampus yang ingin mengadakan study tour dengan harga terjangkau.</p>-->
<!--                                    <ul class="list-group list-group-flush mb-3">-->
<!--                                        <li class="list-group-item">Durasi: 2-5 hari</li>-->
<!--                                        <li class="list-group-item">Kapasitas: 40-100 orang</li>-->
<!--                                        <li class="list-group-item">Termasuk tiket masuk objek wisata</li>-->
<!--                                    </ul>-->
<!--                                    <a href="pesan_paket.php?tipe=study" class="btn btn-success w-100">Lihat Detail</a>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                        <div class="col-md-4 mb-3">-->
<!--                            <div class="card h-100">-->
<!--                                <div class="card-body">-->
<!--                                    <h5 class="card-title">Paket Ziarah</h5>-->
<!--                                    <p class="card-text">Perjalanan ziarah ke tempat-tempat religius dengan bus yang nyaman dan aman.</p>-->
<!--                                    <ul class="list-group list-group-flush mb-3">-->
<!--                                        <li class="list-group-item">Durasi: 1-7 hari</li>-->
<!--                                        <li class="list-group-item">Kapasitas: 30-45 orang</li>-->
<!--                                        <li class="list-group-item">Termasuk pemandu ziarah</li>-->
<!--                                    </ul>-->
<!--                                    <a href="pesan_paket.php?tipe=ziarah" class="btn btn-success w-100">Lihat Detail</a>-->
<!--                                </div>-->
<!--                            </div>-->
<!--                        </div>-->
<!--                    </div>-->
<!--                </div>-->
<!--            </div>-->
<!--        </div>-->
<!--    </div>-->
<!--</div>-->

</div><!-- /.container -->

<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.css" rel="stylesheet">

<style>
    .calendar-modern-card {
        background: #fff;
        border-radius: 20px;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        overflow: hidden;
    }

    .calendar-modern-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        background: linear-gradient(135deg, #0f766e 0%, #059669 45%, #10b981 100%);
        color: #fff;
    }

    .calendar-modern-title {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .calendar-modern-title h5 {
        color: #fff;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .calendar-modern-title .text-muted {
        color: rgba(255, 255, 255, 0.82) !important;
    }

    .calendar-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.16);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        backdrop-filter: blur(8px);
    }

    .calendar-stat-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
    }

    .stat-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.75rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.18);
        border: 1px solid rgba(255, 255, 255, 0.22);
        color: #fff;
        backdrop-filter: blur(6px);
    }

    .calendar-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 0.85rem 1.5rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }

    .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        font-size: 0.82rem;
        color: #475569;
        font-weight: 500;
    }

    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }

    .legend-trip { background: #059669; }
    .legend-maint { background: #d97706; }
    .legend-order { background: #2563eb; }
    .legend-past { background: #64748b; }

    .calendar-body-wrap {
        padding: 1rem 1.25rem 1.5rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }

    #calendar {
        --fc-border-color: #e2e8f0;
        --fc-today-bg-color: rgba(16, 185, 129, 0.08);
    }

    .fc .fc-toolbar {
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1rem !important;
    }

    .fc .fc-toolbar-title {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.03em;
    }

    .fc .fc-button {
        background: #fff !important;
        border: 1px solid #cbd5e1 !important;
        color: #334155 !important;
        font-weight: 600 !important;
        border-radius: 10px !important;
        padding: 0.45rem 0.85rem !important;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: all 0.18s ease;
    }

    .fc .fc-button:hover,
    .fc .fc-button:focus {
        background: #f1f5f9 !important;
        border-color: #94a3b8 !important;
        color: #0f172a !important;
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: #059669 !important;
        border-color: #059669 !important;
        color: #fff !important;
    }

    .fc .fc-col-header-cell {
        background: #f1f5f9;
        border-color: #e2e8f0;
    }

    .fc .fc-col-header-cell-cushion {
        font-weight: 700;
        color: #475569;
        font-size: 0.78rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0.65rem 0;
    }

    .fc .fc-scrollgrid {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        position: relative;
        z-index: 1;
    }

    .fc .fc-daygrid-day {
        transition: background 0.15s ease;
    }

    .fc .fc-daygrid-day:hover {
        background: rgba(241, 245, 249, 0.65);
    }

    .fc .fc-daygrid-day.fc-day-today {
        background: rgba(16, 185, 129, 0.07) !important;
    }

    .fc .fc-daygrid-day-number {
        color: #334155;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 0.45rem;
    }

    .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
        background: #059669;
        color: #fff;
        border-radius: 8px;
        width: 1.75rem;
        height: 1.75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin: 0.2rem;
    }

    .fc .fc-daygrid-event {
        border: none !important;
        border-radius: 8px !important;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 3px 6px;
        margin-bottom: 2px;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        cursor: pointer;
    }

    .fc .fc-daygrid-event:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
        z-index: 5;
    }

    .fc .fc-more-link {
        color: #059669;
        font-weight: 700;
        font-size: 0.75rem;
    }

    .fc .fc-list-event:hover td {
        background: #f0fdf4;
    }

    .event-detail-grid {
        display: grid;
        grid-template-columns: 120px 1fr;
        gap: 0.5rem 1rem;
        font-size: 0.92rem;
    }

    .event-detail-grid dt {
        color: #64748b;
        font-weight: 600;
        margin: 0;
    }

    .event-detail-grid dd {
        margin: 0;
        color: #0f172a;
    }

    .event-type-badge {
        display: inline-block;
        padding: 0.25rem 0.65rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .event-type-badge.trip { background: #d1fae5; color: #065f46; }
    .event-type-badge.maintenance { background: #fef3c7; color: #92400e; }
    .event-type-badge.pemesanan { background: #dbeafe; color: #1e40af; }

    @media (max-width: 768px) {
        .calendar-modern-header {
            padding: 1rem;
        }

        .fc .fc-toolbar-title {
            font-size: 1.05rem;
        }

        .calendar-legend {
            padding: 0.75rem 1rem;
            gap: 0.65rem;
        }
    }

    [data-theme="dark"] .event-type-badge.trip { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
    [data-theme="dark"] .event-type-badge.maintenance { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }
    [data-theme="dark"] .event-type-badge.pemesanan { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
    [data-theme="dark"] .event-detail-grid dt { color: #94a3b8; }
    [data-theme="dark"] .event-detail-grid dd { color: #e2e8f0; }
</style>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.0/locales/id.js"></script>

<!-- Script kalender operasional -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
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

        const rupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
        const detailModal = document.getElementById('eventDetailModal');
        const detailModalBody = document.getElementById('eventDetailBody');
        const detailModalTitle = document.getElementById('eventDetailModalLabel');
        const detailModalLink = document.getElementById('eventDetailLink');
        const bsModal = detailModal ? new bootstrap.Modal(detailModal) : null;

        function showEventDetail(props, title) {
            if (!bsModal) return;
            detailModalTitle.textContent = title;
            const badgeClass = props.type || 'trip';
            let html = `<div class="mb-3"><span class="event-type-badge ${badgeClass}">${props.typeLabel || props.type}</span>`;
            if (props.isPast) html += ' <span class="badge bg-secondary ms-1">Sudah lewat</span>';
            html += '</div><dl class="event-detail-grid">';
            html += `<dt>Armada</dt><dd>${props.kode ? props.kode + ' · ' : ''}${props.bus} (${props.tipe || '-'})</dd>`;
            html += `<dt>${props.type === 'maintenance' ? 'Keterangan' : 'Order'}</dt><dd>${props.order || '-'}</dd>`;
            if (props.tujuan && props.tujuan !== '-') html += `<dt>Tujuan</dt><dd>${props.tujuan}</dd>`;
            html += `<dt>Tanggal</dt><dd>${props.tanggal}${props.waktu ? ' · ' + props.waktu : ''}</dd>`;
            if (props.type === 'trip') {
                html += `<dt>Harga Sewa</dt><dd>${rupiah(props.sewa)}</dd>`;
                html += `<dt>Beban Ops</dt><dd>${rupiah(props.ops)}</dd>`;
                if (props.crew && props.crew !== '-') html += `<dt>Crew</dt><dd>${props.crew}</dd>`;
            } else if (props.type === 'maintenance') {
                html += `<dt>Biaya</dt><dd>${rupiah(props.ops)}</dd>`;
            } else if (props.type === 'pemesanan') {
                html += `<dt>Total</dt><dd>${rupiah(props.sewa)}</dd>`;
                if (props.status) html += `<dt>Status</dt><dd>${props.status.replace('_', ' ')}</dd>`;
            }
            html += '</dl>';
            detailModalBody.innerHTML = html;
            detailModalLink.href = props.detailUrl || '#';
            bsModal.show();
        }

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
                height: 'auto',
                dayMaxEvents: 3,
                moreLinkText: '+%d lainnya',
                navLinks: true,
                nowIndicator: true,
                events: <?php echo json_encode($events, JSON_UNESCAPED_UNICODE); ?>,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    showEventDetail(info.event.extendedProps, info.event.title);
                },
                eventDidMount: function(info) {
                    const props = info.event.extendedProps;
                    const lines = [props.typeLabel, props.bus, props.order];
                    if (props.tujuan && props.tujuan !== '-') lines.push(props.tujuan);
                    info.el.setAttribute('title', lines.filter(Boolean).join(' | '));
                }
            });
            calendar.render();
        }
    });
</script>

<!-- Modal Detail Event Kalender -->
<div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="eventDetailModalLabel">Detail Kegiatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2" id="eventDetailBody"></div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <a href="#" id="eventDetailLink" class="btn btn-success">Lihat Detail</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Bus -->
<div class="modal fade" id="tambahBusModal" tabindex="-1" aria-labelledby="tambahBusModalLabel" aria-hidden="true">
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

<?php include '../templates/footer.php'; ?>