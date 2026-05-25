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
    <!-- Profile Section Only - Centered -->
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
        <li><a href="<?= $base_url ?>/public/dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
        
        <?php if ($role === 'admin'): ?>
            <li><a href="<?= $base_url ?>/admin/department_management.php"><i class="fas fa-building"></i> <span>Department Management</span></a></li>
            <li><a href="<?= $base_url ?>/admin/user_management.php"><i class="fas fa-users"></i> <span>User Management</span></a></li>
            <li><a href="<?= $base_url ?>/admin/training_list.php"><i class="fas fa-calendar-alt"></i> <span>Training List</span></a></li>
        <?php endif; ?>
        
        <?php if ($role === 'unit_head'): ?>
            <li><a href="<?= $base_url ?>/public/users_management.php"><i class="fas fa-users"></i> <span>User Management</span></a></li>
            <li><a href="<?= $base_url ?>/public/trainings.php"><i class="fas fa-calendar-alt"></i> <span>Trainings</span></a></li>
        <?php endif; ?>
        
        <?php if ($role === 'employee'): ?>
            <li><a href="<?= $base_url ?>/public/my_trainings.php"><i class="fas fa-calendar-check"></i> <span>My Trainings</span></a></li>
        <?php endif; ?>
        
        <li class="logout-item"><a href="<?= $base_url ?>/public/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
    </ul>
</div>