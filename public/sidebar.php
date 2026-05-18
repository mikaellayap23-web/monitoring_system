<?php
// No CSS link needed; styles are in sidebar.css included via dashboard.php
?>
<div class="sidebar">
    <h3>Monitoring System</h3>
    <ul>
        <li><a href="dashboard.php">🏠 Dashboard</a></li>
        <li><a href="#">📊 Reports</a></li>
        <li><a href="#">🔔 Notifications</a></li>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li><a href="user_management.php">👥 User Management</a></li>
            <li><a href="#">⚙️ Settings</a></li>
        <?php endif; ?>
        
        <?php if ($_SESSION['role'] === 'employee'): ?>
            <li><a href="#">📝 My Tasks</a></li>
            <li><a href="#">⏱️ Time Log</a></li>
        <?php endif; ?>
        
        <li><a href="logout.php">🚪 Logout</a></li>
    </ul>
</div>