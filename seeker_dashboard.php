<?php
// seeker_dashboard.php
// Dashboard for seekers (students and working professionals)
// Only allow logged-in users with role "seeker"

session_start();

// Require login and seeker role
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$user_role = $user['role'] ?? '';

// Redirect if not a seeker
if ($user_role !== 'seeker') {
    if ($user_role === 'owner') {
        header('Location: owner_dashboard.php');
    } elseif ($user_role === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: indexmain.php');
    }
    exit();
}

// Redirect seekers to homepage (they use profile dropdown in header)
header('Location: indexmain.php');
exit();

// Include database connection
require_once 'db_connection.php';

// Get database connection
$conn = getDBConnection();

$seeker_id = intval($user['user_id']);

// Get user profile information
$user_query = "SELECT name, email, phone, created_at FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param('i', $seeker_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Get reports count (reports made by this seeker)
$reports_query = "SELECT COUNT(*) as total FROM reports WHERE reporter_id = ?";
$reports_stmt = $conn->prepare($reports_query);
$reports_stmt->bind_param('i', $seeker_id);
$reports_stmt->execute();
$reports_result = $reports_stmt->get_result();
$reports_data = $reports_result->fetch_assoc();
$total_reports = $reports_data['total'] ?? 0;
$reports_stmt->close();

// Get available listings count (for quick stats)
$listings_query = "SELECT COUNT(*) as total FROM listings WHERE city IN ('Kathmandu', 'Lalitpur', 'Bhaktapur')";
$listings_result = $conn->query($listings_query);
$listings_data = $listings_result->fetch_assoc();
$total_listings = $listings_data['total'] ?? 0;

// Close database connection
closeDBConnection($conn);

// Include header
include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>seeker_dashboard.css">

<section class="seeker-dashboard-section">
    <div class="seeker-wrap">
        <div class="top-row">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?>!</h1>
                <p class="welcome-text">Your room finder dashboard</p>
            </div>
            <div class="actions">
                <a class="btn primary" href="rooms.php">Browse Rooms</a>
                <a class="btn" href="indexmain.php">Back to Home</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_listings; ?></h3>
                <p>Available Rooms</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_reports; ?></h3>
                <p>Reports Submitted</p>
            </div>
            <div class="stat-card">
                <h3>3</h3>
                <p>Available Cities</p>
            </div>
        </div>

        <!-- Profile Information -->
        <div class="panel">
            <h2>My Profile</h2>
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
                            echo $date->format('F Y');
                        } else {
                            echo 'N/A';
                        }
                    ?></span>
                </div>
            </div>
        </div>

        <!-- Browse Rooms -->
        <div class="panel">
            <h2>Browse Available Rooms</h2>
            <div class="empty-state">
                <p>Search and browse available rooms in Kathmandu, Lalitpur, and Bhaktapur. Owner contact information will be visible once you view a listing.</p>
                <a href="rooms.php" class="btn primary">Browse All Rooms</a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="panel">
            <h2>Quick Actions</h2>
            <div class="quick-actions">
                <a href="rooms.php" class="action-card">
                    <h3>Search Rooms</h3>
                    <p>Find available rooms in Kathmandu, Lalitpur, or Bhaktapur</p>
                </a>
                <a href="rooms.php?city=Kathmandu" class="action-card">
                    <h3>Kathmandu</h3>
                    <p>Browse rooms in Kathmandu</p>
                </a>
                <a href="rooms.php?city=Lalitpur" class="action-card">
                    <h3>Lalitpur</h3>
                    <p>Browse rooms in Lalitpur</p>
                </a>
                <a href="rooms.php?city=Bhaktapur" class="action-card">
                    <h3>Bhaktapur</h3>
                    <p>Browse rooms in Bhaktapur</p>
                </a>
            </div>
        </div>
    </div>
</section>

<?php
include 'footer.php';
?>

