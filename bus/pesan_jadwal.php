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

// Cek parameter ID jadwal
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID jadwal tidak valid';
    header('Location: index.php');
    exit();
}

$jadwal_id = (int)$_GET['id'];

// Ambil data jadwal bus
$stmt = $db->prepare("
    SELECT jb.*, b.* 
    FROM jadwal_bus jb
    JOIN bus b ON jb.id_bus = b.id
    WHERE jb.id = ? AND jb.status = 'tersedia'
");
$stmt->execute([$jadwal_id]);
$jadwal = $stmt->fetch();

if (!$jadwal) {
    $_SESSION['error'] = 'Jadwal bus tidak ditemukan atau tidak tersedia';
    header('Location: index.php');
    exit();
}

// Proses pemesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pemesan = validateInput($_POST['nama_pemesan']);
    $email = validateInput($_POST['email']);
    $telepon = validateInput($_POST['telepon']);
    $jumlah_penumpang = (int)$_POST['jumlah_penumpang'];
    $catatan = validateInput($_POST['catatan']);

    // Validasi input
    $errors = [];

    if (empty($nama_pemesan)) {
        $errors[] = 'Nama pemesan harus diisi';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid';
    }

    if (empty($telepon)) {
        $errors[] = 'Nomor telepon harus diisi';
    }

    if ($jumlah_penumpang < 1 || $jumlah_penumpang > $jadwal['kapasitas']) {
        $errors[] = 'Jumlah penumpang tidak valid (maksimal ' . $jadwal['kapasitas'] . ' orang)';
    }

    if (empty($errors)) {
        try {
            // Mulai transaksi
            $db->beginTransaction();

            // Periksa sekali lagi status jadwal sebelum update
            $stmt = $db->prepare("SELECT status FROM jadwal_bus WHERE id = ? FOR UPDATE");
            $stmt->execute([$jadwal_id]);
            $current_status = $stmt->fetchColumn();

            if ($current_status !== 'tersedia') {
                throw new PDOException('Jadwal ini sudah tidak tersedia');
            }

            // Update status jadwal menjadi 'penuh'
            $stmt = $db->prepare("UPDATE jadwal_bus SET status = 'penuh' WHERE id = ?");
            $stmt->execute([$jadwal_id]);

            // Hitung total harga
            $total_harga = $jadwal['harga'] * $jumlah_penumpang;

            // Simpan pemesanan dengan struktur tabel yang baru
            $stmt = $db->prepare("
                INSERT INTO pemesanan_bus (
                    id_user,
                    id_jadwal_bus,
                    id_bus,
                    tanggal_pemesanan,
                    tanggal_berangkat,
                    waktu_berangkat,
                    kota_asal,
                    kota_tujuan,
                    jumlah_penumpang,
                    total_harga,
                    status,
                    catatan,
                    created_at
                ) VALUES (
                    ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 
                    'menunggu_pembayaran', ?, NOW()
                )
            ");

            $stmt->execute([
                $user_id,
                $jadwal_id,
                $jadwal['id_bus'],
                $jadwal['tanggal_berangkat'],
                $jadwal['waktu_berangkat'],
                $jadwal['kota_asal'],
                $jadwal['kota_tujuan'],
                $jumlah_penumpang,
                $total_harga,
                $catatan
            ]);

            $pemesanan_id = $db->lastInsertId();

            // Commit transaksi
            $db->commit();

            $_SESSION['success'] = 'Pemesanan berhasil dibuat. Silakan lakukan pembayaran';
            header('Location: upload_bukti.php?id=' . $pemesanan_id);
            exit();
        } catch (PDOException $e) {
            // Rollback jika terjadi error
            $db->rollBack();
            $_SESSION['error'] = 'Gagal membuat pemesanan: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Pemesanan Bus</h2>
        <a href="jadwal.php?id=<?php echo $jadwal['id_bus']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Jadwal
        </a>
    </div>

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
                    <?php if (!empty($jadwal['foto'])): ?>
                        <img src="../uploads/bus/<?php echo htmlspecialchars($jadwal['foto']); ?>" class="img-fluid mb-3 rounded" alt="<?php echo htmlspecialchars($jadwal['nama_bus']); ?>">
                    <?php endif; ?>
                    <h5><?php echo htmlspecialchars($jadwal['nama_bus']); ?></h5>
                    <p class="mb-1"><strong>Tipe:</strong> <?php echo htmlspecialchars($jadwal['tipe']); ?></p>
                    <p class="mb-1"><strong>Kapasitas:</strong> <?php echo $jadwal['kapasitas']; ?> Penumpang</p>
                    <p class="mb-1"><strong>Fasilitas:</strong> <?php echo htmlspecialchars($jadwal['fasilitas']); ?></p>
                    <p class="mb-1"><strong>Tanggal Berangkat:</strong> <?php echo date('d/m/Y', strtotime($jadwal['tanggal_berangkat'])); ?></p>
                    <p class="mb-1"><strong>Waktu Berangkat:</strong> <?php echo date('H:i', strtotime($jadwal['waktu_berangkat'])); ?></p>
                    <p class="mb-1"><strong>Rute:</strong> <?php echo htmlspecialchars($jadwal['kota_asal'] . ' - ' . $jadwal['kota_tujuan']); ?></p>
                    <p class="mb-0"><strong>Harga per Orang:</strong> <?php echo formatRupiah($jadwal['harga']); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Form Pemesanan</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <div class="mb-3">
                            <label for="nama_pemesan" class="form-label">Nama Pemesan</label>
                            <input type="text" class="form-control" id="nama_pemesan" name="nama_pemesan" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="telepon" class="form-label">Nomor Telepon</label>
                            <input type="tel" class="form-control" id="telepon" name="telepon" value="<?php echo htmlspecialchars($user['no_hp']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="jumlah_penumpang" class="form-label">Jumlah Penumpang</label>
                            <input type="number" class="form-control" id="jumlah_penumpang" name="jumlah_penumpang" min="1" max="<?php echo $jadwal['kapasitas']; ?>" required>
                            <div class="form-text">Maksimal <?php echo $jadwal['kapasitas']; ?> penumpang</div>
                        </div>
                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan (opsional)</label>
                            <textarea class="form-control" id="catatan" name="catatan" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Buat Pemesanan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>