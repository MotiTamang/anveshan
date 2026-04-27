<?php
// table.php - Create all required tables for Anveshan Room Finder
// NOTE: Run this once (or via a migration). Do NOT leave this file accessible in production.
// IMPORTANT: Always hash passwords before storing (password_hash), and never store plaintext passwords.

// Database credentials
$servername = "localhost";
$username = "root";
$password = ""; // default is empty in XAMPP
$dbname = "anveshan_db";
$port       = 3306;

// First, create database connection without selecting database
$conn = new mysqli($servername, $username, $password, "", $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$create_db = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($create_db) === TRUE) {
    echo "Database '$dbname' created or already exists.<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db($dbname);

// Set charset
$conn->set_charset("utf8mb4");

echo "<h3>Creating Tables...</h3>";

// Users table (must be created first - no foreign keys)
$sql_users = "CREATE TABLE IF NOT EXISTS users (
  user_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  phone VARCHAR(20),
  password VARCHAR(255) NOT NULL,
  role ENUM('owner','admin','seeker') NOT NULL DEFAULT 'seeker',
  profile_image VARCHAR(255) NULL,
  reset_otp VARCHAR(10) NULL,
  reset_expires DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_users) === TRUE) {
    echo "<p style='color: green;'>✓ Users table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating users table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_users) . "</p>";
}

// Listings table (references users)
$sql_listings = "CREATE TABLE IF NOT EXISTS listings (
  listing_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  owner_id INT UNSIGNED NOT NULL,
  city VARCHAR(100),
  address TEXT,
  price INT UNSIGNED,
  room_type VARCHAR(50),
  description TEXT,
  latitude DECIMAL(10,8),
  longitude DECIMAL(11,8),
  amenities TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_owner_id (owner_id),
  INDEX idx_city (city),
  INDEX idx_price (price),
  FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_listings) === TRUE) {
    echo "<p style='color: green;'>✓ Listings table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating listings table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_listings) . "</p>";
}

// Listing images table (references listings)
$sql_images = "CREATE TABLE IF NOT EXISTS listing_images (
  image_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_listing_id (listing_id),
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_images) === TRUE) {
    echo "<p style='color: green;'>✓ Listing images table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating listing_images table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_images) . "</p>";
}

// Reports table (references listings and users)
$sql_reports = "CREATE TABLE IF NOT EXISTS reports (
  report_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  reporter_id INT UNSIGNED NULL,
  reason TEXT NOT NULL,
  status ENUM('pending','reviewed','action_taken') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_listing_id (listing_id),
  INDEX idx_reporter_id (reporter_id),
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_reports) === TRUE) {
    echo "<p style='color: green;'>✓ Reports table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating reports table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_reports) . "</p>";
}

// Inquiries table (tracks when seekers view owner contact info)
$sql_inquiries = "CREATE TABLE IF NOT EXISTS inquiries (
  inquiry_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  seeker_id INT UNSIGNED NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_listing_id (listing_id),
  INDEX idx_seeker_id (seeker_id),
  INDEX idx_owner_id (owner_id),
  INDEX idx_viewed_at (viewed_at),
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (seeker_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_inquiries) === TRUE) {
    echo "<p style='color: green;'>✓ Inquiries table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating inquiries table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_inquiries) . "</p>";
}

// Reviews table
$sql_reviews = "CREATE TABLE IF NOT EXISTS reviews (
  review_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  seeker_id INT UNSIGNED NOT NULL,
  rating INT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (listing_id) REFERENCES listings(listing_id) ON DELETE CASCADE,
  FOREIGN KEY (seeker_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_reviews) === TRUE) {
    echo "<p style='color: green;'>✓ Reviews table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating reviews table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_reviews) . "</p>";
}

// Review replies table
$sql_review_replies = "CREATE TABLE IF NOT EXISTS review_replies (
  reply_id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  review_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  user_role VARCHAR(20) NOT NULL,
  reply_text TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_review_id (review_id),
  INDEX idx_user_id (user_id),
  FOREIGN KEY (review_id) REFERENCES reviews(review_id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_review_replies) === TRUE) {
    echo "<p style='color: green;'>✓ Review replies table created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating review_replies table: " . $conn->error . "</p>";
    echo "<p>SQL: " . htmlspecialchars($sql_review_replies) . "</p>";
}

// Verify tables exist
echo "<h3>Verification:</h3>";
$tables = ['users', 'listings', 'listing_images', 'reports', 'inquiries', 'reviews', 'review_replies'];
$all_exist = true;
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' DOES NOT EXIST</p>";
        $all_exist = false;
    }
}

if ($all_exist) {
    echo "<h3 style='color: green;'>✓ All tables created and verified successfully!</h3>";
} else {
    echo "<h3 style='color: red;'>✗ Some tables are missing. Please check errors above.</h3>";
}

$conn->close();
?>