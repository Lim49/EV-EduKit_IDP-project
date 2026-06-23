<?php
session_start();
require_once 'db.php'; // Use the clean connection file

if (isset($_GET['guest'])) {
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
        die("Guest login failed: " . $e->getMessage());
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        die("Please fill in all fields.");
    }

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Email not found (unregistered)
            header("Location: login.html?status=error&message=EmailNotFound");
            exit();
        } else if (!password_verify($password, $user['password'])) {
            // Incorrect password
            header("Location: login.html?status=error&message=IncorrectPassword");
            exit();
        } else {
            // Authentication Successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $encoded_name = urlencode($user['full_name']);

            // Redirect all successful logins to Home.php
            header("Location: Home.php?user=$encoded_name");
            exit();
        }
    } catch (PDOException $e) {
        die("Login failed: " . $e->getMessage());
    }
}
?>