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

// Handle AJAX Edit Request
if (isset($_GET['ajax_get_user'])) {
    $id = $_GET['ajax_get_user'];
    // Verify this user belongs to unit head's team and is employee
    $stmt = $pdo->prepare("
        SELECT id, full_name, email 
        FROM users 
        WHERE id = ? 
        AND division = ?
        AND department = ? 
        AND section = ? 
        AND unit = ?
        AND role = 'employee'
    ");
    $stmt->execute([$id, $current_user['division'], $current_user['department'], $current_user['section'], $current_user['unit']]);
    $user = $stmt->fetch();
    
    if ($user) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found or unauthorized']);
    }
    exit;
}

// Handle AJAX Edit Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_user'])) {
    $id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    
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
        // Check if email already exists for another user
        $emailCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $emailCheck->execute([$email, $id]);
        if ($emailCheck->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Email already exists for another user.']);
            exit;
        }
        
        // Generate new username from email
        $username = explode('@', $email)[0];
        
        // Update user
        if (!empty($password)) {
            // Update with new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ?, password_hash = ? WHERE id = ?");
            $result = $stmt->execute([$full_name, $email, $username, $password_hash, $id]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, username = ? WHERE id = ?");
            $result = $stmt->execute([$full_name, $email, $username, $id]);
        }
        
        if ($result) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You can only edit employees from your unit.']);
    }
    exit;
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

