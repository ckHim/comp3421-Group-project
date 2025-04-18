<?php
session_start();
include 'config.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$login_error = '';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $login_error = "Invalid CSRF token.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate inputs
        if (empty($username) || empty($password)) {
            $login_error = "Username and password are required.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $login_error = "Invalid username or password.";
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $login_error = "An error occurred. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous">
    <style>
        body {
            margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(to right, #f9f9f9, #dbeafe);
            height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .login-box {
            background: #fff; padding: 40px 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%; max-width: 400px; text-align: center;
        }
        .login-box img { width: 80px; margin-bottom: 20px; }
        .login-box h2 { margin-bottom: 30px; font-weight: 600; color: #333; }
        .form-group { position: relative; }
        .form-group i { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #aaa; }
        .form-control { height: 50px; padding-left: 45px; border-radius: 30px; border: 1px solid #ccc; }
        .btn-login {
            background: #3b82f6; color: white; border: none; height: 50px; border-radius: 30px;
            font-weight: bold; font-size: 16px; margin-top: 15px;
        }
        .btn-login:hover { background: #2563eb; }
        .options { display: flex; justify-content: center; font-size: 14px; margin-top: 10px; }
        .options a { color: #3b82f6; text-decoration: none; }
        .options a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-box">
        <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" alt="User Icon">
        <h2>Login</h2>
        
        <?php if ($login_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" class="form-control" name="username" placeholder="Username" required>
            </div>
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" class="form-control" name="password" placeholder="Password" required>
            </div>
            <div class="form-group form-check text-left mt-3">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <button type="submit" class="btn btn-login btn-block">Login</button>
            <div class="options mt-3">
                <a href="register.php">Create an account</a>
            </div>
        </form>
    </div>
</body>
</html>
