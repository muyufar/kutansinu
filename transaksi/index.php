<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Proses edit transaksi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = validateInput($_POST['id']);
    $tanggal = validateInput($_POST['tanggal']);
    $id_akun_debit = validateInput($_POST['id_akun_debit']);
    $id_akun_kredit = validateInput($_POST['id_akun_kredit']);
    $keterangan = validateInput($_POST['keterangan']);
    $jenis = validateInput($_POST['jenis']);
    $jumlah = validateInput($_POST['jumlah']);
    $penanggung_jawab = validateInput($_POST['penanggung_jawab']);
    $tag = validateInput($_POST['tag']);

    // Debugging input POST
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));

    // Ambil file lampiran lama dari database
    $stmt_file = $db->prepare("SELECT file_lampiran FROM transaksi WHERE id = ?");
    $stmt_file->execute([$id]);
    $existing_file = $stmt_file->fetchColumn();

    // Tangani file baru jika diunggah
    $file_lampiran = $existing_file; // Default ke file lama
    if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/transaksi/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['file_lampiran']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file_lampiran']['tmp_name'], $target_file)) {
            $file_lampiran = 'uploads/transaksi/' . $file_name;
        } else {
            $_SESSION['error'] = 'Gagal mengunggah file lampiran.';
            header('Location: index.php');
            exit();
        }
    }

    try {
        // Update data transaksi
        $stmt = $db->prepare("UPDATE transaksi SET tanggal = ?, id_akun_debit = ?, id_akun_kredit = ?,  keterangan = ?, file_lampiran = ?, penanggung_jawab = ?, jenis = ?, jumlah = ?, tag = ? WHERE id = ?");
        $stmt->execute([$tanggal, $id_akun_debit, $id_akun_kredit, $keterangan, $file_lampiran, $penanggung_jawab, $jenis, $jumlah, $tag, $id]);

        // Debugging hasil update
        error_log("Update Query: " . $stmt->queryString);
        error_log("Update Params: " . json_encode([$tanggal, $id_akun_debit, $id_akun_kredit, $keterangan, $file_lampiran, $penanggung_jawab, $jenis, $jumlah, $id]));

        logAudit($db, $_SESSION['user_id'], 'edit_transaction', 'Edit transaksi ID: ' . $id);

        $_SESSION['success'] = 'Transaksi berhasil diperbarui.';
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal memperbarui transaksi: ' . $e->getMessage();
        error_log("Error: " . $e->getMessage());
        header('Location: index.php');
        exit();
    }
}

// Proses hapus transaksi
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM transaksi WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        logAudit($db, $_SESSION['user_id'], 'delete_transaction', 'Hapus transaksi ID: ' . $_GET['id']);
        $_SESSION['success'] = 'Transaksi berhasil dihapus.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal menghapus transaksi: ' . $e->getMessage();
    }
    header('Location: index.php');
    exit();
}
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];
// Ambil daftar akun untuk dropdown
$stmt = $db->prepare("SELECT * FROM akun WHERE id_perusahaan = ? ORDER BY kode_akun ASC");
$stmt->execute([$id_perusahaan]);
$akun_list = $stmt->fetchAll();

// Ambil id_perusahaan dari default_company pengguna
$stmt_company = $db->prepare("SELECT default_company FROM users WHERE id = ?");
$stmt_company->execute([$_SESSION['user_id']]);
$user_data = $stmt_company->fetch();
$id_perusahaan = $user_data['default_company'];

