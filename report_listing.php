<?php
// report_listing.php
// Allow logged-in seekers to report listings
// Only seekers can report listings

session_start();

// Require login and seeker role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'seeker') {
    header('Location: login.php?redirect=report_listing.php');
    exit();
}

$user = $_SESSION['user'];
$reporter_id = intval($user['user_id']);
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

if ($listing_id <= 0) {
    header('Location: rooms.php');
    exit();
}

// Include database connection
require_once 'db_connection.php';

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason_type = trim($_POST['reason_type'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $reason = $reason_type . ($details ? " - " . $details : "");
    
    if (empty($reason_type)) {
        $error = 'Please provide a reason for reporting this listing.';
    } else {
        // Get database connection
        $conn = getDBConnection();
        
        // Check if listing exists
        $check_stmt = $conn->prepare("SELECT listing_id FROM listings WHERE listing_id = ?");
        $check_stmt->bind_param('i', $listing_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error = 'Listing not found.';
            $check_stmt->close();
            closeDBConnection($conn);
        } else {
            $check_stmt->close();
            
            // Insert report
            $insert_stmt = $conn->prepare("INSERT INTO reports (listing_id, reporter_id, reason, status) VALUES (?, ?, ?, 'pending')");
            $insert_stmt->bind_param('iis', $listing_id, $reporter_id, $reason);
            
            if ($insert_stmt->execute()) {
                $insert_stmt->close();
                closeDBConnection($conn);
                header('Location: rooms.php?reported=1');
                exit();
            } else {
                $error = 'Error submitting report. Please try again.';
                $insert_stmt->close();
                closeDBConnection($conn);
            }
        }
    }
}

// Get listing details for display
$conn = getDBConnection();
$listing_stmt = $conn->prepare("SELECT l.listing_id, l.city, l.address, l.price, l.room_type, l.description 
                                FROM listings l 
                                WHERE l.listing_id = ?");
$listing_stmt->bind_param('i', $listing_id);
$listing_stmt->execute();
$listing_result = $listing_stmt->get_result();
$listing = $listing_result->fetch_assoc();
$listing_stmt->close();
closeDBConnection($conn);

if (!$listing) {
    header('Location: rooms.php');
    exit();
}

// Include header
include 'header.php';
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>report_listing.css">

<section class="report-section">
    <div class="container">
        <div class="report-wrap">
            <h1>Report Listing</h1>
            
            <?php if ($error): ?>
                <div class="flash error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="listing-preview">
                <h3>Listing Details</h3>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($listing['room_type'] ?? 'Room'); ?></p>
                <p><strong>City:</strong> <?php echo htmlspecialchars($listing['city'] ?? 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($listing['address'] ?? 'N/A'); ?></p>
                <p><strong>Price:</strong> Rs. <?php echo number_format($listing['price'] ?? 0); ?>/month</p>
            </div>
            
            <form method="post" action="report_listing.php?listing_id=<?php echo $listing_id; ?>" class="report-form">
                <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>">
                
                <label for="reason_type">Reason for Reporting *</label>
                <select id="reason_type" name="reason_type" required style="width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 5px; font-family: inherit; font-size: 16px;">
                    <option value="">Select a reason</option>
                    <option value="Spam and misleading" <?php echo (isset($_POST['reason_type']) && $_POST['reason_type'] == 'Spam and misleading') ? 'selected' : ''; ?>>Spam and misleading</option>
                    <option value="Inaccurate information or photos" <?php echo (isset($_POST['reason_type']) && $_POST['reason_type'] == 'Inaccurate information or photos') ? 'selected' : ''; ?>>Inaccurate information or photos</option>
                    <option value="Inappropriate content" <?php echo (isset($_POST['reason_type']) && $_POST['reason_type'] == 'Inappropriate content') ? 'selected' : ''; ?>>Inappropriate content</option>
                    <option value="Others" <?php echo (isset($_POST['reason_type']) && $_POST['reason_type'] == 'Others') ? 'selected' : ''; ?>>Others</option>
                </select>
                
                <label for="details">Please provide details:</label>
                <textarea id="details" name="details" rows="6" placeholder="Where we can write what the problem is..."><?php echo htmlspecialchars($_POST['details'] ?? ''); ?></textarea>
                
                <div class="form-actions">
                    <button type="submit" class="btn submit">Submit Report</button>
                    <a href="rooms.php" class="btn cancel">Cancel</a>
                </div>
            </form>
            

        </div>
    </div>
</section>

<?php
include 'footer.php';
?>






