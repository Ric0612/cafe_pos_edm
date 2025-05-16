<?php
// Include access control
include('../includes/access_control.php');

// Strictly enforce manager-only access
enforce_access('audit_logs');

// Verify user is logged in and is a manager
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}

include('../includes/db-conn.php');

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get audit type from request
$audit_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Build base queries for each type
$sales_query = "
    SELECT 
        als.timestamp,
        als.username,
        als.action,
        als.field_changed,
        als.old_value,
        als.new_value,
        'sales' as type,
        als.transaction_ID as details,
        NULL as order_id
    FROM audit_logs_sales als
    WHERE DATE(als.timestamp) BETWEEN ? AND ?";

$inventory_query = "
    SELECT 
        alp.timestamp,
        alp.username,
        alp.action,
        alp.field_changed,
        alp.old_value,
        alp.new_value,
        'inventory' as type,
        NULL as details,
        NULL as order_id
    FROM audit_logs_products alp
    WHERE DATE(alp.timestamp) BETWEEN ? AND ?";

$login_query = "
    SELECT 
        lal.timestamp,
        lal.username,
        lal.action,
        CASE 
            WHEN lal.action = 'LOGIN' THEN 'Login Time'
            WHEN lal.action = 'LOGOUT' THEN 'Logout Time'
        END as field_changed,
        NULL as old_value,
        DATE_FORMAT(lal.timestamp, '%h:%i:%s %p') as new_value,
        'login' as type,
        DATE_FORMAT(lal.timestamp, '%Y-%m-%d') as details,
        NULL as order_id
    FROM login_audit_logs lal
    WHERE DATE(lal.timestamp) BETWEEN ? AND ?";

$orders_query = "
    SELECT 
        alo.timestamp,
        alo.username,
        alo.action,
        alo.status_change as field_changed,
        alo.old_status as old_value,
        alo.new_status as new_value,
        'orders' as type,
        alo.details,
        alo.order_id
    FROM audit_logs_orders alo
    WHERE DATE(alo.timestamp) BETWEEN ? AND ?";

// Build final query based on filter
$count_query = ""; // For counting total records
$final_query = "";

if ($audit_type === 'sales') {
    $final_query = $sales_query;
    $count_query = "SELECT COUNT(*) as total FROM (" . $sales_query . ") as count_table";
    $params = [$start_date, $end_date];
    $types = "ss";
} elseif ($audit_type === 'inventory') {
    $final_query = $inventory_query;
    $count_query = "SELECT COUNT(*) as total FROM (" . $inventory_query . ") as count_table";
    $params = [$start_date, $end_date];
    $types = "ss";
} elseif ($audit_type === 'login') {
    $final_query = $login_query;
    $count_query = "SELECT COUNT(*) as total FROM (" . $login_query . ") as count_table";
    $params = [$start_date, $end_date];
    $types = "ss";
} elseif ($audit_type === 'orders') {
    $final_query = $orders_query;
    $count_query = "SELECT COUNT(*) as total FROM (" . $orders_query . ") as count_table";
    $params = [$start_date, $end_date];
    $types = "ss";
} else {
    $final_query = "(" . $sales_query . ") UNION ALL (" . $inventory_query . ") UNION ALL (" . $login_query . ") UNION ALL (" . $orders_query . ")";
    $count_query = "SELECT COUNT(*) as total FROM (" . $final_query . ") as count_table";
    $params = [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date];
    $types = "ssssssss";
}

// Add sorting
$final_query .= " ORDER BY timestamp DESC";

// Get total records for pagination
$stmt = $conn->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];

