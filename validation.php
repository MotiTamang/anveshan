<?php
/**
 * validation_algorithm.php — validation rules for listings.
 * City polygons: keep the same coordinates as owner_dashboard.php/add_listing.php map scripts.
 */

/** Whole rupees only (digits). No decimals -> stored as integer. */
function monthly_rent_for_db($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
        return null;
    }
    if (strlen($raw) > 6) {
        return null;
    }
    // Additional validation: reasonable price range for Nepal market
    $price = (int) $raw;
    if ($price < 2000 || $price > 100000) {
        return null;
    }
    return (int) $raw;
}

/** Approximate city polygons [lat, lng] for geo-fencing. */
function listing_city_polygon($city)
{
    static $polygons = [
        'Kathmandu' => [
            [27.735, 85.255], [27.785, 85.295], [27.780, 85.350],
            [27.745, 85.372], [27.700, 85.360], [27.675, 85.315],
            [27.690, 85.270],
        ],
        'Lalitpur' => [
            [27.700, 85.300], [27.705, 85.345], [27.690, 85.380],
            [27.655, 85.388], [27.620, 85.365], [27.618, 85.320],
            [27.640, 85.290], [27.675, 85.285],
        ],
        'Bhaktapur' => [
            [27.720, 85.350], [27.725, 85.405], [27.710, 85.470],
            [27.680, 85.495], [27.640, 85.485], [27.625, 85.430],
            [27.635, 85.370], [27.670, 85.350],
        ],
    ];
    return $polygons[$city] ?? null;
}

function point_in_polygon($lat, $lng, $polygon)
{
    $inside = false;
    $count = count($polygon);
    if ($count < 3) return false;
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $yi = (float) $polygon[$i][0];
        $xi = (float) $polygon[$i][1];
        $yj = (float) $polygon[$j][0];
        $xj = (float) $polygon[$j][1];

        $intersect = (($yi > $lat) !== ($yj > $lat))
            && ($lng < (($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi));
        if ($intersect) {
            $inside = !$inside;
        }
    }
    return $inside;
}

function pin_inside_listing_city($lat, $lng, $city)
{
    $polygon = listing_city_polygon($city);
    if (!$polygon) {
        return false;
    }
    return point_in_polygon((float)$lat, (float)$lng, $polygon);
}

function pin_inside_supported_service_area($lat, $lng)
{
    foreach (['Kathmandu', 'Lalitpur', 'Bhaktapur'] as $city) {
        if (pin_inside_listing_city($lat, $lng, $city)) {
            return true;
        }
    }
    return false;
}

/** Address must not name another of the three cities (or common valley towns outside choice). */
function address_matches_listing_city($address, $city)
{
    $a = strtolower($address);
    // Normalize separators so "sano-thimi" and "sano_thimi" are caught.
    $a = str_replace(['-', '_', ','], ' ', $a);
    $forbidden = [
        'Kathmandu' => ['lalitpur', 'patan', 'bhaktapur', 'thimi', 'sano thimi', 'madhyapur', 'suryabinayak', 'kausaltar', 'jagati'],
        'Lalitpur'  => ['kathmandu', 'balaju', 'kalanki', 'swoyambhu', 'maharajgunj', 'bhaktapur', 'thimi', 'sano thimi', 'madhyapur', 'suryabinayak'],
        'Bhaktapur' => ['kathmandu', 'balaju', 'kalanki', 'swoyambhu', 'maharajgunj', 'lalitpur', 'patan', 'jawalakhel', 'pulchowk', 'kupondole'],
    ];
    foreach ($forbidden[$city] ?? [] as $word) {
        if ($word !== '' && strpos($a, $word) !== false) {
            return false;
        }
    }
    $other_nepal = ['pokhara', 'biratnagar', 'nepalgunj', 'butwal', 'dharan', 'hetauda', 'chitwan'];
    foreach ($other_nepal as $word) {
        if (strpos($a, $word) !== false) {
            return false;
        }
    }
    return true;
}

