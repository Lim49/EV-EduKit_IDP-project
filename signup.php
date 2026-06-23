<?php
session_start();

// Database configuration
$host = '127.0.0.1';
$db   = 'evkit';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     $error = "System notice: Database connection failed.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($pdo)) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email address already registered.";
        } else {
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)');
            
            if ($stmt->execute([$full_name, $email, $hashed_password])) {
                $_SESSION['signup_success'] = "Account created successfully! Please sign in.";
                header('Location: login.php');
                exit;
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Create Account - C2D EVKit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#22C55E",
                        "ev-black": "#020617",
                        "ev-dark": "#0F172A"
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-ev-black text-white font-['Inter'] flex items-center justify-center min-h-screen p-6 py-12">

    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHW7QX5Gcx9HyNP0fx6oc2vr5zPoe6axUJZ-rlQ2f_NjTDgN_9E7pl76bnkfDFjWLaRk9xBaNQi9MhgB6_gS1OCzDcwpcInyStp5fwN7nlcUgBuX3TjDnwLVP51vqj92KgNbqYeHWSTgXck6i1y831wzIrYE9pdkGb9N9AtjQW9Mlve5sHuHcJZ7R2vo8FgGIIG5AN1Tq4WRUd08HZau72BWpjjC8K_KBViLxzARq558ZtFmJZ_eC9lMD1xY3cxT2HNBpNZe5jYEs" alt="C2D EVKit" class="h-10 w-auto">
        </div>

        <!-- Signup Card -->
        <div class="bg-white text-ev-black rounded-[2rem] p-6 sm:p-8 shadow-2xl overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-1 bg-primary"></div>
            <h1 class="font-['Montserrat'] text-2xl font-bold mb-3 text-center">Create your account here</h1>
            <p class="text-gray-400 text-xs text-center mb-10 leading-relaxed">Join thousands of learners exploring the future of electric vehicles through hands-on education.</p>

            <?php if (isset($error)): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 text-xs p-3 rounded-xl mb-6 text-center italic">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2 px-1">Full name</label>
                    <input type="text" name="full_name" required placeholder="John Doe" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-gray-300">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2 px-1">Email address</label>
                    <input type="email" name="email" required placeholder="name@example.com" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-gray-300">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2 px-1">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-gray-300">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2 px-1">Confirm password</label>
                    <input type="password" name="confirm_password" required placeholder="••••••••" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-gray-300">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full bg-primary text-white font-bold py-3.5 rounded-xl shadow-[0_10px_20px_-10px_rgba(34,197,94,0.5)] hover:shadow-[0_15px_25px_-10px_rgba(34,197,94,0.6)] hover:scale-[1.01] active:scale-95 transition-all uppercase tracking-wider text-sm">
                        Create Account
                    </button>
                </div>
            </form>

            <div class="mt-8 text-center space-y-4 border-t border-gray-50 pt-8">
                <p class="text-[11px] text-gray-400">
                    Already have an account? <a href="login.php" class="text-primary font-bold hover:underline">Log in here</a>
                </p>
                <div class="h-px bg-gray-50 w-1/4 mx-auto"></div>
                <p class="text-[10px] text-gray-400 italic leading-snug">
                    Are you an instructor? Contact your institution for access
                </p>
            </div>
        </div>

        <!-- Back to Home -->
        <div class="mt-8 text-center">
            <a href="code.html" class="text-xs text-gray-600 hover:text-white transition-colors flex items-center justify-center gap-2">
                <span>← Back to platform</span>
            </a>
        </div>
    </div>

</body>
</html>
