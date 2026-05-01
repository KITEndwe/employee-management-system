<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ems_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

/**
 * sanitize() — strips tags, trims, and escapes for SQL.
 * Does NOT run htmlspecialchars so email addresses are never mangled.
 */
function sanitize($conn, $data) {
    return $conn->real_escape_string(trim(strip_tags($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function requireAdmin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: ../index.php');
        exit();
    }
}

function requireEmployee() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
        header('Location: ../index.php');
        exit();
    }
}

function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit();
    }
}
?>
