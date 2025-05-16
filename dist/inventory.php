<?php
session_start();

// Verify user is logged in and has manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}

$user_role = $_SESSION['role'];

// Include connections
include('../includes/db-conn.php');
include('handle_inventory.php');

// Add supplier order status check
$pending_orders_query = "SELECT so.*, p.name as product_name, s.name as supplier_name 
                        FROM supply_orders so 
                        JOIN products p ON so.product_id = p.product_ID 
                        JOIN suppliers s ON so.supplier_id = s.supplier_id 
                        WHERE so.status != 'delivered'
                        ORDER BY so.order_date DESC";
$pending_orders = $conn->query($pending_orders_query);

// Get default supplier ID (using the first supplier for simplicity)
$supplier_query = "SELECT supplier_id FROM suppliers LIMIT 1";
$supplier_result = $conn->query($supplier_query);
$default_supplier_id = $supplier_result->fetch_assoc()['supplier_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Inventory Management - Café POS</title>
    <link rel="icon" type="image/x-icon" href="#" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <!-- SweetAlert2 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="https://code.iconify.design/1/1.0.7/iconify.min.js"></script>
    <style>

        /* Custom green background and rounded icon button */
.btn-success {
    background-color: #28a745; /* Custom Green color */
    border-color: #28a745;
    border-radius: 20%; /* Make the button round */
    width: 40px;
    height: 40px;
    padding: 5px;
    font-size: 20px; /* Adjust the icon size */
}

.btn-success:hover {
    background-color: #218838; /* Darker green on hover */
    border-color: #1e7e34;
}

/* Action buttons styling */
.action-btn {
    width: 35px;
    height: 35px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin: 0 2px;
}

.action-btn i {
    font-size: 16px;
}

/* Make the buttons in the group display inline */
.btn-group {
    display: inline-flex;
    gap: 5px;
}

.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
}

