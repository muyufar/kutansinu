<?php
// Format angka ke format rupiah
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi untuk memeriksa role pengguna pada perusahaan tertentu
function checkUserRole($db, $user_id, $perusahaan_id, $required_role = 'admin')
{
    $stmt = $db->prepare("SELECT role FROM user_perusahaan WHERE user_id = ? AND perusahaan_id = ?");
    $stmt->execute([$user_id, $perusahaan_id]);
    $user_role = $stmt->fetchColumn();

    // Jika role tidak ditemukan, user tidak memiliki akses
    if (!$user_role) {
        return false;
    }

    // Cek berdasarkan hierarki role
    switch ($required_role) {
        case 'viewer':
            // Viewer hanya bisa melihat, semua role bisa melihat
            return in_array($user_role, ['admin', 'editor', 'viewer']);

        case 'editor':
            // Editor bisa edit, admin juga bisa edit
            return in_array($user_role, ['admin', 'editor']);

        case 'admin':
            // Hanya admin yang bisa melakukan tindakan admin
            return $user_role === 'admin';

        default:
            return false;
    }
}

// Mendapatkan total pemasukan
function getTotalPemasukan($db)
{
    $stmt = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pemasukan'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Mendapatkan total pengeluaran
function getTotalPengeluaran($db)
{
    $stmt = $db->prepare("SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pengeluaran'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Mendapatkan saldo
function getSaldo($db)
{
    return getTotalPemasukan($db) - getTotalPengeluaran($db);
}

// Mendapatkan kelas badge untuk role
function getRoleBadgeClass($role)
{
    switch ($role) {
        case 'admin':
            return 'primary';
        case 'editor':
            return 'warning';
        case 'viewer':
            return 'info';
        case 'staff':
            return 'success';
        default:
            return 'secondary';
    }
}

// Menghasilkan password acak
function generateRandomPassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?';
    $password = '';
    $max = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }

    return $password;
}

// Validasi input
function validateInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateSaldo($id_akun, $jumlah, $jenis)
{
    global $db;

    if (!in_array($jenis, ['pengeluaran', 'tarik_modal', 'transfer_uang', 'transfer_hutang'], true)) {
        return true;
    }

    $id_perusahaan = $_SESSION['default_company'] ?? null;

    if ($id_perusahaan) {
        $stmt = $db->prepare("SELECT saldo, kategori FROM akun WHERE id = ? AND id_perusahaan = ?");
        $stmt->execute([$id_akun, $id_perusahaan]);
    } else {
        $stmt = $db->prepare("SELECT saldo, kategori FROM akun WHERE id = ?");
        $stmt->execute([$id_akun]);
    }

    $akun = $stmt->fetch();
    if (!$akun) {
        return false;
    }

    // Hanya cek saldo kas/bank; akun non-aktiva (mis. hutang) tidak dibatasi saldo
    if ($akun['kategori'] !== 'aktiva') {
        return true;
    }

    return $akun['saldo'] >= $jumlah;
}

// Cek apakah user sudah login
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Redirect jika user belum login
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit();
    }
}

// Mendapatkan nama akun berdasarkan ID
function getNamaAkun($db, $id_akun, $id_perusahaan = null)
{
    // Jika id_perusahaan tidak diberikan, coba ambil dari session
    if ($id_perusahaan === null && isset($_SESSION['default_company'])) {
        $id_perusahaan = $_SESSION['default_company'];
    }
    
    // Jika id_perusahaan tersedia, filter berdasarkan perusahaan
    if ($id_perusahaan) {
        $stmt = $db->prepare("SELECT nama_akun FROM akun WHERE id = ? AND id_perusahaan = ?");
        $stmt->execute([$id_akun, $id_perusahaan]);
    } else {
        // Jika tidak ada id_perusahaan, cari berdasarkan ID saja
        $stmt = $db->prepare("SELECT nama_akun FROM akun WHERE id = ?");
        $stmt->execute([$id_akun]);
    }
    
    $result = $stmt->fetch();
    return $result['nama_akun'] ?? '-';
}

