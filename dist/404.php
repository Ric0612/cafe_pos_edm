<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>404 - Page Not Found - Café POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../img/cafe-logo.jpg" type="image/jpg">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .coffee-icon {
            font-size: 80px;
            color: #6c4f3d;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #6c4f3d;
            margin: 0;
            line-height: 1;
        }
        .error-message {
            font-size: 24px;
            color: #495057;
            margin: 20px 0;
        }
        .error-description {
            color: #6c757d;
            margin-bottom: 30px;
        }
        .home-button {
            background-color: #6c4f3d;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
            border: none;
        }
        .home-button:hover {
            background-color: #5a4232;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(108, 79, 61, 0.2);
        }
        .coffee-steam {
            position: relative;
            display: inline-block;
        }
        .steam {
            position: absolute;
            width: 8px;
            height: 20px;
            background: #6c4f3d;
            border-radius: 10px;
            opacity: 0;
            animation: steam 2s infinite;
        }
        .steam:nth-child(1) { left: -15px; animation-delay: 0.2s; }
        .steam:nth-child(2) { left: 0; animation-delay: 0.4s; }
        .steam:nth-child(3) { left: 15px; animation-delay: 0.6s; }

        @keyframes steam {
            0% {
                transform: translateY(0) scaleX(1);
                opacity: 0;
            }
            15% {
                opacity: 1;
            }
            50% {
                transform: translateY(-30px) scaleX(3);
                opacity: 0;
            }
            100% {
                transform: translateY(-40px) scaleX(4);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="coffee-steam">
                <div class="steam"></div>
                <div class="steam"></div>
                <div class="steam"></div>
                <i class="fas fa-mug-hot coffee-icon"></i>
            </div>
            <h1 class="error-code">404</h1>
            <h2 class="error-message">Oops! Page Not Found</h2>
            <p class="error-description">
                Looks like this page took a coffee break! Don't worry, our other pages are still brewing.
            </p>
            <a href="/cafes_pos/index.php" class="home-button">Home</a>
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 