<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
$default_company_id = $user_data['default_company'];

if (!checkUserRole($db, $user_id, $default_company_id, 'admin')) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya Admin yang dapat mengakses audit log.';
    header('Location: /index.php');
    exit();
}

$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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

function auditActionClass(string $action): string
{
    if (str_contains($action, 'login')) {
        return 'audit-action-login';
    }
    if (str_contains($action, 'delete')) {
        return 'audit-action-delete';
    }
    if (str_contains($action, 'add') || str_contains($action, 'create')) {
        return 'audit-action-create';
    }
    if (str_contains($action, 'edit') || str_contains($action, 'update')) {
        return 'audit-action-update';
    }
    return 'audit-action-default';
}

function shortUserAgent(?string $ua): string
{
    if (empty($ua)) {
        return '-';
    }
    if (stripos($ua, 'Edg/') !== false) {
        return 'Edge';
    }
    if (stripos($ua, 'Chrome/') !== false) {
        return 'Chrome';
    }
    if (stripos($ua, 'Firefox/') !== false) {
        return 'Firefox';
    }
    if (stripos($ua, 'Safari/') !== false && stripos($ua, 'Chrome') === false) {
        return 'Safari';
    }
    return mb_strimwidth($ua, 0, 28, '…');
}

include '../templates/header.php';
?>
<div class="container audit-log-page py-4">
    <div class="audit-log-header mb-4">
        <div>
            <p class="audit-log-eyebrow mb-1"><i class="fas fa-shield-alt me-1"></i> Keamanan &amp; Audit</p>
            <h2 class="audit-log-title mb-0">Audit Trail / Log Aktivitas</h2>
            <p class="text-muted small mb-0 mt-1">Riwayat login, transaksi, dan perubahan data sistem (maks. 200 entri).</p>
        </div>
        <span class="audit-log-count badge"><?= count($audit_logs) ?> entri</span>
    </div>

    <div class="card dashboard-panel mb-4">
        <div class="card-body">
            <form class="row g-2 align-items-end audit-log-filter" method="get">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Tanggal Awal</label>
                    <input type="date" name="tanggal_awal" class="form-control" value="<?= htmlspecialchars($tanggal_awal) ?>">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold mb-1">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" class="form-control" value="<?= htmlspecialchars($tanggal_akhir) ?>">
                </div>
                <div class="col-md-4 col-sm-8">
                    <label class="form-label small fw-semibold mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" placeholder="User, aksi, atau deskripsi…" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card dashboard-panel">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0 audit-log-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>Deskripsi</th>
                            <th>IP</th>
                            <th>Perangkat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($audit_logs) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                    Tidak ada data log untuk periode ini.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td class="audit-log-time"><?= htmlspecialchars($log['created_at']) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($log['nama_lengkap'] ?: $log['username'] ?: '-') ?></td>
                                    <td>
                                        <span class="audit-action-badge <?= auditActionClass($log['action']) ?>">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="audit-log-desc"><?= htmlspecialchars($log['description']) ?></td>
                                    <td><code class="audit-log-ip"><?= htmlspecialchars($log['ip_address'] ?: '-') ?></code></td>
                                    <td>
                                        <span class="audit-log-ua" title="<?= htmlspecialchars($log['user_agent'] ?? '') ?>">
                                            <?= htmlspecialchars(shortUserAgent($log['user_agent'] ?? '')) ?>
                                        </span>
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
<?php include '../templates/footer.php'; ?>
