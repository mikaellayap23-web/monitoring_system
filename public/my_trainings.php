<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Get current user's info - REMOVED section and unit
$userStmt = $pdo->prepare("SELECT full_name, username, division, department FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$current_user = $userStmt->fetch() ?: [];

if (empty($current_user)) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    header('Location: my_trainings.php?msg=deleted');
    exit;
}

$success_msg = '';
$error_msg = '';

// Get divisions for dropdown
$divisionStmt = $pdo->query("SELECT DISTINCT name FROM divisions ORDER BY name");
$divisions = $divisionStmt->fetchAll();

// Handle add training
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    $user_id = $_POST['user_id'];
    $employee_name = $_POST['employee_name'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    $department = trim($_POST['department'] ?? '');
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    
    // Set default values
    $date_filed = null;
    $ob_ot = null;
    $ptr_deadline = null;
    $ptr_file = null;
    
    if ($training_type === 'External') {
        $date_filed = $_POST['date_filed'];
        $ob_ot = $_POST['ob_ot'];
        $ptr_deadline = $_POST['ptr_deadline'];
        
        if (isset($_FILES['ptr_file']) && $_FILES['ptr_file']['error'] === 0) {
            $upload_dir = '../public/uploads/ptr_reports/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['ptr_file']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['ptr_file']['tmp_name'], $target_file)) {
                $ptr_file = 'uploads/ptr_reports/' . $file_name;
            }
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO trainings (user_id, employee_name, training_type, division, department, title_of_activity, date_from, date_to, venue, hospital_order, date_filed, ob_ot, ptr_deadline, ptr_file, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$user_id, $employee_name, $training_type, $division, $department, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks])) {
        $success_msg = 'Training added successfully!';
        header('Location: my_trainings.php?msg=added');
        exit;
    } else {
        $error_msg = 'Failed to add training.';
    }
}

// Handle AJAX Edit Request
if (isset($_GET['ajax_get_training'])) {
    $id = $_GET['ajax_get_training'];
    $stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $training = $stmt->fetch();
    
    if ($training) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'training' => $training]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Training not found']);
    }
    exit;
}

// Handle AJAX Edit Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_training'])) {
    $id = $_POST['training_id'];
    $user_id = $_POST['user_id'];
    $employee_name = $_POST['employee_name'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    $department = trim($_POST['department'] ?? '');
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    
    $date_filed = null;
    $ob_ot = null;
    $ptr_deadline = null;
    $ptr_file = null;
    
    // Get existing PTR file
    $stmt = $pdo->prepare("SELECT ptr_file FROM trainings WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    $ptr_file = $existing['ptr_file'];
    
    if ($training_type === 'External') {
        $date_filed = $_POST['date_filed'];
        $ob_ot = $_POST['ob_ot'];
        $ptr_deadline = $_POST['ptr_deadline'];
        
        if (isset($_FILES['ptr_file']) && $_FILES['ptr_file']['error'] === 0) {
            $upload_dir = '../public/uploads/ptr_reports/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['ptr_file']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['ptr_file']['tmp_name'], $target_file)) {
                $ptr_file = 'uploads/ptr_reports/' . $file_name;
            }
        }
    }
    
    $stmt = $pdo->prepare("UPDATE trainings SET user_id=?, employee_name=?, training_type=?, division=?, department=?, title_of_activity=?, date_from=?, date_to=?, venue=?, hospital_order=?, date_filed=?, ob_ot=?, ptr_deadline=?, ptr_file=?, remarks=? WHERE id=? AND user_id=?");
    
    if ($stmt->execute([$user_id, $employee_name, $training_type, $division, $department, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks, $id, $user_id])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Training updated successfully!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update training.']);
    }
    exit;
}

if (isset($_POST['update_ptr'])) {
    $id = $_POST['training_id'];
    $submitted = isset($_POST['ptr_submitted']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE trainings SET ptr_submitted = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$submitted, $id, $user_id]);
    $success_msg = 'PTR status updated!';
}

$search = $_GET['search'] ?? '';
$query = "SELECT t.*, u.username FROM trainings t LEFT JOIN users u ON t.user_id = u.id WHERE t.user_id = ?";
$params = [$user_id];
if ($search) {
    $query .= " AND (t.title_of_activity LIKE ?)";
    $params[] = "%$search%";
}
$query .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$trainings = $stmt->fetchAll();

