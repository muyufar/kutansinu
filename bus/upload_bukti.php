<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek parameter ID pemesanan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'ID pemesanan tidak valid';
    header('Location: index.php');
    exit();
}

$pemesanan_id = (int)$_GET['id'];

// Ambil data pemesanan
$stmt = $db->prepare("
    SELECT pb.*, jb.*, b.*, u.nama_lengkap, u.email, u.no_hp
    FROM pemesanan_bus pb
    JOIN jadwal_bus jb ON pb.id_jadwal_bus = jb.id
    JOIN bus b ON pb.id_bus = b.id
    JOIN users u ON pb.id_user = u.id
    WHERE pb.id = ?
");
$stmt->execute([$pemesanan_id]);
$pemesanan = $stmt->fetch();

if (!$pemesanan) {
    $_SESSION['error'] = 'Data pemesanan tidak ditemukan';
    header('Location: index.php');
    exit();
}

// Proses upload bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jenis_pembayaran = validateInput($_POST['jenis_pembayaran']);
    $jumlah_bayar = (int)str_replace(['Rp', '.', ','], '', $_POST['jumlah_bayar']);

    // Validasi jumlah bayar
    if ($jumlah_bayar <= 0 || $jumlah_bayar > $pemesanan['total_harga']) {
        $_SESSION['error'] = 'Jumlah pembayaran tidak valid';
        exit();
    }

    // Cek apakah ada file yang diupload
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] == UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = 'Silakan pilih file bukti pembayaran';
    } else {
        $bukti_pembayaran = '';

        // Proses upload file
        $file = $_FILES['bukti_pembayaran'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if ($file['size'] > $max_size) {
            $_SESSION['error'] = 'Ukuran file terlalu besar (maksimal 5MB)';
        } elseif (!in_array($file['type'], $allowed_types)) {
            $_SESSION['error'] = 'Tipe file tidak didukung (hanya JPG, PNG, GIF, dan PDF)';
        } else {
            // Buat direktori jika belum ada
            $upload_dir = '../uploads/bukti_pembayaran/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Generate nama file unik
            $filename = 'bukti_' . $pemesanan_id . '_' . time() . '_' . $user_id . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
            $destination = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $bukti_pembayaran = $filename;

                // Update status pemesanan dan jenis pembayaran
                try {
                    $stmt = $db->prepare("UPDATE pemesanan_bus SET bukti_pembayaran = ?, jenis_pembayaran = ?, jumlah_bayar = ?, status = 'dibayar' WHERE id = ?");
                    $stmt->execute([$bukti_pembayaran, $jenis_pembayaran, $jumlah_bayar, $pemesanan_id]);

                    $_SESSION['success'] = 'Bukti pembayaran berhasil diupload. Pembayaran Anda sedang diverifikasi.';
                    header('Location: riwayat.php');
                    exit();
                } catch (PDOException $e) {
                    $_SESSION['error'] = 'Gagal mengupload bukti pembayaran: ' . $e->getMessage();
                }
            } else {
                $_SESSION['error'] = 'Gagal mengupload file';
            }
        }
    }
}

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Upload Bukti Pembayaran</h2>
        <a href="riwayat.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Form Upload Bukti Pembayaran</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                <h5>Total Pembayaran: <?php echo formatRupiah($pemesanan['total_harga']); ?></h5>
                <p class="mb-0">Silakan pilih jenis pembayaran dan upload bukti pembayaran Anda.</p>
            </div>

            <form action="" method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="form-label">Jenis Pembayaran</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_pembayaran" id="pembayaran_dp" value="dp" required>
                            <label class="form-check-label" for="pembayaran_dp">
                                DP (Down Payment)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="jenis_pembayaran" id="pembayaran_lunas" value="lunas" required>
                            <label class="form-check-label" for="pembayaran_lunas">
                                Lunas (100% - <?php echo formatRupiah($pemesanan['total_harga']); ?>)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="jumlah_bayar" class="form-label">Jumlah Bayar</label>
                    <input type="text" class="form-control" id="jumlah_bayar_format" name="jumlah_bayar_format" required>
                    <input type="hidden" id="jumlah_bayar" name="jumlah_bayar">
                    <small class="text-muted">Masukkan jumlah yang Anda bayarkan</small>
                </div>

                <div class="mb-3">
                    <label for="bukti_pembayaran" class="form-label">Upload Bukti Pembayaran</label>
                    <input type="file" class="form-control" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*,application/pdf" required>
                    <small class="text-muted">Format yang didukung: JPG, PNG, GIF, PDF. Maksimal 5MB</small>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                    </button>
                </div>
            </form>

            <!-- Tambahkan script untuk format rupiah -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var jumlahBayarFormat = document.getElementById('jumlah_bayar_format');
                    var jumlahBayar = document.getElementById('jumlah_bayar');
                    var maxAmount = <?php echo $pemesanan['total_harga']; ?>;

                    // Format awal
                    jumlahBayarFormat.addEventListener('focus', function(e) {
                        // Hapus format ketika focus
                        var value = this.value.replace(/[^\d]/g, '');
                        this.value = value;
                    });

                    // Format ketika input
                    jumlahBayarFormat.addEventListener('input', function(e) {
                        // Hanya terima angka
                        var value = this.value.replace(/[^\d]/g, '');

                        // Update hidden input dengan nilai numerik
                        jumlahBayar.value = value;

                        // Format sebagai rupiah
                        if (value !== '') {
                            value = parseInt(value);
                            if (value > maxAmount) {
                                value = maxAmount;
                                jumlahBayar.value = maxAmount;
                            }
                            this.value = formatRupiah(value.toString());
                        }
                    });

                    // Format ketika blur
                    jumlahBayarFormat.addEventListener('blur', function(e) {
                        var value = this.value.replace(/[^\d]/g, '');
                        if (value !== '') {
                            value = parseInt(value);
                            this.value = formatRupiah(value.toString());
                        }
                    });

                    // Fungsi format rupiah
                    function formatRupiah(angka) {
                        var number_string = angka.replace(/[^,\d]/g, '').toString(),
                            split = number_string.split(','),
                            sisa = split[0].length % 3,
                            rupiah = split[0].substr(0, sisa),
                            ribuan = split[0].substr(sisa).match(/\d{3}/gi);

                        if (ribuan) {
                            separator = sisa ? '.' : '';
                            rupiah += separator + ribuan.join('.');
                        }

                        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
                        return 'Rp ' + rupiah;
                    }
                });
            </script>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>