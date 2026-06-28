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

// --- PART 1: QUICK STATS ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM module_progress WHERE user_id = ? AND status = 'Completed'");
$stmt->execute([$user_id]);
$completed_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT module_number FROM module_progress WHERE user_id = ? AND status = 'Completed' ORDER BY updated_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$last_mod_num = $stmt->fetchColumn();

$module_names = [1 => 'Home AC Charging', 2 => 'DC Fast Charging', 3 => 'Driving'];
$last_completed_str = $last_mod_num ? ($module_names[$last_mod_num] ?? "Module " . $last_mod_num) : "None yet";

$stmt = $pdo->prepare("SELECT AVG(percentage) FROM quiz_logs WHERE user_id = ?");
$stmt->execute([$user_id]);
$avg_score = round($stmt->fetchColumn() ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM module_progress WHERE user_id = ? AND highest_score = 100");
$stmt->execute([$user_id]);
$mastered_count = $stmt->fetchColumn();

// Determine the active module link dynamically based on progress
$stmt_prog = $pdo->prepare("SELECT module_number, status FROM module_progress WHERE user_id = ?");
$stmt_prog->execute([$user_id]);
$progress_rows = $stmt_prog->fetchAll(PDO::FETCH_KEY_PAIR);

$m1_btn_link = 'module.php'; // default if nothing started
$m1_btn_text = 'Start Course';

if (!isset($progress_rows[1]) || $progress_rows[1] !== 'Completed') {
    if (isset($progress_rows[1]) && $progress_rows[1] === 'In Progress') {
        $m1_btn_link = 'module1.php';
        $m1_btn_text = 'Continue Module 1';
    } else {
        $has_any_progress = count($progress_rows) > 0;
        if ($has_any_progress) {
            $m1_btn_link = 'module1.php';
            $m1_btn_text = 'Start Module 1';
        } else {
            $m1_btn_link = 'module.php';
            $m1_btn_text = 'Start Course';
        }
    }
} else if (!isset($progress_rows[2]) || $progress_rows[2] !== 'Completed') {
    $m1_btn_link = 'module2.php';
    if (isset($progress_rows[2]) && $progress_rows[2] === 'In Progress') {
        $m1_btn_text = 'Continue Module 2';
    } else {
        $m1_btn_text = 'Start Module 2';
    }
} else if (!isset($progress_rows[3]) || $progress_rows[3] !== 'Completed') {
    $m1_btn_link = 'module3.php';
    if (isset($progress_rows[3]) && $progress_rows[3] === 'In Progress') {
        $m1_btn_text = 'Continue Module 3';
    } else {
        $m1_btn_text = 'Start Module 3';
    }
} else {
    // All modules completed
    $m1_btn_link = 'module.php';
    $m1_btn_text = 'Review Course';
}
?>
<!DOCTYPE html><html class="scroll-smooth" lang="en" style=""><head>

<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>C2D KIT | Home</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com" rel="preconnect">
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;family=JetBrains+Mono:wght@400&amp;family=Montserrat:wght@600;700&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
  tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        "colors": {
                "primary": "#006e2f",
                "ev-black": "#020617",
                "on-surface-variant": "#3d4a3d",
                "energy-yellow": "#FACC15",
                "eco-green-bright": "#4ADE80",
                "secondary": "#565e74"
        },
        "fontFamily": {
          "headline-xl": "Montserrat",
          "body-lg": "Inter",
          "headline-lg": "Montserrat",
          "label-bold": "Inter",
          "body-md": "Inter",
          "headline-md": "Montserrat"
        },
        "fontSize": {
          "headline-xl": "40px",
          "body-lg": "18px",
          "headline-lg": "32px",
          "label-bold": "14px",
          "body-md": "16px",
          "headline-md": "24px"
        },
        "spacing": {
          "gutter": "16px",
          "section-gap-lg": "80px"
        },
        "maxWidth": {
          "container-max": "1440px"
        }
      }
    }
  };
