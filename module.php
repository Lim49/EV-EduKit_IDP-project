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

// Dynamic Module Routing (Module 0 only)
$current_mod = '0.0';

// Navigation Logic
$prev_link = "Home.php";
$next_link = "module1.php";

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
    <title>Module 0: EV Fundamentals - C2D EVKit</title>
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
                        "body-lg": "18px",
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
        <!-- Sidebar Navigator -->
        <aside id="sidebar" class="fixed top-16 right-0 z-40 w-80 h-[calc(100vh-64px)] glass border-l border-white/5 transform translate-x-full transition-transform duration-300 overflow-y-auto scrollbar-hide">
            <div class="p-6 pb-32 relative text-white">
                <!-- Navigation Links (Home, Module, Dashboard) -->
                <div class="space-y-3 mb-6 pb-6 border-b border-white/10">
                    <a href="Home.php" class="flex items-center gap-2.5 text-sm font-bold text-on-surface-variant hover:text-[#22c55e] transition-colors">
                        <span class="material-symbols-outlined text-lg">home</span> Home
                    </a>
                    <a href="<?php echo htmlspecialchars($active_module_link); ?>" class="flex items-center gap-2.5 text-sm font-bold <?php echo ($active_module_link === 'module.php') ? 'text-[#22c55e]' : 'text-on-surface-variant hover:text-[#22c55e]'; ?> transition-colors">
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
            <!-- EV Fundamentals Basis -->
            <section id="ev-fundamentals" class="mb-4">
                <div class="mb-4">
                    <h2 class="text-headline-lg text-white">EV Fundamentals</h2>
                </div>
                
                <div class="grid md:grid-cols-2 gap-6 mb-4">
                    <div class="glass p-4 md:p-8 rounded-2xl md:rounded-3xl border border-white/5 hover:border-green-400/30 transition-all group">
                        <h3 class="text-lg md:text-2xl font-bold text-white mb-2 md:mb-4">
    What is an EV?
</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed">
                            An Electric Vehicle (EV) uses one or more electric motors for propulsion. Unlike traditional cars, EVs run entirely on electricity stored in rechargeable batteries, producing zero tailpipe emissions.
                        </p>
                    </div>

                    <div class="glass p-4 md:p-8 rounded-2xl md:rounded-3xl border border-white/5 hover:border-blue-400/30 transition-all group">
                        <h3 class="text-lg md:text-2xl font-bold text-white mb-2 md:mb-4">
   How it Works?
</h3>
                        <div class="flex items-center gap-2 mt-3 font-mono text-[10px] text-white/80">
                            <div class="px-2 py-1 bg-white/5 rounded">BATTERY</div>
                            <span class="material-symbols-outlined text-[12px] text-primary">double_arrow</span>
                            <div class="px-2 py-1 bg-white/5 rounded">INVERTER</div>
                            <span class="material-symbols-outlined text-[12px] text-primary">double_arrow</span>
                            <div class="px-2 py-1 bg-white/5 rounded">MOTOR</div>
                        </div>
                        <p class="text-on-surface-variant text-sm leading-relaxed mt-3">
                            The battery provides DC power. The inverter converts it to AC (for most motors) and controls speed. The motor then drives the wheels directly with instant torque.
                        </p>
                    </div>
                </div>

                <div class="glass p-4 md:p-8 rounded-2xl md:rounded-[2.5rem] border border-white/5">
                    <h3 class="text-lg md:text-2xl font-bold text-white mb-3 md:mb-4 flex items-center gap-3">
                        <div class="flex items-center gap-3 flex-1">
                            Types of Electric Vehicles
                        </div>
                        <a href="https://www.omazaki.co.id/en/types-of-electric-cars-and-working-principles/" target="_blank" class="text-xs text-on-surface-variant hover:text-primary transition-colors italic">[Source]</a>
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                        <!-- BEV -->
                        <div class="bg-ev-black/40 p-4 md:p-6 rounded-xl md:rounded-[2rem] border border-white/5 flex flex-col group hover:border-primary/30 transition-all duration-500">
                            <div class="w-full h-36 md:h-56 flex items-center justify-center mb-3 bg-white/5 rounded-xl overflow-hidden p-3 group-hover:bg-white/10 transition-colors">
                                <img src="image/BEVs.jpg" alt="BEV" class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-700">
                            </div>
                            <h4 class="text-base md:text-xl font-bold mb-2 text-white">Battery Electric Vehicles (BEVs)</h4>
                            <p class="text-xs md:text-sm text-on-surface-variant leading-relaxed italic">100% Electric. No internal combustion engine. Zero tailpipe emissions. Relies entirely on external plug-in charging.</p>
                        </div>

                        <!-- HEV -->
                        <div class="bg-ev-black/40 p-4 md:p-6 rounded-xl md:rounded-[2rem] border border-white/5 flex flex-col group hover:border-blue-400/30 transition-all duration-500">
                            <div class="w-full h-36 md:h-56 flex items-center justify-center mb-3 bg-white/5 rounded-xl overflow-hidden p-3 group-hover:bg-white/10 transition-colors">
                                <img src="image/HEVs.jpg" alt="HEV" class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-700">
                            </div>
                            <h4 class="text-base md:text-xl font-bold mb-2 text-white">Hybrid Electric Vehicles (HEVs)</h4>
                            <p class="text-xs md:text-sm text-on-surface-variant leading-relaxed italic">Uses both an engine and electric motor. The battery is charged via regenerative braking and the internal combustion engine, without an external plug.</p>
                        </div>

                        <!-- PHEV -->
                        <div class="bg-ev-black/40 p-4 md:p-6 rounded-xl md:rounded-[2rem] border border-white/5 flex flex-col group hover:border-orange-400/30 transition-all duration-500">
                            <div class="w-full h-36 md:h-56 flex items-center justify-center mb-3 bg-white/5 rounded-xl overflow-hidden p-3 group-hover:bg-white/10 transition-colors">
                                <img src="image/PHEVs.jpg" alt="PHEV" class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-700">
                            </div>
                            <h4 class="text-base md:text-xl font-bold mb-2 text-white">Plug-in Hybrid Electric Vehicles (PHEVs)</h4>
                            <p class="text-xs md:text-sm text-on-surface-variant leading-relaxed italic">A hybrid that can be plugged in. Offers significant electric-only range for city driving with an engine for long distances.</p>
                        </div>

                        <!-- FCEV -->
                        <div class="bg-ev-black/40 p-4 md:p-6 rounded-xl md:rounded-[2rem] border border-white/5 flex flex-col group hover:border-purple-400/30 transition-all duration-500">
                            <div class="w-full h-36 md:h-56 flex items-center justify-center mb-3 bg-white/5 rounded-xl overflow-hidden p-3 group-hover:bg-white/10 transition-colors">
                                <img src="image/FCEVs.jpg" alt="FCEV" class="w-full h-full object-contain transform group-hover:scale-110 transition-transform duration-700">
                            </div>
                            <h4 class="text-base md:text-xl font-bold mb-2 text-white">Fuel Cell Electric Vehicles (FCEVs)</h4>
                            <p class="text-xs md:text-sm text-on-surface-variant leading-relaxed italic">Uses Hydrogen to generate electricity onboard. FCEVs are ready for commercial sale to the public in Malaysia, and are already being actively deployed across government pilot fleets, most notably in pioneering states like Sarawak.</p>
                        </div>
                    </div>
                </div>

                <!-- Charging Modes Section -->
                <div class="mt-6 md:mt-12 glass p-4 md:p-8 rounded-2xl md:rounded-[2.5rem] border border-white/5">
                    <h3 class="text-lg md:text-2xl font-bold text-white mb-3 md:mb-4 flex items-center gap-3">
                        <div class="flex items-center gap-3 flex-1">
                            Charging Modes &amp; Standards
                        </div>
                        <a href="https://www.evguru.com.my/post/malaysia-ev-plug-type-compliance-standard-ac-dc-charging" target="_blank" class="text-xs text-on-surface-variant hover:text-primary transition-colors italic">[Source]</a>
                    </h3>

                    <!-- Top Centered Image & Left-Aligned Compliance -->
