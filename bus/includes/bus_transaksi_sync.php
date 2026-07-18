<?php

require_once __DIR__ . '/bus_report_helper.php';

function resolveBusTransaksiAccounts(PDO $db, int $idPerusahaan): array
{
    $find = function (string $sql, array $params) use ($db) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : null;
    };

    $kas = $find(
        "SELECT id FROM akun WHERE id_perusahaan = ? AND (LOWER(nama_akun) LIKE '%kas%' OR kode_akun LIKE '1-1%') ORDER BY id LIMIT 1",
        [$idPerusahaan]
    );
    $pendapatan = $find(
        "SELECT id FROM akun WHERE id_perusahaan = ? AND (kode_akun LIKE '%4-40000%' OR kategori = 'pendapatan') ORDER BY kode_akun LIMIT 1",
        [$idPerusahaan]
    );
    $bebanOps = $find(
        "SELECT id FROM akun WHERE id_perusahaan = ? AND (kode_akun LIKE '%6-60202%' OR LOWER(nama_akun) LIKE '%bensin%toll%parkir%') ORDER BY id LIMIT 1",
        [$idPerusahaan]
    );
    $bebanMaint = $find(
        "SELECT id FROM akun WHERE id_perusahaan = ? AND (kode_akun LIKE '%6-60203%' OR LOWER(nama_akun) LIKE '%perbaikan%perawatan%') ORDER BY id LIMIT 1",
        [$idPerusahaan]
    );

    if (!$bebanOps) {
        $bebanOps = $find(
            "SELECT id FROM akun WHERE id_perusahaan = ? AND kategori = 'beban' ORDER BY kode_akun LIMIT 1",
            [$idPerusahaan]
        );
    }

    return compact('kas', 'pendapatan', 'bebanOps', 'bebanMaint');
}

function insertBusTransaksiRow(PDO $db, array $data): int
{
    $stmt = $db->prepare("INSERT INTO transaksi
        (tanggal, id_akun_debit, id_akun_kredit, keterangan, jenis, jumlah, pajak, bunga, total, file_lampiran, penanggung_jawab, tag, created_by, id_perusahaan)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['tanggal'],
        $data['id_akun_debit'],
        $data['id_akun_kredit'],
        $data['keterangan'],
        $data['jenis'],
        $data['jumlah'],
        $data['jumlah'],
        $data['file_lampiran'] ?? '',
        $data['penanggung_jawab'] ?? 'Admin Bus',
        $data['tag'],
        $data['created_by'],
        $data['id_perusahaan'],
    ]);

    return (int) $db->lastInsertId();
}

function removeSourceTransaksiSync(PDO $db, string $module, int $sourceId): void
{
    $stmt = $db->prepare("SELECT transaksi_id FROM bus_transaksi_sync WHERE source_module = ? AND source_id = ?");
    $stmt->execute([$module, $sourceId]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM transaksi WHERE id IN ($placeholders)")->execute($ids);
        $db->prepare("DELETE FROM bus_transaksi_sync WHERE source_module = ? AND source_id = ?")->execute([$module, $sourceId]);
    }
}

function linkTransaksiSync(PDO $db, string $module, int $sourceId, int $transaksiId, string $lineType): void
{
    $stmt = $db->prepare("INSERT INTO bus_transaksi_sync (source_module, source_id, transaksi_id, line_type) VALUES (?, ?, ?, ?)");
    $stmt->execute([$module, $sourceId, $transaksiId, $lineType]);
}

