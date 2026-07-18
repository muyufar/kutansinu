<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/bus_report_helper.php';
require_once __DIR__ . '/../includes/bus_transaksi_sync.php';

ensureBusReportSchema($db);
$idPerusahaan = getNugoCompanyId($db);
$userId = 1;

$stats = syncBusReportPeriod($db, $idPerusahaan, '2026-06', $userId);
echo json_encode($stats, JSON_PRETTY_PRINT) . PHP_EOL;

$count = $db->query("SELECT COUNT(*) FROM transaksi WHERE tag LIKE '%bus%'")->fetchColumn();
echo "Transaksi bus tag count: $count\n";
