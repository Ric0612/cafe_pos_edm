<?php
// Ensure no output before headers
ob_start();
session_start();

// Set JSON content type header
header('Content-Type: application/json');

// Verify user is logged in and has manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
require_once('../includes/db-conn.php');

// Get JSON data from request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Extract data
$order_id = $data['order_id'] ?? null;

// Validate data
if (!$order_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Missing order ID']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if order exists and is in pending status
    $check_query = "SELECT status FROM supply_orders WHERE order_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        throw new Exception("Order not found");
    }

    if ($order['status'] !== 'pending') {
        throw new Exception("Only pending orders can be cancelled");
    }

    // Delete the order
    $delete_query = "DELETE FROM supply_orders WHERE order_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to cancel order");
    }

    if ($stmt->affected_rows === 0) {
        throw new Exception("Order could not be cancelled");
    }

    // Commit transaction
    $conn->commit();
    
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection
$conn->close(); 