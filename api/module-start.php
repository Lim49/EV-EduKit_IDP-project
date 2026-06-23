<?php
header('Content-Type: application/json');
require_once '../db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['kit_mac'], $data['module_number'])) {
    echo json_encode(['success' => false, 'error' => 'Missing fields: kit_mac, module_number']);
    exit;
}

$mac     = trim($data['kit_mac']);
$mod_num = (int)$data['module_number'];

// Resolve kit → user
$stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
$stmt->execute([$mac]);
$session = $stmt->fetch();
if (!$session) {
    echo json_encode(['success' => false, 'error' => 'Kit not linked to any user']);
    exit;
}
$user_id = (int)$session['user_id'];

// Upsert: Reset to In Progress for new attempt
$stmt = $pdo->prepare("
    INSERT INTO module_progress (user_id, module_number, status, questions_answered, questions_correct, highest_score)
    VALUES (?, ?, 'In Progress', 0, 0, 0)
    ON DUPLICATE KEY UPDATE
        status = 'In Progress',
        questions_answered = 0,
        questions_correct = 0
");
$stmt->execute([$user_id, $mod_num]);

echo json_encode(['success' => true, 'user_id' => $user_id, 'module_number' => $mod_num]);
