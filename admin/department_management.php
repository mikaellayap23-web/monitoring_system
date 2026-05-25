<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_division'])) {
    $division_name = trim($_POST['division_name']);
    
    if (empty($division_name)) {
        $error_msg = 'Division name is required.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id FROM divisions WHERE name = ?");
        $checkStmt->execute([$division_name]);
        
        if ($checkStmt->fetch()) {
            $error_msg = 'Division already exists.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO divisions (name) VALUES (?)");
            if ($stmt->execute([$division_name])) {
                $success_msg = 'Division added successfully!';
                header('Location: department_management.php?msg=success');
                exit;
            } else {
                $error_msg = 'Failed to add division.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_division'])) {
    $division_id = $_POST['division_id'];
    $division_name = trim($_POST['division_name']);
    
    if (empty($division_name)) {
        $error_msg = 'Division name is required.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id FROM divisions WHERE name = ? AND id != ?");
        $checkStmt->execute([$division_name, $division_id]);
        
        if ($checkStmt->fetch()) {
            $error_msg = 'Division name already exists.';
        } else {
            $stmt = $pdo->prepare("UPDATE divisions SET name = ? WHERE id = ?");
            if ($stmt->execute([$division_name, $division_id])) {
                $success_msg = 'Division updated successfully!';
                header('Location: department_management.php?msg=success');
                exit;
            } else {
                $error_msg = 'Failed to update division.';
            }
        }
    }
}

