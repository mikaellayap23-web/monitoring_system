<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$divisions = $pdo->query("SELECT name FROM divisions ORDER BY name")->fetchAll();

// Fetch departments only (no section/unit)
$departments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $division = $_POST['division'];
        $department = $_POST['department'];
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error_msg = 'Username or email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash, role, division, department, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $full_name, $password_hash, $role, $division, $department, $_SESSION['user_id']])) {
                $success_msg = 'User added successfully!';
                header('Location: user_management.php');
                exit;
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
        $department = $_POST['department'];
        
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, division = ?, department = ? WHERE id = ?");
        $stmt->execute([$full_name, $role, $division, $department, $id]);
        $success_msg = 'User updated successfully!';
        header('Location: user_management.php');
        exit;
    }
}

$users = $pdo->query("SELECT *, COALESCE(department, '') as department FROM users ORDER BY created_at DESC")->fetchAll();

$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT *, COALESCE(department, '') as department FROM users WHERE id = ?");
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
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <button class="btn-open-modal" id="openAddModalBtn">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
            
            <!-- Add User Modal -->
            <div id="addUserModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                        <span class="close-modal" onclick="closeAddModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form method="post">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Username *</label>
                                    <input type="text" name="username" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email *</label>
                                    <input type="email" name="email" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> Full Name *</label>
                                    <input type="text" name="full_name" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Password *</label>
                                    <input type="password" name="password" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Role *</label>
                                    <select name="role" required>
                                        <option value="employee">Employee</option>
                                        <option value="unit_head">Unit Head</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Division *</label>
                                    <select name="division" required>
                                        <option value="">-- Select Division --</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-sitemap"></i> Department *</label>
                                    <select name="department" required>
                                        <option value="">-- Select Department --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="button-container">
                                <button type="submit" name="add_user" class="btn-submit"><i class="fas fa-save"></i> Add User</button>
                                <button type="button" class="btn-cancel" onclick="closeAddModal()"><i class="fas fa-times"></i> Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit User Modal -->
            <div id="editUserModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                        <span class="close-modal" onclick="closeEditModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form method="post">
                            <input type="hidden" name="user_id" id="edit_user_id">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <div class="readonly-display" id="edit_email"></div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> Full Name *</label>
                                    <input type="text" name="full_name" id="edit_full_name" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Role *</label>
                                    <select name="role" id="edit_role" required>
                                        <option value="employee">Employee</option>
                                        <option value="unit_head">Unit Head</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Division *</label>
                                    <select name="division" id="edit_division" required>
                                        <option value="">-- Select Division --</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-sitemap"></i> Department *</label>
                                    <select name="department" id="edit_department" required>
                                        <option value="">-- Select Department --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="button-container">
                                <button type="submit" name="edit_user" class="btn-submit"><i class="fas fa-save"></i> Update User</button>
                                <button type="button" class="btn-cancel" onclick="closeEditModal()"><i class="fas fa-times"></i> Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-list"></i> All Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Division</th>
                            <th>Department</th>
                            <th>Actions</th>
                        </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></div>
                                <td><?= htmlspecialchars($user['full_name'] ?? '') ?></div>
                                <td><?= htmlspecialchars($user['email']) ?></div>
                                <td>
                                    <?php
                                    $roleClass = '';
                                    if ($user['role'] == 'admin') {
                                        $roleClass = 'role-admin';
                                    } elseif ($user['role'] == 'unit_head') {
                                        $roleClass = 'role-unit_head';
                                    } else {
                                        $roleClass = 'role-employee';
                                    }
                                    ?>
                                    <span class="role-badge <?= $roleClass ?>"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></span>
                                 </div>
                                <td><?= htmlspecialchars($user['division'] ?? '') ?></div>
                                <td><?= htmlspecialchars($user['department'] ?? '') ?></div>
                                <td>
                                    <button class="btn-edit" onclick='openEditModal(<?= json_encode($user) ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn-delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                    <?php endif; ?>
                                 </div>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        var addModal = document.getElementById('addUserModal');
        var openAddBtn = document.getElementById('openAddModalBtn');
        var editModal = document.getElementById('editUserModal');
        
        function openAddModal() {
            addModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddModal() {
            addModal.style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addUserModal').querySelector('form').reset();
        }
        
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_email').innerHTML = user.email;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_division').value = user.division || '';
            document.getElementById('edit_department').value = user.department || '';
            
            editModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditModal() {
            editModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        if (openAddBtn) {
            openAddBtn.onclick = openAddModal;
        }
        
        window.onclick = function(event) {
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>