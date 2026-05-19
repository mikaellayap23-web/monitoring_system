<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

// Check if user is Unit Head
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Get Unit Head's department, section, unit info
$userStmt = $pdo->prepare("SELECT division, department, section, unit, full_name, username FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$current_user = $userStmt->fetch();

// If not unit head, redirect
if ($user_role !== 'unit_head') {
    header('Location: ../public/dashboard.php');
    exit;
}

// Handle delete - only allow deleting staff under same unit
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Verify this user belongs to unit head's team and is not a unit head or admin
    $checkStmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE id = ? 
        AND division = ?
        AND department = ? 
        AND section = ? 
        AND unit = ?
        AND role = 'employee'
    ");
    $checkStmt->execute([$id, $current_user['division'], $current_user['department'], $current_user['section'], $current_user['unit']]);
    if ($checkStmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = 'User deleted successfully!';
    } else {
        $error_msg = 'You can only delete employees from your unit.';
    }
    header('Location: users_management.php?msg=' . ($success_msg ? 'deleted' : 'error'));
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle add user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Generate username from email (before @ symbol)
    $username = explode('@', $email)[0];
    
    // Force to unit head's division, department, section, unit
    $division = $current_user['division'];
    $department = $current_user['department'];
    $section = $current_user['section'];
    $unit = $current_user['unit'];
    
    // Force role to employee for unit head
    $role = 'employee';
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        $error_msg = 'Email already exists.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash, role, division, department, section, unit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $full_name, $password_hash, $role, $division, $department, $section, $unit, $_SESSION['user_id']])) {
            $success_msg = 'User added successfully!';
        } else {
            $error_msg = 'Failed to add user.';
        }
    }
}

// Handle edit user
if (isset($_POST['edit_user'])) {
    $id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    
    // Verify this user belongs to unit head's team and is employee
    $checkStmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE id = ? 
        AND division = ?
        AND department = ? 
        AND section = ? 
        AND unit = ?
        AND role = 'employee'
    ");
    $checkStmt->execute([$id, $current_user['division'], $current_user['department'], $current_user['section'], $current_user['unit']]);
    if ($checkStmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->execute([$full_name, $id]);
        $success_msg = 'User updated successfully!';
    } else {
        $error_msg = 'You can only edit employees from your unit.';
    }
}

// Get users under this Unit Head (same division, department, section, unit, and role = employee)
$usersStmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE division = ?
    AND department = ? 
    AND section = ? 
    AND unit = ?
    AND role = 'employee'
    ORDER BY created_at DESC
");
$usersStmt->execute([$current_user['division'], $current_user['department'], $current_user['section'], $current_user['unit']]);
$users = $usersStmt->fetchAll();

$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE id = ? 
        AND division = ?
        AND department = ? 
        AND section = ? 
        AND unit = ?
        AND role = 'employee'
    ");
    $stmt->execute([$_GET['edit'], $current_user['division'], $current_user['department'], $current_user['section'], $current_user['unit']]);
    $edit_user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Unit Head</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_management.css">
    <style>
        .info-box {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
        }
        .info-box i {
            color: #3498db;
            margin-right: 10px;
        }
        .readonly-field {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../public/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <div><i class="fas fa-user-tie"></i> Unit Head: <?= htmlspecialchars($current_user['full_name'] ?: $current_user['username']) ?></div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i> 
                You are managing users under: <strong><?= htmlspecialchars($current_user['division']) ?> / <?= htmlspecialchars($current_user['department']) ?> / <?= htmlspecialchars($current_user['section']) ?> / <?= htmlspecialchars($current_user['unit']) ?></strong>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <div class="section-card">
                <h3><i class="fas <?= $edit_user ? 'fa-user-edit' : 'fa-user-plus' ?>"></i> <?= $edit_user ? 'Edit User' : 'Add New User' ?></h3>
                <form method="post">
                    <?php if ($edit_user): ?>
                        <input type="hidden" name="user_id" value="<?= $edit_user['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Full Name *</label>
                            <input type="text" name="full_name" value="<?= $edit_user['full_name'] ?? '' ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" value="<?= $edit_user['email'] ?? '' ?>" <?= $edit_user ? 'readonly' : 'required' ?>>
                            <?php if (!$edit_user): ?>
                                <small style="font-size: 11px; color: #666;">Username will be auto-generated from email</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!$edit_user): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Password *</label>
                                <input type="password" name="password" required>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Division *</label>
                            <input type="text" class="readonly-field" value="<?= htmlspecialchars($current_user['division']) ?>" readonly disabled style="background:#f5f5f5; padding:10px; border:1px solid #ddd; border-radius:5px; width:100%">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Department/Section/Unit *</label>
                            <input type="text" class="readonly-field" value="<?= htmlspecialchars($current_user['department']) ?> / <?= htmlspecialchars($current_user['section']) ?> / <?= htmlspecialchars($current_user['unit']) ?>" readonly disabled style="background:#f5f5f5; padding:10px; border:1px solid #ddd; border-radius:5px; width:100%">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-info-circle"></i> Role</label>
                            <input type="text" class="readonly-field" value="Employee (Auto-assigned)" readonly disabled style="background:#f5f5f5; padding:10px; border:1px solid #ddd; border-radius:5px; width:100%">
                        </div>
                    </div>
                    
                    <button type="submit" name="<?= $edit_user ? 'edit_user' : 'add_user' ?>" class="btn-submit">
                        <i class="fas <?= $edit_user ? 'fa-save' : 'fa-user-plus' ?>"></i> <?= $edit_user ? 'Update User' : 'Add User' ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="users_management.php" class="btn-submit" style="background: #666; text-decoration: none;"><i class="fas fa-times"></i> Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-list"></i> Users Under <?= htmlspecialchars($current_user['department']) ?> / <?= htmlspecialchars($current_user['section']) ?> / <?= htmlspecialchars($current_user['unit']) ?> (<?= count($users) ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Division</th>
                            <th>Department/Section/Unit</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No users found in your unit. Add your first user above.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span style="background: #3498db; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                                        <?= $user['role'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['division'] ?? '') ?></td>
                                <td><?= htmlspecialchars(($user['department'] ?? '') . '/' . ($user['section'] ?? '') . '/' . ($user['unit'] ?? '')) ?></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <a href="?edit=<?= $user['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn-delete" onclick="return confirm('Delete this user? This action cannot be undone.')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>