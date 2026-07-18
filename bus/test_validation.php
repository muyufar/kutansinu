<?php
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_helper.php';

echo "<h2>Test Validasi Tanggal</h2>";

// Test cases
$test_dates = [
    date('Y-m-d'), // Hari ini
    date('Y-m-d', strtotime('+1 day')), // Besok
    date('Y-m-d', strtotime('-1 day')), // Kemarin
    '2024-13-01', // Bulan tidak valid
    '2024-12-32', // Tanggal tidak valid
    'invalid-date', // Format tidak valid
    '', // Kosong
];

foreach ($test_dates as $date) {
    $result = validateBookingDate($date);
    $status = $result ? "❌ ERROR: $result" : "✅ VALID";
    echo "<p><strong>$date</strong>: $status</p>";
}

echo "<h2>Test getAvailableBusSchedule</h2>";
$available_buses = getAvailableBusSchedule($db, date('Y-m-d'));
echo "<p>Bus tersedia untuk hari ini: " . count($available_buses) . "</p>";

if (!empty($available_buses)) {
    echo "<ul>";
    foreach ($available_buses as $bus) {
        echo "<li>{$bus['nama_bus']} - Kapasitas: {$bus['sisa_kapasitas']}/{$bus['kapasitas']}</li>";
    }
    echo "</ul>";
}
