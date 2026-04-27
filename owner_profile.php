<?php
// owner_profile.php
// View and edit owner profile

session_start();

// Require login and owner role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'db_connection.php';

$user = $_SESSION['user'];
$owner_id = intval($user['user_id']);

// Get user profile information
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT user_id, name, email, phone, created_at, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();
closeDBConnection($conn);

// Include header
include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>seeker_dashboard.css">

<section class="seeker-dashboard-section">
    <div class="seeker-wrap">
        <div class="top-row">
            <h1>My Profile</h1>
            <div class="actions">
                <a class="btn" href="indexmain.php">Back to Home</a>
            </div>
        </div>

        <?php if (isset($_SESSION['flash'])): ?>
            <div style="background: #e6f4ea; color: #1e8e3e; padding: 10px; margin-bottom: 15px; border-radius: 5px;">
                <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_error'])): ?>
            <div style="background: #fce8e6; color: #d93025; padding: 10px; margin-bottom: 15px; border-radius: 5px;">
                <?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="panel">
            <h2>Profile Information</h2>
            
            <div style="display: flex; gap: 30px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="flex: 0 0 auto; text-align: center;">
                    <?php if (!empty($user_data['profile_image']) && file_exists($user_data['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user_data['profile_image']); ?>" alt="Profile Picture" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #eee;">
                        <form action="update_profile_image.php" method="POST" style="margin-top: 10px;">
                            <input type="hidden" name="action" value="delete_image">
                            <button type="submit" class="btn" style="background: #e74c3c; color: white; padding: 5px 10px; font-size: 14px;">Delete Photo</button>
                        </form>
                    <?php else: ?>
                        <div style="width: 150px; height: 150px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #888;">
                            <?php echo strtoupper(substr($user_data['name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="update_profile_image.php" method="POST" enctype="multipart/form-data" style="margin-top: 15px; text-align: left;">
                        <input type="hidden" name="action" value="upload_image">
                        <input type="file" name="profile_pic" accept="image/*" required style="font-size: 12px; max-width: 150px;">
                        <button type="submit" class="btn primary" style="padding: 5px 10px; font-size: 14px; margin-top: 5px;">Upload</button>
                    </form>
                </div>
                
                <div style="flex: 1;">
                    <div class="profile-info">
                <div class="info-row">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($user_data['phone'] ?? 'Not provided'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value"><?php 
                        if (!empty($user_data['created_at'])) {
                            $date = new DateTime($user_data['created_at']);
                            echo $date->format('F d, Y');
                        } else {
                            echo 'N/A';
                        }
                    ?></span>
                </div>
            </div>
                </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
                <a href="owner_settings.php" class="btn primary">Go to Settings</a>
            </div>
        </div>
    </div>
</section>

<?php
include 'footer.php';
?>
