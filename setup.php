<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS evkit");
    $pdo->exec("USE evkit");
// Create Users Table (Simplified)
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Add a test user (Password: password123)
$hashed_pass = password_hash('password123', PASSWORD_DEFAULT);
$pdo->exec("INSERT IGNORE INTO users (email, password, full_name) VALUES ('test@evkit.com', '$hashed_pass', 'EV Specialist')");
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Setup Complete</title>
        <script src='https://cdn.tailwindcss.com'></script>
    </head>
    <body class='bg-[#020617] text-white font-sans flex items-center justify-center min-h-screen'>
        <div class='bg-[#0F172A] border border-white/10 p-8 rounded-3xl max-w-md w-full shadow-2xl text-center'>
            <div class='text-green-500 text-5xl mb-4'>✓</div>
            <h1 class='text-2xl font-bold mb-4'>Setup Successful</h1>
            <p class='text-gray-400 text-sm mb-6'>Database 'evkit' and updated table 'users' have been initialized.</p>
            <div class='bg-black/30 p-4 rounded-xl text-left mb-6'>
                <p class='text-xs font-bold text-green-500 uppercase tracking-widest mb-2'>Test Credentials</p>
                <p class='text-sm mb-1'><b>Email:</b> test@evkit.com</p>
                <p class='text-sm'><b>Password:</b> password123</p>
            </div>
            <a href='login.php' class='block w-full bg-[#22C55E] text-black font-bold py-3 rounded-xl hover:opacity-90 transition-all'>GO TO LOGIN</a>
        </div>
    </body>
    </html>
    ";

} catch (PDOException $e) {
    die("Setup Error: " . $e->getMessage());
}
?>
