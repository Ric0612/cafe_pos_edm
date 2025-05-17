<?php
session_start();

// Verify user is logged in and is a manager
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header("Location: ../login.php");
    exit();
}

include('../includes/db-conn.php');

// Handle user updates and additions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $user_id = $conn->real_escape_string($_POST['user_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $role = $conn->real_escape_string($_POST['role']);
        
        // Check if username already exists for other users
        $stmt = $conn->prepare("SELECT user_ID FROM users WHERE username = ? AND user_ID != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $check_username = $stmt->get_result();
        
        if ($check_username->num_rows > 0) {
            $_SESSION['message'] = "Username already exists!";
            $_SESSION['message_type'] = "danger";
        } else {
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ? WHERE user_ID = ?");
            $stmt->bind_param("ssssi", $name, $username, $email, $role, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "User updated successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error updating user: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
        }
        
        header("Location: manage_users.php");
        exit();
    } elseif (isset($_POST['add_user'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = $_POST['password'];
        $role = $conn->real_escape_string($_POST['role']);
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_ID FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $check_username = $stmt->get_result();
        
        if ($check_username->num_rows > 0) {
            $_SESSION['message'] = "Username already exists!";
            $_SESSION['message_type'] = "danger";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (name, username, password, email, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $username, $hashed_password, $email, $role);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "New user added successfully!";
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = "Error adding user: " . $conn->error;
                $_SESSION['message_type'] = "danger";
            }
        }
        
        header("Location: manage_users.php");
        exit();
    }
}

// Handle user deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $conn->real_escape_string($_GET['delete_id']);
    
    // Prevent deleting your own account
    if ($delete_id == $_SESSION['user_ID']) {
        $_SESSION['message'] = "You cannot delete your own account!";
        $_SESSION['message_type'] = "danger";
        header("Location: manage_users.php");
        exit();
    }
    
    // Delete the user
    $stmt = $conn->prepare("DELETE FROM users WHERE user_ID = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "User deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting user: " . $conn->error;
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: manage_users.php");
    exit();
}

// Fetch all users except the current user
$current_user_id = $_SESSION['user_ID'];
$users_query = "SELECT * FROM users WHERE user_ID != $current_user_id ORDER BY role, name";
$users_result = $conn->query($users_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Manage Users - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css" rel="stylesheet">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <style>
    :root {
        --primary-color: #6c4f3d;
        --primary-light: #8b7355;
        --primary-dark: #523a2b;
        --accent-color: #f4e1c1;
    }

    .container-fluid {
        padding: 2rem 2.5rem;
    }

    .page-header {
        background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
        padding: 1.5rem 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .page-header h1 {
        color: white;
        margin: 0;
        font-size: 1.75rem;
        font-weight: 600;
    }

    .btn-add-user {
        background-color: var(--accent-color);
        color: var(--primary-dark);
        border: none;
        padding: 0.625rem 1.25rem;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-add-user:hover {
        background-color: var(--primary-light);
        color: white;
        transform: translateY(-2px);
    }

    .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
    }

    .card-body {
        padding: 1.5rem;
    }

    .table {
        margin-bottom: 0;
    }

    .table th {
        background-color: #f8f9fc;
        border-bottom: 2px solid var(--primary-color);
        color: var(--primary-dark);
        font-weight: 600;
        padding: 1rem;
        white-space: nowrap;
    }

    .table td {
        padding: 1rem;
        vertical-align: middle;
    }

    .table tr:hover {
        background-color: #f8f9fa;
    }

    .badge {
        font-size: 0.85em;
        padding: 0.5em 1em;
        border-radius: 20px;
        font-weight: 500;
    }

    .badge-manager {
        background-color: var(--primary-color);
    }

    .badge-cashier {
        background-color: #28a745;
    }

    .badge-kitchen {
        background-color: #17a2b8;
    }

    .btn-action {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        margin: 0 0.25rem;
        transition: all 0.3s ease;
    }

    .btn-edit {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #000;
    }

    .btn-delete {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        background: linear-gradient(to right, var(--primary-color), var(--primary-dark));
        color: white;
        border-radius: 10px 10px 0 0;
        padding: 1.25rem;
    }

    .modal-title {
        font-weight: 600;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .form-label {
        color: var(--primary-dark);
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.625rem 1rem;
        border: 1px solid #ced4da;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(108, 79, 61, 0.25);
    }

    .input-group .btn-outline-secondary {
        border-color: #ced4da;
        color: var(--primary-dark);
    }

    .input-group .btn-outline-secondary:hover {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .modal-footer {
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-radius: 0 0 10px 10px;
    }

    .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    /* Alert Styles */
    .alert {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Responsive Table */
    @media (max-width: 768px) {
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .container-fluid {
            padding: 1rem;
        }
        
        .page-header {
            padding: 1rem;
            margin-bottom: 1.5rem;
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
            <div class="container-fluid">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <h1><i class="fas fa-users me-2"></i>Manage Users</h1>
                    <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Add New User
                    </button>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['message'];
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-user me-2"></i>Name</th>
                                        <th><i class="fas fa-at me-2"></i>Username</th>
                                        <th><i class="fas fa-envelope me-2"></i>Email</th>
                                        <th><i class="fas fa-user-tag me-2"></i>Role</th>
                                        <th><i class="fas fa-cog me-2"></i>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-action btn-edit" 
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                    title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-action btn-delete" 
                                                    onclick="deleteUser(<?php echo $user['user_ID']; ?>)"
                                                    title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addUserForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="manager">Manager</option>
                                <option value="cashier">Cashier</option>
                                <option value="kitchen">Kitchen Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="manager">Manager</option>
                                <option value="cashier">Cashier</option>
                                <option value="kitchen">Kitchen Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/scripts.js"></script>
    
    <script>
    function editUser(user) {
        document.getElementById('edit_user_id').value = user.user_ID;
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_role').value = user.role;
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }
    
    function deleteUser(userId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete user!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `manage_users.php?delete_id=${userId}`;
            }
        });
    }

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const icon = document.querySelector('.fa-eye');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Form validation
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const username = document.querySelector('input[name="username"]').value;
        
        // Check password length
        if (password.length < 8) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Password',
                text: 'Password must be at least 8 characters long'
            });
            return;
        }
        
        // Check username format
        if (!/^[A-Za-z0-9_]+$/.test(username)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Username',
                text: 'Username can only contain letters, numbers, and underscores'
            });
            return;
        }
    });

    // Add validation to edit form
    document.querySelector('#editUserModal form').addEventListener('submit', function(e) {
        const username = document.getElementById('edit_username').value;
        
        // Check username format
        if (!/^[A-Za-z0-9_]+$/.test(username)) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Invalid Username',
                text: 'Username can only contain letters, numbers, and underscores'
            });
        }
    });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
</body>
</html> 