</script>
<style type="text/tailwindcss">
    @layer utilities {
      .font-headline-xl { font-family: theme('fontFamily.headline-xl'); }
      .text-headline-xl { font-size: theme('fontSize.headline-xl'); font-weight: 700; }
      
      .font-headline-lg { font-family: theme('fontFamily.headline-lg'); }
      .text-headline-lg { font-size: theme('fontSize.headline-lg'); font-weight: 700; }
      
      .font-headline-md { font-family: theme('fontFamily.headline-md'); }
      .text-headline-md { font-size: theme('fontSize.headline-md'); font-weight: 600; }

      .font-body-lg { font-family: theme('fontFamily.body-lg'); }
      .text-body-lg { font-size: theme('fontSize.body-lg'); }

      .font-body-md { font-family: theme('fontFamily.body-md'); }
      .text-body-md { font-size: theme('fontSize.body-md'); }

      .font-label-bold { font-family: theme('fontFamily.label-bold'); }
      .text-label-bold { font-size: theme('fontSize.label-bold'); font-weight: 600; }
      
      .fade-up {
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
      }
      .fade-up.active {
        opacity: 1;
        transform: translateY(0);
      }
      /* 3D Flip Card Utilities */
      .perspective-1000 { perspective: 1000px; }
      .backface-hidden { backface-visibility: hidden; -webkit-backface-visibility: hidden; }
      .rotate-y-180 { transform: rotateY(180deg); }
      
      .flip-card-inner {
        transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        transform-style: preserve-3d;
      }
      .flipped .flip-card-inner {
        transform: rotateY(180deg);
      }
      
      .premium-card {
        transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
      }
      .section-spacing {
        @apply py-10;
      }

      .section-heading {
        @apply mb-8;
      }

      .section-grid {
        @apply gap-8;
      }

      .card-padding {
        @apply p-6;
      } 
      .page-container {
        @apply max-w-[1440px] mx-auto px-6;
      }   
      .glass {
        @apply bg-[#020617]/85 backdrop-blur-md border border-white/10;
      }
      .scrollbar-hide::-webkit-scrollbar {
        display: none;
      }
      .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
      }
    }
    /* Slideshow Animations */
    @keyframes fade5_1 { 0%, 16% { opacity: 1; } 20%, 96% { opacity: 0; } 100% { opacity: 1; } }
    @keyframes fade5_2 { 0%, 16% { opacity: 0; } 20%, 36% { opacity: 1; } 40%, 100% { opacity: 0; } }
    @keyframes fade5_3 { 0%, 36% { opacity: 0; } 40%, 56% { opacity: 1; } 60%, 100% { opacity: 0; } }
    @keyframes fade5_4 { 0%, 56% { opacity: 0; } 60%, 76% { opacity: 1; } 80%, 100% { opacity: 0; } }
    @keyframes fade5_5 { 0%, 76% { opacity: 0; } 80%, 96% { opacity: 1; } 100% { opacity: 0; } }
  </style>
