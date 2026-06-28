<?php
header('Content-Type: application/json');
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS kit_status (
        user_id INT PRIMARY KEY,
        node_grid TINYINT(1) DEFAULT 0,
        node_substation TINYINT(1) DEFAULT 0,
        node_home TINYINT(1) DEFAULT 0,
        node_obc TINYINT(1) DEFAULT 0,
        node_battery TINYINT(1) DEFAULT 0,
        node_solar TINYINT(1) DEFAULT 0,
        node_dcdc TINYINT(1) DEFAULT 0,
        node_bess TINYINT(1) DEFAULT 0,
        node_station TINYINT(1) DEFAULT 0,
        node_charger TINYINT(1) DEFAULT 0,
        potentiometer_value INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Ensure the column exists if the table was already created
    try {
        $pdo->exec("ALTER TABLE kit_status ADD COLUMN potentiometer_value INT DEFAULT 0 AFTER node_charger");
    } catch (Exception $e) {
        // Column probably exists
    }

    try {
        $pdo->exec("ALTER TABLE module_progress ADD COLUMN questions_answered INT DEFAULT 0 AFTER status");
        $pdo->exec("ALTER TABLE module_progress ADD COLUMN questions_correct INT DEFAULT 0 AFTER questions_answered");
    } catch (Exception $e) {
        // Columns probably exist
    }

    // 2. Fetch status
    $stmt = $pdo->prepare("SELECT * FROM kit_status WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $status = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$status) {
        // Initial insert if not found
        $pdo->prepare("INSERT INTO kit_status (user_id) VALUES (?)")->execute([$user_id]);
        $status = [
            'node_grid' => 0, 'node_substation' => 0, 'node_home' => 0, 'node_obc' => 0,
            'node_battery' => 0, 'node_solar' => 0, 'node_dcdc' => 0, 'node_bess' => 0,
            'node_station' => 0, 'node_charger' => 0, 'potentiometer_value' => 0
        ];
    }

    // Clean up expired sessions (no heartbeat in the last 20 seconds)
    $pdo->exec("DELETE FROM kit_sessions WHERE last_active < NOW() - INTERVAL 20 SECOND");

    // 3. Determine if the kit is actually online (last_seen within 15 seconds)
    $online = false;
    $stmtMac = $pdo->prepare("SELECT kit_mac FROM kit_sessions WHERE user_id = ?");
    $stmtMac->execute([$user_id]);
    $session = $stmtMac->fetch(PDO::FETCH_ASSOC);
    if ($session) {
        $mac = $session['kit_mac'];
        $stmtSeen = $pdo->prepare("
            SELECT (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_seen)) AS seconds_ago 
            FROM kit_available 
            WHERE kit_mac = ?
        ");
        $stmtSeen->execute([$mac]);
        $available = $stmtSeen->fetch(PDO::FETCH_ASSOC);
        if ($available && $available['seconds_ago'] !== null) {
            if ((int)$available['seconds_ago'] < 15) {
                $online = true;
            }
        }
    }
    
    $status['online'] = $online;

    echo json_encode($status);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>