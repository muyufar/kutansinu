<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_helper.php';

// Cek login
requireLogin();

// Cek akses Nugrosir
$user_id = $_SESSION['user_id'];
$is_nugrosir = checkNugrosirAccess($db, $user_id);

if (!$is_nugrosir) {
    http_response_code(403);
    echo 'Akses ditolak';
    exit();
}

// Ambil parameter
$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');
$bus_id = isset($_GET['bus_id']) ? (int)$_GET['bus_id'] : 0;

// Debug info (hapus setelah testing)
error_log("AJAX Request - Tanggal: $tanggal, Bus ID: $bus_id");

// Validasi tanggal
$dateError = validateBookingDate($tanggal);
if ($dateError) {
    error_log("Tanggal validation error: $dateError");
    echo '<div class="alert alert-danger">' . $dateError . '</div>';
    exit();
}

// Ambil jadwal bus yang tersedia
$available_schedule = getAvailableBusSchedule($db, $tanggal);

if (!empty($available_schedule)): ?>
    <div class="alert alert-success">
        <strong>✅ Tersedia untuk Dipesan:</strong> Berikut adalah bus yang tersedia untuk tanggal <?= date('d/m/Y', strtotime($tanggal)) ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Nama Bus</th>
                    <th>Kapasitas</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($available_schedule as $bus_available): ?>
                    <tr class="<?= $bus_available['id'] == $bus_id ? 'table-success' : '' ?>">
                        <td>
                            <?= htmlspecialchars($bus_available['nama_bus']) ?>
                            <?= $bus_available['id'] == $bus_id ? ' <span class="badge bg-success">Dipilih</span>' : '' ?>
                        </td>
                        <td><?= $bus_available['kapasitas'] ?> Penumpang</td>
                        <td>
                            <span class="badge bg-success">✅ Tersedia</span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="alert alert-danger">
        <strong>❌ Tidak Tersedia:</strong> Untuk tanggal <?= date('d/m/Y', strtotime($tanggal)) ?> sudah ada bus yang dipesan. 
        <br><small>Hanya satu bus yang boleh berangkat per tanggal sesuai aturan sistem.</small>
    </div>
<?php endif; ?>