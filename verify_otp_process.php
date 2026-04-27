<?php
// verify_otp_process.php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['reset_email'])) {
    header('Location: login.php');
    exit();
}

$otp_entered = trim($_POST['otp'] ?? '');
$email = $_SESSION['reset_email'];

// Get database connection
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT reset_otp, reset_expires FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['flash'] = 'Invalid session. Please start over.';
    $stmt->close();
    closeDBConnection($conn);
    header('Location: forgot_password.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if OTP matches and has not expired
$current_time = date('Y-m-d H:i:s');

if ($user['reset_otp'] !== $otp_entered) {
    $_SESSION['flash'] = 'Invalid OTP. Please try again.';
    closeDBConnection($conn);
    header('Location: verify_otp.php');
    exit();
}

if ($current_time > $user['reset_expires']) {
    $_SESSION['flash'] = 'Your OTP has expired. Please request a new one.';
    // Clear the expired OTP
    $update_stmt = $conn->prepare("UPDATE users SET reset_otp = NULL, reset_expires = NULL WHERE email = ?");
    $update_stmt->bind_param('s', $email);
    $update_stmt->execute();
    $update_stmt->close();
    closeDBConnection($conn);
    
    header('Location: forgot_password.php');
    exit();
}

// If valid, store a verified flag in session so they can access the reset page
$_SESSION['reset_verified'] = true;
closeDBConnection($conn);

header('Location: reset_password.php');
exit();
?>
