<?php
// Prevent PHP errors from being output directly
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', '../error.log');

header('Content-Type: application/json');

try {
    if (!file_exists('../includes/db-conn.php')) {
        throw new Exception('Connection file not found');
    }

    require_once '../includes/db-conn.php';

    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Product ID is required']);
        exit;
    }

    $product_id = intval($_GET['id']);
    error_log("Attempting to fetch product ID: " . $product_id);

    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed or not established");
    }

    $stmt = $conn->prepare("SELECT product_ID, name, category, description, price, stock, image FROM products WHERE product_ID = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $product_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        // Ensure all values are properly encoded
        array_walk_recursive($product, function(&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });
        $json_response = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json_response === false) {
            throw new Exception("JSON encoding failed: " . json_last_error_msg());
        }
        echo $json_response;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
    }

    if ($stmt) {
        $stmt->close();
    }
    if ($conn) {
        $conn->close();
    }

} catch (Exception $e) {
    error_log("Error in get_product.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'details' => error_get_last()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?> 