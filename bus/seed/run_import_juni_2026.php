<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/bus_report_importer.php';
require_once __DIR__ . '/../includes/bus_report_helper.php';

try {
    $result = importJuni2026Document($db, true);
    echo "Import OK: " . json_encode($result) . PHP_EOL;

    $report = fetchBusMonthlyReport($db, 3, '2026-06');
    $summary = buildPendapatanSummary($report, $db, 3, $report['tanggal_awal'], $report['tanggal_akhir']);
    echo "Grand total: " . number_format($summary['grand_total'], 0, ',', '.') . PHP_EOL;
    foreach ($report['buses'] as $b) {
        echo $b['nama_bus'] . " | pemasukan: " . number_format($b['pemasukan'], 0, ',', '.') . " | total: " . number_format($b['total'], 0, ',', '.') . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
