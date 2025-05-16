<?php
session_start();

// Verify user is logged in
if (!isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include('../includes/db-conn.php');

if (!isset($_GET['transaction_id'])) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID not provided']);
    exit();
}

$transaction_id = intval($_GET['transaction_id']);

try {
    // Get transaction details
    $sql = "SELECT 
            t.transaction_id,
            t.total_amount,
            t.discount_type,
            t.discount_id,
            t.vat_amount,
            t.discount_amount,
            t.transaction_date,
            u.username,
            td.quantity,
            td.price,
            p.name as product_name
        FROM transactions t
        JOIN users u ON t.cashier_id = u.user_ID
        JOIN transaction_details td ON t.transaction_id = td.transaction_id
        JOIN products p ON td.product_id = p.product_ID
        WHERE t.transaction_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Receipt not found']);
        exit();
    }
    
    // Get all products from this transaction
    $products = [];
    $transaction = null;
    
    while ($row = $result->fetch_assoc()) {
        if (!$transaction) {
            $transaction = [
                'transaction_id' => $row['transaction_id'],
                'total_amount' => $row['total_amount'],
                'discount_type' => $row['discount_type'],
                'discount_id' => $row['discount_id'],
                'vat_amount' => $row['vat_amount'],
                'discount_amount' => $row['discount_amount'],
                'transaction_date' => $row['transaction_date'],
                'username' => $row['username']
            ];
        }
        $products[] = [
            'name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price'],
            'total' => $row['quantity'] * $row['price']
        ];
    }
    
    // Generate discount notice
    $discount_notice = '';
    if ($transaction['discount_type'] !== 'none') {
        $discount_notice = "<small class='text-muted'>This serves as your official receipt for<br>" . 
                         ucfirst($transaction['discount_type']) . " discount purposes.<br>" .
                         "ID Number: {$transaction['discount_id']}</small>";
    }
    
    // Generate products HTML
    $products_html = '';
    $subtotal = 0;
    foreach ($products as $product) {
        $products_html .= "
        <div class='d-flex justify-content-between mb-2'>
            <div>{$product['name']} × {$product['quantity']}</div>
            <div>₱" . number_format($product['total'], 2) . "</div>
        </div>";
        $subtotal += $product['total'];
    }
    
    // Generate receipt HTML
    $receipt_html = "
    <div class='text-center mb-4'>
        <h4>Café POS</h4>
        <p class='mb-0'>Date: " . date('M d, Y h:i:s A', strtotime($transaction['transaction_date'])) . "</p>
        <p>Transaction #: {$transaction['transaction_id']}</p>
        <p class='mb-0'>Cashier: {$transaction['username']}</p>
    </div>
    <div class='mb-4'>
        {$products_html}
        <hr>
        <div class='d-flex justify-content-between'>
            <div>Subtotal</div>
            <div>₱" . number_format($subtotal, 2) . "</div>
        </div>
        <div class='d-flex justify-content-between'>
            <div>VAT (12%)</div>
            <div>₱" . number_format($transaction['vat_amount'], 2) . "</div>
        </div>";
    
    if ($transaction['discount_type'] !== 'none') {
        $receipt_html .= "
        <div class='d-flex justify-content-between'>
            <div>" . ucfirst($transaction['discount_type']) . " Discount (20%)</div>
            <div>₱" . number_format($transaction['discount_amount'], 2) . "</div>
        </div>";
    }
    
    $receipt_html .= "
        <div class='d-flex justify-content-between fw-bold'>
            <div>Total</div>
            <div>₱" . number_format($transaction['total_amount'], 2) . "</div>
        </div>
    </div>
    <div class='text-center'>
        <p>Thank you for your purchase!</p>
        $discount_notice
    </div>";

    echo json_encode([
        'success' => true,
        'receipt_html' => $receipt_html
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load receipt: ' . $e->getMessage()
    ]);
} 