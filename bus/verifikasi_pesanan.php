<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek apakah user terhubung dengan perusahaan Nugrosir
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Proses verifikasi pesanan jika ada
if (isset($_POST['verifikasi'])) {
    $pemesanan_id = (int)$_POST['pemesanan_id'];
    $status = validateInput($_POST['status']);
    $catatan_admin = isset($_POST['catatan_admin']) ? validateInput($_POST['catatan_admin']) : '';
    $bukti_transfer = '';

    // Proses upload bukti transfer jika ada
    if (isset($_FILES['bukti_transfer']) && $_FILES['bukti_transfer']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['bukti_transfer']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array(strtolower($ext), $allowed)) {
            $new_filename = time() . '_' . $filename;
            $upload_dir = '../uploads/pembayaran_bus/';

            // Pastikan direktori ada
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['bukti_transfer']['tmp_name'], $destination)) {
                $bukti_transfer = $new_filename;
            } else {
                $_SESSION['error'] = 'Gagal mengupload bukti transfer';
                header('Location: verifikasi_pesanan.php');
                exit();
            }
        } else {
            $_SESSION['error'] = 'Format file tidak diizinkan. Gunakan JPG, JPEG, PNG, atau PDF';
            header('Location: verifikasi_pesanan.php');
            exit();
        }
    }

    try {
        // Ambil data pemesanan untuk mendapatkan jumlah_bayar dan bukti_pembayaran
        $stmt_pemesanan = $db->prepare("SELECT jumlah_bayar, bukti_pembayaran, pembayaran_dp, sisa_pembayaran, jenis_pembayaran FROM pemesanan_bus WHERE id = ?");
        $stmt_pemesanan->execute([$pemesanan_id]);
        $pemesanan_data = $stmt_pemesanan->fetch();

        // Update query berdasarkan ada tidaknya bukti transfer
        if (!empty($bukti_transfer)) {
            $stmt = $db->prepare("UPDATE pemesanan_bus SET 
                status = ?, 
                catatan_admin = ?, 
                bukti_transfer_admin = ?, 
                tanggal_verifikasi = NOW(),
                jumlah_bayar = CASE 
                    WHEN ? = 'selesai' THEN pembayaran_dp + sisa_pembayaran
                    WHEN status = 'dibayar_dp' AND ? = 'dibayar' THEN pembayaran_dp + sisa_pembayaran
                    ELSE jumlah_bayar 
                END
                WHERE id = ?");
            $stmt->execute([$status, $catatan_admin, $bukti_transfer, $status, $status, $pemesanan_id]);
        } else {
            $stmt = $db->prepare("UPDATE pemesanan_bus SET 
                status = ?, 
                catatan_admin = ?, 
                tanggal_verifikasi = NOW(),
                jumlah_bayar = CASE 
                    WHEN ? = 'selesai' THEN pembayaran_dp + sisa_pembayaran
                    WHEN status = 'dibayar_dp' AND ? = 'dibayar' THEN pembayaran_dp + sisa_pembayaran
                    ELSE jumlah_bayar 
                END
                WHERE id = ?");
            $stmt->execute([$status, $catatan_admin, $status, $status, $pemesanan_id]);
        }

        // Jika status diubah menjadi 'selesai', tambahkan transaksi keuangan secara otomatis
        if ($status === 'selesai') {
            // Ambil id_perusahaan dari default_company pengguna
            $stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
            $stmt_company->execute([$_SESSION['user_id']]);
            $user_data = $stmt_company->fetch();
            $id_perusahaan = $user_data['default_company'];

            // Cari akun kas (untuk kredit) dan akun pendapatan (untuk debit)
            $stmt_kas = $db->prepare("SELECT id FROM akun WHERE nama_akun LIKE '%kas%' AND id_perusahaan = ? LIMIT 1");
            $stmt_kas->execute([$id_perusahaan]);
            $akun_kas = $stmt_kas->fetch();

            $stmt_pendapatan = $db->prepare("SELECT id FROM akun WHERE kode_akun LIKE '%4-40000%' AND id_perusahaan = ? LIMIT 1");
            $stmt_pendapatan->execute([$id_perusahaan]);
            $akun_pendapatan = $stmt_pendapatan->fetch();

            // Jika akun kas dan pendapatan ditemukan
            if ($akun_kas && $akun_pendapatan) {
                $id_akun_debit = $akun_kas['id']; // Kas bertambah (debit)
                $id_akun_kredit = $akun_pendapatan['id']; // Pendapatan bertambah (kredit)

                // Hitung total pendapatan berdasarkan jenis pembayaran
                if ($pemesanan_data['jenis_pembayaran'] === 'lunas') {
                    $total_pendapatan = $pemesanan_data['jumlah_bayar']; // Gunakan jumlah_bayar untuk pembayaran lunas
                } else {
                    $total_pendapatan = $pemesanan_data['pembayaran_dp'] + $pemesanan_data['sisa_pembayaran']; // Untuk DP
                }

                $keterangan = "Transaksi bus - Pemesanan #$pemesanan_id";
                $jenis = "pemasukan";
                $tanggal = date('Y-m-d');
                $penanggung_jawab = "Admin";
                $tag = "bus,pendapatan";

                // Path bukti pembayaran
                $file_lampiran = '';
                // Ambil bukti pembayaran terbaru dari tabel bukti_pembayaran_bus
                $stmt_bukti = $db->prepare("SELECT nama_file FROM bukti_pembayaran_bus WHERE pemesanan_id = ? ORDER BY tanggal_upload DESC LIMIT 1");
                $stmt_bukti->execute([$pemesanan_id]);
                $bukti = $stmt_bukti->fetch();

                if ($bukti) {
                    $file_lampiran = 'uploads/pembayaran_bus/' . $bukti['nama_file'];
                }

                // Tambahkan transaksi ke database
                $stmt_transaksi = $db->prepare("INSERT INTO transaksi (tanggal, id_akun_debit, id_akun_kredit, keterangan, jenis, jumlah, pajak, bunga, total, file_lampiran, penanggung_jawab, tag, created_by, id_perusahaan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_transaksi->execute([$tanggal, $id_akun_debit, $id_akun_kredit, $keterangan, $jenis, $total_pendapatan, 0, 0, $total_pendapatan, $file_lampiran, $penanggung_jawab, $tag, $_SESSION['user_id'], $id_perusahaan]);
            }
        }

        $_SESSION['success'] = 'Status pemesanan berhasil diperbarui';
        header('Location: verifikasi_pesanan.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui status: ' . $e->getMessage();
    }
}

// Filter status
$status_filter = isset($_GET['status']) ? validateInput($_GET['status']) : '';
$tipe_bus_filter = isset($_GET['bus']) ? validateInput($_GET['bus']) : '';
$tanggal_mulai = isset($_GET['tanggal_mulai']) ? validateInput($_GET['tanggal_mulai']) : '';
$tanggal_selesai = isset($_GET['tanggal_selesai']) ? validateInput($_GET['tanggal_selesai']) : '';

// Pagination
$items_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $items_per_page;
$view_all = isset($_GET['view_all']) && $_GET['view_all'] == 1;

$where_clause = '';
$params = [];

if ($status_filter || $tipe_bus_filter || $tanggal_mulai || $tanggal_selesai) {
    $where_clause = " WHERE 1=1";
    if ($status_filter) {
        $where_clause .= " AND pb.status = ?";
        $params[] = $status_filter;
    }
    if ($tipe_bus_filter) {
        $where_clause .= " AND b.nama_bus = ?";
        $params[] = $tipe_bus_filter;
    }
    if ($tanggal_mulai) {
        $where_clause .= " AND DATE(pb.tanggal_berangkat) >= ?";
        $params[] = $tanggal_mulai;
    }
    if ($tanggal_selesai) {
        $where_clause .= " AND DATE(pb.tanggal_berangkat) <= ?";
        $params[] = $tanggal_selesai;
    }
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) FROM pemesanan_bus pb JOIN bus b ON pb.id_bus = b.id $where_clause";
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $items_per_page);

// Ambil daftar pemesanan dengan limit
$query = "SELECT pb.*, b.nama_bus, b.nomor_polisi
          FROM pemesanan_bus pb
          JOIN bus b ON pb.id_bus = b.id
          $where_clause
          ORDER BY pb.tanggal_pemesanan DESC";

if (!$view_all) {
    $query .= " LIMIT $items_per_page OFFSET $offset";
}

$stmt = $db->prepare($query);
$stmt->execute($params);
$pemesanan_list = $stmt->fetchAll();

// Ambil daftar tipe bus yang tersedia
$stmt_tipe = $db->query("SELECT DISTINCT nama_bus FROM bus WHERE nama_bus IS NOT NULL ORDER BY tipe");
$tipe_bus_list = $stmt_tipe->fetchAll(PDO::FETCH_COLUMN);

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Verifikasi Pemesanan Bus</h2>
        <div>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
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

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Filter Pemesanan</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status Pemesanan</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="dibayar_dp" <?php echo $status_filter == 'dibayar_dp' ? 'selected' : ''; ?>>Dibayar DP</option>
                        <option value="dibayar" <?php echo $status_filter == 'dibayar' ? 'selected' : ''; ?>>Dibayar Lunas</option>
                        <option value="selesai" <?php echo $status_filter == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="dibatalkan" <?php echo $status_filter == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="nama_bus" class="form-label">Nama Bus</label>
                    <select class="form-select" id="nama_bus" name="bus">
                        <option value="">Semua Nama Bus</option>
                        <?php foreach ($tipe_bus_list as $bus): ?>
                            <option value="<?php echo htmlspecialchars($bus); ?>" <?php echo $tipe_bus_filter == $bus ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($bus); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="tanggal_mulai" class="form-label">Tanggal Berangkat</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="tanggal_mulai" name="tanggal_mulai" value="<?php echo $tanggal_mulai; ?>" placeholder="Dari">
                        <span class="input-group-text">sampai</span>
                        <input type="date" class="form-control" id="tanggal_selesai" name="tanggal_selesai" value="<?php echo $tanggal_selesai; ?>" placeholder="Sampai">
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="verifikasi_pesanan.php" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Daftar Pemesanan</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pemesanan_list)): ?>
                <div class="alert alert-info">
                    Tidak ada data pemesanan yang ditemukan.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID</th>
                                <th>Tanggal Pemesanan</th>
                                <th>Pemesan</th>
                                <th>Bus</th>
                                <th>Rute</th>
                                <th>Tanggal Berangkat</th>
                                <th>Total Harga</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = $view_all ? 1 : ($offset + 1);
                            foreach ($pemesanan_list as $pemesanan):
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $pemesanan['id']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_pemesanan'])); ?></td>
                                    <td><?php echo htmlspecialchars($pemesanan['nama_pemesan']); ?></td>
                                    <td><?php echo htmlspecialchars($pemesanan['nama_bus']); ?> (<?php echo htmlspecialchars($pemesanan['nomor_polisi']); ?>)</td>
                                    <td><?php echo htmlspecialchars($pemesanan['kota_asal']); ?> - <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?></td>
                                    <td><?php echo formatRupiah($pemesanan['total_harga']); ?></td>
                                    <td>
                                        <span class="badge <?php
                                                            switch ($pemesanan['status']) {
                                                                case 'dibayar_dp':
                                                                    echo 'bg-warning';
                                                                    break;
                                                                case 'dibayar':
                                                                    echo 'bg-success';
                                                                    break;
                                                                case 'selesai':
                                                                    echo 'bg-primary';
                                                                    break;
                                                                case 'dibatalkan':
                                                                    echo 'bg-secondary';
                                                                    break;
                                                                default:
                                                                    echo 'bg-light text-dark';
                                                            }
                                                            ?>">
                                            <?php
                                            switch ($pemesanan['status']) {
                                                case 'dibayar_dp':
                                                    echo 'Dibayar DP';
                                                    break;
                                                case 'dibayar':
                                                    echo 'Dibayar Lunas';
                                                    break;
                                                case 'selesai':
                                                    echo 'Selesai';
                                                    break;
                                                case 'dibatalkan':
                                                    echo 'Dibatalkan';
                                                    break;
                                                default:
                                                    echo $pemesanan['status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?php echo $pemesanan['id']; ?>">
                                            <i class="fas fa-eye"></i> Detail
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if (!$view_all && $total_pages > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <a href="?view_all=1<?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-list"></i> Lihat Semua
                            </a>
                        </div>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php elseif ($view_all): ?>
                    <div class="mt-4">
                        <a href="?page=1<?php echo $status_filter ? '&status=' . $status_filter : ''; ?><?php echo $tipe_bus_filter ? '&bus=' . $tipe_bus_filter : ''; ?><?php echo $tanggal_mulai ? '&tanggal_mulai=' . $tanggal_mulai : ''; ?><?php echo $tanggal_selesai ? '&tanggal_selesai=' . $tanggal_selesai : ''; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-th-list"></i> Kembali ke Pagination
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Detail dan Verifikasi -->
<?php foreach ($pemesanan_list as $pemesanan): ?>
    <div class="modal fade" id="detailModal<?php echo $pemesanan['id']; ?>" tabindex="-1" aria-labelledby="detailModalLabel<?php echo $pemesanan['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailModalLabel<?php echo $pemesanan['id']; ?>">Detail Pemesanan #<?php echo $pemesanan['id']; ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Informasi Pemesanan</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>ID Pemesanan</th>
                                    <td><?php echo $pemesanan['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Tanggal Pemesanan</th>
                                    <td><?php echo date('d/m/Y H:i', strtotime($pemesanan['tanggal_pemesanan'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <?php
                                        switch ($pemesanan['status']) {
                                            case 'menunggu_pembayaran':
                                                echo 'Menunggu Pembayaran';
                                                break;
                                            case 'menunggu_verifikasi':
                                                echo 'Menunggu Verifikasi';
                                                break;
                                            case 'dikonfirmasi':
                                                echo 'Dikonfirmasi';
                                                break;
                                            case 'ditolak':
                                                echo 'Ditolak';
                                                break;
                                            case 'selesai':
                                                echo 'Selesai';
                                                break;
                                            case 'dibatalkan':
                                                echo 'Dibatalkan';
                                                break;
                                            default:
                                                echo $pemesanan['status'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Harga</th>
                                    <td><?php echo formatRupiah($pemesanan['total_harga']); ?></td>
                                </tr>
                                <tr>
                                    <th>Jenis Pembayaran</th>
                                    <td><?= ($pemesanan['jenis_pembayaran'] == 'dp') ? 'Uang Muka (DP)' : 'Lunas'; ?></td>
                                </tr>
                                <tr>
                                    <th>Jumlah Bayar</th>
                                    <td><?= formatRupiah($pemesanan['jumlah_bayar']) ?></td>
                                </tr>
                                <?php
                                // Ambil semua bukti pembayaran
                                $stmt_bukti = $db->prepare("SELECT * FROM bukti_pembayaran_bus WHERE pemesanan_id = ? ORDER BY tanggal_upload ASC");
                                $stmt_bukti->execute([$pemesanan['id']]);
                                $bukti_pembayaran_list = $stmt_bukti->fetchAll();

                                if (!empty($bukti_pembayaran_list)): ?>
                                    <tr>
                                        <th>Bukti Pembayaran</th>
                                        <td>
                                            <div class="d-flex flex-column gap-2">
                                                <?php foreach ($bukti_pembayaran_list as $bukti): ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <a href="../uploads/pembayaran_bus/<?php echo htmlspecialchars($bukti['nama_file']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                            <i class="fas fa-image"></i>
                                                            <?php echo $bukti['jenis_pembayaran'] == 'dp' ? 'Bukti DP' : 'Bukti Lunas'; ?>
                                                            (<?php echo date('d/m/Y H:i', strtotime($bukti['tanggal_upload'])); ?>)
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($pemesanan['bukti_transfer_admin'])): ?>
                                    <tr>
                                        <th>Bukti Transfer Admin</th>
                                        <td>
                                            <a href="../uploads/pembayaran_bus/<?php echo htmlspecialchars($pemesanan['bukti_transfer_admin']); ?>" target="_blank" class="btn btn-sm btn-success">
                                                <i class="fas fa-file-invoice-dollar"></i> Lihat Bukti Transfer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($pemesanan['catatan'])): ?>
                                    <tr>
                                        <th>Catatan Pemesan</th>
                                        <td><?php echo nl2br(htmlspecialchars($pemesanan['catatan'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if (!empty($pemesanan['catatan_admin'])): ?>
                                    <tr>
                                        <th>Catatan Admin</th>
                                        <td><?php echo nl2br(htmlspecialchars($pemesanan['catatan_admin'])); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Detail Perjalanan</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th>Bus</th>
                                    <td><?php echo htmlspecialchars($pemesanan['nama_bus']); ?> (<?php echo htmlspecialchars($pemesanan['nomor_polisi']); ?>)</td>
                                </tr>
                                <tr>
                                    <th>Rute</th>
                                    <td><?php echo htmlspecialchars($pemesanan['kota_asal']); ?> - <?php echo htmlspecialchars($pemesanan['kota_tujuan']); ?></td>
                                </tr>
                                <tr>
                                    <th>Tanggal Berangkat</th>
                                    <td><?php echo date('d/m/Y', strtotime($pemesanan['tanggal_berangkat'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Waktu Berangkat</th>
                                    <td><?php echo date('H:i', strtotime($pemesanan['waktu_berangkat'])); ?> WIB</td>
                                </tr>
                                <tr>
                                    <th>Jumlah Penumpang</th>
                                    <td><?php echo $pemesanan['jumlah_penumpang']; ?> orang</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if ($pemesanan['status'] == 'menunggu_pembayaran'): ?>
                        <form action="" method="post">
                            <input type="hidden" name="pemesanan_id" value="<?php echo $pemesanan['id']; ?>">
                            <input type="hidden" name="status" value="dibayar">
                            <div class="text-end">
                                <button type="submit" name="verifikasi" class="btn btn-success">
                                    <i class="fas fa-check"></i> Terima Pemesanan
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>

                    <!-- Form Verifikasi Pesanan -->
                    <?php if ($pemesanan['status'] != 'selesai'): ?>
                        <div class="card mt-3">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">Verifikasi Pesanan</h5>
                            </div>
                            <div class="card-body">
                                <form action="" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="pemesanan_id" value="<?php echo $pemesanan['id']; ?>">

                                    <div class="mb-3">
                                        <label for="status<?php echo $pemesanan['id']; ?>" class="form-label">Status Pesanan</label>
                                        <select class="form-select status-select" id="status<?php echo $pemesanan['id']; ?>" name="status" data-pemesanan-id="<?php echo $pemesanan['id']; ?>" required>
                                            <option value="">Pilih Status</option>
                                            <option value="dibayar_dp" <?php echo $pemesanan['status'] == 'dibayar_dp' ? 'selected' : ''; ?>>Dibayar DP</option>
                                            <option value="dibayar" <?php echo $pemesanan['status'] == 'dibayar' ? 'selected' : ''; ?>>Dibayar Lunas</option>
                                            <option value="selesai" <?php echo $pemesanan['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                            <option value="dibatalkan" <?php echo $pemesanan['status'] == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="catatan_admin<?php echo $pemesanan['id']; ?>" class="form-label">Catatan Admin</label>
                                        <textarea class="form-control" id="catatan_admin<?php echo $pemesanan['id']; ?>" name="catatan_admin" rows="3"><?php echo htmlspecialchars($pemesanan['catatan_admin'] ?? ''); ?></textarea>
                                        <div class="form-text">Tambahkan catatan atau keterangan untuk pesanan ini.</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="bukti_transfer<?php echo $pemesanan['id']; ?>" class="form-label">Upload Bukti Transfer Pelunasan</label>
                                        <input type="file" class="form-control" id="bukti_transfer<?php echo $pemesanan['id']; ?>" name="bukti_transfer">
                                        <div class="form-text">Format yang diizinkan: JPG, JPEG, PNG, PDF. Maksimal 2MB.</div>
                                    </div>

                                    <!-- Form Pembayaran Sisa DP (hanya jika status dibayar_dp) -->
                                    <div class="mb-3 remaining-payment-form" id="remainingPaymentForm<?php echo $pemesanan['id']; ?>" style="display: none;">
                                        <label class="form-label">Pembayaran Sisa DP</label>
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <div class="input-group">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="text" class="form-control payment-amount" name="payment_amount"
                                                        data-max="<?php echo max(0, $pemesanan['total_harga'] - $pemesanan['jumlah_bayar']); ?>"
                                                        placeholder="Jumlah Pembayaran Sisa"
                                                        onkeyup="formatRupiah(this)">
                                                </div>
                                                <small class="text-muted">Maksimal: <?php echo formatRupiah(max(0, $pemesanan['total_harga'] - $pemesanan['jumlah_bayar'])); ?></small>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <select class="form-select" name="payment_method">
                                                    <option value="cash">Tunai</option>
                                                    <option value="transfer">Transfer Bank</option>
                                                </select>
                                            </div>
                                        </div>
                                        <textarea class="form-control mt-2" name="payment_note" rows="2" placeholder="Keterangan pembayaran sisa (opsional)"></textarea>
                                    </div>

                                    <div class="d-grid">
                                        <button type="submit" name="verifikasi" class="btn btn-primary">
                                            <i class="fas fa-check-circle"></i> Konfirmasi & Simpan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mt-3">
                            <i class="fas fa-check-circle"></i> Pesanan ini telah selesai dan tidak dapat diubah lagi.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Tampilkan/hidden form pembayaran sisa DP sesuai status
        $('.status-select').on('change', function() {
            var id = $(this).data('pemesanan-id');
            var val = $(this).val();
            var form = $('#remainingPaymentForm' + id);
            if (val === 'dibayar') {
                form.show();
            } else {
                form.hide();
            }
        });

        // Validasi sebelum submit form verifikasi
        $('form').on('submit', function(e) {
            var statusSelect = $(this).find('.status-select');
            if (statusSelect.length) {
                var id = statusSelect.data('pemesanan-id');
                var status = statusSelect.val();
                var paymentInput = $(this).find('.payment-amount');
                if (status === 'dibayar') {
                    var paymentAmount = paymentInput.val().replace(/[^\d]/g, '');
                    if (!paymentAmount || parseInt(paymentAmount) <= 0) {
                        e.preventDefault();
                        alert('Mohon isi jumlah pembayaran sisa DP jika status Dibayar.');
                        return false;
                    }
                }
            }
        });
    });

    // Fungsi untuk memformat input ke format Rupiah
    function formatRupiah(input) {
        // Hapus semua karakter non-digit
        var value = input.value.replace(/[^\d]/g, '');

        // Dapatkan nilai maksimum dari data-max
        var max = parseInt(input.getAttribute('data-max'));

        // Jika nilai melebihi maksimum, set ke nilai maksimum
        if (parseInt(value) > max) {
            value = max.toString();
        }

        // Format ke Rupiah
        if (value.length > 0) {
            value = parseInt(value).toLocaleString('id-ID');
        }

        // Update nilai input
        input.value = value;
    }
</script>

<?php include '../templates/footer.php'; ?>