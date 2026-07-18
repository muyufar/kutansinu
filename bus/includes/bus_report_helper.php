<?php

function ensureBusReportSchema(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS bus_laporan_trip (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_bus INT NOT NULL,
        tanggal DATE NOT NULL,
        nama_order VARCHAR(255) DEFAULT NULL,
        tujuan TEXT DEFAULT NULL,
        harga_sewa DECIMAL(15,2) NOT NULL DEFAULT 0,
        bbm DECIMAL(15,2) NOT NULL DEFAULT 0,
        um DECIMAL(15,2) NOT NULL DEFAULT 0,
        driver DECIMAL(15,2) NOT NULL DEFAULT 0,
        co_driver DECIMAL(15,2) NOT NULL DEFAULT 0,
        toll DECIMAL(15,2) NOT NULL DEFAULT 0,
        parkir DECIMAL(15,2) NOT NULL DEFAULT 0,
        pogah DECIMAL(15,2) NOT NULL DEFAULT 0,
        crew VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_bus_tanggal (id_bus, tanggal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS bus_laporan_maintenance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_bus INT NOT NULL,
        tanggal DATE NOT NULL,
        keterangan TEXT DEFAULT NULL,
        biaya DECIMAL(15,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_bus_tanggal (id_bus, tanggal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = $db->query("SHOW COLUMNS FROM bus LIKE 'kode_armada'")->fetch();
    if (!$columns) {
        $db->exec("ALTER TABLE bus ADD COLUMN kode_armada VARCHAR(20) DEFAULT NULL AFTER nomor_polisi");
    }

    $columns = $db->query("SHOW COLUMNS FROM bus LIKE 'lembar_saham'")->fetch();
    if (!$columns) {
        $db->exec("ALTER TABLE bus ADD COLUMN lembar_saham INT NOT NULL DEFAULT 0 AFTER kode_armada");
    }

    $columns = $db->query("SHOW COLUMNS FROM bus LIKE 'grup_armada'")->fetch();
    if (!$columns) {
        $db->exec("ALTER TABLE bus ADD COLUMN grup_armada ENUM('1-5','6-7') DEFAULT '1-5' AFTER lembar_saham");
    }

    $columns = $db->query("SHOW COLUMNS FROM bus_laporan_trip LIKE 'id_pemesanan'")->fetch();
    if (!$columns) {
        $db->exec("ALTER TABLE bus_laporan_trip ADD COLUMN id_pemesanan INT DEFAULT NULL AFTER id_bus");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS bus_transaksi_sync (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_module ENUM('bus_laporan_trip','bus_laporan_maintenance') NOT NULL,
        source_id INT NOT NULL,
        transaksi_id INT NOT NULL,
        line_type ENUM('sewa','operasional','maintenance') NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_source_line (source_module, source_id, line_type),
        KEY idx_transaksi (transaksi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function parseBusNumber(string $namaBus): int
{
    if (preg_match('/(\d+)/', $namaBus, $matches)) {
        return (int) $matches[1];
    }

    return 0;
}

function getBusFeeScheme(int $busNumber): string
{
    return $busNumber >= 6 ? '5_5_15' : '20_management';
}

function getTripOperationalCost(array $trip): float
{
    return (float) $trip['bbm']
        + (float) $trip['um']
        + (float) $trip['driver']
        + (float) $trip['co_driver']
        + (float) $trip['toll']
        + (float) $trip['parkir']
        + (float) $trip['pogah'];
}

function calculateBusMonthlyMetrics(float $pemasukan, float $pengeluaran, float $maintenance, int $busNumber): array
{
    $total = $pemasukan - $pengeluaran - $maintenance;
    $scheme = getBusFeeScheme($busNumber);

    $metrics = [
        'pemasukan' => $pemasukan,
        'pengeluaran' => $pengeluaran,
        'maintenance' => $maintenance,
        'total' => $total,
        'kas_pc' => 0.0,
        'kas_pt' => 0.0,
        'fee_management' => 0.0,
        'saldo_pendapatan' => 0.0,
        'scheme' => $scheme,
    ];

    if ($scheme === '20_management') {
        $metrics['fee_management'] = $total * 0.20;
        $metrics['saldo_pendapatan'] = $total - $metrics['fee_management'];
        $metrics['kas_pt_cadangan'] = $total * 0.05;
    } else {
        $metrics['kas_pc'] = $total * 0.05;
        $metrics['kas_pt'] = $total * 0.05;
        $metrics['fee_management'] = $total * 0.15;
        $metrics['saldo_pendapatan'] = $total - $metrics['kas_pc'] - $metrics['kas_pt'] - $metrics['fee_management'];
    }

    return $metrics;
}

function applyBagiHasilPcnu(array $busRows): array
{
    $groups = [
        '1-5' => [],
        '6-7' => [],
    ];

    foreach ($busRows as $index => $row) {
        $groups[$row['grup']][] = $index;
    }

    foreach ($groups as $grup => $indexes) {
        if (empty($indexes)) {
            continue;
        }

        if ($grup === '1-5') {
            $totalSaldo = 0.0;
            $totalSaham = 0;

            foreach ($indexes as $index) {
                $totalSaldo += $busRows[$index]['saldo_pendapatan'];
                $totalSaham += (int) $busRows[$index]['lembar_saham'];
            }

            foreach ($indexes as $index) {
                $lembarSaham = (int) $busRows[$index]['lembar_saham'];
                if ($totalSaham > 0 && $lembarSaham > 0) {
                    $bagiHasil = $totalSaldo * ($lembarSaham / $totalSaham);
                } else {
                    $bagiHasil = $busRows[$index]['saldo_pendapatan'];
                }

                $busRows[$index]['bagi_hasil_saham'] = $bagiHasil;
                $busRows[$index]['nu_5pct'] = $bagiHasil * 0.05;
                $busRows[$index]['pendapatan_pcnu'] = $bagiHasil * 0.95;
            }
        } else {
            $totalBeforeFee = 0.0;
            foreach ($indexes as $index) {
                $totalBeforeFee += $busRows[$index]['total'];
            }

            $groupPcnu = $totalBeforeFee * 0.05;

            foreach ($indexes as $index) {
                $busRows[$index]['bagi_hasil_saham'] = $busRows[$index]['saldo_pendapatan'];
                $busRows[$index]['nu_5pct'] = $busRows[$index]['kas_pc'];
                $busRows[$index]['pendapatan_pcnu'] = $busRows[$index]['saldo_pendapatan'];
            }

            $busRows[$indexes[0]]['group_pcnu_total'] = $groupPcnu;
        }
    }

    return $busRows;
}

function fetchBusMonthlyReport(PDO $db, int $idPerusahaan, string $bulan): array
{
    ensureBusReportSchema($db);

    $tanggalAwal = date('Y-m-01', strtotime($bulan . '-01'));
    $tanggalAkhir = date('Y-m-t', strtotime($bulan . '-01'));

    $stmt = $db->prepare("SELECT * FROM bus WHERE id_perusahaan = ? ORDER BY nama_bus ASC");
    $stmt->execute([$idPerusahaan]);
    $buses = $stmt->fetchAll();

    $busRows = [];

    foreach ($buses as $bus) {
        $busNumber = parseBusNumber($bus['nama_bus']);
        $grup = $busNumber >= 6 ? '6-7' : '1-5';

        $stmtTrip = $db->prepare("SELECT * FROM bus_laporan_trip WHERE id_bus = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
        $stmtTrip->execute([$bus['id'], $tanggalAwal, $tanggalAkhir]);
        $trips = $stmtTrip->fetchAll();

        $stmtMaint = $db->prepare("SELECT * FROM bus_laporan_maintenance WHERE id_bus = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal ASC, id ASC");
        $stmtMaint->execute([$bus['id'], $tanggalAwal, $tanggalAkhir]);
        $maintenanceRows = $stmtMaint->fetchAll();

        $pemasukan = 0.0;
        $pengeluaran = 0.0;
        foreach ($trips as $trip) {
            $pemasukan += (float) $trip['harga_sewa'];
            $pengeluaran += getTripOperationalCost($trip);
        }

        $maintenance = 0.0;
        foreach ($maintenanceRows as $item) {
            $maintenance += (float) $item['biaya'];
        }

        $metrics = calculateBusMonthlyMetrics($pemasukan, $pengeluaran, $maintenance, $busNumber);

        $busRows[] = array_merge([
            'id' => (int) $bus['id'],
            'nama_bus' => $bus['nama_bus'],
            'kode_armada' => $bus['kode_armada'] ?: ('NG' . str_pad((string) $busNumber, 3, '0', STR_PAD_LEFT)),
            'nomor_polisi' => $bus['nomor_polisi'],
            'lembar_saham' => (int) ($bus['lembar_saham'] ?? 0),
            'bus_number' => $busNumber,
            'grup' => $grup,
            'trips' => $trips,
            'maintenance_rows' => $maintenanceRows,
        ], $metrics);
    }

    $busRows = applyBagiHasilPcnu($busRows);

    return [
        'bulan' => $bulan,
        'tanggal_awal' => $tanggalAwal,
        'tanggal_akhir' => $tanggalAkhir,
        'buses' => $busRows,
    ];
}

function buildPendapatanSummary(array $report, PDO $db, int $idPerusahaan, string $tanggalAwal, string $tanggalAkhir): array
{
    $group15 = array_values(array_filter($report['buses'], fn($b) => $b['grup'] === '1-5'));
    $group67 = array_values(array_filter($report['buses'], fn($b) => $b['grup'] === '6-7'));

    $kasPt15 = array_sum(array_map(fn($b) => $b['kas_pt_cadangan'] ?? ($b['total'] * 0.05), $group15));
    $kasPt67 = array_sum(array_map(fn($b) => $b['kas_pt'], $group67));
    $kasPcnu15 = array_sum(array_map(fn($b) => $b['nu_5pct'], $group15));
    $kasPcnu67 = array_sum(array_map(fn($b) => $b['total'], $group67)) * 0.05;

    $bebanAdmin = fetchBebanAdminBank($db, $idPerusahaan, $tanggalAwal, $tanggalAkhir);
    $totalKasPt = $kasPt15 + $kasPt67 - $bebanAdmin;

    $pendapatanPerBus = [];
    foreach ($report['buses'] as $bus) {
        if (($bus['pendapatan_pcnu'] ?? 0) > 0) {
            $pendapatanPerBus[] = [
                'nama_bus' => $bus['nama_bus'],
                'jumlah' => $bus['pendapatan_pcnu'],
            ];
        }
    }

    $grandTotal = $totalKasPt + $kasPcnu15 + $kasPcnu67 + array_sum(array_column($pendapatanPerBus, 'jumlah'));

    return [
        'kas_pt_nugo_1_5' => $kasPt15,
        'kas_pt_nugo_6_7' => $kasPt67,
        'beban_admin_bank' => $bebanAdmin,
        'total_kas_pt' => $totalKasPt,
        'kas_pcnu_1_5' => $kasPcnu15,
        'kas_pcnu_6_7' => $kasPcnu67,
        'pendapatan_bus_pcnu' => $pendapatanPerBus,
        'grand_total' => $grandTotal,
    ];
}

function fetchBebanAdminBank(PDO $db, int $idPerusahaan, string $tanggalAwal, string $tanggalAkhir): float
{
    $companyIds = [$idPerusahaan];
    $stmtBumnu = $db->prepare("SELECT id FROM perusahaan WHERE UPPER(nama) = 'KAS BUMNU' LIMIT 1");
    $stmtBumnu->execute();
    $bumnuId = $stmtBumnu->fetchColumn();
    if ($bumnuId) {
        $companyIds[] = (int) $bumnuId;
    }

    $companyIds = array_unique($companyIds);
    $placeholders = implode(',', array_fill(0, count($companyIds), '?'));

    $sql = "SELECT COALESCE(SUM(t.total), 0) AS total
        FROM transaksi t
        LEFT JOIN akun a ON t.id_akun_debit = a.id
        WHERE t.id_perusahaan IN ($placeholders)
          AND t.tanggal BETWEEN ? AND ?
          AND t.jenis = 'pengeluaran'
          AND (
            LOWER(t.keterangan) LIKE '%beban administrasi bank%'
            OR LOWER(t.keterangan) LIKE '%administrasi bank%'
            OR LOWER(a.nama_akun) LIKE '%beban bank%'
          )";

    $params = array_merge($companyIds, [$tanggalAwal, $tanggalAkhir]);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (float) $stmt->fetchColumn();
}

function formatReportCurrency(float $amount): string
{
    return number_format($amount, 2, ',', '.');
}

function requireBusReportAccess(PDO $db, int $userId): void
{
    require_once __DIR__ . '/bus_helper.php';
    if (!checkNugrosirAccess($db, $userId)) {
        $_SESSION['error'] = 'Anda tidak memiliki hak akses untuk laporan bus.';
        header('Location: /index.php');
        exit();
    }
}

function getNugoCompanyId(PDO $db): ?int
{
    $stmt = $db->prepare("SELECT id FROM perusahaan WHERE UPPER(nama) = 'NUGO' LIMIT 1");
    $stmt->execute();
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function getMonthNameId(string $bulan): string
{
    $names = [
        '01' => 'JANUARI', '02' => 'FEBRUARI', '03' => 'MARET', '04' => 'APRIL',
        '05' => 'MEI', '06' => 'JUNI', '07' => 'JULI', '08' => 'AGUSTUS',
        '09' => 'SEPTEMBER', '10' => 'OKTOBER', '11' => 'NOVEMBER', '12' => 'DESEMBER',
    ];

    $month = date('m', strtotime($bulan . '-01'));
    return $names[$month] ?? strtoupper($bulan);
}
