<?php
session_start();

// Strict Owner check
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: login.php');
    exit();
}

require_once 'db_connection.php';
$conn = getDBConnection();

$owner_id = intval($_SESSION['user']['user_id']);

// Fetch all reviews for this owner's listings
$reviews = [];
$res = $conn->query("
    SELECT r.review_id, r.rating, r.comment, r.created_at, 
           l.room_type, l.address,
           u.name as reviewer_name
    FROM reviews r
    JOIN listings l ON r.listing_id = l.listing_id
    JOIN users u ON r.seeker_id = u.user_id
    WHERE l.owner_id = " . $owner_id . "
    ORDER BY r.created_at DESC
");

if ($res) {
    while($row = $res->fetch_assoc()) {
        $reviews[] = $row;
    }
}



// Fetch replies for these reviews
$replies = [];
$rep_res = $conn->query("SELECT rr.*, u.name as user_name FROM review_replies rr JOIN users u ON rr.user_id = u.user_id ORDER BY rr.created_at ASC");
if ($rep_res) {
    while($row = $rep_res->fetch_assoc()) {
        $replies[$row['review_id']][] = $row;
    }
}

closeDBConnection($conn);

include 'header.php';
?>
<link rel="stylesheet" href="<?php echo $base_url; ?>owner_dashboard.css">
<style>
.reply-box {
    background: #f9f9f9;
    padding: 10px;
    margin-top: 10px;
    border-left: 3px solid var(--brand-color);
    font-size: 14px;
}
.reply-form {
    margin-top: 10px;
    display: flex;
    gap: 10px;
}
.reply-input {
    flex: 1;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}
.reply-btn {
    padding: 8px 15px;
    background: var(--brand-color);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
</style>

<div class="owner-layout">
    <aside class="owner-sidebar">
        <div class="sidebar-brand">
            Anveshan
            <span>Owner Panel</span>
        </div>
        <nav class="sidebar-nav">
            <a href="owner_dashboard.php?page=dashboard" class="nav-link">Dashboard</a>
            <a href="owner_dashboard.php?page=listings" class="nav-link">My Listings</a>
            <a href="owner_dashboard.php?page=add" class="nav-link">Add Listing</a>
            <a href="owner_reviews.php" class="nav-link active">My Reviews</a>
            <a href="owner_dashboard.php?page=inquiries" class="nav-link">Inquiries</a>
            <a href="owner_dashboard.php?page=reports" class="nav-link">Reports Received</a>
        </nav>
    </aside>

    <main class="owner-content">
        <h1>My Reviews</h1>
        <p class="subtitle" style="margin-bottom: 20px; color: #666;">View and reply to feedback on your listings.</p>

        <?php if (isset($_SESSION['flash'])): ?>
            <div style="background: #e6f4ea; color: #1e8e3e; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <?php echo htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-panel">
            <?php if (empty($reviews)): ?>
                <div class="empty-state" style="text-align: center; padding: 40px; color: #7f8c8d;">
                    <p>No one has reviewed your listings yet.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <?php foreach($reviews as $rev): ?>
                        <div style="border: 1px solid #eee; border-radius: 8px; padding: 20px; background: white;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <h3 style="margin: 0; font-size: 18px; color: #333;"><?php echo htmlspecialchars($rev['room_type'] . ' in ' . $rev['address']); ?></h3>
                                    <div style="color: #666; font-size: 14px; margin-top: 5px;">
                                        Review by <strong><?php echo htmlspecialchars($rev['reviewer_name']); ?></strong> on <?php echo date('M d, Y', strtotime($rev['created_at'])); ?>
                                    </div>
                                </div>
                                <div style="color: #f39c12; font-size: 16px;">
                                    <?php echo str_repeat('⭐', $rev['rating']); ?>
                                </div>
                            </div>
                            
                            <p style="margin: 10px 0; line-height: 1.5; color: #444;">
                                <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                            </p>

                            <!-- Display Existing Replies -->
                            <?php if (isset($replies[$rev['review_id']])): ?>
                                <div style="margin-top: 15px; padding-left: 15px; border-left: 2px solid #ddd;">
                                    <?php foreach ($replies[$rev['review_id']] as $reply): ?>
                                        <div class="reply-box" style="margin-top: 10px; padding: 10px; background: <?php echo $reply['user_role'] === 'owner' ? '#effbf0' : '#f4f6f8'; ?>; border-radius: 4px;">
                                            <div style="font-size: 12px; color: #888; margin-bottom: 5px;">
                                                <strong><?php echo htmlspecialchars($reply['user_name']); ?></strong> 
                                                <?php if($reply['user_role'] === 'owner'): ?><span style="background: var(--brand-color); color: white; padding: 2px 6px; border-radius: 10px; font-size: 10px; margin-left: 5px;">Owner</span><?php endif; ?>
                                                <span style="float: right;"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                            <div style="color: #333; font-size: 14px;">
                                                <?php echo nl2br(htmlspecialchars($reply['reply_text'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Reply Form -->
                            <form action="submit_reply.php" method="POST" class="reply-form">
                                <input type="hidden" name="review_id" value="<?php echo $rev['review_id']; ?>">
                                <input type="hidden" name="return_url" value="owner_reviews.php">
                                <input type="text" name="reply_text" class="reply-input" placeholder="Write a reply..." required>
                                <button type="submit" class="reply-btn">Reply</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'footer.php'; ?>
