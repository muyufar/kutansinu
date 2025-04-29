<?php
session_start();
require_once '../config/database.php';
require_once '../config/functions.php';

// Cek login
requireLogin();

// Proses tambah transaksi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'tambah') {
    $tanggal = validateInput($_POST['tanggal']);
    $id_akun_debit = validateInput($_POST['id_akun_debit']);
    $id_akun_kredit = validateInput($_POST['id_akun_kredit']);
    $keterangan = validateInput($_POST['keterangan']);
    $jenis = validateInput($_POST['jenis']);
    $jumlah = validateInput($_POST['jumlah']);
    $pajak = validateInput($_POST['pajak']);
    $bunga = validateInput($_POST['bunga']);

    // Hitung total dengan pajak dan bunga
    $nilai_pajak = $jumlah * ($pajak / 100);
    $nilai_bunga = $jumlah * ($bunga / 100);
    $total = $jumlah + $nilai_pajak + $nilai_bunga;

    // Upload file lampiran jika ada
    $file_lampiran = '';
    if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] == 0) {
        $upload_dir = '../uploads/transaksi/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['file_lampiran']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file_lampiran']['tmp_name'], $target_file)) {
            $file_lampiran = 'uploads/transaksi/' . $file_name;
        } else {
            $_SESSION['error'] = 'Gagal mengupload file lampiran';
            $file_lampiran = '';
        }
    }

    $penanggung_jawab = validateInput($_POST['penanggung_jawab']);
    $tag = validateInput($_POST['tag']);

    // Validasi saldo sebelum melakukan transaksi
    if ($jenis == 'pengeluaran' || $jenis == 'tarik_modal' || $jenis == 'transfer_uang' || $jenis == 'transfer_hutang') {
        if (!validateSaldo($id_akun_kredit, $total, $jenis)) {
            $_SESSION['error'] = 'Saldo tidak mencukupi untuk melakukan transaksi ini';
            header('Location: tambah.php');
            exit();
        }
    }

    try {
        $stmt = $db->prepare("INSERT INTO transaksi (tanggal, id_akun_debit, id_akun_kredit, keterangan, jenis, jumlah, pajak, bunga, total, file_lampiran, penanggung_jawab, tag, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tanggal, $id_akun_debit, $id_akun_kredit, $keterangan, $jenis, $jumlah, $pajak, $bunga, $total, $file_lampiran, $penanggung_jawab, $tag, $_SESSION['user_id']]);
        $_SESSION['success'] = 'Transaksi berhasil ditambahkan';
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Gagal menambahkan transaksi: ' . $e->getMessage();
    }
}

// Ambil daftar akun untuk dropdown
$stmt = $db->query("SELECT * FROM akun ORDER BY kode_akun ASC");
$akun_list = $stmt->fetchAll();

// Konversi daftar akun ke format JSON untuk digunakan di JavaScript
$akun_json = json_encode($akun_list);