</head>
<body class="bg-white text-ev-black antialiased selection:bg-primary/30">
<header class="bg-[#0F172A] sticky top-0 z-50 border-b border-white/10 transition-all duration-300 ease-in-out">
<div class="flex justify-between items-center h-16 md:h-18 px-6 md:px-12 w-full mx-auto">
  <div class="flex items-center gap-4">
    <img alt="C2D EVKit Logo" class="h-8 md:h-10 w-auto object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHW7QX5Gcx9HyNP0fx6oc2vr5zPoe6axUJZ-rlQ2f_NjTDgN_9E7pl76bnkfDFjWLaRk9xBaNQi9MhgB6_gS1OCzDcwpcInyStp5fwN7nlcUgBuX3TjDnwLVP51vqj92KgNbqYeHWSTgXck6i1y831wzIrYE9pdkGb9N9AtjQW9Mlve5sHuHcJZ7R2vo8FgGIIG5AN1Tq4WRUd08HZau72BWpjjC8K_KBViLxzARq558ZtFmJZ_eC9lMD1xY3cxT2HNBpNZe5jYEs"/>
  </div>
  
  <div class="flex items-center gap-4 md:gap-8">
    <!-- User Controls -->
    <div class="flex items-center gap-4 sm:gap-6">
      <button class="text-gray-400 hover:text-[#22c55e] transition-colors relative flex items-center justify-center">
        <span class="material-symbols-outlined text-2xl">notifications</span>
        <span class="absolute top-0 right-0 w-2 h-2 bg-primary rounded-full border-2 border-[#0F172A]"></span>
      </button>
      <div class="flex items-center gap-3 group cursor-pointer">
        <div class="w-10 h-10 rounded-full border-2 border-[#22c55e]/30 p-0.5 group-hover:border-[#22c55e] transition-all">
          <img alt="Profile" class="w-full h-full rounded-full object-cover" src="image/user.png"/>
        </div>
        <span class="hidden lg:inline text-sm font-medium text-gray-400 group-hover:text-white transition-colors"><?php echo htmlspecialchars($full_name); ?></span>
      </div>
      <a class="material-symbols-outlined text-gray-400 hover:text-red-500 transition-colors text-2xl" href="logout.php" title="Logout">
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
            <a href="Home.php" class="flex items-center gap-2.5 text-sm font-bold text-[#22c55e] transition-colors">
                <span class="material-symbols-outlined text-lg">home</span> Home
            </a>
            <a href="<?php echo htmlspecialchars($m1_btn_link); ?>" class="flex items-center gap-2.5 text-sm font-bold text-gray-400 hover:text-[#22c55e] transition-colors">
                <span class="material-symbols-outlined text-lg">menu_book</span> Module
            </a>
            <a href="dashboard.php" class="flex items-center gap-2.5 text-sm font-bold text-gray-400 hover:text-[#22c55e] transition-colors">
                <span class="material-symbols-outlined text-lg">dashboard</span> Dashboard
            </a>
            <a href="pair-kit.php" class="flex items-center gap-2.5 text-sm font-bold text-gray-400 hover:text-[#22c55e] transition-colors">
                <span class="material-symbols-outlined text-lg">link</span> Pair Kit
            </a>
        </div>

        <h2 class="text-body-lg font-bold text-white mb-6">Course Content</h2>
        
        <div class="space-y-4">
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
<main class="relative z-10">
<!-- Hero Section (Slideshow Background) -->
<section class="relative pt-14 md:pt-16 pb-6 md:pb-10 bg-[#020617] border-b border-white/5 overflow-hidden min-h-[320px] md:min-h-[400px]">
<!-- Cinematic Slideshow Background -->
<div class="absolute inset-y-0 right-0 w-full lg:w-[60%] z-0 overflow-hidden bg-[#020617]">
  <div class="absolute inset-0 w-full h-full opacity-100 bg-[#020617]" style="animation: 25s ease 0s infinite normal none running fade5_1;">
    <img alt="EV Image 1" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBuLJdbA24V-Qw_MTr7-adyTwOVd1X4R9zL4HamMFKfg9Lo0tNIlv9yL5DrEcIFfHivkvJFd7_cuMg256cLeMJgffKUN_JlMFfjf6w3mJs-hojs_X--9M0PSj7zc6xqY8pW2bG-jpNIcZsCk7crcYYVPT7ob01mY8TIODbrpgcFrxi442Q1QsIXT3mbkVZ_n9wK_Zv5US9mNJzz-KNaty3raO1hi5E1Ad5-Nc1jt34EZhDjk6erXtFAlTNWfAv-YwQtEMwcHXJ1uEM">
  </div>
  <div class="absolute inset-0 w-full h-full opacity-0 bg-[#020617]" style="animation: 25s ease 0s infinite normal none running fade5_2;">
    <img alt="EV Image 2" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAebw5rZOAs2T2Sl9hFYeyD14H1886leNsq1pWTyDgYF5JFdnFSLjuX7ZDLwPf4b6cb11wCmaelQQfl3jz95_SP47sQlDq1CXpUvnZcvw-Wlba0J_MXEbQ54dfRh7c0FCq8LFD6ZKOV7-rzLEZ9DpH0oorCJlcnL2WSF3noZzin0if4hCWN0Wsw7D7I1Oj7V1LYSQMluS1k1TSrw9vqlEvC6M6-OfV0aw5gSxftv1Z0QTC8ee01MC8ckzUwxXGI_F0MnK6XHT-4Aco">
  </div>
  <div class="absolute inset-0 w-full h-full opacity-0 bg-[#020617]" style="animation: 25s ease 0s infinite normal none running fade5_3;">
    <img alt="EV Image 3" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBK1Dm5LeZk2pCe_Qkqwae17Gti8FPkDL7W1vD5v4uaA9EjAk-bTG3siEWwP5RZXtydB147nCvnQCk-IJv4vnF-op2krPxTMz5NXF3B1uHT0KPi1lgNWVpN-QrGg8ge5232I73oS3P80tOS6IL1oN2gocDVm4n111sjDS1ymt1_4WKkWYyrjyENQGF3EPvSgQ_4oz3PH8kwpO0vOFJtN-t7_kLOZcFt5RsgZ8GkM43-Agbz3G4ZFdp45eADrNceo8rfSnLGy824Rjk">
  </div>
  <div class="absolute inset-0 w-full h-full opacity-0 bg-[#020617]" style="animation: 25s ease 0s infinite normal none running fade5_4;">
    <img alt="EV Image 4" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBfbpNSfbjiGcLQ3QLRckhhvv3228GCgzNBqXPMROgmkZblnlR9V1SMK5IH0GKY81w_nFIOhhAY3LypS29JwxtzlIR2GsAdHyV3xvX2ncNYjQP6ZYPs3GfjfeITK5jq9Xz9A-y8NxKyBbeDYPwx7Jhczmat92UxL5aKvFc-_Gwf6Szy7UgIBql3lN718CtD0x7x0SVK8lceQBxIoDuBllSOwBOCv-UiTe-mXT5xg4yPJMjNX48MxzaX9PqEgreZotoKrBFODG3H9w4">
  </div>
  <div class="absolute inset-0 w-full h-full opacity-0 bg-[#020617]" style="animation: 25s ease 0s infinite normal none running fade5_5;">
    <img alt="EV Image 5" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD5m2b38JbYaOkroe5HO5YIZA1T3gv3_DqBLWB_WsxIBX39070D5hcV8ZwebHO2CsAqIl8V8g-sFbk2x1qTtQAcn-0d7DYip5GS5lzK2E6gmzw3MlEjcqoP00TA6_XRLbOYyUvQoD3e6EK5NOlNXQP42JLWGleRWXd2YI6whgl-QzBK5vp69QA1lyE9oGNEXM7fv5n-4CeZma4qpgIWNfzMYcH5_bPpLAAeg_DcufCQMDoz9riN5puFfUcWPJXBfxV4eDjvEBAz-Y4">
  </div>
</div>
<!-- Targeted Gradient Overlay -->
<div class="absolute inset-0 z-10 pointer-events-none bg-gradient-to-r from-[#020617] via-[#020617]/95 to-transparent lg:w-[75%]" style="background: linear-gradient(to right, rgb(2, 6, 23) 0%, rgb(2, 6, 23) 50%, transparent 100%);"></div>

<div class="page-container relative z-20">
<div class="grid lg:grid-cols-[1.5fr_0.7fr] gap-4 md:gap-6 items-center">
<div class="max-w-3xl fade-up active space-y-6">
<h1 class="font-headline-xl text-2xl md:text-headline-xl leading-tight text-white">Welcome, <span class="text-[#22c55e]"><?php echo htmlspecialchars($full_name); ?>!</span></h1>