function validate_listing($data, $files)
{
    $errors = [];

    $price = $data['price'] ?? null;
    if ($price === null || $price === '' || !preg_match('/^\d+$/', (string) $price)) {
        $errors[] = 'Monthly rent must be a whole number in Rs. (no decimals), between 2,000 and 100,000.';
    } else {
        $n = (int) $price;
        if ($n < 2000 || $n > 100000) {
            $errors[] = 'Monthly rent must be between Rs. 2,000 and Rs. 100,000.';
        }
    }

    $city = trim($data['city'] ?? '');
    $allowed = ['Kathmandu', 'Lalitpur', 'Bhaktapur'];
    if (!in_array($city, $allowed, true)) {
        $errors[] = 'City must be Kathmandu, Lalitpur, or Bhaktapur.';
    }

    $lat = isset($data['latitude']) && $data['latitude'] !== '' && $data['latitude'] !== null
        ? (float) $data['latitude'] : null;
    $lng = isset($data['longitude']) && $data['longitude'] !== '' && $data['longitude'] !== null
        ? (float) $data['longitude'] : null;

    $is_edit = !empty($data['is_edit']);

    if ($lat === null || $lng === null) {
        $errors[] = 'Please set a map pin inside the city you selected.';
    }

    if ($lat !== null && $lng !== null) {
        if (!pin_inside_supported_service_area($lat, $lng)) {
            $errors[] = 'Location must be inside Kathmandu, Lalitpur, or Bhaktapur only.';
        }
    }

    if ($lat !== null && $lng !== null && in_array($city, $allowed, true)) {
        if (!pin_inside_listing_city($lat, $lng, $city)) {
            $errors[] = 'Map pin must lie inside ' . $city . ' only (change city or move the pin).';
        }
    }

    $address = trim($data['address'] ?? '');
    if ($address !== '' && in_array($city, $allowed, true) && !address_matches_listing_city($address, $city)) {
        $errors[] = 'Full address must not name another city outside your selection (e.g. do not write Bhaktapur if you chose Kathmandu).';
    }

    // Require at least one image for add and edit.
    $imagesCount = 0;
    if (isset($files['images']) && is_array($files['images']['name'] ?? null)) {
        foreach ($files['images']['name'] as $name) {
            if (trim((string) $name) !== '') {
                $imagesCount++;
            }
        }
    }

    if ($is_edit && isset($data['conn'], $data['listing_id'])) {
        $conn = $data['conn'];
        $listing_id = (int) $data['listing_id'];
        $stmtImg = $conn->prepare('SELECT COUNT(*) AS total FROM listing_images WHERE listing_id = ?');
        $stmtImg->bind_param('i', $listing_id);
        $stmtImg->execute();
        $row = $stmtImg->get_result()->fetch_assoc();
        $stmtImg->close();
        $existingImages = (int) ($row['total'] ?? 0);
        if (($existingImages + $imagesCount) <= 0) {
            $errors[] = 'At least one listing image is required.';
        }
    } elseif (!$is_edit && $imagesCount <= 0) {
        $errors[] = 'At least one listing image is required.';
    }

    if ($price !== null && $price !== '' && preg_match('/^\d+$/', (string) $price) && isset($data['conn'], $data['owner_id']) && !$is_edit) {
        $conn = $data['conn'];
        $owner_id = (int) $data['owner_id'];
        $room_type = trim($data['room_type'] ?? '');
        $stmt = $conn->prepare('SELECT listing_id FROM listings WHERE owner_id = ? AND room_type = ? AND price = ? AND created_at > (NOW() - INTERVAL 1 DAY)');
        $stmt->bind_param('isi', $owner_id, $room_type, $price);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Duplicate warning: a listing like this was created in the last 24 hours.';
        }
        $stmt->close();
    }

    return $errors;
}

/**
 * Validate registration input data.
 */
function validate_registration($name, $email, $phone, $pass, $confirm, $role) {
    $errors = [];
    if ($name === '' || $email === '' || $phone === '' || $pass === '' || $confirm === '') {
        $errors[] = 'Please fill in all required fields, including your phone number.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    if ($name !== '' && !preg_match('/^[a-zA-Z\s]+$/', $name)) {
        $errors[] = 'Full Name must contain only letters and spaces.';
    }
    if ($phone !== '' && !preg_match('/^9\d{9}$/', $phone)) {
        $errors[] = 'Phone number must be a valid 10-digit number starting with 9.';
    }
   if ($pass !== '' && !preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/', $pass)) {
    $errors[] = 'Password must be at least 6 characters long and include at least one uppercase letter, one number, and one special character.';
    }
    if ($pass !== '' && $confirm !== '' && $pass !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (!in_array($role, ['seeker','owner'])) {
        $errors[] = 'Invalid role selected.';
    }
    return $errors;
}

/**
 * Validate login input data.
 */
function validate_login($email, $pass) {
    $errors = [];
    if ($email === '' || $pass === '') {
        $errors[] = 'Please provide email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address format.';
    }
    return $errors;
}
