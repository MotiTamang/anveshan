<?php
session_start();

// Strict Admin check
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db_connection.php';
$conn = getDBConnection();

// Get Total Users
$res_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE role != 'admin'");
$total_users = $res_users->fetch_assoc()['count'] ?? 0;

// Get Total Listings
$res_listings = $conn->query("SELECT COUNT(*) as count FROM listings");
$total_listings = $res_listings->fetch_assoc()['count'] ?? 0;

// Get Pending Reports
// Assuming 'reports' table structure: report_id, listing_id, reporter_id, reason, status
$total_reports = 0;
// Check if reports table exists first so it doesn't crash if it doesn't
$table_check = $conn->query("SHOW TABLES LIKE 'reports'");
if ($table_check->num_rows > 0) {
    $res_reports = $conn->query("
        SELECT COUNT(*) as count 
        FROM reports r 
        JOIN listings l ON r.listing_id = l.listing_id 
        JOIN users u ON r.reporter_id = u.user_id 
        WHERE r.status = 'pending'
    ");
    $total_reports = $res_reports->fetch_assoc()['count'] ?? 0;
}

// Fetch 5 most recent pending reports
$recent_reports = [];
if ($table_check->num_rows > 0) {
    $res_recent = $conn->query("
        SELECT r.report_id, r.reason, l.room_type, l.address, u.name as reporter_name
        FROM reports r
        JOIN listings l ON r.listing_id = l.listing_id
        JOIN users u ON r.reporter_id = u.user_id
        WHERE r.status = 'pending'
        ORDER BY r.report_id DESC LIMIT 5
    ");
    while($row = $res_recent->fetch_assoc()) {
        $recent_reports[] = $row;
    }
}

closeDBConnection($conn);

include 'header.php';
?>
<link rel="stylesheet" href="<?php echo $base_url; ?>admin_dashboard.css">

<div class="admin-layout">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            Anveshan
            <span>Admin Portal</span>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="nav-link active">Dashboard</a>
            <a href="manage_users.php" class="nav-link">Manage Users</a>
            <a href="manage_reviews.php" class="nav-link">Manage Reviews</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <h1>Dashboard Overview</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo number_format($total_users); ?></h3>
                <p>Total Registered Users</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_listings); ?></h3>
                <p>Total Active Listings</p>
            </div>
            <div class="stat-card">
                <h3><?php echo number_format($total_reports); ?></h3>
                <p>Pending Reports</p>
            </div>
        </div>

        <div class="panel">
            <h2>Recent Reports</h2>
            <?php if (empty($recent_reports)): ?>
                <p class="muted">No recent reports. Everything is clean!</p>
            <?php else: ?>
                <table class="listings-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 10px; border-bottom: 2px solid #eee;">Reporter</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee;">Reported Listing</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee;">Reason</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_reports as $rep): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($rep['reporter_name']); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($rep['room_type'] . ' - ' . $rep['address']); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($rep['reason']); ?></td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                    <form action="report_actions.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="report_id" value="<?php echo $rep['report_id']; ?>">
                                        <button type="submit" name="action" value="suspend" style="padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 5px;" onclick="return confirm('Suspend listing?');">Suspend</button>
                                        <button type="submit" name="action" value="dismiss" style="padding: 5px 10px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">Dismiss</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>