<?php
// report_actions.php
session_start();

if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php");
    exit();
}

require_once 'db_connection.php';
$conn = getDBConnection();

// Check and add status column to listings if it doesn't exist
$col_check = $conn->query("SHOW COLUMNS FROM listings LIKE 'status'");
if ($col_check->num_rows == 0) {
    $conn->query("ALTER TABLE listings ADD status ENUM('active', 'suspended', 'rejected') DEFAULT 'active'");
}

$action = $_POST['action'] ?? '';
$report_id = intval($_POST['report_id'] ?? 0);

if ($report_id > 0) {
    // Get listing_id and owner info
    $stmt = $conn->prepare("SELECT r.listing_id, u.email, u.name FROM reports r JOIN listings l ON r.listing_id = l.listing_id JOIN users u ON l.owner_id = u.user_id WHERE r.report_id = ?");
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $report_data = $res->fetch_assoc();
    $stmt->close();

    if ($report_data) {
        $listing_id = $report_data['listing_id'];
        $owner_email = $report_data['email'];

        if ($action === 'suspend') {
            // Delete listing entirely (this cascades and deletes the report too)
            $stmt1 = $conn->prepare("DELETE FROM listings WHERE listing_id = ?");
            $stmt1->bind_param('i', $listing_id);
            $stmt1->execute();
            $stmt1->close();
            
            $_SESSION['flash'] = "✅ Listing has been permanently deleted.";
            
        } elseif ($action === 'dismiss') {
            $stmt2 = $conn->prepare("UPDATE reports SET status = 'dismissed' WHERE report_id = ?");
            $stmt2->bind_param('i', $report_id);
            $stmt2->execute();
            $stmt2->close();
            
            $_SESSION['flash'] = "✅ Report has been dismissed.";
        }
    }
}

closeDBConnection($conn);
header("Location: admin_dashboard.php");
exit();
?>
