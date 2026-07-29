<?php

require_once __DIR__ . '/bus_helper.php';

function getInactiveBookingStatuses(): array
{
    return ['dibatalkan', 'ditolak'];
}

function pemesananCanEdit(string $status): bool
{
    return in_array($status, getBlockingBookingStatuses(), true);
}

function pemesananCanCancel(string $status): bool
{
    return pemesananCanEdit($status);
}

function pemesananCanDelete(string $status, bool $is_admin): bool
{
    if (!$is_admin) {
        return false;
    }

    return in_array($status, ['pending', 'dibatalkan', 'ditolak'], true);
}

function isBusStaff($db, $user_id): bool
{
    return checkNugrosirAccess($db, $user_id);
}

function getPemesananById($db, $pemesanan_id)
{
    $stmt = $db->prepare("
        SELECT pb.*, b.nama_bus, b.nomor_polisi, b.kapasitas, b.fasilitas, b.id_perusahaan, b.status AS status_bus
        FROM pemesanan_bus pb
        JOIN bus b ON pb.id_bus = b.id
        WHERE pb.id = ?
    ");
    $stmt->execute([(int) $pemesanan_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function userCanAccessPemesanan($db, $user_id, array $pemesanan, bool $require_staff = false): bool
{
    if ($require_staff && !isBusStaff($db, $user_id)) {
        return false;
    }

    if (isBusStaff($db, $user_id)) {
        return true;
    }

    return (int) $pemesanan['id_user'] === (int) $user_id;
}

function getBusListForPemesanan($db, $id_perusahaan = null, $include_bus_id = null)
{
    $sql = "SELECT id, nama_bus, nomor_polisi, kapasitas, status FROM bus WHERE 1=1";
    $params = [];

    if ($id_perusahaan) {
        $sql .= " AND id_perusahaan = ?";
        $params[] = $id_perusahaan;
    }

    if ($include_bus_id) {
        $sql .= " AND (status = 'tersedia' OR id = ?)";
        $params[] = (int) $include_bus_id;
    } else {
        $sql .= " AND status = 'tersedia'";
    }

    $sql .= " ORDER BY nama_bus ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatPemesananStatus(string $status): string
{
    $labels = [
        'pending' => 'Pending',
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'dibayar_dp' => 'Dibayar DP',
        'dibayar' => 'Dibayar Lunas',
        'dikonfirmasi' => 'Dikonfirmasi',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan',
        'ditolak' => 'Ditolak',
    ];

    return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
}

function getPemesananStatusBadgeClass(string $status): string
{
    switch ($status) {
        case 'menunggu_pembayaran':
        case 'dibayar_dp':
            return 'bg-warning text-dark';
        case 'dibayar':
        case 'dikonfirmasi':
            return 'bg-info';
        case 'selesai':
            return 'bg-success';
        case 'dibatalkan':
        case 'ditolak':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function cancelPemesananBus($db, $pemesanan_id, $catatan = '', $by_staff = false)
{
    $pemesanan = getPemesananById($db, $pemesanan_id);
    if (!$pemesanan) {
        return ['success' => false, 'message' => 'Pemesanan tidak ditemukan.'];
    }

    if (!pemesananCanCancel($pemesanan['status'])) {
        return ['success' => false, 'message' => 'Pemesanan dengan status "' . formatPemesananStatus($pemesanan['status']) . '" tidak dapat dibatalkan.'];
    }

    $catatan_baru = trim($catatan);
    if ($catatan_baru !== '') {
        $prefix = $by_staff ? '[Admin] ' : '[User] ';
        $catatan_admin = trim(($pemesanan['catatan_admin'] ?? '') . "\n" . $prefix . 'Dibatalkan: ' . $catatan_baru);
    } else {
        $catatan_admin = $pemesanan['catatan_admin'] ?? '';
    }

    $stmt = $db->prepare("UPDATE pemesanan_bus SET status = 'dibatalkan', catatan_admin = ? WHERE id = ?");
    $stmt->execute([$catatan_admin, $pemesanan_id]);

    return ['success' => true, 'message' => 'Pemesanan #' . $pemesanan_id . ' berhasil dibatalkan.'];
}

function deletePemesananBusFiles($db, $pemesanan_id)
{
    $upload_dir = dirname(__DIR__, 2) . '/uploads/pembayaran_bus/';

    $stmt = $db->prepare("SELECT nama_file FROM bukti_pembayaran_bus WHERE pemesanan_id = ?");
    $stmt->execute([(int) $pemesanan_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
        $path = $upload_dir . $file;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $pemesanan = getPemesananById($db, $pemesanan_id);
    if (!$pemesanan) {
        return;
    }

    foreach (['bukti_pembayaran', 'bukti_transfer_admin'] as $field) {
        if (empty($pemesanan[$field])) {
            continue;
        }
        $filename = basename($pemesanan[$field]);
        $path = $upload_dir . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function deleteLinkedTripFromPemesanan($db, $pemesanan_id)
{
    require_once __DIR__ . '/bus_transaksi_sync.php';

    $stmt = $db->prepare("SELECT id FROM bus_laporan_trip WHERE id_pemesanan = ?");
    $stmt->execute([(int) $pemesanan_id]);
    $trip_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($trip_ids as $trip_id) {
        removeSourceTransaksiSync($db, 'bus_laporan_trip', (int) $trip_id);
        $del = $db->prepare("DELETE FROM bus_laporan_trip WHERE id = ?");
        $del->execute([(int) $trip_id]);
    }
}

function deletePemesananBus($db, $pemesanan_id)
{
    $pemesanan = getPemesananById($db, $pemesanan_id);
    if (!$pemesanan) {
        return ['success' => false, 'message' => 'Pemesanan tidak ditemukan.'];
    }

    if (!pemesananCanDelete($pemesanan['status'], true)) {
        return ['success' => false, 'message' => 'Hanya pemesanan pending/dibatalkan/ditolak yang dapat dihapus permanen.'];
    }

    try {
        $db->beginTransaction();

        deleteLinkedTripFromPemesanan($db, $pemesanan_id);
        deletePemesananBusFiles($db, $pemesanan_id);

        $db->prepare("DELETE FROM bukti_pembayaran_bus WHERE pemesanan_id = ?")->execute([(int) $pemesanan_id]);
        $db->prepare("DELETE FROM pemesanan_bus WHERE id = ?")->execute([(int) $pemesanan_id]);

        $db->commit();

        return ['success' => true, 'message' => 'Pemesanan #' . $pemesanan_id . ' berhasil dihapus permanen.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return ['success' => false, 'message' => 'Gagal menghapus pemesanan: ' . $e->getMessage()];
    }
}

function updatePemesananBus($db, $pemesanan_id, array $input, $user_id, bool $is_staff = false)
{
    $pemesanan = getPemesananById($db, $pemesanan_id);
    if (!$pemesanan) {
        return ['success' => false, 'message' => 'Pemesanan tidak ditemukan.'];
    }

    if (!userCanAccessPemesanan($db, $user_id, $pemesanan, false)) {
        return ['success' => false, 'message' => 'Anda tidak memiliki akses ke pemesanan ini.'];
    }

    if (!pemesananCanEdit($pemesanan['status'])) {
        return ['success' => false, 'message' => 'Pemesanan dengan status "' . formatPemesananStatus($pemesanan['status']) . '" tidak dapat diubah.'];
    }

    $bus_id = (int) ($input['id_bus'] ?? $pemesanan['id_bus']);
    $tanggal_berangkat = date('Y-m-d', strtotime($input['tanggal_berangkat'] ?? $pemesanan['tanggal_berangkat']));
    $waktu_berangkat = validateInput($input['waktu_berangkat'] ?? $pemesanan['waktu_berangkat']);
    $kota_asal = validateInput($input['kota_asal'] ?? $pemesanan['kota_asal']);
    $kota_tujuan = validateInput($input['kota_tujuan'] ?? $pemesanan['kota_tujuan']);
    $nama_pemesan = validateInput($input['nama_pemesan'] ?? $pemesanan['nama_pemesan']);
    $kontak_pemesan = validateInput($input['kontak_pemesan'] ?? $pemesanan['kontak_pemesan']);
    $titik_jemput = validateInput($input['titik_jemput'] ?? ($pemesanan['titik_jemput'] ?? ''));
    $latitude = $input['latitude'] ?? $pemesanan['latitude'];
    $longitude = $input['longitude'] ?? $pemesanan['longitude'];
    $jumlah_penumpang = (int) ($input['jumlah_penumpang'] ?? $pemesanan['jumlah_penumpang']);
    $total_harga = preg_replace('/[^\d]/', '', (string) ($input['total_harga'] ?? $pemesanan['total_harga']));
    $total_harga = (float) $total_harga;
    $catatan = validateInput($input['catatan'] ?? ($pemesanan['catatan'] ?? ''));

    $stmt_bus = $db->prepare("SELECT * FROM bus WHERE id = ?");
    $stmt_bus->execute([$bus_id]);
    $bus = $stmt_bus->fetch(PDO::FETCH_ASSOC);
    if (!$bus) {
        return ['success' => false, 'message' => 'Bus tidak ditemukan.'];
    }

    if ($jumlah_penumpang < 1 || $jumlah_penumpang > (int) $bus['kapasitas']) {
        return ['success' => false, 'message' => 'Jumlah penumpang harus antara 1 dan ' . $bus['kapasitas'] . '.'];
    }

    if (!$is_staff) {
        $dateError = validateBookingDate($tanggal_berangkat);
        if ($dateError) {
            return ['success' => false, 'message' => $dateError];
        }
    }

    $validation = validateBusBooking($db, $bus_id, $tanggal_berangkat, $waktu_berangkat, $pemesanan_id);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => $validation['message']];
    }

    $stmt = $db->prepare("UPDATE pemesanan_bus SET
        id_bus = ?,
        tanggal_berangkat = ?,
        waktu_berangkat = ?,
        kota_asal = ?,
        kota_tujuan = ?,
        nama_pemesan = ?,
        kontak_pemesan = ?,
        titik_jemput = ?,
        latitude = ?,
        longitude = ?,
        jumlah_penumpang = ?,
        total_harga = ?,
        catatan = ?
        WHERE id = ?");

    $stmt->execute([
        $bus_id,
        $tanggal_berangkat,
        $waktu_berangkat,
        $kota_asal,
        $kota_tujuan,
        $nama_pemesan,
        $kontak_pemesan,
        $titik_jemput,
        $latitude !== '' ? $latitude : null,
        $longitude !== '' ? $longitude : null,
        $jumlah_penumpang,
        $total_harga,
        $catatan,
        $pemesanan_id,
    ]);

    return ['success' => true, 'message' => 'Pemesanan #' . $pemesanan_id . ' berhasil diperbarui.'];
}
