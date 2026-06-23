<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true);
$mac  = trim($data['kit_mac'] ?? '');

if ($mac === '' || !preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/', $mac)) {
    echo json_encode(['success' => false, 'error' => 'Invalid MAC address format']);
    exit;
}

// Ensure table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS kit_sessions (
    kit_mac   VARCHAR(17) NOT NULL,
    user_id   INT         NOT NULL,
    linked_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kit_mac),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Overwrite any existing link for this MAC (one row per kit)
$stmt = $pdo->prepare("
    INSERT INTO kit_sessions (kit_mac, user_id)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)
");
$stmt->execute([$mac, $user_id]);

echo json_encode(['success' => true, 'kit_mac' => $mac, 'user_id' => $user_id]);
