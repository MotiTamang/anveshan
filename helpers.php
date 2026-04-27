<?php
// helpers.php - Common database and utility functions for Anveshan

require_once 'db_connection.php';

/**
 * Get listings with filters and search
 */
function getListingsWithFilters($city_filter = '', $room_type = '', $min_price = '', $max_price = '', $amenities = '', $search_query = '') {
    $conn = getDBConnection();
    
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
        JOIN users u ON l.owner_id = u.user_id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // City filter
    if (!empty($city_filter)) {
        $sql .= " AND LOWER(l.city) = ?";
        $params[] = strtolower($city_filter);
        $types .= 's';
    }
    
    // Room type filter
    if (!empty($room_type)) {
        $sql .= " AND LOWER(l.room_type) LIKE ?";
        $params[] = '%' . strtolower($room_type) . '%';
        $types .= 's';
    }
    
    // Price filters
    if (!empty($min_price) && is_numeric($min_price)) {
        $sql .= " AND l.price >= ?";
        $params[] = intval($min_price);
        $types .= 'i';
    }
    
    if (!empty($max_price) && is_numeric($max_price)) {
        $sql .= " AND l.price <= ?";
        $params[] = intval($max_price);
        $types .= 'i';
    }
    
    // Amenities filter
    if (!empty($amenities)) {
        $amenity_list = explode(',', $amenities);
        foreach ($amenity_list as $amenity) {
            $amenity = trim($amenity);
            if (!empty($amenity)) {
                $sql .= " AND LOWER(l.amenities) LIKE ?";
                $params[] = '%' . strtolower($amenity) . '%';
                $types .= 's';
            }
        }
    }
    
    // Smart search query
    if (!empty($search_query)) {
        $search_query_lower = strtolower(trim($search_query));
        
        // 1. Extract known cities
        $known_cities = ['kathmandu', 'pokhara', 'lalitpur', 'bhaktapur', 'biratnagar', 'dharan', 'birgunj'];
        foreach ($known_cities as $kc) {
            if (strpos($search_query_lower, $kc) !== false) {
                $sql .= " AND LOWER(l.city) = ?";
                $params[] = $kc;
                $types .= 's';
                $search_query_lower = trim(str_replace($kc, '', $search_query_lower));
                break;
            }
        }
        
        // 2. Extract known room types
        $known_types = ['single', 'shared', 'flat', '1 bhk', '2 bhk'];
        foreach ($known_types as $kt) {
            if (strpos($search_query_lower, $kt) !== false) {
                $sql .= " AND LOWER(l.room_type) LIKE ?";
                $params[] = '%' . $kt . '%';
                $types .= 's';
                $search_query_lower = trim(str_replace($kt, '', $search_query_lower));
            }
        }
        
        // 3. Remaining terms search
        $remaining_terms = array_filter(explode(' ', $search_query_lower));
        foreach ($remaining_terms as $term) {
            if (in_array($term, ['in', 'at', 'on', 'room', 'rooms', 'for', 'rent']) || strlen($term) <= 2) {
                continue; 
            }
            $sql .= " AND (LOWER(l.description) LIKE ? OR LOWER(l.address) LIKE ?)";
            $like_search = "%$term%";
            array_push($params, $like_search, $like_search);
            $types .= 'ss';
        }
    }
    
    $sql .= " ORDER BY l.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $listings = $result->fetch_all(MYSQLI_ASSOC);
    
    $stmt->close();
    closeDBConnection($conn);
    
    return $listings;
}

/**
 * Get owner dashboard statistics
 */
function getOwnerStats($owner_id) {
    $conn = getDBConnection();
    
    $stats = [
        'total_listings' => 0,
        'total_inquiries' => 0,
        'pending_reports' => 0
    ];
    
    // Total listings
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM listings WHERE owner_id = ?");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_listings'] = $row['total'];
    }
    $stmt->close();
    
    // Total inquiries
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM inquiries WHERE owner_id = ?");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['total_inquiries'] = $row['total'];
    }
    $stmt->close();
    
    // Pending reports
    $stmt = $conn->prepare("SELECT COUNT(r.report_id) as total FROM reports r INNER JOIN listings l ON r.listing_id = l.listing_id WHERE l.owner_id = ? AND r.status = 'pending'");
    $stmt->bind_param('i', $owner_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $stats['pending_reports'] = $row['total'];
    }
    $stmt->close();
    
    closeDBConnection($conn);
    return $stats;
}

