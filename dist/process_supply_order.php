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
$product_id = $data['product_id'] ?? null;
$quantity = $data['quantity'] ?? null;
$supplier_id = $data['supplier_id'] ?? null;

// Validate data
if (!$product_id || !$quantity || !$supplier_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

if ($quantity < 1) {
    echo json_encode(['success' => false, 'message' => 'Quantity must be greater than 0']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Insert supply order
    $insert_query = "INSERT INTO supply_orders (product_id, supplier_id, quantity, status, order_date) 
                     VALUES (?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iii", $product_id, $supplier_id, $quantity);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create supply order");
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Supply order created successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close connection
$conn->close(); 