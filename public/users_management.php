<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

// Check if user is Unit Head
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Get Unit Head's division and department info (section and unit removed)
$userStmt = $pdo->prepare("SELECT division, department, full_name, username FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$current_user = $userStmt->fetch();

// If not unit head, redirect
if ($user_role !== 'unit_head') {
    header('Location: ../public/dashboard.php');
    exit;
}

// Handle delete - only allow deleting staff under same unit (now using division and department only)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Verify this user belongs to unit head's team and is not a unit head or admin
    $checkStmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE id = ? 
        AND division = ?
        AND department = ? 
        AND role = 'employee'
    ");
    $checkStmt->execute([$id, $current_user['division'], $current_user['department']]);
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
    
    // Force to unit head's division and department
    $division = $current_user['division'];
    $department = $current_user['department'];
    
    // Force role to employee for unit head
    $role = 'employee';
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        $error_msg = 'Email already exists.';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, full_name, password_hash, role, division, department, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $full_name, $password_hash, $role, $division, $department, $_SESSION['user_id']])) {
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
        AND role = 'employee'
    ");
    $stmt->execute([$id, $current_user['division'], $current_user['department']]);
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
        AND role = 'employee'
    ");
    $checkStmt->execute([$id, $current_user['division'], $current_user['department']]);
    
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

// Get users under this Unit Head (same division, department, and role = employee)
$usersStmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE division = ?
    AND department = ? 
    AND role = 'employee'
    ORDER BY created_at DESC
");
$usersStmt->execute([$current_user['division'], $current_user['department']]);
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
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/user_management.css">
    <style>
        .info-box {
            background: var(--secondary);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary);
            color: var(--navy);
        }
        .info-box i {
            color: var(--primary);
            margin-right: 10px;
        }
        .readonly-display {
            background-color: var(--bg-light);
            padding: 12px 16px;
            border: 1.5px solid var(--secondary);
            border-radius: 10px;
            color: var(--navy);
            font-size: 14px;
        }
        .btn-open-modal {
            background: linear-gradient(135deg, var(--primary), var(--navy));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
            transition: all 0.3s;
            box-shadow: var(--shadow-sm);
        }
        .btn-open-modal:hover {
            background: linear-gradient(135deg, var(--navy), var(--primary));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        .btn-open-modal i {
            margin-right: 8px;
        }
        .btn-edit-modal {
            background: var(--light-blue);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .btn-edit-modal:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        .btn-delete {
            background: var(--danger);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }
        .btn-delete:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        .modal-content {
            background-color: var(--off-white);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--navy));
        }
        .modal-body .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--navy));
        }
        .modal-body .btn-submit:hover {
            background: linear-gradient(135deg, var(--navy), var(--primary));
        }
        .btn-cancel-modal {
            background: var(--light-blue);
        }
        .btn-cancel-modal:hover {
            background: var(--primary-light);
        }
        .form-group label i {
            color: var(--primary);
        }
        .form-group input,
        .form-group select {
            border: 1.5px solid var(--secondary);
            border-radius: 10px;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 2px rgba(143, 186, 243, 0.2);
        }
        .role-badge {
            background: linear-gradient(135deg, var(--primary), var(--navy));
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
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
                You are managing users under: <strong><?= htmlspecialchars($current_user['division']) ?> / <?= htmlspecialchars($current_user['department']) ?></strong>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
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
                                    <label><i class="fas fa-sitemap"></i> Department *</label>
                                    <div class="readonly-display"><?= htmlspecialchars($current_user['department']) ?></div>
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
                <h3><i class="fas fa-list"></i> Users Under <?= htmlspecialchars($current_user['department']) ?> (<?= count($users) ?>)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Division</th>
                            <th>Department</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                         </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No users found in your unit. Add your first user above.</div>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><span class="role-badge"><?= ucfirst($user['role']) ?></span></td>
                                <td><?= htmlspecialchars($user['division'] ?? '') ?></td>
                                <td><?= htmlspecialchars($user['department'] ?? '') ?></td>
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
                                 </div>
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
            
            loading.style.display = 'block';
            formContainer.classList.add('hide');
            alertSuccess.style.display = 'none';
            alertError.style.display = 'none';
            
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
            
            var email = document.getElementById('edit_email').value;
            if (!email) {
                alertError.querySelector('#editErrorMsg').textContent = 'Email is required';
                alertError.style.display = 'block';
                return;
            }
            
            var fullName = document.getElementById('edit_full_name').value;
            if (!fullName) {
                alertError.querySelector('#editErrorMsg').textContent = 'Full Name is required';
                alertError.style.display = 'block';
                return;
            }
            
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