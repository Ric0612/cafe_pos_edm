<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c4f3d;
            --secondary-color: #f4e1c1;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, #8b6b56 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .unauthorized-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .unauthorized-icon {
            font-size: 80px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .unauthorized-title {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 20px;
        }
        
        .unauthorized-message {
            color: #6c757d;
            margin-bottom: 30px;
        }
        
        .back-button {
            background: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .back-button:hover {
            background: #5a4232;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 79, 61, 0.2);
        }
    </style>
</head>
<body>
    <div class="unauthorized-container">
        <i class="fas fa-exclamation-circle unauthorized-icon"></i>
        <h1 class="unauthorized-title">Unauthorized Access</h1>
        <p class="unauthorized-message">
            Sorry, you don't have permission to access this page. 
            <?php if (isset($_SESSION['role'])): ?>
                Please contact your manager if you believe this is an error.
            <?php else: ?>
                Please log in to access this feature.
            <?php endif; ?>
        </p>
        <?php if (isset($_SESSION['role'])): ?>
            <?php
            $redirect_url = '';
            switch ($_SESSION['role']) {
                case 'cashier':
                    $redirect_url = 'sales.php';
                    break;
                case 'kitchen':
                    $redirect_url = 'orders.php';
                    break;
                default:
                    $redirect_url = 'dashboard.php';
            }
            ?>
            <a href="<?php echo $redirect_url; ?>" class="back-button">
                <i class="fas fa-arrow-left me-2"></i>Back to Allowed Page
            </a>
        <?php else: ?>
            <a href="../index.php" class="back-button">
                <i class="fas fa-sign-in-alt me-2"></i>Login
            </a>
        <?php endif; ?>
    </div>
</body>
</html> 