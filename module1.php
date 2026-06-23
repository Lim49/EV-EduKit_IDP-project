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
$prev_link = "module.php"; // Point back to Module 0
$next_link = "module2.php"; // Point forward to Module 2
$current_mod = '1.0';

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
    <title>Module 1: Home AC Charging - C2D EVKit</title>
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
                    <a href="<?php echo htmlspecialchars($active_module_link); ?>" class="flex items-center gap-2.5 text-sm font-bold <?php echo ($active_module_link === 'module1.php') ? 'text-[#22c55e]' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> transition-colors">
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
            <!-- Module 1: AC Charging Interactive Lab -->
            <section id="module-1-lab" class="mb-12 scroll-mt-24">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl md:text-headline-lg text-white mb-2">Module 1: Home AC Charging</h2>
                    </div>
                </div>

                <!-- Live Hardware Monitor (ESP32 Synchronized) -->
                <div class="glass rounded-[2.5rem] p-8 border-white/5 mb-6 overflow-hidden relative group">
                    <div class="flex items-center justify-end mb-4">
                        <div id="kit-status-badge" class="flex items-center gap-3 bg-primary/10 border border-primary/20 px-4 py-2 rounded-xl">
                            <span class="relative flex h-2 w-2">
                                <span id="ping-dot-pulse" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span id="ping-dot" class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            <span id="kit-status-text" class="text-[10px] font-bold text-primary uppercase tracking-widest">Kit: Online</span>
                        </div>
                    </div>

                    <!-- Horizontal Flow Diagram -->
                    <div class="relative px-4">
                        <!-- Connection Line Background -->
                        <div class="absolute top-1/2 left-0 w-full h-1 bg-white/10 -translate-y-1/2 rounded-full overflow-hidden">
                            <div id="active-flow-line" class="absolute top-0 left-0 h-full transition-all duration-700 w-0 animate-pulse"></div>
                        </div>

                        <!-- Technical Nodes -->
                        <div class="relative flex justify-between items-center w-full">
                            <!-- Transmission Tower -->
                            <div class="flex flex-col items-center gap-4 group/node">
                                <div id="node-tower" class="w-16 h-16 rounded-2xl bg-ev-black border-2 border-white/10 flex items-center justify-center transition-all duration-500 overflow-hidden relative group/img">
                                    <span class="relative material-symbols-outlined text-3xl text-on-surface-variant font-bold drop-shadow-md">factory</span>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Tower</p>
                                </div>
                            </div>

                            <!-- Substation -->
                            <div class="flex flex-col items-center gap-4 group/node">
                                <div id="node-substation" class="w-16 h-16 rounded-2xl bg-ev-black border-2 border-white/10 flex items-center justify-center transition-all duration-500 overflow-hidden relative group/img">
                                    <span class="relative material-symbols-outlined text-3xl text-on-surface-variant font-bold drop-shadow-md">electrical_services</span>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Substation</p>
                                </div>
                            </div>

                            <!-- Your Home -->
                            <div class="flex flex-col items-center gap-4 group/node">
                                <div id="node-home" class="w-16 h-16 rounded-2xl bg-ev-black border-2 border-white/10 flex items-center justify-center transition-all duration-500 overflow-hidden relative group/img">
                                    <span class="relative material-symbols-outlined text-3xl text-on-surface-variant font-bold drop-shadow-md">home</span>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Home</p>
                                </div>
                            </div>

                            <!-- OBC -->
                            <div class="flex flex-col items-center gap-4 group/node">
                                <div id="node-obc" class="w-16 h-16 rounded-2xl bg-ev-black border-2 border-white/10 flex items-center justify-center transition-all duration-500 overflow-hidden relative group/img">
                                    <span class="relative material-symbols-outlined text-3xl text-on-surface-variant group-hover/node:text-white font-bold drop-shadow-md">published_with_changes</span>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest group-hover/node:text-white">OBC</p>
                                </div>
                            </div>

                            <!-- Battery -->
                            <div class="flex flex-col items-center gap-4 group/node">
                                <div id="node-battery" class="w-16 h-16 rounded-2xl bg-ev-black border-2 border-white/10 flex items-center justify-center transition-all duration-500 overflow-hidden relative group/img">
                                    <span class="relative material-symbols-outlined text-3xl text-on-surface-variant group-hover/node:text-white font-bold drop-shadow-md">battery_charging_full</span>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest group-hover/node:text-white">Battery</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="technical-content-1" class="space-y-6 mb-12 border-t border-white/5 pt-6">

                    <!-- 1.1 The Power Grid -->
                    <div id="tower-to-substation" class="scroll-mt-24 glass rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group/section">
                        <!-- Decorative background element -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-primary/5 rounded-full blur-3xl group-hover/section:bg-primary/10 transition-colors duration-700"></div>

                        <div class="w-full relative z-10">
                            <div class="text-primary text-[11px] font-bold uppercase tracking-widest mb-3">
                                1.1 Transmission Tower to Substation
                            </div>
                            
                            <div class="grid lg:grid-cols-2 gap-6 items-center mb-8">
                                <div class="space-y-2">
                                    <h3 class="text-xl md:text-headline-lg text-white">Power Grid</h3>
                                    <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                        The power grid is the entire interconnected system responsible for <strong class="text-white">generating, transmitting, stepping down, and distributing</strong> electricity from where it is produced to where it is used. Electricity is generated at a mass scale at distant power plants, transmitted across vast distances, and finally distributed into homes. Together, these stages make up the power grid as a whole.
                                    </p>
                                </div>
                                <div class="flex items-center justify-center">
                                    <img src="image/PowerGrid.png" alt="Power Grid Overall Flow" class="w-full h-auto">
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="flex items-center gap-4">
                                    <div class="h-px flex-grow bg-white/10"></div>
                                    <h4 class="text-label-bold text-on-surface-variant uppercase tracking-[0.3em]">Grid Components</h4>
                                    <div class="h-px flex-grow bg-white/10"></div>
                                </div>
                                
                                <div class="grid md:grid-cols-2 gap-6">
                                    <!-- Component 1: Transmission Tower -->
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/card">
                                        <div class="flex items-center justify-between mb-2">
                                            <h3 class="text-lg md:text-headline-md text-white group-hover/card:text-primary transition-colors">Transmission Tower</h3>
                                            <span class="text-[10px] font-bold text-primary uppercase tracking-[0.2em] bg-primary/10 px-3 py-1 rounded-full border border-primary/20">Transmit</span>
                                        </div>
                                        <p class="text-sm text-on-surface-variant leading-relaxed">
                                            Within the transmission stage, these tall steel structures carry electricity at extremely high voltages — often between <strong class="text-white">132,000 and 500,000 volts</strong>. This high voltage minimizes energy loss over long distances.
                                        </p>
                                    </div>

                                    <!-- Component 2: Substation -->
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/card">
                                        <div class="flex items-center justify-between mb-2">
                                            <h3 class="text-lg md:text-headline-md text-white group-hover/card:text-primary transition-colors">Substation</h3>
                                            <span class="text-[10px] font-bold text-primary uppercase tracking-[0.2em] bg-primary/10 px-3 py-1 rounded-full border border-primary/20">Step Down</span>
                                        </div>
                                        <p class="text-sm text-on-surface-variant leading-relaxed">
                                            The raw voltage from the towers is too high for appliances. The substation acts as a <strong class="text-white">regional gatekeeper</strong>, stepping down electricity to around 11,000 to 33,000 volts for neighborhood lines.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 1.2 Substation to Home -->
                    <div id="substation-to-home" class="scroll-mt-24 glass rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group/section">
                        <!-- Decorative background element -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-blue-400/5 rounded-full blur-3xl group-hover/section:bg-blue-400/10 transition-colors duration-700"></div>

                        <div class="w-full relative z-10">
                            <div class="text-blue-400 text-[11px] font-bold uppercase tracking-widest mb-3">
                                1.2 Substation to Home
                            </div>
                            
                            <div class="grid lg:grid-cols-2 gap-6 items-center mb-8">
                                <div class="space-y-2">
                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">Local Distribution</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            Once stepped down at the substation, the electricity travels through neighbourhood power lines straight into the home's main consumer unit. In Malaysia, the standard voltage delivered is <strong class="text-white">230V single-phase at 50Hz</strong>.
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">AC Wallbox</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            When an EV owner installs an AC Wallbox at home, it taps straight into this existing domestic AC supply, safely drawing power under standard local utility residential tariffs.
                                        
                                        The wallbox itself does not generate or convert power, it just communicates with the vehicle to manage how much current is safely drawn.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-center">
                                    <img src="image/Home.png" alt="Home Distribution" class="w-full h-auto">
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div class="flex items-center gap-4">
                                    <div class="h-px flex-grow bg-white/10"></div>
                                    <h4 class="text-label-bold text-on-surface-variant uppercase tracking-[0.3em]">Why Homes Use AC</h4>
                                    <div class="h-px flex-grow bg-white/10"></div>
                                </div>
                                
                                <div class="grid md:grid-cols-3 gap-6">
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/item">
                                        <h5 class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-4 group-hover/item:translate-x-1 transition-transform">
                                            Efficiency
                                        </h5>
                                        <p class="text-xs text-on-surface-variant leading-relaxed">AC experiences significantly less power loss over distance compared to DC, making it ideal for national Transmission Towers.</p>
                                    </div>
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/item">
                                        <h5 class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-4 group-hover/item:translate-x-1 transition-transform">
                                            Flexibility
                                        </h5>
                                        <p class="text-xs text-on-surface-variant leading-relaxed">Voltage levels can be easily manipulated up or down using localized transformers to suit different needs.</p>
                                    </div>
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/item">
                                        <h5 class="text-xs font-bold text-blue-400 uppercase tracking-widest mb-4 group-hover/item:translate-x-1 transition-transform">
                                            Economical
                                        </h5>
                                        <p class="text-xs text-on-surface-variant leading-relaxed">It is significantly more cost-effective to generate and distribute at a massive national scale.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 1.3 EV Charger to OBC -->
                    <div id="charger-to-obc" class="scroll-mt-24 glass rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group/section">
                        <!-- Decorative background element -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-orange-400/5 rounded-full blur-3xl group-hover/section:bg-orange-400/10 transition-colors duration-700"></div>

                        <div class="w-full relative z-10">
                            <div class="text-orange-400 text-[11px] font-bold uppercase tracking-widest mb-3">
                                1.3 EV charger to OBC
                            </div>
                            
                            <div class="space-y-2 mb-16">
                                <!-- Plugging In - Top Section -->
                                <div class="space-y-2">
                                    <h3 class="text-xl md:text-headline-lg text-white">Plugging In</h3>
                                    <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                        When the driver connects the charging nozzle from the home wallbox to the vehicle's charging port, a physical and digital handshake takes place between the charger and the EV. This communication process verifies that both systems are compatible and ensures that charging can begin safely.
                                    </p>
                                </div>

                                <!-- Charging - Grid Section -->
                                <div class="grid lg:grid-cols-2 gap-6 items-center">
                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">Charging</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            Once the handshake is completed, electrical energy flows from the home wallbox through the charging cable and enters the vehicle via the charge port. The electricity is then delivered to the <strong class="text-white">On-Board Charger (OBC)</strong>, which is the internal component responsible for receiving incoming power before it can reach the battery. 
                                        </p>
                                    </div>
                                    <div class="flex items-center justify-center">
                                        <img src="image/OBC.png" alt="On-Board Charger" class="w-full h-auto object-contain">
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-px flex-grow bg-white/10"></div>
                                    <h4 class="text-label-bold text-on-surface-variant uppercase tracking-[0.3em]">Standard vs. Fast AC</h4>
                                    <div class="h-px flex-grow bg-white/10"></div>
                                </div>
                                
                                <div class="grid md:grid-cols-2 gap-6">
                                    <!-- Card 1: Single-Phase -->
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/card">
                                        <div class="flex items-center justify-between mb-2">
                                            <h3 class="text-lg md:text-headline-md text-white group-hover/card:text-orange-400 transition-colors">Single-Phase</h3>
                                            <span class="text-[10px] font-bold text-orange-400 uppercase tracking-[0.2em] bg-orange-400/10 px-3 py-1 rounded-full border border-orange-400/20">7.4 kW</span>
                                        </div>
					<p class="text-sm text-on-surface-variant leading-relaxed">
                                            Uses one live wire. The most common setup for terrace houses and apartments. Ideal for overnight charging.

                                        </p>

                                        <div class="flex items-center gap-2 text-[10px] text-orange-400 font-bold uppercase tracking-widest pt-2">
                                            <span class="material-symbols-outlined text-sm">schedule</span>
                                            6-10 Hours Full Charge
                                        </div>
                                    </div>

                                    <!-- Card 2: Three-Phase -->
                                    <div class="bg-white/5 p-5 rounded-2xl border border-white/10 space-y-2 hover:bg-white/[0.08] transition-all duration-300 group/card">
                                        <div class="flex items-center justify-between mb-2">
                                            <h3 class="text-lg md:text-headline-md text-white group-hover/card:text-orange-400 transition-colors">Three-Phase</h3>
                                            <span class="text-[10px] font-bold text-orange-400 uppercase tracking-[0.2em] bg-orange-400/10 px-3 py-1 rounded-full border border-orange-400/20">11-22 kW</span>
                                        </div>
                                        <p class="text-sm text-on-surface-variant leading-relaxed">
                                            Uses three live wires. Found in shopping malls, offices, and premium residences for much faster AC top-ups.
                                        </p>
                                        <div class="flex items-center gap-2 text-[10px] text-orange-400 font-bold uppercase tracking-widest pt-2">
                                            <span class="material-symbols-outlined text-sm">schedule</span>
                                            3-5 Hours Full Charge
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 1.4 OBC to Battery -->
                    <div id="obc-to-battery" class="scroll-mt-24 glass rounded-[2.5rem] p-6 md:p-8 relative overflow-hidden group/section">
                        <!-- Decorative background element -->
                        <div class="absolute -top-24 -right-24 w-64 h-64 bg-purple-400/5 rounded-full blur-3xl group-hover/section:bg-purple-400/10 transition-colors duration-700"></div>

                        <div class="w-full relative z-10">
                            <div class="text-purple-400 text-[11px] font-bold uppercase tracking-widest mb-3">
                                1.4 OBC to Battery
                            </div>
                            
                            <div class="grid lg:grid-cols-2 gap-6 items-center">
                                <div class="space-y-2">
                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white"> Conversion</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            Since an EV battery can only store energy as <strong class="text-white">Direct Current (DC)</strong>, the On-Board Charger (OBC) works as a high-speed translator. It takes the incoming AC electricity and converts it into a stable DC flow.
                                        </p>
                                        <p class="text-sm text-on-surface-variant italic leading-relaxed border-t border-white/5">
                                            Take note: Charging speed is limited by the OBC's rating, not the wallbox.
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <h3 class="text-xl md:text-headline-lg text-white">Final Storage</h3>
                                        <p class="text-on-surface-variant text-sm md:text-body-lg leading-relaxed">
                                            Once the OBC converts the power, it passes to the <strong class="text-white">High-Voltage Battery Pack</strong>. This is where the energy waits until you press the accelerator, starting the next transformation into motion.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-center">
                                    <img src="image/OBC-Battery.png" alt="OBC to Battery" class="w-full h-auto object-contain">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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
    </script>

    <!-- ── IoT Kit Node Sync ── -->
    <script>
    (function () {
        // Map node field names → DOM element IDs (module 1)
        const NODE_MAP = {
            node_grid:       'node-tower',
            node_substation: 'node-substation',
            node_home:       'node-home',
            node_obc:        'node-obc',
            node_battery:    'node-battery',
        };

        // Flow line width steps match cumulative LED lighting
        // 0 nodes active = 0%, grid only = 25%, +sub = 25%, +home = 50%, +obc = 75%, +battery = 100%
        function calcFlowWidth(s) {
            let active = [s.node_grid, s.node_substation, s.node_home, s.node_obc, s.node_battery]
                          .filter(Boolean).length;
            return [0, 25, 25, 50, 75, 100][Math.min(active, 5)];
        }

        const flowLine = document.getElementById('active-flow-line');
        const kitStatusBadge = document.getElementById('kit-status-badge');
        const kitStatusText = document.getElementById('kit-status-text');
        const pingDot = document.getElementById('ping-dot');
        const pingDotPulse = document.getElementById('ping-dot-pulse');

        function setNodeActiveState(elId, isActive, isRed = false) {
            const el = document.getElementById(elId);
            if (!el) return;
            const icon = el.querySelector('.material-symbols-outlined');
            const label = el.nextElementSibling ? el.nextElementSibling.querySelector('p') : null;

            if (isActive) {
                el.classList.remove('border-white/10');
                if (isRed) {
                    el.style.borderColor = '#ef4444'; // Red
                    el.style.boxShadow   = '0 0 20px rgba(239,68,68,0.6)';
                    if (icon) {
                        icon.style.color = '#ef4444';
                        icon.classList.remove('text-on-surface-variant');
                    }
                    if (label) {
                        label.style.color = '#ef4444';
                        label.classList.remove('text-on-surface-variant');
                    }
                } else {
                    el.style.borderColor = '#22c55e'; // Green
                    el.style.boxShadow   = '0 0 20px rgba(34,197,94,0.6)';
                    if (icon) {
                        icon.style.color = '#22c55e';
                        icon.classList.remove('text-on-surface-variant');
                    }
                    if (label) {
                        label.style.color = '#22c55e';
                        label.classList.remove('text-on-surface-variant');
                    }
                }
            } else {
                el.classList.add('border-white/10');
                el.style.borderColor = '';
                el.style.boxShadow   = '';
                if (icon) {
                    icon.style.color = '';
                    icon.classList.add('text-on-surface-variant');
                }
                if (label) {
                    label.style.color = '';
                    label.classList.add('text-on-surface-variant');
                }
            }
        }

        function applyNodeState(status) {
            const isOnline = !!status.online;

            // Update badge UI
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
            }

            // Sync nodes based on online status
            for (const [field, elId] of Object.entries(NODE_MAP)) {
                const isActive = isOnline && !!status[field];
                const isRed = elId !== 'node-battery';
                setNodeActiveState(elId, isActive, isRed);
            }

            // Update flow line
            if (flowLine) {
                const w = isOnline ? calcFlowWidth(status) : 0;
                flowLine.style.width = w + '%';
                if (!isOnline) {
                    flowLine.style.background = '';
                    flowLine.style.boxShadow = '';
                } else if (w <= 75) {
                    flowLine.style.background = '#ef4444'; // Red
                    flowLine.style.boxShadow = '0 0 15px rgba(239,68,68,0.8)';
                } else {
                    flowLine.style.background = 'linear-gradient(to right, #ef4444 75%, #22c55e 75%)'; // Red and Green
                    flowLine.style.boxShadow = '0 0 15px rgba(34,197,94,0.8)';
                }
            }
        }

        async function pollKitStatus() {
            try {
                const res  = await fetch('get-kit-status.php');
                const data = await res.json();
                if (data && !data.error) {
                    applyNodeState(data);
                } else {
                    applyNodeState({ online: false });
                }
            } catch (e) {
                applyNodeState({ online: false });
            }
        }

        setInterval(pollKitStatus, 2000);
        pollKitStatus();
    })();
    </script>
</body>
</html>


