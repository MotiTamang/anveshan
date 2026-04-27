<?php
// search_rooms.php
session_start();
require_once 'db_connection.php';

$conn = getDBConnection();

$city_filter  = isset($_GET['city']) ? trim($_GET['city']) : '';
$room_type    = isset($_GET['room_type']) ? trim($_GET['room_type']) : '';
$min_price    = isset($_GET['min_price']) ? trim($_GET['min_price']) : '';
$max_price    = isset($_GET['max_price']) ? trim($_GET['max_price']) : '';
$amenities    = isset($_GET['amenities']) ? trim($_GET['amenities']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "
    SELECT 
        l.listing_id,
        l.city,
        l.address,
        l.price,
        l.room_type,
        l.description,
        u.name AS owner_name,
        u.email AS owner_email,
        u.phone AS owner_phone,
        (
            SELECT image_path 
            FROM listing_images 
            WHERE listing_id = l.listing_id 
            LIMIT 1
        ) AS main_image,
        (
            SELECT AVG(rating)
            FROM reviews
            WHERE listing_id = l.listing_id
        ) AS avg_rating,
        (
            SELECT COUNT(*)
            FROM reviews
            WHERE listing_id = l.listing_id
        ) AS review_count
    FROM listings l
    INNER JOIN users u ON l.owner_id = u.user_id
    WHERE l.status = 'active'
";

$params = [];
$types = '';

if ($city_filter !== '') {
    $query .= " AND l.city = ?";
    $params[] = $city_filter;
    $types .= 's';
}

if ($room_type !== '') {
    $query .= " AND l.room_type LIKE ?";
    $search_room = "%$room_type%";
    $params[] = $search_room;
    $types .= 's';
}

if ($min_price !== '') {
    $query .= " AND l.price >= ?";
    $params[] = $min_price;
    $types .= 'd';
}

if ($max_price !== '') {
    $query .= " AND l.price <= ?";
    $params[] = $max_price;
    $types .= 'd';
}

if ($amenities !== '') {
    $query .= " AND l.amenities LIKE ?";
    $search_amenity = "%$amenities%";
    $params[] = $search_amenity;
    $types .= 's';
}

if ($search_query !== '') {
    $search_query_lower = strtolower($search_query);
    
    // 1. Extract known cities
    $known_cities = ['kathmandu', 'lalitpur', 'bhaktapur'];
    $city_matched = false;
    foreach ($known_cities as $kc) {
        if (strpos($search_query_lower, $kc) !== false) {
            $query .= " AND LOWER(l.city) = ?";
            $params[] = $kc;
            $types .= 's';
            $search_query_lower = trim(str_replace($kc, '', $search_query_lower));
            $city_matched = true;
            break; // only match one city
        }
    }
    
    // 2. Extract known room types
    $known_types = ['single', 'shared', 'flat', '1 bhk', '2 bhk'];
    foreach ($known_types as $kt) {
        if (strpos($search_query_lower, $kt) !== false) {
            $query .= " AND LOWER(l.room_type) LIKE ?";
            $params[] = '%' . $kt . '%';
            $types .= 's';
            $search_query_lower = trim(str_replace($kt, '', $search_query_lower));
        }
    }
    
    // 3. Remaining terms search across description and address
    $remaining_terms = array_filter(explode(' ', $search_query_lower));
    foreach ($remaining_terms as $term) {
        // Skip common stop-words
        if (in_array($term, ['in', 'at', 'on', 'room', 'rooms', 'for', 'rent']) || strlen($term) <= 2) {
            continue; 
        }
        $query .= " AND (LOWER(l.description) LIKE ? OR LOWER(l.address) LIKE ?)";
        $like_search = "%$term%";
        array_push($params, $like_search, $like_search);
        $types .= 'ss';
    }
}

$query .= " ORDER BY l.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$listings = [];
while ($row = $result->fetch_assoc()) {
    $listings[] = $row;
}

$stmt->close();
closeDBConnection($conn);

// Return JSON
header('Content-Type: application/json');
echo json_encode($listings);
