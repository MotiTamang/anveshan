<?php
// send_otp.php
session_start();
require_once 'db_connection.php';
require_once 'mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash'] = 'Invalid email address.';
    header('Location: forgot_password.php');
    exit();
}

$conn = getDBConnection();

// Check if user exists
$stmt = $conn->prepare("SELECT user_id, name FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['flash'] = 'No account found with this email address.';
    $stmt->close();
    closeDBConnection($conn);
    header('Location: forgot_password.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Generate a 4-digit OTP
$otp = (string) rand(1000, 9999);

// Set expiration in 15 minutes
$expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

$subject = "Your Anveshan Password Reset OTP";
$message = "Hello " . $user['name'] . ",\n\n";
$message .= "You requested a password reset. Here is your 4-digit OTP code:\n";
$message .= $otp . "\n\n";
$message .= "This code will expire in 15 minutes.\n";
$message .= "If you did not request this, please ignore this email.\n";

$mailError = null;
$mailOk = send_smtp_mail($email, $subject, $message, $mailError);

if (!$mailOk) {
    closeDBConnection($conn);
    $_SESSION['flash'] = $mailError ?? 'Could not send the OTP email.';
    header('Location: forgot_password.php');
    exit();
}

// Only persist OTP after the message was accepted by SMTP
$update_stmt = $conn->prepare("UPDATE users SET reset_otp = ?, reset_expires = ? WHERE email = ?");
$update_stmt->bind_param('sss', $otp, $expires, $email);
$update_stmt->execute();
$update_stmt->close();
closeDBConnection($conn);

$_SESSION['reset_email'] = $email;

header('Location: verify_otp.php');
exit();
