<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
$stmt->execute();
$totalUsers = $stmt->fetch()['total_users'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Monitoring System</title>
    <link rel="stylesheet" href="../assets/css/sidebar.css">
	<link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Welcome, <?= htmlspecialchars($username) ?>!</h1>
                <div class="user-info">Role: <strong><?= ucfirst($role) ?></strong></div>
            </div>
            
            <div class="dashboard-widgets">
                <div class="widget">
                    <h3>Total Users</h3>
                    <p><?= $totalUsers ?></p>
                </div>
                <div class="widget">
                    <h3>Your Role</h3>
                    <p><?= ucfirst($role) ?></p>
                </div>
                <div class="widget">
                    <h3>Status</h3>
                    <p>Active</p>
                </div>
            </div>
            
            <?php if ($role === 'admin'): ?>
                <div class="admin-panel">
                    <h2>Admin Controls</h2>
                    <p>You have full access. <a href="user_management.php">Manage Users</a> (add, edit roles, delete).</p>
                </div>
            <?php endif; ?>
            
            <?php if ($role === 'employee'): ?>
                <div class="employee-panel">
                    <h2>My Tasks</h2>
                    <p>Task list and monitoring data will appear here soon.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>