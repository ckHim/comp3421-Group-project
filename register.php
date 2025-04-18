<?php
session_start();
include 'config.php';

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$register_error = '';
$register_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $register_error = "Invalid CSRF token.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate inputs
        if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
            $register_error = "Username must be 3-20 characters and contain only letters or numbers.";
        } elseif (strlen($password) < 8) {
            $register_error = "Password must be at least 8 characters.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                if ($stmt->execute([$username, $password_hash])) {
                    $register_success = true;
                    header("Location: index.php");
                    exit;
                } else {
                    $register_error = "Registration failed. Please try again.";
                }
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $register_error = "An error occurred. Please try again later.";
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
  <title>Create Account</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous">
  <style>
    body {
      margin: 0; font-family: 'Segoe UI', sans-serif; background: linear-gradient(to right, #f9f9f9, #dbeafe);
      height: 100vh; display: flex; align-items: center; justify-content: center;
    }
    .register-box {
      background: #fff; padding: 40px 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      width: 100%; max-width: 400px; text-align: center;
    }
    .register-box img { width: 80px; margin-bottom: 20px; }
    .register-box h2 { margin-bottom: 30px; font-weight: 600; color: #333; }
    .form-group { position: relative; }
    .form-group i { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #aaa; }
    .form-control { height: 50px; padding-left: 45px; border-radius: 30px; border: 1px solid #ccc; }
    .btn-register {
      background: #10b981; color: white; border: none; height: 50px; border-radius: 30px;
      font-weight: bold; font- size: 16px; margin-top: 15px;
    }
    .btn-register:hover { background: #059669; }
    .back-login { margin-top: 15px; font-size: 14px; }
    .back-login a { color: #3b82f6; text-decoration: none; }
    .back-login a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="register-box">
    <img src="https://cdn-icons-png.flaticon.com/512/847/847969.png" alt="User Icon">
    <h2>Create an Account</h2>

    <?php if ($register_success): ?>
      <div class="alert alert-success">Registration successful! Redirecting to login...</div>
    <?php elseif ($register_error): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($register_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
      <div class="form-group">
        <i class="fas fa-user"></i>
        <input type="text" name="username" class="form-control" placeholder="Username" required>
      </div>
      <div class="form-group">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" class="form-control" placeholder="Password" required>
      </div>
      <button type="submit" class="btn btn-register btn-block">Register</button>
      <div class="back-login">
        Already have an account? <a href="index.php">Login here</a>
      </div>
    </form>
  </div>
</body>
</html>