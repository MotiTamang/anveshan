<?php
// reset_password.php
session_start();
require_once 'db_connection.php';

// Ensure the user has verified the OTP
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_verified'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $email = $_SESSION['reset_email'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $_SESSION['flash'] = 'Please fill in both fields.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['flash'] = 'Passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['flash'] = 'Password must be at least 6 characters.';
    } else {
        // Hash the new password
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user DB: set new password, clear OTP
        $conn = getDBConnection();
        $stmt = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_expires = NULL WHERE email = ?");
        $stmt->bind_param('ss', $hashed, $email);
        
        if ($stmt->execute()) {
            $_SESSION['register_success'] = 'Password reset successfully! You can now log in.';
            
            // Clear the reset session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            
            $stmt->close();
            closeDBConnection($conn);
            
            header('Location: login.php');
            exit();
        } else {
            $_SESSION['flash'] = 'Error updating password. Try again.';
            $stmt->close();
            closeDBConnection($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set New Password | Anveshan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
</head>
<body>
<section class="login-section">
    <div class="login-container">
        <h2>Set New Password</h2>
        
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php 
                echo htmlspecialchars($_SESSION['flash']); 
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>
        
        <form action="reset_password.php" method="post" class="login-form">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">

            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">

            <button type="submit">Update Password</button>
        </form>
    </div>
</section>
</body>
</html>
