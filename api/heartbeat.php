<?php
header('Content-Type: application/json');
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Ensure table exists with last_active column
    $pdo->exec("CREATE TABLE IF NOT EXISTS kit_sessions (
        kit_mac     VARCHAR(17) NOT NULL,
        user_id     INT         NOT NULL,
        linked_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        last_active TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (kit_mac),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    try {
        $pdo->exec("ALTER TABLE kit_sessions ADD COLUMN last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    } catch (\PDOException $e) {
        // Already exists
    }

    // Clean up any expired sessions (no heartbeat in the last 20 seconds)
    $pdo->exec("DELETE FROM kit_sessions WHERE last_active < NOW() - INTERVAL 20 SECOND");

    // Update last_active for current user
    $stmt = $pdo->prepare("UPDATE kit_sessions SET last_active = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode(['success' => true]);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
