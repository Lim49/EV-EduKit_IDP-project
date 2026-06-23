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
     // Database might not be setup yet
     $error = "System notice: Please run setup.php first.";
}

if (isset($_GET['guest']) && isset($pdo)) {
    try {
        // 1. Delete expired guest accounts older than 24 hours
        $stmt = $pdo->prepare("DELETE FROM users WHERE email LIKE 'guest_%@evkit.local' AND created_at < NOW() - INTERVAL 1 DAY");
        $stmt->execute();

        // 2. Create a temporary guest user "Explorer"
        $guest_email = 'guest_' . uniqid() . '_' . time() . '@evkit.local';
        $guest_name = 'Explorer';
        $dummy_password = password_hash(uniqid(), PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$guest_name, $guest_email, $dummy_password]);
        $guest_id = $pdo->lastInsertId();

        // 3. Auto-link a mock kit so they bypass the pairing redirect
        $mock_mac = '00:11:22:33:44:55';
        $pdo->exec("CREATE TABLE IF NOT EXISTS kit_sessions (
            kit_mac   VARCHAR(17) NOT NULL,
            user_id   INT         NOT NULL,
            linked_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (kit_mac),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        $stmt_kit = $pdo->prepare("INSERT INTO kit_sessions (kit_mac, user_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)");
        $stmt_kit->execute([$mock_mac, $guest_id]);

        // 4. Setup session
        $_SESSION['user_id'] = $guest_id;
        $_SESSION['full_name'] = $guest_name;

        // 5. Redirect to Home.php
        header("Location: Home.php?user=" . urlencode($guest_name));
        exit();
    } catch (PDOException $e) {
        $error = "Guest login failed: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($pdo)) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'This email is not registered. Would you like to <a href="signup.php" class="underline font-bold text-primary">register a new account</a>?';
    } else if (!password_verify($password, $user['password'])) {
        $error = "Incorrect password. Please try again.";
    } else {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Sign In - C2D EVKit</title>
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
<body class="bg-ev-black text-white font-['Inter'] flex items-center justify-center min-h-screen p-6">

    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="flex justify-center mb-8">
            <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCHW7QX5Gcx9HyNP0fx6oc2vr5zPoe6axUJZ-rlQ2f_NjTDgN_9E7pl76bnkfDFjWLaRk9xBaNQi9MhgB6_gS1OCzDcwpcInyStp5fwN7nlcUgBuX3TjDnwLVP51vqj92KgNbqYeHWSTgXck6i1y831wzIrYE9pdkGb9N9AtjQW9Mlve5sHuHcJZ7R2vo8FgGIIG5AN1Tq4WRUd08HZau72BWpjjC8K_KBViLxzARq558ZtFmJZ_eC9lMD1xY3cxT2HNBpNZe5jYEs" alt="C2D EVKit" class="h-10 w-auto">
        </div>

        <!-- Login Card -->
        <div class="bg-white text-ev-black rounded-[2rem] p-6 sm:p-8 shadow-2xl overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-1 bg-primary"></div>
            <h1 class="font-['Montserrat'] text-2xl font-bold mb-2 text-center">Welcome Back</h1>
            <p class="text-gray-400 text-sm text-center mb-8">Sign in to continue your EV journey</p>

            <?php if (isset($error)): ?>
                <div class="bg-red-500/10 border border-red-500/50 text-red-500 text-xs p-3 rounded-xl mb-6 text-center italic">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2 px-1">Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-gray-300">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-2 px-1">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary focus:border-transparent outline-none transition-all placeholder:text-gray-300">
                </div>

                <div class="flex items-center justify-between px-1">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" class="w-4 h-4 rounded border-gray-200 bg-gray-50 text-primary focus:ring-offset-white">
                        <span class="text-[11px] text-gray-400 group-hover:text-gray-600 transition-colors">Remember me</span>
                    </label>
                    <a href="#" class="text-[11px] text-primary hover:underline font-medium">Forgot password?</a>
                </div>

                <button type="submit" class="w-full bg-primary text-white font-bold py-3.5 rounded-xl mt-4 shadow-[0_10px_20px_-10px_rgba(34,197,94,0.5)] hover:shadow-[0_15px_25px_-10px_rgba(34,197,94,0.6)] hover:scale-[1.02] active:scale-95 transition-all uppercase tracking-wider text-sm">
                    Sign In
                </button>
            </form>

            <div class="relative flex py-4 items-center">
                <div class="flex-grow border-t border-gray-100"></div>
                <span class="flex-shrink mx-4 text-gray-400 text-xs uppercase tracking-widest font-semibold">Or</span>
                <div class="flex-grow border-t border-gray-100"></div>
            </div>

            <a href="login.php?guest=1" class="w-full flex justify-center items-center bg-gray-50 border border-gray-100 hover:bg-gray-100 hover:border-gray-200 text-gray-600 font-bold py-3.5 rounded-xl transition-all uppercase tracking-wider text-sm">
                Continue as Guest
            </a>

            <div class="mt-8 text-center border-t border-gray-50 pt-6">
                <p class="text-[11px] text-gray-400">Don't have an account? <a href="signup.php" class="text-primary font-bold hover:underline">Sign Up Free</a></p>
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
