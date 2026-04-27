<?php
// admin_actions.php
// Secure backend handler for Admin Actions

session_start();

// Strict Admin validation
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

$action = $_POST['action'] ?? '';

if ($action === 'delete_user') {
    $target_user_id = intval($_POST['user_id'] ?? 0);
    
    if ($target_user_id > 0) {
        // Find all listings belonging to this user
        $stmt_list = $conn->prepare("SELECT listing_id FROM listings WHERE owner_id = ?");
        $stmt_list->bind_param('i', $target_user_id);
        $stmt_list->execute();
        $res_list = $stmt_list->get_result();
        
        while($row = $res_list->fetch_assoc()) {
            $l_id = $row['listing_id'];
            
            // Delete associated images from database
            $stmt_img = $conn->prepare("DELETE FROM listing_images WHERE listing_id = ?");
            $stmt_img->bind_param('i', $l_id);
            $stmt_img->execute();
            $stmt_img->close();
            
            // Delete associated reports
            $table_check = $conn->query("SHOW TABLES LIKE 'reports'");
            if ($table_check->num_rows > 0) {
                $stmt_rep = $conn->prepare("DELETE FROM reports WHERE listing_id = ?");
                $stmt_rep->bind_param('i', $l_id);
                $stmt_rep->execute();
                $stmt_rep->close();
            }
        }
        $stmt_list->close();
        
        // Delete listings themselves
        $stmt_del_list = $conn->prepare("DELETE FROM listings WHERE owner_id = ?");
        $stmt_del_list->bind_param('i', $target_user_id);
        $stmt_del_list->execute();
        $stmt_del_list->close();
        
        // Delete any reports made BY this user
        $table_check = $conn->query("SHOW TABLES LIKE 'reports'");
        if ($table_check->num_rows > 0) {
            $stmt_rep_user = $conn->prepare("DELETE FROM reports WHERE reporter_id = ?");
            $stmt_rep_user->bind_param('i', $target_user_id);
            $stmt_rep_user->execute();
            $stmt_rep_user->close();
        }

        // Finally, delete the user
        $stmt_del_user = $conn->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
        $stmt_del_user->bind_param('i', $target_user_id);
        
        if ($stmt_del_user->execute()) {
            $_SESSION['flash'] = '✅ User and all associated data successfully deleted.';
        } else {
            $_SESSION['flash'] = '❌ Failed to delete user.';
        }
        $stmt_del_user->close();
    }
}

closeDBConnection($conn);
header("Location: manage_users.php");
exit();
?>
