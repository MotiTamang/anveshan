<?php
// submit_review.php
session_start();
require_once 'db_connection.php';

// Check if user is logged in and is a seeker
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'seeker') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seeker_id = intval($_SESSION['user']['user_id']);
    $listing_id = intval($_POST['listing_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($listing_id > 0 && $rating >= 1 && $rating <= 5 && !empty($comment)) {
        $conn = getDBConnection();
        
        // Prevent duplicate reviews from the same seeker for the same listing
        $check_stmt = $conn->prepare("SELECT review_id FROM reviews WHERE listing_id = ? AND seeker_id = ?");
        $check_stmt->bind_param('ii', $listing_id, $seeker_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        
        if ($check_res->num_rows > 0) {
            $_SESSION['flash'] = 'You have already reviewed this listing.';
        } else {
            $stmt = $conn->prepare("INSERT INTO reviews (listing_id, seeker_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiis', $listing_id, $seeker_id, $rating, $comment);
            
            if ($stmt->execute()) {
                $_SESSION['flash'] = 'Review added successfully!';
                
                // Fetch owner info to send email notification
                $owner_stmt = $conn->prepare("SELECT u.email, l.room_type, l.address FROM listings l JOIN users u ON l.owner_id = u.user_id WHERE l.listing_id = ?");
                $owner_stmt->bind_param('i', $listing_id);
                $owner_stmt->execute();
                $owner_data = $owner_stmt->get_result()->fetch_assoc();
                $owner_stmt->close();
                
                if ($owner_data && !empty($owner_data['email'])) {
                    require_once 'mail_helper.php';
                    $subject = "New " . $rating . "-Star Review on Your Listing!";
                    $body = "Hello,\n\nYou just received a new review on your listing: " . $owner_data['room_type'] . " in " . $owner_data['address'] . ".\n\nRating: " . $rating . " Stars\nComment: " . $comment . "\n\nLog in to your Owner Dashboard to view or reply to this review.\n\nRegards,\nThe Anveshan Team";
                    send_smtp_mail($owner_data['email'], $subject, $body);
                }

            } else {
                $_SESSION['flash'] = 'Error adding review.';
            }
            $stmt->close();
        }
        
        $check_stmt->close();
        closeDBConnection($conn);
        
        header('Location: listing_detail_page.php?listing_id=' . $listing_id);
        exit();
    } else {
        // Invalid input
        if ($listing_id > 0) {
            header('Location: listing_detail_page.php?listing_id=' . $listing_id);
        } else {
            header('Location: rooms.php');
        }
        exit();
    }
}
header('Location: rooms.php');
exit();
?>