/* Add hover effect while maintaining the warning/danger background */
.table-warning:hover {
    background-color: #fff3cd !important;
}
.table-danger:hover {
    background-color: #f8d7da !important;
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
    <h1 class="mt-4">Café Inventory</h1>

    <!-- Display Success Message -->
    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $_SESSION['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['message']);
    }
    ?>

                <!-- Alert Section for Low/Out of Stock -->
                <?php
                $low_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock <= 10 AND stock > 0")->fetch_assoc()['count'];
                $out_stock_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE stock = 0")->fetch_assoc()['count'];

                if ($low_stock_count > 0 || $out_stock_count > 0): ?>
                    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>
                                <?php if ($out_stock_count > 0): ?>
                                    <strong><?php echo $out_stock_count; ?></strong> product(s) are out of stock!<br>
                                <?php endif; ?>
                                <?php if ($low_stock_count > 0): ?>
                                    <strong><?php echo $low_stock_count; ?></strong> product(s) are running low on stock!
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

    <!-- Search and Filter Form -->
                <form action="inventory.php" method="GET" class="mb-4">
                    <div class="row g-3 align-items-end">
            <div class="col-md-4">
                            <label class="form-label">Search Products</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Enter product ID or name">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
            </div>
            <div class="col-md-4">
                            <label class="form-label">Category Filter</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                                <option value="Cold Drinks" <?php echo $category_filter === 'Cold Drinks' ? 'selected' : ''; ?>>Cold Drinks</option>
                                <option value="Hot Drinks" <?php echo $category_filter === 'Hot Drinks' ? 'selected' : ''; ?>>Hot Drinks</option>
                                <option value="Snacks" <?php echo $category_filter === 'Snacks' ? 'selected' : ''; ?>>Snacks</option>
                </select>
            </div>
            <div class="col-md-4">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i>
                </button>
                                <?php if (!empty($search) || !empty($category_filter)) : ?>
                                    <a href="inventory.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
            </div>
        </div>
    </form>

                <!-- Results Summary -->
                <?php if (!empty($search) || !empty($category_filter)) : ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Showing results 
                        <?php if (!empty($search)) echo 'for "' . htmlspecialchars($search) . '"'; ?>
                        <?php if (!empty($category_filter)) echo ' in category "' . htmlspecialchars($category_filter) . '"'; ?>
                        (<?php echo $total_records; ?> items found)
                    </div>
                <?php endif; ?>

                <!-- Button to trigger add product modal -->
                <button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus"></i>
                </button>

                <!-- Add this before the main inventory table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-truck me-1"></i>
                        Pending Supply Orders
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="supplyOrdersTable">
                                <thead>
                                    <tr>
                                        <th>Order Date</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Supplier</th>
                                        <th>Status</th>
                                        <th>ETA</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($order = $pending_orders->fetch_assoc()): ?>
                                        <tr data-order-id="<?php echo $order['order_id']; ?>" data-status="<?php echo $order['status']; ?>">
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($order['order_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] === 'pending' ? 'warning' : 
                                                        ($order['status'] === 'preparing' ? 'info' : 
                                                        ($order['status'] === 'out_for_delivery' ? 'primary' : 'success')); 
                                                ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $order['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="countdown-cell"></td>
                                            <td>
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <button class="btn btn-danger action-btn" 
                                                            onclick="cancelOrder(<?php echo $order['order_id']; ?>)"
                                                            title="Cancel Order">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
            <th>ID</th>
            <th>Image</th>
            <th>Name</th>
            <th>Category</th>
            <th>Description</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
                                    $imagePath = '../uploads/' . $row['image'];
                                    $imageSrc = file_exists($imagePath) ? $imagePath : '../img/default-image.jpg';
                                    $rowClass = '';
                                    if ($row['stock'] == 0) {
                                        $rowClass = 'table-danger';
                                    } elseif ($row['stock'] <= 10) {
                                        $rowClass = 'table-warning';
                                    }
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo htmlspecialchars($row['product_ID']); ?></td>
                                        <td><img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;"></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td>₱<?php echo number_format($row['price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['stock']); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button onclick="openUpdateModal(<?php echo $row['product_ID']; ?>)" 
                                                        class="btn btn-warning action-btn"
                                                        title="Edit Product">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button onclick="confirmDelete(<?php echo $row['product_ID']; ?>)" 
                                                        class="btn btn-danger action-btn"
                                                        title="Delete Product">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php if ($row['stock'] <= 10): ?>
                                                    <button class="btn btn-warning action-btn order-stock-btn" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#orderStockModal" 
                                                            data-product-id="<?php echo $row['product_ID']; ?>"
                                                            data-product-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                            title="Order Stock">
                                                        <i class="fas fa-truck"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
} else {
                                echo '<tr><td colspan="8" class="text-center">No products found</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1) : ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1) : ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php 
                                        echo (!empty($search) ? '&search='.urlencode($search) : '');
                                        echo (!empty($category_filter) ? '&category='.urlencode($category_filter) : '');
                                    ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1);
                                        echo (!empty($search) ? '&search='.urlencode($search) : '');
                                        echo (!empty($category_filter) ? '&category='.urlencode($category_filter) : '');
                                    ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            // Show page numbers
                            for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                echo '<a class="page-link" href="?page=' . $i;
                                if (!empty($search)) echo '&search=' . urlencode($search);
                                if (!empty($category_filter)) echo '&category=' . urlencode($category_filter);
                                echo '">' . $i . '</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages) : ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1);
                                        echo (!empty($search) ? '&search='.urlencode($search) : '');
                                        echo (!empty($category_filter) ? '&category='.urlencode($category_filter) : '');
                                    ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages;
                                        echo (!empty($search) ? '&search='.urlencode($search) : '');
                                        echo (!empty($category_filter) ? '&category='.urlencode($category_filter) : '');
                                    ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
<script>
    function confirmDelete(productId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'inventory.php?delete_id=' + productId;
            }
        });
    }

    // Handle Order Stock button click
    document.querySelectorAll('.order-stock-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const productName = this.dataset.productName;
            document.getElementById('orderProductId').value = productId;
            document.getElementById('orderProductName').value = productName;
            document.getElementById('orderProductNameDisplay').value = productName;
        });
    });

    function submitOrder() {
        const productId = document.getElementById('orderProductId').value;
        const quantity = document.getElementById('orderQuantity').value;
        
        if (!quantity || quantity < 1) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Quantity',
                text: 'Please enter a valid quantity (minimum 1)'
            });
            return;
        }

        fetch('process_supply_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: quantity,
                supplier_id: <?php echo $default_supplier_id; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal and show success message
                bootstrap.Modal.getInstance(document.getElementById('orderStockModal')).hide();
                Swal.fire({
                    icon: 'success',
                    title: 'Order Placed',
                    text: 'Supply order has been placed successfully!',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Failed to place order');
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message
            });
        });
    }

    // Status update countdown system
    function startCountdown(element, duration, onComplete) {
        let timeLeft = duration;
        return setInterval(() => {
            element.textContent = `${timeLeft}s`;
            timeLeft--;
            
            if (timeLeft < 0) {
                clearInterval(element.dataset.intervalId);
                onComplete();
            }
        }, 1000);
    }

    function updateOrderStatus(orderId, currentStatus, nextStatus) {
        fetch('update_supply_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: orderId,
                status: nextStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-order-id="${orderId}"]`);
                const statusCell = row.querySelector('td:nth-child(5)');
                const countdownCell = row.querySelector('.countdown-cell');
                const badgeClass = nextStatus === 'preparing' ? 'info' : 
                                 (nextStatus === 'out_for_delivery' ? 'primary' : 'success');
                
                statusCell.innerHTML = `<span class="badge bg-${badgeClass}">
                    ${nextStatus.replace('_', ' ').toUpperCase()}
                </span>`;
                
                // Clear any existing countdown
                if (countdownCell.dataset.intervalId) {
                    clearInterval(parseInt(countdownCell.dataset.intervalId));
                }
                
                // Continue the status progression
                if (nextStatus === 'preparing') {
                    const intervalId = startCountdown(countdownCell, 10, () => {
                        updateOrderStatus(orderId, 'preparing', 'out_for_delivery');
                    });
                    countdownCell.dataset.intervalId = intervalId;
                } else if (nextStatus === 'out_for_delivery') {
                    const intervalId = startCountdown(countdownCell, 10, () => {
                        updateOrderStatus(orderId, 'out_for_delivery', 'delivered');
                    });
                    countdownCell.dataset.intervalId = intervalId;
                } else if (nextStatus === 'delivered') {
                    countdownCell.textContent = 'Delivered';
                    setTimeout(() => location.reload(), 1000);
                }
            }
        })
        .catch(error => {
            console.error('Error updating order status:', error);
        });
    }

    // Initialize countdowns for pending orders
    document.querySelectorAll('#supplyOrdersTable tbody tr').forEach(row => {
        const orderId = row.dataset.orderId;
        const currentStatus = row.dataset.status;
        const countdownCell = row.querySelector('.countdown-cell');
        
        if (currentStatus === 'pending') {
            const intervalId = startCountdown(countdownCell, 5, () => {
                updateOrderStatus(orderId, 'pending', 'preparing');
            });
            countdownCell.dataset.intervalId = intervalId;
        } else if (currentStatus === 'preparing') {
            const intervalId = startCountdown(countdownCell, 10, () => {
                updateOrderStatus(orderId, 'preparing', 'out_for_delivery');
            });
            countdownCell.dataset.intervalId = intervalId;
        } else if (currentStatus === 'out_for_delivery') {
            const intervalId = startCountdown(countdownCell, 10, () => {
                updateOrderStatus(orderId, 'out_for_delivery', 'delivered');
            });
            countdownCell.dataset.intervalId = intervalId;
        }
    });

    function cancelOrder(orderId) {
        Swal.fire({
            title: 'Cancel Order',
            text: 'Are you sure you want to cancel this order?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, cancel it!'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('cancel_supply_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Order Cancelled',
                            text: 'The supply order has been cancelled successfully!',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        throw new Error(data.message || 'Failed to cancel order');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message
                    });
                });
            }
        });
    }
</script>

<!-- Modal for Adding New Product -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form to add a new product -->
                <form action="inventory.php" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category" required>
                            <option value="Cold Drinks">Cold Drinks</option>
                            <option value="Hot Drinks">Hot Drinks</option>
                            <option value="Snacks">Snacks</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="number" class="form-control" id="price" name="price" required>
                    </div>
                    <div class="mb-3">
                        <label for="stock" class="form-label">Stock</label>
                        <input type="number" class="form-control" id="stock" name="stock" required>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary" name="add_product">Add Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

    <!-- Include the Update Product Modal -->
    <?php include 'update_product_modal.php'; ?>

    <!-- Add Order Stock Modal -->
    <div class="modal fade" id="orderStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="orderStockForm">
                        <input type="hidden" id="orderProductId" name="productId">
                        <input type="hidden" id="orderProductName" name="productName">
                        
                        <div class="mb-3">
                            <label for="orderProductNameDisplay" class="form-label">Product</label>
                            <input type="text" class="form-control" id="orderProductNameDisplay" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label for="orderQuantity" class="form-label">Quantity to Order</label>
                            <input type="number" class="form-control" id="orderQuantity" name="quantity" min="1" required>
                        </div>
                        
                        <button type="button" class="btn btn-primary" onclick="submitOrder()">Place Order</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
