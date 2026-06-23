<?php
header('Content-Type: application/json');
require_once '../db.php';

// Ensure kit_status table exists (matches get-kit-status.php schema)
$pdo->exec("CREATE TABLE IF NOT EXISTS kit_status (
    user_id            INT PRIMARY KEY,
    node_grid          TINYINT(1) DEFAULT 0,
    node_substation    TINYINT(1) DEFAULT 0,
    node_home          TINYINT(1) DEFAULT 0,
    node_obc           TINYINT(1) DEFAULT 0,
    node_battery       TINYINT(1) DEFAULT 0,
    node_solar         TINYINT(1) DEFAULT 0,
    node_dcdc          TINYINT(1) DEFAULT 0,
    node_bess          TINYINT(1) DEFAULT 0,
    node_station       TINYINT(1) DEFAULT 0,
    node_charger       TINYINT(1) DEFAULT 0,
    potentiometer_value INT DEFAULT 0,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['kit_mac'])) {
    echo json_encode(['success' => false, 'error' => 'Missing kit_mac']);
    exit;
}

$mac = trim($data['kit_mac']);

// Resolve kit → user
$stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
$stmt->execute([$mac]);
$session = $stmt->fetch();
if (!$session) {
    echo json_encode(['success' => false, 'error' => 'Kit not linked to any user']);
    exit;
}
$user_id = (int)$session['user_id'];

// Extract node values (default 0 for any missing field)
$g   = (int)($data['node_grid']          ?? 0);
$sub = (int)($data['node_substation']     ?? 0);
$hm  = (int)($data['node_home']           ?? 0);
$obc = (int)($data['node_obc']            ?? 0);
$bat = (int)($data['node_battery']        ?? 0);
$sol = (int)($data['node_solar']          ?? 0);
$ddc = (int)($data['node_dcdc']           ?? 0);
$bss = (int)($data['node_bess']           ?? 0);
$sta = (int)($data['node_station']        ?? 0);
$chg = (int)($data['node_charger']        ?? 0);
$pot = (int)($data['potentiometer_value'] ?? 0);

$stmt = $pdo->prepare("
    INSERT INTO kit_status
        (user_id, node_grid, node_substation, node_home, node_obc, node_battery,
         node_solar, node_dcdc, node_bess, node_station, node_charger, potentiometer_value)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        node_grid           = VALUES(node_grid),
        node_substation     = VALUES(node_substation),
        node_home           = VALUES(node_home),
        node_obc            = VALUES(node_obc),
        node_battery        = VALUES(node_battery),
        node_solar          = VALUES(node_solar),
        node_dcdc           = VALUES(node_dcdc),
        node_bess           = VALUES(node_bess),
        node_station        = VALUES(node_station),
        node_charger        = VALUES(node_charger),
        potentiometer_value = VALUES(potentiometer_value)
");
$stmt->execute([$user_id, $g, $sub, $hm, $obc, $bat, $sol, $ddc, $bss, $sta, $chg, $pot]);

echo json_encode(['success' => true, 'user_id' => $user_id]);
