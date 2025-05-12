<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Cek apakah user terhubung dengan perusahaan Nugrosir
$user_id = $_SESSION['user_id'];
$stmt_nugrosir = $db->prepare("SELECT 1 FROM user_perusahaan up
                    JOIN perusahaan p ON up.perusahaan_id = p.id
                    WHERE up.user_id = ? AND UPPER(p.nama) = 'NUGO' AND up.status = 'active'");
$stmt_nugrosir->execute([$user_id]);
$is_nugrosir = $stmt_nugrosir->fetch() ? true : false;

// Verifikasi role user (hanya untuk nugrosir)
if (!$is_nugrosir) {
    $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk halaman ini. Hanya perusahaan NUGROSIR yang dapat mengakses fitur pemesanan bus.';
    header('Location: /kutansinu/index.php');
    exit();
}

// Cek apakah ID bus tersedia
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'ID Bus tidak valid';
    header('Location: index.php');
    exit();
}

$bus_id = (int)$_GET['id'];

// Ambil data bus yang akan diedit
$stmt = $db->prepare("SELECT * FROM bus WHERE id = ?");
$stmt->execute([$bus_id]);
$bus = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bus) {
    $_SESSION['error'] = 'Bus tidak ditemukan';
    header('Location: index.php');
    exit();
}

// Proses form edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $nama_bus = validateInput($_POST['nama_bus']);
    $tipe = validateInput($_POST['tipe']);
    $nomor_polisi = validateInput($_POST['nomor_polisi']);
    $kapasitas = (int)$_POST['kapasitas'];
    $harga_per_km = (int)$_POST['harga_per_km'];
    $status = validateInput($_POST['status']);
    $fasilitas = isset($_POST['fasilitas']) ? implode(', ', $_POST['fasilitas']) : '';

    // Cek apakah nomor polisi sudah ada (kecuali untuk bus yang sedang diedit)
    $stmt = $db->prepare("SELECT 1 FROM bus WHERE nomor_polisi = ? AND id != ?");
    $stmt->execute([$nomor_polisi, $bus_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'Nomor polisi sudah terdaftar pada bus lain';
        header('Location: edit_bus.php?id=' . $bus_id);
        exit();
    }

    // Upload foto jika ada
    $foto = $bus['foto']; // Default ke foto yang sudah ada
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Validasi ekstensi file
        if (!in_array($ext, $allowed)) {
            $_SESSION['error'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG';
            header('Location: edit_bus.php?id=' . $bus_id);
            exit();
        }

        // Validasi ukuran file (max 2MB)
        if ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = 'Ukuran file terlalu besar. Maksimal 2MB';
            header('Location: edit_bus.php?id=' . $bus_id);
            exit();
        }

        // Buat direktori jika belum ada
        $upload_dir = '../uploads/bus/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate nama file unik
        $foto = uniqid() . '_' . $filename;
        $destination = $upload_dir . $foto;

        // Upload file
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destination)) {
            $_SESSION['error'] = 'Gagal mengupload foto';
            header('Location: edit_bus.php?id=' . $bus_id);
            exit();
        }

        // Hapus foto lama jika ada
        if (!empty($bus['foto']) && file_exists($upload_dir . $bus['foto'])) {
            unlink($upload_dir . $bus['foto']);
        }
    }

    // Update data bus
    try {
        $stmt = $db->prepare("UPDATE bus SET nama_bus = ?, tipe = ?, nomor_polisi = ?, kapasitas = ?, harga_per_km = ?, status = ?, fasilitas = ?, foto = ? WHERE id = ?");
        $stmt->execute([$nama_bus, $tipe, $nomor_polisi, $kapasitas, $harga_per_km, $status, $fasilitas, $foto, $bus_id]);

        $_SESSION['success'] = 'Data bus berhasil diperbarui';
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui data bus: ' . $e->getMessage();
        header('Location: edit_bus.php?id=' . $bus_id);
        exit();
    }
}

// Header
include '../templates/header.php';

// Pisahkan fasilitas menjadi array untuk checkbox
$fasilitas_array = !empty($bus['fasilitas']) ? explode(', ', $bus['fasilitas']) : [];
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Edit Bus</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="edit_bus.php?id=<?php echo $bus_id; ?>" method="post" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nama_bus" class="form-label">Nama Bus</label>
                                <input type="text" class="form-control" id="nama_bus" name="nama_bus" value="<?php echo htmlspecialchars($bus['nama_bus']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="tipe" class="form-label">Tipe Bus</label>
                                <input type="text" class="form-control" id="tipe" name="tipe" value="<?php echo htmlspecialchars($bus['tipe']); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nomor_polisi" class="form-label">Nomor Polisi</label>
                                <input type="text" class="form-control" id="nomor_polisi" name="nomor_polisi" value="<?php echo htmlspecialchars($bus['nomor_polisi']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="kapasitas" class="form-label">Kapasitas (Orang)</label>
                                <input type="number" class="form-control" id="kapasitas" name="kapasitas" value="<?php echo $bus['kapasitas']; ?>" min="1" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="harga_per_km" class="form-label">Harga per KM (Rp)</label>
                                <input type="number" class="form-control" id="harga_per_km" name="harga_per_km" value="<?php echo $bus['harga_per_km']; ?>" min="0" required>
                            </div>
                            <div class="col-md-6">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="tersedia" <?php echo ($bus['status'] == 'tersedia') ? 'selected' : ''; ?>>Tersedia</option>
                                    <option value="dalam_perbaikan" <?php echo ($bus['status'] == 'dalam_perbaikan') ? 'selected' : ''; ?>>Dalam Perbaikan</option>
                                    <option value="tidak_tersedia" <?php echo ($bus['status'] == 'tidak_tersedia') ? 'selected' : ''; ?>>Tidak Tersedia</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Fasilitas</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="ac" name="fasilitas[]" value="AC" <?php echo in_array('AC', $fasilitas_array) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="ac">AC</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="wifi" name="fasilitas[]" value="WiFi" <?php echo in_array('WiFi', $fasilitas_array) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="wifi">WiFi</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="toilet" name="fasilitas[]" value="Toilet" <?php echo in_array('Toilet', $fasilitas_array) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="toilet">Toilet</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="tv" name="fasilitas[]" value="TV" <?php echo in_array('TV', $fasilitas_array) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tv">TV</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="karaoke" name="fasilitas[]" value="Karaoke" <?php echo in_array('Karaoke', $fasilitas_array) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="karaoke">Karaoke</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="foto" class="form-label">Foto Bus</label>
                                <?php if (!empty($bus['foto'])): ?>
                                    <div class="mb-2">
                                        <img src="../uploads/bus/<?php echo htmlspecialchars($bus['foto']); ?>" class="img-thumbnail" style="max-height: 200px;" alt="Foto Bus">
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="foto" name="foto">
                                <small class="text-muted">Kosongkan jika tidak ingin mengubah foto</small>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../templates/footer.php'; ?>