<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Specialist';

// Redirect to pairing page if user has no linked kit
$stmt = $pdo->prepare("SELECT COUNT(*) FROM kit_sessions WHERE user_id = ?");
$stmt->execute([$user_id]);
$has_kit = $stmt->fetchColumn();
if (!$has_kit) {
    header("Location: pair-kit.php");
    exit;
}

// Navigation Logic
$prev_link = "module2.php"; // Point back to Module 2
$next_link = "dashboard.php"; // Point to Dashboard or Completion
$current_mod = '3.0';

// Determine the active module link dynamically based on progress
$stmt_prog = $pdo->prepare("SELECT module_number, status FROM module_progress WHERE user_id = ?");
$stmt_prog->execute([$user_id]);
$progress_rows = $stmt_prog->fetchAll(PDO::FETCH_KEY_PAIR);

$active_module_link = 'module.php'; // default if nothing started
$active_module_text = 'Start Course';

if (!isset($progress_rows[1]) || $progress_rows[1] !== 'Completed') {
    if (isset($progress_rows[1]) && $progress_rows[1] === 'In Progress') {
        $active_module_link = 'module1.php';
        $active_module_text = 'Continue Module 1';
    } else {
        $has_any_progress = count($progress_rows) > 0;
        if ($has_any_progress) {
            $active_module_link = 'module1.php';
            $active_module_text = 'Start Module 1';
        } else {
            $active_module_link = 'module.php';
            $active_module_text = 'Start Course';
        }
    }
} else if (!isset($progress_rows[2]) || $progress_rows[2] !== 'Completed') {
    $active_module_link = 'module2.php';
    if (isset($progress_rows[2]) && $progress_rows[2] === 'In Progress') {
        $active_module_text = 'Continue Module 2';
    } else {
        $active_module_text = 'Start Module 2';
    }
} else if (!isset($progress_rows[3]) || $progress_rows[3] !== 'Completed') {
    $active_module_link = 'module3.php';
    if (isset($progress_rows[3]) && $progress_rows[3] === 'In Progress') {
        $active_module_text = 'Continue Module 3';
    } else {
        $active_module_text = 'Start Module 3';
    }
} else {
    // All modules completed
    $active_module_link = 'module.php';
    $active_module_text = 'Review Course';
}
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Module 3: Driving - C2D EVKit</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#22C55E",
                        "ev-black": "#020617",
                        "ev-dark": "#0F172A",
                        "ev-surface": "#1E293B",
                        "on-surface": "#F8FAFC",
                        "on-surface-variant": "#94A3B8"
                    },
                    fontFamily: {
                        'sans': ['Inter', 'ui-sans-serif', 'system-ui'],
                        'display': ['Montserrat', 'ui-sans-serif', 'system-ui'],
                    },
                    fontSize: {
                        "headline-xl": "40px",
                        "headline-lg": "32px",
                        "headline-md": "24px",
                        "body-lg": "17px",
                        "body-md": "16px",
                        "label-bold": "14px"
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="css/style.css">
    <style type="text/tailwindcss">
        @layer utilities {
            .ev-container {
                @apply w-full max-w-[1440px] mx-auto px-3 md:px-12;
            }
            .text-headline-xl { font-family: 'Montserrat', sans-serif; font-size: theme('fontSize.headline-xl'); font-weight: 700; }
            .text-headline-lg { font-family: 'Montserrat', sans-serif; font-size: theme('fontSize.headline-lg'); font-weight: 700; }
            .text-headline-md { font-family: 'Montserrat', sans-serif; font-size: theme('fontSize.headline-md'); font-weight: 600; }
            .text-body-lg { font-family: 'Inter', sans-serif; font-size: theme('fontSize.body-lg'); }
            .text-body-md { font-family: 'Inter', sans-serif; font-size: theme('fontSize.body-md'); }
            .text-label-bold { font-family: 'Inter', sans-serif; font-size: theme('fontSize.label-bold'); font-weight: 600; }
            
            .glass {
                @apply bg-white/5 backdrop-blur-md border border-white/10;
            }
            .glass-hover {
                @apply hover:bg-white/10 transition-all duration-300;
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .scrollbar-hide {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            
            /* Vertical Flow Indicator */
            .sidebar-flow-container {
                @apply relative pl-8;
            }
            .sidebar-flow-line {
                @apply absolute left-[15px] top-2 bottom-2 w-0.5 bg-white/10;
            }
            .flow-bullet {
                @apply absolute left-[-21px] w-3 h-3 rounded-full bg-white/20 border-2 border-ev-black transition-all duration-300;
            }
            .flow-bullet-active {
                @apply bg-primary border-primary shadow-[0_0_10px_rgba(34,197,94,0.6)];
            }

        }
    </style>
</head>
<body class="bg-ev-black text-on-surface antialiased selection:bg-primary/30">

    <!-- Header -->
    <header class="bg-[#0F172A] sticky top-0 z-50 border-b border-white/10 transition-all duration-300 ease-in-out">
        <div class="flex justify-between items-center h-16 md:h-18 px-3 md:px-12 w-full mx-auto">
            <div class="flex items-center gap-4">
                <img alt="C2D EVKit Logo" class="h-8 md:h-10 w-auto object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHW7QX5Gcx9HyNP0fx6oc2vr5zPoe6axUJZ-rlQ2f_NjTDgN_9E7pl76bnkfDFjWLaRk9xBaNQi9MhgB6_gS1OCzDcwpcInyStp5fwN7nlcUgBuX3TjDnwLVP51vqj92KgNbqYeHWSTgXck6i1y831wzIrYE9pdkGb9N9AtjQW9Mlve5sHuHcJZ7R2vo8FgGIIG5AN1Tq4WRUd08HZau72BWpjjC8K_KBViLxzARq558ZtFmJZ_eC9lMD1xY3cxT2HNBpNZe5jYEs">
            </div>
            <div class="flex items-center gap-6">
                    <button class="text-gray-400 hover:text-[#22c55e] transition-colors relative flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">notifications</span>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-primary rounded-full border-2 border-[#0F172A]"></span>
                    </button>
                    <div class="flex items-center gap-3 group cursor-pointer">
                        <div class="w-10 h-10 rounded-full border-2 border-[#22c55e]/30 p-0.5 group-hover:border-[#22c55e] transition-all">
                            <img src="image/user.png" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="hidden sm:inline text-sm font-medium text-gray-400 group-hover:text-white transition-colors"><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                    <a href="logout.php" class="material-symbols-outlined text-gray-400 hover:text-red-500 transition-colors text-2xl" title="Logout">
                        logout
                    </a>
                    <button id="menu-toggle" class="p-2 text-gray-400 hover:text-[#22c55e] transition-colors flex items-center justify-center focus:outline-none">
                        <span class="material-symbols-outlined text-2xl">menu</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-64px)] relative overflow-x-hidden">
        <!-- Sidebar Navigator -->
        <aside id="sidebar" class="fixed top-16 right-0 z-40 w-80 h-[calc(100vh-64px)] glass border-l border-white/5 transform translate-x-full transition-transform duration-300 overflow-y-auto scrollbar-hide">
            <div class="p-6 pb-32 relative text-white">
                <!-- Navigation Links (Home, Module, Dashboard) -->
                <div class="space-y-3 mb-6 pb-6 border-b border-white/10">
                    <a href="Home.php" class="flex items-center gap-2.5 text-sm font-bold text-on-surface-variant hover:text-[#22c55e] transition-colors">
                        <span class="material-symbols-outlined text-lg">home</span> Home
                    </a>
                    <a href="<?php echo htmlspecialchars($active_module_link); ?>" class="flex items-center gap-2.5 text-sm font-bold <?php echo ($active_module_link === 'module3.php') ? 'text-[#22c55e]' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> transition-colors">
                        <span class="material-symbols-outlined text-lg">menu_book</span> Module
                    </a>
                    <a href="dashboard.php" class="flex items-center gap-2.5 text-sm font-bold text-on-surface-variant hover:text-[#22c55e] transition-colors">
                        <span class="material-symbols-outlined text-lg">dashboard</span> Dashboard
                    </a>
                    <a href="pair-kit.php" class="flex items-center gap-2.5 text-sm font-bold text-on-surface-variant hover:text-[#22c55e] transition-colors">
                        <span class="material-symbols-outlined text-lg">link</span> Pair Kit
                    </a>
                </div>

                <h2 class="text-body-lg font-bold text-on-surface mb-6">Course Content</h2>
                
                <div class="space-y-4">
                    <!-- Module 0 -->
                    <details class="group select-none" <?php echo ($current_mod === '0.0') ? 'open' : ''; ?>>
                        <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-on-surface hover:text-[#22c55e] cursor-pointer py-2">
                            <span>Module 0</span>
                            <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                            <a href="module.php" class="block text-xs font-bold <?php echo ($current_mod === '0.0') ? 'text-[#22c55e] underline' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> mb-2">Go to Module 0 →</a>
                            <span class="block text-xs font-medium text-on-surface-variant">0.0 EV Fundamentals</span>
                        </div>
                    </details>

                    <!-- Module 1 -->
                    <details class="group select-none" <?php echo (strpos($current_mod, '1.') === 0) ? 'open' : ''; ?>>
                        <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-on-surface hover:text-[#22c55e] cursor-pointer py-2">
                            <span>Module 1</span>
                            <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                            <a href="module1.php" class="block text-xs font-bold <?php echo (strpos($current_mod, '1.') === 0) ? 'text-[#22c55e] underline' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> mb-2">Go to Module 1 →</a>
                            <span class="block text-xs font-medium text-on-surface-variant">1.1 Transmission Tower to Substation</span>
                            <span class="block text-xs font-medium text-on-surface-variant">1.2 Substation to Home</span>
                            <span class="block text-xs font-medium text-on-surface-variant">1.3 EV Charger to OBC</span>
                            <span class="block text-xs font-medium text-on-surface-variant">1.4 OBC to Battery</span>
                        </div>
                    </details>

                    <!-- Module 2 -->
                    <details class="group select-none" <?php echo (strpos($current_mod, '2.') === 0) ? 'open' : ''; ?>>
                        <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-on-surface hover:text-[#22c55e] cursor-pointer py-2">
                            <span>Module 2</span>
                            <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                            <a href="module2.php" class="block text-xs font-bold <?php echo (strpos($current_mod, '2.') === 0) ? 'text-[#22c55e] underline' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> mb-2">Go to Module 2 →</a>
                            <span class="block text-xs font-medium text-on-surface-variant">2.1 Substation to DC station</span>
                            <span class="block text-xs font-medium text-on-surface-variant">2.2 DC Charger to Battery</span>
                            <span class="block text-xs font-medium text-on-surface-variant">2.3 Solar to DC-DC</span>
                            <span class="block text-xs font-medium text-on-surface-variant">2.4 DC-DC to BESS</span>
                            <span class="block text-xs font-medium text-on-surface-variant">2.5 BESS to Station</span>
                            <span class="block text-xs font-medium text-on-surface-variant">2.6 Station to Battery</span>
                        </div>
                    </details>

                    <!-- Module 3 -->
                    <details class="group select-none" <?php echo (strpos($current_mod, '3.') === 0) ? 'open' : ''; ?>>
                        <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-on-surface hover:text-[#22c55e] cursor-pointer py-2">
                            <span>Module 3</span>
                            <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                        </summary>
                        <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                            <a href="module3.php" class="block text-xs font-bold <?php echo (strpos($current_mod, '3.') === 0) ? 'text-[#22c55e] underline' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> mb-2">Go to Module 3 →</a>
                            <span class="block text-xs font-medium text-on-surface-variant">3.1 Power Conversion</span>
                            <span class="block text-xs font-medium text-on-surface-variant">3.2 Momentum to Electric</span>
                        </div>
                    </details>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 ev-container py-6 md:py-12 pb-32 transition-all duration-300 md:mr-80">
            <!-- Module 3: Driving Interactive Lab -->
            <section id="module-3-lab" class="mb-12 scroll-mt-24">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl md:text-headline-lg text-white mb-2">Module 3: Driving</h2>
                    </div>
                </div>

                <!-- Interactive Driving Lab Dashboard -->
                <div class="glass rounded-[2.5rem] p-8 border-white/5 mb-6 overflow-hidden relative group text-on-surface">
                    <div class="flex items-center justify-end mb-8">
                        <div id="kit-status-badge" class="flex items-center gap-3 bg-primary/10 border border-primary/20 px-4 py-2 rounded-xl">
                            <span class="relative flex h-2 w-2">
                                <span id="ping-dot-pulse" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span id="ping-dot" class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            <span id="kit-status-text" class="text-[10px] font-bold text-primary uppercase tracking-widest">Kit: Online</span>
                        </div>
                    </div>
                    
                    <div class="grid lg:grid-cols-3 gap-12 items-center">
                        <!-- Speedometer Gauge -->
                        <div class="flex flex-col items-center justify-center space-y-4">
                            <div class="relative w-64 h-64">
                                <!-- Gauge Background -->
                                <svg class="w-full h-full transform -rotate-90">
                                    <circle cx="128" cy="128" r="110" stroke="currentColor" stroke-width="12" fill="transparent" class="text-white/5" />
                                    <!-- Gauge Progress -->
                                    <circle id="speed-gauge" cx="128" cy="128" r="110" stroke="currentColor" stroke-width="12" fill="transparent" 
                                        stroke-dasharray="691" stroke-dashoffset="691"
                                        stroke-linecap="round"
                                        class="text-primary transition-all duration-500 ease-out shadow-[0_0_20px_rgba(34,197,94,0.5)]" />
                                </svg>
                                <!-- Center Text -->
                                <div class="absolute inset-0 flex flex-col items-center justify-center">
                                    <span id="speed-value" class="text-6xl font-black text-white font-mono italic">0</span>
                                    <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.3em]">km/h</span>
                                </div>
                                <!-- Decorative marks -->
                                <div class="absolute inset-0 pointer-events-none opacity-20">
                                    <div class="absolute top-4 left-1/2 -translate-x-1/2 w-0.5 h-3 bg-white"></div>
                                    <div class="absolute bottom-4 left-1/2 -translate-x-1/2 w-0.5 h-3 bg-white"></div>
                                    <div class="absolute left-4 top-1/2 -translate-y-1/2 w-3 h-0.5 bg-white"></div>
                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 w-3 h-0.5 bg-white"></div>
                                </div>
                            </div>
                            <p class="text-sm font-bold text-on-surface-variant uppercase tracking-widest italic">Vehicle Velocity</p>
                        </div>

                        <!-- System Status -->
                        <div class="lg:col-span-2 space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-6 rounded-3xl bg-ev-black/40 border border-white/10 backdrop-blur-md group-hover:border-primary/30 transition-all">
                                    <span class="block text-[9px] font-bold text-on-surface-variant uppercase tracking-widest mb-1">Throttle Input</span>
                                    <div class="flex items-end gap-2">
                                        <span id="pot-value" class="text-3xl font-bold text-white font-mono italic">0</span>
                                        <span class="text-[10px] font-bold text-on-surface-variant mb-1 uppercase italic">ADC</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-white/5 rounded-full mt-3 overflow-hidden">
                                        <div id="pot-bar" class="h-full bg-primary transition-all duration-300 w-0"></div>
                                    </div>
                                </div>
                                <div class="p-6 rounded-3xl bg-ev-black/40 border border-white/10 backdrop-blur-md group-hover:border-primary/30 transition-all">
                                    <span class="block text-[9px] font-bold text-on-surface-variant uppercase tracking-widest mb-1">Battery Stored</span>
                                    <div class="flex items-end gap-2">
                                        <span id="battery-stored-value" class="text-3xl font-bold text-white font-mono italic">75.0</span>
                                        <span class="text-[10px] font-bold text-on-surface-variant mb-1 uppercase italic">%</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-white/5 rounded-full mt-3 overflow-hidden">
                                        <div id="battery-stored-bar" class="h-full bg-green-400 transition-all duration-300 w-0" style="width: 75%"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dashboard Warning Panel -->
                            
                        </div>
                    </div>
                </div>

                <script>
                    // Speedometer Logic
                    const speedGauge = document.getElementById('speed-gauge');
                    const speedValue = document.getElementById('speed-value');
                    const potValue = document.getElementById('pot-value');
                    const potBar = document.getElementById('pot-bar');
                    const batteryStoredValue = document.getElementById('battery-stored-value');
                    const batteryStoredBar = document.getElementById('battery-stored-bar');
                    const kitStatusBadge = document.getElementById('kit-status-badge');
                    const kitStatusText = document.getElementById('kit-status-text');
                    const pingDot = document.getElementById('ping-dot');
                    const pingDotPulse = document.getElementById('ping-dot-pulse');

                    let batteryStored = 75.0; // Initialize battery to 75%
                    let prevSpeed = 0;

                    function updateSpeedometer(rawValue) {
                        // ADC is 0-4095, Map to 0-120 km/h
                        const maxADC = 4095;
                        const maxSpeed = 120;
                        const speed = Math.round((rawValue / maxADC) * maxSpeed);
                        
                        // Update Speedometer UI
                        speedValue.innerText = speed;
                        
                        // SVG Circumference = 2 * PI * R (R=110) ≈ 691
                        const circumference = 691;
                        const offset = circumference - (speed / maxSpeed) * circumference;
                        speedGauge.style.strokeDashoffset = offset;

                        // Update Pot UI
                        potValue.innerText = rawValue;
                        potBar.style.width = `${(rawValue / maxADC) * 100}%`;

                        // Calculate Battery Stored (Simulated based on delta speed)
                        if (speed > prevSpeed) {
                            // Deplete battery during acceleration
                            batteryStored -= (speed - prevSpeed) * 0.05;
                        } else if (speed < prevSpeed) {
                            // Recover energy into battery during deceleration (Regenerative braking)
                            batteryStored += (prevSpeed - speed) * 0.08;
                        }
                        
                        // Clamp battery between 0% and 100%
                        batteryStored = Math.max(0.0, Math.min(100.0, batteryStored));
                        prevSpeed = speed;

                        // Update Battery Stored UI
                        if (batteryStoredValue) {
                            batteryStoredValue.innerText = batteryStored.toFixed(1);
                        }
                        if (batteryStoredBar) {
                            batteryStoredBar.style.width = `${batteryStored}%`;
                        }
                    }

                    function applyOnlineState(isOnline) {
                        if (isOnline) {
                            if (kitStatusText) {
                                kitStatusText.textContent = 'Kit: Online';
                                kitStatusText.classList.remove('text-orange-500');
                                kitStatusText.classList.add('text-primary');
                            }
                            if (kitStatusBadge) {
                                kitStatusBadge.className = 'flex items-center gap-3 bg-primary/10 border border-primary/20 px-4 py-2 rounded-xl';
                            }
                            if (pingDot) {
                                pingDot.className = 'relative inline-flex rounded-full h-2 w-2 bg-primary';
                            }
                            if (pingDotPulse) {
                                pingDotPulse.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75';
                            }
                        } else {
                            if (kitStatusText) {
                                kitStatusText.textContent = 'Kit: Offline';
                                kitStatusText.classList.remove('text-primary');
                                kitStatusText.classList.add('text-orange-500');
                            }
                            if (kitStatusBadge) {
                                kitStatusBadge.className = 'flex items-center gap-3 bg-orange-500/10 border border-orange-500/20 px-4 py-2 rounded-xl';
                            }
                            if (pingDot) {
                                pingDot.className = 'relative inline-flex rounded-full h-2 w-2 bg-orange-500';
                            }
                            if (pingDotPulse) {
                                pingDotPulse.className = 'animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-500 opacity-75';
                            }
                            // Reset driving values to 0 when disconnected
                            updateSpeedometer(0);
                        }
                    }

                    // Polling Logic
                    async function fetchKitStatus() {
                        try {
                            const response = await fetch('get-kit-status.php');
                            const data = await response.json();
                            
                            if (data && !data.error) {
                                applyOnlineState(!!data.online);
                                if (data.online) {
                                    updateSpeedometer(data.potentiometer_value || 0);
                                }
                            } else {
                                applyOnlineState(false);
                            }
                        } catch (error) {
                            console.error('Fetch Error:', error);
                            applyOnlineState(false);
                        }
                    }

                    // Start Polling
                    setInterval(fetchKitStatus, 1000);
                    fetchKitStatus();
                </script>

                <div id="technical-content-3" class="space-y-6 mb-12 border-t border-white/5 pt-6">

                    <!-- 3.1 Power Conversion -->
                    <div id="power-conversion" class="scroll-mt-24 glass border-white/5 rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group/section">
                        <!-- Decorative background element -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover/section:bg-primary/10 transition-colors duration-700"></div>

                        <div class="w-full relative z-10 space-y-6">
                            <div>
                                <div class="text-primary text-[11px] font-bold uppercase tracking-widest mb-3">
                                    3.1 Power Conversion
                                </div>
                                <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed max-w-4xl">
                                    An electric vehicle's main battery pack stores massive amounts of DC electricity at an extremely high voltage (400V to 800V). However, the car's components cannot use this raw power all in the same way. The vehicle relies on two critical power electronics components to translate and distribute this energy: the Inverter and the DC/DC Converter.
                                </p>
                            </div>
                            
                            <!-- Inverter subtopic -->
                            <div class="grid lg:grid-cols-2 gap-6 items-center">
                                <div class="space-y-6">
                                    <div class="space-y-4">
                                        <h3 class="text-xl md:text-headline-lg text-white">Inverter</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            While the main battery pack stores energy as Direct Current (DC), the powerful traction motor requires Alternating Current (AC) to spin the wheels efficiently. The inverter bridges this gap by rapidly switching the power on and off thousands of times a second, transforming DC into three-phase AC power and precisely controlling the motor speed.
                                        </p>
                                        <div class="pt-2">
                                            <a href="https://industrial.panasonic.com/ww/ds/ss/technical/ap6" target="_blank" class="inline-flex items-center gap-2 text-xs md:text-sm text-primary hover:underline group/link">
                                                <span class="material-symbols-outlined text-sm transition-transform group-hover/link:translate-x-0.5">open_in_new</span>
                                                For more info, you can refer here
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-center">
                                    <img src="image/DCAC.png" alt="Inverter DC to AC" class="w-full h-auto object-contain">
                                </div>
                            </div>

                            <!-- DC/DC Converter subtopic -->
                            <div class="grid lg:grid-cols-2 gap-6 items-center">
                                <div class="flex items-center justify-center order-2 lg:order-1">
                                    <img src="image/flow.png" alt="DC/DC Converter" class="w-full h-auto object-contain">
                                </div>
                                <div class="space-y-6 order-1 lg:order-2">
                                    <div class="space-y-4">
                                        <h3 class="text-xl md:text-headline-lg text-white">DC/DC Converter</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            Electric vehicles rely on a dual-battery system to manage vastly different electrical demands. The massive high-voltage (HV) lithium-ion battery operates at 400V or higher to provide the extreme power needed to physically move the car. However, the vehicle's standard auxiliary systems, like the ECU (Electronic Control Unit), infotainment screens, power windows, and headlights cannot handle that intense power. They require a traditional 12V supply. The DC/DC converter acts as the essential, safely isolated bridge between these two systems.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3.2 Momentum to Electric -->
                    <div id="momentum-to-electric" class="scroll-mt-24 glass border-white/5 rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group/section">
                        <!-- Decorative background element -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-400/5 rounded-full blur-3xl group-hover/section:bg-blue-400/10 transition-colors duration-700"></div>

                        <div class="w-full relative z-10 space-y-6">
                            <div class="text-blue-400 text-[11px] font-bold uppercase tracking-widest mb-3">
                                3.2 Momentum to Electric
                            </div>

                            <div class="space-y-6">
                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">Regenerative Braking</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            In a traditional gas car, pressing the brake pedal forces friction pads onto the rotors, converting the vehicle's kinetic energy into useless heat. Electric vehicles, however, can recapture this lost energy. When you lift your foot off the accelerator, the forward momentum of the heavy car keeps the wheels spinning, which mechanically turns the electric motor.
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">Electric Motors</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            Electric motors and generators are essentially the exact same machine. By forcing the motor to spin without feeding it electricity, it begins to act as a generator. The physical act of generating this electricity creates electromagnetic resistance inside the motor, which drags on the wheels and slows the car down for most driving, though physical brake pads are still required for emergency stops and complete halts.
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">One-Pedal Driving</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            The electricity generated during this process is routed back to the main battery pack, directly extending the vehicle's driving range and improving overall efficiency. Depending on the EV's specific software, this allows for "one-pedal driving," where lifting off the accelerator applies strong regenerative braking automatically. Other EVs are programmed to coast smoothly, seamlessly blending the regenerative braking into the physical brake pedal only when you press it.
                                        </p>
                                    </div>

                                    <div class="p-5 rounded-3xl bg-blue-400/5 border border-blue-400/10 text-sm text-on-surface-variant italic leading-relaxed">
                                        <strong class="text-white">Take note:</strong> The motor generates AC power during regenerative braking. This AC must travel back through the inverter to be converted back into DC before it can be stored in the high-voltage lithium-ion battery.
                                    </div>
                                </div>
                                <div class="flex items-center justify-center pt-4">
                                    <img src="image/regen.png" alt="Regenerative Braking Flow" class="max-w-3xl w-full h-auto object-contain">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


            </section>
        </main>
    </div>

    <!-- Bottom Nav -->
    <div id="bottom-nav" class="fixed bottom-0 left-0 right-0 z-50 transition-all duration-300 md:mr-80">
        <div class="ev-container glass border-t border-white/5 py-5 flex items-center justify-between">
            <a href="<?php echo $prev_link; ?>" class="flex items-center gap-3 text-on-surface-variant hover:text-white transition-colors group">
                <div class="w-8 h-8 rounded-full glass flex items-center justify-center group-hover:border-primary/50 group-hover:text-primary transition-all">
                    <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
                </div>
                <span class="text-sm font-bold uppercase tracking-wider hidden sm:inline">Previous</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="<?php echo $next_link; ?>" class="bg-primary text-ev-black px-10 py-3 rounded-xl font-bold shadow-[0_0_20px_rgba(34,197,94,0.4)] hover:shadow-[0_0_35px_rgba(34,197,94,0.6)] hover:scale-105 active:scale-95 transition-all flex items-center gap-3">
                    <span class="text-sm tracking-wide">COMPLETE & CONTINUE</span>
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </a>
            </div>
        </div>
    </div>

    <script>

        // Universal Sidebar Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('main');
        const bottomNav = document.getElementById('bottom-nav');

        function toggleSidebar(isInitial = false) {
            const isCurrentlyCollapsed = sidebar.classList.contains('translate-x-full');
            const shouldCollapse = isInitial ? true : !isCurrentlyCollapsed;
            
            if (shouldCollapse) {
                sidebar.classList.add('translate-x-full');
                if (window.innerWidth >= 768) {
                    mainContent.classList.remove('md:mr-80');
                    bottomNav.classList.remove('md:mr-80');
                }
            } else {
                sidebar.classList.remove('translate-x-full');
                if (window.innerWidth >= 768) {
                    mainContent.classList.add('md:mr-80');
                    bottomNav.classList.add('md:mr-80');
                }
            }
        }

        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleSidebar();
        });

        // Initialize state
        toggleSidebar(true);
        
        // Add transitions after initial state is set
        setTimeout(() => {
            mainContent.classList.add('transition-all', 'duration-300');
            bottomNav.classList.add('transition-all', 'duration-300');
            sidebar.classList.add('transition-transform', 'duration-300');
        }, 50);

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768 && 
                !sidebar.contains(e.target) && 
                !menuToggle.contains(e.target) && 
                !sidebar.classList.contains('translate-x-full')) {
                sidebar.classList.add('translate-x-full');
            }
        });

        // Heartbeat to keep kit pairing active
        setInterval(() => {
            fetch('api/heartbeat.php').catch(err => console.error(err));
        }, 5000);
    </script>
</body>
</html>

