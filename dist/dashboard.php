<?php

session_start();

// Verify user is logged in and has manager role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}
include('../includes/db-conn.php');

// Get date range for sales analytics
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Fetch sales statistics
$total_sales_query = "SELECT 
    SUM(total_amount) as total_sales,
    COUNT(*) as total_transactions,
    AVG(total_amount) as avg_transaction
    FROM transactions 
    WHERE DATE(transaction_date) BETWEEN '$start_date' AND '$end_date'";
$total_sales_result = $conn->query($total_sales_query);
$sales_stats = $total_sales_result->fetch_assoc();

// Fetch daily sales for the last 30 days
$daily_sales_query = "SELECT 
    DATE(transaction_date) as sale_date,
    SUM(total_amount) as daily_total,
    COUNT(*) as daily_transactions
    FROM transactions 
    WHERE DATE(transaction_date) BETWEEN '$start_date' AND '$end_date'
    GROUP BY DATE(transaction_date)
    ORDER BY sale_date";
$daily_sales_result = $conn->query($daily_sales_query);

$dates = [];
$daily_totals = [];
$daily_transactions = [];
while ($row = $daily_sales_result->fetch_assoc()) {
    $dates[] = date('M d', strtotime($row['sale_date']));
    $daily_totals[] = $row['daily_total'];
    $daily_transactions[] = $row['daily_transactions'];
}

// Fetch best-selling products
$best_sellers_query = "SELECT 
    p.name,
    p.category,
    SUM(td.quantity) as total_quantity,
    SUM(td.quantity * td.price) as total_revenue
    FROM transaction_details td
    JOIN products p ON td.product_id = p.product_ID
    JOIN transactions t ON td.transaction_id = t.transaction_id
    WHERE DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
    GROUP BY p.product_ID
    ORDER BY total_quantity DESC
    LIMIT 5";
$best_sellers_result = $conn->query($best_sellers_query);

// Fetch sales by category
$category_sales_query = "SELECT 
    p.category,
    SUM(td.quantity) as total_quantity,
    SUM(td.quantity * td.price) as total_revenue
    FROM transaction_details td
    JOIN products p ON td.product_id = p.product_ID
    JOIN transactions t ON td.transaction_id = t.transaction_id
    WHERE DATE(t.transaction_date) BETWEEN '$start_date' AND '$end_date'
    GROUP BY p.category";
$category_sales_result = $conn->query($category_sales_query);

$categories_sales = [];
$category_revenues = [];
while ($row = $category_sales_result->fetch_assoc()) {
    $categories_sales[] = $row['category'];
    $category_revenues[] = $row['total_revenue'];
}

// Fetch inventory statistics
$total_products_query = "SELECT COUNT(*) as total FROM products";
$low_stock_query = "SELECT COUNT(*) as low_stock FROM products WHERE stock <= 10";
$out_of_stock_query = "SELECT COUNT(*) as out_of_stock FROM products WHERE stock = 0";
$total_items_query = "SELECT SUM(stock) as total_items FROM products";
$category_distribution_query = "SELECT category, COUNT(*) as count FROM products GROUP BY category";

$total_products_result = $conn->query($total_products_query);
$low_stock_result = $conn->query($low_stock_query);
$out_of_stock_result = $conn->query($out_of_stock_query);
$total_items_result = $conn->query($total_items_query);
$category_distribution_result = $conn->query($category_distribution_query);

$total_products = $total_products_result->fetch_assoc()['total'];
$low_stock = $low_stock_result->fetch_assoc()['low_stock'];
$out_of_stock = $out_of_stock_result->fetch_assoc()['out_of_stock'];
$total_items = $total_items_result->fetch_assoc()['total_items'];

// Fetch category data for chart
$categories = [];
$category_counts = [];
while ($row = $category_distribution_result->fetch_assoc()) {
    $categories[] = $row['category'];
    $category_counts[] = $row['count'];
}

