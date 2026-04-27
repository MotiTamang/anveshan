<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register | Anveshan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="register.css">
</head>
<body>
<section class="register-section">
    <div class="register-container">
        <h2>Create Your Anveshan Account</h2>
        
        <!-- Show error messages -->
        <?php if (isset($_SESSION['flash'])): ?>
            <div class="error-message" style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <?php 
                echo htmlspecialchars($_SESSION['flash']); 
                unset($_SESSION['flash']);
                ?>
            </div>
        <?php endif; ?>
        
        <form action="register_process.php" method="post" class="register-form">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required autocomplete="name">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email">

            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" required autocomplete="tel">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="new-password">

            <label for="confirm">Confirm Password</label>
            <input type="password" id="confirm" name="confirm" required autocomplete="new-password">

            <label for="role">Register as</label>
            <select id="role" name="role" required>
                <option value="seeker" selected>Seeker (find rooms)</option>
                <option value="owner">Owner (list rooms)</option>
            </select>

            <button type="submit">Register</button>
        </form>
        <p class="login-link">Already have an account? <a href="login.php">Log In</a></p>
    </div>
</section>
</body>
</html>