// Mendapatkan daftar akun berdasarkan perusahaan
function getDaftarAkun($db, $id_perusahaan = null)
{
    // Jika id_perusahaan tidak diberikan, coba ambil dari session
    if ($id_perusahaan === null && isset($_SESSION['default_company'])) {
        $id_perusahaan = $_SESSION['default_company'];
    }
    
    // Jika id_perusahaan tersedia, filter berdasarkan perusahaan
    if ($id_perusahaan) {
        $stmt = $db->prepare("SELECT * FROM akun WHERE id_perusahaan = ? ORDER BY kode_akun ASC");
        $stmt->execute([$id_perusahaan]);
    } else {
        // Jika tidak ada id_perusahaan, tampilkan semua akun (untuk admin)
        $stmt = $db->query("SELECT * FROM akun ORDER BY kode_akun ASC");
    }
    
    return $stmt->fetchAll();
}

// Mendapatkan neraca saldo
function getNeracaSaldo($db, $tanggal_awal, $tanggal_akhir, $id_perusahaan)
{
    $sql = "SELECT 
                a.id,
                a.kode_akun,
                a.nama_akun,
                COALESCE(SUM(CASE WHEN t.id_akun_debit = a.id THEN t.jumlah ELSE 0 END), 0) as debit,
                COALESCE(SUM(CASE WHEN t.id_akun_kredit = a.id THEN t.jumlah ELSE 0 END), 0) as kredit
            FROM akun a
            LEFT JOIN transaksi t ON (a.id = t.id_akun_debit OR a.id = t.id_akun_kredit)
                AND t.tanggal BETWEEN ? AND ?
                AND t.id_perusahaan = ?
            GROUP BY a.id, a.kode_akun, a.nama_akun
            ORDER BY a.kode_akun ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$tanggal_awal, $tanggal_akhir, $id_perusahaan]);
    return $stmt->fetchAll();
}

