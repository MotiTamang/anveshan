<?php
// edit_listing_process.php
// Process form submission for editing listings

session_start();

// Require login and owner role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: login.php');
    exit();
}

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash'] = 'Error: Invalid request method.';
    header('Location: owner_dashboard.php?page=listings');
    exit();
}

// Include database connection
require_once 'db_connection.php';
require_once 'validation.php';

$user = $_SESSION['user'];
$owner_id = intval($user['user_id']);
$listing_id = isset($_POST['listing_id']) ? intval($_POST['listing_id']) : 0;
$conn = getDBConnection(); // Get connection early

if ($listing_id <= 0) {
    $_SESSION['flash'] = 'Error: Invalid listing ID.';
    closeDBConnection($conn);
    header('Location: owner_dashboard.php?page=listings');
    exit();
}

// Get and validate form data
$city = trim($_POST['city'] ?? '');
$address = trim($_POST['address'] ?? '');
$room_type = trim($_POST['room_type'] ?? '');
$price = monthly_rent_for_db($_POST['price'] ?? '');
$description = trim($_POST['description'] ?? '');
$latRaw = trim($_POST['latitude'] ?? '');
$lngRaw = trim($_POST['longitude'] ?? '');
$latitude = $latRaw !== '' ? (float) $latRaw : null;
$longitude = $lngRaw !== '' ? (float) $lngRaw : null;
$amenities_arr = $_POST['amenities'] ?? [];
$amenities = is_array($amenities_arr) ? implode(', ', $amenities_arr) : '';

// 1. Basic Required Fields Check
$errors = [];
if (empty($address)) $errors[] = 'Address is required.';
if (empty($room_type)) $errors[] = 'Room type is required.';

// 2. Custom Advanced Validation Algorithm
$validation_data = [
    'price' => $price,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'city' => $city,
    'address' => $address,
    'is_edit' => true,
    'conn' => $conn,
    'listing_id' => $listing_id,
    'owner_id' => $owner_id,
    'room_type' => $room_type,
];

// Note: For edits, we check $_FILES['new_images'] instead of 'images'. Let's rename it temporarily for the validator
$check_files = [];
if (isset($_FILES['new_images'])) {
    $check_files['images'] = $_FILES['new_images'];
}
$algo_errors = validate_listing($validation_data, $check_files);
$errors = array_merge($errors, $algo_errors);

// If validation errors, redirect back instantly
if (!empty($errors)) {
    $_SESSION['flash'] = 'Error: ' . implode('<br>- ', $errors);
    closeDBConnection($conn);
    header('Location: owner_dashboard.php?page=edit&id=' . $listing_id);
    exit();
}

// First verify that the listing belongs to this owner
$check_stmt = $conn->prepare("SELECT listing_id FROM listings WHERE listing_id = ? AND owner_id = ?");
$check_stmt->bind_param('ii', $listing_id, $owner_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    closeDBConnection($conn);
    $_SESSION['flash'] = 'Error: Listing not found or you do not have permission to edit it.';
    header('Location: owner_dashboard.php?page=listings');
    exit();
}
$check_stmt->close();

// Update the listing
$stmt = $conn->prepare("UPDATE listings SET city = ?, address = ?, room_type = ?, price = ?, description = ?, latitude = ?, longitude = ?, amenities = ? WHERE listing_id = ? AND owner_id = ?");

$latVal = $latitude === null ? null : sprintf('%.8f', $latitude);
$lngVal = $longitude === null ? null : sprintf('%.8f', $longitude);
$stmt->bind_param('sssissssii',
    $city,
    $address,
    $room_type,
    $price,
    $description,
    $latVal,
    $lngVal,
    $amenities,
    $listing_id,
    $owner_id
);

if ($stmt->execute()) {
    $stmt->close();
    
    // Handle new image uploads if any
    if (isset($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/listings/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $files = $_FILES['new_images'];
        $file_count = count($files['name']);
        
        // Process each uploaded file
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                // Validate file type
                $file_type = $files['type'][$i];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                
                if (in_array($file_type, $allowed_types)) {
                    // Generate unique filename
                    $file_extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                    $unique_filename = 'listing_' . $listing_id . '_' . time() . '_' . $i . '.' . $file_extension;
                    $file_path = $upload_dir . $unique_filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                        // Save image path to database
                        $image_stmt = $conn->prepare("INSERT INTO listing_images (listing_id, image_path) VALUES (?, ?)");
                        $image_stmt->bind_param('is', $listing_id, $file_path);
                        $image_stmt->execute();
                        $image_stmt->close();
                    }
                }
            }
        }
    }
    
    closeDBConnection($conn);
    
    $_SESSION['flash'] = 'Listing updated successfully!';
    header('Location: owner_dashboard.php?page=listings');
    exit();
} else {
    $error_msg = $stmt->error;
    $stmt->close();
    closeDBConnection($conn);
    
    $_SESSION['flash'] = 'Error: Failed to update listing. ' . $error_msg;
    header('Location: owner_dashboard.php?page=edit&id=' . $listing_id);
    exit();
}
?>

