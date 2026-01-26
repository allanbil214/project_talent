<?php
// public/unauthorized.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #333;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 550px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .error-code {
            font-size: 100px;
            font-weight: 900;
            color: #667eea;
            line-height: 1;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 32px;
            margin-bottom: 15px;
            color: #333;
        }
        p {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        .flash-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon">🚫</div>
        <div class="error-code">403</div>
        <h1>Unauthorized Access</h1>
        
        <?php
        $flash = getFlash();
        if ($flash && $flash['type'] === 'error'):
        ?>
        <div class="flash-message">
            <?php echo htmlspecialchars($flash['message']); ?>
        </div>
        <?php endif; ?>
        
        <p>You don't have permission to access this page. This area is restricted to authorized users only.</p>
        
        <div>
            <?php if (isLoggedIn()): ?>
                <a href="<?php 
                    $role = getCurrentUserRole();
                    if ($role === ROLE_TALENT) echo SITE_URL . '/public/talent/dashboard.php';
                    elseif ($role === ROLE_EMPLOYER) echo SITE_URL . '/public/employer/dashboard.php';
                    elseif ($role === ROLE_SUPER_ADMIN || $role === ROLE_STAFF) echo SITE_URL . '/public/admin/dashboard.php';
                    else echo SITE_URL . '/public/index.php';
                ?>" class="btn">Go to Dashboard</a>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/public/login.php" class="btn">Login</a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/public/index.php" class="btn btn-secondary">Go Home</a>
        </div>
    </div>
</body>
</html>