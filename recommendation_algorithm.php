<?php
function cosine_similarity($A, $B) {
    $dot_product = 0;
    $magnitudeA  = 0;
    $magnitudeB  = 0;

    for ($i = 0; $i < count($A); $i++) {
        $dot_product += $A[$i] * $B[$i];
        $magnitudeA  += pow($A[$i], 2);
        $magnitudeB  += pow($B[$i], 2);
    }

    if ($magnitudeA == 0 || $magnitudeB == 0) return 0;

    return $dot_product / (sqrt($magnitudeA) * sqrt($magnitudeB));
}

function build_vector($listing, $cities, $room_types, $amenities_set, $max_price,
                      $weight_city, $weight_room_type, $weight_price, $weight_amenities, $weight_rating) {
    $vector = [];

    $listing_city = strtolower(trim($listing['city'] ?? ''));
    foreach ($cities as $c) {
        $vector[] = ($listing_city === $c ? 1 : 0) * $weight_city;
    }

    $listing_rtype = strtolower(trim($listing['room_type'] ?? ''));
    foreach ($room_types as $rt) {
        $vector[] = ($listing_rtype === $rt ? 1 : 0) * $weight_room_type;
    }

    $vector[] = (floatval($listing['price']) / $max_price) * $weight_price;

    $listing_amenities = array_map('strtolower', array_filter(array_map('trim', explode(',', $listing['amenities'] ?? ''))));
    foreach ($amenities_set as $am) {
        $vector[] = (in_array($am, $listing_amenities) ? 1 : 0) * $weight_amenities;
    }

    $vector[] = (floatval($listing['avg_rating'] ?? 0) / 5.0) * $weight_rating;

    return $vector;
}


function get_recommendations($conn, $current_listing, $limit = 4) {
    $current_id = intval($current_listing['listing_id']);

    $stmt = $conn->prepare("
        SELECT l.listing_id, l.city, l.room_type, l.price, l.amenities, l.address,
               (SELECT image_path FROM listing_images WHERE listing_id = l.listing_id LIMIT 1) AS main_image,
               IFNULL((SELECT AVG(r.rating) FROM reviews r WHERE r.listing_id = l.listing_id), 0) AS avg_rating,
               IFNULL((SELECT COUNT(*) FROM reviews r WHERE r.listing_id = l.listing_id), 0)      AS review_count
        FROM listings l
        WHERE l.status = 'active'
    ");
    $stmt->execute();
    $result     = $stmt->get_result();
    $all_listings = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($all_listings)) return [];

    $cities        = [];
    $room_types    = [];
    $amenities_set = [];
    $max_price     = 0;

    $weight_city      = 3.0;
    $weight_room_type = 2.0;
    $weight_price     = 1.0;
    $weight_amenities = 1.5;
    $weight_rating    = 1.0;

    foreach ($all_listings as $listing) {
        $city = strtolower(trim($listing['city'] ?? ''));
        if ($city && !in_array($city, $cities)) $cities[] = $city;

        $rtype = strtolower(trim($listing['room_type'] ?? ''));
        if ($rtype && !in_array($rtype, $room_types)) $room_types[] = $rtype;

        $amenities = array_filter(array_map('trim', explode(',', $listing['amenities'] ?? '')));
        foreach ($amenities as $am) {
            $am = strtolower($am);
            if ($am && !in_array($am, $amenities_set)) $amenities_set[] = $am;
        }

        $price = floatval($listing['price']);
        if ($price > $max_price) $max_price = $price;
    }

    $max_price = max($max_price, 1); 
    $vectors        = [];
    $listings_by_id = [];
    $current_vector = [];

    foreach ($all_listings as $listing) {
        $lid = intval($listing['listing_id']);

        $vector = build_vector(
            $listing, $cities, $room_types, $amenities_set, $max_price,
            $weight_city, $weight_room_type, $weight_price, $weight_amenities, $weight_rating
        );

        $vectors[$lid]        = $vector;
        $listings_by_id[$lid] = $listing;

        if ($lid === $current_id) {
            $current_vector = $vector;
        }
    }

    if (empty($current_vector)) {
        $current_vector = build_vector(
            $current_listing, $cities, $room_types, $amenities_set, $max_price,
            $weight_city, $weight_room_type, $weight_price, $weight_amenities, $weight_rating
        );
    }

    $recommendations = [];

    foreach ($vectors as $lid => $vector) {
        if ($lid === $current_id) continue; 

        $similarity       = cosine_similarity($current_vector, $vector);
        $score_out_of_100 = round($similarity * 100, 2);

        $listing                    = $listings_by_id[$lid];
        $listing['relevance_score'] = $score_out_of_100;
        $listing['similarity']      = $similarity;

        $recommendations[] = $listing;
    }

    usort($recommendations, function ($a, $b) {
        if ($b['similarity'] != $a['similarity']) {
            return $b['similarity'] <=> $a['similarity'];
        }
        return $b['avg_rating'] <=> $a['avg_rating'];
    });

    return array_slice($recommendations, 0, $limit);
}
?>