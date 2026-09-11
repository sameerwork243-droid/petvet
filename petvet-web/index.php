<?php
require_once 'config/database.php';
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($identifier && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetVet - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .login-left { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; }
        .login-right { flex: 1; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #059669, #10b981); position: relative; overflow: hidden; }
        .login-right::before { content: ''; position: absolute; width: 600px; height: 600px; border-radius: 50%; background: rgba(255,255,255,0.05); top: -200px; right: -200px; }
        .login-right::after { content: ''; position: absolute; width: 400px; height: 400px; border-radius: 50%; background: rgba(255,255,255,0.05); bottom: -100px; left: -100px; }
        .brand { text-align: center; margin-bottom: 40px; }
        .brand h1 { font-size: 36px; font-weight: 800; background: linear-gradient(135deg, #059669, #34d399); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .brand p { color: #94a3b8; margin-top: 8px; font-size: 14px; }
        .login-box { background: #1e293b; border-radius: 16px; padding: 40px; width: 100%; max-width: 400px; border: 1px solid #334155; box-shadow: 0 25px 50px rgba(0,0,0,0.3); }
        .login-box h2 { color: #f1f5f9; font-size: 24px; margin-bottom: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #94a3b8; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 10px; color: #f1f5f9; font-size: 14px; transition: border-color 0.2s; outline: none; }
        .form-group input:focus { border-color: #10b981; }
        .btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #059669, #10b981); color: white; border: none; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.3); }
        .error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
        .branding-side { position: relative; z-index: 1; text-align: center; color: white; }
        .branding-side h2 { font-size: 42px; font-weight: 800; margin-bottom: 16px; }
        .branding-side p { font-size: 16px; opacity: 0.85; max-width: 400px; line-height: 1.6; }
        .paw-icon { font-size: 80px; margin-bottom: 20px; filter: drop-shadow(0 4px 20px rgba(0,0,0,0.2)); }
        @media (max-width: 768px) { .login-right { display: none; } body { justify-content: center; } }
    </style>
</head>
<body>
    <div class="login-left">
        <div class="brand">
            <h1>PetVet</h1>
            <p>Veterinary Management System</p>
        </div>
        <div class="login-box">
            <h2>Welcome back</h2>
            <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Username or Email</label>
                    <input type="text" name="identifier" placeholder="Enter username or email" required value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn">Sign In</button>
            </form>
            <p style="color:#64748b;text-align:center;margin-top:20px;font-size:12px;">Default: admin / admin123</p>
        </div>
    </div>
    <div class="login-right">
        <div class="branding-side">
            <div class="paw-icon">&#128062;</div>
            <h2>PetVet Pro</h2>
            <p>Complete veterinary clinic management. Appointments, billing, medical records, boarding, and more — all in one place.</p>
        </div>
    </div>
</body>
</html>
