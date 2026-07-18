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

function fetchBusCalendarEvents(PDO $db, ?int $idPerusahaan): array
{
    ensureBusReportSchema($db);

    $events = [];
    $stats = ['trip' => 0, 'maintenance' => 0, 'pemesanan' => 0];
    $linkedPemesananIds = [];
    $companySql = $idPerusahaan ? ' AND b.id_perusahaan = ?' : '';
    $companyParams = $idPerusahaan ? [$idPerusahaan] : [];

    $stmtTrips = $db->prepare("SELECT t.*, b.nama_bus, b.tipe, b.kode_armada, b.id AS bus_id
        FROM bus_laporan_trip t
        JOIN bus b ON t.id_bus = b.id
        WHERE 1=1 {$companySql}
        ORDER BY t.tanggal ASC, t.id ASC");
    $stmtTrips->execute($companyParams);
    $trips = $stmtTrips->fetchAll();

    foreach ($trips as $trip) {
        if (!empty($trip['id_pemesanan'])) {
            $linkedPemesananIds[] = (int) $trip['id_pemesanan'];
        }

        $busLabel = $trip['kode_armada'] ?: $trip['nama_bus'];
        $orderLabel = $trip['nama_order'] ?: 'Trip operasional';
        $orderShort = function_exists('mb_strimwidth')
            ? mb_strimwidth($orderLabel, 0, 28, '…')
            : (strlen($orderLabel) > 28 ? substr($orderLabel, 0, 27) . '…' : $orderLabel);
        $isPast = strtotime($trip['tanggal']) < strtotime(date('Y-m-d'));
        $bulan = date('Y-m', strtotime($trip['tanggal']));

        $events[] = [
            'id' => 'trip-' . $trip['id'],
            'title' => $busLabel . ' · ' . $orderShort,
            'start' => $trip['tanggal'],
            'allDay' => true,
            'backgroundColor' => $isPast ? '#64748b' : '#059669',
            'borderColor' => $isPast ? '#475569' : '#047857',
            'extendedProps' => [
                'type' => 'trip',
                'typeLabel' => 'Trip Operasional',
                'bus' => $trip['nama_bus'],
                'tipe' => $trip['tipe'],
                'kode' => $trip['kode_armada'],
                'order' => $orderLabel,
                'tujuan' => $trip['tujuan'] ?? '-',
                'tanggal' => $trip['tanggal'],
                'sewa' => (float) $trip['harga_sewa'],
                'ops' => getTripOperationalCost($trip),
                'crew' => $trip['crew'] ?? '-',
                'isPast' => $isPast,
                'detailUrl' => '/bus/operasional_bus.php?bulan=' . $bulan,
                'armadaUrl' => '/bus/laporan_armada.php?bus_id=' . $trip['bus_id'] . '&bulan=' . $bulan,
            ],
        ];
        $stats['trip']++;
    }

    $stmtMaint = $db->prepare("SELECT m.*, b.nama_bus, b.tipe, b.kode_armada, b.id AS bus_id
        FROM bus_laporan_maintenance m
        JOIN bus b ON m.id_bus = b.id
        WHERE 1=1 {$companySql}
        ORDER BY m.tanggal ASC, m.id ASC");
    $stmtMaint->execute($companyParams);
    $maintRows = $stmtMaint->fetchAll();

    foreach ($maintRows as $item) {
        $busLabel = $item['kode_armada'] ?: $item['nama_bus'];
        $ket = $item['keterangan'] ?: 'Maintenance';
        $isPast = strtotime($item['tanggal']) < strtotime(date('Y-m-d'));
        $bulan = date('Y-m', strtotime($item['tanggal']));

        $ketShort = function_exists('mb_strimwidth')
            ? mb_strimwidth($ket, 0, 22, '…')
            : (strlen($ket) > 22 ? substr($ket, 0, 21) . '…' : $ket);

        $events[] = [
            'id' => 'maint-' . $item['id'],
            'title' => '🔧 ' . $busLabel . ' · ' . $ketShort,
            'start' => $item['tanggal'],
            'allDay' => true,
            'backgroundColor' => $isPast ? '#78716c' : '#d97706',
            'borderColor' => $isPast ? '#57534e' : '#b45309',
            'extendedProps' => [
                'type' => 'maintenance',
                'typeLabel' => 'Maintenance',
                'bus' => $item['nama_bus'],
                'tipe' => $item['tipe'],
                'kode' => $item['kode_armada'],
                'order' => $ket,
                'tujuan' => '-',
                'tanggal' => $item['tanggal'],
                'sewa' => 0,
                'ops' => (float) $item['biaya'],
                'crew' => '-',
                'isPast' => $isPast,
                'detailUrl' => '/bus/operasional_bus.php?bulan=' . $bulan,
                'armadaUrl' => '/bus/laporan_armada.php?bus_id=' . $item['bus_id'] . '&bulan=' . $bulan,
            ],
        ];
        $stats['maintenance']++;
    }

    $pemesananSql = "SELECT pb.*, b.nama_bus, b.tipe, b.kode_armada, b.id AS bus_id
        FROM pemesanan_bus pb
        JOIN bus b ON pb.id_bus = b.id
        WHERE pb.status NOT IN ('dibatalkan', 'ditolak') {$companySql}
        ORDER BY pb.tanggal_berangkat ASC, pb.waktu_berangkat ASC";
    $stmtPemesanan = $db->prepare($pemesananSql);
    $stmtPemesanan->execute($companyParams);
    $pemesananRows = $stmtPemesanan->fetchAll();

    $statusColors = [
        'pending' => ['#6366f1', '#4f46e5'],
        'dibayar_dp' => ['#2563eb', '#1d4ed8'],
        'dibayar' => ['#0ea5e9', '#0284c7'],
        'selesai' => ['#64748b', '#475569'],
    ];

    foreach ($pemesananRows as $order) {
        if (in_array((int) $order['id'], $linkedPemesananIds, true)) {
            continue;
        }

        $datetime = $order['tanggal_berangkat'] . 'T' . ($order['waktu_berangkat'] ?: '08:00:00');
        $isPast = strtotime($datetime) < time();
        $colors = $statusColors[$order['status']] ?? ['#6366f1', '#4f46e5'];
        if ($isPast) {
            $colors = ['#64748b', '#475569'];
        }

        $events[] = [
            'id' => 'order-' . $order['id'],
            'title' => ($order['kode_armada'] ?: $order['nama_bus']) . ' · ' . ($order['nama_pemesan'] ?: 'Pemesanan'),
            'start' => $datetime,
            'backgroundColor' => $colors[0],
            'borderColor' => $colors[1],
            'extendedProps' => [
                'type' => 'pemesanan',
                'typeLabel' => 'Pemesanan Online',
                'bus' => $order['nama_bus'],
                'tipe' => $order['tipe'],
                'kode' => $order['kode_armada'],
                'order' => $order['nama_pemesan'] ?: ('Pemesanan #' . $order['id']),
                'tujuan' => $order['kota_asal'] . ' → ' . $order['kota_tujuan'],
                'tanggal' => $order['tanggal_berangkat'],
                'waktu' => substr($order['waktu_berangkat'], 0, 5),
                'sewa' => (float) $order['total_harga'],
                'ops' => 0,
                'status' => $order['status'],
                'isPast' => $isPast,
                'detailUrl' => '/bus/verifikasi_pesanan.php',
                'armadaUrl' => '/bus/jadwal.php?id=' . $order['bus_id'],
            ],
        ];
        $stats['pemesanan']++;
    }

    return [
        'events' => $events,
        'stats' => $stats,
        'total' => count($events),
    ];
}
