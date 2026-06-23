<?php
require_once 'db.php'; // Use the clean connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($full_name) || empty($email) || empty($password)) {
        die("Please fill in all fields.");
    }

    if ($password !== $confirm_password) {
        die("Passwords do not match.");
    }

    // Hash the password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Prepare the statement to include institutional fields if they exist in your form
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$full_name, $email, $hashed_password]);

        // Redirect to login page for the welcome message
        $encoded_name = urlencode($full_name);
        header("Location: login.html?status=new_success&user=$encoded_name");
        exit();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Integrity constraint violation (duplicate email)
            die("Email already registered.");
        }
        die("Registration failed: " . $e->getMessage());
    }
}
?>