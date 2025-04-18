<?php
// config.example.php
// This is an example configuration file for database connection.
// Rename to config.php and update with your actual values or use .env file.

// Require Composer's autoloader for phpdotenv
require 'vendor/autoload.php';

// Load environment variables from .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'your_database';
    $username = $_ENV['DB_USER'] ?? 'your_username';
    $password = $_ENV['DB_PASS'] ?? 'your_password';

    // Initialize PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log error securely and show generic message to user
    error_log("Connection failed: " . $e->getMessage());
    die("Unable to connect to the database. Please try again later.");
}
?>
