<?php
// search_rooms.php - API endpoint for AJAX room searches

session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

$conn = getDBConnection();

// Get all filter parameters
$search_query = isset($_GET['query']) ? trim($_GET['query']) : '';
$city_filter  = $_GET['city'] ?? '';
$room_type    = $_GET['room_type'] ?? '';
$min_price    = $_GET['min_price'] ?? '';
$max_price    = $_GET['max_price'] ?? '';

// Build the SQL query (same as rooms.php)
$sql = "
    SELECT 
        l.listing_id,
        l.city,
        l.address,
        l.price,
        l.room_type,
        l.description,
        l.created_at,
        u.name AS owner_name,
        u.email AS owner_email,
        u.phone AS owner_phone,
        (
            SELECT image_path 
            FROM listing_images 
            WHERE listing_id = l.listing_id 
            LIMIT 1
        ) AS main_image
    FROM listings l
    INNER JOIN users u ON l.owner_id = u.user_id
    WHERE 1=1
";

$params = [];
$types  = "";

// Apply filters
if ($search_query !== '') {
    $sql .= " AND (
        l.city LIKE ? OR 
        l.address LIKE ? OR 
        l.description LIKE ? OR 
        l.room_type LIKE ?
    )";
    for ($i = 0; $i < 4; $i++) {
        $params[] = "%$search_query%";
        $types .= "s";
    }
}

if ($city_filter !== '') {
    $sql .= " AND l.city = ?";
    $params[] = $city_filter;
    $types .= "s";
}

if ($room_type !== '') {
    $sql .= " AND l.room_type = ?";
    $params[] = $room_type;
    $types .= "s";
}

if ($min_price !== '') {
    $sql .= " AND l.price >= ?";
    $params[] = $min_price;
    $types .= "d";
}

if ($max_price !== '') {
    $sql .= " AND l.price <= ?";
    $params[] = $max_price;
    $types .= "d";
}

$sql .= " ORDER BY l.created_at DESC";

// Execute query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$listings = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
closeDBConnection($conn);

// Return JSON response
echo json_encode($listings);
?>