<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Check if this user is a guest (by email pattern)
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $email = $stmt->fetchColumn();
    
    if ($email && strpos($email, 'guest_') === 0 && strpos($email, '@evkit.local') !== false) {
        // Delete the guest user. ON DELETE CASCADE handles all linked progress/sessions.
        $stmt_del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt_del->execute([$user_id]);
    }
}

session_destroy();
header('Location: login.html');
exit;
?>
