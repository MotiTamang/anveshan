<?php
// login_process.php
// Authenticate user and set session. Uses prepared statements and password_verify.

session_start();

// Include database connection and validation logic
require_once 'db_connection.php';
require_once 'validation.php';

function redirect_with_msg($url, $msg = '') {
    if ($msg) {
        $_SESSION['flash'] = $msg;
    }
    header("Location: $url");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_msg('login.php');
}

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

// Use validation.php
$errors = validate_login($email, $pass);

if (!empty($errors)) {
    redirect_with_msg('login.php', implode(' | ', $errors));
}

// Get database connection
$conn = getDBConnection();

$stmt = $conn->prepare(
    "SELECT user_id, name, email, password, role 
     FROM users 
     WHERE email = ? 
     LIMIT 1"
);
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    redirect_with_msg('login.php', 'Invalid email or password.');
}

$stmt->bind_result($user_id, $name, $email_db, $hash, $role);
$stmt->fetch();

if (!password_verify($pass, $hash)) {
    $stmt->close();
    closeDBConnection($conn);
    redirect_with_msg('login.php', 'Invalid email or password.');
}

// ✅ SUCCESS — regenerate session + set session
session_regenerate_id(true);

$_SESSION['user'] = [
    'user_id' => $user_id,
    'name'    => $name,
    'email'   => $email_db,
    'role'    => $role
];

$stmt->close();
closeDBConnection($conn);

// Redirect based on role
if (isset($_SESSION['redirect_after_login'])) {
    $redirect_url = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect_url);
} else {
    if ($role === 'owner') {
        header('Location: owner_dashboard.php');
    } elseif ($role === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        // default = seeker - redirect to seeker dashboard
        header('Location: seeker_dashboard.php');
    }
}

exit();
?>
