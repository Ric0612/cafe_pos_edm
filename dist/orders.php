<?php
include('../includes/access_control.php');
include('../includes/db-conn.php');

// Enforce access control for orders module
enforce_access('orders');

// Handle order completion
if (isset($_POST['complete_order'])) {
    $order_id = $_POST['order_id'];
    $current_user_id = $_SESSION['user_ID'];
    $current_username = $_SESSION['username'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update order status to completed
        $update_sql = "UPDATE orders SET status = 'completed', completed_at = NOW() WHERE order_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();

        // Log the completion
        $log_sql = "INSERT INTO audit_logs_orders (
            order_id, 
            user_id, 
            username, 
            action, 
            status_change,
            details,
            old_status,
            new_status
        ) VALUES (?, ?, ?, 'COMPLETE', 'completed', 'Order completed', 'new', 'completed')";
        
        $stmt = $conn->prepare($log_sql);
        $stmt->bind_param("iis", $order_id, $current_user_id, $current_username);
        $stmt->execute();

        $conn->commit();
        header("Location: orders.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error completing order: " . $e->getMessage();
    }
}

// Fetch new orders
$orders_sql = "
    SELECT 
        o.order_id,
        o.created_at,
        t.transaction_id,
        t.total_amount,
        t.discount_type,
        t.discount_id,
        t.discount_amount,
        u.name as cashier_name,
        GROUP_CONCAT(
            CONCAT(oi.quantity, 'x ', p.name) 
            ORDER BY p.name 
            SEPARATOR '<br>'
        ) as items,
        GROUP_CONCAT(oi.notes SEPARATOR '<br>') as notes
    FROM orders o
    JOIN transactions t ON o.transaction_id = t.transaction_id
    JOIN users u ON t.cashier_id = u.user_ID
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_ID
    WHERE o.status = 'new'
    GROUP BY o.order_id
    ORDER BY o.created_at ASC";

$result = $conn->query($orders_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Orders - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <link href="css/styles.css" rel="stylesheet" />
    <style>
        .order-card {
            border: 1px solid #dee2e6;
            border-left: 5px solid #dc3545;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .order-time {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .order-items {
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .notes {
            font-style: italic;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .order-total {
            font-weight: bold;
            color: #6c4f3d;
            margin-top: 0.5rem;
        }
        .cashier-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .no-orders {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include('../includes/sidebar.php'); ?>
        
        <!-- Page content wrapper -->
        <div id="page-content-wrapper">
            <!-- Top navigation -->
            <?php include('../includes/nav.php'); ?>
            
            <!-- Page content -->
            <div class="container-fluid px-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="mt-4">New Orders</h1>
                    <div class="badge bg-primary fs-5">
                        <i class="fas fa-bell me-1"></i>
                        <?php echo $result->num_rows; ?> Orders
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($result->num_rows == 0): ?>
                    <div class="no-orders">
                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                        <h4>No new orders at the moment</h4>
                        <p>New orders will appear here automatically</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php while ($order = $result->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card order-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title">Order #<?php echo $order['order_id']; ?></h5>
                                            <span class="order-time">
                                                <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="cashier-info">
                                            <i class="fas fa-user me-1"></i> 
                                            <?php echo htmlspecialchars($order['cashier_name']); ?>
                                        </div>

                                        <div class="order-items mb-2">
                                            <?php echo $order['items']; ?>
                                        </div>

                                        <?php if ($order['notes']): ?>
                                            <div class="notes mb-2">
                                                <i class="fas fa-comment-alt me-1"></i>
                                                <?php echo $order['notes']; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="order-total">
                                            Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                                        </div>

                                        <?php if ($order['discount_type'] != 'none'): ?>
                                            <div class="text-success small mt-1">
                                                <?php echo ucfirst($order['discount_type']); ?> Discount: 
                                                ₱<?php echo number_format($order['discount_amount'], 2); ?>
                                                <?php if ($order['discount_id']): ?>
                                                    <br>(ID: <?php echo htmlspecialchars($order['discount_id']); ?>)
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <form method="POST" class="mt-3">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <button type="submit" name="complete_order" class="btn btn-success w-100">
                                                <i class="fas fa-check-circle me-1"></i>Complete Order
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh the page every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html> 