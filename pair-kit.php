<?php
session_start();
require_once 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$full_name = $_SESSION['full_name'] ?? 'Explorer';
$user_id   = (int)$_SESSION['user_id'];

// Fetch current linked kit if any
$pdo->exec("CREATE TABLE IF NOT EXISTS kit_sessions (
    kit_mac   VARCHAR(17) NOT NULL,
    user_id   INT         NOT NULL,
    linked_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kit_mac),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$stmt = $pdo->prepare("SELECT kit_mac, linked_at FROM kit_sessions WHERE user_id = ? LIMIT 1");
$stmt->execute([$user_id]);
$linked = $stmt->fetch();

// Fetch available kits announced recently
$pdo->exec("CREATE TABLE IF NOT EXISTS kit_available (
    kit_mac   VARCHAR(17) NOT NULL,
    last_seen TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kit_mac)
)");

$stmt_avail = $pdo->query("SELECT kit_mac, last_seen FROM kit_available WHERE last_seen >= NOW() - INTERVAL 15 MINUTE ORDER BY last_seen DESC LIMIT 5");
$avail_kits = $stmt_avail->fetchAll();
if (empty($avail_kits)) {
    $stmt_avail = $pdo->query("SELECT kit_mac, last_seen FROM kit_available ORDER BY last_seen DESC LIMIT 3");
    $avail_kits = $stmt_avail->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Your Kit — C2D EVKit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: '#22c55e', 'ev-black': '#020617' },
                fontFamily: { sans: ['Inter', 'sans-serif'] }
            }}
        };
    </script>
    <style>
        body { background: #020617; font-family: 'Inter', sans-serif; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.08); }
    </style>
</head>
<body class="min-h-screen bg-ev-black text-white flex items-center justify-center p-4">

    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-3xl text-primary">bolt</span>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">Link Your Kit</h1>
            <p class="text-sm text-gray-400">Enter the MAC address shown on your kit's welcome screen to connect it to your account.</p>
        </div>

        <!-- Current Link Status -->
        <?php if ($linked): ?>
        <div class="glass rounded-2xl p-4 mb-6 border-primary/20">
            <div class="flex items-center gap-3">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                </span>
                <div>
                    <p class="text-xs font-bold text-primary uppercase tracking-widest">Kit Currently Linked</p>
                    <p class="text-sm text-white font-mono mt-0.5"><?= htmlspecialchars($linked['kit_mac']) ?></p>
                    <p class="text-[10px] text-gray-500 mt-0.5">Linked <?= date('d M Y, H:i', strtotime($linked['linked_at'])) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Discovered Kits -->
        <?php if (!empty($avail_kits)): ?>
        <div class="glass rounded-3xl p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest">Discovered Kits on Network</label>
                <button onclick="window.location.reload()" class="text-xs text-primary hover:underline flex items-center gap-1 transition-all">
                    <span class="material-symbols-outlined text-sm">refresh</span> Refresh
                </button>
            </div>
            <div class="space-y-3">
                <?php foreach ($avail_kits as $kit): ?>
                    <?php
                    $last_seen_time = strtotime($kit['last_seen']);
                    $is_active = ($last_seen_time >= time() - 900); // 15 mins
                    $status_color = $is_active ? 'bg-primary' : 'bg-gray-500';
                    
                    // Human readable time
                    $diff = time() - $last_seen_time;
                    if ($diff < 60) {
                        $time_str = "just now";
                    } elseif ($diff < 3600) {
                        $time_str = round($diff / 60) . "m ago";
                    } else {
                        $time_str = round($diff / 3600) . "h ago";
                    }
                    ?>
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white/5 border border-white/10 hover:border-white/20 transition-all">
                        <div class="flex items-center gap-2.5">
                            <span class="relative flex h-2.5 w-2.5">
                                <?php if ($is_active): ?>
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <?php endif; ?>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 <?= $status_color ?>"></span>
                            </span>
                            <div>
                                <p class="text-sm font-mono font-bold text-white"><?= htmlspecialchars($kit['kit_mac']) ?></p>
                                <p class="text-[10px] text-gray-500">Last seen: <?= $time_str ?></p>
                            </div>
                        </div>
                        <button
                            onclick="selectDiscoveredKit('<?= htmlspecialchars($kit['kit_mac']) ?>')"
                            class="bg-primary text-black text-xs font-bold px-3 py-1.5 rounded-lg hover:opacity-90 active:scale-95 transition-all"
                        >
                            Select & Link
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Link Form -->
        <div class="glass rounded-3xl p-6 mb-4">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Kit MAC Address</label>
            <input
                id="mac-input"
                type="text"
                maxlength="17"
                placeholder="AA:BB:CC:DD:EE:FF"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white font-mono text-sm placeholder:text-gray-600 focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/30 transition-all mb-4"
            >
            <button
                id="link-btn"
                onclick="linkKit()"
                class="w-full bg-primary text-black font-bold py-3 rounded-xl hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2"
            >
                <span class="material-symbols-outlined text-lg">link</span>
                Link This Kit
            </button>
            <p id="status-msg" class="text-sm text-center mt-3 hidden"></p>
        </div>

        <!-- Help -->
        <div class="glass rounded-2xl p-4 mb-6">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">How to find your kit MAC</p>
            <ol class="text-xs text-gray-400 space-y-1 list-decimal list-inside">
                <li>Power on the EVKit hardware</li>
                <li>Wait for the welcome screen to load</li>
                <li>The MAC address is shown below "EDUCATIONAL QUIZ SYSTEM"</li>
                <li>It looks like: <span class="font-mono text-white">24:6F:28:AB:CD:EF</span></li>
            </ol>
        </div>

        <div class="text-center">
            <a href="Home.php" class="text-sm text-gray-400 hover:text-white transition-colors">← Back to Home</a>
        </div>
    </div>

    <script>
        function selectDiscoveredKit(mac) {
            document.getElementById('mac-input').value = mac;
            linkKit();
        }

        async function linkKit() {
            const mac = document.getElementById('mac-input').value.trim().toUpperCase();
            const btn = document.getElementById('link-btn');
            const msg = document.getElementById('status-msg');

            const macRegex = /^([0-9A-F]{2}[:\-]){5}[0-9A-F]{2}$/;
            if (!macRegex.test(mac)) {
                msg.textContent = '⚠ Invalid MAC format. Example: AA:BB:CC:DD:EE:FF';
                msg.className   = 'text-sm text-center mt-3 text-orange-400';
                msg.classList.remove('hidden');
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'Linking...';

            try {
                const res  = await fetch('api/kit-link.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ kit_mac: mac })
                });
                const data = await res.json();
                if (data.success) {
                    msg.textContent = '✓ Kit linked successfully! Redirecting…';
                    msg.className   = 'text-sm text-center mt-3 text-primary';
                    msg.classList.remove('hidden');
                    setTimeout(() => window.location.href = 'Home.php', 1500);
                } else {
                    msg.textContent = '✗ ' + (data.error || 'Unknown error');
                    msg.className   = 'text-sm text-center mt-3 text-red-400';
                    msg.classList.remove('hidden');
                    btn.disabled    = false;
                    btn.textContent = 'Link This Kit';
                }
            } catch (e) {
                msg.textContent = '✗ Network error. Is the server running?';
                msg.className   = 'text-sm text-center mt-3 text-red-400';
                msg.classList.remove('hidden');
                btn.disabled    = false;
                btn.textContent = 'Link This Kit';
            }
        }

        // Auto-format MAC input as user types
        document.getElementById('mac-input').addEventListener('input', function (e) {
            let v = e.target.value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase();
            let formatted = v.match(/.{1,2}/g)?.join(':') || v;
            if (formatted.length > 17) formatted = formatted.substring(0, 17);
            e.target.value = formatted;
        });
    </script>
</body>
</html>
