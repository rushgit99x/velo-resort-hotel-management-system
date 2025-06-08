<?php
$host = 'localhost';
$dbname = 'resort_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful");
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed: Unable to connect to the database.");
}
?>