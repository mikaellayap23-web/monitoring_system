<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

// Fetch divisions and departments from database
$divisions = $pdo->query("SELECT name FROM divisions ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll();

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: training_list.php?msg=deleted');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    $user_id = $_POST['user_id'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    $department = trim($_POST['department'] ?? '');
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    
    // Get employee name from users table
    $stmt2 = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $stmt2->execute([$user_id]);
    $user = $stmt2->fetch();
    $employee_name = $user['full_name'] ?: $user['username'];
    
    // External training fields (default null for Internal)
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
                mkdir($upload_dir, 0755, true);
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
    } else {
        $error_msg = 'Failed to add training.';
    }
}

if (isset($_POST['update_ptr'])) {
    $id = $_POST['training_id'];
    $submitted = isset($_POST['ptr_submitted']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE trainings SET ptr_submitted = ? WHERE id = ?");
    $stmt->execute([$submitted, $id]);
    $success_msg = 'PTR status updated!';
}

// Handle AJAX Edit Request
if (isset($_GET['ajax_get_training'])) {
    $id = $_GET['ajax_get_training'];
    $stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
    $stmt->execute([$id]);
    $training = $stmt->fetch();
    if ($training) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'training' => $training]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Training not found']);
    }
    exit;
}

// Handle AJAX Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_training'])) {
    $id = $_POST['training_id'];
    $user_id = $_POST['user_id'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    $department = trim($_POST['department'] ?? '');
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    $ob_ot = $_POST['ob_ot'] ?? null;
    
    // Get employee name from users table
    $stmt2 = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
    $stmt2->execute([$user_id]);
    $user = $stmt2->fetch();
    $employee_name = $user['full_name'] ?: $user['username'];
    
    $date_filed = null;
    $ptr_deadline = null;
    $ptr_file = null;
    
    // Get existing PTR file if not updating
    $stmt3 = $pdo->prepare("SELECT ptr_file FROM trainings WHERE id = ?");
    $stmt3->execute([$id]);
    $existing = $stmt3->fetch();
    $ptr_file = $existing['ptr_file'];
    
    if ($training_type === 'External') {
        $date_filed = $_POST['date_filed'];
        $ptr_deadline = $_POST['ptr_deadline'];
        
        if (isset($_FILES['ptr_file']) && $_FILES['ptr_file']['error'] === 0) {
            $upload_dir = '../public/uploads/ptr_reports/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['ptr_file']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['ptr_file']['tmp_name'], $target_file)) {
                $ptr_file = 'uploads/ptr_reports/' . $file_name;
            }
        }
    }
    
    $stmt = $pdo->prepare("UPDATE trainings SET user_id=?, employee_name=?, training_type=?, division=?, department=?, title_of_activity=?, date_from=?, date_to=?, venue=?, hospital_order=?, date_filed=?, ob_ot=?, ptr_deadline=?, ptr_file=?, remarks=? WHERE id=?");
    
    if ($stmt->execute([$user_id, $employee_name, $training_type, $division, $department, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks, $id])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Training updated successfully!']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to update training.']);
    }
    exit;
}

$search = $_GET['search'] ?? '';
$query = "SELECT t.*, u.username FROM trainings t LEFT JOIN users u ON t.user_id = u.id";
$search_params = [];
if ($search) {
    $query .= " WHERE t.employee_name LIKE ? OR t.title_of_activity LIKE ?";
    $search_params[] = "%$search%";
    $search_params[] = "%$search%";
}
$query .= " ORDER BY t.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($search_params);
$trainings = $stmt->fetchAll();

