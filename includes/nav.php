<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
    <div class="container-fluid d-flex justify-content-between align-items-center">

        <!-- Center: Time display -->
        <div id="time" class="d-none d-md-flex align-items-center">
            <span id="philippine-time" class="navbar-text fw-bold" style="font-size: 1.2rem;"></span>
        </div>

        <!-- Right: Profile and Dropdown -->
        <div class="d-flex align-items-center">
            <ul class="navbar-nav">
                <!-- Profile Link -->
                <li class="nav-item me-3">
                    <a class="nav-link d-flex align-items-center" href="profile.php">
                        <i class="fas fa-user-circle me-2"></i>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </a>
                </li>

                <!-- Settings Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cog"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="navbarDropdown">
                        <?php if ($_SESSION['role'] === 'manager') : ?>
                            <li><a class="dropdown-item" href="audit-logs.php">
                                <i class="fas fa-history me-2"></i>Audit Logs
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="../includes/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
.navbar {
    box-shadow: 0 2px 4px rgba(0,0,0,.1);
}

.navbar .btn-link {
    color: #6c4f3d;
    text-decoration: none;
}

.navbar .btn-link:hover {
    color: #8b6b4d;
}

.navbar .nav-link {
    color: #6c4f3d !important;
    font-weight: 500;
}

.navbar .nav-link:hover {
    color: #8b6b4d !important;
}

.dropdown-item {
    padding: .5rem 1rem;
    display: flex;
    align-items: center;
}

.dropdown-item:hover {
    background-color: #f4e1c1;
}

#philippine-time {
    color: #6c4f3d;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    background-color: #f4e1c1;
}
</style>

<script>
    function updateTime() {
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit', 
            timeZone: 'Asia/Manila' 
        };
        const timeString = new Date().toLocaleString('en-US', options);
        document.getElementById('philippine-time').innerText = timeString;
    }

    // Update time immediately and then every second
    updateTime();
    setInterval(updateTime, 1000);
</script>
