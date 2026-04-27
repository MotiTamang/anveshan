<?php
// track_inquiry.php
// Track when a seeker views owner contact information
// This creates an inquiry record

function trackInquiry($listing_id, $seeker_id, $owner_id) {
    // Only track if all IDs are valid
    if ($listing_id <= 0 || $seeker_id <= 0 || $owner_id <= 0) {
        return false;
    }
    
    require_once 'db_connection.php';
    $conn = getDBConnection();
    
    // Check if inquiry already exists for this seeker and listing (to avoid duplicates)
    // We'll allow multiple inquiries but only one per day per seeker per listing
    $check_stmt = $conn->prepare("SELECT inquiry_id FROM inquiries 
                                   WHERE listing_id = ? AND seeker_id = ? 
                                   AND DATE(viewed_at) = CURDATE() 
                                   LIMIT 1");
    $check_stmt->bind_param('ii', $listing_id, $seeker_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    // If inquiry already exists today, don't create duplicate
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        closeDBConnection($conn);
        return true; // Already tracked today
    }
    $check_stmt->close();
    
    // Insert new inquiry
    $insert_stmt = $conn->prepare("INSERT INTO inquiries (listing_id, seeker_id, owner_id) VALUES (?, ?, ?)");
    $insert_stmt->bind_param('iii', $listing_id, $seeker_id, $owner_id);
    $result = $insert_stmt->execute();
    $insert_stmt->close();
    closeDBConnection($conn);
    
    return $result;
}
?>

