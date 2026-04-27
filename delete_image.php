<?php
// delete_image.php
// Delete a single image from a listing

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
$image_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$listing_id = isset($_GET['listing_id']) ? intval($_GET['listing_id']) : 0;

if ($image_id <= 0 || $listing_id <= 0) {
    $_SESSION['flash'] = 'Error: Invalid image or listing ID.';
    header('Location: owner_dashboard.php?page=listings');
    exit();
}

// Get database connection
$conn = getDBConnection();

// Verify that the listing belongs to this owner and get image path
$check_stmt = $conn->prepare("SELECT li.image_path, l.owner_id 
                              FROM listing_images li 
                              INNER JOIN listings l ON li.listing_id = l.listing_id 
                              WHERE li.image_id = ? AND li.listing_id = ? AND l.owner_id = ?");
$check_stmt->bind_param('iii', $image_id, $listing_id, $owner_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    closeDBConnection($conn);
    $_SESSION['flash'] = 'Error: Image not found or you do not have permission to delete it.';
    header('Location: owner_dashboard.php?page=edit&id=' . $listing_id);
    exit();
}

$image_data = $check_result->fetch_assoc();
$image_path = $image_data['image_path'];
$check_stmt->close();

// Delete image from database
$delete_stmt = $conn->prepare("DELETE FROM listing_images WHERE image_id = ? AND listing_id = ?");
$delete_stmt->bind_param('ii', $image_id, $listing_id);

if ($delete_stmt->execute()) {
    $delete_stmt->close();
    closeDBConnection($conn);
    
    // Delete image file from server
    if (file_exists($image_path)) {
        @unlink($image_path);
    }
    
    $_SESSION['flash'] = 'Image deleted successfully!';
    header('Location: owner_dashboard.php?page=edit&id=' . $listing_id);
    exit();
} else {
    $error_msg = $delete_stmt->error;
    $delete_stmt->close();
    closeDBConnection($conn);
    
    $_SESSION['flash'] = 'Error: Failed to delete image. ' . $error_msg;
    header('Location: owner_dashboard.php?page=edit&id=' . $listing_id);
    exit();
}
?>