// Ambil daftar transaksi dengan informasi akun debit dan kredit, hanya untuk perusahaan pengguna yang login
$stmt = $db->prepare("SELECT t.*, 
                    ad.kode_akun as kode_akun_debit, ad.nama_akun as nama_akun_debit,
                    ak.kode_akun as kode_akun_kredit, ak.nama_akun as nama_akun_kredit
                    FROM transaksi t 
                    LEFT JOIN akun ad ON t.id_akun_debit = ad.id
                    LEFT JOIN akun ak ON t.id_akun_kredit = ak.id
                    WHERE t.id_perusahaan = ?
                    ORDER BY t.tanggal DESC, t.id DESC");
$stmt->execute([$id_perusahaan]);
$transaksi_list = $stmt->fetchAll();

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Daftar Transaksi Terbaru</h2>
        <a href="tambah.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Transaksi
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

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Akun</th>
                            <th>Keterangan</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transaksi_list as $transaksi): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($transaksi['tanggal'])); ?></td>
                                <td>
                                    <div>Debit: <?php echo htmlspecialchars($transaksi['kode_akun_debit'] . ' - ' . $transaksi['nama_akun_debit']); ?></div>
                                    <div>Kredit: <?php echo htmlspecialchars($transaksi['kode_akun_kredit'] . ' - ' . $transaksi['nama_akun_kredit']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($transaksi['keterangan']); ?></td>
                                <td>
                                    <span class="badge <?php echo $transaksi['jenis'] == 'pemasukan' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($transaksi['jenis']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatRupiah($transaksi['jumlah']); ?></td>
                                <td>
                                    <?php if (!empty($transaksi['file_lampiran'])): ?>
                                        <a href="../<?php echo htmlspecialchars($transaksi['file_lampiran']); ?>" target="_blank" class="badge bg-success">Lihat Bukti</a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Tidak Ada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalViewTransaksi"
                                        data-id="<?php echo $transaksi['id']; ?>"
                                        data-tanggal="<?php echo $transaksi['tanggal']; ?>"
                                        data-akun-debit="<?php echo htmlspecialchars($transaksi['kode_akun_debit'] . ' - ' . $transaksi['nama_akun_debit']); ?>"
                                        data-akun-kredit="<?php echo htmlspecialchars($transaksi['kode_akun_kredit'] . ' - ' . $transaksi['nama_akun_kredit']); ?>"
                                        data-keterangan="<?php echo htmlspecialchars($transaksi['keterangan']); ?>"
                                        data-tag="<?php echo htmlspecialchars($transaksi['tag']); ?>"
                                        data-jenis="<?php echo $transaksi['jenis']; ?>"
                                        data-jumlah="<?php echo formatRupiah($transaksi['jumlah']); ?>"
                                        data-pj="<?php echo htmlspecialchars($transaksi['penanggung_jawab']); ?>"
                                        data-file="<?php echo htmlspecialchars($transaksi['file_lampiran']); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-info"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalEditTransaksi"
                                        data-id="<?php echo $transaksi['id']; ?>"
                                        data-tanggal="<?php echo $transaksi['tanggal']; ?>"
                                        data-akun-kredit="<?php echo $transaksi['id_akun_kredit']; ?>"
                                        data-akun-debit="<?php echo $transaksi['id_akun_debit']; ?>"
                                        data-keterangan="<?php echo htmlspecialchars($transaksi['keterangan']); ?>"
                                        data-tag="<?php echo htmlspecialchars($transaksi['tag']); ?>"
                                        data-jenis="<?php echo $transaksi['jenis']; ?>"
                                        data-jumlah="<?php echo $transaksi['jumlah']; ?>"
                                        data-pj="<?php echo htmlspecialchars($transaksi['penanggung_jawab']); ?>"
                                        data-file="<?php echo htmlspecialchars($transaksi['file_lampiran']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?action=hapus&id=<?php echo $transaksi['id']; ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus transaksi ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Transaksi -->
<div class="modal fade" id="modalEditTransaksi" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label for="edit_tanggal" class="form-label">Tanggal</label>
                        <input type="date" class="form-control" id="edit_tanggal" name="tanggal" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_id_akun_debit" class="form-label">Akun Debit</label>
                        <select class="form-select" id="edit_id_akun_debit" name="id_akun_debit" required>
                            <option value="">Pilih Akun</option>
                            <?php foreach ($akun_list as $akun): ?>
                                <option value="<?php echo $akun['id']; ?>">
                                    <?php echo htmlspecialchars($akun['kode_akun'] . ' - ' . $akun['nama_akun']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_id_akun_kredit" class="form-label">Akun Kredit</label>
                        <select class="form-select" id="edit_id_akun_kredit" name="id_akun_kredit" required>
                            <option value="">Pilih Akun</option>
                            <?php foreach ($akun_list as $akun): ?>
                                <option value="<?php echo $akun['id']; ?>">
                                    <?php echo htmlspecialchars($akun['kode_akun'] . ' - ' . $akun['nama_akun']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="edit_keterangan" name="keterangan" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_jenis" class="form-label">Jenis Transaksi</label>
                        <select class="form-select" id="edit_jenis" name="jenis" required>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_jumlah" class="form-label">Jumlah</label>
                        <input type="number" class="form-control" id="edit_jumlah" name="jumlah" required min="0" step="0.01">
                    </div>
                    <div class="mb-3">
                        <label for="edit_pj" class="form-label">Penanggung Jawab</label>
                        <input type="text" class="form-control" id="edit_pj" name="penanggung_jawab" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tag" class="form-label">Tag</label>
                        <input type="text" class="form-control" id="edit_tag" name="tag" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_file_lampiran" class="form-label">File Lampiran</label>
                        <input type="file" class="form-control" id="edit_file_lampiran" name="file_lampiran" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">Format yang diizinkan: PDF, JPG, JPEG, PNG, DOC, DOCX</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal View Transaksi -->
<div class="modal fade" id="modalViewTransaksi" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Transaksi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Tanggal</label>
                    <p id="view_tanggal" class="form-control-static"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Akun Debit</label>
                    <p id="view_akun_debit" class="form-control-static"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Akun Kredit</label>
                    <p id="view_akun_kredit" class="form-control-static"></p>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <p id="view_keterangan" class="form-control-static"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tag</label>
                        <p id="view_tag" class="form-control-static"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jenis Transaksi</label>
                        <p id="view_jenis" class="form-control-static"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah</label>
                        <p id="view_jumlah" class="form-control-static"></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Penanggung Jawab</label>
                        <p id="view_pj" class="form-control-static"></p>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Script untuk mengisi modal view
        document.getElementById('modalViewTransaksi').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var tanggal = button.getAttribute('data-tanggal');
            var akun_debit = button.getAttribute('data-akun-debit');
            var akun_kredit = button.getAttribute('data-akun-kredit');
            var keterangan = button.getAttribute('data-keterangan');
            var tag = button.getAttribute('data-tag');
            var jenis = button.getAttribute('data-jenis');
            var jumlah = button.getAttribute('data-jumlah');
            var penanggung_jawab = button.getAttribute('data-pj');
            var file_lampiran = button.getAttribute('data-file');

            var modal = this;
            modal.querySelector('#view_tanggal').textContent = new Date(tanggal).toLocaleDateString('id-ID');
            modal.querySelector('#view_akun_debit').textContent = akun_debit;
            modal.querySelector('#view_akun_kredit').textContent = akun_kredit;
            modal.querySelector('#view_keterangan').textContent = keterangan;
            modal.querySelector('#view_tag').textContent = tag || '-';
            modal.querySelector('#view_jenis').textContent = jenis.charAt(0).toUpperCase() + jenis.slice(1);
            modal.querySelector('#view_jumlah').textContent = jumlah;
            modal.querySelector('#view_pj').textContent = penanggung_jawab || '-';

            var fileNone = modal.querySelector('#view_file_none');
            var fileLink = modal.querySelector('#view_file_link');

            if (file_lampiran) {
                fileNone.classList.add('d-none');
                fileLink.classList.remove('d-none');
                fileLink.href = '../' + file_lampiran;
            } else {
                fileNone.classList.remove('d-none');
                fileLink.classList.add('d-none');
            }
        });
        document.getElementById('modalEditTransaksi').addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var tanggal = button.getAttribute('data-tanggal');
            var id_akun_debit = button.getAttribute('data-akun-debit');
            var id_akun_kredit = button.getAttribute('data-akun-kredit');
            var keterangan = button.getAttribute('data-keterangan');
            var tag = button.getAttribute('data-tag');
            var jenis = button.getAttribute('data-jenis');
            var jumlah = button.getAttribute('data-jumlah');
            var penanggung_jawab = button.getAttribute('data-pj');
            var file_lampiran = button.getAttribute('data-file');

            var modal = this;
            modal.querySelector('#edit_id').value = id;
            modal.querySelector('#edit_tanggal').value = tanggal;
            modal.querySelector('#edit_id_akun_debit').value = id_akun_debit;
            modal.querySelector('#edit_id_akun_kredit').value = id_akun_kredit;
            modal.querySelector('#edit_keterangan').value = keterangan;
            modal.querySelector('#edit_tag').value = tag;
            modal.querySelector('#edit_jenis').value = jenis;
            modal.querySelector('#edit_jumlah').value = jumlah;
            modal.querySelector('#edit_pj').value = penanggung_jawab;

            // Tampilkan nama file lampiran lama
            var fileInput = modal.querySelector('#edit_file_lampiran');
            if (file_lampiran) {
                fileInput.setAttribute('data-existing-file', file_lampiran); // Simpan file lama sebagai atribut
                fileInput.placeholder = 'File lama: ' + file_lampiran;
            } else {
                fileInput.removeAttribute('data-existing-file');
                fileInput.placeholder = 'Tidak ada file lama';
            }
        });
    </script>

    <?php
    // Footer
    include '../templates/footer.php';
    ?>