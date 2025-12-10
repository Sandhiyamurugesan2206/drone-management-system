<?php
// Database/db.php
// Minimal mysqli connection used by the project. Adjust credentials for your environment.

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';        // <-- set your MySQL password
$DB_NAME = 'ooad';    // <-- set your DB name

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    // In production, don't echo DB errors - log them instead.
    die("Database connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}

// Set charset
$mysqli->set_charset('utf8mb4');

// Backwards compatibility: many files in this project expect $conn to be a mysqli
// instance, so export $conn for them as an alias to $mysqli.
$conn = $mysqli;