if (isset($_GET['delete_division'])) {
    $division_id = $_GET['delete_division'];
    
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM departments WHERE division_id = ?");
    $checkStmt->execute([$division_id]);
    $dept_count = $checkStmt->fetch()['count'];
    
    if ($dept_count > 0) {
        $error_msg = 'Cannot delete division. Please delete all departments under this division first.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM divisions WHERE id = ?");
        if ($stmt->execute([$division_id])) {
            $success_msg = 'Division deleted successfully!';
        } else {
            $error_msg = 'Failed to delete division.';
        }
    }
    header('Location: department_management.php?msg=' . ($success_msg ? 'success' : 'error'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department'])) {
    $department_name = trim($_POST['department_name']);
    $division_id = $_POST['division_id'];
    
    if (empty($department_name)) {
        $error_msg = 'Department name is required.';
    } elseif (empty($division_id)) {
        $error_msg = 'Please select a division.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND division_id = ?");
        $checkStmt->execute([$department_name, $division_id]);
        
        if ($checkStmt->fetch()) {
            $error_msg = 'Department already exists in this division.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO departments (name, division_id) VALUES (?, ?)");
            if ($stmt->execute([$department_name, $division_id])) {
                $success_msg = 'Department added successfully!';
                header('Location: department_management.php?msg=success');
                exit;
            } else {
                $error_msg = 'Failed to add department.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_department'])) {
    $department_id = $_POST['department_id'];
    $department_name = trim($_POST['department_name']);
    $division_id = $_POST['division_id'];
    
    if (empty($department_name)) {
        $error_msg = 'Department name is required.';
    } elseif (empty($division_id)) {
        $error_msg = 'Please select a division.';
    } else {
        $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND division_id = ? AND id != ?");
        $checkStmt->execute([$department_name, $division_id, $department_id]);
        
        if ($checkStmt->fetch()) {
            $error_msg = 'Department name already exists in this division.';
        } else {
            $stmt = $pdo->prepare("UPDATE departments SET name = ?, division_id = ? WHERE id = ?");
            if ($stmt->execute([$department_name, $division_id, $department_id])) {
                $success_msg = 'Department updated successfully!';
                header('Location: department_management.php?msg=success');
                exit;
            } else {
                $error_msg = 'Failed to update department.';
            }
        }
    }
}

if (isset($_GET['delete_department'])) {
    $department_id = $_GET['delete_department'];
    
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE department = (SELECT name FROM departments WHERE id = ?)");
    $checkStmt->execute([$department_id]);
    $user_count = $checkStmt->fetch()['count'];
    
    if ($user_count > 0) {
        $error_msg = 'Cannot delete department. Please reassign or delete users under this department first.';
    } else {
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        if ($stmt->execute([$department_id])) {
            $success_msg = 'Department deleted successfully!';
        } else {
            $error_msg = 'Failed to delete department.';
        }
    }
    header('Location: department_management.php?msg=' . ($success_msg ? 'success' : 'error'));
    exit;
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') {
        $success_msg = 'Operation completed successfully!';
    } elseif ($_GET['msg'] === 'error') {
        $error_msg = $error_msg ?: 'Operation failed.';
    }
}

$divisions = $pdo->query("SELECT * FROM divisions ORDER BY name")->fetchAll();

$departments = $pdo->query("
    SELECT d.*, dv.name as division_name 
    FROM departments d 
    LEFT JOIN divisions dv ON d.division_id = dv.id 
    ORDER BY dv.name, d.name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Department Management - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
	<link rel="stylesheet" href="../assets/css/department_management.css">
</head>
<body>
    <div class="app-container">
        <?php include '../public/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-building"></i> Department Management</h1>
                <div><i class="fas fa-user-shield"></i> Admin: <?= htmlspecialchars($_SESSION['username'] ?? '') ?></div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <div class="action-buttons-container">
                <button class="btn-add-division" onclick="openAddDivisionModal()">
                    <i class="fas fa-plus"></i> Add Division
                </button>
                <button class="btn-add-department" onclick="openAddDepartmentModal()">
                    <i class="fas fa-plus"></i> Add Department
                </button>
            </div>
            
            <div class="management-container">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-layer-group"></i> Divisions List
                    </div>
                    <div class="card-body">
                        <div class="filter-bar">
                            <input type="text" id="divisionSearch" class="filter-input" placeholder="Search divisions...">
                            <button class="btn-scroll" onclick="scrollToTop('divisionsTable')" title="Scroll to top">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button class="btn-scroll" onclick="scrollToBottom('divisionsTable')" title="Scroll to bottom">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                        </div>
                        <div class="table-responsive" id="divisionsTable" style="max-height: 400px; overflow-y: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Division Name</th>
                                        <th>Actions</th>
                                    </thead>
                                <tbody id="divisionsTableBody">
                                    <?php if (empty($divisions)): ?>
                                        <tr><td colspan="2" class="text-center">No divisions found.</div></tr>
                                    <?php endif; ?>
                                    <?php foreach ($divisions as $div): ?>
                                        <tr data-division-name="<?= strtolower(htmlspecialchars($div['name'])) ?>">
                                            <td><strong><?= htmlspecialchars($div['name']) ?></strong></div>
                                            <td class="action-buttons">
                                                <button class="btn-edit" onclick="openEditDivisionModal(<?= $div['id'] ?>, '<?= htmlspecialchars($div['name']) ?>')">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="?delete_division=<?= $div['id'] ?>" class="btn-delete" onclick="return confirm('Delete this division? This will also delete all departments under it.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                         </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-info" id="divisionPaginationInfo"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sitemap"></i> Departments List
                    </div>
                    <div class="card-body">
                        <div class="filter-bar">
                            <select id="departmentDivisionFilter" class="filter-select">
                                <option value="">All Divisions</option>
                                <?php foreach ($divisions as $div): ?>
                                    <option value="<?= strtolower(htmlspecialchars($div['name'])) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="departmentSearch" class="filter-input" placeholder="Search departments...">
                            <button class="btn-scroll" onclick="scrollToTop('departmentsTable')" title="Scroll to top">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button class="btn-scroll" onclick="scrollToBottom('departmentsTable')" title="Scroll to bottom">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                        </div>
                        <div class="table-responsive" id="departmentsTable" style="max-height: 400px; overflow-y: auto;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Division</th>
                                        <th>Department Name</th>
                                        <th>Actions</th>
                                    </thead>
                                <tbody id="departmentsTableBody">
                                    <?php if (empty($departments)): ?>
                                        <tr><td colspan="3" class="text-center">No departments found.</div></tr>
                                    <?php endif; ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr data-division="<?= strtolower(htmlspecialchars($dept['division_name'] ?? '')) ?>" data-department-name="<?= strtolower(htmlspecialchars($dept['name'])) ?>">
                                            <td><span class="division-badge"><?= htmlspecialchars($dept['division_name'] ?? 'N/A') ?></span></div>
                                            <td><?= htmlspecialchars($dept['name']) ?></div>
                                            <td class="action-buttons">
                                                <button class="btn-edit" onclick="openEditDepartmentModal(<?= $dept['id'] ?>, '<?= htmlspecialchars($dept['name']) ?>', <?= $dept['division_id'] ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <a href="?delete_department=<?= $dept['id'] ?>" class="btn-delete" onclick="return confirm('Delete this department? This will affect users assigned to this department.')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                         </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-info" id="departmentPaginationInfo"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="addDivisionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Division</h2>
                <span class="close-modal" onclick="closeAddDivisionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="addDivisionForm">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Division Name</label>
                        <input type="text" name="division_name" placeholder="Enter division name" required>
                    </div>
                    <div class="button-container">
                        <button type="submit" name="add_division" class="btn-submit">
                            <i class="fas fa-save"></i> Add Division
                        </button>
                        <button type="button" class="btn-cancel" onclick="closeAddDivisionModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="editDivisionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Division</h2>
                <span class="close-modal" onclick="closeEditDivisionModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="editDivisionForm">
                    <input type="hidden" name="division_id" id="edit_division_id">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Division Name</label>
                        <input type="text" name="division_name" id="edit_division_name" required>
                    </div>
                    <div class="button-container">
                        <button type="submit" name="edit_division" class="btn-submit">
                            <i class="fas fa-save"></i> Update Division
                        </button>
                        <button type="button" class="btn-cancel" onclick="closeEditDivisionModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="addDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-plus-circle"></i> Add New Department</h2>
                <span class="close-modal" onclick="closeAddDepartmentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="addDepartmentForm">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Select Division</label>
                        <select name="division_id" required>
                            <option value="">-- Select Division --</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Department Name</label>
                        <input type="text" name="department_name" placeholder="Enter department name" required>
                    </div>
                    <div class="button-container">
                        <button type="submit" name="add_department" class="btn-submit">
                            <i class="fas fa-save"></i> Add Department
                        </button>
                        <button type="button" class="btn-cancel" onclick="closeAddDepartmentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="editDepartmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Edit Department</h2>
                <span class="close-modal" onclick="closeEditDepartmentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="post" id="editDepartmentForm">
                    <input type="hidden" name="department_id" id="edit_department_id">
                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Select Division</label>
                        <select name="division_id" id="edit_department_division_id" required>
                            <option value="">-- Select Division --</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Department Name</label>
                        <input type="text" name="department_name" id="edit_department_name" required>
                    </div>
                    <div class="button-container">
                        <button type="submit" name="edit_department" class="btn-submit">
                            <i class="fas fa-save"></i> Update Department
                        </button>
                        <button type="button" class="btn-cancel" onclick="closeEditDepartmentModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openAddDivisionModal() {
            document.getElementById('addDivisionModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddDivisionModal() {
            document.getElementById('addDivisionModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addDivisionForm').reset();
        }
        
        function openEditDivisionModal(id, name) {
            document.getElementById('edit_division_id').value = id;
            document.getElementById('edit_division_name').value = name;
            document.getElementById('editDivisionModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditDivisionModal() {
            document.getElementById('editDivisionModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function openAddDepartmentModal() {
            document.getElementById('addDepartmentModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddDepartmentModal() {
            document.getElementById('addDepartmentModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addDepartmentForm').reset();
        }
        
        function openEditDepartmentModal(id, name, divisionId) {
            document.getElementById('edit_department_id').value = id;
            document.getElementById('edit_department_name').value = name;
            document.getElementById('edit_department_division_id').value = divisionId;
            document.getElementById('editDepartmentModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditDepartmentModal() {
            document.getElementById('editDepartmentModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function filterDivisions() {
            var searchValue = document.getElementById('divisionSearch').value.toLowerCase();
            var rows = document.querySelectorAll('#divisionsTableBody tr');
            var visibleCount = 0;
            
            rows.forEach(function(row) {
                if (row.getAttribute('data-division-name')) {
                    var divisionName = row.getAttribute('data-division-name');
                    if (divisionName.includes(searchValue)) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
            
            var paginationInfo = document.getElementById('divisionPaginationInfo');
            if (visibleCount === 0) {
                paginationInfo.innerHTML = '<span class="no-results">No matching divisions found</span>';
            } else {
                paginationInfo.innerHTML = '<span class="result-count">Showing ' + visibleCount + ' divisions</span>';
            }
        }
        
        function filterDepartments() {
            var divisionFilter = document.getElementById('departmentDivisionFilter').value.toLowerCase();
            var searchValue = document.getElementById('departmentSearch').value.toLowerCase();
            var rows = document.querySelectorAll('#departmentsTableBody tr');
            var visibleCount = 0;
            
            rows.forEach(function(row) {
                var division = row.getAttribute('data-division') || '';
                var departmentName = row.getAttribute('data-department-name') || '';
                
                var divisionMatch = divisionFilter === '' || division === divisionFilter;
                var searchMatch = searchValue === '' || departmentName.includes(searchValue);
                
                if (divisionMatch && searchMatch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            var paginationInfo = document.getElementById('departmentPaginationInfo');
            if (visibleCount === 0) {
                paginationInfo.innerHTML = '<span class="no-results">No matching departments found</span>';
            } else {
                paginationInfo.innerHTML = '<span class="result-count">Showing ' + visibleCount + ' departments</span>';
            }
        }
        
        function scrollToTop(tableId) {
            var container = document.getElementById(tableId);
            if (container) {
                container.scrollTop = 0;
            }
        }
        
        function scrollToBottom(tableId) {
            var container = document.getElementById(tableId);
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
        
        document.getElementById('divisionSearch').addEventListener('keyup', filterDivisions);
        document.getElementById('departmentDivisionFilter').addEventListener('change', filterDepartments);
        document.getElementById('departmentSearch').addEventListener('keyup', filterDepartments);
        
        window.onclick = function(event) {
            var modals = ['addDivisionModal', 'editDivisionModal', 'addDepartmentModal', 'editDepartmentModal'];
            modals.forEach(function(modalId) {
                var modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
        
        filterDivisions();
        filterDepartments();
    </script>
</body>
</html>