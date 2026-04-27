<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$conn = getDBConnection();

// Fallback logic to ensure table exists before inserting
$setup_sql = "CREATE TABLE IF NOT EXISTS review_replies (
    reply_id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    user_id INT NOT NULL,
    user_role VARCHAR(20) NOT NULL,
    reply_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(review_id) ON DELETE CASCADE
)";
$conn->query($setup_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $review_id = intval($_POST['review_id'] ?? 0);
    $reply_text = trim($_POST['reply_text'] ?? '');
    $return_url = $_POST['return_url'] ?? 'indexmain.php'; // By default return to listings or dashboard depending on where it was called from
    
    $user_id = intval($_SESSION['user']['user_id']);
    $user_role = $_SESSION['user']['role'];

    if ($review_id > 0 && !empty($reply_text)) {
        
        $stmt = $conn->prepare("INSERT INTO review_replies (review_id, user_id, user_role, reply_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $review_id, $user_id, $user_role, $reply_text);
        
        if ($stmt->execute()) {
            $_SESSION['flash'] = 'Reply added successfully!';
        } else {
            $_SESSION['flash'] = 'Error adding reply: ' . htmlspecialchars($stmt->error);
        }
        $stmt->close();
    } else {
        $_SESSION['flash'] = 'Reply cannot be empty.';
    }
    
    closeDBConnection($conn);
    header('Location: ' . $return_url);
    exit();
}

header('Location: indexmain.php');
exit();
?>
