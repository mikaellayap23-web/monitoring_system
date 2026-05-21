<?php
if (!isset($_SESSION['user_id'])) {
    return;
}
$role = $_SESSION['role'];
$base_url = '/monitoring_system'; // Define once

// Get user info for profile (adjust queries based on your schema)
$user_name = $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'User';
$user_role_display = ucfirst(str_replace('_', ' ', $role));
?>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="Uploads/Images/armmc-logo.png" alt="Logo" class="sidebar-logo">
        <div class="sidebar-title">
            <h3>RMMC</h3>
            <span>Monitoring System</span>
        </div>
    </div>

    <!-- Profile Section Inside Sidebar -->
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($user_name) ?></div>
            <div class="profile-role"><?= htmlspecialchars($user_role_display) ?></div>
        </div>
    </div>

    <ul>
        <li><a href="<?= $base_url ?>/public/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
        
        <?php if ($role === 'admin'): ?>
            <li><a href="<?= $base_url ?>/admin/user_management.php"><i class="fas fa-users"></i> User Management</a></li>
            <li><a href="<?= $base_url ?>/admin/training_list.php"><i class="fas fa-calendar-alt"></i> Training List</a></li>
        <?php endif; ?>
        
        <?php if ($role === 'unit_head'): ?>
            <li><a href="<?= $base_url ?>/public/users_management.php"><i class="fas fa-users"></i> User Management</a></li>
            <li><a href="<?= $base_url ?>/public/trainings.php"><i class="fas fa-calendar-alt"></i> Trainings</a></li>
        <?php endif; ?>
        
        <?php if ($role === 'employee'): ?>
            <li><a href="<?= $base_url ?>/public/my_trainings.php"><i class="fas fa-calendar-check"></i> My Trainings</a></li>
        <?php endif; ?>
        
        <li class="logout-item"><a href="<?= $base_url ?>/public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>
