<?php
if (!isset($_SESSION['user_id'])) {
    return;
}
$role = $_SESSION['role'];
?>
<div class="sidebar">
    <h3><i class="fas fa-chart-line"></i> Monitoring System</h3>
    <ul>
        <li><a href="/monitoring_system/public/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
        
        <?php if ($role === 'admin'): ?>
            <li><a href="/monitoring_system/admin/user_management.php"><i class="fas fa-users"></i> User Management</a></li>
            <li><a href="/monitoring_system/admin/training_list.php"><i class="fas fa-calendar-alt"></i> Training List</a></li>
        <?php endif; ?>
        
        <?php if ($role === 'unit_head'): ?>
            <li><a href="#"><i class="fas fa-users"></i> My Unit</a></li>
            <li><a href="#"><i class="fas fa-calendar-alt"></i> Unit Trainings</a></li>
        <?php endif; ?>
        
        <?php if ($role === 'employee'): ?>
            <li><a href="/monitoring_system/public/my_trainings.php"><i class="fas fa-calendar-check"></i> My Trainings</a></li>
        <?php endif; ?>
        
        <li><a href="/monitoring_system/public/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>