$users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Training List - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/training_list.css">
    <style>
        /* Additional styles for new columns */
        .data-table {
            min-width: 100%;
        }
        .data-table th,
        .data-table td {
            white-space: nowrap;
        }
        .remarks-cell {
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }
        .hospital-order-cell {
            font-family: monospace;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../public/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-calendar-alt"></i> Training List Management</h1>
                <div><i class="fas fa-user-shield"></i> Admin: <?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
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
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Employee *</label>
                                    <select name="user_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
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
                                            <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Department *</label>
                                    <select name="department" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
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
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-file-alt"></i> Hospital Order</label>
                                    <input type="text" name="hospital_order" placeholder="e.g., HOSP-2024-001">
                                </div>
                            </div>
                            
                            <div id="add_external_fields" class="external-fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-day"></i> Date Filed *</label>
                                        <input type="date" name="date_filed">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-briefcase"></i> OB/OT *</label>
                                        <select name="ob_ot">
                                            <option value="">Select OB/OT</option>
                                            <option value="Official Business">Official Business</option>
                                            <option value="Official Time">Official Time</option>
                                        </select>
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
                                    <textarea name="remarks" rows="2" placeholder="Additional notes..."></textarea>
                                </div>
                            </div>
                            
                            <div class="button-container">
                                <button type="submit" name="add_training" class="btn-submit"><i class="fas fa-save"></i> Add Training</button>
                                <button type="button" class="btn-cancel" onclick="closeAddModal()"><i class="fas fa-times"></i> Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Edit Training Modal -->
            <div id="editModal" class="modal modal-edit">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><i class="fas fa-edit"></i> Edit Training Record</h2>
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
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Employee *</label>
                                        <select name="user_id" id="edit_user_id" required>
                                            <option value="">Select Employee</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
                                                <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-building"></i> Department *</label>
                                        <select name="department" id="edit_department" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= htmlspecialchars($dept['name']) ?>"><?= htmlspecialchars($dept['name']) ?></option>
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
                                            <label><i class="fas fa-calendar-day"></i> Date Filed</label>
                                            <input type="date" name="date_filed" id="edit_date_filed">
                                        </div>
                                        <div class="form-group">
                                            <label><i class="fas fa-briefcase"></i> OB/OT</label>
                                            <select name="ob_ot" id="edit_ob_ot">
                                                <option value="">Select OB/OT</option>
                                                <option value="Official Business">Official Business</option>
                                                <option value="Official Time">Official Time</option>
                                            </select>
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
                                    <button type="button" class="btn-submit" onclick="submitEditForm()"><i class="fas fa-save"></i> Update Training</button>
                                    <button type="button" class="btn-cancel" onclick="closeEditModal()"><i class="fas fa-times"></i> Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="search-bar">
                <form method="get">
                    <input type="text" name="search" placeholder="Search by employee or training title..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="training_list.php" class="clear-link"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="data-table">
                <h3><i class="fas fa-calendar-alt"></i> Training Records</h3>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Division</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>Hospital Order #</th>
                            <th>OB/OT</th>
                            <th>PTR Status</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                         </thead>
                    <tbody>
                        <?php foreach ($trainings as $training): ?>
                            <tr>
                                <td><?= $training['id'] ?></td>
                                <td><?= htmlspecialchars($training['employee_name']) ?></td>
                                <td>
                                    <span class="type-badge <?= $training['training_type'] == 'Internal' ? 'type-internal' : 'type-external' ?>">
                                        <?= $training['training_type'] ?>
                                    </span>
                                 </div>
                                <td><?= htmlspecialchars($training['title_of_activity']) ?></td>
                                <td><?= htmlspecialchars($training['division']) ?></td>
                                <td><?= htmlspecialchars($training['department'] ?? '-') ?></td>
                                <td><?= date('M d, Y', strtotime($training['date_from'])) ?> - <?= date('M d, Y', strtotime($training['date_to'])) ?></td>
                                <td><?= $training['venue'] ?></td>
                                <td class="hospital-order-cell"><?= htmlspecialchars($training['hospital_order'] ?: '-') ?></td>
                                <td>
                                    <?php if (!empty($training['ob_ot'])): ?>
                                        <span class="ob-badge"><?= htmlspecialchars($training['ob_ot']) ?></span>
                                    <?php else: ?>
                                        <span class="ob-badge-na">-</span>
                                    <?php endif; ?>
                                 </div>
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
                                <td class="remarks-cell"><?= htmlspecialchars(strlen($training['remarks'] ?? '') > 50 ? substr($training['remarks'] ?? '', 0, 50) . '...' : $training['remarks'] ?? '-') ?></td>
                                <td>
                                    <button onclick="openEditModal(<?= $training['id'] ?>)" class="btn-edit"><i class="fas fa-edit"></i> Edit</button>
                                    <a href="?delete=<?= $training['id'] ?>" class="btn-delete" onclick="return confirm('Delete this training?')"><i class="fas fa-trash-alt"></i> Delete</a>
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
            toggleAddExternalFields();
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function toggleAddExternalFields() {
            var type = document.getElementById('add_training_type').value;
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
            document.getElementById('edit_user_id').value = training.user_id;
            document.getElementById('edit_training_type').value = training.training_type;
            document.getElementById('edit_division').value = training.division;
            document.getElementById('edit_department').value = training.department;
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
        
        toggleAddExternalFields();
    </script>
</body>
</html>