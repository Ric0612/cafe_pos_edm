<?php
session_start();
include('../includes/db-conn.php');

// Check if user is logged in and has valid user_ID
if (!isset($_SESSION['role']) || !isset($_SESSION['user_ID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized or invalid user session']);
    exit();
}

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Function to log sales audit
function logSalesAudit($user_id, $username, $transaction_id, $action, $field_changed, $old_value, $new_value) {
    global $conn;
    $sql = "INSERT INTO audit_logs_sales (
        user_ID, 
        username, 
        transaction_ID, 
        action, 
        field_changed, 
        old_value, 
        new_value, 
        ip_address, 
        user_agent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt->bind_param("isissssss", 
        $user_id,
        $username,
        $transaction_id,
        $action,
        $field_changed,
        $old_value,
        $new_value,
        $ip,
        $user_agent
    );
    
    return $stmt->execute();
}

// Start transaction
$conn->begin_transaction();

try {
    $sales_ids = [];
    $products_summary = [];
    
    // First, create the transaction record
    $sql = "INSERT INTO transactions (total_amount, discount_type, discount_id, vat_amount, discount_amount, transaction_date, cashier_id) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dssddi", 
        $data['total'],
        $data['discount_type'],
        $data['discount_id'],
        $data['vat_amount'],
        $data['discount_amount'],
        $_SESSION['user_ID']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create transaction record");
    }
    
    $transaction_id = $conn->insert_id;
    
    // Process each product as a separate sale record and transaction detail
    foreach ($data['products'] as $product) {
        // Calculate individual product total with VAT and discount
        $product_total = $product['price'] * $product['quantity'];
        
        // Insert into sales table
        $sql = "INSERT INTO sales (
                    user_ID, 
                    product_ID, 
                    quantity, 
                    total, 
                    type,
                    discount,
                    customer_payment,
                    payment_method,
                    change_amount
                ) VALUES (?, ?, ?, ?, 'Dine In', ?, ?, 'Cash', ?)";
                
        $stmt = $conn->prepare($sql);
        
        // Convert discount type to enum value
        $discount_type = 'Normal';
        if ($data['discount_type'] === 'senior') {
            $discount_type = 'Senior';
        } else if ($data['discount_type'] === 'pwd') {
            $discount_type = 'PWD';
        }
        
        $customer_payment = floatval($data['total']); // Total amount received
        $change_amount = $customer_payment - $data['total']; // Calculate change
        
        $stmt->bind_param("iiidsdd", 
            $_SESSION['user_ID'],
            $product['product_ID'],
            $product['quantity'],
            $product_total,
            $discount_type,
            $customer_payment,
            $change_amount
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert sale record for product ID: " . $product['product_ID']);
        }
        
        $sale_id = $conn->insert_id;
        $sales_ids[] = $sale_id;
        
        // Insert into transaction_details
        $sql = "INSERT INTO transaction_details (transaction_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiid",
            $transaction_id,
            $product['product_ID'],
            $product['quantity'],
            $product['price']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert transaction detail for product ID: " . $product['product_ID']);
        }
        
        // Add to products summary for audit log
        $products_summary[] = "{$product['name']} x {$product['quantity']} @ ₱{$product['price']}";
        
        // Update stock
        $sql = "UPDATE products 
                SET stock = stock - ? 
                WHERE product_ID = ? AND stock >= ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", 
            $product['quantity'], 
            $product['product_ID'], 
            $product['quantity']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update stock for product ID: " . $product['product_ID']);
        }
        
        // Check if stock went below 0
        $sql = "SELECT stock FROM products WHERE product_ID = ?";
        $check_stmt = $conn->prepare($sql);
        $check_stmt->bind_param("i", $product['product_ID']);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $current_stock = $result->fetch_assoc()['stock'];
        
        if ($current_stock < 0) {
            throw new Exception("Insufficient stock for product ID: " . $product['product_ID']);
        }
    }
    
    // Log the sales transaction in audit_logs_sales
    $transaction_summary = implode(", ", $products_summary);
    logSalesAudit(
        $_SESSION['user_ID'],
        $_SESSION['username'],
        $transaction_id,
        'CREATE',
        'new_sale',
        '',
        "New sale created: Total: ₱{$data['total']}, Products: {$transaction_summary}, Discount: {$discount_type}"
    );
    
    // Create a new order
    $create_order_sql = "INSERT INTO orders (transaction_id, status, created_at) VALUES (?, 'new', NOW())";
    $stmt = $conn->prepare($create_order_sql);
    $stmt->bind_param("i", $transaction_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create order record");
    }
    
    $order_id = $conn->insert_id;
    
    // Add order items
    $order_items_sql = "INSERT INTO order_items (order_id, product_id, quantity, notes) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($order_items_sql);
    
    foreach ($data['products'] as $product) {
        $notes = $product['notes'] ?? null;
        $stmt->bind_param("iiis", $order_id, $product['product_ID'], $product['quantity'], $notes);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create order item for product ID: " . $product['product_ID']);
        }
    }
    
    // Log order creation in audit
    $order_audit_sql = "INSERT INTO audit_logs_orders (
        order_id,
        user_id,
        username,
        action,
        status_change,
        details,
        old_status,
        new_status,
        transaction_id
    ) VALUES (?, ?, ?, 'CREATE', 'new', ?, NULL, 'new', ?)";
    
    $stmt = $conn->prepare($order_audit_sql);
    $order_details = "Order created with items: " . $transaction_summary;
    $stmt->bind_param("iissi", $order_id, $_SESSION['user_ID'], $_SESSION['username'], $order_details, $transaction_id);
    $stmt->execute();
    
    // If everything is successful, commit the transaction
    $conn->commit();
    
    // Return success response with the transaction ID
    echo json_encode([
        'success' => true, 
        'transaction_id' => $transaction_id,
        'message' => 'Transaction processed successfully'
    ]);
    
} catch (Exception $e) {
    // If there's an error, rollback the transaction
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 