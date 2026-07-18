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
    // Validasi format tanggal
    if (empty($tanggal_berangkat)) {
        return 'Tanggal keberangkatan tidak boleh kosong';
    }

    // Cek apakah format tanggal valid
    $date_parts = explode('-', $tanggal_berangkat);
    if (count($date_parts) !== 3 || !checkdate($date_parts[1], $date_parts[2], $date_parts[0])) {
        return 'Format tanggal tidak valid';
    }

    // Set time to start of day for both dates to ensure accurate date comparison
    $booking_date = strtotime(date('Y-m-d', strtotime($tanggal_berangkat)));
    $today = strtotime(date('Y-m-d'));

    if ($booking_date < $today) {
        return 'Tanggal keberangkatan tidak boleh kurang dari hari ini';
    }

    return null; // Tanggal valid
}

function checkBusAvailability($db, $bus_id, $tanggal_berangkat, $waktu_berangkat = null, $exclude_pemesanan_id = null)
{
    // Validasi parameter
    if (empty($bus_id) || empty($tanggal_berangkat)) {
        error_log("Parameter tidak valid - Bus ID atau tanggal kosong");
        return false;
    }

    try {
        // Query untuk cek ketersediaan bus
        $sql = "SELECT COUNT(*) as total FROM pemesanan_bus 
                WHERE id_bus = ? 
                AND tanggal_berangkat = ? 
                AND status IN ('dibayar_dp', 'dibayar', 'pending')
                AND id != ?";

        $params = [$bus_id, $tanggal_berangkat, $exclude_pemesanan_id ?: 0];

        // Jika ada waktu keberangkatan, tambahkan validasi waktu
        if ($waktu_berangkat) {
            $sql .= " AND waktu_berangkat = ?";
            $params[] = $waktu_berangkat;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Hasil query ketersediaan bus: " . $result['total'] . " pemesanan ditemukan untuk bus ID: $bus_id, tanggal: $tanggal_berangkat");
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

/**
 * Fungsi untuk validasi komprehensif pemesanan bus
 * Mencegah pemesanan ganda pada tanggal yang sama
 */
function validateBusBooking($db, $bus_id, $tanggal_berangkat, $waktu_berangkat, $exclude_pemesanan_id = null)
{
    try {
        // 1. Cek apakah bus sudah dipesan pada tanggal yang sama
        $existing_booking = checkBusAvailability($db, $bus_id, $tanggal_berangkat, $waktu_berangkat, $exclude_pemesanan_id);
        if ($existing_booking) {
            return [
                'valid' => false,
                'message' => 'Bus sudah dipesan pada tanggal ' . date('d/m/Y', strtotime($tanggal_berangkat)) .
                    ($waktu_berangkat ? ' pukul ' . $waktu_berangkat : '')
            ];
        }

        // 2. Cek apakah ada konflik waktu dengan pemesanan lain pada tanggal yang sama
        $sql = "SELECT pb.*, b.nama_bus 
                FROM pemesanan_bus pb 
                JOIN bus b ON pb.id_bus = b.id 
                WHERE pb.tanggal_berangkat = ? 
                AND pb.id != ? 
                AND pb.status IN ('dibayar_dp', 'dibayar', 'pending')
                AND (
                    (pb.waktu_berangkat = ?) OR 
                    (ABS(TIME_TO_SEC(TIMEDIFF(pb.waktu_berangkat, ?)) / 3600) < 2)
                )";

        $stmt = $db->prepare($sql);
        $stmt->execute([$tanggal_berangkat, $exclude_pemesanan_id ?: 0, $waktu_berangkat, $waktu_berangkat]);
        $conflicting_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($conflicting_bookings)) {
            $conflict_details = [];
            foreach ($conflicting_bookings as $conflict) {
                $conflict_details[] = $conflict['nama_bus'] . ' - ' . $conflict['waktu_berangkat'];
            }

            return [
                'valid' => false,
                'message' => 'Ada konflik jadwal dengan pemesanan lain pada tanggal yang sama: ' . implode(', ', $conflict_details)
            ];
        }

        // 3. Cek apakah ada konflik bus yang sama pada tanggal yang sama (mencegah pemesanan ganda)
        $sql_bus_conflict = "SELECT COUNT(*) as total FROM pemesanan_bus pb 
                            WHERE pb.tanggal_berangkat = ? 
                            AND pb.id != ? 
                            AND pb.id_bus = ? 
                            AND pb.status IN ('dibayar_dp', 'dibayar', 'pending')";

        $stmt_conflict = $db->prepare($sql_bus_conflict);
        $stmt_conflict->execute([$tanggal_berangkat, $exclude_pemesanan_id ?: 0, $bus_id]);
        $bus_conflict = $stmt_conflict->fetch(PDO::FETCH_ASSOC);

        if ($bus_conflict['total'] > 0) {
            return [
                'valid' => false,
                'message' => 'Bus ini sudah dipesan pada tanggal ' . date('d/m/Y', strtotime($tanggal_berangkat)) . '. Setiap bus hanya bisa dipesan satu kali per tanggal.'
            ];
        }

        // 4. Cek apakah ada bus lain yang sudah dipesan pada tanggal yang sama (mencegah jadwal ganda)
        $sql_other_bus = "SELECT COUNT(*) as total FROM pemesanan_bus pb 
                          WHERE pb.tanggal_berangkat = ? 
                          AND pb.id != ? 
                          AND pb.status IN ('dibayar_dp', 'dibayar', 'pending')";

        $stmt_other_bus = $db->prepare($sql_other_bus);
        $stmt_other_bus->execute([$tanggal_berangkat, $exclude_pemesanan_id ?: 0]);
        $other_bus_count = $stmt_other_bus->fetch(PDO::FETCH_ASSOC);

        if ($other_bus_count['total'] > 0) {
            return [
                'valid' => false,
                'message' => 'Sudah ada bus lain yang dipesan pada tanggal ' . date('d/m/Y', strtotime($tanggal_berangkat)) . '. Hanya satu bus yang boleh berangkat per tanggal.'
            ];
        }

        // 5. Cek apakah bus tersedia untuk tanggal tersebut (tidak sedang maintenance atau rusak)
        $stmt_bus = $db->prepare("SELECT status FROM bus WHERE id = ?");
        $stmt_bus->execute([$bus_id]);
        $bus_status = $stmt_bus->fetch(PDO::FETCH_COLUMN);

        if ($bus_status && $bus_status !== 'tersedia') {
            return [
                'valid' => false,
                'message' => 'Bus sedang tidak tersedia (Status: ' . ucfirst($bus_status) . ')'
            ];
        }

        // 6. Validasi waktu keberangkatan (tidak boleh terlalu pagi atau terlalu malam)
        $waktu_hour = (int)date('H', strtotime($waktu_berangkat));
        if ($waktu_hour < 5 || $waktu_hour > 23) {
            return [
                'valid' => false,
                'message' => 'Waktu keberangkatan harus antara pukul 05:00 - 23:00'
            ];
        }

        // Semua validasi berhasil
        return [
            'valid' => true,
            'message' => 'Bus tersedia untuk pemesanan'
        ];
    } catch (PDOException $e) {
        error_log("Error saat validasi pemesanan bus: " . $e->getMessage());
        return [
            'valid' => false,
            'message' => 'Terjadi kesalahan saat validasi: ' . $e->getMessage()
        ];
    }
}

/**
 * Fungsi untuk mendapatkan jadwal bus yang tersedia
 * Sinkron dengan aturan validateBusBooking
 */
function getAvailableBusSchedule($db, $tanggal_berangkat, $waktu_berangkat = null, $exclude_bus_ids = [])
{
    try {
        // Debug info
        error_log("getAvailableBusSchedule called with tanggal: $tanggal_berangkat");

        // Cek apakah sudah ada bus yang dipesan pada tanggal ini
        $stmt_check_date = $db->prepare("SELECT COUNT(*) as total FROM pemesanan_bus 
                                        WHERE tanggal_berangkat = ? 
                                        AND status IN ('dibayar_dp', 'dibayar', 'pending')");
        $stmt_check_date->execute([$tanggal_berangkat]);
        $existing_booking = $stmt_check_date->fetch(PDO::FETCH_ASSOC);

        // Jika sudah ada bus yang dipesan pada tanggal ini, tidak ada bus yang tersedia
        if ($existing_booking['total'] > 0) {
            error_log("getAvailableBusSchedule: Sudah ada " . $existing_booking['total'] . " pemesanan untuk tanggal $tanggal_berangkat");
            return [];
        }

        // Jika belum ada pemesanan, semua bus dengan status 'tersedia' bisa dipilih
        $sql = "SELECT b.id, b.nama_bus, b.kapasitas, b.nomor_polisi, b.fasilitas,
                0 as total_pemesanan,
                b.kapasitas as sisa_kapasitas
                FROM bus b 
                WHERE b.status = 'tersedia'";

        $params = [];

        if (!empty($exclude_bus_ids)) {
            $placeholders = str_repeat('?,', count($exclude_bus_ids) - 1) . '?';
            $sql .= " AND b.id NOT IN ($placeholders)";
            $params = array_merge($params, $exclude_bus_ids);
        }

        $sql .= " ORDER BY b.nama_bus ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug info
        error_log("getAvailableBusSchedule result: " . count($result) . " buses found for date $tanggal_berangkat");

        return $result;
    } catch (PDOException $e) {
        error_log("Error saat mendapatkan jadwal bus: " . $e->getMessage());
        return [];
    }
}

/**
 * Fungsi untuk mengecek apakah tanggal tertentu sudah ada pemesanan bus
 */
function isDateBooked($db, $tanggal_berangkat, $exclude_pemesanan_id = null)
{
    try {
        $sql = "SELECT COUNT(*) as total FROM pemesanan_bus 
                WHERE tanggal_berangkat = ? 
                AND status IN ('dibayar_dp', 'dibayar', 'pending')";

        $params = [$tanggal_berangkat];

        if ($exclude_pemesanan_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_pemesanan_id;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] > 0;
    } catch (PDOException $e) {
        error_log("Error saat cek tanggal yang sudah dipesan: " . $e->getMessage());
        return false;
    }
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
