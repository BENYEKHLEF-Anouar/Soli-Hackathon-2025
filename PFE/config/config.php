<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
$host = 'localhost';
$dbname = 'mentora2';
$user = 'root';
$pass = '';

// Connect to the database with error handling
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log error securely and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Erreur: Impossible de se connecter à la base de données. Veuillez réessayer plus tard.");
}

// Enable error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>