<?php
$servername = "localhost";
$username = "user_name_here"; // change to your database username
$password = "password_here"; // change to your database password
$dbname = "db_name_here"; // change to your database name
$port       = 3306;

// Create connection function
function getDBConnection() {
    global $servername, $username, $password, $dbname, $port;

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname, $port);
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Set charset
    $conn->set_charset("utf8mb4");

    return $conn;
}

// Close connection function
function closeDBConnection($conn) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
?>





