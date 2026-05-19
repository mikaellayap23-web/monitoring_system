<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM trainings WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: my_trainings.php?msg=deleted');
    exit;
}

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_training'])) {
    $user_id = $_POST['user_id'];
    $employee_name = $_POST['employee_name'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    
    // Split the combined department/section value
    $department_section = $_POST['department_section'];
    list($department, $section) = explode('||', $department_section);
    
    $unit = $_POST['unit'];
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    $ob_ot = $_POST['ob_ot']; // OB/OT is now always present
    
    $date_filed = null;
    $ptr_deadline = null;
    $ptr_file = null;
    
    if ($training_type === 'External') {
        $date_filed = $_POST['date_filed'];
        $ptr_deadline = $_POST['ptr_deadline'];
        
        if (isset($_FILES['ptr_file']) && $_FILES['ptr_file']['error'] === 0) {
            $upload_dir = '../public/uploads/ptr_reports/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_name = time() . '_' . $_FILES['ptr_file']['name'];
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['ptr_file']['tmp_name'], $target_file)) {
                $ptr_file = 'uploads/ptr_reports/' . $file_name;
            }
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO trainings (user_id, employee_name, training_type, division, department, section, unit, title_of_activity, date_from, date_to, venue, hospital_order, date_filed, ob_ot, ptr_deadline, ptr_file, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$user_id, $employee_name, $training_type, $division, $department, $section, $unit, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks])) {
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

$search = $_GET['search'] ?? '';
$query = "SELECT t.*, u.username FROM trainings t LEFT JOIN users u ON t.user_id = u.id";
if ($search) {
    $query .= " WHERE t.employee_name LIKE '%$search%' OR t.title_of_activity LIKE '%$search%'";
}
$query .= " ORDER BY t.created_at DESC";
$trainings = $pdo->query($query)->fetchAll();

$users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Trainings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/training_list.css">
</head>
<body>
    <div class="app-container">
        <?php include '../public/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-calendar-alt"></i> My Trainings</h1>
                <div><i class="fas fa-user"></i> Employee: <?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <div class="section-card">
                <h3><i class="fas fa-plus-circle"></i> Add New Training</h3>
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
                            <label><i class="fas fa-user-tag"></i> Employee Name *</label>
                            <input type="text" name="employee_name" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Training Type *</label>
                            <select name="training_type" id="training_type" required onchange="toggleExternalFields()">
                                <option value="Internal">Internal</option>
                                <option value="External">External</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Division *</label>
                            <input type="text" name="division" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-building"></i> Department/Section *</label>
                            <select name="department_section" id="department_section" required>
                                <option value="">Select Department/Section</option>
                                <!-- Options will be added dynamically or you can add them here -->
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
                        <div class="form-group">
                            <label><i class="fas fa-users"></i> Unit</label>
                            <input type="text" name="unit">
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
                    
                    <div id="external_fields" class="external-fields">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Date Filed</label>
                                <input type="date" name="date_filed">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-hourglass-end"></i> PTR Deadline</label>
                                <input type="date" name="ptr_deadline">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-file-upload"></i> PTR File</label>
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
                    
                    <button type="submit" name="add_training" class="btn-submit"><i class="fas fa-save"></i> Add Training</button>
                </form>
            </div>
            
            <div class="search-bar">
                <form method="get">
                    <input type="text" name="search" placeholder="Search by employee or training title..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="my_trainings.php"><i class="fas fa-times"></i> Clear</a>
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
                            <th>Dept/Section</th>
                            <th>Date</th>
                            <th>Venue</th>
                            <th>OB/OT</th>
                            <th>PTR Status</th>
                            <th>Actions</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trainings as $training): ?>
                             <tr>
                                 <td><?= $training['id'] ?></td>
                                 <td><?= htmlspecialchars($training['employee_name']) ?></td>
                                 <td><?= $training['training_type'] ?></td>
                                 <td><?= htmlspecialchars($training['title_of_activity']) ?></td>
                                 <td><?= htmlspecialchars($training['division']) ?></td>
                                 <td><?= htmlspecialchars($training['department'] . ' / ' . $training['section']) ?></td>
                                 <td><?= $training['date_from'] ?> to <?= $training['date_to'] ?></td>
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
                                            <br><a href="../public/<?= $training['ptr_file'] ?>" target="_blank"><i class="fas fa-file-pdf"></i> View File</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                 </td>
                                 <td>
                                    <a href="edit_training.php?id=<?= $training['id'] ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <a href="?delete=<?= $training['id'] ?>" class="btn-delete" onclick="return confirm('Delete this training?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                 </td>
                             </tr>
                        <?php endforeach; ?>
                    </tbody>
                 </table>
            </div>
        </div>
    </div>
    
    <script>
        function toggleExternalFields() {
            var type = document.getElementById('training_type').value;
            var externalFields = document.getElementById('external_fields');
            if (type === 'External') {
                externalFields.style.display = 'block';
            } else {
                externalFields.style.display = 'none';
            }
        }
        toggleExternalFields();
    </script>
</body>
</html>