function fetchTripForSync(PDO $db, int $tripId): ?array
{
    $stmt = $db->prepare("SELECT t.*, b.nama_bus, b.kode_armada, b.id_perusahaan
        FROM bus_laporan_trip t
        JOIN bus b ON t.id_bus = b.id
        WHERE t.id = ?");
    $stmt->execute([$tripId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchMaintenanceForSync(PDO $db, int $maintId): ?array
{
    $stmt = $db->prepare("SELECT m.*, b.nama_bus, b.kode_armada, b.id_perusahaan
        FROM bus_laporan_maintenance m
        JOIN bus b ON m.id_bus = b.id
        WHERE m.id = ?");
    $stmt->execute([$maintId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function buildBusTag(array $busRow, string $suffix = ''): string
{
    $kode = $busRow['kode_armada'] ?: str_replace(' ', '_', strtolower($busRow['nama_bus']));
    $tag = 'bus,operasional,' . $kode;
    return $suffix ? $tag . ',' . $suffix : $tag;
}

function syncTripToTransaksi(PDO $db, int $tripId, int $userId): array
{
    ensureBusReportSchema($db);

    $trip = fetchTripForSync($db, $tripId);
    if (!$trip) {
        throw new RuntimeException('Trip tidak ditemukan.');
    }

    $accounts = resolveBusTransaksiAccounts($db, (int) $trip['id_perusahaan']);
    if (!$accounts['kas'] || !$accounts['pendapatan']) {
        throw new RuntimeException('Akun Kas/Pendapatan perusahaan belum dikonfigurasi.');
    }

    removeSourceTransaksiSync($db, 'bus_laporan_trip', $tripId);

    $created = 0;
    $tag = buildBusTag($trip);
    $busLabel = $trip['kode_armada'] ? $trip['kode_armada'] . ' - ' . $trip['nama_bus'] : $trip['nama_bus'];
    $orderLabel = $trip['nama_order'] ?: 'Trip #' . $tripId;

    $sewa = (float) $trip['harga_sewa'];
    if ($sewa > 0) {
        $transaksiId = insertBusTransaksiRow($db, [
            'tanggal' => $trip['tanggal'],
            'id_akun_debit' => $accounts['kas'],
            'id_akun_kredit' => $accounts['pendapatan'],
            'keterangan' => "Pendapatan sewa bus {$busLabel} - {$orderLabel}",
            'jenis' => 'pemasukan',
            'jumlah' => $sewa,
            'tag' => $tag . ',sewa',
            'created_by' => $userId,
            'id_perusahaan' => (int) $trip['id_perusahaan'],
        ]);
        linkTransaksiSync($db, 'bus_laporan_trip', $tripId, $transaksiId, 'sewa');
        $created++;
    }

    $ops = getTripOperationalCost($trip);
    if ($ops > 0 && $accounts['bebanOps']) {
        $transaksiId = insertBusTransaksiRow($db, [
            'tanggal' => $trip['tanggal'],
            'id_akun_debit' => $accounts['bebanOps'],
            'id_akun_kredit' => $accounts['kas'],
            'keterangan' => "Beban operasional bus {$busLabel} - {$orderLabel} (BBM/UM/Driver/Toll/Parkir/P.Ogah)",
            'jenis' => 'pengeluaran',
            'jumlah' => $ops,
            'tag' => $tag . ',pengeluaran',
            'created_by' => $userId,
            'id_perusahaan' => (int) $trip['id_perusahaan'],
        ]);
        linkTransaksiSync($db, 'bus_laporan_trip', $tripId, $transaksiId, 'operasional');
        $created++;
    }

    return ['created' => $created, 'trip_id' => $tripId];
}

function syncMaintenanceToTransaksi(PDO $db, int $maintId, int $userId): array
{
    ensureBusReportSchema($db);

    $item = fetchMaintenanceForSync($db, $maintId);
    if (!$item) {
        throw new RuntimeException('Data maintenance tidak ditemukan.');
    }

    $accounts = resolveBusTransaksiAccounts($db, (int) $item['id_perusahaan']);
    if (!$accounts['kas']) {
        throw new RuntimeException('Akun Kas perusahaan belum dikonfigurasi.');
    }

    $beban = $accounts['bebanMaint'] ?: $accounts['bebanOps'];
    if (!$beban) {
        throw new RuntimeException('Akun Beban maintenance/operasional belum dikonfigurasi.');
    }

    removeSourceTransaksiSync($db, 'bus_laporan_maintenance', $maintId);

    $biaya = (float) $item['biaya'];
    if ($biaya <= 0) {
        return ['created' => 0, 'maintenance_id' => $maintId];
    }

    $tag = buildBusTag($item, 'maintenance');
    $busLabel = $item['kode_armada'] ? $item['kode_armada'] . ' - ' . $item['nama_bus'] : $item['nama_bus'];
    $ket = trim($item['keterangan'] ?? '') ?: 'Maintenance';

    $transaksiId = insertBusTransaksiRow($db, [
        'tanggal' => $item['tanggal'],
        'id_akun_debit' => $beban,
        'id_akun_kredit' => $accounts['kas'],
        'keterangan' => "Maintenance bus {$busLabel} - {$ket}",
        'jenis' => 'pengeluaran',
        'jumlah' => $biaya,
        'tag' => $tag,
        'created_by' => $userId,
        'id_perusahaan' => (int) $item['id_perusahaan'],
    ]);
    linkTransaksiSync($db, 'bus_laporan_maintenance', $maintId, $transaksiId, 'maintenance');

    return ['created' => 1, 'maintenance_id' => $maintId];
}

function unsyncTripFromTransaksi(PDO $db, int $tripId): void
{
    removeSourceTransaksiSync($db, 'bus_laporan_trip', $tripId);
}

function unsyncMaintenanceFromTransaksi(PDO $db, int $maintId): void
{
    removeSourceTransaksiSync($db, 'bus_laporan_maintenance', $maintId);
}

function syncBusReportPeriod(PDO $db, int $idPerusahaan, string $bulan, int $userId): array
{
    ensureBusReportSchema($db);

    $tanggalAwal = date('Y-m-01', strtotime($bulan . '-01'));
    $tanggalAkhir = date('Y-m-t', strtotime($bulan . '-01'));

    $stmtTrips = $db->prepare("SELECT t.id FROM bus_laporan_trip t JOIN bus b ON t.id_bus = b.id WHERE b.id_perusahaan = ? AND t.tanggal BETWEEN ? AND ?");
    $stmtTrips->execute([$idPerusahaan, $tanggalAwal, $tanggalAkhir]);
    $tripIds = $stmtTrips->fetchAll(PDO::FETCH_COLUMN);

    $stmtMaint = $db->prepare("SELECT m.id FROM bus_laporan_maintenance m JOIN bus b ON m.id_bus = b.id WHERE b.id_perusahaan = ? AND m.tanggal BETWEEN ? AND ?");
    $stmtMaint->execute([$idPerusahaan, $tanggalAwal, $tanggalAkhir]);
    $maintIds = $stmtMaint->fetchAll(PDO::FETCH_COLUMN);

    $stats = ['trips' => 0, 'maintenance' => 0, 'transaksi' => 0, 'errors' => []];

    foreach ($tripIds as $tripId) {
        try {
            $result = syncTripToTransaksi($db, (int) $tripId, $userId);
            $stats['trips']++;
            $stats['transaksi'] += $result['created'];
        } catch (Throwable $e) {
            $stats['errors'][] = 'Trip #' . $tripId . ': ' . $e->getMessage();
        }
    }

    foreach ($maintIds as $maintId) {
        try {
            $result = syncMaintenanceToTransaksi($db, (int) $maintId, $userId);
            $stats['maintenance']++;
            $stats['transaksi'] += $result['created'];
        } catch (Throwable $e) {
            $stats['errors'][] = 'Maintenance #' . $maintId . ': ' . $e->getMessage();
        }
    }

    return $stats;
}

function getSourceSyncStatus(PDO $db, string $module, int $sourceId): bool
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM bus_transaksi_sync WHERE source_module = ? AND source_id = ?");
    $stmt->execute([$module, $sourceId]);
    return (int) $stmt->fetchColumn() > 0;
}

function createTripFromPemesanan(PDO $db, int $pemesananId): ?int
{
    ensureBusReportSchema($db);

    $stmt = $db->prepare("SELECT p.*, b.nama_bus FROM pemesanan_bus p JOIN bus b ON p.id_bus = b.id WHERE p.id = ?");
    $stmt->execute([$pemesananId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    $check = $db->prepare("SELECT id FROM bus_laporan_trip WHERE id_pemesanan = ? LIMIT 1");
    $check->execute([$pemesananId]);
    $existing = $check->fetchColumn();
    if ($existing) {
        return (int) $existing;
    }

    $stmt = $db->prepare("INSERT INTO bus_laporan_trip
        (id_bus, id_pemesanan, tanggal, nama_order, tujuan, harga_sewa, bbm, um, driver, co_driver, toll, parkir, pogah, crew)
        VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, 0, 0, NULL)");
    $stmt->execute([
        (int) $order['id_bus'],
        $pemesananId,
        $order['tanggal_berangkat'],
        $order['nama_pemesan'] ?: ('Pemesanan #' . $pemesananId),
        trim($order['kota_asal'] . ' → ' . $order['kota_tujuan']),
        (float) $order['total_harga'],
    ]);

    return (int) $db->lastInsertId();
}

function fetchOperasionalSummary(PDO $db, int $idPerusahaan, string $bulan): array
{
    $tanggalAwal = date('Y-m-01', strtotime($bulan . '-01'));
    $tanggalAkhir = date('Y-m-t', strtotime($bulan . '-01'));

    $stmt = $db->prepare("SELECT
            COALESCE(SUM(t.harga_sewa), 0) AS pemasukan,
            COALESCE(SUM(t.bbm + t.um + t.driver + t.co_driver + t.toll + t.parkir + t.pogah), 0) AS pengeluaran_ops
        FROM bus_laporan_trip t
        JOIN bus b ON t.id_bus = b.id
        WHERE b.id_perusahaan = ? AND t.tanggal BETWEEN ? AND ?");
    $stmt->execute([$idPerusahaan, $tanggalAwal, $tanggalAkhir]);
    $tripSum = $stmt->fetch();

    $stmtMaint = $db->prepare("SELECT COALESCE(SUM(m.biaya), 0) AS maintenance
        FROM bus_laporan_maintenance m
        JOIN bus b ON m.id_bus = b.id
        WHERE b.id_perusahaan = ? AND m.tanggal BETWEEN ? AND ?");
    $stmtMaint->execute([$idPerusahaan, $tanggalAwal, $tanggalAkhir]);
    $maintSum = (float) $stmtMaint->fetchColumn();

    $pemasukan = (float) $tripSum['pemasukan'];
    $pengeluaranOps = (float) $tripSum['pengeluaran_ops'];
    $maintenance = $maintSum;

    return [
        'pemasukan' => $pemasukan,
        'pengeluaran_ops' => $pengeluaranOps,
        'maintenance' => $maintenance,
        'pengeluaran_total' => $pengeluaranOps + $maintenance,
        'laba_kotor' => $pemasukan - $pengeluaranOps - $maintenance,
    ];
}
