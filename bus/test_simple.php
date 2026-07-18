<?php
require_once '../config/database.php';
require_once '../config/functions.php';
require_once 'includes/bus_helper.php';

echo "<h2>Test Validasi Bus Booking</h2>";

// Test 1: Validasi tanggal
echo "<h3>1. Test Validasi Tanggal</h3>";
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

// Test 2: Test getAvailableBusSchedule
echo "<h3>2. Test getAvailableBusSchedule</h3>";
try {
    $available_buses = getAvailableBusSchedule($db, date('Y-m-d'));
    echo "<p>Bus tersedia untuk hari ini: " . count($available_buses) . "</p>";
    
    if (!empty($available_buses)) {
        echo "<ul>";
        foreach ($available_buses as $bus) {
            echo "<li>{$bus['nama_bus']} - Kapasitas: {$bus['kapasitas']}</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test 3: Test checkBusAvailability
echo "<h3>3. Test checkBusAvailability</h3>";
try {
    $bus_id = 1; // Asumsikan ada bus dengan ID 1
    $tanggal = date('Y-m-d', strtotime('+1 day'));
    
    $is_available = checkBusAvailability($db, $bus_id, $tanggal);
    echo "<p>Bus ID $bus_id untuk tanggal $tanggal: " . ($is_available ? "Tidak tersedia" : "Tersedia") . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test 4: Test validateBusBooking - Aturan Satu Bus Per Tanggal
echo "<h3>4. Test validateBusBooking - Aturan Satu Bus Per Tanggal</h3>";
try {
    $bus_id = 1;
    $tanggal = date('Y-m-d', strtotime('+2 days'));
    $waktu = '08:00:00';
    
    echo "<p>Testing validasi untuk Bus ID $bus_id pada tanggal $tanggal pukul $waktu</p>";
    
    $result = validateBusBooking($db, $bus_id, $tanggal, $waktu);
    
    if ($result['valid']) {
        echo "<p style='color: green;'>✅ " . $result['message'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ " . $result['message'] . "</p>";
    }
    
    // Test apakah ada bus lain yang sudah dipesan pada tanggal yang sama
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM pemesanan_bus WHERE tanggal_berangkat = ? AND status IN ('dibayar_dp', 'dibayar', 'pending')");
    $stmt->execute([$tanggal]);
    $existing_bookings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>Jumlah pemesanan yang sudah ada untuk tanggal $tanggal: " . $existing_bookings['total'] . "</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Test 5: Test Sinkronisasi Jadwal dan Validasi
echo "<h3>5. Test Sinkronisasi Jadwal dan Validasi</h3>";
try {
    $test_tanggal = date('Y-m-d', strtotime('+3 days'));
    
    echo "<p><strong>Testing tanggal: $test_tanggal</strong></p>";
    
    // 1. Cek jadwal yang tersedia
    $available_buses = getAvailableBusSchedule($db, $test_tanggal);
    echo "<p>Jadwal yang tersedia: " . count($available_buses) . " bus</p>";
    
    if (!empty($available_buses)) {
        $test_bus = $available_buses[0];
        echo "<p>Bus yang akan ditest: {$test_bus['nama_bus']} (ID: {$test_bus['id']})</p>";
        
        // 2. Test validasi untuk bus yang tersedia
        $validation_result = validateBusBooking($db, $test_bus['id'], $test_tanggal, '08:00:00');
        
        if ($validation_result['valid']) {
            echo "<p style='color: green;'>✅ SINKRON: Bus tersedia di jadwal dan bisa dipesan</p>";
        } else {
            echo "<p style='color: red;'>❌ TIDAK SINKRON: Bus tersedia di jadwal tapi tidak bisa dipesan</p>";
            echo "<p>Alasan: " . $validation_result['message'] . "</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️ Tidak ada bus tersedia untuk tanggal ini</p>";
        
        // Test apakah karena sudah ada pemesanan
        $is_booked = isDateBooked($db, $test_tanggal);
        if ($is_booked) {
            echo "<p>✅ Konsisten: Tanggal sudah dipesan</p>";
        } else {
            echo "<p>❌ Tidak konsisten: Tanggal tidak dipesan tapi tidak ada bus tersedia</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Test selesai!</strong></p>";
echo "<p><strong>Aturan yang diterapkan:</strong></p>";
echo "<ul>";
echo "<li>✅ Hanya satu bus yang boleh dipesan per tanggal</li>";
echo "<li>✅ Bus yang sama tidak boleh dipesan dua kali pada tanggal yang sama</li>";
echo "<li>✅ Tidak boleh ada konflik waktu dengan pemesanan lain</li>";
echo "<li>✅ Bus harus berstatus 'tersedia'</li>";
echo "<li>✅ Waktu keberangkatan harus antara 05:00 - 23:00</li>";
echo "<li>✅ Jadwal yang ditampilkan harus sinkron dengan validasi pemesanan</li>";
echo "</ul>";
?>
