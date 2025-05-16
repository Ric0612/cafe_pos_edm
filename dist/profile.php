<?php
session_start();

// Verify user is logged in
if (!isset($_SESSION['user_ID'])) {
    header("Location: ../login.php");
    exit();
}

include('../includes/db-conn.php');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $user_id = $_SESSION['user_ID'];
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_ID = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stored_password = $result->fetch_assoc()['password'];
    
    if (password_verify($current_password, $stored_password)) {
        // Only managers can update username
        $username_update = "";
        $params = [];
        $types = "";
        
        if ($_SESSION['role'] === 'manager' && isset($_POST['username'])) {
            $username = $conn->real_escape_string($_POST['username']);
            // Check if username already exists for other users
            $stmt = $conn->prepare("SELECT user_ID FROM users WHERE username = ? AND user_ID != ?");
            $stmt->bind_param("si", $username, $user_id);
            $stmt->execute();
            $username_check = $stmt->get_result();
            
            if ($username_check->num_rows > 0) {
                $_SESSION['message'] = "Username already exists!";
                $_SESSION['message_type'] = "danger";
                header("Location: profile.php");
                exit();
            }
            $username_update = ", username = ?";
            $params[] = $username;
            $types .= "s";
        }
        
        // Handle password update if provided
        $password_update = "";
        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) < 8) {
                    $_SESSION['message'] = "New password must be at least 8 characters long!";
                    $_SESSION['message_type'] = "danger";
                    header("Location: profile.php");
                    exit();
                }
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $password_update = ", password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            } else {
                $_SESSION['message'] = "New passwords do not match!";
                $_SESSION['message_type'] = "danger";
                header("Location: profile.php");
                exit();
            }
        }
        
        // Build the update query
        $query = "UPDATE users SET name = ?, email = ?" . $username_update . $password_update . " WHERE user_ID = ?";
        $stmt = $conn->prepare($query);
        
        // Add base parameters
        array_unshift($params, $name, $email);
        $types = "ss" . $types . "i";
        $params[] = $user_id;
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Profile updated successfully!";
            $_SESSION['message_type'] = "success";
            // Update session variables
            $_SESSION['name'] = $name;
            if ($_SESSION['role'] === 'manager' && isset($_POST['username'])) {
                $_SESSION['username'] = $username;
            }
        } else {
            $_SESSION['message'] = "Error updating profile: " . $conn->error;
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Current password is incorrect!";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: profile.php");
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_ID'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_ID = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Profile - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <link href="css/styles.css" rel="stylesheet" />
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
                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0 rounded-lg mt-4">
                            <div class="card-header bg-primary text-white">
                                <h3 class="text-center font-weight-light my-2">
                                    <i class="fas fa-user-circle me-2"></i>Profile Settings
                                </h3>
                            </div>
                            
                            <?php if (isset($_SESSION['message'])): ?>
                                <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show m-3" role="alert">
                                    <?php 
                                    echo $_SESSION['message'];
                                    unset($_SESSION['message']);
                                    unset($_SESSION['message_type']);
                                    ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <div class="profile-image">
                                        <i class="fas fa-user-circle fa-6x text-primary"></i>
                                    </div>
                                    <h4 class="mt-3"><?php echo htmlspecialchars($user_data['name']); ?></h4>
                                    <span class="badge bg-primary"><?php echo ucfirst(htmlspecialchars($user_data['role'])); ?></span>
                                </div>
                                
                                <form method="POST" action="profile.php" id="profileForm" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="name" name="name" type="text" 
                                                       value="<?php echo htmlspecialchars($user_data['name']); ?>" required 
                                                       pattern="[A-Za-z\s]+" title="Please enter a valid name (letters and spaces only)"/>
                                                <label for="name">Full Name</label>
                                                <div class="invalid-feedback">
                                                    Please enter a valid name (letters and spaces only).
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="username" name="username" type="text" 
                                                       value="<?php echo htmlspecialchars($user_data['username']); ?>"
                                                       <?php echo ($_SESSION['role'] !== 'manager') ? 'readonly' : 'required'; ?>
                                                       pattern="[A-Za-z0-9_]+" title="Username can only contain letters, numbers, and underscores"/>
                                                <label for="username">Username</label>
                                                <div class="invalid-feedback">
                                                    Username can only contain letters, numbers, and underscores.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input class="form-control" id="email" name="email" type="email" 
                                               value="<?php echo htmlspecialchars($user_data['email']); ?>" required />
                                        <label for="email">Email address</label>
                                        <div class="invalid-feedback">
                                            Please enter a valid email address.
                                        </div>
                                    </div>
                                    
                                    <div class="form-floating mb-3">
                                        <input class="form-control" id="role" type="text" 
                                               value="<?php echo ucfirst(htmlspecialchars($user_data['role'])); ?>" readonly />
                                        <label for="role">Role</label>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="currentPassword" name="current_password" 
                                                       type="password" required />
                                                <label for="currentPassword">Current Password</label>
                                                <div class="invalid-feedback">
                                                    Current password is required.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="newPassword" name="new_password" 
                                                       type="password" pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$"
                                                       title="Password must be at least 8 characters long and contain at least one letter and one number"/>
                                                <label for="newPassword">New Password (optional)</label>
                                                <div class="invalid-feedback">
                                                    Password must be at least 8 characters long and contain at least one letter and one number.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="confirmPassword" name="confirm_password" 
                                                       type="password"/>
                                                <label for="confirmPassword">Confirm New Password</label>
                                                <div class="invalid-feedback">
                                                    Passwords do not match.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 mb-0">
                                        <div class="d-grid">
                                            <button type="submit" name="update_profile" class="btn btn-primary btn-block">
                                                <i class="fas fa-save me-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <?php if ($_SESSION['role'] === 'manager'): ?>
                            <div class="card-footer">
                                <div class="d-grid">
                                    <a href="manage_users.php" class="btn btn-secondary">
                                        <i class="fas fa-users-cog me-2"></i>Manage Users
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <footer class="footer mt-auto py-3 bg-light">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Café POS 2025</h5>
                            <p class="text-muted">A comprehensive point of sale system designed for cafés.</p>
                        </div>
                        <div class="col-md-3">
                            <h5>Quick Links</h5>
                            <ul class="list-unstyled">
                                <li><a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a></li>
                                <li><a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a></li>
                                <li><a href="#" data-bs-toggle="modal" data-bs-target="#helpModal">Help & Support</a></li>
                            </ul>
                        </div>
                        <div class="col-md-3">
                            <h5>Contact</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-envelope me-2"></i>22-35042@g.batstate-u.edu.ph</li>
                                <li><i class="fas fa-phone me-2"></i>(+63) 956-390-2628</li>
                            </ul>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <p class="mb-0">&copy; <?php echo date('Y'); ?> Café POS. All rights reserved.</p>
                    </div>
                </div>
            </footer>

            <!-- Terms & Conditions Modal -->
            <div class="modal fade" id="termsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Terms & Conditions</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6>1. Acceptance of Terms</h6>
                            <p>By accessing and using the Café POS system, you agree to be bound by these Terms and Conditions.</p>

                            <h6>2. User Responsibilities</h6>
                            <p>Users must maintain the confidentiality of their account credentials and are responsible for all activities under their account.</p>

                            <h6>3. System Usage</h6>
                            <p>The system should be used only for legitimate business purposes related to café operations.</p>

                            <h6>4. Data Security</h6>
                            <p>Users must follow security protocols and protect sensitive information accessed through the system.</p>

                            <h6>5. Compliance</h6>
                            <p>Users must comply with all applicable laws and regulations while using the system.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Privacy Policy Modal -->
            <div class="modal fade" id="privacyModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Privacy Policy</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6>1. Data Collection</h6>
                            <p>We collect and store information necessary for café operations and user management.</p>

                            <h6>2. Data Usage</h6>
                            <p>Collected data is used for business operations, system improvement, and user support.</p>

                            <h6>3. Data Protection</h6>
                            <p>We implement security measures to protect user data and maintain confidentiality.</p>

                            <h6>4. User Rights</h6>
                            <p>Users have the right to access, modify, and delete their personal information.</p>

                            <h6>5. Data Sharing</h6>
                            <p>We do not share user data with third parties without explicit consent or legal requirement.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help & Support Modal -->
            <div class="modal fade" id="helpModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Help & Support</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <h6>Contact Support</h6>
                            <p>For technical support or assistance:</p>
                            <ul>
                                <li>Email: 22-355042@g.batstate-u.edu.ph</li>
                                <li>Phone: (+63) 956-390-2628</li>
                                <li>Hours: Monday - Friday, 9:00 AM - 5:00 PM</li>
                            </ul>

                            <h6>User Manual</h6>
                            <div class="accordion" id="userManualAccordion">
                                <!-- Getting Started -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#gettingStarted">
                                            Getting Started
                                        </button>
                                    </h2>
                                    <div id="gettingStarted" class="accordion-collapse collapse show" data-bs-parent="#userManualAccordion">
                                        <div class="accordion-body">
                                            <ol>
                                                <li><strong>Login:</strong> Use your username and password to access the system.</li>
                                                <li><strong>Dashboard:</strong> View sales statistics, best-selling items, and daily summaries.</li>
                                                <li><strong>Navigation:</strong> Use the sidebar menu to access different sections.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Management -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#orderManagement">
                                            Order Management
                                        </button>
                                    </h2>
                                    <div id="orderManagement" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                                        <div class="accordion-body">
                                            <ol>
                                                <li><strong>Taking Orders:</strong>
                                                    <ul>
                                                        <li>Click "New Order" to start</li>
                                                        <li>Select items from the menu</li>
                                                        <li>Add quantity and special instructions</li>
                                                        <li>Apply discounts if applicable</li>
                                                        <li>Confirm and submit order</li>
                                                    </ul>
                                                </li>
                                                <li><strong>Order Status:</strong> Track orders through New → Preparing → Ready → Completed</li>
                                                <li><strong>Modifications:</strong> Edit orders before they enter "Preparing" status</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <!-- Inventory Management -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#inventoryManagement">
                                            Inventory Management
                                        </button>
                                    </h2>
                                    <div id="inventoryManagement" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                                        <div class="accordion-body">
                                            <ol>
                                                <li><strong>View Inventory:</strong> Access current stock levels and product details</li>
                                                <li><strong>Add Products:</strong>
                                                    <ul>
                                                        <li>Click the "+" button</li>
                                                        <li>Fill in product details</li>
                                                        <li>Upload product image</li>
                                                        <li>Set initial stock level</li>
                                                    </ul>
                                                </li>
                                                <li><strong>Update Products:</strong> Edit existing products using the edit button</li>
                                                <li><strong>Stock Alerts:</strong> System notifies when items are low or out of stock</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <!-- Discounts and Payments -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#discountsPayments">
                                            Discounts and Payments
                                        </button>
                                    </h2>
                                    <div id="discountsPayments" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                                        <div class="accordion-body">
                                            <ol>
                                                <li><strong>Apply Discounts:</strong>
                                                    <ul>
                                                        <li>Senior Citizen (20%): Verify SC ID</li>
                                                        <li>PWD (20%): Verify PWD ID</li>
                                                        <li>Enter ID number in the designated field</li>
                                                    </ul>
                                                </li>
                                                <li><strong>Process Payments:</strong>
                                                    <ul>
                                                        <li>Select payment method</li>
                                                        <li>Enter received amount</li>
                                                        <li>System calculates change</li>
                                                        <li>Print receipt</li>
                                                    </ul>
                                                </li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reports and Analytics -->
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#reportsAnalytics">
                                            Reports and Analytics
                                        </button>
                                    </h2>
                                    <div id="reportsAnalytics" class="accordion-collapse collapse" data-bs-parent="#userManualAccordion">
                                        <div class="accordion-body">
                                            <ol>
                                                <li><strong>Sales Reports:</strong> View daily, weekly, and monthly sales summaries</li>
                                                <li><strong>Inventory Reports:</strong> Track stock movements and product performance</li>
                                                <li><strong>Analytics:</strong> Access charts and graphs for business insights</li>
                                                <li><strong>Export:</strong> Download reports in various formats</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-4">Common Issues</h6>
                            <p>For immediate assistance with common issues, please contact the system administrator.</p>

                            <h6>Training Resources</h6>
                            <p>View guides through the help of user manual available in the system.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Form validation
    (function () {
        'use strict'
        
        const forms = document.querySelectorAll('.needs-validation')
        
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                // Check if new passwords match
                const newPassword = form.querySelector('#newPassword')
                const confirmPassword = form.querySelector('#confirmPassword')
                if (newPassword.value || confirmPassword.value) {
                    if (newPassword.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match')
                        event.preventDefault()
                        event.stopPropagation()
                    } else {
                        confirmPassword.setCustomValidity('')
                    }
                }
                
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
    
    <style>
    .profile-image {
        width: 120px;
        height: 120px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f8f9fa;
        border-radius: 50%;
        border: 3px solid #6c4f3d;
    }
    
    .card {
        margin-bottom: 2rem;
    }
    
    .form-floating > .form-control:focus,
    .form-floating > .form-control:not(:placeholder-shown) {
        padding-top: 1.625rem;
        padding-bottom: 0.625rem;
    }
    
    .form-floating > .form-control:-webkit-autofill {
        padding-top: 1.625rem;
        padding-bottom: 0.625rem;
    }
    
    .alert {
        margin-bottom: 0;
    }

    .footer {
        background-color: #f8f9fa;
        padding: 2rem 0;
        margin-top: 3rem;
        border-top: 1px solid #dee2e6;
    }

    .footer h5 {
        color: #6c4f3d;
        margin-bottom: 1rem;
    }

    .footer a {
        color: #6c757d;
        text-decoration: none;
    }

    .footer a:hover {
        color: #6c4f3d;
    }

    .modal-header {
        background-color: #6c4f3d;
        color: white;
    }

    .modal-body h6 {
        color: #6c4f3d;
        margin-top: 1rem;
    }

    /* User Manual Accordion Styles */
    .accordion-button:not(.collapsed) {
        background-color: #f4e1c1;
        color: #6c4f3d;
    }

    .accordion-button:focus {
        border-color: #6c4f3d;
        box-shadow: 0 0 0 0.25rem rgba(108, 79, 61, 0.25);
    }

    .accordion-button::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%236c4f3d'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
    }

    .accordion-item {
        border-color: #dee2e6;
    }

    .accordion-body {
        background-color: #fff;
        padding: 1.25rem;
    }

    .accordion-body ol {
        padding-left: 1.25rem;
        margin-bottom: 0;
    }

    .accordion-body ul {
        padding-left: 1.5rem;
        margin-top: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .accordion-body li {
        margin-bottom: 0.5rem;
    }

    .accordion-body li:last-child {
        margin-bottom: 0;
    }

    .accordion-body strong {
        color: #6c4f3d;
    }
    </style>
</body>
</html>
