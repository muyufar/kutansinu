<?php

require_once __DIR__ . '/bus_report_helper.php';

function importBusReportSeed(PDO $db, array $seed, bool $replaceExisting = true): array
{
    ensureBusReportSchema($db);

    $stmtCompany = $db->prepare("SELECT id FROM perusahaan WHERE UPPER(nama) = ? LIMIT 1");
    $stmtCompany->execute([strtoupper($seed['perusahaan'])]);
    $idPerusahaan = (int) $stmtCompany->fetchColumn();

    if (!$idPerusahaan) {
        throw new RuntimeException('Perusahaan ' . $seed['perusahaan'] . ' tidak ditemukan.');
    }

    $bulan = $seed['bulan'];
    $tanggalAwal = date('Y-m-01', strtotime($bulan . '-01'));
    $tanggalAkhir = date('Y-m-t', strtotime($bulan . '-01'));

    $db->beginTransaction();

    try {
        $busMap = [];
        $stats = ['buses' => 0, 'trips' => 0, 'maintenance' => 0, 'updated_buses' => 0];

        foreach ($seed['buses'] as $busData) {
            $busId = upsertBusFromSeed($db, $idPerusahaan, $busData);
            $busMap[$busData['kode_armada']] = $busId;
            $stats['buses']++;
        }

        if ($replaceExisting) {
            clearBusReportPeriod($db, array_values($busMap), $tanggalAwal, $tanggalAkhir);
        }

        $insertTrip = $db->prepare("INSERT INTO bus_laporan_trip
            (id_bus, tanggal, nama_order, tujuan, harga_sewa, bbm, um, driver, co_driver, toll, parkir, pogah, crew)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($seed['trips'] as $kode => $trips) {
            if (!isset($busMap[$kode])) {
                continue;
            }
            foreach ($trips as $trip) {
                $insertTrip->execute([
                    $busMap[$kode],
                    $trip['tanggal'],
                    $trip['nama_order'],
                    $trip['tujuan'],
                    $trip['harga_sewa'],
                    $trip['bbm'],
                    $trip['um'],
                    $trip['driver'],
                    $trip['co_driver'],
                    $trip['toll'],
                    $trip['parkir'],
                    $trip['pogah'],
                    $trip['crew'] ?? null,
                ]);
                $stats['trips']++;
            }
        }

        $insertMaint = $db->prepare("INSERT INTO bus_laporan_maintenance (id_bus, tanggal, keterangan, biaya) VALUES (?, ?, ?, ?)");
        foreach ($seed['maintenance'] as $kode => $items) {
            if (!isset($busMap[$kode])) {
                continue;
            }
            foreach ($items as $item) {
                $insertMaint->execute([
                    $busMap[$kode],
                    $item['tanggal'],
                    $item['keterangan'],
                    $item['biaya'],
                ]);
                $stats['maintenance']++;
            }
        }

        $db->commit();

        return array_merge($stats, [
            'id_perusahaan' => $idPerusahaan,
            'bulan' => $bulan,
            'bus_map' => $busMap,
        ]);
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function upsertBusFromSeed(PDO $db, int $idPerusahaan, array $busData): int
{
    $stmt = $db->prepare("SELECT id FROM bus WHERE id_perusahaan = ? AND (kode_armada = ? OR nama_bus = ? OR nomor_polisi = ?) LIMIT 1");
    $stmt->execute([$idPerusahaan, $busData['kode_armada'], $busData['nama_bus'], $busData['nomor_polisi']]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $update = $db->prepare("UPDATE bus SET
            nama_bus = ?, nomor_polisi = ?, kode_armada = ?, lembar_saham = ?, grup_armada = ?,
            tipe = ?, kapasitas = ?, fasilitas = ?, id_perusahaan = ?, status = 'tersedia'
            WHERE id = ?");
        $update->execute([
            $busData['nama_bus'],
            $busData['nomor_polisi'],
            $busData['kode_armada'],
            $busData['lembar_saham'],
            $busData['grup_armada'],
            $busData['tipe'],
            $busData['kapasitas'],
            $busData['fasilitas'],
            $idPerusahaan,
            $existingId,
        ]);
        return (int) $existingId;
    }

    $insert = $db->prepare("INSERT INTO bus
        (nama_bus, nomor_polisi, kode_armada, lembar_saham, grup_armada, kapasitas, tipe, fasilitas, harga_per_km, status, id_perusahaan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'tersedia', ?)");
    $insert->execute([
        $busData['nama_bus'],
        $busData['nomor_polisi'],
        $busData['kode_armada'],
        $busData['lembar_saham'],
        $busData['grup_armada'],
        $busData['kapasitas'],
        $busData['tipe'],
        $busData['fasilitas'],
        $idPerusahaan,
    ]);

    return (int) $db->lastInsertId();
}

function clearBusReportPeriod(PDO $db, array $busIds, string $tanggalAwal, string $tanggalAkhir): void
{
    if (empty($busIds)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($busIds), '?'));
    $params = array_merge($busIds, [$tanggalAwal, $tanggalAkhir]);

    $stmtTrip = $db->prepare("DELETE FROM bus_laporan_trip WHERE id_bus IN ($placeholders) AND tanggal BETWEEN ? AND ?");
    $stmtTrip->execute($params);

    $stmtMaint = $db->prepare("DELETE FROM bus_laporan_maintenance WHERE id_bus IN ($placeholders) AND tanggal BETWEEN ? AND ?");
    $stmtMaint->execute($params);
}

function importJuni2026Document(PDO $db, bool $replaceExisting = true): array
{
    $seed = require __DIR__ . '/../seed/juni_2026_data.php';
    return importBusReportSeed($db, $seed, $replaceExisting);
}
