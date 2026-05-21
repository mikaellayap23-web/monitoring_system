<?php
require_once '../public/config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../public/login.php');
    exit;
}

$id = $_GET['id'] ?? 0;
$success_msg = '';
$error_msg = '';

$stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
$stmt->execute([$id]);
$training = $stmt->fetch();

if (!$training) {
    header('Location: training_list.php?msg=not_found');
    exit;
}

$users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_training'])) {
    $user_id = $_POST['user_id'];
    $employee_name = $_POST['employee_name'];
    $training_type = $_POST['training_type'];
    $division = $_POST['division'];
    
    $department_section = $_POST['department_section'];
    $department = '';
    $section = '';
    if ($department_section) {
        list($department, $section) = explode('||', $department_section);
    }
    
    $unit = $_POST['unit'];
    $title_of_activity = $_POST['title_of_activity'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $venue = $_POST['venue'];
    $hospital_order = $_POST['hospital_order'];
    $remarks = $_POST['remarks'];
    $ob_ot = $_POST['ob_ot'];
    
    $date_filed = null;
    $ptr_deadline = null;
    $ptr_file = $training['ptr_file'];
    
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
    
    $stmt = $pdo->prepare("UPDATE trainings SET user_id=?, employee_name=?, training_type=?, division=?, department=?, section=?, unit=?, title_of_activity=?, date_from=?, date_to=?, venue=?, hospital_order=?, date_filed=?, ob_ot=?, ptr_deadline=?, ptr_file=?, remarks=? WHERE id=?");
    
    if ($stmt->execute([$user_id, $employee_name, $training_type, $division, $department, $section, $unit, $title_of_activity, $date_from, $date_to, $venue, $hospital_order, $date_filed, $ob_ot, $ptr_deadline, $ptr_file, $remarks, $id])) {
        $success_msg = 'Training updated successfully!';
        $stmt = $pdo->prepare("SELECT * FROM trainings WHERE id = ?");
        $stmt->execute([$id]);
        $training = $stmt->fetch();
    } else {
        $error_msg = 'Failed to update training.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Training - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/training_list.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
</head>
<body>
    <div class="app-container">
        <?php include '../public/sidebar.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-edit"></i> Edit Training</h1>
                <div><i class="fas fa-user-shield"></i> Admin: <?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
            
            <?php if ($success_msg): ?>
                <div class="alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?= $error_msg ?></div>
            <?php endif; ?>
            
            <div class="section-card">
                <h3><i class="fas fa-edit"></i> Edit Training Record</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Employee *</label>
                            <select name="user_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $training['user_id'] == $user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-user-tag"></i> Employee Name *</label>
                            <input type="text" name="employee_name" value="<?= htmlspecialchars($training['employee_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Training Type *</label>
                            <select name="training_type" id="training_type" required onchange="toggleExternalFields()">
                                <option value="Internal" <?= $training['training_type'] === 'Internal' ? 'selected' : '' ?>>Internal</option>
                                <option value="External" <?= $training['training_type'] === 'External' ? 'selected' : '' ?>>External</option>
                            </select>
                        </div>
                    </div>
                    
<div class="form-row">
                         <div class="form-group">
                             <label><i class="fas fa-building"></i> Division *</label>
                             <select name="division" required>
                                 <option value="">Select Division</option>
                                 <option value="nursing service" <?= $training['division'] === 'nursing service' ? 'selected' : '' ?>>nursing service</option>
                                 <option value="medical service" <?= $training['division'] === 'medical service' ? 'selected' : '' ?>>medical service</option>
                                 <option value="HOPSS (HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE)" <?= $training['division'] === 'HOPSS (HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE)' ? 'selected' : '' ?>>HOPSS (HOSPITAL OPERATIONS AND PATIENT SUPPORT SERVICE)</option>
                                 <option value="ALLIED HEALTH PROFESSIONAL SERVICE" <?= $training['division'] === 'ALLIED HEALTH PROFESSIONAL SERVICE' ? 'selected' : '' ?>>ALLIED HEALTH PROFESSIONAL SERVICE</option>
                                 <option value="FINANCES" <?= $training['division'] === 'FINANCES' ? 'selected' : '' ?>>FINANCES</option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label><i class="fas fa-building"></i> Department/Section *</label>
                             <select name="department_section" id="department_section" required>
                                 <option value="<?= htmlspecialchars($training['department'] . '||' . $training['section']) ?>"><?= htmlspecialchars($training['department'] . ' / ' . $training['section']) ?></option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label><i class="fas fa-briefcase"></i> OB/OT *</label>
                             <select name="ob_ot" required>
                                 <option value="">Select OB/OT</option>
                                 <option value="Official Business" <?= $training['ob_ot'] === 'Official Business' ? 'selected' : '' ?>>Official Business</option>
                                 <option value="Official Time" <?= $training['ob_ot'] === 'Official Time' ? 'selected' : '' ?>>Official Time</option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label><i class="fas fa-users"></i> Unit</label>
                             <input type="text" name="unit" value="<?= htmlspecialchars($training['unit']) ?>">
                         </div>
                     </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-heading"></i> Title of Activity *</label>
                            <input type="text" name="title_of_activity" value="<?= htmlspecialchars($training['title_of_activity']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Date From *</label>
                            <input type="date" name="date_from" value="<?= $training['date_from'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-calendar-alt"></i> Date To *</label>
                            <input type="date" name="date_to" value="<?= $training['date_to'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Venue *</label>
                            <select name="venue" required>
                                <option value="Online" <?= $training['venue'] === 'Online' ? 'selected' : '' ?>>Online</option>
                                <option value="Auditorium" <?= $training['venue'] === 'Auditorium' ? 'selected' : '' ?>>Auditorium</option>
                                <option value="Executive Lounge" <?= $training['venue'] === 'Executive Lounge' ? 'selected' : '' ?>>Executive Lounge</option>
                                <option value="Local" <?= $training['venue'] === 'Local' ? 'selected' : '' ?>>Local</option>
                                <option value="International" <?= $training['venue'] === 'International' ? 'selected' : '' ?>>International</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-file-alt"></i> Hospital Order</label>
                            <input type="text" name="hospital_order" value="<?= htmlspecialchars($training['hospital_order']) ?>">
                        </div>
                    </div>
                    
                    <div id="external_fields" class="external-fields" style="<?= $training['training_type'] === 'External' ? 'display:block' : 'display:none' ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Date Filed</label>
                                <input type="date" name="date_filed" value="<?= $training['date_filed'] ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-hourglass-end"></i> PTR Deadline</label>
                                <input type="date" name="ptr_deadline" value="<?= $training['ptr_deadline'] ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-file-upload"></i> PTR File</label>
                                <input type="file" name="ptr_file">
                                <?php if ($training['ptr_file']): ?>
                                    <br><a href="../public/<?= $training['ptr_file'] ?>" target="_blank"><i class="fas fa-file-pdf"></i> Current File</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-comment"></i> Remarks</label>
                            <textarea name="remarks" rows="2"><?= htmlspecialchars($training['remarks']) ?></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" name="edit_training" class="btn-submit"><i class="fas fa-save"></i> Update Training</button>
                    <a href="training_list.php" class="btn-submit" style="background: #666; text-decoration: none;"><i class="fas fa-times"></i> Cancel</a>
                </form>
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
    </script>
</body>
</html>