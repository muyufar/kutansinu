<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Pastikan user sudah login dan punya hak akses admin
requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

if (!checkUserRole($db, $user_id, $default_company_id, 'admin')) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya Admin yang dapat mengakses audit log.';
    header('Location: /kutansinu/index.php');
    exit();
}

// Filter
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query log
$sql = "SELECT a.*, u.username, u.nama_lengkap FROM audit_log a LEFT JOIN users u ON a.user_id = u.id WHERE DATE(a.created_at) BETWEEN :awal AND :akhir";
$params = [':awal' => $tanggal_awal, ':akhir' => $tanggal_akhir];
if ($search) {
    $sql .= " AND (u.username LIKE :search OR u.nama_lengkap LIKE :search OR a.action LIKE :search OR a.description LIKE :search)";
    $params[':search'] = "%$search%";
}
$sql .= " ORDER BY a.created_at DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$audit_logs = $stmt->fetchAll();

include '../templates/header.php';
?>
<div class="container py-4">
    <h2 class="mb-4">Audit Trail / Log Aktivitas</h2>
    <form class="row g-2 mb-3" method="get">
        <div class="col-auto">
            <input type="date" name="tanggal_awal" class="form-control" value="<?= htmlspecialchars($tanggal_awal) ?>">
        </div>
        <div class="col-auto">
            <input type="date" name="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
        </div>
        <div class="col-auto">
            <input type="text" name="search" class="form-control" placeholder="Cari user/action/deskripsi" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-success" type="submit">Filter</button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-dark table-striped table-hover align-middle rounded shadow">
            <thead>
                <tr style="background: linear-gradient(90deg, #22c55e 0%, #15803d 100%);">
                    <th style="background:transparent;">Waktu</th>
                    <th style="background:transparent;">User</th>
                    <th style="background:transparent;">Aksi</th>
                    <th style="background:transparent;">Deskripsi</th>
                    <th style="background:transparent;">IP</th>
                    <th style="background:transparent;">User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($audit_logs) === 0): ?>
                    <tr>
                        <td colspan="6" class="text-center">Tidak ada data log.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['created_at']) ?></td>
                            <td><?= htmlspecialchars($log['nama_lengkap'] ?: $log['username'] ?: '-') ?></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td><?= htmlspecialchars($log['description']) ?></td>
                            <td><?= htmlspecialchars($log['ip_address']) ?></td>
                            <td><span title="<?= htmlspecialchars($log['user_agent']) ?>">UA</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../templates/footer.php'; ?>