<?php
function checkNugrosirAccess($db, $user_id)
{
    $stmt = $db->prepare("SELECT 1 FROM user_perusahaan up
                    JOIN perusahaan p ON up.perusahaan_id = p.id
                    WHERE up.user_id = ? AND UPPER(p.nama) = 'NUGO' AND up.status = 'active'");
    $stmt->execute([$user_id]);
    return $stmt->fetch() ? true : false;
}

function getUserData($db, $user_id)
{
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function validateBookingDate($tanggal_berangkat)
{
    // Set time to start of day for both dates to ensure accurate date comparison
    $booking_date = strtotime(date('Y-m-d', strtotime($tanggal_berangkat)));
    $today = strtotime(date('Y-m-d'));

    if ($booking_date < $today) {
        return 'Tanggal keberangkatan tidak boleh kurang dari hari ini';
    }
    return null;
}

function checkBusAvailability($db, $bus_id, $tanggal_berangkat)
{
    // Validasi parameter
    if (empty($bus_id) || empty($tanggal_berangkat)) {
        error_log("Parameter tidak valid - Bus ID atau tanggal kosong");
        return false;
    }

    try {
        // Format tanggal untuk query
        // $tanggal_berangkat = date('Y-m-d', strtotime($tanggal_berangkat));

        $query = "SELECT COUNT(*) as total FROM pemesanan_bus 
                          WHERE id_bus = $bus_id
                          AND tanggal_berangkat = $tanggal_berangkat
                          AND status IN ('dibayar_dp', 'dibayar', 'pending')";
        // echo '<pre>';
        // echo $query;
        // die;

        $stmt = $db->prepare($query);
        $stmt->execute([$bus_id, $tanggal_berangkat]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Hasil query: " . $result['total'] . " pemesanan ditemukan");
        return $result['total'] > 0;
    } catch (PDOException $e) {
        error_log("Error saat cek ketersediaan bus: " . $e->getMessage());
        return false;
    }
}

function uploadBuktiPembayaran($files)
{
    $uploaded_files = [];

    // Handle single file upload (backward compatibility)
    if (!isset($files['name']) || !is_array($files['name'])) {
        if ($files['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/pembayaran_bus/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = time() . '_' . basename($files['name']);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($files['tmp_name'], $target_file)) {
                $uploaded_files[] = $file_name;
            }
        }
        return $uploaded_files;
    }

    // Handle multiple file upload
    $upload_dir = '../uploads/pembayaran_bus/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_count = count($files['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] == UPLOAD_ERR_OK) {
            $file_name = time() . '_' . $i . '_' . basename($files['name'][$i]);
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                $uploaded_files[] = $file_name;
            }
        }
    }

    return $uploaded_files;
}

function processPayment($jenis_pembayaran, $jumlah_bayar, $bukti_pembayaran)
{
    $status = 'pending';
    $pembayaran_dp = null;
    $dp_created_at = null;
    $created_at = null;

    if (!empty($bukti_pembayaran)) {
        if ($jenis_pembayaran === 'lunas') {
            $status = 'dibayar';
            $created_at = date('Y-m-d H:i:s');
        } else if ($jenis_pembayaran === 'dp') {
            $status = 'dibayar_dp';
            $pembayaran_dp = $jumlah_bayar;
            $dp_created_at = date('Y-m-d H:i:s');
            $jumlah_bayar = 0;
        }
    }

    return [
        'status' => $status,
        'pembayaran_dp' => $pembayaran_dp,
        'dp_created_at' => $dp_created_at,
        'jumlah_bayar' => $jumlah_bayar,
        'created_at' => $created_at
    ];
}
