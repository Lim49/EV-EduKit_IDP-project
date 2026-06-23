<?php
header('Content-Type: application/json');
require_once 'db.php';

// Retrieve JSON from POST body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data or User ID missing']);
    exit;
}

$user_id = (int)$data['user_id'];

// Map incoming keys to database columns
$allowed_nodes = [
    'node_grid', 'node_substation', 'node_home', 'node_obc', 'node_battery',
    'node_solar', 'node_dcdc', 'node_bess', 'node_station', 'node_charger',
    'potentiometer_value'
];

$updates = [];
$params = [];

foreach ($allowed_nodes as $node) {
    if (isset($data[$node])) {
        $updates[] = "$node = ?";
        $params[] = $data[$node];
    }
}

if (empty($updates)) {
    echo json_encode(['message' => 'No updates provided']);
    exit;
}

// Add user_id for the WHERE clause
$params[] = $user_id;

try {
    $sql = "UPDATE kit_status SET " . implode(', ', $updates) . " WHERE user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        // If no row updated, it might be a new user or same data
        // Try to insert if it doesn't exist
        $check = $pdo->prepare("SELECT user_id FROM kit_status WHERE user_id = ?");
        $check->execute([$user_id]);
        if (!$check->fetch()) {
            // Standard columns + potentiometer
            $pdo->prepare("INSERT INTO kit_status (user_id) VALUES (?)")->execute([$user_id]);
            // Re-run update
            $stmt->execute($params);
        }
    }

    echo json_encode(['status' => 'success', 'updated_at' => date('Y-m-d H:i:s')]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>