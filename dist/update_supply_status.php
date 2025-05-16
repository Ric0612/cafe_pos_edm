<?php
session_start();
header('Content-Type: application/json');

// Verify user is logged in and has manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database connection
include('../includes/db-conn.php');

// Get JSON data from request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Extract data
$order_id = $data['order_id'] ?? null;
$status = $data['status'] ?? null;

// Validate data
if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate status
$valid_statuses = ['pending', 'preparing', 'out_for_delivery', 'delivered'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Update order status
    $update_query = "UPDATE supply_orders SET status = ? WHERE order_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $status, $order_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update order status");
    }

    // If status is 'delivered', update product stock
    if ($status === 'delivered') {
        // Get order details
        $order_query = "SELECT product_id, quantity FROM supply_orders WHERE order_id = ?";
        $stmt = $conn->prepare($order_query);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        if ($order) {
            // Update product stock
            $update_stock = "UPDATE products SET stock = stock + ? WHERE product_ID = ?";
            $stmt = $conn->prepare($update_stock);
            $stmt->bind_param("ii", $order['quantity'], $order['product_id']);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update product stock");
            }
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection
$conn->close(); 