// Get success/error message from URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') {
        $success_msg = 'User deleted successfully!';
    } elseif ($_GET['msg'] === 'error') {
        $error_msg = 'Failed to delete user.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management - Unit Head</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_management.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .info-box {
            background: #e8f4fd;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #1B3C53;
        }
        .info-box i {
            color: #1B3C53;
            margin-right: 10px;
        }
        .readonly-field {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }
        
        /* Modal Styles */
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
            transition: background 0.3s;
        }
        
        .btn-open-modal:hover {
            background: #0f2a3a;
        }
        
        .btn-open-modal i {
            margin-right: 8px;
        }
        
        .btn-edit-modal {
            background: #456882;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }
        
        .btn-edit-modal:hover {
            background: #2c4e6e;
        }
        
        .btn-delete {
            background: #dc2626;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
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
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            animation: slideDown 0.3s;
            overflow: visible;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
            margin-right: 10px;
        }
        
        .close-modal {
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .close-modal:hover {
            color: #ddd;
            transform: scale(1.1);
        }
        
        .modal-body {
            padding: 25px;
            overflow: visible;
        }
        
        .modal-body .btn-submit {
            background: #1B3C53;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
            width: auto;
            display: inline-block;
        }
        
        .modal-body .btn-submit:hover {
            background: #0f2a3a;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
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
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #1B3C53;
            box-shadow: 0 0 0 2px rgba(27, 60, 83, 0.1);
        }
        
        .readonly-display {
            background-color: #f5f5f5;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #666;
            font-size: 14px;
        }
        
        small {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        .button-container {
            text-align: left;
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 40px;
            display: none;
        }
        
        .loading-spinner i {
            font-size: 40px;
            color: #1B3C53;
        }
        
        .edit-form-container {
            display: block;
        }
        
        .edit-form-container.hide {
            display: none;
        }
        
        .alert-success-modal, .alert-error-modal {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-success-modal {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-error-modal {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .btn-cancel-modal {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-cancel-modal:hover {
            background: #5a6268;
        }
        
        .password-hint {
            font-size: 11px;
            color: #666;
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
            
            <!-- Add User Button -->
            <button class="btn-open-modal" onclick="openAddModal()">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
            
            <!-- Add User Modal -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
                        <span class="close-modal" onclick="closeAddModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="addUserForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-id-card"></i> Full Name *</label>
                                    <input type="text" name="full_name" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email *</label>
                                    <input type="email" name="email" required>
                                    <small>Username will be auto-generated from email</small>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Password *</label>
                                    <input type="password" name="password" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Division *</label>
                                    <div class="readonly-display"><?= htmlspecialchars($current_user['division']) ?></div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-sitemap"></i> Department/Section/Unit *</label>
                                    <div class="readonly-display"><?= htmlspecialchars($current_user['department']) ?> / <?= htmlspecialchars($current_user['section']) ?> / <?= htmlspecialchars($current_user['unit']) ?></div>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-info-circle"></i> Role</label>
                                    <div class="readonly-display">Employee (Auto-assigned)</div>
                                </div>
                            </div>
                            
                            <div class="button-container">
                                <button type="submit" name="add_user" class="btn-submit">
                                    <i class="fas fa-user-plus"></i> Add User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit User Modal - Only Full Name, Email, Password -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-user-edit"></i> Edit User</h2>
                        <span class="close-modal" onclick="closeEditModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <div id="editAlertSuccess" class="alert-success-modal">
                            <i class="fas fa-check-circle"></i> <span id="editSuccessMsg"></span>
                        </div>
                        <div id="editAlertError" class="alert-error-modal">
                            <i class="fas fa-exclamation-triangle"></i> <span id="editErrorMsg"></span>
                        </div>
                        <div id="loadingSpinner" class="loading-spinner">
                            <i class="fas fa-spinner fa-pulse"></i> Loading user data...
                        </div>
                        <div id="editFormContainer" class="edit-form-container hide">
                            <form id="editForm">
                                <input type="hidden" name="ajax_edit_user" value="1">
                                <input type="hidden" name="user_id" id="edit_user_id">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-id-card"></i> Full Name *</label>
                                        <input type="text" name="full_name" id="edit_full_name" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-envelope"></i> Email *</label>
                                        <input type="email" name="email" id="edit_email" required>
                                        <small>Username will be auto-generated from email</small>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-lock"></i> Password</label>
                                        <input type="password" name="password" id="edit_password" placeholder="Leave blank to keep current password">
                                        <small class="password-hint">Leave empty to keep current password</small>
                                    </div>
                                </div>
                                
                                <div class="button-container">
                                    <button type="button" class="btn-submit" onclick="submitEditForm()">
                                        <i class="fas fa-save"></i> Update User
                                    </button>
                                    <button type="button" class="btn-cancel-modal" onclick="closeEditModal()">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
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
                                    <span style="background: #1B3C53; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                                        <?= $user['role'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['division'] ?? '') ?></td>
                                <td><?= htmlspecialchars(($user['department'] ?? '') . ' / ' . ($user['section'] ?? '') . ' / ' . ($user['unit'] ?? '')) ?></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td class="action-buttons">
                                    <button onclick="openEditModal(<?= $user['id'] ?>)" class="btn-edit-modal">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $user['id'] ?>" class="btn-delete" onclick="return confirm('Delete this user? This action cannot be undone.')">
                                            <i class="fas fa-trash-alt"></i> Delete
                                        </a>
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
        // Add Modal Functions
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            // Reset form
            document.getElementById('addUserForm').reset();
        }
        
        // Edit Modal Functions
        function openEditModal(id) {
            var modal = document.getElementById('editModal');
            var loading = document.getElementById('loadingSpinner');
            var formContainer = document.getElementById('editFormContainer');
            var alertSuccess = document.getElementById('editAlertSuccess');
            var alertError = document.getElementById('editAlertError');
            
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Reset and show loading
            loading.style.display = 'block';
            formContainer.classList.add('hide');
            alertSuccess.style.display = 'none';
            alertError.style.display = 'none';
            
            // Fetch user data
            fetch('?ajax_get_user=' + id)
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    if (data.success) {
                        populateEditForm(data.user);
                        formContainer.classList.remove('hide');
                    } else {
                        alertError.querySelector('#editErrorMsg').textContent = data.message || 'Failed to load user data';
                        alertError.style.display = 'block';
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    alertError.querySelector('#editErrorMsg').textContent = 'Error loading data';
                    alertError.style.display = 'block';
                    console.error('Error:', error);
                });
        }
        
        function populateEditForm(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_full_name').value = user.full_name || '';
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_password').value = '';
        }
        
        function submitEditForm() {
            var form = document.getElementById('editForm');
            var formData = new FormData(form);
            var alertSuccess = document.getElementById('editAlertSuccess');
            var alertError = document.getElementById('editAlertError');
            var submitBtn = document.querySelector('#editFormContainer .btn-submit');
            
            // Validate email
            var email = document.getElementById('edit_email').value;
            if (!email) {
                alertError.querySelector('#editErrorMsg').textContent = 'Email is required';
                alertError.style.display = 'block';
                return;
            }
            
            // Validate full name
            var fullName = document.getElementById('edit_full_name').value;
            if (!fullName) {
                alertError.querySelector('#editErrorMsg').textContent = 'Full Name is required';
                alertError.style.display = 'block';
                return;
            }
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Updating...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update User';
                
                if (data.success) {
                    alertSuccess.querySelector('#editSuccessMsg').textContent = data.message;
                    alertSuccess.style.display = 'block';
                    alertError.style.display = 'none';
                    
                    // Reload page after 1.5 seconds to show updated data
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    alertError.querySelector('#editErrorMsg').textContent = data.message || 'Update failed';
                    alertError.style.display = 'block';
                    alertSuccess.style.display = 'none';
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update User';
                alertError.querySelector('#editErrorMsg').textContent = 'Error submitting form';
                alertError.style.display = 'block';
                console.error('Error:', error);
            });
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            var addModal = document.getElementById('addModal');
            var editModal = document.getElementById('editModal');
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