<!-- Quick Stats Row -->
<div class="bg-[#020617]/60 backdrop-blur-md border border-white/10 rounded-2xl p-4 md:p-5 max-w-md">
<div class="grid grid-cols-3 gap-4">
<div>
<p class="text-[10px] uppercase text-gray-500 mb-1 font-mono">Last Completed</p>
<p class="text-sm font-bold text-white"><?php echo htmlspecialchars($last_completed_str); ?></p>
</div>
<div>
<p class="text-[10px] uppercase text-gray-500 mb-1 font-mono">Avg Score</p>
<p class="text-sm font-bold text-white"><?php echo $avg_score; ?>%</p>
</div>
<div>
<p class="text-[10px] uppercase text-gray-500 mb-1 font-mono">Mastered</p>
<p class="text-sm font-bold text-white"><?php echo $mastered_count; ?></p>
</div>
</div>
</div>

<div class="flex flex-wrap items-center gap-3">
<a class="inline-flex items-center justify-center px-5 md:px-10 py-2.5 md:py-3 bg-[#22c55e] text-white font-bold rounded-xl hover:shadow-[0_0_30px_rgba(34,197,94,0.3)] hover:scale-105 transition-all text-xs md:text-base whitespace-nowrap" href="<?php echo $m1_btn_link; ?>"><?php echo $m1_btn_text; ?></a>
<a class="inline-flex items-center justify-center px-5 md:px-8 py-2.5 md:py-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl text-white text-xs md:text-base font-bold transition-all shadow-sm" href="dashboard.php">View Detailed Stats</a>
</div>
</div>

<!-- Right Column remains empty on desktop so slideshow is fully visible -->
<div class="hidden lg:block"></div>
</div>
</div>
</section>

<!-- platform guideline section is integrated below, now we place the Overview and EV Trends (White background) -->
<section class="py-10 bg-white border-b border-gray-100" id="overview-trends">
  <div class="page-container">
    <!-- Short Overview -->
    <div class="max-w-3xl mb-12 fade-up active">
      <div class="h-1 w-10 md:w-16 bg-[#22c55e] mb-3 md:mb-4"></div>
      <h2 class="font-headline-lg text-xl md:text-headline-lg text-ev-black mb-3">Overview</h2>
      <p class="text-sm md:text-base text-secondary leading-relaxed">
        Electric vehicles (EVs) are rapidly transforming the transportation industry and driving the transition toward a cleaner, more sustainable future. As nations worldwide work to reduce carbon emissions and adopt renewable energy solutions, the demand for EV knowledge and skills continues to grow.
      </p>
    </div>

    <!-- EV Revolution / Trends -->
    <div class="fade-up active">
      <div class="mb-8 text-left">
        <span class="text-[#22c55e] font-label-bold text-xs uppercase tracking-wider block mb-2">WHY EV MATTERS NOW</span>
        <h2 class="font-headline-lg text-xl md:text-headline-lg text-ev-black mb-4">EV Revolution</h2>
      </div>
      
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Card 1 -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col premium-card hover:-translate-y-1 hover:shadow-xl group transition-all duration-300">
          <div class="h-32 md:h-40 overflow-hidden">
            <img alt="Environmental Impact" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAe9szonPgwp6Ff-T_cEGf2d-VPMPKDQl_AhcCN9QRyLFE2CxJXYGgeEmNoCRaaeEBJst_IK-O-kPAxvkFgW9Yml5XZD3R6PDhStJ959PzgemK2EClyFhcUfG3u58PDThUfijs5DQOld6V2nqxMTPe3rOO0el8s3KcOpS4Aud4Kw24m2RdOS8hplJc3CmN0Buw8HDxPYbL6paJdJ6EaIxfVv9ZbnIzcz6AYHW1jgJNS9nJnsZnnChJMesgBmKoyqqndBqIb-u-wON0">
          </div>
          <div class="flex-grow flex flex-col p-5">
            <h3 class="font-headline-md text-base text-ev-black mb-2 font-bold">Environmental Impact</h3>
            <p class="text-xs md:text-sm text-secondary leading-relaxed">EVs produce zero tailpipe emissions, allowing their lifetime carbon footprint to be up to 70% lower than traditional vehicles.</p>
          </div>
        </div>
        <!-- Card 2 -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col premium-card hover:-translate-y-1 hover:shadow-xl group transition-all duration-300">
          <div class="h-32 md:h-40 overflow-hidden">
            <img alt="Economic Advantage" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCI-cKqizAfH2thk0hL_z9nnvbJUeO7NISdkACRh0FNTERZqgzbhy1vanUBk21GEb4ub8EpQiwTxnagTWb0b_zV4rKQWkIklnE_fMvM8cExJOBWP5TtL_1kzJdadzIbBVhQpsg7Sh2cmh9IrEpx7hdcnfI5QZcRIL2HWSw1i_ZMd1xWV2RV3fNTP3Fr2RFRjww8_AYJmxF0RGzoultni7R866pn_xvFjH1cZ6InIa3H881GcuUwpbF5WrHtApSjJKl3MFb6sOcBwMU">
          </div>
          <div class="flex-grow flex flex-col p-5">
            <h3 class="font-headline-md text-base text-ev-black mb-2 font-bold">Economic Advantage</h3>
            <p class="text-xs md:text-sm text-secondary leading-relaxed">Charging at home reduces running costs to ~RM0.10/km with significantly lower maintenance demands.</p>
          </div>
        </div>
        <!-- Card 3 -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col premium-card hover:-translate-y-1 hover:shadow-xl group transition-all duration-300">
          <div class="h-32 md:h-40 overflow-hidden">
            <img alt="Malaysia's EV Push" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDZHlMS1oRDfJoN8L9p4VwoTl6dYlfGsKo2bqAPRs9x5-PMRKDwgGPLAX2eG4VVsye6Elnj8yAxVhn1NEnmPS2laQ8ztfovyfY7Qc6R6sU0dqjGLUvdov9f_nXADrnbRQWFbKoxo8PKbaGNvQHWhH7eN_2LBR_xDbdOrMoIttDuLazBIuDgBNGkCuCY9nIMaFHnKimw0ZiwWyyiWoKNkPpiJYwJfLPTt88AYY9JB8sOHcF_hlkh1WElV2Ct2rVCM9Uh-_NqsWYLC_o">
          </div>
          <div class="flex-grow flex flex-col p-5">
            <h3 class="font-headline-md text-base text-ev-black mb-2 font-bold">Malaysia's EV Push</h3>
            <p class="text-xs md:text-sm text-secondary leading-relaxed">Sales grew over 10,000% in four years, surging to over 30,000 units expected by 2025.</p>
          </div>
        </div>
        <!-- Card 4 -->
        <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col premium-card hover:-translate-y-1 hover:shadow-xl group transition-all duration-300">
          <div class="h-32 md:h-40 overflow-hidden">
            <img alt="Technology Is Ready" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDe6wuCKINC5Fo7Nau2UXbFgLFt69w4JTQqZXPrxVamOHeIXmVsWxTm0MLv4XNNoFCYxPuP3ffoD7RhP65lUggi1E9RUTh4ghLiwZyieQVHWzjRFMbyLiL2U0cINm39eq-yZk5wJryQu64we5husrm1I21tHMelPmwMwTINaPrEHPteuLUd1FHAv3QhGyJz3qO7XwRzRVmo0y64TAecAQNHsTtC5LTb_CNGUx_iVqPO9gR-3dRS6Z9bMFfgVtuxwgvsZ4__oeDBbSY">
          </div>
          <div class="flex-grow flex flex-col p-5">
            <h3 class="font-headline-md text-base text-ev-black mb-2 font-bold">Technology Is Ready</h3>
            <p class="text-xs md:text-sm text-secondary leading-relaxed">Modern EVs exceed 500km range and fast-charge 20-80% in under 20 minutes.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- About the Kit Section (Dark Background as requested) -->
