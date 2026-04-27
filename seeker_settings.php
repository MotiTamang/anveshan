<?php
// seeker_settings.php
// Settings page for seekers - change password, delete account

session_start();

// Require login and seeker role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'seeker') {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'db_connection.php';

$user = $_SESSION['user'];
$seeker_id = intval($user['user_id']);

$message = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $new_name = trim($_POST['name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if (empty($new_name) || empty($new_email) || empty($new_phone)) {
        $error = 'Name, email, and phone are required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $conn = getDBConnection();
        // Check if email already exists for another user
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_stmt->bind_param('si', $new_email, $seeker_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = 'Email is already in use by another account.';
        } else {
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE user_id = ?");
            $update_stmt->bind_param('sssi', $new_name, $new_email, $new_phone, $seeker_id);
            if ($update_stmt->execute()) {
                $message = 'Profile updated successfully!';
                $_SESSION['user']['name'] = $new_name;
                $_SESSION['user']['email'] = $new_email;
            } else {
                $error = 'Error updating profile. Please try again.';
            }
            $update_stmt->close();
        }
        $check_stmt->close();
        closeDBConnection($conn);
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $seeker_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($user_data && password_verify($current_password, $user_data['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update_stmt->bind_param('si', $hashed, $seeker_id);
            if ($update_stmt->execute()) {
                $message = 'Password changed successfully!';
            } else {
                $error = 'Error updating password. Please try again.';
            }
            $update_stmt->close();
        } else {
            $error = 'Current password is incorrect.';
        }
        closeDBConnection($conn);
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_account') {
    $confirm_delete = $_POST['confirm_delete'] ?? '';
    
    if ($confirm_delete !== 'DELETE') {
        $error = 'Please type DELETE to confirm account deletion.';
    } else {
        $conn = getDBConnection();
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $delete_stmt->bind_param('i', $seeker_id);
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            closeDBConnection($conn);
            session_destroy();
            header('Location: indexmain.php?account_deleted=1');
            exit();
        } else {
            $error = 'Error deleting account. Please try again.';
        }
        $delete_stmt->close();
        closeDBConnection($conn);
    }
}

// Fetch current user data for the form
$conn = getDBConnection();
$fetch_stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE user_id = ?");
$fetch_stmt->bind_param('i', $seeker_id);
$fetch_stmt->execute();
$current_info = $fetch_stmt->get_result()->fetch_assoc();
$fetch_stmt->close();
closeDBConnection($conn);

// Include header
include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>seeker_dashboard.css">

<section class="seeker-dashboard-section">
    <div class="seeker-wrap">
        <div class="top-row">
            <h1>Settings</h1>
            <div class="actions">
                <a class="btn" href="seeker_profile.php">My Profile</a>
                <a class="btn" href="indexmain.php">Back to Home</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="flash-message success" style="padding: 15px; margin-bottom: 20px; background: #d4edda; color: #155724; border-radius: 5px;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash-message error" style="padding: 15px; margin-bottom: 20px; background: #f8d7da; color: #721c24; border-radius: 5px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Update Profile -->
        <div class="panel">
            <h2>Update Profile</h2>
            <form method="post" action="seeker_settings.php" style="max-width: 500px;">
                <input type="hidden" name="action" value="update_profile">
                
                <label for="name" style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name *</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($current_info['name'] ?? ''); ?>" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                
                <label for="email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email Address *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($current_info['email'] ?? ''); ?>" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                
                <label for="phone" style="display: block; margin-bottom: 5px; font-weight: 600;">Phone Number *</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($current_info['phone'] ?? ''); ?>" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                
                <button type="submit" class="btn primary" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">Save Changes</button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="panel">
            <h2>Change Password</h2>
            <form method="post" action="seeker_settings.php" style="max-width: 500px;">
                <input type="hidden" name="action" value="change_password">
                
                <label for="current_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Current Password *</label>
                <input type="password" id="current_password" name="current_password" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                
                <label for="new_password" style="display: block; margin-bottom: 5px; font-weight: 600;">New Password *</label>
                <input type="password" id="new_password" name="new_password" required minlength="6" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                
                <label for="confirm_password" style="display: block; margin-bottom: 5px; font-weight: 600;">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px;">
                
                <button type="submit" class="btn primary" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">Change Password</button>
            </form>
        </div>

        <!-- Delete Account -->
        <div class="panel" style="border: 2px solid #dc3545;">
            <h2 style="color: #dc3545;">Delete Account</h2>
            <p style="color: #666; margin-bottom: 15px;">Warning: This action cannot be undone. All your data, reports, and account information will be permanently deleted.</p>
            <form method="post" action="seeker_settings.php" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action cannot be undone!');" style="max-width: 500px;">
                <input type="hidden" name="action" value="delete_account">
                
                <label for="confirm_delete" style="display: block; margin-bottom: 5px; font-weight: 600;">Type DELETE to confirm *</label>
                <input type="text" id="confirm_delete" name="confirm_delete" required placeholder="Type DELETE" style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #dc3545; border-radius: 5px;">
                
                <button type="submit" class="btn" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Delete My Account</button>
            </form>
        </div>
    </div>
</section>

<?php
include 'footer.php';
?>