function getSaldoAkunSampaiTanggal($db, $id_akun, $tanggal_akhir, $id_perusahaan, $tipe_akun)
{
    $stmt = $db->prepare('
        SELECT
            COALESCE(SUM(CASE WHEN t.id_akun_debit = ? THEN t.jumlah ELSE 0 END), 0) AS total_debit,
            COALESCE(SUM(CASE WHEN t.id_akun_kredit = ? THEN t.jumlah ELSE 0 END), 0) AS total_kredit
        FROM transaksi t
        WHERE t.id_perusahaan = ?
          AND t.tanggal <= ?
          AND (t.id_akun_debit = ? OR t.id_akun_kredit = ?)
    ');
    $stmt->execute([$id_akun, $id_akun, $id_perusahaan, $tanggal_akhir, $id_akun, $id_akun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return hitungMutasiAkun($row['total_debit'], $row['total_kredit'], $tipe_akun);
}

function getLabaRugiPeriode($db, $tanggal_awal, $tanggal_akhir, $id_perusahaan)
{
    $sql_pendapatan = "SELECT 
        a.kode_akun,
        a.nama_akun,
        COALESCE(SUM(CASE WHEN t.jenis = 'pemasukan' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
    FROM akun a
    LEFT JOIN transaksi t ON (a.id = t.id_akun_kredit OR a.id = t.id_akun_debit)
        AND t.tanggal BETWEEN ? AND ?
        AND t.id_perusahaan = ?
    WHERE a.kategori = 'pendapatan'
      AND a.id_perusahaan = ?
    GROUP BY a.id, a.kode_akun, a.nama_akun
    ORDER BY a.kode_akun ASC";

    $stmt_pendapatan = $db->prepare($sql_pendapatan);
    $stmt_pendapatan->execute([$tanggal_awal, $tanggal_akhir, $id_perusahaan, $id_perusahaan]);
    $data_pendapatan = array_values(array_filter($stmt_pendapatan->fetchAll(), function ($item) {
        return $item['jumlah'] != 0;
    }));

    $sql_beban = "SELECT 
        a.kode_akun,
        a.nama_akun,
        COALESCE(SUM(CASE WHEN t.jenis = 'pengeluaran' THEN t.jumlah ELSE -t.jumlah END), 0) as jumlah
    FROM akun a
    LEFT JOIN transaksi t ON (a.id = t.id_akun_debit OR a.id = t.id_akun_kredit)
        AND t.tanggal BETWEEN ? AND ?
        AND t.id_perusahaan = ?
    WHERE a.kategori = 'beban'
      AND a.id_perusahaan = ?
    GROUP BY a.id, a.kode_akun, a.nama_akun
    ORDER BY a.kode_akun ASC";

    $stmt_beban = $db->prepare($sql_beban);
    $stmt_beban->execute([$tanggal_awal, $tanggal_akhir, $id_perusahaan, $id_perusahaan]);
    $data_beban = array_values(array_filter($stmt_beban->fetchAll(), function ($item) {
        return $item['jumlah'] != 0;
    }));

    $total_pendapatan = array_sum(array_column($data_pendapatan, 'jumlah'));
    $total_beban = array_sum(array_column($data_beban, 'jumlah'));

    return [
        'pendapatan' => $data_pendapatan,
        'beban' => $data_beban,
        'total_pendapatan' => $total_pendapatan,
        'total_beban' => $total_beban,
        'laba_bersih' => $total_pendapatan - $total_beban,
    ];
}

function groupAkunBySubKategori(array $accounts)
{
    $grouped = [];

    foreach ($accounts as $account) {
        $sub = $account['sub_kategori'] ?: 'Lainnya';
        $grouped[$sub][] = $account;
    }

    return $grouped;
}

function getNeracaData($db, $tanggal_akhir, $id_perusahaan, $tanggal_awal_laba = null)
{
    if (!$tanggal_awal_laba) {
        $tanggal_awal_laba = date('Y-01-01', strtotime($tanggal_akhir));
    }

    $sql = "SELECT 
                a.id,
                a.kode_akun,
                a.nama_akun,
                a.kategori,
                a.sub_kategori,
                a.tipe_akun,
                COALESCE(SUM(CASE WHEN t.id_akun_debit = a.id THEN t.jumlah ELSE 0 END), 0) AS total_debit,
                COALESCE(SUM(CASE WHEN t.id_akun_kredit = a.id THEN t.jumlah ELSE 0 END), 0) AS total_kredit
            FROM akun a
            LEFT JOIN transaksi t ON (a.id = t.id_akun_debit OR a.id = t.id_akun_kredit)
                AND t.tanggal <= ?
                AND t.id_perusahaan = ?
            WHERE a.id_perusahaan = ?
              AND a.kategori IN ('aktiva', 'pasiva', 'modal')
            GROUP BY a.id, a.kode_akun, a.nama_akun, a.kategori, a.sub_kategori, a.tipe_akun
            ORDER BY a.kode_akun ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$tanggal_akhir, $id_perusahaan, $id_perusahaan]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $aktiva = [];
    $kewajiban = [];
    $ekuitas = [];

    foreach ($rows as $row) {
        $saldo = hitungMutasiAkun($row['total_debit'], $row['total_kredit'], $row['tipe_akun']);
        if (abs($saldo) < 0.005) {
            continue;
        }

        $item = [
            'kode_akun' => $row['kode_akun'],
            'nama_akun' => $row['nama_akun'],
            'sub_kategori' => $row['sub_kategori'],
            'saldo' => $saldo,
        ];

        if ($row['kategori'] === 'aktiva') {
            $aktiva[] = $item;
        } elseif ($row['kategori'] === 'pasiva') {
            $kewajiban[] = $item;
        } else {
            $ekuitas[] = $item;
        }
    }

    $laba_rugi = getLabaRugiPeriode($db, $tanggal_awal_laba, $tanggal_akhir, $id_perusahaan);
    $laba_bersih = $laba_rugi['laba_bersih'];

    $total_aktiva = array_sum(array_column($aktiva, 'saldo'));
    $total_kewajiban = array_sum(array_column($kewajiban, 'saldo'));
    $total_ekuitas_akun = array_sum(array_column($ekuitas, 'saldo'));
    $total_ekuitas = $total_ekuitas_akun + $laba_bersih;
    $total_pasiva = $total_kewajiban + $total_ekuitas;

    return [
        'aktiva' => $aktiva,
        'aktiva_grouped' => groupAkunBySubKategori($aktiva),
        'kewajiban' => $kewajiban,
        'kewajiban_grouped' => groupAkunBySubKategori($kewajiban),
        'ekuitas' => $ekuitas,
        'ekuitas_grouped' => groupAkunBySubKategori($ekuitas),
        'laba_bersih' => $laba_bersih,
        'tanggal_awal_laba' => $tanggal_awal_laba,
        'total_aktiva' => $total_aktiva,
        'total_kewajiban' => $total_kewajiban,
        'total_ekuitas_akun' => $total_ekuitas_akun,
        'total_ekuitas' => $total_ekuitas,
        'total_pasiva' => $total_pasiva,
        'seimbang' => abs($total_aktiva - $total_pasiva) < 0.01,
    ];
}

function logAudit($db, $user_id, $action, $description = '')
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip, $ua]);
}

function getReportFilterPerusahaan($db, $user_id, $requested_perusahaan = null)
{
    $stmt = $db->prepare("SELECT default_company FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $id_perusahaan = $stmt->fetchColumn();

    if (!$id_perusahaan) {
        $_SESSION['error'] = 'Anda belum memiliki perusahaan default. Silakan tambahkan perusahaan terlebih dahulu.';
        header('Location: /pengaturan/perusahaan.php');
        exit();
    }

    $filter_perusahaan = $id_perusahaan;

    if ($requested_perusahaan && checkUserRole($db, $user_id, $id_perusahaan, 'admin')) {
        if (checkUserRole($db, $user_id, $requested_perusahaan, 'viewer')) {
            $filter_perusahaan = $requested_perusahaan;
        }
    }

    return $filter_perusahaan;
}

function appendTransaksiTagFilter(&$sql, &$params, $tags, $prefix = 't.', $param_prefix = 'tag')
{
    if (empty($tags)) {
        return;
    }

    $tag_conditions = [];
    foreach ($tags as $index => $tag_value) {
        $tag_conditions[] = "({$prefix}tag = :{$param_prefix}{$index}
                            OR {$prefix}tag LIKE :{$param_prefix}{$index}_start
                            OR {$prefix}tag LIKE :{$param_prefix}{$index}_end
                            OR {$prefix}tag LIKE :{$param_prefix}{$index}_middle)";

        $params[":{$param_prefix}{$index}"] = $tag_value;
        $params[":{$param_prefix}{$index}_start"] = $tag_value . ',%';
        $params[":{$param_prefix}{$index}_end"] = '%,' . $tag_value;
        $params[":{$param_prefix}{$index}_middle"] = '%,' . $tag_value . ',%';
    }

    $sql .= ' AND (' . implode(' OR ', $tag_conditions) . ')';
}

function hitungMutasiAkun($total_debit, $total_kredit, $tipe_akun)
{
    $total_debit = (float)$total_debit;
    $total_kredit = (float)$total_kredit;

    if ($tipe_akun === 'debit') {
        return $total_debit - $total_kredit;
    }

    return $total_kredit - $total_debit;
}

function getAkunById($db, $id_akun, $id_perusahaan)
{
    $stmt = $db->prepare('SELECT * FROM akun WHERE id = ? AND id_perusahaan = ?');
    $stmt->execute([$id_akun, $id_perusahaan]);
    $akun = $stmt->fetch(PDO::FETCH_ASSOC);

    return $akun ?: null;
}

function getSaldoAwalAkun($db, $id_akun, $tanggal_awal, $id_perusahaan, $tipe_akun)
{
    $stmt = $db->prepare('
        SELECT
            COALESCE(SUM(CASE WHEN t.id_akun_debit = ? THEN t.jumlah ELSE 0 END), 0) AS total_debit,
            COALESCE(SUM(CASE WHEN t.id_akun_kredit = ? THEN t.jumlah ELSE 0 END), 0) AS total_kredit
        FROM transaksi t
        WHERE t.id_perusahaan = ?
          AND t.tanggal < ?
          AND (t.id_akun_debit = ? OR t.id_akun_kredit = ?)
    ');
    $stmt->execute([$id_akun, $id_akun, $id_perusahaan, $tanggal_awal, $id_akun, $id_akun]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return hitungMutasiAkun($row['total_debit'], $row['total_kredit'], $tipe_akun);
}

function getBukuBesarMutasi($db, $id_akun, $tanggal_awal, $tanggal_akhir, $id_perusahaan)
{
    $sql = '
        SELECT * FROM (
            SELECT
                t.id AS transaksi_id,
                t.tanggal,
                t.keterangan,
                t.jenis,
                t.jumlah AS debit,
                0 AS kredit,
                \'D\' AS posisi,
                ak.kode_akun AS lawan_kode,
                ak.nama_akun AS lawan_nama,
                t.penanggung_jawab,
                t.tag
            FROM transaksi t
            INNER JOIN akun ak ON t.id_akun_kredit = ak.id
            WHERE t.id_akun_debit = ?
              AND t.id_perusahaan = ?
              AND t.tanggal BETWEEN ? AND ?

            UNION ALL

            SELECT
                t.id AS transaksi_id,
                t.tanggal,
                t.keterangan,
                t.jenis,
                0 AS debit,
                t.jumlah AS kredit,
                \'K\' AS posisi,
                ad.kode_akun AS lawan_kode,
                ad.nama_akun AS lawan_nama,
                t.penanggung_jawab,
                t.tag
            FROM transaksi t
            INNER JOIN akun ad ON t.id_akun_debit = ad.id
            WHERE t.id_akun_kredit = ?
              AND t.id_perusahaan = ?
              AND t.tanggal BETWEEN ? AND ?
        ) AS mutasi
        ORDER BY tanggal ASC, transaksi_id ASC, posisi ASC
    ';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $id_akun,
        $id_perusahaan,
        $tanggal_awal,
        $tanggal_akhir,
        $id_akun,
        $id_perusahaan,
        $tanggal_awal,
        $tanggal_akhir,
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildBukuBesarRows(array $mutasi_list, $saldo_awal, $tipe_akun)
{
    $saldo = (float)$saldo_awal;
    $rows = [];
    $total_debit = 0;
    $total_kredit = 0;

    foreach ($mutasi_list as $mutasi) {
        $debit = (float)$mutasi['debit'];
        $kredit = (float)$mutasi['kredit'];
        $total_debit += $debit;
        $total_kredit += $kredit;

        if ($tipe_akun === 'debit') {
            $saldo += $debit - $kredit;
        } else {
            $saldo += $kredit - $debit;
        }

        $rows[] = array_merge($mutasi, ['saldo' => $saldo]);
    }

    return [
        'rows' => $rows,
        'total_debit' => $total_debit,
        'total_kredit' => $total_kredit,
        'saldo_akhir' => $saldo,
    ];
}

function getBukuBesarData($db, $id_akun, $tanggal_awal, $tanggal_akhir, $id_perusahaan)
{
    $akun = getAkunById($db, $id_akun, $id_perusahaan);
    if (!$akun) {
        return null;
    }

    $saldo_awal = getSaldoAwalAkun($db, $id_akun, $tanggal_awal, $id_perusahaan, $akun['tipe_akun']);
    $mutasi_list = getBukuBesarMutasi($db, $id_akun, $tanggal_awal, $tanggal_akhir, $id_perusahaan);
    $ledger = buildBukuBesarRows($mutasi_list, $saldo_awal, $akun['tipe_akun']);

    return [
        'akun' => $akun,
        'saldo_awal' => $saldo_awal,
        'rows' => $ledger['rows'],
        'total_debit' => $ledger['total_debit'],
        'total_kredit' => $ledger['total_kredit'],
        'saldo_akhir' => $ledger['saldo_akhir'],
    ];
}
