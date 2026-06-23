<?php
header('Content-Type: application/json');
require_once '../db.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['kit_mac'], $data['module_number'], $data['score'], $data['total_questions'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$mac        = trim($data['kit_mac']);
$mod_num    = (int)$data['module_number'];
$score      = (int)$data['score'];
$total      = (int)$data['total_questions'];
$percent    = $total > 0 ? round(($score / $total) * 100, 2) : 0;
$breakdown  = isset($data['breakdown']) ? json_encode($data['breakdown']) : null;

// Resolve kit → user
$stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
$stmt->execute([$mac]);
$session = $stmt->fetch();
if (!$session) {
    echo json_encode(['success' => false, 'error' => 'Kit not linked to any user']);
    exit;
}
$user_id = (int)$session['user_id'];

try {
    $pdo->beginTransaction();

    // Check if breakdown_data column exists; if not, skip it
    $cols = "user_id, module_number, score, total_questions, percentage";
    $vals = "?, ?, ?, ?, ?";
    $params = [$user_id, $mod_num, $score, $total, $percent];
    if ($breakdown !== null) {
        $cols  .= ", breakdown_data";
        $vals  .= ", ?";
        $params[] = $breakdown;
    }

    $stmt = $pdo->prepare("INSERT INTO quiz_logs ($cols) VALUES ($vals)");
    $stmt->execute($params);

    // Upsert module_progress — always mark Completed, keep highest score
    $stmt = $pdo->prepare("
        INSERT INTO module_progress (user_id, module_number, status, highest_score)
        VALUES (?, ?, 'Completed', ?)
        ON DUPLICATE KEY UPDATE
            status        = 'Completed',
            highest_score = GREATEST(highest_score, VALUES(highest_score))
    ");
    $stmt->execute([$user_id, $mod_num, $percent]);

    $pdo->commit();
    echo json_encode([
        'success'       => true,
        'user_id'       => $user_id,
        'module_number' => $mod_num,
        'score'         => $score,
        'total'         => $total,
        'percentage'    => $percent
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
