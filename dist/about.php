<?php
session_start();

// Verify user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

include('../includes/db-conn.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>About - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <style>
        /* Responsive fixes */
        @media (max-width: 768px) {
            #wrapper {
                display: block !important;
            }
            #sidebar-wrapper {
                width: 100% !important;
                position: relative !important;
                height: auto !important;
            }
            #page-content-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .container-fluid {
                padding: 15px !important;
            }
            .display-4 {
                font-size: 2rem !important;
            }
            .card-body {
                padding: 1rem !important;
            }
            .support-contact {
                flex-direction: column;
                text-align: center;
            }
            .support-contact > div {
                margin-bottom: 1rem;
            }
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
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="text-center my-5">
                            <h1 class="display-4 text-primary mb-4">Café POS System</h1>
                            <p class="lead text-muted">- Since 2025 -</p>
                        </div>

                        <!-- System Overview -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h2 class="card-title h4 mb-3">
                                    <i class="fas fa-info-circle text-primary me-2"></i>System Overview
                                </h2>
                                <p>Our Café POS system is designed to streamline your café operations with an intuitive interface and powerful features. Whether you're managing inventory, processing orders, or analyzing sales data, our system provides the tools you need for efficient café management.</p>
                            </div>
                        </div>

                        <!-- Key Features -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h3 class="h5 mb-3">
                                            <i class="fas fa-cash-register text-success me-2"></i>Sales Management
                                        </h3>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Quick and Easy Order Processing</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Cash Payment</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Discount Privileges</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Receipt Generation</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h3 class="h5 mb-3">
                                            <i class="fas fa-box text-primary me-2"></i>Inventory Management
                                        </h3>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Real-time Stock Tracking</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Low Stock Alerts</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Product Categorization</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Stock Audit</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h3 class="h5 mb-3">
                                            <i class="fas fa-user-shield text-warning me-2"></i>User Management
                                        </h3>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Role-based Access Control</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Secure Authentication</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Activity Logging</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>User Permissions</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h3 class="h5 mb-3">
                                            <i class="fas fa-chart-line text-info me-2"></i>Reporting
                                        </h3>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Sales Reports</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Inventory Reports</li>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Roles -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h2 class="card-title h4 mb-3">
                                    <i class="fas fa-users text-success me-2"></i>User Roles
                                </h2>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="h6 mb-2"><i class="fas fa-user-tie me-2"></i>Manager</h4>
                                            <p class="small text-muted mb-0">Full system access with administrative privileges</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="h6 mb-2"><i class="fas fa-user-friends me-2"></i>Cashier</h4>
                                            <p class="small text-muted mb-0">Sales and order management access</p>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="border rounded p-3">
                                            <h4 class="h6 mb-2"><i class="fas fa-utensils me-2"></i>Kitchen Staff</h4>
                                            <p class="small text-muted mb-0">Order managing access</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Requirements -->
                        <div class="card mb-4 shadow-sm">
                            <div class="card-body">
                                <h2 class="card-title h4 mb-3">
                                    <i class="fas fa-laptop text-info me-2"></i>System Requirements & Device Compatibility
                                </h2>
                                <div class="alert alert-warning mb-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>Important Note:</strong> This POS system is optimized for desktop and laptop computers. While it can be accessed on mobile devices, for the best experience and full functionality, we recommend using a computer.
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-4">
                                        <h5 class="h6 mb-3"><i class="fas fa-desktop text-primary me-2"></i>Recommended Devices</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Desktop Computers</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Laptop Computers</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Tablets (Landscape Mode)</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <h5 class="h6 mb-3"><i class="fas fa-browser text-primary me-2"></i>Browser Requirements</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Google Chrome (Recommended)</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mozilla Firefox</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Microsoft Edge</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6 mb-4">
                                        <h5 class="h6 mb-3"><i class="fas fa-cogs text-primary me-2"></i>System Specifications</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Stable Internet Connection</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Minimum Screen Resolution: 1024x768</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>JavaScript Enabled</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h5 class="h6 mb-3"><i class="fas fa-print text-primary me-2"></i>Optional Equipment</h5>
                                        <ul class="list-unstyled">
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Receipt Printer</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Barcode Scanner</li>
                                            <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Cash Drawer</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Support -->
                        <div class="card mb-5 shadow-sm">
                            <div class="card-body text-center">
                                <h2 class="card-title h4 mb-3">
                                    <i class="fas fa-headset text-warning me-2"></i>Support
                                </h2>
                                <p class="mb-3">Need help? Our support team is here to assist you.</p>
                                <div class="row justify-content-center">
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-envelope text-primary me-2"></i>
                                            <span>22-35042@g.batstate-u.edu.ph</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-phone text-primary me-2"></i>
                                            <span>(+63)956-390-2628</span>
                                        </div>
                                    </div>
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
</body>
</html> 