<?php
// delete_listing.php
// Delete a listing and its associated images

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
$listing_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($listing_id <= 0) {
    $_SESSION['flash'] = 'Error: Invalid listing ID.';
    header('Location: owner_dashboard.php?page=listings');
    exit();
}

// Get database connection
$conn = getDBConnection();

// First verify that the listing belongs to this owner
$check_stmt = $conn->prepare("SELECT listing_id FROM listings WHERE listing_id = ? AND owner_id = ?");
$check_stmt->bind_param('ii', $listing_id, $owner_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    closeDBConnection($conn);
    $_SESSION['flash'] = 'Error: Listing not found or you do not have permission to delete it.';
    header('Location: owner_dashboard.php?page=listings');
    exit();
}
$check_stmt->close();

// Get all image paths before deleting (to delete files from server)
$images_stmt = $conn->prepare("SELECT image_path FROM listing_images WHERE listing_id = ?");
$images_stmt->bind_param('i', $listing_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();
$image_paths = [];
while ($row = $images_result->fetch_assoc()) {
    $image_paths[] = $row['image_path'];
}
$images_stmt->close();

// Delete the listing (this will cascade delete images from database due to FOREIGN KEY)
$delete_stmt = $conn->prepare("DELETE FROM listings WHERE listing_id = ? AND owner_id = ?");
$delete_stmt->bind_param('ii', $listing_id, $owner_id);

if ($delete_stmt->execute()) {
    $delete_stmt->close();
    closeDBConnection($conn);
    
    // Delete image files from server
    foreach ($image_paths as $image_path) {
        if (file_exists($image_path)) {
            @unlink($image_path); // @ suppresses errors if file doesn't exist
        }
    }
    
    $_SESSION['flash'] = 'Listing deleted successfully!';
    header('Location: owner_dashboard.php?page=listings');
    exit();
} else {
    $error_msg = $delete_stmt->error;
    $delete_stmt->close();
    closeDBConnection($conn);
    
    $_SESSION['flash'] = 'Error: Failed to delete listing. ' . $error_msg;
    header('Location: owner_dashboard.php?page=listings');
    exit();
}
?>

