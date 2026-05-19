<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: user_management.php?msg=deleted');
    exit;
}

$success_msg = '';
$error_msg = '';

// Fetch unique divisions for dropdown
$divisionStmt = $pdo->query("SELECT DISTINCT division FROM users WHERE division IS NOT NULL ORDER BY division");
$divisions = $divisionStmt->fetchAll();

// Fetch unique department/section/unit combinations for dropdown
$deptSectionUnitStmt = $pdo->query("SELECT DISTINCT department, section, unit FROM users WHERE department IS NOT NULL AND section IS NOT NULL ORDER BY department, section, unit");
$deptSectionUnitOptions = $deptSectionUnitStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $division = $_POST['division'];

        // Split Department/Section/Unit dropdown value
        $dept_section_unit = $_POST['dept_section_unit'];
        $parts = explode('/', $dept_section_unit);
        $department = $parts[0] ?? null;
        $section = $parts[1] ?? null;
        $unit = $parts[2] ?? null;
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error_msg = 'Username or email already exists.';
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
    
    if (isset($_POST['edit_user'])) {
        $id = $_POST['user_id'];
        $full_name = $_POST['full_name'];
        $role = $_POST['role'];
        $division = $_POST['division'];

        // Split Department/Section/Unit dropdown value for edit
        $dept_section_unit = $_POST['dept_section_unit'];
        $parts = explode('/', $dept_section_unit);
        $department = $parts[0] ?? null;
        $section = $parts[1] ?? null;
        $unit = $parts[2] ?? null;
        
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, division = ?, department = ?, section = ?, unit = ? WHERE id = ?");
        $stmt->execute([$full_name, $role, $division, $department, $section, $unit, $id]);
        $success_msg = 'User updated successfully!';
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_management.css">
</head>
<body>
    <div class="app-container">
        <?php include '../public/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <div><i class="fas fa-user-shield"></i> Admin: <?= htmlspecialchars($_SESSION['username']) ?></div>
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
                            <label><i class="fas fa-user"></i> Username *</label>
                            <input type="text" name="username" value="<?= $edit_user['username'] ?? '' ?>" <?= $edit_user ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email *</label>
                            <input type="email" name="email" value="<?= $edit_user['email'] ?? '' ?>" <?= $edit_user ? 'readonly' : 'required' ?>>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Full Name *</label>
                            <input type="text" name="full_name" value="<?= $edit_user['full_name'] ?? '' ?>" required>
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
                            <label><i class="fas fa-tag"></i> Role *</label>
                            <select name="role" required>
                                <option value="employee" <?= ($edit_user['role'] ?? '') === 'employee' ? 'selected' : '' ?>>Employee</option>
                                <option value="unit_head" <?= ($edit_user['role'] ?? '') === 'unit_head' ? 'selected' : '' ?>>Unit Head</option>
                                <option value="admin" <?= ($edit_user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                        
                        <!-- UPDATED: Division as dropdown -->
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Division *</label>
                            <select name="division" required>
                                <option value="">-- Select Division --</option>
                                <?php foreach ($divisions as $div): ?>
                                    <option value="<?= htmlspecialchars($div['division']) ?>" 
                                        <?= ($edit_user['division'] ?? '') === $div['division'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($div['division']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <!-- Combined Department/Section/Unit dropdown -->
                        <div class="form-group">
                            <label><i class="fas fa-sitemap"></i> Department/Section/Unit *</label>
                            <select name="dept_section_unit" required>
                                <option value="">-- Select Department/Section/Unit --</option>
                                <?php foreach ($deptSectionUnitOptions as $option): ?>
                                    <?php 
                                    $combined = htmlspecialchars($option['department']) . '/' . 
                                                htmlspecialchars($option['section']) . '/' . 
                                                htmlspecialchars($option['unit']);
                                    $selected = '';
                                    if ($edit_user) {
                                        $userCombined = htmlspecialchars($edit_user['department'] ?? '') . '/' . 
                                                       htmlspecialchars($edit_user['section'] ?? '') . '/' . 
                                                       htmlspecialchars($edit_user['unit'] ?? '');
                                        if ($userCombined === $combined) {
                                            $selected = 'selected';
                                        }
                                    }
                                    ?>
                                    <option value="<?= $combined ?>" <?= $selected ?>><?= $combined ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="<?= $edit_user ? 'edit_user' : 'add_user' ?>" class="btn-submit">
                        <i class="fas <?= $edit_user ? 'fa-save' : 'fa-user-plus' ?>"></i> <?= $edit_user ? 'Update User' : 'Add User' ?>
                    </button>
                    <?php if ($edit_user): ?>
                        <a href="user_management.php" class="btn-submit" style="background: #666; text-decoration: none;"><i class="fas fa-times"></i> Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Division</th>
                            <th>Department/Section/Unit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= $user['role'] ?></td>
                                <td><?= htmlspecialchars($user['division'] ?? '') ?></td>
                                <td><?= htmlspecialchars(($user['department'] ?? '') . '/' . ($user['section'] ?? '') . '/' . ($user['unit'] ?? '')) ?></td>
                                <td>
                                    <a href="?edit=<?= $user['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn-delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash-alt"></i> Delete</a>
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