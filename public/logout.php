<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged Out</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <meta http-equiv="refresh" content="2;url=login.php">
</head>
<body>
    <div class="auth-container">
        <h2><i class="fas fa-sign-out-alt"></i> Logged Out</h2>
        <p><i class="fas fa-spinner fa-pulse"></i> You have been successfully logged out. Redirecting to login...</p>
        <p><a href="login.php"><i class="fas fa-sign-in-alt"></i> Click here if not redirected</a></p>
    </div>
</body>
</html>