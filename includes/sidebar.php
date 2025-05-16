<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$user_role = $_SESSION['role'];

include_once('access_control.php');
?>

<div class="border-end custom-sidebar" id="sidebar-wrapper">
    <div class="sidebar-heading">
        <img src="../img/cafe-logo.jpg" alt="Café Logo" class="sidebar-logo">
        <span class="cafe-title">Café POS</span>
    </div>

    <div class="list-group list-group-flush">
        <?php if (has_permission('dashboard')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
               href="dashboard.php">
                <i class="fas fa-chart-line me-2"></i>Dashboard
            </a>
        <?php endif; ?>

        <?php if (has_permission('sales')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : ''; ?>" 
               href="sales.php">
                <i class="fas fa-cash-register me-2"></i>Sales
            </a>
        <?php endif; ?>

        <?php if (has_permission('orders')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'active' : ''; ?>" 
               href="orders.php">
                <i class="fas fa-utensils me-2"></i>Orders
            </a>
        <?php endif; ?>

        <?php if (has_permission('inventory')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" 
               href="inventory.php">
                <i class="fas fa-box me-2"></i>Inventory
            </a>
        <?php endif; ?>

        <?php if (has_permission('users')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
               href="users.php">
                <i class="fas fa-users me-2"></i>Users
            </a>
        <?php endif; ?>

        <?php if (has_permission('reports')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>" 
               href="report.php">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </a>
        <?php endif; ?>

        <?php if (has_permission('profile')): ?>
            <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" 
               href="profile.php">
                <i class="fas fa-user me-2"></i>Profile
            </a>
        <?php endif; ?>

        <!-- Common links for all roles -->
        <a class="list-group-item <?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active' : ''; ?>" 
           href="about.php">
            <i class="fas fa-info-circle me-2"></i>About
        </a>
    </div>
</div>

<!-- Back to Top Button -->
<button id="backToTop" class="back-to-top" onclick="scrollToTop()">
    <i class="fas fa-arrow-up"></i>
</button>

<style>
.custom-sidebar {
    background-color: #f4e1c1;
    min-height: 100vh;
    transition: all 0.3s ease-in-out;
    position: fixed;
    top: 0;
    left: 0;
    width: 15rem;
    overflow-y: auto;
    z-index: 1000;
}

#page-content-wrapper {
    margin-left: 15rem;
    width: calc(100% - 15rem);
    transition: all 0.3s ease-in-out;
}

.sidebar-heading {
    background-color: #fff;
    padding: 1rem;
    display: flex;
    align-items: center;
    border-bottom: 1px solid rgba(0,0,0,.125);
    position: sticky;
    top: 0;
    z-index: 1001;
    background-color: #f4e1c1;
}

.sidebar-logo {
    height: 40px;
    margin-right: 10px;
    border-radius: 5px;
}

.cafe-title {
    font-family: 'Poppins', sans-serif;
    font-size: 1.5rem;
    font-weight: bold;
    color: #6c4f3d;
}

.list-group-item {
    background-color: transparent;
    color: #6c4f3d;
    border: none;
    padding: 1rem 1.5rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    font-weight: 500;
}

.list-group-item:hover {
    background-color: #e6cba5;
    color: #4a3528;
    transform: translateX(5px);
}

.list-group-item.active {
    background-color: #8b6b4d;
    color: #fff;
    border: none;
}

.list-group-item i {
    width: 20px;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .custom-sidebar {
        margin-left: -15rem;
        position: fixed;
    }
    
    .custom-sidebar.active {
        margin-left: 0;
    }

    #page-content-wrapper {
        margin-left: 0;
        width: 100%;
    }

    #page-content-wrapper.sidebar-open {
        margin-left: 15rem;
        width: calc(100% - 15rem);
    }
}

/* Custom scrollbar for sidebar */
.custom-sidebar::-webkit-scrollbar {
    width: 5px;
}

.custom-sidebar::-webkit-scrollbar-track {
    background: #f4e1c1;
}

.custom-sidebar::-webkit-scrollbar-thumb {
    background: #8b6b4d;
    border-radius: 10px;
}

.custom-sidebar::-webkit-scrollbar-thumb:hover {
    background: #6c4f3d;
}

/* Back to Top Button Styles */
.back-to-top {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 40px;
    height: 40px;
    background-color: #6c4f3d;
    color: #fff;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    z-index: 1000;
}

.back-to-top:hover {
    background-color: #8b6b4d;
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}

.back-to-top.show {
    display: flex;
}
</style>

<script>
// Back to Top functionality
window.onscroll = function() {
    toggleBackToTop();
};

function toggleBackToTop() {
    const button = document.getElementById("backToTop");
    if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
        button.classList.add("show");
    } else {
        button.classList.remove("show");
    }
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}
</script> 