<?php
// contact.php
// Show owner contact information for a listing
// Owner contact is only visible to logged-in seekers

session_start();

// Require login
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'seeker') {
    header('Location: login.php?redirect=contact.php');
    exit();
}

$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

if ($listing_id <= 0) {
    header('Location: rooms.php');
    exit();
}

// Include database connection
require_once 'db_connection.php';

// Get database connection
$conn = getDBConnection();

// Get listing and owner information
$query = "SELECT l.listing_id, l.city, l.address, l.price, l.room_type, l.description,
                 u.name as owner_name, u.email as owner_email, u.phone as owner_phone
          FROM listings l
          INNER JOIN users u ON l.owner_id = u.user_id
          WHERE l.listing_id = ?";
          
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $listing_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();
$stmt->close();

if (!$listing) {
    closeDBConnection($conn);
    header('Location: rooms.php');
    exit();
}

closeDBConnection($conn);

// Include header
include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>contact.css">

<section class="contact-section">
    <div class="container">
        <div class="contact-wrap">
            <h2>Owner Contact Information</h2>
            
            <div class="listing-info">
                <h3>Listing Details</h3>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($listing['room_type'] ?? 'Room'); ?></p>
                <p><strong>City:</strong> <?php echo htmlspecialchars($listing['city'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($listing['address'] ?? 'N/A'); ?></p>
                <p><strong>Price:</strong> Rs. <?php echo number_format($listing['price'] ?? 0); ?>/month</p>
            </div>
            
            <div class="owner-info">
                <h3>Contact Owner</h3>
                <div class="contact-details">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($listing['owner_name'] ?? 'N/A'); ?></p>
                    <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($listing['owner_email'] ?? ''); ?>"><?php echo htmlspecialchars($listing['owner_email'] ?? 'N/A'); ?></a></p>
                    <?php if (!empty($listing['owner_phone'])): ?>
                        <p><strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($listing['owner_phone']); ?>"><?php echo htmlspecialchars($listing['owner_phone']); ?></a></p>
                    <?php else: ?>
                        <p><strong>Phone:</strong> Not provided</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="contact-actions">
                <a href="rooms.php" class="btn">Back to Listings</a>
            </div>
        </div>
    </div>
</section>

<?php
include 'footer.php';
?>