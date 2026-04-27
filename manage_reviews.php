<?php
session_start();

// Strict Admin check
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'db_connection.php';
$conn = getDBConnection();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $review_id = intval($_POST['review_id']);
    
    $del_stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
    $del_stmt->bind_param('i', $review_id);
    if ($del_stmt->execute()) {
        $_SESSION['flash'] = 'Review deleted successfully.';
    } else {
        $_SESSION['flash_error'] = 'Failed to delete review.';
    }
    $del_stmt->close();
    header('Location: manage_reviews.php');
    exit();
}

// Fetch all reviews
$reviews = [];
$res = $conn->query("
    SELECT r.review_id, r.rating, r.comment, r.created_at, 
           l.room_type, l.address,
           u.name as reviewer_name
    FROM reviews r
    JOIN listings l ON r.listing_id = l.listing_id
    JOIN users u ON r.seeker_id = u.user_id
    ORDER BY r.created_at DESC
");

if ($res) {
    while($row = $res->fetch_assoc()) {
        $reviews[] = $row;
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
            <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
            <a href="manage_users.php" class="nav-link">Manage Users</a>
            <a href="manage_reviews.php" class="nav-link active">Manage Reviews</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <h1>Manage Reviews</h1>
        
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

        <div class="panel">
            <h2>All Room Reviews</h2>
            <?php if (empty($reviews)): ?>
                <p class="muted">No reviews found.</p>
            <?php else: ?>
                <table class="listings-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 10px; border-bottom: 2px solid #eee; text-align: left;">Listing</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee; text-align: left;">Reviewer</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee; text-align: center;">Rating</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee; text-align: left;">Comment</th>
                            <th style="padding: 10px; border-bottom: 2px solid #eee; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reviews as $rev): ?>
                            <tr>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                    <?php echo htmlspecialchars($rev['room_type'] . ' in ' . $rev['address']); ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                    <?php echo htmlspecialchars($rev['reviewer_name']); ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: center; color: #f39c12;">
                                    <?php echo str_repeat('⭐', $rev['rating']); ?>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee;">
                                    <div style="max-height: 60px; overflow-y: auto; font-size: 13px;">
                                        <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                                    </div>
                                    <small style="color: #999;"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></small>
                                </td>
                                <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">
                                    <form action="manage_reviews.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                        <button type="submit" name="action" value="delete" style="padding: 6px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;" onclick="return confirm('Are you sure you want to delete this review? This cannot be undone.');">Delete</button>
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
