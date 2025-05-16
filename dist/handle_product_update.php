<?php
session_start();
require_once '../includes/db-conn.php';

// Handle GET request to fetch product details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_ID = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    echo json_encode($product);
    exit();
}

// Handle POST request to update product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $user_id = $_SESSION['user_ID'];
    $username = $_SESSION['username'];

    // Get the old product data for audit logging
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_ID = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $old_product = $stmt->get_result()->fetch_assoc();

    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock = ? WHERE product_ID = ?");
    $stmt->bind_param("sssdsi", 
        $_POST['name'],
        $_POST['category'],
        $_POST['description'],
        $_POST['price'],
        $_POST['stock'],
        $product_id
    );

    if ($stmt->execute()) {
        // Log changes in audit_logs_products
        $fields = ['name', 'category', 'description', 'price', 'stock'];
        foreach ($fields as $field) {
            if ($old_product[$field] != $_POST[$field]) {
                $audit_stmt = $conn->prepare("INSERT INTO audit_logs_products (user_ID, username, product_ID, action, field_changed, old_value, new_value) VALUES (?, ?, ?, 'UPDATE', ?, ?, ?)");
                $audit_stmt->bind_param("iissss", 
                    $user_id,
                    $username,
                    $product_id,
                    $field,
                    $old_product[$field],
                    $_POST[$field]
                );
                $audit_stmt->execute();
            }
        }
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating product']);
    }
    exit();
}
?> 