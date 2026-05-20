<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

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
    $ob_ot = $_POST['ob_ot'];
    
    // Set all external fields to null since we're not using them
    $date_filed = null;
    $ptr_deadline = null;
    $ptr_file = null;
    
    $stmt = $pdo->prepare("INSERT INTO trainings (user_id, training_type, division, department, section, unit, title_of_activity, date_from, date_to, venue, hospital_order, date_filed, ob_ot, ptr_deadline, ptr_file, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$user_id, $training_type, $division, $department, $section, $unit, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks])) {
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
    <link rel="stylesheet" href="../assets/css/training_list.css">
    <style>
        /* Modal Styles - Matching user_management.php color #1B3C53 */
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
            max-height: 90%;
            overflow-y: auto;
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
        }
        
        /* Style the submit button in modal to match user_management */
        .modal-body .btn-submit {
            background: #1B3C53;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .modal-body .btn-submit:hover {
            background: #0f2a3a;
        }
        
        /* Form styling to match user_management */
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
            padding: 10px;
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
        
        /* Adjust padding for specific fields */
        .form-group select[name="ob_ot"],
        .form-group input[name="hospital_order"] {
            padding: 10px;
        }
        
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
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <!-- Add Training Button - Color #1B3C53 like user_management -->
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
                                        <option value="nursing service">nursing service</option>
                                        <option value="medical service">medical service</option>
                                        <option value="HOPSS (HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE)">HOPSS (HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE)</option>
                                        <option value="ALLIED HEALTH PROFESSIONAL SERVICE">ALLIED HEALTH PROFESSIONAL SERVICE</option>
                                        <option value="FINANCES">FINANCES</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Department/Section/Unit *</label>
                                    <select name="department_section" id="department_section" required>
                                        <option value="">Select Department/Section/Unit</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Employee *</label>
                                    <select name="user_id" required>
                                        <option value="">Select Employee</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></option>
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
                            
                            <button type="submit" name="add_training" class="btn-submit"><i class="fas fa-save"></i> Add Training</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="search-bar">
                <form method="get">
                    <input type="text" name="search" placeholder="Search by employee or training title..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-submit"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="training_list.php"><i class="fas fa-times"></i> Clear</a>
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
    </script>
</body>
</html>