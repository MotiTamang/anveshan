<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'];
$user_id = intval($user['user_id']);
$role = $user['role'];
$redirect_url = ($role === 'owner') ? 'owner_profile.php' : 'seeker_profile.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = getDBConnection();
    
    if ($_POST['action'] === 'upload_image' && isset($_FILES['profile_pic'])) {
        $file = $_FILES['profile_pic'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = $file['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $path)) {
                    // Update DB
                    $stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE user_id = ?");
                    $stmt->bind_param('si', $path, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $_SESSION['flash'] = 'Profile image updated successfully!';
                } else {
                    $_SESSION['flash_error'] = 'Failed to save uploaded file.';
                }
            } else {
                $_SESSION['flash_error'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            }
        } else {
            $_SESSION['flash_error'] = 'File upload error.';
        }
    } elseif ($_POST['action'] === 'delete_image') {
        // Fetch old image to delete file
        $stmt = $conn->prepare("SELECT profile_image FROM users WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        if (!empty($data['profile_image']) && file_exists($data['profile_image'])) {
            unlink($data['profile_image']); // physically delete file
        }
        
        // Remove from DB
        $stmt2 = $conn->prepare("UPDATE users SET profile_image = NULL WHERE user_id = ?");
        $stmt2->bind_param('i', $user_id);
        $stmt2->execute();
        $stmt2->close();
        
        $_SESSION['flash'] = 'Profile image deleted.';
    }
    
    closeDBConnection($conn);
    header('Location: ' . $redirect_url);
    exit();
} else {
    header('Location: ' . $redirect_url);
    exit();
}
?>
