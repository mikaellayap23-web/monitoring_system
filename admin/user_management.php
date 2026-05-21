<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$divisions = $pdo->query("SELECT name FROM divisions ORDER BY name")->fetchAll();

$departments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll();

$deptSectionUnitOptions = [];
foreach ($departments as $dept) {
    $deptSectionUnitOptions[] = $dept['name'] . ' / / ';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $full_name = $_POST['full_name'];
        $password = $_POST['password'];
        $role = $_POST['role'];
        $division = $_POST['division'];
        
        $dept_section_unit = $_POST['dept_section_unit'];
        $parts = explode(' / ', $dept_section_unit);
        $department = $parts[0] ?? '';
        $section = $parts[1] ?? '';
        $unit = $parts[2] ?? '';
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error_msg = 'Username or email already exists.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash, role, division, department, section, unit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$username, $email, $full_name, $password_hash, $role, $division, $department, $section, $unit, $_SESSION['user_id']])) {
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
        
        $dept_section_unit = $_POST['dept_section_unit'];
        $parts = explode(' / ', $dept_section_unit);
        $department = $parts[0] ?? '';
        $section = $parts[1] ?? '';
        $unit = $parts[2] ?? '';
        
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, division = ?, department = ?, section = ?, unit = ? WHERE id = ?");
        $stmt->execute([$full_name, $role, $division, $department, $section, $unit, $id]);
        $success_msg = 'User updated successfully!';
        header('Location: user_management.php');
        exit;
    }
}

$users = $pdo->query("SELECT *, CONCAT(COALESCE(department,''), ' / ', COALESCE(section,''), ' / ', COALESCE(unit,'')) as dept_section_unit FROM users ORDER BY created_at DESC")->fetchAll();

$edit_user = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT *, CONCAT(COALESCE(department,''), ' / ', COALESCE(section,''), ' / ', COALESCE(unit,'')) as dept_section_unit FROM users WHERE id = ?");
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
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .btn-open-modal {
            background: #1B3C53;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-open-modal:hover {
            background: #0f2a3a;
        }
        .btn-open-modal i {
            margin-right: 8px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            overflow: visible;
        }
        
        .modal-header {
            padding: 15px 20px;
            background: #1B3C53;
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .modal-header h2 i {
            margin-right: 8px;
        }
        
        .close-modal {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover {
            color: #ddd;
        }
        
        .modal-body {
            padding: 25px;
            padding-bottom: 60px;
            overflow: visible;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: visible;
        }
        
        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-group label i {
            margin-right: 8px;
            color: #1B3C53;
        }
        
        .form-group input,
        .form-group select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group select {
            position: relative;
        }
        
        .btn-submit {
            background: #1B3C53;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-submit:hover {
            background: #0f2a3a;
        }
        
        .btn-edit, .btn-delete {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-edit {
            background: #456882;
            color: white;
        }
        .btn-delete {
            background: #dc2626;
            color: white;
        }
        
        .data-table {
            overflow-x: auto;
            margin-top: 20px;
        }
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: #1B3C53;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        
        .readonly-display {
            background: #f5f5f5;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #666;
        }
        
        small {
            font-size: 11px;
            color: #888;
            margin-top: 5px;
        }
    </style>
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
                                    <label><i class="fas fa-sitemap"></i> Department/Section/Unit *</label>
                                    <select name="dept_section_unit" required>
                                        <option value="">-- Select Department/Section/Unit --</option>
                                        <?php foreach ($deptSectionUnitOptions as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Format: Department / Section / Unit</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <button type="submit" name="add_user" class="btn-submit"><i class="fas fa-save"></i> Add User</button>
                                <button type="button" class="btn-submit" onclick="closeAddModal()" style="background: #666;"><i class="fas fa-times"></i> Cancel</button>
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
                                    <label><i class="fas fa-sitemap"></i> Department/Section/Unit *</label>
                                    <select name="dept_section_unit" id="edit_dept_section_unit" required>
                                        <option value="">-- Select Department/Section/Unit --</option>
                                        <?php foreach ($deptSectionUnitOptions as $option): ?>
                                            <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <button type="submit" name="edit_user" class="btn-submit"><i class="fas fa-save"></i> Update User</button>
                                <button type="button" class="btn-submit" onclick="closeEditModal()" style="background: #666;"><i class="fas fa-times"></i> Cancel</button>
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
                            <th>Department/Section/Unit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= $user['role'] ?></td>
                                <td><?= htmlspecialchars($user['division'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['dept_section_unit'] ?? '') ?></td>
                                <td>
                                    <button class="btn-edit" onclick='openEditModal(<?= json_encode($user) ?>)'>
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
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
    
    <script>
        var addModal = document.getElementById('addUserModal');
        var openAddBtn = document.getElementById('openAddModalBtn');
        var editModal = document.getElementById('editUserModal');
        
        function openAddModal() {
            addModal.style.display = 'block';
        }
        
        function closeAddModal() {
            addModal.style.display = 'none';
            document.getElementById('addUserModal').querySelector('form').reset();
        }
        
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_email').innerHTML = user.email;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_division').value = user.division || '';
            
            var deptSectionUnit = (user.department || '') + ' / ' + (user.section || '') + ' / ' + (user.unit || '');
            var select = document.getElementById('edit_dept_section_unit');
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value === deptSectionUnit) {
                    select.selectedIndex = i;
                    break;
                }
            }
            
            editModal.style.display = 'block';
        }
        
        function closeEditModal() {
            editModal.style.display = 'none';
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