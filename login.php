<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Anveshan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Only call login.css here! -->
    <link rel="stylesheet" href="login.css">
</head>
<body>
<section class="login-section">
    <div class="login-container">
        <h2>Log In to Anveshan</h2>
        
        <!-- Show error messages -->
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php 
                echo htmlspecialchars($_SESSION['flash']); 
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>
        
        <form action="login_process.php" method="post" class="login-form">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="username" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">

            <button type="submit">Log In</button>
        </form>
        <p class="register-link"><a href="forgot_password.php" style="color: #666; font-weight: normal;">Forgot Password?</a></p>
        <p class="register-link">Don't have an account? <a href="register.php">Register</a></p>
    </div>
</section>

<script>
// Show success alert if registration was successful
<?php if (isset($_SESSION['register_success'])): ?>
    alert('<?php echo addslashes($_SESSION['register_success']); ?>');
    <?php unset($_SESSION['register_success']); ?>
<?php endif; ?>

// Show success message from URL parameter
<?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
    alert('Registration successful! Please log in to continue.');
<?php endif; ?>
</script>
</body>
</html>