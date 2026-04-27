<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | Anveshan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
</head>
<body>
<section class="login-section">
    <div class="login-container">
        <h2>Reset Your Password</h2>
        
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php 
                echo htmlspecialchars($_SESSION['flash']); 
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>
        
        <p style="margin-bottom: 15px; color: #555;">Enter your registered email address to receive a 4-digit code.</p>

        <form action="send_otp.php" method="post" class="login-form">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required placeholder="e.g. you@example.com">

            <button type="submit">Send OTP</button>
        </form>
        <p class="register-link"><a href="login.php" style="color: #666; font-weight: normal;">Back to Log In</a></p>
    </div>
</section>
</body>
</html>
