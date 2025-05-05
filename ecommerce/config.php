<?php
$host = 'localhost';
$username = 'root';
$password = ''; // Empty string if no password
$database = 'metadrop';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>