// Pagination settings
$records_per_page = 10;
$total_pages = ceil($total_records / $records_per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Add pagination to final query
$final_query .= " LIMIT ? OFFSET ?";
$params[] = $records_per_page;
$params[] = $offset;
$types .= "ii";

// Execute final query
$stmt = $conn->prepare($final_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Audit Logs - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <style>
        .audit-log {
            border-left: 4px solid;
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.25rem;
        }
        .audit-log.sales { border-left-color: #0d6efd; }
        .audit-log.inventory { border-left-color: #198754; }
        .audit-log.login { border-left-color: #6c757d; }
        .audit-log.orders { border-left-color: #dc3545; }
        .timestamp {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .action-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.6em;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        .pagination {
            margin-top: 1rem;
            justify-content: center;
        }
        .audit-log.login .login-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.6em;
        }
        .audit-log.login .login-time {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
        }
        .audit-log.login .login-date {
            font-size: 0.9rem;
            color: #666;
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
                <h1 class="mt-4">Audit Logs</h1>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="all" <?php echo $audit_type === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="sales" <?php echo $audit_type === 'sales' ? 'selected' : ''; ?>>Sales</option>
                                <option value="inventory" <?php echo $audit_type === 'inventory' ? 'selected' : ''; ?>>Inventory</option>
                                <option value="login" <?php echo $audit_type === 'login' ? 'selected' : ''; ?>>Login</option>
                                <option value="orders" <?php echo $audit_type === 'orders' ? 'selected' : ''; ?>>Orders</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary d-block w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>

                <!-- Audit Logs -->
                <div class="row">
                    <div class="col-12">
                        <?php if ($result->num_rows === 0): ?>
                            <div class="alert alert-info">
                                No audit logs found for the selected criteria.
                            </div>
                        <?php else: ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <?php if ($row['type'] === 'login'): ?>
                                    <div class="audit-log login">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="badge bg-primary login-badge">
                                                    <?php echo strtoupper($row['type']); ?>
                                                </span>
                                                <span class="badge <?php echo $row['action'] === 'LOGIN' ? 'bg-success' : 'bg-danger'; ?> login-badge">
                                                    <?php echo $row['action']; ?>
                                                </span>
                                                <h6 class="mt-2 mb-1"><?php echo htmlspecialchars($row['username']); ?></h6>
                                                <div class="login-time">
                                                    <?php echo htmlspecialchars($row['new_value']); ?>
                                                </div>
                                                <div class="login-date">
                                                    <?php echo htmlspecialchars($row['details']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="audit-log <?php echo $row['type']; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <span class="badge bg-primary action-badge"><?php echo strtoupper($row['type']); ?></span>
                                                <span class="badge bg-secondary action-badge"><?php echo strtoupper($row['action']); ?></span>
                                                <?php if ($row['type'] === 'orders'): ?>
                                                    <span class="badge bg-info action-badge">Order #<?php echo $row['order_id']; ?></span>
                                                <?php endif; ?>
                                                <h6 class="mt-2 mb-1"><?php echo htmlspecialchars($row['username']); ?></h6>
                                                <?php if ($row['field_changed']): ?>
                                                    <p class="mb-1">
                                                        <strong><?php echo htmlspecialchars($row['field_changed']); ?>:</strong>
                                                        <?php if ($row['old_value']): ?>
                                                            <span class="text-muted"><?php echo htmlspecialchars($row['old_value']); ?></span>
                                                            <i class="fas fa-arrow-right mx-2"></i>
                                                        <?php endif; ?>
                                                        <span class="text-primary"><?php echo htmlspecialchars($row['new_value']); ?></span>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($row['type'] === 'orders' && $row['details']): ?>
                                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($row['details']); ?></p>
                                                <?php endif; ?>
                                                <?php if ($row['type'] === 'sales' && $row['details']): ?>
                                                    <div class="mt-2">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewReceipt(<?php echo $row['details']; ?>)">
                                                            <i class="fas fa-receipt"></i> View Receipt
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <span class="timestamp">
                                                <?php echo date('M d, Y h:i A', strtotime($row['timestamp'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endwhile; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination">
                                        <?php if ($current_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&type=<?php echo $audit_type; ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&type=<?php echo $audit_type; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&type=<?php echo $audit_type; ?>">
                                                    Next
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Transaction Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receiptContent">
                    <!-- Receipt content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewReceipt(transactionId) {
        // Show loading state
        const receiptContent = document.getElementById('receiptContent');
        receiptContent.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading receipt...</div>';
        
        // Show the modal
        const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        receiptModal.show();
        
        // Fetch receipt data
            fetch(`get-receipt.php?transaction_id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                    receiptContent.innerHTML = data.receipt_html;
                    } else {
                    receiptContent.innerHTML = `<div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Failed to load receipt: ${data.message}
                    </div>`;
                    }
                })
                .catch(error => {
                receiptContent.innerHTML = `<div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error loading receipt. Please try again.
                </div>`;
                    console.error('Error:', error);
                });
        }

        function printReceipt() {
            const receiptWindow = window.open('', '', 'width=600,height=600');
            receiptWindow.document.write('<html><head><title>Receipt</title>');
            receiptWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">');
        receiptWindow.document.write('<style>@media print { .no-print { display: none; } }</style>');
            receiptWindow.document.write('</head><body class="p-4">');
            receiptWindow.document.write(document.getElementById('receiptContent').innerHTML);
            receiptWindow.document.write('</body></html>');
            receiptWindow.document.close();
            
            receiptWindow.onload = function() {
                receiptWindow.print();
                receiptWindow.close();
            };
        }
    </script>
</body>
</html> 