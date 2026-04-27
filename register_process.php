<?php
// register_process.php
// Securely create a new user. Uses prepared statements and password_hash.
// Adjust DB credentials as needed.

session_start();

// Include database connection and validation logic
require_once 'db_connection.php';
require_once 'validation.php';

// Simple helper to redirect with a message (you can expand later)
function redirect_with_msg($url, $msg = '') {
    if ($msg) { $_SESSION['flash'] = $msg; }
    header("Location: $url");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_msg('register.php');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$pass = $_POST['password'] ?? '';
$confirm = $_POST['confirm'] ?? '';
$role = $_POST['role'] ?? 'seeker';

// Use validation.php
$errors = validate_registration($name, $email, $phone, $pass, $confirm, $role);

if (!empty($errors)) {
    redirect_with_msg('register.php', implode(' | ', $errors));
}

// Hash password
$hashed = password_hash($pass, PASSWORD_DEFAULT);

// Get database connection
$conn = getDBConnection();

// Check if email already exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    closeDBConnection($conn);
    redirect_with_msg('register.php', 'Email already registered. Please log in.');
}
$stmt->close();

// Insert new user
$insert = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
$insert->bind_param('sssss', $name, $email, $phone, $hashed, $role);
if ($insert->execute()) {
    $insert->close();
    closeDBConnection($conn);
    
    // Set success message and redirect to login page
    $_SESSION['register_success'] = 'Registration successful! Please log in to continue.';
    header('Location: login.php?registered=1');
    exit();
} else {
    $insert->close();
    closeDBConnection($conn);
    redirect_with_msg('register.php', 'Error creating account. Try again.');
}
?>