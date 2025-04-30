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
function getTotalPemasukan($db, $id_perusahaan = null)
{
    $sql = "SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pemasukan'";
    $params = [];
    
    if ($id_perusahaan) {
        $sql .= " AND id_perusahaan = ?";
        $params[] = $id_perusahaan;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Mendapatkan total pengeluaran
function getTotalPengeluaran($db, $id_perusahaan = null)
{
    $sql = "SELECT SUM(jumlah) as total FROM transaksi WHERE jenis = 'pengeluaran'";
    $params = [];
    
    if ($id_perusahaan) {
        $sql .= " AND id_perusahaan = ?";
        $params[] = $id_perusahaan;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Mendapatkan saldo
function getSaldo($db, $id_perusahaan = null)
{
    return getTotalPemasukan($db, $id_perusahaan) - getTotalPengeluaran($db, $id_perusahaan);
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

function validateSaldo($id_akun, $jumlah, $jenis) {
    global $db;
    $stmt = $db->prepare("SELECT saldo FROM akun WHERE id = ?");
    $stmt->execute([$id_akun]);
    $akun = $stmt->fetch();
    
    if ($jenis == 'pengeluaran' || $jenis == 'tarik_modal' || $jenis == 'transfer_uang' || $jenis == 'transfer_hutang') {
        if ($akun['saldo'] < $jumlah) {
            return false;
        }
    }
    return true;
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
        header('Location: /kutansinu/login.php');
        exit();
    }
}

// Mendapatkan nama akun berdasarkan ID
function getNamaAkun($db, $id_akun)
{
    $stmt = $db->prepare("SELECT nama_akun FROM akun WHERE id = ?");
    $stmt->execute([$id_akun]);
    $result = $stmt->fetch();
    return $result['nama_akun'] ?? '-';
}

// Mendapatkan daftar akun
function getDaftarAkun($db)
{
    $stmt = $db->query("SELECT * FROM akun ORDER BY kode_akun ASC");
    return $stmt->fetchAll();
}

// Mendapatkan neraca saldo
function getNeracaSaldo($db, $tanggal_awal, $tanggal_akhir, $id_perusahaan = null)
{
    $params = [$tanggal_awal, $tanggal_akhir];
    $sql = "SELECT 
                a.id,
                a.kode_akun,
                a.nama_akun,
                COALESCE(SUM(CASE WHEN t.id_akun_debit = a.id THEN t.jumlah ELSE 0 END), 0) as debit,
                COALESCE(SUM(CASE WHEN t.id_akun_kredit = a.id THEN t.jumlah ELSE 0 END), 0) as kredit
            FROM akun a
            LEFT JOIN transaksi t ON (a.id = t.id_akun_debit OR a.id = t.id_akun_kredit)
                AND t.tanggal BETWEEN ? AND ?";
    
    // Tambahkan filter id_perusahaan jika ada
    if ($id_perusahaan) {
        $sql .= " AND t.id_perusahaan = ?";
        $params[] = $id_perusahaan;
    }
    
    $sql .= " GROUP BY a.id, a.kode_akun, a.nama_akun
              ORDER BY a.kode_akun ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