<section class="py-10 bg-[#020617] border-b border-white/5 text-white" id="about">
  <div class="page-container">
    <div class="fade-up active mb-8">
      <div class="h-1 w-10 md:w-16 bg-[#22c55e] mb-3 md:mb-4"></div>
      <h2 class="font-headline-lg text-xl md:text-headline-lg text-white mb-4">About the Kit</h2>
      <p class="text-sm md:text-base text-gray-400 leading-relaxed max-w-3xl">
        Understanding EV technology is essential for modern STEM education. Through hands-on learning, explore how energy is charged, converted, and recovered.
      </p>
    </div>

    <!-- Target Audience -->
    <div class="fade-up active mb-12">
      <h3 class="font-headline-md text-lg text-center text-white mb-6">Target Audience</h3>
      <div class="grid md:grid-cols-2 gap-6">
        <div class="flex items-center gap-4 p-6 bg-white/5 border border-white/10 rounded-2xl border-l-4 border-[#22c55e] premium-card hover:translate-x-2 transition-all duration-300">
          <div class="bg-[#22c55e]/10 p-3 rounded-full text-[#22c55e] flex items-center justify-center w-12 h-12 md:w-14 md:h-14 shrink-0">
            <img alt="Learner Icon" class="w-8 h-8 md:w-10 md:h-10 object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAzzFbd8W-LQsFKRKF-aqHI7TVmanZHUQrOzU27dXjbgSG9fVfTz3VwdKHpQlIkPmcv6wEe-FsjuZE2F2YU7Z3oT0ML8jxeYyxtQ8Ep9Og5KEsj7eAG_0376vCW3YrU1VBRubxfzb6W0sFOMiUZvl5h9AQgLJqPjo5PDT_q5mjBmY8iMx71c4td9GpojQjqnkSQ8qh2wKOxajE-MVKzWVxZwwUTNmrl80U2aiX0XHC-b0DkuIWWQZgeivnexnyktMxvioL9YXfPqHY">
          </div>
          <div>
            <h3 class="font-headline-md text-base text-white font-bold">For Learners</h3>
            <p class="text-xs md:text-sm text-gray-400">Explore. Experiment. Understand.</p>
          </div>
        </div>
        <div class="flex items-center gap-4 p-6 bg-white/5 border border-white/10 rounded-2xl border-l-4 border-[#22c55e] premium-card hover:translate-x-2 transition-all duration-300">
          <div class="bg-[#22c55e]/10 p-3 rounded-full text-[#22c55e] flex items-center justify-center w-12 h-12 md:w-14 md:h-14 shrink-0">
            <img alt="Instructor Icon" class="w-8 h-8 md:w-10 md:h-10 object-contain" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD3drfFRoaWR4whc6OuJGuocRe2geQv3PWWmmoKuNf6mjBXNbI9Cc9v4MJc_4VOxd_z2CLJq6kdud0uV3e3vUn6eRq0YqJjPbJtINSQ2iFgx2--x8BJfC2BreCh-bqgJCqgJqzQF7ja8vcZCs9o7C1u8Fji48i8g3LHwxENVF-8vu_Dag6R8e6rFerir8Hukimr1y-zzDWmeb0W8c7vJjgWF2y1VkCqwWjjXBC7m-knZ8oWAQnBpKlqkEKFKJPXvm9nmjlBVfxF6UE">
          </div>
          <div>
            <h3 class="font-headline-md text-base text-white font-bold">For Instructors</h3>
            <p class="text-xs md:text-sm text-gray-400">Curriculum-Aligned Classroom Activity.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Key Features -->
    <div class="fade-up active">
      <h3 class="font-headline-md text-lg text-center text-white mb-2">Key Features</h3>
      <p class="text-center text-[10px] md:text-xs italic text-gray-500 mb-8">[Tap each card for more details]</p>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4" id="key-features">
        <!-- Card 1 -->
        <div class="perspective-1000 h-40 md:h-48 cursor-pointer group hover:-translate-y-1 transition-all duration-300" onclick="this.classList.toggle('flipped')">
          <div class="relative w-full h-full flip-card-inner">
            <div class="absolute w-full h-full backface-hidden bg-white/5 border border-white/10 rounded-2xl flex flex-col items-center justify-center p-4 text-center shadow-sm group-hover:border-[#22c55e]/50 transition-colors">
              <span class="material-symbols-outlined text-[#22c55e] text-2xl md:text-3xl mb-3">monitor</span>
              <h4 class="font-headline-md text-xs md:text-sm text-white font-semibold">TFT Touchscreen</h4>
            </div>
            <div class="absolute w-full h-full backface-hidden bg-[#22c55e] border border-[#22c55e] text-white rounded-2xl flex flex-col items-center justify-center p-4 text-center rotate-y-180 shadow-md">
              <p class="text-[10px] md:text-xs leading-relaxed">Use to select learning modules and answer quizzes directly on the kit.</p>
            </div>
          </div>
        </div>
        <!-- Card 2 -->
        <div class="perspective-1000 h-40 md:h-48 cursor-pointer group hover:-translate-y-1 transition-all duration-300" onclick="this.classList.toggle('flipped')">
          <div class="relative w-full h-full flip-card-inner">
            <div class="absolute w-full h-full backface-hidden bg-white/5 border border-white/10 rounded-2xl flex flex-col items-center justify-center p-4 text-center shadow-sm group-hover:border-[#22c55e]/50 transition-colors">
              <span class="material-symbols-outlined text-[#22c55e] text-2xl md:text-3xl mb-3">school</span>
              <h4 class="font-headline-md text-xs md:text-sm text-white font-semibold">LED Strip Vis.</h4>
            </div>
            <div class="absolute w-full h-full backface-hidden bg-[#22c55e] border border-[#22c55e] text-white rounded-2xl flex flex-col items-center justify-center p-4 text-center rotate-y-180 shadow-md">
              <p class="text-[10px] md:text-xs leading-relaxed">Trace electrical pathways using color-coded animations.</p>
            </div>
          </div>
        </div>
        <!-- Card 3 -->
        <div class="perspective-1000 h-40 md:h-48 cursor-pointer group hover:-translate-y-1 transition-all duration-300" onclick="this.classList.toggle('flipped')">
          <div class="relative w-full h-full flip-card-inner">
            <div class="absolute w-full h-full backface-hidden bg-white/5 border border-white/10 rounded-2xl flex flex-col items-center justify-center p-4 text-center shadow-sm group-hover:border-[#22c55e]/50 transition-colors">
              <span class="material-symbols-outlined text-[#22c55e] text-2xl md:text-3xl mb-3">settings_input_component</span>
              <h4 class="font-headline-md text-xs md:text-sm text-white font-semibold">Throttle Pedal</h4>
            </div>
            <div class="absolute w-full h-full backface-hidden bg-[#22c55e] border border-[#22c55e] text-white rounded-2xl flex flex-col items-center justify-center p-4 text-center rotate-y-180 shadow-md">
              <p class="text-[10px] md:text-xs leading-relaxed">Use an integrated potentiometer to simulate acceleration.</p>
            </div>
          </div>
        </div>
        <!-- Card 4 -->
        <div class="perspective-1000 h-40 md:h-48 cursor-pointer group hover:-translate-y-1 transition-all duration-300" onclick="this.classList.toggle('flipped')">
          <div class="relative w-full h-full flip-card-inner">
            <div class="absolute w-full h-full backface-hidden bg-white/5 border border-white/10 rounded-2xl flex flex-col items-center justify-center p-4 text-center shadow-sm group-hover:border-[#22c55e]/50 transition-colors">
              <span class="material-symbols-outlined text-[#22c55e] text-2xl md:text-3xl mb-3">quiz</span>
              <h4 class="font-headline-md text-xs md:text-sm text-white font-semibold">Built-In Quiz</h4>
            </div>
            <div class="absolute w-full h-full backface-hidden bg-[#22c55e] border border-[#22c55e] text-white rounded-2xl flex flex-col items-center justify-center p-4 text-center rotate-y-180 shadow-md">
              <p class="text-[10px] md:text-xs leading-relaxed">Reinforce core concepts on the spot with an instant audio buzzer.</p>
            </div>
          </div>
        </div>
        <!-- Card 5 -->
        <div class="perspective-1000 h-40 md:h-48 cursor-pointer group hover:-translate-y-1 transition-all duration-300" onclick="this.classList.toggle('flipped')">
          <div class="relative w-full h-full flip-card-inner">
            <div class="absolute w-full h-full backface-hidden bg-white/5 border border-white/10 rounded-2xl flex flex-col items-center justify-center p-4 text-center shadow-sm group-hover:border-[#22c55e]/50 transition-colors">
              <span class="material-symbols-outlined text-[#22c55e] text-2xl md:text-3xl mb-3">insights</span>
              <h4 class="font-headline-md text-xs md:text-sm text-white font-semibold">Live Telemetry</h4>
            </div>
            <div class="absolute w-full h-full backface-hidden bg-[#22c55e] border border-[#22c55e] text-white rounded-2xl flex flex-col items-center justify-center p-4 text-center rotate-y-180 shadow-md">
              <p class="text-[10px] md:text-xs leading-relaxed">Match physical responses with explanations on the website.</p>
            </div>
          </div>
        </div>
        <!-- Card 6 -->
        <div class="perspective-1000 h-40 md:h-48 cursor-pointer group hover:-translate-y-1 transition-all duration-300" onclick="this.classList.toggle('flipped')">
          <div class="relative w-full h-full flip-card-inner">
            <div class="absolute w-full h-full backface-hidden bg-white/5 border border-white/10 rounded-2xl flex flex-col items-center justify-center p-4 text-center shadow-sm group-hover:border-[#22c55e]/50 transition-colors">
              <span class="material-symbols-outlined text-[#22c55e] text-2xl md:text-3xl mb-3">database</span>
              <h4 class="font-headline-md text-xs md:text-sm text-white font-semibold">Analytics</h4>
            </div>
            <div class="absolute w-full h-full backface-hidden bg-[#22c55e] border border-[#22c55e] text-white rounded-2xl flex flex-col items-center justify-center p-4 text-center rotate-y-180 shadow-md">
              <p class="text-[10px] md:text-xs leading-relaxed">Save quiz histories to track progress and correct mistakes.</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Demo Video Sub-Section -->
    <div class="fade-up active mt-16 mb-12">
      <h3 class="font-headline-md text-lg text-center text-white mb-6">Demo video</h3>
      <div class="flex justify-center">
        <div class="w-full max-w-3xl aspect-video rounded-3xl overflow-hidden shadow-2xl border border-white/10">
          <iframe class="w-full h-full" src="https://www.youtube.com/embed/fkE3xXpfQMQ" title="C2D EVKit Guideline" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
        </div>
      </div>
    </div>

    <!-- Curriculum Sub-Section -->
    <div class="fade-up active mt-16" id="curriculum">
      <h3 class="font-headline-md text-lg text-center text-white mb-8">Curriculum</h3>
      
      <div class="grid lg:grid-cols-[1.3fr_0.9fr] gap-6 text-white text-left">
        <!-- Left: Outcomes -->
        <div class="space-y-6 flex flex-col">
          <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
            <h3 class="font-headline-md text-lg text-white mb-6 flex items-center gap-2">
              <span class="material-symbols-outlined text-[#22c55e]">verified</span>
              Learning Outcomes
            </h3>
            <ul class="space-y-4">
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[#22c55e] text-xl">check_circle</span>
                <span class="text-sm text-gray-300 font-body-md">Explain electricity fundamentals and battery systems.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[#22c55e] text-xl">check_circle</span>
                <span class="text-sm text-gray-300 font-body-md">Analyze energy conversion efficiency (AC/DC).</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[#22c55e] text-xl">check_circle</span>
                <span class="text-sm text-gray-300 font-body-md">Demonstrate electrical energy to propulsion.</span>
              </li>
              <li class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[#22c55e] text-xl">check_circle</span>
                <span class="text-sm text-gray-300 font-body-md">Evaluate regenerative braking impact.</span>
              </li>
            </ul>
          </div>
        </div>
        <!-- Right: Interactive Modules -->
        <div class="bg-white/5 border border-white/10 rounded-2xl p-6 h-full font-body-md">
          <h3 class="font-headline-md text-base md:text-lg text-white mb-6 flex items-center gap-2">Interactive Modules</h3>
          <div class="space-y-4">
            <!-- Module 1 details -->
            <details class="group bg-ev-black/40 border border-white/5 rounded-xl overflow-hidden transition-all hover:border-[#22c55e]/50">
              <summary class="flex items-center justify-between p-4 cursor-pointer list-none">
                <div class="flex items-center gap-3">
                  <span class="material-symbols-outlined text-[#22c55e] text-xl md:text-2xl">ev_station</span>
                  <span class="text-sm md:text-base text-white font-semibold">Module 1: Home AC charging</span>
                </div>
                <span class="material-symbols-outlined text-gray-400 transition-transform group-open:rotate-180">expand_more</span>
              </summary>
              <div class="p-4 pt-0 text-xs md:text-sm text-gray-400 border-t border-white/5">
                <p class="my-3">Explore how grid power flows to your EV battery.</p>
                <a href="module1.php" class="text-[#22c55e] font-bold inline-flex items-center gap-1 hover:gap-2 transition-all text-xs md:text-sm">Start Module <span class="material-symbols-outlined text-xs md:text-sm">arrow_forward</span></a>
              </div>
            </details>
            <!-- Module 2 details -->
            <details class="group bg-ev-black/40 border border-white/5 rounded-xl overflow-hidden transition-all hover:border-[#22c55e]/50">
              <summary class="flex items-center justify-between p-4 cursor-pointer list-none">
                <div class="flex items-center gap-3">
                  <span class="material-symbols-outlined text-[#22c55e] text-xl md:text-2xl">battery_charging_full</span>
                  <span class="text-sm md:text-base text-white font-semibold">Module 2: DC fast charging</span>
                </div>
                <span class="material-symbols-outlined text-gray-400 transition-transform group-open:rotate-180">expand_more</span>
              </summary>
              <div class="p-4 pt-0 text-xs md:text-sm text-gray-400 border-t border-white/5">
                <p class="my-3">Explore how public charging stations and solar work together.</p>
                <a href="module2.php" class="text-[#22c55e] font-bold inline-flex items-center gap-1 hover:gap-2 transition-all text-xs md:text-sm">Start Module <span class="material-symbols-outlined text-xs md:text-sm">arrow_forward</span></a>
              </div>
            </details>
            <!-- Module 3 details -->
            <details class="group bg-ev-black/40 border border-white/5 rounded-xl overflow-hidden transition-all hover:border-[#22c55e]/50">
              <summary class="flex items-center justify-between p-4 cursor-pointer list-none">
                <div class="flex items-center gap-3">
                  <span class="material-symbols-outlined text-[#22c55e] text-xl md:text-2xl">electric_car</span>
                  <span class="text-sm md:text-base text-white font-semibold">Module 3: Driving</span>
                </div>
                <span class="material-symbols-outlined text-gray-400 transition-transform group-open:rotate-180">expand_more</span>
              </summary>
              <div class="p-4 pt-0 text-xs md:text-sm text-gray-400 border-t border-white/5">
                <p class="my-3">Explore how the EV recovers energy when braking.</p>
                <a href="module3.php" class="text-[#22c55e] font-bold inline-flex items-center gap-1 hover:gap-2 transition-all text-xs md:text-sm">Start Module <span class="material-symbols-outlined text-xs md:text-sm">arrow_forward</span></a>
              </div>
            </details>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Contact Us Section (White Background) -->
<section class="py-10 bg-white" id="contact">
  <div class="page-container">
    <div class="max-w-3xl section-heading">
      <div class="h-1 w-10 md:w-16 bg-[#22c55e] mb-3 md:mb-4"></div>
      <h2 class="font-headline-lg text-xl md:text-headline-lg text-ev-black mb-3">Contact Us</h2>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      <!-- Left Column: Contact Info -->
      <div class="space-y-6 md:space-y-10">
        <h3 class="font-headline-md text-base md:text-xl text-ev-black">Contact Information</h3>
        <div class="space-y-5 md:space-y-8">
          <div class="flex items-start gap-3 md:gap-5">
            <div class="bg-[#22c55e]/10 p-3 md:p-4 rounded-xl md:rounded-2xl text-[#22c55e] shadow-sm">
              <span class="material-symbols-outlined text-xl md:text-2xl">mail</span>
            </div>
            <div>
              <p class="font-label-bold text-xs uppercase text-gray-400 mb-1">Email Us</p>
              <a class="text-secondary hover:text-[#22c55e] transition-colors text-sm md:text-lg font-medium" href="mailto:idpp1g13@gmail.com">idpp1g13@gmail.com</a>
            </div>
          </div>
          <div class="flex items-start gap-3 md:gap-5">
            <div class="bg-[#22c55e]/10 p-3 md:p-4 rounded-xl md:rounded-2xl text-[#22c55e] shadow-sm">
              <span class="material-symbols-outlined text-xl md:text-2xl">location_on</span>
            </div>
            <div>
              <p class="font-label-bold text-xs uppercase text-gray-400 mb-1">Our Location</p>
              <div class="text-secondary leading-relaxed text-sm md:text-base">
                P07, Advance Power Lab,<br>
                Faculty of Electrical Engineering (FKE),<br>
                Universiti Teknologi Malaysia (UTM),<br>
                81310 Skudai, Johor, Malaysia.
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Right Column: Map -->
      <div class="relative p-2 bg-gray-50 border border-gray-100 rounded-3xl overflow-hidden shadow-inner">
        <div class="w-full h-[280px] rounded-2xl overflow-hidden">
          <iframe allowfullscreen="" class="w-full h-full border-0 transition-all duration-700" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=1.5600833,103.6424167&t=&z=18&ie=UTF8&iwloc=&output=embed"></iframe>
        </div>
      </div>
    </div>
  </div>
</section>
</main>

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
<a class="font-body-md text-xs text-gray-400 hover:text-[#22c55e] transition-colors" href="#contact">Contact</a>
</nav>
</div>
</div>
</footer>

<script>
  // Animation on scroll
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) entry.target.classList.add('active');
    });
  }, { threshold: 0.1 });
  document.querySelectorAll('.fade-up, .reveal').forEach(el => observer.observe(el));

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
</body></html>