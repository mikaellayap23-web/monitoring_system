<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logged Out</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
    <meta http-equiv="refresh" content="2;url=login.php">
</head>
<body>
    <div class="auth-container">
        <h2>Logged Out</h2>
        <p>You have been successfully logged out. Redirecting to login...</p>
        <p><a href="login.php">Click here if not redirected</a></p>
    </div>
</body>
</html>