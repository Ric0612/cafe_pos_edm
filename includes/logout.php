<?php
session_start();

// Log the logout action
if (isset($_SESSION['user_ID']) && isset($_SESSION['username'])) {
    include('db-conn.php');
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $log_sql = "INSERT INTO login_audit_logs (user_ID, username, action, ip_address, user_agent) VALUES (?, ?, 'LOGOUT', ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param("isss", $_SESSION['user_ID'], $_SESSION['username'], $ip, $user_agent);
    $log_stmt->execute();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear any output buffering
while (ob_get_level()) {
    ob_end_clean();
}

// Set cache-control headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Past date to ensure expiration

// Redirect to login page
header("Location: ../index.php");
exit();