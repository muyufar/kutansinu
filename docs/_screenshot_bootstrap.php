<?php
/**
 * Helper lokal untuk capture screenshot dokumentasi.
 * Hanya boleh diakses dari localhost / keuangan.test
 */
$allowedHosts = ['localhost', '127.0.0.1', 'keuangan.test'];
$host = $_SERVER['HTTP_HOST'] ?? '';

$isAllowed = false;
foreach ($allowedHosts as $allowed) {
    if (stripos($host, $allowed) !== false) {
        $isAllowed = true;
        break;
    }
}

if (!$isAllowed) {
    http_response_code(403);
    exit('Forbidden');
}

session_start();
require_once __DIR__ . '/../config/database.php';

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 1;
$stmt = $db->prepare('SELECT id, username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    exit('User not found');
}

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];

$redirect = $_GET['redirect'] ?? '/index.php';
header('Location: ' . $redirect);
exit;
