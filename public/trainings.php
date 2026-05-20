<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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
    header('Location: my_trainings.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Verify this training belongs to unit head's team
    $checkStmt = $pdo->prepare("
        SELECT t.* FROM trainings t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ? AND u.department = ? AND u.section = ? AND u.unit = ?
    ");
    $checkStmt->execute([$id, $current_user['department'], $current_user['section'], $current_user['unit']]);
    if ($checkStmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ?");
        $stmt->execute([$id]);
        $success_msg = 'Training deleted successfully!';
    } else {
        $error_msg = 'You can only delete trainings from your unit.';
    }
    header('Location: trainings.php?msg=' . ($success_msg ? 'deleted' : 'error'));
    exit;
}

$success_msg = '';
$error_msg = '';

// Handle add training
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    $user_id = $_POST['user_id'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    
    // Split the combined department/section/unit value
    $dept_section_unit = $_POST['dept_section_unit'];
    $parts = explode('/', $dept_section_unit);
    $department = $parts[0] ?? null;
    $section = $parts[1] ?? null;
    $unit = $parts[2] ?? null;
    
    // Get employee name from selected user
    $userStmt = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $userStmt->execute([$user_id]);
    $selected_user = $userStmt->fetch();
    $employee_name = $selected_user['full_name'] ?: $selected_user['username'];
    
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    $ob_ot = $_POST['ob_ot'];
    
    // Set all external fields to null since we're not using them
    $date_filed = null;
    $ptr_deadline = null;
    $ptr_file = null;
    
    $stmt = $pdo->prepare("INSERT INTO trainings (user_id, employee_name, training_type, division, department, section, unit, title_of_activity, date_from, date_to, venue, hospital_order, date_filed, ob_ot, ptr_deadline, ptr_file, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$user_id, $employee_name, $training_type, $division, $department, $section, $unit, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks])) {
        $success_msg = 'Training added successfully!';
    } else {
        $error_msg = 'Failed to add training.';
    }
}

// Handle PTR submission update
if (isset($_POST['update_ptr'])) {
    $id = $_POST['training_id'];
    $submitted = isset($_POST['ptr_submitted']) ? 1 : 0;
    
    // Verify permission
    $checkStmt = $pdo->prepare("
        SELECT t.* FROM trainings t 
        JOIN users u ON t.user_id = u.id 
        WHERE t.id = ? AND u.department = ? AND u.section = ? AND u.unit = ?
    ");
    $checkStmt->execute([$id, $current_user['department'], $current_user['section'], $current_user['unit']]);
    if ($checkStmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE trainings SET ptr_submitted = ? WHERE id = ?");
        $stmt->execute([$submitted, $id]);
        $success_msg = 'PTR status updated!';
    } else {
        $error_msg = 'You can only update PTR for your unit members.';
    }
}

// Get users under this Unit Head (same department, section, unit)
$usersStmt = $pdo->prepare("
    SELECT id, username, full_name, department, section, unit 
    FROM users 
    WHERE department = ? AND section = ? AND unit = ?
    ORDER BY full_name, username
");
$usersStmt->execute([$current_user['department'], $current_user['section'], $current_user['unit']]);
$team_members = $usersStmt->fetchAll();

// Get trainings for unit head's team
$search = $_GET['search'] ?? '';
$query = "
    SELECT t.*, u.username, u.full_name as user_full_name 
    FROM trainings t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE u.department = ? AND u.section = ? AND u.unit = ?
";
$params = [$current_user['department'], $current_user['section'], $current_user['unit']];

if ($search) {
    $query .= " AND (t.employee_name LIKE ? OR t.title_of_activity LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trainings = $stmt->fetchAll();

// Get unique department/section/unit combinations for dropdown
$deptSectionUnitStmt = $pdo->prepare("
    SELECT DISTINCT department, section, unit 
    FROM users 
    WHERE department = ? AND section = ? AND unit = ?
    ORDER BY department, section, unit
");
$deptSectionUnitStmt->execute([$current_user['department'], $current_user['section'], $current_user['unit']]);
$deptSectionUnitOptions = $deptSectionUnitStmt->fetchAll();

// Get divisions for dropdown
$divisionStmt = $pdo->query("SELECT DISTINCT division FROM users WHERE division IS NOT NULL ORDER BY division");
$divisions = $divisionStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unit Head - Team Trainings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/training_list.css">
    <style>
        .unit-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 15px;
            display: inline-block;
        }
        .badge-info {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .badge-warning {
            background: #f39c12;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .small-link {
            font-size: 12px;
            text-decoration: none;
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
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 900px;
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
        
        .button-container {
            text-align: left;
            margin-top: 10px;
        }
        
        /* Form styling */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        .form-group select,
        .form-group textarea {
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #1B3C53;
            box-shadow: 0 0 0 2px rgba(27, 60, 83, 0.1);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-users"></i> Team Trainings Management</h1>
                <div><i class="fas fa-user-tie"></i> Unit Head: <?= htmlspecialchars($current_user['full_name'] ?: $current_user['username']) ?></div>
            </div>
            
            <div class="unit-badge">
                <i class="fas fa-building"></i> <?= htmlspecialchars($current_user['department']) ?> / 
                <?= htmlspecialchars($current_user['section']) ?> / 
                <?= htmlspecialchars($current_user['unit']) ?>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <!-- Add Training Button -->
            <button class="btn-open-modal" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Add Training
            </button>
            
            <!-- Modal -->
            <div id="trainingModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-plus-circle"></i> Add New Training</h2>
                        <span class="close-modal" onclick="closeModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form method="post" enctype="multipart/form-data" id="trainingForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Division *</label>
                                    <select name="division" required>
                                        <option value="">Select Division</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?= htmlspecialchars($div['division']) ?>">
                                                <?= htmlspecialchars($div['division']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Department/Section/Unit *</label>
                                    <select name="dept_section_unit" required>
                                        <option value="">Select Department/Section/Unit</option>
                                        <?php foreach ($deptSectionUnitOptions as $option): ?>
                                            <?php 
                                            $combined = htmlspecialchars($option['department']) . '/' . 
                                                        htmlspecialchars($option['section']) . '/' . 
                                                        htmlspecialchars($option['unit']);
                                            ?>
                                            <option value="<?= $combined ?>"><?= $combined ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Employee *</label>
                                    <select name="user_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($team_members as $member): ?>
                                            <option value="<?= $member['id'] ?>">
                                                <?= htmlspecialchars($member['full_name'] ?: $member['username']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Training Type *</label>
                                    <select name="training_type" id="training_type" required>
                                        <option value="Internal">Internal</option>
                                        <option value="External">External</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-briefcase"></i> OB/OT *</label>
                                    <select name="ob_ot" required>
                                        <option value="">Select OB/OT</option>
                                        <option value="Official Business">Official Business</option>
                                        <option value="Official Time">Official Time</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-heading"></i> Title of Activity *</label>
                                    <input type="text" name="title_of_activity" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-alt"></i> Date From *</label>
                                    <input type="date" name="date_from" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-alt"></i> Date To *</label>
                                    <input type="date" name="date_to" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-map-marker-alt"></i> Venue *</label>
                                    <select name="venue" required>
                                        <option value="Online">Online</option>
                                        <option value="Auditorium">Auditorium</option>
                                        <option value="Executive Lounge">Executive Lounge</option>
                                        <option value="Local">Local</option>
                                        <option value="International">International</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-file-alt"></i> Hospital Order</label>
                                    <input type="text" name="hospital_order">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-comment"></i> Remarks</label>
                                    <textarea name="remarks" rows="2"></textarea>
                                </div>
                            </div>
                            
                            <div class="button-container">
                                <button type="submit" name="add_training" class="btn-submit">
                                    <i class="fas fa-save"></i> Add Training
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="search-bar">
                <form method="get">
                    <input type="text" name="search" placeholder="Search by employee name or training title..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="trainings.php"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Division</th>
                            <th>Dept/Section/Unit</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>OB/OT</th>
                            <th>PTR Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($trainings)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center;">No trainings found for your team.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($trainings as $training): ?>
                            <tr>
                                <td><?= $training['id'] ?></td>
                                <td><?= htmlspecialchars($training['employee_name']) ?></td>
                                <td>
                                    <span class="<?= $training['training_type'] == 'Internal' ? 'badge-info' : 'badge-warning' ?>">
                                        <?= $training['training_type'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($training['title_of_activity']) ?></td>
                                <td><?= htmlspecialchars($training['division']) ?></td>
                                <td><?= htmlspecialchars($training['department'] . '/' . $training['section'] . '/' . $training['unit']) ?></td>
                                <td><?= date('M d, Y', strtotime($training['date_from'])) ?> - <?= date('M d, Y', strtotime($training['date_to'])) ?></td>
                                <td><?= $training['venue'] ?></td>
                                <td><?= $training['ob_ot'] ?: '-' ?></td>
                                <td>
                                    <?php if ($training['training_type'] === 'External'): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="training_id" value="<?= $training['id'] ?>">
                                            <input type="checkbox" name="ptr_submitted" <?= $training['ptr_submitted'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <input type="hidden" name="update_ptr" value="1">
                                            <label><i class="fas fa-check"></i> Submitted</label>
                                        </form>
                                        <?php if ($training['ptr_file']): ?>
                                            <br><a href="<?= $training['ptr_file'] ?>" target="_blank" class="small-link"><i class="fas fa-file-pdf"></i> View File</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit_training.php?id=<?= $training['id'] ?>&unit_head=1" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?= $training['id'] ?>" class="btn-delete" onclick="return confirm('Delete this training? This action cannot be undone.')"><i class="fas fa-trash-alt"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('trainingModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('trainingModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('trainingModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Set default division and department/section/unit from unit head's data
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select division dropdown if value matches current user's division
            var divisionSelect = document.querySelector('select[name="division"]');
            if (divisionSelect) {
                var currentDivision = '<?= htmlspecialchars($current_user['division']) ?>';
                for(var i = 0; i < divisionSelect.options.length; i++) {
                    if(divisionSelect.options[i].value === currentDivision) {
                        divisionSelect.options[i].selected = true;
                        break;
                    }
                }
            }
            
            // Auto-select department/section/unit dropdown
            var deptSelect = document.querySelector('select[name="dept_section_unit"]');
            if (deptSelect) {
                var currentDeptSectionUnit = '<?= htmlspecialchars($current_user['department']) ?>/<?= htmlspecialchars($current_user['section']) ?>/<?= htmlspecialchars($current_user['unit']) ?>';
                for(var i = 0; i < deptSelect.options.length; i++) {
                    if(deptSelect.options[i].value === currentDeptSectionUnit) {
                        deptSelect.options[i].selected = true;
                        break;
                    }
                }
            }
        });
    </script>
</body>
</html>