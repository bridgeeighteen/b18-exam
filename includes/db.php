<?php

require_once __DIR__ . '/../config.php';

// Establish database connection
function connectToDatabase() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Close the database connection
function closeDatabaseConnection($conn) {
    $conn->close();
}
?>