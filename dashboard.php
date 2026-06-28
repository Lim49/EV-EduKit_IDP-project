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

// Fetch Module Progress
$modules = [
    1 => ['name' => 'Home AC charging', 'status' => 'Not Started', 'score' => 0],
    2 => ['name' => 'DC fast charging', 'status' => 'Not Started', 'score' => 0],
    3 => ['name' => 'Driving', 'status' => 'Not Started', 'score' => 0]
];

$stmt = $pdo->prepare("SELECT module_number, status, highest_score, questions_answered, questions_correct FROM module_progress WHERE user_id = ?");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $num = $row['module_number'];
    if (isset($modules[$num])) {
        $modules[$num]['status'] = $row['status'];
        if ($row['status'] === 'In Progress' && $row['questions_answered'] > 0) {
            $modules[$num]['score'] = round(($row['questions_correct'] / $row['questions_answered']) * 100);
            $modules[$num]['correct_cnt'] = $row['questions_correct'];
            $modules[$num]['answered_cnt'] = $row['questions_answered'];
        } else {
            $modules[$num]['score'] = $row['highest_score'];
            $modules[$num]['correct_cnt'] = null;
            $modules[$num]['answered_cnt'] = null;
        }
    }
}

// Fetch Detailed Quiz Log
$stmt = $pdo->prepare("SELECT id, module_number, score, total_questions, percentage, breakdown_data, completed_at FROM quiz_logs WHERE user_id = ? ORDER BY completed_at DESC");
$stmt->execute([$user_id]);
$quiz_logs = $stmt->fetchAll();

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
    <title>EV Learning Dashboard | C2D EVKit</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#15803d",
                        "primary-container": "#22c55e",
                        "on-primary-container": "#f0fdf4",
                        "ev-black": "#f8fafc",
                        "ev-dark": "#ffffff",
                        "ev-surface": "#ffffff",
                        "on-surface": "#0f172a",
                        "on-surface-variant": "#475569"
                    },
                    fontFamily: {
                        'sans': ['Inter', 'sans-serif'],
                        'display': ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .glass { @apply bg-[#020617]/85 backdrop-blur-md border border-white/10; }
            .glass-card { @apply bg-white border border-slate-200 shadow-xl; }
            .neon-border { @apply border-primary-container/30 hover:border-primary-container/60 transition-all duration-500; }
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        }
    </style>
</head>
<body class="bg-white text-on-surface antialiased font-sans selection:bg-primary-container/30 min-h-screen flex flex-col">

    <!-- TopNavBar Component -->
    <header class="bg-[#0F172A] sticky top-0 z-50 border-b border-white/10 transition-all duration-300 ease-in-out">
        <div class="flex justify-between items-center h-16 md:h-18 px-6 md:px-12 w-full mx-auto">
            <div class="flex items-center gap-4">
                <img alt="C2D EVKit Logo" class="h-8 md:h-10 w-auto object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHW7QX5Gcx9HyNP0fx6oc2vr5zPoe6axUJZ-rlQ2f_NjTDgN_9E7pl76bnkfDFjWLaRk9xBaNQi9MhgB6_gS1OCzDcwpcInyStp5fwN7nlcUgBuX3TjDnwLVP51vqj92KgNbqYeHWSTgXck6i1y831wzIrYE9pdkGb9N9AtjQW9Mlve5sHuHcJZ7R2vo8FgGIIG5AN1Tq4WRUd08HZau72BWpjjC8K_KBViLxzARq558ZtFmJZ_eC9lMD1xY3cxT2HNBpNZe5jYEs">
            </div>
            <div class="flex items-center gap-4 md:gap-8">
                <!-- User Controls -->
                <div class="flex items-center gap-4 sm:gap-6">
                    <button class="text-gray-400 hover:text-[#22c55e] transition-colors relative flex items-center justify-center">
                        <span class="material-symbols-outlined text-2xl">notifications</span>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-primary-container rounded-full border-2 border-[#0F172A]"></span>
                    </button>
                    <div class="flex items-center gap-3 group cursor-pointer">
                        <div class="w-10 h-10 rounded-full border-2 border-[#22c55e]/30 p-0.5 group-hover:border-[#22c55e] transition-all">
                            <img src="image/user.png" alt="Profile" class="w-full h-full rounded-full object-cover">
                        </div>
                        <span class="hidden lg:inline text-sm font-medium text-gray-400 group-hover:text-white transition-colors"><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                    <a href="logout.php" class="material-symbols-outlined text-gray-400 hover:text-red-500 transition-colors text-2xl" title="Logout">
                        logout
                    </a>
                    <!-- Collapsible Right Sidebar Menu Toggle -->
                    <button id="menu-toggle" class="p-2 text-gray-400 hover:text-[#22c55e] transition-colors flex items-center justify-center focus:outline-none">
                        <span class="material-symbols-outlined text-2xl">menu</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Collapsible Course Content Sidebar Navigator -->
    <aside id="sidebar" class="fixed top-16 right-0 z-40 w-80 h-[calc(100vh-64px)] glass border-l border-white/5 transform translate-x-full transition-transform duration-300 overflow-y-auto scrollbar-hide">
        <div class="p-6 pb-32 relative text-white">
            <!-- Navigation Links (Home, Module, Dashboard) -->
            <div class="space-y-3 mb-6 pb-6 border-b border-white/10">
                <a href="Home.php" class="flex items-center gap-2.5 text-sm font-bold text-gray-400 hover:text-[#22c55e] transition-colors">
                    <span class="material-symbols-outlined text-lg">home</span> Home
                </a>
                <a href="<?php echo htmlspecialchars($active_module_link); ?>" class="flex items-center gap-2.5 text-sm font-bold text-gray-400 hover:text-[#22c55e] transition-colors">
                    <span class="material-symbols-outlined text-lg">menu_book</span> Module
                </a>
                <a href="dashboard.php" class="flex items-center gap-2.5 text-sm font-bold text-[#22c55e] transition-colors">
                    <span class="material-symbols-outlined text-lg">dashboard</span> Dashboard
                </a>
                <a href="pair-kit.php" class="flex items-center gap-2.5 text-sm font-bold text-gray-400 hover:text-[#22c55e] transition-colors">
                    <span class="material-symbols-outlined text-lg">link</span> Pair Kit
                </a>
            </div>

            <h2 class="text-body-lg font-bold text-white mb-6">Course Content</h2>
            
            <div class="space-y-4 text-white">
                <!-- Module 0 -->
                <details class="group select-none">
                    <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-white hover:text-[#22c55e] cursor-pointer py-2">
                        <span>Module 0</span>
                        <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                    </summary>
                    <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                        <a href="module.php" class="block text-xs font-bold text-[#22c55e] hover:underline mb-2">Go to Module 0 →</a>
                        <span class="block text-xs font-medium text-gray-400">0.0 EV Fundamentals</span>
                    </div>
                </details>

                <!-- Module 1 -->
                <details class="group select-none">
                    <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-white hover:text-[#22c55e] cursor-pointer py-2">
                        <span>Module 1</span>
                        <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                    </summary>
                    <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                        <a href="module1.php" class="block text-xs font-bold text-[#22c55e] hover:underline mb-2">Go to Module 1 →</a>
                        <span class="block text-xs font-medium text-gray-400">1.1 Transmission Tower to Substation</span>
                        <span class="block text-xs font-medium text-gray-400">1.2 Substation to Home</span>
                        <span class="block text-xs font-medium text-gray-400">1.3 EV Charger to OBC</span>
                        <span class="block text-xs font-medium text-gray-400">1.4 OBC to Battery</span>
                    </div>
                </details>

                <!-- Module 2 -->
                <details class="group select-none">
                    <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-white hover:text-[#22c55e] cursor-pointer py-2">
                        <span>Module 2</span>
                        <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                    </summary>
                    <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                        <a href="module2.php" class="block text-xs font-bold text-[#22c55e] hover:underline mb-2">Go to Module 2 →</a>
                        <span class="block text-xs font-medium text-gray-400">2.1 Substation to DC station</span>
                        <span class="block text-xs font-medium text-gray-400">2.2 DC Charger to Battery</span>
                        <span class="block text-xs font-medium text-gray-400">2.3 Solar to DC-DC</span>
                        <span class="block text-xs font-medium text-gray-400">2.4 DC-DC to BESS</span>
                        <span class="block text-xs font-medium text-gray-400">2.5 BESS to Station</span>
                        <span class="block text-xs font-medium text-gray-400">2.6 Station to Battery</span>
                    </div>
                </details>

                <!-- Module 3 -->
                <details class="group select-none">
                    <summary class="flex items-center justify-between text-sm font-bold uppercase tracking-wider text-white hover:text-[#22c55e] cursor-pointer py-2">
                        <span>Module 3</span>
                        <span class="material-symbols-outlined text-sm transition-transform duration-300 group-open:rotate-180">expand_more</span>
                    </summary>
                    <div class="pl-4 mt-2 space-y-3 border-l border-white/10 ml-1.5">
                        <a href="module3.php" class="block text-xs font-bold text-[#22c55e] hover:underline mb-2">Go to Module 3 →</a>
                        <span class="block text-xs font-medium text-gray-400">3.1 Power Conversion</span>
                        <span class="block text-xs font-medium text-gray-400">3.2 Momentum to Electric</span>
                    </div>
                </details>
            </div>
        </div>
    </aside>

    <!-- Main Content Stage -->
    <main class="flex-1 w-full px-4 md:px-12 mt-16 md:mt-8">
        <!-- Analytics Header -->
        <header class="mb-4 md:mb-8">
            <h1 class="text-2xl md:text-5xl font-display font-bold text-on-surface">Dashboard</h1>
        </header>

        <div class="space-y-6 md:space-y-8 md:pl-12">
            <!-- Learning Journey Section -->
            <section>
                <div class="h-1 w-10 md:w-16 bg-[#22c55e] mb-3 md:mb-4"></div>
                <div class="flex items-center justify-between mb-4 md:mb-8">
                    <h2 class="text-base md:text-2xl font-display font-bold text-on-surface">Learning Journey</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-8 mb-4 md:mb-8">
                    <?php foreach ($modules as $num => $mod): ?>
                    <div class="group relative bg-white border border-slate-200 rounded-2xl md:rounded-[2rem] p-4 md:p-6 hover:border-primary-container shadow-sm hover:shadow-xl transition-all duration-500">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-end">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?php 
                                    echo $mod['status'] === 'Completed' ? 'bg-primary-container/10 text-primary' : 'bg-slate-100 text-slate-500'; 
                                ?>">
                                    <?php echo $mod['status']; ?>
                                </span>
                            </div>
                            
                            <div>
                                <p class="text-[10px] font-bold font-mono text-slate-400 uppercase tracking-[0.2em] mb-1">Module 0<?php echo $num; ?></p>
                                <h3 class="font-bold text-sm md:text-lg line-clamp-2 text-on-surface"><?php echo htmlspecialchars($mod['name']); ?></h3>
                            </div>

                            <div class="space-y-2">
                                <div class="flex justify-between items-end">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-none">Proficiency</p>
                                    <p class="text-base md:text-xl font-display font-bold leading-none text-primary">
                                        <?php 
                                        if ($mod['status'] === 'In Progress' && isset($mod['answered_cnt'])) {
                                            echo $mod['score'] . "% (" . $mod['correct_cnt'] . "/" . $mod['answered_cnt'] . ")";
                                        } else {
                                            echo $mod['score'] . "%";
                                        }
                                        ?>
                                    </p>
                                </div>
                                <!-- Modern Progress Bar -->
                                <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-primary-container transition-all duration-1000 ease-out" style="width: <?php echo $mod['score']; ?>%"></div>
                                </div>
                            </div>

                            <a href="module<?php echo $num; ?>.php" class="mt-1 text-center py-2 md:py-3 rounded-xl border border-slate-200 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant hover:bg-primary-container hover:text-white hover:border-transparent transition-all">Enter Module</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Performance History Section -->
            <section>
                <div class="h-1 w-10 md:w-16 bg-[#22c55e] mb-3 md:mb-4"></div>
                <h2 class="text-base md:text-2xl font-display font-bold mb-4 md:mb-8 text-on-surface">Performance History</h2>
                <div class="bg-white border border-slate-200 rounded-2xl md:rounded-[2.5rem] p-4 md:p-10 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto scrollbar-hide">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[10px] md:text-xs font-bold font-display text-black uppercase tracking-[0.1em] border-b-2 border-slate-200 bg-slate-50/30">
                                    <th class="py-3 md:py-5 px-3 md:px-6 rounded-tl-2xl">Date</th>
                                    <th class="py-3 md:py-5 px-3 md:px-6">Module</th>
                                    <th class="py-3 md:py-5 px-2 md:px-6 text-center">Score</th>
                                    <th class="py-3 md:py-5 px-3 md:px-6 text-right rounded-tr-2xl">Review</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($quiz_logs as $log): ?>
                                <tr class="group hover:bg-slate-50 transition-colors">
                                    <td class="py-3 md:py-6 px-3 md:px-6 text-[10px] md:text-xs font-mono text-slate-500 group-hover:text-on-surface transition-colors whitespace-nowrap">
                                        <?php echo date('M d, Y', strtotime($log['completed_at'])); ?>
                                    </td>
                                    <td class="py-3 md:py-6 px-3 md:px-6">
                                        <span class="font-bold text-xs md:text-sm text-on-surface">
                                            <?php 
                                                $mod_titles = [
                                                    1 => 'Mod 1: AC',
                                                    2 => 'Mod 2: DC',
                                                    3 => 'Mod 3: Drive'
                                                ];
                                                echo $mod_titles[$log['module_number']] ?? "Mod 0{$log['module_number']}"; 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="py-3 md:py-6 px-1 md:px-6 text-center">
                                        <div class="flex flex-col items-center gap-0.5">
                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                                <?php echo $log['percentage']; ?>%
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-medium"><?php echo $log['score']; ?>/<?php echo $log['total_questions']; ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 md:py-6 px-3 md:px-6 text-right">
                                        <button onclick='openReview(<?php echo json_encode($log); ?>)' class="px-2 md:px-4 py-1.5 md:py-2 rounded-xl border border-slate-200 text-[10px] font-bold uppercase tracking-widest text-on-surface-variant hover:bg-ev-black hover:text-primary hover:border-primary transition-all">
                                            View
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Review Modal -->
    <div id="review-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
        <div class="bg-white rounded-[2.5rem] w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col shadow-2xl">
            <div class="p-8 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 id="modal-title" class="text-2xl font-display font-bold text-on-surface">Quiz Review</h3>
                    <p id="modal-subtitle" class="text-sm text-on-surface-variant">Detailed breakdown of your performance.</p>
                </div>
                <button onclick="closeReview()" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-on-surface transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div id="modal-content" class="flex-1 overflow-y-auto p-8 space-y-6 scrollbar-hide">
                <!-- Questions will be injected here -->
            </div>
        </div>
    </div>

    <script>
        const quizDatabase = {
            1: [ // Module 1: AC charging
                {
                    q: "What is the main purpose of a substation?",
                    correctKey: 'B',
                    options: {
                        'A': "A. Increase voltage",
                        'B': "B. Reduce voltage for distribution",
                        'C': "C. Store electricity",
                        '-': "Skipped"
                    }
                },
                {
                    q: "What is the typical supply for a home EV charger?",
                    correctKey: 'A',
                    options: {
                        'A': "A. AC power",
                        'B': "B. DC power",
                        'C': "C. Battery power",
                        '-': "Skipped"
                    }
                },
                {
                    q: "What does an On-Board Charger do?",
                    correctKey: 'C',
                    options: {
                        'A': "A. Store electrical energy",
                        'B': "B. Spins the vehicle wheels",
                        'C': "C. Converts AC to DC power",
                        '-': "Skipped"
                    }
                }
            ],
            2: [ // Module 2: DC fast charging
                {
                    q: "What power supply does TNB provide to stations?",
                    correctKey: 'B',
                    options: {
                        'A': "A. Single-phase, 230V",
                        'B': "B. Three-phase, 400V",
                        'C': "C. DC Current, 12V",
                        '-': "Skipped"
                    }
                },
                {
                    q: "Where is AC converted to DC in fast charging?",
                    correctKey: 'A',
                    options: {
                        'A': "A. Inside the charging station kiosk",
                        'B': "B. Inside the vehicle",
                        'C': "C. Along the power cables",
                        '-': "Skipped"
                    }
                },
                {
                    q: "Solar panels produce:",
                    correctKey: 'C',
                    options: {
                        'A': "A. AC electricity",
                        'B': "B. Mechanical energy",
                        'C': "C. DC electricity",
                        '-': "Skipped"
                    }
                },
                {
                    q: "Why do we need a DC-DC Converter?",
                    correctKey: 'B',
                    options: {
                        'A': "A. To convert AC power into DC power",
                        'B': "B. To regulate solar voltage for the battery",
                        'C': "C. To convert electricity into chemical fluid",
                        '-': "Skipped"
                    }
                },
                {
                    q: "What does a BESS do at a station?",
                    correctKey: 'A',
                    options: {
                        'A': "A. It reduces the peak power demand",
                        'B': "B. It cools down the EV charging cable",
                        'C': "C. It changes the chemical reaction",
                        '-': "Skipped"
                    }
                },
                {
                    q: "Does DC fast charging use the vehicle's OBC?",
                    correctKey: 'B',
                    options: {
                        'A': "A. Yes",
                        'B': "B. No",
                        'C': "C. Sometimes",
                        '-': "Skipped"
                    }
                }
            ],
            3: [ // Module 3: Driving & Regen
                {
                    q: "When accelerator is pressed, energy flows:",
                    correctKey: 'A',
                    options: {
                        'A': "A. Battery -> Inverter -> Motor",
                        'B': "B. Motor -> Battery -> Inverter",
                        'C': "C. Motor -> Charger -> Battery",
                        '-': "Skipped"
                    }
                },
                {
                    q: "What happens when accelerator pedal is released?",
                    correctKey: 'A',
                    options: {
                        'A': "A. Regenerative braking begins",
                        'B': "B. Charging begins",
                        'C': "C. Battery disconnects",
                        '-': "Skipped"
                    }
                },
                {
                    q: "During regenerative braking, the motor acts as:",
                    correctKey: 'B',
                    options: {
                        'A': "A. Transformer",
                        'B': "B. Generator",
                        'C': "C. Battery",
                        '-': "Skipped"
                    }
                },
                {
                    q: "Does regen braking completely replace friction?",
                    correctKey: 'A',
                    options: {
                        'A': "A. No",
                        'B': "B. Yes",
                        'C': "C. Sometimes",
                        '-': "Skipped"
                    }
                },
                {
                    q: "Which EV component stores recovered energy?",
                    correctKey: 'A',
                    options: {
                        'A': "A. Battery Pack",
                        'B': "B. Motor",
                        'C': "C. Inverter",
                        '-': "Skipped"
                    }
                }
            ]
        };

        function openReview(log) {
            const modal = document.getElementById('review-modal');
            const content = document.getElementById('modal-content');
            const title = document.getElementById('modal-title');
            
            title.innerText = `Module 0${log.module_number} Review`;
            content.innerHTML = '';
            
            const questions = quizDatabase[log.module_number] || [];
            
            if (!log.breakdown_data) {
                if (questions.length === 0) {
                    content.innerHTML = '<div class="text-center py-12"><p class="text-slate-400 italic">No review details available for this module.</p></div>';
                } else {
                    const totalQ = log.total_questions || questions.length;
                    const scoreVal = log.score;
                    questions.forEach((qObj, index) => {
                        const isCorrect = (index < scoreVal);
                        const correctChar = qObj.correctKey || '';
                        const correctText = qObj.options[correctChar] || 'N/A';
                        
                        let answerDisplay = '';
                        if (isCorrect) {
                            answerDisplay = `<p class="text-sm text-emerald-700 font-medium">Your Answer: ${correctText}</p>`;
                        } else {
                            answerDisplay = `
                                <p class="text-sm text-red-700 font-medium">Result: Incorrect or Skipped</p>
                                <p class="text-sm text-emerald-700 font-medium">Correct Answer: ${correctText}</p>
                            `;
                        }

                        const qDiv = document.createElement('div');
                        qDiv.className = `p-6 rounded-3xl border ${isCorrect ? 'bg-emerald-50/50 border-emerald-100' : 'bg-red-50/50 border-red-100'} space-y-2`;
                        qDiv.innerHTML = `
                            <div class="flex items-start gap-4">
                                <span class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center font-bold text-xs ${isCorrect ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'}">
                                    ${index + 1}
                                </span>
                                <div class="space-y-1">
                                    <p class="font-bold text-on-surface">${qObj.q}</p>
                                    ${answerDisplay}
                                </div>
                                <span class="material-symbols-outlined ml-auto ${isCorrect ? 'text-emerald-500' : 'text-red-500'}">
                                    ${isCorrect ? 'check_circle' : 'cancel'}
                                </span>
                            </div>
                        `;
                        content.appendChild(qDiv);
                    });
                }
            } else {
                const data = typeof log.breakdown_data === 'string' ? JSON.parse(log.breakdown_data) : log.breakdown_data;
                data.forEach((item, index) => {
                    const qObj = questions[index] || { q: `Question ${index + 1}`, options: {} };
                    const choiceChar = item.choice || '-';
                    const answerText = qObj.options[choiceChar] || (choiceChar === '-' ? "Skipped" : choiceChar);
                    const isCorrect = !!item.correct;
                    const correctChar = qObj.correctKey || '';
                    const correctText = qObj.options[correctChar] || 'N/A';
                    
                    let answerDisplay = `<p class="text-sm ${isCorrect ? 'text-emerald-700' : 'text-red-700'} font-medium">Your Answer: ${answerText}</p>`;
                    if (!isCorrect) {
                        answerDisplay += `<p class="text-sm text-emerald-700 font-medium">Correct Answer: ${correctText}</p>`;
                    }
                    
                    const qDiv = document.createElement('div');
                    qDiv.className = `p-6 rounded-3xl border ${isCorrect ? 'bg-emerald-50/50 border-emerald-100' : 'bg-red-50/50 border-red-100'} space-y-2`;
                    qDiv.innerHTML = `
                        <div class="flex items-start gap-4">
                            <span class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center font-bold text-xs ${isCorrect ? 'bg-emerald-500 text-white' : 'bg-red-500 text-white'}">
                                ${index + 1}
                            </span>
                            <div class="space-y-1">
                                <p class="font-bold text-on-surface">${qObj.q}</p>
                                ${answerDisplay}
                            </div>
                            <span class="material-symbols-outlined ml-auto ${isCorrect ? 'text-emerald-500' : 'text-red-500'}">
                                ${isCorrect ? 'check_circle' : 'cancel'}
                            </span>
                        </div>
                    `;
                    content.appendChild(qDiv);
                });
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeReview() {
            const modal = document.getElementById('review-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        // Collapsible Right Sidebar Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('main');
        const footer = document.querySelector('footer');

        function toggleSidebar(isInitial = false) {
            const isCurrentlyCollapsed = sidebar.classList.contains('translate-x-full');
            const shouldCollapse = isInitial ? true : !isCurrentlyCollapsed;
            
            if (shouldCollapse) {
                sidebar.classList.add('translate-x-full');
                if (window.innerWidth >= 768) {
                    mainContent.classList.remove('md:mr-80');
                    footer.classList.remove('md:mr-80');
                }
            } else {
                sidebar.classList.remove('translate-x-full');
                if (window.innerWidth >= 768) {
                    mainContent.classList.add('md:mr-80');
                    footer.classList.add('md:mr-80');
                }
            }
        }

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleSidebar();
            });

            toggleSidebar(true);
            
            setTimeout(() => {
                mainContent.classList.add('transition-all', 'duration-300');
                footer.classList.add('transition-all', 'duration-300');
                sidebar.classList.add('transition-transform', 'duration-300');
            }, 50);

            document.addEventListener('click', (e) => {
                if (window.innerWidth < 768 && 
                    !sidebar.contains(e.target) && 
                    !menuToggle.contains(e.target) && 
                    !sidebar.classList.contains('translate-x-full')) {
                    sidebar.classList.add('translate-x-full');
                }
            });
        }

        // Heartbeat to keep kit pairing active
        setInterval(() => {
            fetch('api/heartbeat.php').catch(err => console.error(err));
        }, 5000);
    </script>

    <footer class="bg-[#020617] border-t border-white/10 py-3 w-full">
        <div class="px-8 md:px-12 w-full mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <!-- Left Side: Logo and Copyright -->
                <div class="flex flex-col md:flex-row items-center gap-6 text-center md:text-left">
                    <img alt="C2D EVKit Logo" class="h-8 w-auto object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHW7QX5Gcx9HyNP0fx6oc2vr5zPoe6axUJZ-rlQ2f_NjTDgN_9E7pl76bnkfDFjWLaRk9xBaNQi9MhgB6_gS1OCzDcwpcInyStp5fwN7nlcUgBuX3TjDnwLVP51vqj92KgNbqYeHWSTgXck6i1y831wzIrYE9pdkGb9N9AtjQW9Mlve5sHuHcJZ7R2vo8FgGIIG5AN1Tq4WRUd08HZau72BWpjjC8K_KBViLxzARq558ZtFmJZ_eC9lMD1xY3cxT2HNBpNZe5jYEs">
                    <p class="font-body-md text-xs text-gray-400">© 2025 C2D EVKit. All Rights Reserved.</p>
                </div>
                <!-- Right Side: Links -->
                <nav class="flex flex-wrap justify-center md:justify-end gap-x-8 gap-y-2">
                    <a class="font-body-md text-xs text-gray-400 hover:text-[#22c55e] transition-colors" href="#">Privacy Policy</a>
                    <a class="font-body-md text-xs text-gray-400 hover:text-[#22c55e] transition-colors" href="#">Terms of Service</a>
                    <a class="font-body-md text-xs text-gray-400 hover:text-[#22c55e] transition-colors" href="Home.php#contact">Contact</a>
                </nav>
            </div>
        </div>
    </footer>

</body>
</html>