/**
 * Get listing details with owner info
 */
function getListingDetails($listing_id) {
    $conn = getDBConnection();
    
    $query = "
        SELECT 
            l.*,
            u.name AS owner_name,
            u.email AS owner_email,
            u.phone AS owner_phone,
            u.profile_image AS owner_profile_image
        FROM listings l
        INNER JOIN users u ON l.owner_id = u.user_id
        WHERE l.listing_id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $listing_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $listing = $result->fetch_assoc();
    $stmt->close();
    
    closeDBConnection($conn);
    return $listing;
}

/**
 * Get listing images
 */
function getListingImages($listing_id) {
    $conn = getDBConnection();
    
    $image_query = "SELECT image_path FROM listing_images WHERE listing_id = ?";
    $image_stmt = $conn->prepare($image_query);
    $image_stmt->bind_param("i", $listing_id);
    $image_stmt->execute();
    $image_result = $image_stmt->get_result();
    $images = $image_result->fetch_all(MYSQLI_ASSOC);
    $image_stmt->close();
    
    closeDBConnection($conn);
    return $images;
}

/**
 * Get reviews for a listing
 */
function getListingReviews($listing_id) {
    $conn = getDBConnection();
    
    $reviews_query = "
        SELECT r.*, u.name as reviewer_name, u.profile_image as reviewer_image
        FROM reviews r
        JOIN users u ON r.seeker_id = u.user_id
        WHERE r.listing_id = ?
        ORDER BY r.created_at DESC
    ";
    
    $reviews_stmt = $conn->prepare($reviews_query);
    $reviews_stmt->bind_param("i", $listing_id);
    $reviews_stmt->execute();
    $reviews_result = $reviews_stmt->get_result();
    $reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
    $reviews_stmt->close();
    
    closeDBConnection($conn);
    return $reviews;
}

/**
 * Get replies for reviews
 */
function getReviewReplies($review_ids = []) {
    if (empty($review_ids)) return [];
    
    $conn = getDBConnection();
    
    $placeholders = str_repeat('?,', count($review_ids) - 1) . '?';
    $types = str_repeat('i', count($review_ids));
    
    $query = "
        SELECT rr.*, u.name as user_name 
        FROM review_replies rr 
        JOIN users u ON rr.user_id = u.user_id 
        WHERE rr.review_id IN ($placeholders)
        ORDER BY rr.created_at ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$review_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $replies = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    closeDBConnection($conn);
    
    // Group replies by review_id
    $grouped_replies = [];
    foreach ($replies as $reply) {
        $grouped_replies[$reply['review_id']][] = $reply;
    }
    
    return $grouped_replies;
}

/**
 * Get similar listings
 */
function getSimilarListings($listing_id, $city, $room_type, $limit = 4) {
    $conn = getDBConnection();
    
    $query = "
        SELECT l.listing_id, l.city, l.address, l.price, l.room_type,
               (SELECT image_path FROM listing_images WHERE listing_id = l.listing_id LIMIT 1) AS main_image,
               (SELECT AVG(rating) FROM reviews WHERE listing_id = l.listing_id) AS avg_rating
        FROM listings l
        WHERE l.listing_id != ? AND l.city = ? AND l.room_type = ?
        ORDER BY l.created_at DESC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issi", $listing_id, $city, $room_type, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $similar = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    closeDBConnection($conn);
    return $similar;
}

/**
 * Format price display
 */
function formatPrice($price) {
    return 'Rs. ' . number_format($price) . '/month';
}

/**
 * Format date display
 */
function formatDate($date_string) {
    return date('M d, Y', strtotime($date_string));
}

/**
 * Format datetime display
 */
function formatDateTime($date_string) {
    return date('M d, Y H:i', strtotime($date_string));
}

/**
 * Get star rating HTML
 */
function getStarRating($rating) {
    $stars = '';
    $full_stars = floor($rating);
    $has_half_star = ($rating - $full_stars) >= 0.5;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $stars .= '&#11088;';
        } else if ($i == $full_stars + 1 && $has_half_star) {
            $stars .= '&#11088;';
        } else {
            $stars .= '&#9734;';
        }
    }
    
    return $stars;
}
?>
