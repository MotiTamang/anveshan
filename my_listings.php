<?php
// my_listings.php
// Owner's list of their listings
// Only owners can access

session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'db_connection.php';

// Get owner ID from session
$owner_id = intval($_SESSION['user']['user_id']);

// Get database connection
$conn = getDBConnection();

// Get all listings for this owner
$listings = [];
$stmt = $conn->prepare("SELECT listing_id, city, address, room_type, price, description, created_at FROM listings WHERE owner_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $owner_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $listings[] = $row;
}
$stmt->close();

// Close database connection
closeDBConnection($conn);

include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>my_listings.css">

<section class="my-listings-section">
    <div class="wrap">
        <div class="head">
            <h1>My Listings</h1>
            <a class="btn add" href="add_listing.php">+ Add Listing</a>
        </div>

        <div class="table-wrap">
            <table class="listings-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Location</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($listings)): ?>
                        <tr class="empty-row">
                            <td colspan="5">You have no listings yet. Click "Add Listing" to create one.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($listing['room_type'] ?? 'Room'); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($listing['city'] ?? ''); ?></strong><br>
                                    <small><?php echo htmlspecialchars(substr($listing['address'] ?? '', 0, 50)); ?><?php echo strlen($listing['address'] ?? '') > 50 ? '...' : ''; ?></small>
                                </td>
                                <td>Rs. <?php echo number_format($listing['price'] ?? 0); ?>/month</td>
                                <td><span style="color: green;">Active</span></td>
                                <td>
                                    <a href="edit_listing.php?id=<?php echo $listing['listing_id']; ?>" style="color: #007bff; text-decoration: none; margin-right: 10px;">Edit</a>
                                    <a href="delete_listing.php?id=<?php echo $listing['listing_id']; ?>" onclick="return confirm('Are you sure you want to delete this listing?');" style="color: #dc3545; text-decoration: none;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php
include 'footer.php';
?>