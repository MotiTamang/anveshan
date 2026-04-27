<?php
session_start();

// Ensure the user has actually requested an OTP first
if (!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP | Anveshan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="login.css">
    <style>
        .otp-input {
            margin-bottom: 20px;
        }
        .otp-input input {
            letter-spacing: 5px;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>
<section class="login-section">
    <div class="login-container">
        <h2>Enter OTP</h2>
        
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php 
                echo htmlspecialchars($_SESSION['flash']); 
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>
        
        <p style="margin-bottom: 15px; color: #555;">We have sent a 4-digit code to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>. Please enter it below to verify.</p>

        <form action="verify_otp_process.php" method="post" class="login-form">
            <div class="otp-input">
                <label for="otp">4-Digit Code</label>
                <input type="text" id="otp" name="otp" required maxlength="4" pattern="\d{4}" title="Please enter a 4-digit code" placeholder="e.g. 1234">
            </div>

            <button type="submit">Verify Code</button>
        </form>
        <p class="register-link"><a href="forgot_password.php" style="color: #666; font-weight: normal;">Cancel</a></p>
    </div>
</section>
</body>
</html>