// Get department options
$deptStmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$departments = $deptStmt->fetchAll();

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added') {
        $success_msg = 'Training added successfully!';
    } elseif ($_GET['msg'] === 'deleted') {
        $success_msg = 'Training deleted successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Trainings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/training_list.css">
    <style>
        .btn-open-modal {
            background: linear-gradient(135deg, #0245A3, #355872);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        .btn-open-modal:hover {
            background: linear-gradient(135deg, #355872, #0245A3);
            transform: translateY(-2px);
        }
        .btn-open-modal i {
            margin-right: 8px;
        }
        .btn-edit-modal {
            background: #7AAACE;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }
        .btn-edit-modal:hover {
            background: #8FBAF3;
            transform: translateY(-1px);
        }
        .btn-delete {
            background: #ef4444;
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
        .btn-cancel-modal {
            background: #7AAACE;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-cancel-modal:hover {
            background: #8FBAF3;
            transform: translateY(-1px);
        }
        .btn-submit {
            background: linear-gradient(135deg, #0245A3, #355872);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #355872, #0245A3);
            transform: translateY(-1px);
        }
        .modal-content {
            background-color: #F7F8F0;
        }
        .modal-header {
            background: linear-gradient(135deg, #0245A3, #355872);
        }
        .external-fields {
            background: #F2FCFC;
            border-radius: 10px;
        }
        .search-bar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-bar input {
            padding: 10px 14px;
            width: 300px;
            border: 1.5px solid #BDF1F6;
            border-radius: 10px;
            font-size: 13px;
        }
        .search-bar input:focus {
            outline: none;
            border-color: #8FBAF3;
            box-shadow: 0 0 0 2px rgba(143, 186, 243, 0.2);
        }
        .search-bar a {
            color: #7AAACE;
            text-decoration: none;
        }
        .search-bar a:hover {
            color: #8FBAF3;
            text-decoration: underline;
        }
        .data-table {
            background: #F7F8F0;
        }
        .data-table th {
            background: linear-gradient(135deg, #0245A3, #355872);
        }
        .form-group input, .form-group select, .form-group textarea {
            border: 1.5px solid #BDF1F6;
            border-radius: 8px;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #8FBAF3;
            box-shadow: 0 0 0 2px rgba(143, 186, 243, 0.2);
        }
        .button-container {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-calendar-alt"></i> My Trainings</h1>
                <div><i class="fas fa-user"></i> Employee: <?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <!-- Add Training Button -->
            <button class="btn-open-modal" onclick="openAddModal()">
                <i class="fas fa-plus-circle"></i> Add Training
            </button>
            
            <!-- Add Training Modal -->
            <div id="addModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-plus-circle"></i> Add New Training</h2>
                        <span class="close-modal" onclick="closeAddModal()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form method="post" enctype="multipart/form-data" id="addTrainingForm">
                            <input type="hidden" name="user_id" value="<?= $user_id ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-user-tag"></i> Employee Name *</label>
                                    <input type="text" name="employee_name" value="<?= htmlspecialchars($current_user['full_name'] ?: $current_user['username']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-tag"></i> Training Type *</label>
                                    <select name="training_type" id="add_training_type" required onchange="toggleAddExternalFields()">
                                        <option value="Internal">Internal</option>
                                        <option value="External">External</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Division *</label>
                                    <select name="division" required>
                                        <option value="">Select Division</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?= htmlspecialchars($div['name']) ?>">
                                                <?= htmlspecialchars($div['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Department *</label>
                                    <?php if (!empty($departments)): ?>
                                        <select name="department" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept['department']) ?>"><?= htmlspecialchars($dept['department']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="department" required placeholder="Department">
                                    <?php endif; ?>
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
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-file-alt"></i> Hospital Order</label>
                                    <input type="text" name="hospital_order">
                                </div>
                            </div>
                            
                            <div id="add_external_fields" class="external-fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-briefcase"></i> OB/OT *</label>
                                        <select name="ob_ot">
                                            <option value="">Select OB/OT</option>
                                            <option value="Official Business">Official Business</option>
                                            <option value="Official Time">Official Time</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-day"></i> Date Filed *</label>
                                        <input type="date" name="date_filed">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-hourglass-end"></i> PTR Deadline *</label>
                                        <input type="date" name="ptr_deadline">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-file-upload"></i> PTR File *</label>
                                        <input type="file" name="ptr_file">
                                    </div>
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
                                <button type="button" class="btn-cancel-modal" onclick="closeAddModal()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Training Modal -->
            <div id="editModal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-edit"></i> Edit Training</h2>
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
                            <i class="fas fa-spinner fa-pulse"></i> Loading training data...
                        </div>
                        <div id="editFormContainer" class="edit-form-container hide">
                            <form id="editForm" enctype="multipart/form-data">
                                <input type="hidden" name="ajax_edit_training" value="1">
                                <input type="hidden" name="training_id" id="edit_training_id">
                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Employee Name *</label>
                                        <input type="text" name="employee_name" id="edit_employee_name" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-tag"></i> Training Type *</label>
                                        <select name="training_type" id="edit_training_type" required onchange="toggleEditExternalFields()">
                                            <option value="Internal">Internal</option>
                                            <option value="External">External</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-building"></i> Division *</label>
                                        <select name="division" id="edit_division" required>
                                            <option value="">Select Division</option>
                                            <?php foreach ($divisions as $div): ?>
                                                <option value="<?= htmlspecialchars($div['name']) ?>">
                                                    <?= htmlspecialchars($div['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-building"></i> Department *</label>
                                        <select name="department" id="edit_department" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept['department']) ?>"><?= htmlspecialchars($dept['department']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-heading"></i> Title of Activity *</label>
                                        <input type="text" name="title_of_activity" id="edit_title_of_activity" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-alt"></i> Date From *</label>
                                        <input type="date" name="date_from" id="edit_date_from" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-alt"></i> Date To *</label>
                                        <input type="date" name="date_to" id="edit_date_to" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-map-marker-alt"></i> Venue *</label>
                                        <select name="venue" id="edit_venue" required>
                                            <option value="Online">Online</option>
                                            <option value="Auditorium">Auditorium</option>
                                            <option value="Executive Lounge">Executive Lounge</option>
                                            <option value="Local">Local</option>
                                            <option value="International">International</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-file-alt"></i> Hospital Order</label>
                                        <input type="text" name="hospital_order" id="edit_hospital_order">
                                    </div>
                                </div>
                                
                                <div id="edit_external_fields" class="external-fields">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label><i class="fas fa-briefcase"></i> OB/OT *</label>
                                            <select name="ob_ot" id="edit_ob_ot">
                                                <option value="">Select OB/OT</option>
                                                <option value="Official Business">Official Business</option>
                                                <option value="Official Time">Official Time</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-calendar-day"></i> Date Filed</label>
                                            <input type="date" name="date_filed" id="edit_date_filed">
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-hourglass-end"></i> PTR Deadline</label>
                                            <input type="date" name="ptr_deadline" id="edit_ptr_deadline">
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-file-upload"></i> PTR File</label>
                                            <input type="file" name="ptr_file" id="edit_ptr_file">
                                            <div id="currentPtrFile" class="current-ptr-file"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-comment"></i> Remarks</label>
                                        <textarea name="remarks" id="edit_remarks" rows="2"></textarea>
                                    </div>
                                </div>
                                
                                <div class="button-container">
                                    <button type="button" class="btn-submit" onclick="submitEditForm()">
                                        <i class="fas fa-save"></i> Update Training
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
            
            <div class="search-bar">
                <form method="get">
                    <input type="text" name="search" placeholder="Search by training title..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="my_trainings.php"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-calendar-alt"></i> My Training Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Training Type</th>
                            <th>Title of Activity</th>
                            <th>Division</th>
                            <th>Department</th>
                            <th>Date From</th>
                            <th>Date To</th>
                            <th>Venue</th>
                            <th>OB/OT</th>
                            <th>PTR Status</th>
                            <th>Actions</th>
                         </thead>
                    <tbody>
                        <?php if (empty($trainings)): ?>
                            <tr>
                                <td colspan="11" style="text-align: center;">No trainings found. Add your first training above.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($trainings as $training): ?>
                            <tr>
                                <td><?= $training['id'] ?></td>
                                <td><?= $training['training_type'] ?></td>
                                <td><?= htmlspecialchars($training['title_of_activity']) ?></td>
                                <td><?= htmlspecialchars($training['division']) ?></td>
                                <td><?= htmlspecialchars($training['department'] ?? '-') ?></td>
                                <td><?= date('M d, Y', strtotime($training['date_from'])) ?></td>
                                <td><?= date('M d, Y', strtotime($training['date_to'])) ?></td>
                                <td><?= $training['venue'] ?></td>
                                <td><?= $training['ob_ot'] ?: '-' ?></td>
                                <td>
                                    <?php if ($training['training_type'] === 'External'): ?>
                                        <div class="ptr-status-container">
                                            <form method="post" class="ptr-form">
                                                <input type="hidden" name="training_id" value="<?= $training['id'] ?>">
                                                <label class="ptr-checkbox">
                                                    <input type="checkbox" name="ptr_submitted" <?= $training['ptr_submitted'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                                    <span>Submitted</span>
                                                </label>
                                                <input type="hidden" name="update_ptr" value="1">
                                            </form>
                                            <?php if ($training['ptr_file']): ?>
                                                <a href="../public/<?= $training['ptr_file'] ?>" class="ptr-file-link" target="_blank"><i class="fas fa-file-pdf"></i> View File</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="ptr-badge ptr-na">N/A</span>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <button onclick="openEditModal(<?= $training['id'] ?>)" class="btn-edit-modal">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="?delete=<?= $training['id'] ?>" class="btn-delete" onclick="return confirm('Delete this training?')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                 </div>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            var modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
                toggleAddExternalFields();
            } else {
                console.error('Add modal not found');
            }
        }
        
        function closeAddModal() {
            var modal = document.getElementById('addModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                document.getElementById('addTrainingForm').reset();
            }
        }
        
        function toggleAddExternalFields() {
            var typeSelect = document.getElementById('add_training_type');
            if (!typeSelect) return;
            var type = typeSelect.value;
            var externalFields = document.getElementById('add_external_fields');
            if (type === 'External') {
                externalFields.style.display = 'block';
                document.querySelectorAll('#add_external_fields input, #add_external_fields select').forEach(function(el) {
                    if (el.name !== 'ptr_file') el.setAttribute('required', 'required');
                });
            } else {
                externalFields.style.display = 'none';
                document.querySelectorAll('#add_external_fields input, #add_external_fields select').forEach(function(el) {
                    el.removeAttribute('required');
                });
            }
        }
        
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
            
            fetch('?ajax_get_training=' + id)
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    if (data.success) {
                        populateEditForm(data.training);
                        formContainer.classList.remove('hide');
                    } else {
                        alertError.querySelector('#editErrorMsg').textContent = data.message || 'Failed to load training data';
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
        
        function populateEditForm(training) {
            document.getElementById('edit_training_id').value = training.id;
            document.getElementById('edit_employee_name').value = training.employee_name;
            document.getElementById('edit_training_type').value = training.training_type;
            document.getElementById('edit_division').value = training.division;
            document.getElementById('edit_department').value = training.department || '';
            document.getElementById('edit_title_of_activity').value = training.title_of_activity;
            document.getElementById('edit_date_from').value = training.date_from;
            document.getElementById('edit_date_to').value = training.date_to;
            document.getElementById('edit_venue').value = training.venue;
            document.getElementById('edit_ob_ot').value = training.ob_ot || '';
            document.getElementById('edit_hospital_order').value = training.hospital_order || '';
            document.getElementById('edit_remarks').value = training.remarks || '';
            
            if (training.date_filed) {
                document.getElementById('edit_date_filed').value = training.date_filed;
            }
            if (training.ptr_deadline) {
                document.getElementById('edit_ptr_deadline').value = training.ptr_deadline;
            }
            
            var ptrFileDiv = document.getElementById('currentPtrFile');
            if (training.ptr_file) {
                ptrFileDiv.innerHTML = '<i class="fas fa-file-pdf"></i> Current: <a href="../public/' + training.ptr_file + '" target="_blank">View PTR File</a>';
            } else {
                ptrFileDiv.innerHTML = '';
            }
            
            toggleEditExternalFields();
        }
        
        function toggleEditExternalFields() {
            var type = document.getElementById('edit_training_type').value;
            var externalFields = document.getElementById('edit_external_fields');
            if (type === 'External') {
                externalFields.style.display = 'block';
            } else {
                externalFields.style.display = 'none';
            }
        }
        
        function submitEditForm() {
            var form = document.getElementById('editForm');
            var formData = new FormData(form);
            var alertSuccess = document.getElementById('editAlertSuccess');
            var alertError = document.getElementById('editAlertError');
            var submitBtn = document.querySelector('#editFormContainer .btn-submit');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Updating...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Training';
                
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
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Training';
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
        
        // Initialize
        toggleAddExternalFields();
    </script>
</body>
</html>