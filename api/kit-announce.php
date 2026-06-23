<?php
// ESP32 calls this on every boot to announce itself to the network.
// No session/auth required — called by hardware.
header('Content-Type: application/json');
require_once '../db.php';

// Create kit_available table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS kit_available (
    kit_mac   VARCHAR(17) NOT NULL,
    last_seen TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kit_mac)
)");

$data = json_decode(file_get_contents('php://input'), true);
$mac  = trim($data['kit_mac'] ?? '');

if ($mac === '' || !preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/', $mac)) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing kit_mac']);
    exit;
}

// Upsert: one row per kit, last_seen auto-updates
$stmt = $pdo->prepare("
    INSERT INTO kit_available (kit_mac)
    VALUES (?)
    ON DUPLICATE KEY UPDATE last_seen = CURRENT_TIMESTAMP
");
$stmt->execute([$mac]);

echo json_encode(['success' => true, 'kit_mac' => $mac]);
