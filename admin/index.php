<?php
/**
 * IntuiFy Admin — Login Page
 */

declare(strict_types=1);

session_start();

// Already authenticated? Go to dashboard
if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

$config = require dirname(__DIR__) . '/config.php';
$error = '';
$expired = isset($_GET['expired']);

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    require_once __DIR__ . '/includes/auth.php';
    
    if (attemptLogin($username, $password, $config)) {
        header('Location: /admin/dashboard.php');
        exit;
    } else {
        $error = 'Credenziali non valide.';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login — IntuiFy Admin</title>
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #08080f;
            font-family: 'Inter', -apple-system, sans-serif;
            padding: 1rem;
        }
        
        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: 
                radial-gradient(ellipse 600px 400px at 20% 50%, rgba(99, 102, 241, 0.08), transparent),
                radial-gradient(ellipse 500px 300px at 80% 30%, rgba(139, 92, 246, 0.06), transparent);
            pointer-events: none;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: rgba(15, 15, 26, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.4);
        }
        
        .login-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2.5rem;
        }
        
        .login-logo img {
            height: 28px;
            filter: invert(1) brightness(2);
        }
        
        .login-logo span {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #6366f1;
            background: rgba(99, 102, 241, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        label {
            display: block;
            font-size: 0.6875rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }
        
        input {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            color: white;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            outline: none;
            font-family: inherit;
            transition: all 0.2s;
        }
        
        input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        }
        
        input::placeholder { color: #334155; }
        
        .login-btn {
            width: 100%;
            padding: 0.75rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: white;
            background: #6366f1;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
            margin-top: 0.5rem;
            box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
        }
        
        .login-btn:hover {
            background: #5558e6;
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            transform: translateY(-1px);
        }
        
        .login-btn:active {
            transform: scale(0.98);
        }
        
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            font-size: 0.8125rem;
            padding: 0.75rem 1rem;
            border-radius: 0.625rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }
        
        .info-msg {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            font-size: 0.8125rem;
            padding: 0.75rem 1rem;
            border-radius: 0.625rem;
            margin-bottom: 1.25rem;
            text-align: center;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #475569;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #94a3b8; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <img src="/logo/intuifylogo.svg" alt="IntuiFy">
                <span>Area Riservata</span>
            </div>
            
            <?php if ($error): ?>
                <div class="error-msg"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($expired): ?>
                <div class="info-msg">Sessione scaduta. Effettua di nuovo il login.</div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Il tuo username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="login-btn">Accedi</button>
            </form>
        </div>
        <a href="/" class="back-link">← Torna al sito</a>
    </div>
</body>
</html>
