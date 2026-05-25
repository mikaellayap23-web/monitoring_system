<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$current_user = null;
if ($role === 'unit_head' || $role === 'admin') {
    $stmt = $pdo->prepare("SELECT division, department, username, full_name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();
}

// Set timezone
date_default_timezone_set('Asia/Manila');
$current_datetime = date('l, F j, Y - g:i A');

$where_clauses = [];
$params = [];

if ($role === 'employee') {
    $where_clauses[] = "user_id = ?";
    $params[] = $user_id;
} elseif ($role === 'unit_head') {
    $filter_division = $_GET['division'] ?? '';
    $filter_dept = $_GET['department'] ?? '';

    if (empty($filter_division) && !empty($current_user['division'])) {
        $filter_division = $current_user['division'];
    }

    if (empty($filter_dept) && !empty($current_user['department'])) {
        $filter_dept = $current_user['department'];
    }

    if (!empty($filter_division)) {
        $where_clauses[] = "division = ?";
        $params[] = $filter_division;
    }
    if (!empty($filter_dept)) {
        $where_clauses[] = "department = ?";
        $params[] = $filter_dept;
    }

    $filter_employee = $_GET['employee'] ?? '';
    if (!empty($filter_employee)) {
        $where_clauses[] = "employee_name LIKE ?";
        $params[] = "%$filter_employee%";
    }

    $filter_date_from = $_GET['date_from'] ?? '';
    $filter_date_to = $_GET['date_to'] ?? '';
    if (!empty($filter_date_from) && !empty($filter_date_to)) {
        $where_clauses[] = "(date_from >= ? AND date_to <= ?)";
        $params[] = $filter_date_from;
        $params[] = $filter_date_to;
    }
} else {
    $filter_division = $_GET['division'] ?? '';
    $filter_dept = $_GET['department'] ?? '';
    $filter_employee = $_GET['employee'] ?? '';
    $filter_date_from = $_GET['date_from'] ?? '';
    $filter_date_to = $_GET['date_to'] ?? '';
    
    if (!empty($filter_division)) {
        $where_clauses[] = "division = ?";
        $params[] = $filter_division;
    }
    if (!empty($filter_dept)) {
        $where_clauses[] = "department = ?";
        $params[] = $filter_dept;
    }
    if (!empty($filter_employee)) {
        $where_clauses[] = "employee_name LIKE ?";
        $params[] = "%$filter_employee%";
    }
    if (!empty($filter_date_from) && !empty($filter_date_to)) {
        $where_clauses[] = "(date_from >= ? AND date_to <= ?)";
        $params[] = $filter_date_from;
        $params[] = $filter_date_to;
    }
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$divisions = [];
$departments = [];
$employees = [];

if ($role === 'admin') {
    $divisions = $pdo->query("SELECT name FROM divisions ORDER BY name")->fetchAll();

    $allDepartments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll();
    foreach ($allDepartments as $dept) {
        $departments[] = $dept['name'];
    }

    $employees = $pdo->query("SELECT DISTINCT full_name as employee_name FROM users WHERE role != 'admin' AND full_name IS NOT NULL ORDER BY full_name")->fetchAll();
} elseif ($role === 'unit_head' && $current_user) {
    if (!empty($current_user['division'])) {
        $divisions = [[ 'name' => $current_user['division'] ]];
    }

    if (!empty($current_user['department'])) {
        $departments = [$current_user['department']];
    }

    $employeeStmt = $pdo->prepare("
        SELECT DISTINCT u.full_name as employee_name
        FROM users u
        WHERE u.division = ?
        AND u.department = ?
        AND u.role IN ('employee', 'unit_head')
        AND u.full_name IS NOT NULL
        ORDER BY u.full_name
    ");
    $employeeStmt->execute([
        $current_user['division'],
        $current_user['department']
    ]);
    $employees = $employeeStmt->fetchAll();
}

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trainings $where_sql");
$stmt->execute($params);
$total_trainings = $stmt->fetch()['total'];

$internal_sql = "SELECT COUNT(*) as total FROM trainings";
if (!empty($where_clauses)) {
    $internal_sql .= " $where_sql AND training_type = 'Internal'";
} else {
    $internal_sql .= " WHERE training_type = 'Internal'";
}
$stmt = $pdo->prepare($internal_sql);
$stmt->execute($params);
$internal_count = $stmt->fetch()['total'];

$external_sql = "SELECT COUNT(*) as total FROM trainings";
if (!empty($where_clauses)) {
    $external_sql .= " $where_sql AND training_type = 'External'";
} else {
    $external_sql .= " WHERE training_type = 'External'";
}
$stmt = $pdo->prepare($external_sql);
$stmt->execute($params);
$external_count = $stmt->fetch()['total'];

$ob_ot_sql = "SELECT COUNT(*) as total FROM trainings";
if (!empty($where_clauses)) {
    $ob_ot_sql .= " $where_sql AND ob_ot IS NOT NULL AND ob_ot != ''";
} else {
    $ob_ot_sql .= " WHERE ob_ot IS NOT NULL AND ob_ot != ''";
}
$stmt = $pdo->prepare($ob_ot_sql);
$stmt->execute($params);
$ob_ot_count = $stmt->fetch()['total'];

$ptr_sql = "SELECT COUNT(*) as total, SUM(ptr_submitted) as submitted FROM trainings";
if (!empty($where_clauses)) {
    $ptr_sql .= " $where_sql AND training_type = 'External'";
} else {
    $ptr_sql .= " WHERE training_type = 'External'";
}
$stmt = $pdo->prepare($ptr_sql);
$stmt->execute($params);
$ptr_data = $stmt->fetch();
$ptr_total = $ptr_data['total'] ?? 0;
$ptr_submitted = $ptr_data['submitted'] ?? 0;
$ptr_percentage = $ptr_total > 0 ? round(($ptr_submitted / $ptr_total) * 100) : 0;

$total_employees = 0;
$attended_employees = 0;
$not_attended = 0;
$attended_percentage = 0;

$bar_labels = [];
$bar_attended = [];
$bar_not_attended = [];
$training_data = [];

if ($role === 'unit_head' && $current_user) {
    $emp_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role IN ('employee', 'unit_head') AND division = ? AND department = ?");
    $emp_stmt->execute([
        $current_user['division'] ?? '',
        $current_user['department'] ?? ''
    ]);
    $total_employees = $emp_stmt->fetch()['total'];
} else {
    $total_employees = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role IN ('employee', 'unit_head')")->fetch()['total'];
}

if ($role !== 'employee') {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as attended FROM trainings $where_sql");
    $stmt->execute($params);
    $attended_employees = $stmt->fetch()['attended'];
    $not_attended = $total_employees - $attended_employees;
    $attended_percentage = $total_employees > 0 ? round(($attended_employees / $total_employees) * 100) : 0;
    
    $bar_where = "";
    $bar_params = [];
    
    if (!empty($filter_division)) {
        $bar_where .= " AND u.division = ?";
        $bar_params[] = $filter_division;
    }
    if (!empty($filter_dept)) {
        $bar_where .= " AND u.department = ?";
        $bar_params[] = $filter_dept;
    }
    
    $bar_sql = "
        SELECT
            COALESCE(u.department, 'Unassigned') AS name,
            COUNT(DISTINCT u.id) AS total_employees,
            COUNT(DISTINCT t.user_id) AS attended
        FROM users u
        LEFT JOIN trainings t ON u.id = t.user_id
        WHERE u.role IN ('employee', 'unit_head')
        $bar_where
        GROUP BY COALESCE(u.department, 'Unassigned')
        ORDER BY name
    ";
    
    $stmt = $pdo->prepare($bar_sql);
    $stmt->execute($bar_params);
    $bar_data = $stmt->fetchAll();
    
    foreach ($bar_data as $row) {
        $bar_labels[] = $row['name'];
        $bar_attended[] = $row['attended'];
        $bar_not_attended[] = $row['total_employees'] - $row['attended'];
    }
    
    $table_sql = "SELECT
        t.employee_name,
        t.training_type,
        t.division,
        t.department,
        t.title_of_activity,
        t.date_from,
        t.date_to,
        t.ob_ot,
        t.ptr_submitted
        FROM trainings t
        $where_sql
        ORDER BY t.created_at DESC";
    $stmt = $pdo->prepare($table_sql);
    $stmt->execute($params);
    $training_data = $stmt->fetchAll();
}

// Handle AJAX Report Data
if (isset($_GET['ajax_get_report_data'])) {
    header('Content-Type: application/json');
    
    $report_division = $_GET['report_division'] ?? '';
    $report_department = $_GET['report_department'] ?? '';
    $report_date_from = $_GET['report_date_from'] ?? '';
    $report_date_to = $_GET['report_date_to'] ?? '';
    
    $report_where = [];
    $report_params = [];
    
    if ($role === 'unit_head' && $current_user) {
        $report_where[] = "division = ?";
        $report_params[] = $current_user['division'];
        $report_where[] = "department = ?";
        $report_params[] = $current_user['department'];
    } else {
        if (!empty($report_division)) {
            $report_where[] = "division = ?";
            $report_params[] = $report_division;
        }
        if (!empty($report_department)) {
            $report_where[] = "department = ?";
            $report_params[] = $report_department;
        }
    }
    
    if (!empty($report_date_from)) {
        $report_where[] = "date_from >= ?";
        $report_params[] = $report_date_from;
    }
    
    if (!empty($report_date_to)) {
        $report_where[] = "date_to <= ?";
        $report_params[] = $report_date_to;
    }
    
    $report_where_sql = !empty($report_where) ? "WHERE " . implode(" AND ", $report_where) : "";
    
    $report_query = "
        SELECT 
            DATE_FORMAT(t.date_from, '%M') as month_name,
            t.employee_name,
            t.title_of_activity,
            DATE_FORMAT(t.date_from, '%Y-%m-%d') as date_from,
            DATE_FORMAT(t.date_to, '%Y-%m-%d') as date_to,
            DATE_ADD(t.date_to, INTERVAL 3 MONTH) as ptr_deadline,
            DATEDIFF(t.date_to, t.date_from) + 1 as num_days,
            CASE 
                WHEN t.ob_ot = 'Official Business' THEN 'Y' 
                ELSE 'N' 
            END as is_ob,
            t.training_type,
            t.ptr_submitted,
            t.remarks
        FROM trainings t
        $report_where_sql
        ORDER BY MONTH(t.date_from) ASC, t.date_from ASC
    ";
    
    $stmt = $pdo->prepare($report_query);
    $stmt->execute($report_params);
    $report_data = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $report_data]);
    exit;
}

// Pass current_user data to JavaScript
$user_division = $current_user['division'] ?? '';
$user_department = $current_user['department'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <?php if ($role !== 'employee'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/xlsx-js-style@1.2.0/dist/xlsx.bundle.js"></script>

    <style>
        .header-right {
            text-align: right;
        }
        .datetime-display {
            font-size: 0.8rem;
            color: #355872;
            background: #F2FCFC;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .datetime-display i {
            color: #0245A3;
            font-size: 12px;
        }
        
        .report-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(3px);
        }
        
        .report-modal-content {
            background-color: #fff;
            margin: 2% auto;
            padding: 0;
            border-radius: 16px;
            width: 95%;
            max-width: 1400px;
            max-height: 90%;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .report-modal-header {
            padding: 18px 24px;
            background: #0245A3;
            color: white;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .report-modal-header h2 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .close-report-modal {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        
        .close-report-modal:hover {
            opacity: 0.8;
        }
        
        .report-filters {
            padding: 20px 24px;
            background: #F2FCFC;
            border-bottom: 1px solid #BDF1F6;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .report-filter-group {
            min-width: 150px;
        }
        
        .report-filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 12px;
            color: #355872;
        }
        
        .report-filter-group select,
        .report-filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #BDF1F6;
            border-radius: 8px;
            font-size: 13px;
            background: white;
        }
        
        .report-filter-group select:focus,
        .report-filter-group input:focus {
            outline: none;
            border-color: #8FBAF3;
            box-shadow: 0 0 0 2px rgba(143,186,243,0.2);
        }
        
        .report-filter-group button {
            background: #0245A3;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .report-filter-group button:hover {
            background: #355872;
            transform: translateY(-1px);
        }
        
        .export-btn {
            background: #7AAACE !important;
            margin-left: 10px;
        }
        
        .export-btn:hover {
            background: #8FBAF3 !important;
        }
        
        .report-table-container {
            padding: 24px;
            overflow-x: auto;
        }
        
        .report-title {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .report-title h3 {
            margin: 0;
            color: #0245A3;
            font-size: 1.3rem;
        }
        
        .report-title p {
            margin: 8px 0 0;
            font-size: 12px;
            color: #355872;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .report-table th {
            background: #0245A3;
            color: white;
            padding: 12px 10px;
            text-align: center;
            border: 1px solid #8FBAF3;
            font-weight: 600;
            white-space: nowrap;
        }
        
        .report-table td {
            padding: 10px;
            border: 1px solid #BDF1F6;
            vertical-align: middle;
            text-align: center;
        }
        
        .report-table tr:hover {
            background: #F2FCFC;
        }
        
        .ptr-yes {
            color: #10b981;
            font-weight: bold;
        }
        
        .ptr-no {
            color: #ef4444;
            font-weight: bold;
        }
        
        .signature-section {
            margin-top: 30px;
            padding: 20px 24px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            border-top: 1px solid #BDF1F6;
        }
        
        .signature-line {
            text-align: center;
        }
        
        .signature-line p {
            margin: 5px 0;
            font-size: 11px;
            color: #355872;
        }
        
        .signature-line .name {
            margin-top: 30px;
            font-weight: 600;
            color: #0245A3;
        }
        
        .btn-generate-report {
            background: #0245A3;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-generate-report:hover {
            background: #355872;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(2,69,163,0.2);
        }
        
        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
            min-width: 70px;
        }
        
        .type-internal {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .type-external {
            background: #fff3e0;
            color: #e65100;
        }
        
        .ob-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            background: #fef3c7;
            color: #92400e;
        }
        
        .ob-badge-na {
            background: #f1f5f9;
            color: #64748b;
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .ptr-badge { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 10px; 
            font-weight: 600;
            text-align: center;
            min-width: 70px;
        }
        
        .ptr-submitted { 
            background: #d4edda; 
            color: #155724; 
        }
        
        .ptr-pending { 
            background: #f8d7da; 
            color: #721c24; 
        }
        
        .ptr-na { 
            background: #e2e3e5; 
            color: #383d41; 
        }
        
        .btn-apply {
            background: #0245A3;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .clear-btn {
            background: #7AAACE;
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        
        .clear-btn:hover {
            background: #8FBAF3;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                <div class="header-right">
                    <div class="datetime-display">
                        <i class="fas fa-calendar-alt"></i>
                        <span><?= $current_datetime ?></span>
                    </div>
                </div>
            </div>
            
            <?php if ($role === 'unit_head' && $current_user): ?>
            <div class="unit-info">
                <i class="fas fa-building"></i>
                Managing Unit: <strong><?= htmlspecialchars($current_user['division'] ?? '') ?></strong> /
                <strong><?= htmlspecialchars($current_user['department'] ?? '') ?></strong>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-widgets">
                <div class="widget"><i class="fas fa-users"></i><h3>TOTAL STAFF</h3><p><?= $total_employees ?></p></div>
                <div class="widget"><i class="fas fa-graduation-cap"></i><h3>TOTAL TRAININGS</h3><p><?= $total_trainings ?></p></div>
                <div class="widget"><i class="fas fa-calendar-alt"></i><h3>INTERNAL</h3><p><?= $internal_count ?></p></div>
                <div class="widget"><i class="fas fa-globe"></i><h3>EXTERNAL</h3><p><?= $external_count ?></p></div>
                <div class="widget"><i class="fas fa-clock"></i><h3>OB/OT COUNT</h3><p><?= $ob_ot_count ?></p></div>
                <div class="widget"><i class="fas fa-file-alt"></i><h3>PTR SUBMITTED</h3><p><?= $ptr_submitted ?> / <?= $ptr_total ?> (<?= $ptr_percentage ?>%)</p></div>
            </div>
            
            <?php if ($role !== 'employee'): ?>
            <div class="filters-bar">
                <form method="get" class="filters-form">
                    <div class="filter-group">
                        <label>Division</label>
                        <select name="division">
                            <option value="">All Divisions</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= htmlspecialchars($d['name']) ?>" <?= ($_GET['division'] ?? '') == $d['name'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Department</label>
                        <select name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= ($_GET['department'] ?? '') == $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Employee Name</label>
                        <select name="employee">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= htmlspecialchars($e['employee_name']) ?>" <?= ($_GET['employee'] ?? '') == $e['employee_name'] ? 'selected' : '' ?>><?= htmlspecialchars($e['employee_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group date-range-group">
                        <label>Date Range</label>
                        <div class="date-range-inputs">
                            <input type="date" name="date_from" class="date-input" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                            <span class="date-separator">to</span>
                            <input type="date" name="date_to" class="date-input" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply Filters</button>
                        <?php if (!empty($_GET)): ?>
                            <a href="dashboard.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="charts-row">
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Training Attendance</h3>
                    <canvas id="pieChart"></canvas>
                    <p style="text-align:center;">Attended: <?= $attended_employees ?> (<?= $attended_percentage ?>%)<br>Not Attended: <?= $not_attended ?></p>
                </div>
                <div class="chart-container">
                    <h3><i class="fas fa-chart-bar"></i> Attendance by Department</h3>
                    <canvas id="barChart"></canvas>
                </div>
            </div>
            
            <div class="data-table">
                <div class="table-header">
                    <span><i class="fas fa-table"></i> Training Data (Filtered Results)</span>
                    <button class="btn-generate-report" onclick="openReportModal()">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Division</th>
                            <th>Department</th>
                            <th>Employee Name</th>
                            <th>Type</th>
                            <th>Title of Activity</th>
                            <th>Date Range</th>
                            <th>OB/OT</th>
                            <th>PTR Status</th>
                         </thead>
                    <tbody>
                        <?php if (isset($training_data) && count($training_data) > 0): ?>
                            <?php foreach ($training_data as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['division'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['department'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td>
                                        <span class="type-badge <?= $row['training_type'] == 'Internal' ? 'type-internal' : 'type-external' ?>">
                                            <?= $row['training_type'] ?>
                                        </span>
                                     </div>
                                    <td><?= htmlspecialchars($row['title_of_activity']) ?></div>
                                    <td><?= date('M d, Y', strtotime($row['date_from'])) ?> - <?= date('M d, Y', strtotime($row['date_to'])) ?></div>
                                    <td>
                                        <?php if (!empty($row['ob_ot'])): ?>
                                            <span class="ob-badge"><?= htmlspecialchars($row['ob_ot']) ?></span>
                                        <?php else: ?>
                                            <span class="ob-badge-na">-</span>
                                        <?php endif; ?>
                                     </div>
                                    <td>
                                        <?php if ($row['training_type'] == 'External'): ?>
                                            <span class="ptr-badge <?= $row['ptr_submitted'] ? 'ptr-submitted' : 'ptr-pending' ?>">
                                                <?= $row['ptr_submitted'] ? 'Submitted' : 'Pending' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ptr-badge ptr-na">N/A</span>
                                        <?php endif; ?>
                                     </div>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px;">
                                    <i class="fas fa-inbox"></i> No training records found
                                 </div>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                new Chart(document.getElementById('pieChart').getContext('2d'), { 
                    type: 'pie', 
                    data: { 
                        labels: ['Attended Training', 'Not Attended'], 
                        datasets: [{ 
                            data: [<?= $attended_employees ?>, <?= $not_attended ?>], 
                            backgroundColor: ['#0245A3', '#8FBAF3'] 
                        }] 
                    }, 
                    options: { responsive: true } 
                });
                
                new Chart(document.getElementById('barChart').getContext('2d'), { 
                    type: 'bar', 
                    data: { 
                        labels: <?= json_encode($bar_labels) ?>, 
                        datasets: [
                            { label: 'Attended', data: <?= json_encode($bar_attended) ?>, backgroundColor: '#0245A3' }, 
                            { label: 'Not Attended', data: <?= json_encode($bar_not_attended) ?>, backgroundColor: '#8FBAF3' }
                        ] 
                    }, 
                    options: { responsive: true, scales: { y: { beginAtZero: true } } } 
                });
            </script>
            <?php else: ?>
            <div class="employee-welcome">
                <h2>Welcome, <?= htmlspecialchars($username) ?>!</h2>
                <p>Here is your personal training summary. Your unit head can see aggregated data from your unit.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Report Modal with Date Pickers -->
    <div id="reportModal" class="report-modal">
        <div class="report-modal-content">
            <div class="report-modal-header">
                <h2><i class="fas fa-chart-line"></i> LEARNING AND DEVELOPMENT INTERVENTION MONITORING TOOL</h2>
                <span class="close-report-modal" onclick="closeReportModal()">&times;</span>
            </div>
            <div class="report-filters">
                <div class="report-filter-group">
                    <label>Division</label>
                    <?php if ($role === 'admin'): ?>
                        <select id="report_division">
                            <option value="">All Divisions</option>
                            <?php foreach ($divisions as $div): ?>
                                <option value="<?= htmlspecialchars($div['name']) ?>"><?= htmlspecialchars($div['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="report_division" value="<?= htmlspecialchars($current_user['division'] ?? '') ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="report-filter-group">
                    <label>Department</label>
                    <?php if ($role === 'admin'): ?>
                        <select id="report_department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="report_department" value="<?= htmlspecialchars($current_user['department'] ?? '') ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="report-filter-group">
                    <label>Date From</label>
                    <input type="date" id="report_date_from">
                </div>
                <div class="report-filter-group">
                    <label>Date To</label>
                    <input type="date" id="report_date_to">
                </div>
                <div class="report-filter-group">
                    <button onclick="loadReportData()"><i class="fas fa-filter"></i> Apply Filters</button>
                    <button onclick="exportReportToExcel()" class="export-btn"><i class="fas fa-download"></i> Export Excel</button>
                </div>
            </div>
            <div class="report-table-container">
                <div class="report-title">
                    <h3>LEARNING AND DEVELOPMENT INTERVENTION MONITORING TOOL</h3>
                    <p>100% Employees attended training; 90% of employees who attended training have submitted post-training activity report within 3 months from the time of the learning and development intervention.</p>
                </div>
                <table class="report-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>MONTH</th>
                            <th>NAME OF EMPLOYEE</th>
                            <th>TITLE OF ACTIVITY</th>
                            <th>DATE FROM</th>
                            <th>DATE TO</th>
                            <th>PTR DEADLINE (3 months after)</th>
                            <th># OF DAYS</th>
                            <th>OB</th>
                            <th>TYPE</th>
                            <th>PTR SUBMITTED</th>
                            <th>REMARKS</th>
                         </thead>
                    <tbody id="reportTableBody">
                        <tr><td colspan="11" style="text-align:center;">Select filters and click Apply Filters</div></tr>
                    </tbody>
                </table>
                <div class="signature-section">
                    <div class="signature-line">
                        <p>Prepared by:</p>
                        <p class="name">_________________</p>
                        <p>Department</p>
                    </div>
                    <div class="signature-line">
                        <p>Reviewed By:</p>
                        <p class="name">_________________</p>
                        <p>Immediate Supervisor</p>
                    </div>
                    <div class="signature-line">
                        <p>Approved by:</p>
                        <p class="name">_________________</p>
                        <p>Division Chief</p>
                    </div>
                    <div class="signature-line">
                        <p>Verified By:</p>
                        <p class="name">_________________</p>
                        <p>OPET</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Pass PHP variables to JavaScript
        var userDivision = <?= json_encode($user_division) ?>;
        var userDepartment = <?= json_encode($user_department) ?>;
        var userName = <?= json_encode($username) ?>;
        
        function openReportModal() { 
            var modal = document.getElementById('reportModal');
            if (modal) {
                modal.style.display = 'block'; 
                document.body.style.overflow = 'hidden'; 
                loadReportData();
            } else {
                console.error('Report modal not found');
            }
        }
        
        function closeReportModal() { 
            var modal = document.getElementById('reportModal');
            if (modal) {
                modal.style.display = 'none'; 
                document.body.style.overflow = 'auto';
            }
        }
        
        function loadReportData() {
            var division = document.getElementById('report_division') ? document.getElementById('report_division').value : '';
            var department = document.getElementById('report_department') ? document.getElementById('report_department').value : '';
            var dateFrom = document.getElementById('report_date_from').value;
            var dateTo = document.getElementById('report_date_to').value;
            
            var url = '?ajax_get_report_data=1&report_division=' + encodeURIComponent(division) + '&report_department=' + encodeURIComponent(department) + '&report_date_from=' + dateFrom + '&report_date_to=' + dateTo;
            
            var tbody = document.getElementById('reportTableBody');
            tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Loading...</div></tr>';
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        tbody.innerHTML = '';
                        for (var i = 0; i < data.data.length; i++) {
                            var row = data.data[i];
                            var rowHtml = '<tr>' +
                                '<td>' + (row.month_name || '') + '</td>' +
                                '<td>' + escapeHtml(row.employee_name || '') + '</td>' +
                                '<td>' + escapeHtml(row.title_of_activity || '') + '</td>' +
                                '<td>' + formatDate(row.date_from) + '</td>' +
                                '<td>' + formatDate(row.date_to) + '</td>' +
                                '<td>' + formatDate(row.ptr_deadline) + '</td>' +
                                '<td>' + (row.num_days || 1) + '</td>' +
                                '<td>' + (row.is_ob || 'N') + '</td>' +
                                '<td>' + (row.training_type || '') + '</td>' +
                                '<td>' + (row.ptr_submitted == 1 ? 'YES' : 'NO') + '</td>' +
                                '<td>' + escapeHtml(row.remarks || '') + '</td>' +
                                '</tr>';
                            tbody.innerHTML += rowHtml;
                        }
                    } else { 
                        tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;">No training records found for the selected filters</div></tr>'; 
                    }
                })
                .catch(error => { 
                    console.error('Error:', error); 
                    tbody.innerHTML = '<tr><td colspan="11" style="text-align:center;">Error loading data. Please try again.</div></tr>'; 
                });
        }
        
function exportReportToExcel() {
    var division = document.getElementById('report_division') ? document.getElementById('report_division').value : '';
    var department = document.getElementById('report_department') ? document.getElementById('report_department').value : '';
    var dateFrom = document.getElementById('report_date_from').value;
    var dateTo = document.getElementById('report_date_to').value;
    
    var url = '?ajax_get_report_data=1&report_division=' + encodeURIComponent(division) + '&report_department=' + encodeURIComponent(department) + '&report_date_from=' + dateFrom + '&report_date_to=' + dateTo;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                // Create workbook data
                var ws_data = [];
                
                // Row 1: Title
                ws_data.push(['LEARNING AND DEVELOPMENT INTERVENTION MONITORING TOOL']);
                
                // Row 2: Empty
                ws_data.push([]);
                
                // Row 3: Subtitle
                ws_data.push(['100% Employees attended training; 90% of employees who attended training have submitted post-training activity report within 3 months from the time of the learning and development intervention.']);
                
                // Row 4: Empty
                ws_data.push([]);
                
                // Row 5: Division
                var divisionText = division ? division : userDivision;
                ws_data.push(['DIVISION:', divisionText]);
                
                // Row 6: Department
                var departmentText = department ? department : userDepartment;
                ws_data.push(['DEPARTMENT:', departmentText]);
                
                // Row 7: Empty
                ws_data.push([]);
                
                 // Row 8: Table Headers
                 ws_data.push([
                     'MONTH', 
                     'NAME OF EMPLOYEE', 
                     'TITLE OF ACTIVITY', 
                     'DATE FROM', 
                     'DATE TO', 
                     'PTR DEADLINE (3 months after)', 
                     '# OF DAYS', 
                     'OB', 
                     'TYPE',
                     'PTR SUBMITTED',
                     'REMARKS'
                 ]);
                
                // Data rows
                for (var i = 0; i < data.data.length; i++) {
                    var row = data.data[i];
                    ws_data.push([
                        row.month_name || '',
                        row.employee_name || '',
                        row.title_of_activity || '',
                        formatDateForExcel(row.date_from),
                        formatDateForExcel(row.date_to),
                        formatDateForExcel(row.ptr_deadline),
                        row.num_days || 1,
                        row.is_ob || 'N',
                        row.training_type || '',
                        row.ptr_submitted == 1 ? 'YES' : 'NO',
                        row.remarks || ''
                    ]);
                }
                
                // Add empty rows before signature
                ws_data.push([]);
                ws_data.push([]);
                
                // Signature section
                ws_data.push(['Prepared by:', 'Reviewed By:', 'Approved by:', '[  ] OPET ']);
                ws_data.push(['', '',]);
                ws_data.push([userName,'', '',]);
                ws_data.push([userDepartment, 'Immediate Supervisor', 'Division Chief', 'Verified By:']);
                
                // Create worksheet
                var ws = XLSX.utils.aoa_to_sheet(ws_data);
                
                // Set column widths
                ws['!cols'] = [
                    {wch: 15},  // MONTH
                    {wch: 25},  // NAME OF EMPLOYEE
                    {wch: 35},  // TITLE OF ACTIVITY
                    {wch: 18},  // DATE FROM
                    {wch: 18},  // DATE TO
                    {wch: 32},  // PTR DEADLINE
                    {wch: 10},  // # OF DAYS
                    {wch: 8},   // OB
                    {wch: 12},  // TYPE
                    {wch: 15},  // PTR SUBMITTED
                    {wch: 30}   // REMARKS
                ];
                
                // Merge cells for title (row 0, columns 0-10)
                if (!ws['!merges']) ws['!merges'] = [];
                ws['!merges'].push({s: {r: 0, c: 0}, e: {r: 0, c: 10}});
                
                // Merge cells for subtitle (row 2, columns 0-10)
                ws['!merges'].push({s: {r: 2, c: 0}, e: {r: 2, c: 10}});
                
                // Apply styles using cell references
                for (var R = 0; R < ws_data.length; R++) {
                    for (var C = 0; C < ws_data[R].length; C++) {
                        var cellRef = XLSX.utils.encode_cell({r: R, c: C});
                        if (!ws[cellRef]) continue;
                        if (!ws[cellRef].s) ws[cellRef].s = {};
                        
                        // Title row (row 0) - bold, larger font, centered
                        if (R === 0) {
                            ws[cellRef].s.font = { bold: true, sz: 14 };
                            ws[cellRef].s.alignment = { horizontal: 'center', vertical: 'center' };
                        }
                        // Subtitle row (row 2) - centered
                        else if (R === 2) {
                            ws[cellRef].s.alignment = { horizontal: 'center', vertical: 'center' };
                        }
                        // Division row (row 4)
                        else if (R === 4) {
                            if (C === 1) {
                                ws[cellRef].s.font = { bold: true };
                            }
                        }
                        // Department row (row 5)
                        else if (R === 5) {
                            if (C === 1) {
                                ws[cellRef].s.font = { bold: true };
                            }
                        }
                        // Header row (row 7) - bold, centered, background color
                        else if (R === 7) {
                            ws[cellRef].s.font = { bold: true };
                            ws[cellRef].s.alignment = { horizontal: 'center', vertical: 'center' };
                            ws[cellRef].s.fill = { fgColor: { rgb: "0245A3" } };
                            ws[cellRef].s.font = { color: { rgb: "FFFFFF" }, bold: true };
                        }
                        // Data rows - apply colors based on content
                        else if (R >= 8 && R < ws_data.length - 3) {
                            // Center align for all data cells except TITLE OF ACTIVITY (column 2) and REMARKS (column 10)
                            if (C !== 2 && C !== 10) {
                                ws[cellRef].s.alignment = { horizontal: 'center', vertical: 'center' };
                            }
                            
                            // TYPE column (column 8) - Orange for External, Green for Internal
                            if (C === 8 && ws[cellRef].v) {
                                if (ws[cellRef].v === 'External') {
                                    ws[cellRef].s.fill = { fgColor: { rgb: "FF8C00" } };
                                    ws[cellRef].s.font = { color: { rgb: "FFFFFF" }, bold: true };
                                } else if (ws[cellRef].v === 'Internal') {
                                    ws[cellRef].s.fill = { fgColor: { rgb: "32CD32" } };
                                    ws[cellRef].s.font = { color: { rgb: "FFFFFF" }, bold: true };
                                }
                            }
                            
                            // OB column (column 7) - Yellow for Y, Red for N
                            if (C === 7 && ws[cellRef].v) {
                                if (ws[cellRef].v === 'Y') {
                                    ws[cellRef].s.fill = { fgColor: { rgb: "FFFF00" } };
                                    ws[cellRef].s.font = { color: { rgb: "000000" }, bold: true };
                                } else if (ws[cellRef].v === 'N') {
                                    ws[cellRef].s.fill = { fgColor: { rgb: "FF0000" } };
                                    ws[cellRef].s.font = { color: { rgb: "FFFFFF" }, bold: true };
                                }
                            }
                            
                            // PTR SUBMITTED column (column 9) - Yellow for YES, Red for NO
                            if (C === 9 && ws[cellRef].v) {
                                if (ws[cellRef].v === 'YES') {
                                    ws[cellRef].s.fill = { fgColor: { rgb: "FFFF00" } };
                                    ws[cellRef].s.font = { color: { rgb: "000000" }, bold: true };
                                } else if (ws[cellRef].v === 'NO') {
                                    ws[cellRef].s.fill = { fgColor: { rgb: "FF0000" } };
                                    ws[cellRef].s.font = { color: { rgb: "FFFFFF" }, bold: true };
                                }
                            }
                        }
                        // Signature rows - center aligned
                        else if (R >= ws_data.length - 3) {
                            ws[cellRef].s.alignment = { horizontal: 'center', vertical: 'center' };
                        }
                    }
                }
                
                // Create workbook and save
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Training Report');
                XLSX.writeFile(wb, 'training_report_' + new Date().toISOString().slice(0,10) + '.xlsx');
                
            } else { 
                alert('No data to export'); 
            }
        })
        .catch(error => { 
            console.error('Error:', error); 
            alert('Error exporting data'); 
        });
}
        
        function formatDate(dateString) { 
            if (!dateString) return ''; 
            var date = new Date(dateString); 
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }); 
        }
        
        function formatDateForExcel(dateString) {
            if (!dateString) return '';
            var date = new Date(dateString);
            var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            return months[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
        }
        
        function escapeHtml(text) { 
            if (!text) return ''; 
            var div = document.createElement('div'); 
            div.textContent = text; 
            return div.innerHTML; 
        }
        
        window.onclick = function(event) { 
            var modal = document.getElementById('reportModal'); 
            if (event.target == modal) closeReportModal(); 
        }
    </script>
</body>
</html>