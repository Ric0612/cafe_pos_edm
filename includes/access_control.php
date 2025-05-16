<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define access permissions for each role
$role_permissions = [
    'manager' => [
        'dashboard' => true,
        'sales' => true,
        'orders' => true,
        'inventory' => true,
        'reports' => true,
        'audit_logs' => true,
        'profile' => true
    ],
    'cashier' => [
        'dashboard' => false,
        'sales' => true,
        'orders' => true,
        'inventory' => false,
        'users' => false,
        'reports' => false,
        'audit_logs' => false,
        'profile' => true
    ],
    'kitchen' => [
        'dashboard' => false,
        'sales' => false,
        'orders' => true,
        'inventory' => false,
        'users' => false,
        'reports' => false,
        'audit_logs' => false,
        'profile' => true
    ]
];

// Function to check if user has permission for a specific module
function has_permission($module) {
    global $role_permissions;
    
    // Check if user is logged in
    if (!isset($_SESSION['role'])) {
        return false;
    }
    
    $role = $_SESSION['role'];
    
    // Check if role exists in permissions
    if (!isset($role_permissions[$role])) {
        return false;
    }
    
    // Check if module exists in role permissions
    if (!isset($role_permissions[$role][$module])) {
        return false;
    }
    
    return $role_permissions[$role][$module];
}

// Function to verify user can access their own profile only
function can_access_profile($user_id) {
    if (!isset($_SESSION['role']) || !isset($_SESSION['user_ID'])) {
        return false;
    }
    
    // Managers can access all profiles
    if ($_SESSION['role'] === 'manager') {
        return true;
    }
    
    // Other roles can only access their own profile
    return $_SESSION['user_ID'] == $user_id;
}

// Function to enforce access control
function enforce_access($module, $user_id = null) {
    // Check basic module permission
    if (!has_permission($module)) {
        // Log unauthorized access attempt
        if (isset($_SESSION['user_ID'])) {
            global $conn;
            $log_sql = "INSERT INTO login_audit_logs (user_ID, username, action, ip_address, user_agent) 
                       VALUES (?, ?, 'UNAUTHORIZED_ACCESS', ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("isss", 
                $_SESSION['user_ID'], 
                $_SESSION['username'], 
                $_SERVER['REMOTE_ADDR'], 
                $_SERVER['HTTP_USER_AGENT']
            );
            $log_stmt->execute();
        }
        
        // Redirect to appropriate page
        if (!isset($_SESSION['role'])) {
            header('Location: /cafes_pos/index.php');
        } else {
            switch ($_SESSION['role']) {
                case 'cashier':
                    header('Location: /cafes_pos/dist/sales.php');
                    break;
                case 'kitchen':
                    header('Location: /cafes_pos/dist/orders.php');
                    break;
                default:
                    header('Location: /cafes_pos/dist/dashboard.php');
            }
        }
        exit();
    }
    
    // Check profile access if user_id is provided
    if ($user_id !== null && !can_access_profile($user_id)) {
        header('Location: /cafes_pos/dist/unauthorized.php');
        exit();
    }
}
?> 