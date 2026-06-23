<?php
/**
 * EVKit Secure Cloud MQTT Bridge
 * This script subscribes to HiveMQ Cloud MQTT topics and updates the local Laragon database.
 * Run this from the command line: php mqtt-bridge.php
 */

set_time_limit(0);

require_once 'db.php';
require_once 'vendor/autoload.php'; // Requires php-mqtt/client

use \PhpMqtt\Client\MqttClient;
use \PhpMqtt\Client\ConnectionSettings;

// ── HiveMQ Cloud MQTT Configuration ─────────────────────────────────────────
$server   = '07b25c25a5d447b5a39a2edc150c0689.s1.eu.hivemq.cloud'; // ← Replace with your HiveMQ Host
$port     = 8883;                                  // Secure TLS Port
$username = 'C2DKit';                       // ← Replace with your HiveMQ Username
$password = 'weare*P1G13*';                       // ← Replace with your HiveMQ Password
$clientId = 'evkit_backend_bridge_' . uniqid();

// ── Database Schema Verification ─────────────────────────────────────────────
try {
    // 1. kit_sessions
    $pdo->exec("CREATE TABLE IF NOT EXISTS kit_sessions (
        kit_mac   VARCHAR(17) NOT NULL,
        user_id   INT         NOT NULL,
        linked_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (kit_mac),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 2. kit_available
    $pdo->exec("CREATE TABLE IF NOT EXISTS kit_available (
        kit_mac   VARCHAR(17) NOT NULL,
        last_seen TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (kit_mac)
    )");

    // 3. kit_status
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
    try {
        $pdo->exec("ALTER TABLE module_progress ADD COLUMN questions_answered INT DEFAULT 0 AFTER status");
        $pdo->exec("ALTER TABLE module_progress ADD COLUMN questions_correct INT DEFAULT 0 AFTER questions_answered");
    } catch (\Exception $e) {
        // Columns probably exist
    }
    echo "Database schemas verified/created.\n";
} catch (\PDOException $e) {
    die("Database Schema Init Error: " . $e->getMessage() . "\n");
}

// ── Persistent Bridge Daemon Loop ────────────────────────────────────────────
while (true) {
    try {
        echo "Connecting to HiveMQ Cloud MQTT Broker at $server:$port...\n";

        $mqtt = new MqttClient($server, $port, $clientId);

        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval(60)
            ->setUseTls(true)
            ->setTlsVerifyPeer(false) // Skip cert path validation to avoid OpenSSL issues on local PHP CLI
            ->setLastWillTopic('evkit/bridge/status')
            ->setLastWillMessage('offline');

        if (!empty($username)) {
            $settings = $settings
                ->setUsername($username)
                ->setPassword($password);
        }

        $mqtt->connect($settings, true);
        echo "Connected successfully to MQTT Broker!\n";

        // --- Topic 1: Kit Announcement ---
        $mqtt->subscribe('evkit/announce', function ($topic, $message) use ($pdo) {
            $data = json_decode($message, true);
            if (!$data || !isset($data['mac'])) return;

            $mac = trim($data['mac']);
            if ($mac === '' || !preg_match('/^([0-9A-Fa-f]{2}[:\-]){5}[0-9A-Fa-f]{2}$/', $mac)) {
                echo "[ANNOUNCE] Invalid MAC format: $mac\n";
                return;
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO kit_available (kit_mac)
                    VALUES (?)
                    ON DUPLICATE KEY UPDATE last_seen = CURRENT_TIMESTAMP
                ");
                $stmt->execute([$mac]);
                echo "[ANNOUNCE] Kit seen: $mac\n";
            } catch (\PDOException $e) {
                echo "DB Error (Announce): " . $e->getMessage() . "\n";
            }
        }, 0);

        // --- Topic 2: Dynamic Handshake Request ---
        $mqtt->subscribe('evkit/request', function ($topic, $message) use ($mqtt, $pdo) {
            $data = json_decode($message, true);
            if (!$data || !isset($data['mac'])) return;

            $mac = trim($data['mac']);
            try {
                $stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
                $stmt->execute([$mac]);
                $row = $stmt->fetch();

                $userId = $row ? (int)$row['user_id'] : 0;
                $linked = $row ? true : false;

                $responsePayload = json_encode([
                    'user_id' => $userId,
                    'linked'  => $linked
                ]);

                $responseTopic = 'evkit/response/' . $mac;
                $mqtt->publish($responseTopic, $responsePayload, 0);
                echo "[HANDSHAKE] Request from $mac -> Response: user_id=$userId, linked=" . ($linked ? 'true' : 'false') . "\n";
            } catch (\Exception $e) {
                echo "Error (Handshake): " . $e->getMessage() . "\n";
            }
        }, 0);

        // --- Topic 3: Module Start ---
        $mqtt->subscribe('evkit/module-start', function ($topic, $message) use ($pdo) {
            $data = json_decode($message, true);
            if (!$data || !isset($data['mac'], $data['module_number'])) return;

            $mac     = trim($data['mac']);
            $mod_num = (int)$data['module_number'];

            try {
                // Resolve kit -> user
                $stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
                $stmt->execute([$mac]);
                $session = $stmt->fetch();
                if (!$session) {
                    echo "[MODULE START] Warning: Kit $mac not linked to any user.\n";
                    return;
                }
                $user_id = (int)$session['user_id'];

                // Upsert module_progress (Reset to In Progress for new attempt)
                $stmtProg = $pdo->prepare("
                    INSERT INTO module_progress (user_id, module_number, status, questions_answered, questions_correct, highest_score)
                    VALUES (?, ?, 'In Progress', 0, 0, 0)
                    ON DUPLICATE KEY UPDATE
                        status = IF(status = 'Completed', 'Completed', 'In Progress'),
                        questions_answered = 0,
                        questions_correct = 0
                ");
                $stmtProg->execute([$user_id, $mod_num]);
                echo "[MODULE START] User $user_id, Module $mod_num started.\n";
            } catch (\PDOException $e) {
                echo "DB Error (Module Start): " . $e->getMessage() . "\n";
            }
        }, 0);

        // --- Topic 4: Node State Updates ---
        $mqtt->subscribe('evkit/update-nodes', function ($topic, $message) use ($pdo) {
            $data = json_decode($message, true);
            if (!$data || !isset($data['mac'])) return;

            $mac = trim($data['mac']);
            try {
                // Resolve kit -> user
                $stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
                $stmt->execute([$mac]);
                $session = $stmt->fetch();
                if (!$session) {
                    echo "[NODES] Warning: Kit $mac not linked to any user.\n";
                    return;
                }
                $user_id = (int)$session['user_id'];

                $nodes = $data['nodes'] ?? [];
                $g   = (int)($nodes['grid']       ?? 0);
                $sub = (int)($nodes['substation'] ?? 0);
                $hm  = (int)($nodes['home']       ?? 0);
                $obc = (int)($nodes['obc']        ?? 0);
                $bat = (int)($nodes['battery']    ?? 0);
                $sol = (int)($nodes['solar']      ?? 0);
                $ddc = (int)($nodes['dcdc']       ?? 0);
                $bss = (int)($nodes['bess']       ?? 0);
                $sta = (int)($nodes['station']    ?? 0);
                $chg = (int)($nodes['charger']    ?? 0);
                $pot = (int)($data['pot']         ?? 0);

                $stmtUpdate = $pdo->prepare("
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
                $stmtUpdate->execute([$user_id, $g, $sub, $hm, $obc, $bat, $sol, $ddc, $bss, $sta, $chg, $pot]);
                echo "[NODES] Updated for User $user_id: Pot=$pot\n";

                // Parse and update in-progress quiz score
                $mod_num = isset($data['module_number']) ? (int)$data['module_number'] : 0;
                $qScore  = isset($data['quiz_score']) ? (int)$data['quiz_score'] : null;
                $qTotal  = isset($data['quiz_total']) ? (int)$data['quiz_total'] : null;

                if ($mod_num > 0 && $qScore !== null && $qTotal !== null && $qTotal > 0) {
                    $stmtProg = $pdo->prepare("
                        INSERT INTO module_progress 
                            (user_id, module_number, status, questions_answered, questions_correct)
                        VALUES (?, ?, 'In Progress', ?, ?)
                        ON DUPLICATE KEY UPDATE
                            status = IF(status = 'Completed', 'Completed', 'In Progress'),
                            questions_answered = VALUES(questions_answered),
                            questions_correct = VALUES(questions_correct)
                    ");
                    $stmtProg->execute([$user_id, $mod_num, $qTotal, $qScore]);
                }
            } catch (\PDOException $e) {
                echo "DB Error (Nodes): " . $e->getMessage() . "\n";
            }
        }, 0);

        // --- Topic 5: Quiz Submit ---
        $mqtt->subscribe('evkit/quiz-submit', function ($topic, $message) use ($pdo) {
            $data = json_decode($message, true);
            if (!$data || !isset($data['mac'], $data['module_number'], $data['score'], $data['total_questions'])) {
                echo "[QUIZ] Invalid payload.\n";
                return;
            }

            $mac        = trim($data['mac']);
            $mod_num    = (int)$data['module_number'];
            $score      = (int)$data['score'];
            $total      = (int)$data['total_questions'];
            $percent    = $total > 0 ? round(($score / $total) * 100, 2) : 0;
            $breakdown  = isset($data['breakdown']) ? json_encode($data['breakdown']) : null;

            try {
                // Resolve kit -> user
                $stmt = $pdo->prepare("SELECT user_id FROM kit_sessions WHERE kit_mac = ?");
                $stmt->execute([$mac]);
                $session = $stmt->fetch();
                if (!$session) {
                    echo "[QUIZ] Warning: Kit $mac not linked to any user.\n";
                    return;
                }
                $user_id = (int)$session['user_id'];

                $pdo->beginTransaction();

                $cols = "user_id, module_number, score, total_questions, percentage";
                $vals = "?, ?, ?, ?, ?";
                $params = [$user_id, $mod_num, $score, $total, $percent];
                if ($breakdown !== null) {
                    $cols  .= ", breakdown_data";
                    $vals  .= ", ?";
                    $params[] = $breakdown;
                }

                $stmtLog = $pdo->prepare("INSERT INTO quiz_logs ($cols) VALUES ($vals)");
                $stmtLog->execute($params);

                // Upsert module_progress
                $stmtProg = $pdo->prepare("
                    INSERT INTO module_progress (user_id, module_number, status, highest_score)
                    VALUES (?, ?, 'Completed', ?)
                    ON DUPLICATE KEY UPDATE
                        status        = 'Completed',
                        highest_score = GREATEST(highest_score, VALUES(highest_score))
                ");
                $stmtProg->execute([$user_id, $mod_num, $percent]);

                $pdo->commit();
                echo "[QUIZ] Saved for User $user_id, Module $mod_num: $score/$total ($percent%)\n";
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo "DB Error (Quiz): " . $e->getMessage() . "\n";
            }
        }, 0);

        // Keep subscribing and checking for incoming messages
        $mqtt->loop(true);

    } catch (\Exception $e) {
        echo "MQTT Daemon Error: " . $e->getMessage() . "\n";
        echo "Reconnecting in 5 seconds...\n";
        sleep(5);
    }
}