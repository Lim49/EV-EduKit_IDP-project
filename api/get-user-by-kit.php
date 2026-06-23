<?php
header('Content-Type: application/json');
require_once '../db.php';

// Ensure kit_sessions table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS kit_sessions (
    kit_mac   VARCHAR(17) NOT NULL,
    user_id   INT         NOT NULL,
    linked_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kit_mac),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$mac = trim($_GET['mac'] ?? '');
if ($mac === '') {
    echo json_encode(['user_id' => 0, 'linked' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
$stmt->execute([$mac]);
$row = $stmt->fetch();

if ($row) {
    echo json_encode(['user_id' => (int)$row['user_id'], 'linked' => true]);
} else {
    echo json_encode(['user_id' => 0, 'linked' => false]);
}
