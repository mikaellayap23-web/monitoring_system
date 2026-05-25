<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_fullname = trim($_POST['email_or_fullname'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email_or_fullname) || empty($password)) {
        $error = 'Please enter email or full name and password.';
    } else {
        // Search by email OR full_name
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR full_name = ?");
        $stmt->execute([$email_or_fullname, $email_or_fullname]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid email/full name or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - RMMC Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <!-- Logo / Icon Section -->
        <div class="logo-wrapper">
            <img src="Uploads/Images/armmc-logo.png" alt="RMMC Logo" class="login-logo">
            <div class="hospital-name">
                <span class="hospital-title">RMMC Monitoring System</span>
            </div>
        </div>

        <h2><i class="fas fa-lock"></i> Login</h2>
        
        <?php if ($error): ?>
            <div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="post" id="loginForm">
            <div class="input-group">
                <i class="fas fa-envelope input-icon"></i>
                <input type="text" name="email_or_fullname" placeholder="Email or Full Name" required autofocus>
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" name="password" id="password" placeholder="Password" required>
                <i class="fas fa-eye-slash toggle-password" id="togglePassword"></i>
            </div>
            
            <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
    </div>

    <script>
        // Password eye toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>