// Header
include '../templates/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <!-- Form Tambah Transaksi -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Tambah Transaksi</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="tambah">
                        <input type="hidden" id="akun_list_json" value='<?php echo htmlspecialchars($akun_json); ?>'>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tanggal" class="form-label">Tanggal <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="tanggal" name="tanggal" required>
                            </div>
                            <div class="col-md-6">
                                <label for="jenis" class="form-label">Jenis Transaksi <span class="text-danger">*</span></label>
                                <select class="form-select" id="jenis" name="jenis" required>
                                    <option value="">Pilih Jenis</option>
                                    <option value="pemasukan">Pemasukan</option>
                                    <option value="pengeluaran">Pengeluaran</option>
                                    <option value="hutang">Hutang</option>
                                    <option value="piutang">Piutang</option>
                                    <option value="tanam_modal">Tanam Modal</option>
                                    <option value="tarik_modal">Tarik Modal</option>
                                    <option value="transfer_uang">Transfer Uang</option>
                                    <option value="pemasukan_piutang">Pemasukan Piutang</option>
                                    <option value="transfer_hutang">Transfer Hutang</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="id_akun_debit" class="form-label">Akun Debit <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_akun_debit" name="id_akun_debit" required>
                                    <option value="">Pilih Akun Debit</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="id_akun_kredit" class="form-label">Akun Kredit <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_akun_kredit" name="id_akun_kredit" required>
                                    <option value="">Pilih Akun Kredit</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="nominal" class="form-label">Nominal <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="nominal" name="jumlah" required min="0" step="0.01" onchange="hitungTotal()">
                            </div>
                            <div class="col-md-6">
                                <label for="pajak" class="form-label">Pajak (%)</label>
                                <input type="number" class="form-control" id="pajak" name="pajak" value="0" min="0" max="100" onchange="hitungTotal()">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="bunga" class="form-label">Bunga (%)</label>
                                <input type="number" class="form-control" id="bunga" name="bunga" value="0" min="0" max="100" onchange="hitungTotal()">
                            </div>
                            <div class="col-md-6">
                                <label for="total" class="form-label">Total</label>
                                <input type="text" class="form-control" id="total" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="file_lampiran" class="form-label">File Lampiran</label>
                            <input type="file" class="form-control" id="file_lampiran" name="file_lampiran" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Format yang diizinkan: PDF, JPG, JPEG, PNG, DOC, DOCX</small>
                        </div>

                        <div class="mb-3">
                            <label for="penanggung_jawab" class="form-label">Penanggung Jawab <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="penanggung_jawab" name="penanggung_jawab" required>
                        </div>

                        <div class="mb-3">
                            <label for="tag" class="form-label">Tag</label>
                            <input type="text" class="form-control" id="tag" name="tag" placeholder="Masukkan tag (pisahkan dengan koma)">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="index.php" class="btn btn-secondary">Kembali</a>
                            <button type="button" class="btn btn-primary" onclick="showKonfirmasi()">Simpan Transaksi</button>
                        </div>

                        <!-- Modal Konfirmasi -->
                        <div class="modal fade" id="modalKonfirmasi" tabindex="-1" aria-labelledby="modalKonfirmasiLabel" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="modalKonfirmasiLabel">Konfirmasi Transaksi</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="fw-bold">Tanggal Transaksi</label>
                                            <p id="konfirmasi-tanggal"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Jenis Transaksi</label>
                                            <p id="konfirmasi-jenis"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Untuk Biaya (Debit)</label>
                                            <p id="konfirmasi-debit"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Diambil Dari (Kredit)</label>
                                            <p id="konfirmasi-kredit"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Catatan</label>
                                            <p id="konfirmasi-keterangan"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Nominal</label>
                                            <p id="konfirmasi-nominal"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Kontak</label>
                                            <p id="konfirmasi-penanggung-jawab"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Tag</label>
                                            <p id="konfirmasi-tag"></p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Ayat Jurnal</label>
                                            <table class="table table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>AKUN</th>
                                                        <th>DEBIT</th>
                                                        <th>CREDIT</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td id="konfirmasi-akun-debit"></td>
                                                        <td id="konfirmasi-nilai-debit"></td>
                                                        <td>0</td>
                                                    </tr>
                                                    <tr>
                                                        <td id="konfirmasi-akun-kredit"></td>
                                                        <td>0</td>
                                                        <td id="konfirmasi-nilai-kredit"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Lampiran</label>
                                            <p id="konfirmasi-lampiran">-</p>
                                        </div>
                                        <div class="mb-3">
                                            <label class="fw-bold">Simpan sebagai Draft</label>
                                            <p>Tidak</p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary">Simpan</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Form Import Excel -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Import Transaksi</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="import.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="file_csv" class="form-label">File CSV</label>
                            <input type="file" class="form-control" id="file_csv" name="file_csv" accept=".csv" required>
                        </div>
                        <div class="mb-3">
                            <a href="template_transaksi.csv" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-download"></i> Download Template CSV
                            </a>
                        </div>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-file-import"></i> Import Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Fungsi untuk menghitung total
    function hitungTotal() {
        // Check if necessary element is defined and get its values
        const nilaiPajakElement = document.getElementById("nilai_pajak");
        const nilaiBungaElement = document.getElementById("nilai_bunga");
        const nilaiPajak = nilaiPajakElement ? parseFloat(nilaiPajakElement.value) || 0 : 0; // Parse nilaiPajak or default to 0
        const nilaiBunga = nilaiBungaElement ? parseFloat(nilaiBungaElement.value) || 0 : 0; // Parse nilaiBunga or default to 0

        // Perform your calculation here
        let total = nilaiPajak + nilaiBunga;

        // Ensure total is a valid number
        if (isNaN(total)) {
            total = 0;
        }

        return total; // Ensure a number is returned
    }

    // Fungsi untuk menampilkan modal konfirmasi
    function showKonfirmasi() {
        // Validasi form
        const form = document.querySelector('form');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Ambil nilai dari form
        const tanggal = document.getElementById('tanggal').value;
        const jenis = document.getElementById('jenis').value;
        const akunDebit = document.getElementById('id_akun_debit');
        const akunKredit = document.getElementById('id_akun_kredit');
        const nominal = parseFloat(document.getElementById('nominal').value) || 0;
        const pajak = parseFloat(document.getElementById('pajak').value) || 0;
        const bunga = parseFloat(document.getElementById('bunga').value) || 0;
        const keterangan = document.getElementById('keterangan').value;
        const penanggungJawab = document.getElementById('penanggung_jawab').value;
        const fileLampiran = document.getElementById('file_lampiran').value;

        // Hitung total dengan pajak dan bunga
        const nilaiPajak = nominal * (pajak / 100);
        const nilaiBunga = nominal * (bunga / 100);
        const total = nominal + nilaiPajak + nilaiBunga;

        // Format tanggal
        const formattedTanggal = new Date(tanggal).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });

        // Format total
        const formattedTotal = total.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR'
        });

        // Isi modal konfirmasi
        document.getElementById('konfirmasi-tanggal').textContent = formattedTanggal;
        document.getElementById('konfirmasi-jenis').textContent = jenis.replace(/_/g, ' ').toUpperCase();
        document.getElementById('konfirmasi-debit').textContent = akunDebit.options[akunDebit.selectedIndex].text;
        document.getElementById('konfirmasi-kredit').textContent = akunKredit.options[akunKredit.selectedIndex].text;
        document.getElementById('konfirmasi-keterangan').textContent = keterangan;
        document.getElementById('konfirmasi-nominal').textContent = formattedTotal;
        document.getElementById('konfirmasi-penanggung-jawab').textContent = penanggungJawab;

        // Isi tabel ayat jurnal
        document.getElementById('konfirmasi-akun-debit').textContent = akunDebit.options[akunDebit.selectedIndex].text;
        document.getElementById('konfirmasi-akun-kredit').textContent = akunKredit.options[akunKredit.selectedIndex].text;
        document.getElementById('konfirmasi-nilai-debit').textContent = formattedTotal;
        document.getElementById('konfirmasi-nilai-kredit').textContent = formattedTotal;

        // Tampilkan tag jika ada
        
        if (tag) {
            const tag = document.getElementById('tag').value;
            document.getElementById('konfirmasi-tag').textContent = tag;
        }

        // Tampilkan nama file lampiran jika ada
        if (fileLampiran) {
            const fileName = fileLampiran.split('\\').pop();
            document.getElementById('konfirmasi-lampiran').textContent = fileName;
        }

        // Tampilkan modal
        const modal = new bootstrap.Modal(document.getElementById('modalKonfirmasi'));
        modal.show();
    }

    function updateFieldLabels(jenisTransaksi) {
        const labelDebit = document.querySelector('label[for="id_akun_debit"]');
        const labelKredit = document.querySelector('label[for="id_akun_kredit"]');

        switch (jenisTransaksi) {
            case 'pemasukan':
                labelDebit.innerHTML = 'Simpan ke (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Diterima dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'pengeluaran':
                labelDebit.innerHTML = 'Untuk biaya (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Diambil dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'hutang':
                labelDebit.innerHTML = 'Simpan ke (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Hutang dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'piutang':
                labelDebit.innerHTML = 'Simpan ke (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'tanam_modal':
                labelDebit.innerHTML = 'Simpan ke (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Modal (Kredit) <span class="text-danger">*</span>';
                break;
            case 'tarik_modal':
                labelDebit.innerHTML = 'Modal (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Diambil dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'transfer_uang':
                labelDebit.innerHTML = 'ke (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'pemasukan_piutang':
                labelDebit.innerHTML = 'Simpan ke (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Diterima dari (Kredit) <span class="text-danger">*</span>';
                break;
            case 'transfer_hutang':
                labelDebit.innerHTML = 'Untuk biaya (Debit) <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Diambil dari (Kredit) <span class="text-danger">*</span>';
                break;
            default:
                labelDebit.innerHTML = 'Akun Debit <span class="text-danger">*</span>';
                labelKredit.innerHTML = 'Akun Kredit <span class="text-danger">*</span>';
        }
    }

    function filterAkunList(jenisTransaksi) {
        const akunList = JSON.parse(document.getElementById('akun_list_json').value);
        const selectDebit = document.getElementById('id_akun_debit');
        const selectKredit = document.getElementById('id_akun_kredit');

        // Reset options
        selectDebit.innerHTML = '<option value="">Pilih Akun Debit</option>';
        selectKredit.innerHTML = '<option value="">Pilih Akun Kredit</option>';

        // Filter akun berdasarkan jenis transaksi
        let akunDebit = [];
        let akunKredit = [];

        switch (jenisTransaksi) {
            case 'pemasukan':
                akunDebit = akunList.filter(akun => akun.kategori === 'aktiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'pendapatan');
                break;
            case 'pengeluaran':
                akunDebit = akunList.filter(akun => akun.kategori === 'beban');
                akunKredit = akunList.filter(akun => akun.kategori === 'aktiva');
                break;
            case 'hutang':
                akunDebit = akunList.filter(akun => akun.kategori === 'aktiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'pasiva');
                break;
            case 'piutang':
                akunDebit = akunList.filter(akun => akun.kategori === 'aktiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'pendapatan');
                break;
            case 'tanam_modal':
                akunDebit = akunList.filter(akun => akun.kategori === 'aktiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'modal');
                break;
            case 'tarik_modal':
                akunDebit = akunList.filter(akun => akun.kategori === 'modal');
                akunKredit = akunList.filter(akun => akun.kategori === 'aktiva');
                break;
            case 'transfer_uang':
                akunDebit = akunList.filter(akun => akun.kategori === 'aktiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'aktiva');
                break;
            case 'pemasukan_piutang':
                akunDebit = akunList.filter(akun => akun.kategori === 'aktiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'aktiva');
                break;
            case 'transfer_hutang':
                akunDebit = akunList.filter(akun => akun.kategori === 'pasiva');
                akunKredit = akunList.filter(akun => akun.kategori === 'aktiva');
                break;
            default:
                akunDebit = akunList;
                akunKredit = akunList;
        }

        // Populate options
        akunDebit.forEach(akun => {
            const option = document.createElement('option');
            option.value = akun.id;
            option.textContent = akun.kode_akun + ' - ' + akun.nama_akun;
            selectDebit.appendChild(option);
        });

        akunKredit.forEach(akun => {
            const option = document.createElement('option');
            option.value = akun.id;
            option.textContent = akun.kode_akun + ' - ' + akun.nama_akun;
            selectKredit.appendChild(option);
        });
    }

    document.getElementById('jenis').addEventListener('change', function() {
        updateFieldLabels(this.value);
        filterAkunList(this.value);
    });

    // Initialize labels and filters
    updateFieldLabels(document.getElementById('jenis').value);
    filterAkunList(document.getElementById('jenis').value);

    // Set tanggal hari ini sebagai default
    document.getElementById('tanggal').valueAsDate = new Date();

    function hitungTotal() {
        const nominal = parseFloat(document.getElementById('nominal').value) || 0;
        const pajak = parseFloat(document.getElementById('pajak').value) || 0;
        const bunga = parseFloat(document.getElementById('bunga').value) || 0;

        // Hitung nilai pajak dan bunga
        const nilaiPajak = nominal * (pajak / 100);
        const nilaiBunga = nominal * (bunga / 100);

        // Hitung total
        const total = nominal + nilaiPajak + nilaiBunga;

        // Format total sebagai currency
        document.getElementById('total').value = total.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR'
        });

        return total;
    }
</script>

<?php
// Footer
include '../templates/footer.php';
?>