// Fetch low stock items
$low_stock_items_query = "SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5";
$low_stock_items_result = $conn->query($low_stock_items_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Dashboard - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <h1 class="mt-4">Dashboard</h1>
                
                <!-- Sales Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1">Total Sales (30 Days)</h6>
                                        <h2 class="mb-0">PHP <?php echo number_format($sales_stats['total_sales'], 2); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-white text-primary">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1">Total Transactions</h6>
                                        <h2 class="mb-0"><?php echo $sales_stats['total_transactions']; ?></h2>
                                    </div>
                                    <div class="icon-circle bg-white text-primary">
                                        <i class="fas fa-receipt fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1">Total Products</h6>
                                        <h2 class="mb-0"><?php echo $total_products; ?></h2>
                                    </div>
                                    <div class="icon-circle bg-white text-success">
                                        <i class="fas fa-box fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-uppercase mb-1">Total Items in Stock</h6>
                                        <h2 class="mb-0"><?php echo number_format($total_items); ?></h2>
                                    </div>
                                    <div class="icon-circle bg-white text-success">
                                        <i class="fas fa-boxes fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sales Analytics Charts -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Sales by Category</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <canvas id="categorySalesChart" height="300"></canvas>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mt-4">
                                            <h6 class="mb-3">Category Breakdown</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Category</th>
                                                            <th class="text-end">Revenue</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $category_sales_result->data_seek(0);
                                                        while ($row = $category_sales_result->fetch_assoc()):
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                                                            <td class="text-end">PHP <?php echo number_format($row['total_revenue'], 2); ?></td>
                                                        </tr>
                                                        <?php endwhile; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Best Selling Products -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-crown me-2"></i>
                                    Best Selling Products
                                </h5>
                                <span class="badge bg-white text-success">Top 5 Products</span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Units Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rank = 1;
                                            while ($product = $best_sellers_result->fetch_assoc()): 
                                            ?>
                                            <tr>
                                                <td><?php echo $rank++; ?></td>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                                <td><?php echo number_format($product['total_quantity']); ?></td>
                                                <td>PHP <?php echo number_format($product['total_revenue'], 2); ?></td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Category Distribution</h5>
                            </div>
                            <div class="card-body d-flex justify-content-center">
                                <div style="width: 300px; height: 300px;">
                                    <canvas id="categoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Stock Status</h5>
                            </div>
                            <div class="card-body d-flex justify-content-center">
                                <div style="width: 300px; height: 300px;">
                                    <canvas id="stockStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stock Alerts -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Stock Alerts
                                </h5>
                                <span class="badge bg-white text-warning">
                                    <?php echo $low_stock + $out_of_stock; ?> Items Need Attention
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Product Name</th>
                                                <th>Category</th>
                                                <th>Current Stock</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get all low and out of stock items
                                            $stock_alerts_query = "SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC";
                                            $stock_alerts_result = $conn->query($stock_alerts_query);
                                            
                                            while ($item = $stock_alerts_result->fetch_assoc()):
                                                $status_class = $item['stock'] == 0 ? 'danger' : 'warning';
                                                $status_text = $item['stock'] == 0 ? 'Out of Stock' : 'Low Stock';
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status_class; ?>">
                                                        <?php echo $item['stock']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="inventory.php" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-edit"></i> Update Stock
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    
    <script>
        // Category Sales Chart
        const categorySalesChart = new Chart(document.getElementById('categorySalesChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categories_sales); ?>,
                datasets: [{
                    data: <?php echo json_encode($category_revenues); ?>,
                    backgroundColor: ['#0d6efd', '#0d6efd99', '#0d6efd77', '#0d6efd55', '#0d6efd33'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            boxWidth: 12
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Category Distribution Chart
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categories); ?>,
                datasets: [{
                    data: <?php echo json_encode($category_counts); ?>,
                    backgroundColor: [
                        '#FF6B6B',
                        '#4ECDC4',
                        '#45B7D1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });

        // Stock Status Chart
        new Chart(document.getElementById('stockStatusChart'), {
            type: 'pie',
            data: {
                labels: ['Normal Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [
                        <?php echo $total_products - $low_stock; ?>,
                        <?php echo $low_stock - $out_of_stock; ?>,
                        <?php echo $out_of_stock; ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

    <style>
        .icon-circle {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }

        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }

        .table th {
            background-color: #f8f9fc;
        }

        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.75em;
        }

        .card-header.bg-warning {
            background-color: #ffc107 !important;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</body>
</html>
