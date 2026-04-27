<?php
// add_listing_process.php
// Process form submission from add_listing.php
// Handles listing creation, validation, and image uploads

session_start();

// Require login and owner role
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'owner') {
    header('Location: login.php');
    exit();
}

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash'] = 'Error: Invalid request method.';
    header('Location: owner_dashboard.php?page=add');
    exit();
}

// Include database connection
require_once 'db_connection.php';
require_once 'validation.php';

$user = $_SESSION['user'];
$owner_id = intval($user['user_id']);
$conn = getDBConnection(); // Get connection early for validation

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
if (empty($address)) {
    $errors[] = 'Address is required.';
}
if (empty($room_type)) {
    $errors[] = 'Room type is required.';
}
if (empty($latitude) || empty($longitude)) {
    $errors[] = 'Location on map is required.';
}
if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
    $errors[] = 'At least one listing image is required.';
}

// 2. Custom Advanced Validation Algorithm
$validation_data = [
    'price' => $price,
    'latitude' => $latitude,
    'longitude' => $longitude,
    'city' => $city,
    'address' => $address,
    'is_edit' => false,
    'conn' => $conn,
    'owner_id' => $owner_id,
    'room_type' => $room_type,
];

$algo_errors = validate_listing($validation_data, $_FILES);
$errors = array_merge($errors, $algo_errors);

if (!empty($errors)) {
    $_SESSION['flash'] = 'Error: ' . implode('<br>- ', $errors);
    closeDBConnection($conn);
    header('Location: owner_dashboard.php?page=add');
    exit();
}

// Prepare SQL statement to insert listing
$stmt = $conn->prepare("INSERT INTO listings (owner_id, city, address, price, room_type, description, latitude, longitude, amenities) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

// Bind parameters
$latVal = $latitude === null ? null : sprintf('%.8f', $latitude);
$lngVal = $longitude === null ? null : sprintf('%.8f', $longitude);
$stmt->bind_param('ississsss',
    $owner_id,
    $city,
    $address,
    $price,
    $room_type,
    $description,
    $latVal,
    $lngVal,
    $amenities
);

// Execute the insert
if ($stmt->execute()) {
    $listing_id = $stmt->insert_id; // Get the ID of the newly created listing
    $stmt->close();
    
    // Handle image uploads if any
    $uploaded_images = [];
    
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/listings/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create directory with write permissions
        }
        
        $files = $_FILES['images'];
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
                        
                        if ($image_stmt->execute()) {
                            $uploaded_images[] = $file_path;
                        }
                        $image_stmt->close();
                    }
                }
            }
        }
    }
    
    // Close database connection
    closeDBConnection($conn);
    
    // Success message
    $_SESSION['flash'] = 'Listing created successfully!';
    header('Location: owner_dashboard.php?page=listings');
    exit();
    
} else {
    // Database error
    $error_msg = $stmt->error;
    $stmt->close();
    closeDBConnection($conn);
    
    $_SESSION['flash'] = 'Error: Failed to create listing. ' . $error_msg;
    header('Location: owner_dashboard.php?page=add');
    exit();
}
?>