<div class="mb-10">

    <!-- Centered Image -->
    <div class="w-full max-w-4xl mx-auto bg-ev-black/40 rounded-[2.5rem] border border-white/5 p-6 overflow-hidden group mb-4">
        <img src="image/EVChargingMode.avif"
             alt="EV Charging Standards Table"
             class="w-full h-auto rounded-2xl object-contain group-hover:scale-[1.02] transition-transform duration-700">
    </div>

    <!-- Compliance Text -->
    <div class="max-w-4xl mx-auto px-2">
        <div class="flex items-center gap-2 mb-2 text-primary whitespace-nowrap">
            <span class="text-[10px] font-bold uppercase tracking-[0.2em]">
                Malaysia Compliance
            </span>
        </div>

        <p class="text-xs text-on-surface-variant leading-relaxed italic">
            Standardized charging interfaces are critical for safety and vehicle interoperability.
            Malaysia actively follows IEC Type 2 standards for AC and CCS2/CHAdeMO for DC infrastructure
            to ensure a seamless charging experience.
        </p>
    </div>

</div>

                    <!-- 2-Column Mode Details -->
                    <div class="grid md:grid-cols-2 gap-4 md:gap-8">
                        <!-- Left: Mode 2 & 3 -->
                        <div class="bg-white/5 p-4 md:p-8 rounded-xl md:rounded-[2rem] border border-white/5 hover:border-primary/20 transition-all group">
                            <div class="flex flex-col">
                                <h4 class="text-base md:text-xl font-bold text-white mb-2">Mode 2 &amp; 3 Charging</h4>
                                <div class="flex mb-3 md:mb-5">
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/5 rounded-lg border border-white/10 text-[10px] text-white font-bold uppercase tracking-widest">
                                        Type 2 Connector
                                    </div>
                                </div>
                                <p class="text-xs md:text-sm text-on-surface-variant leading-relaxed">
                                    These modes are for standard and fast AC charge. Type 2 connectors feature an inbuilt safety locking mechanism and can safely handle both single-phase (home) and three-phase (commercial) electrical power.
                                </p>
                            </div>
                        </div>

                        <!-- Right: Mode 4 -->
                        <div class="bg-white/5 p-4 md:p-8 rounded-xl md:rounded-[2rem] border border-white/5 hover:border-blue-400/20 transition-all group">
                            <div class="flex flex-col">
                                <h4 class="text-base md:text-xl font-bold text-white mb-2">Mode 4 Charging</h4>
                                <div class="flex flex-wrap gap-2 mb-3 md:mb-5">
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/5 rounded-lg border border-white/10 text-[10px] text-white font-bold uppercase tracking-widest">
                                        CCS Type 2
                                    </div>
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/5 rounded-lg border border-white/10 text-[10px] text-white font-bold uppercase tracking-widest">
                                        CHAdeMO
                                    </div>
                                </div>
                                <p class="text-xs md:text-sm text-on-surface-variant leading-relaxed">
                                    This mode delivers fast DC of 20kW and above straight to the battery, bypassing the onboard charger for maximum speed. Only these two specific connector types are approved for DC quick charging infrastructure in Malaysia.
                                </p>
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
