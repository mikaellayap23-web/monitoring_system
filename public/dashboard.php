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
if ($role === 'unit_head') {
    $stmt = $pdo->prepare("SELECT division, department, section, unit FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();
}

$where_clauses = [];
$params = [];

if ($role === 'employee') {
    $where_clauses[] = "user_id = ?";
    $params[] = $user_id;
} elseif ($role === 'unit_head') {
    if ($current_user) {
        if (!empty($current_user['division'])) {
            $where_clauses[] = "division = ?";
            $params[] = $current_user['division'];
        }
        if (!empty($current_user['department'])) {
            $where_clauses[] = "department = ?";
            $params[] = $current_user['department'];
        }
        if (!empty($current_user['section'])) {
            $where_clauses[] = "section = ?";
            $params[] = $current_user['section'];
        }
        if (!empty($current_user['unit'])) {
            $where_clauses[] = "unit = ?";
            $params[] = $current_user['unit'];
        }
    }
} else {
    $filter_division = $_GET['division'] ?? '';
    $filter_dept_section_unit = $_GET['dept_section_unit'] ?? '';
    $filter_employee = $_GET['employee'] ?? '';
    $filter_date_from = $_GET['date_from'] ?? '';
    $filter_date_to = $_GET['date_to'] ?? '';
    
    if (!empty($filter_division)) {
        $where_clauses[] = "division = ?";
        $params[] = $filter_division;
    }
    if (!empty($filter_dept_section_unit)) {
        $parts = explode(' / ', $filter_dept_section_unit);
        $filter_department = $parts[0] ?? '';
        $filter_section = $parts[1] ?? '';
        $filter_unit = $parts[2] ?? '';
        
        if (!empty($filter_department)) {
            $where_clauses[] = "department = ?";
            $params[] = $filter_department;
        }
        if (!empty($filter_section)) {
            $where_clauses[] = "section = ?";
            $params[] = $filter_section;
        }
        if (!empty($filter_unit)) {
            $where_clauses[] = "unit = ?";
            $params[] = $filter_unit;
        }
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
$deptSectionUnitOptions = [];
$employees = [];

if ($role === 'admin') {
    $divisions = $pdo->query("SELECT name FROM divisions ORDER BY name")->fetchAll();
    
    $allDepartments = $pdo->query("SELECT name FROM departments ORDER BY name")->fetchAll();
    foreach ($allDepartments as $dept) {
        $combined = $dept['name'] . ' / / ';
        $deptSectionUnitOptions[] = $combined;
    }
    
    $employees = $pdo->query("SELECT DISTINCT full_name as employee_name FROM users WHERE role != 'admin' AND full_name IS NOT NULL ORDER BY full_name")->fetchAll();
} elseif ($role === 'unit_head' && $current_user) {
    if (!empty($current_user['division'])) {
        $divisions = [[ 'name' => $current_user['division'] ]];
    }
    
    $combined = ($current_user['department'] ?? '') . ' / ' . ($current_user['section'] ?? '') . ' / ' . ($current_user['unit'] ?? '');
    if (trim($combined) !== ' / / ') {
        $deptSectionUnitOptions = [$combined];
    }
    
    $employeeStmt = $pdo->prepare("
        SELECT DISTINCT u.full_name as employee_name 
        FROM users u 
        WHERE u.division = ? 
        AND u.department = ? 
        AND u.section = ? 
        AND u.unit = ?
        AND u.role = 'employee'
        AND u.full_name IS NOT NULL 
        ORDER BY u.full_name
    ");
    $employeeStmt->execute([
        $current_user['division'], 
        $current_user['department'], 
        $current_user['section'], 
        $current_user['unit']
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

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$stmt->execute();
$total_employees = $stmt->fetch()['total'];

$attended_employees = 0;
$not_attended = 0;
$attended_percentage = 0;
$bar_labels = [];
$bar_attended = [];
$bar_not_attended = [];
$training_data = [];

if ($role !== 'employee') {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) as attended FROM trainings $where_sql");
    $stmt->execute($params);
    $attended_employees = $stmt->fetch()['attended'];
    $not_attended = $total_employees - $attended_employees;
    $attended_percentage = $total_employees > 0 ? round(($attended_employees / $total_employees) * 100) : 0;
    
    $bar_sql = "SELECT 
        COALESCE(u.department, 'Unassigned') as name,
        COUNT(DISTINCT u.id) as total_employees,
        COUNT(DISTINCT t.user_id) as attended
        FROM users u
        LEFT JOIN trainings t ON u.id = t.user_id
        WHERE u.role != 'admin'";
    
    if ($role === 'unit_head' && $current_user) {
        if (!empty($current_user['division'])) {
            $bar_sql .= " AND u.division = '" . addslashes($current_user['division']) . "'";
        }
        if (!empty($current_user['department'])) {
            $bar_sql .= " AND u.department = '" . addslashes($current_user['department']) . "'";
        }
        if (!empty($current_user['section'])) {
            $bar_sql .= " AND u.section = '" . addslashes($current_user['section']) . "'";
        }
        if (!empty($current_user['unit'])) {
            $bar_sql .= " AND u.unit = '" . addslashes($current_user['unit']) . "'";
        }
    }
    $bar_sql .= " GROUP BY COALESCE(u.department, 'Unassigned') ORDER BY name";
    $bar_data = $pdo->query($bar_sql)->fetchAll();
    
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
        t.section,
        t.unit,
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <?php if ($role !== 'employee'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    <style>
        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid rgba(210,193,182,0.3);
        }
        .filters-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #1B3C53;
            font-size: 12px;
        }
        .filter-group select, .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .filter-group button {
            background: #1B3C53;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .filter-group button:hover { background: #234C6A; }
        .clear-btn {
            background: #666;
            color: white;
            padding: 8px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
        }
        .date-range-inputs {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .date-range-inputs input { width: auto; flex: 1; }
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(210,193,182,0.3);
        }
        .chart-container h3 { margin-bottom: 15px; color: #1B3C53; font-size: 1rem; }
        canvas { max-height: 300px; }
        .data-table {
            background: white;
            border-radius: 12px;
            border: 1px solid rgba(210,193,182,0.3);
            overflow-x: auto;
            margin-top: 20px;
        }
        .data-table table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #1B3C53; color: white; padding: 12px; text-align: left; font-size: 12px; }
        .data-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 12px; }
        .data-table tr:hover { background: #f5f5f5; }
        .table-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid rgba(210,193,182,0.3); font-weight: 600; color: #1B3C53; }
        .ptr-badge { display: inline-block; padding: 4px 8px; border-radius: 20px; font-size: 10px; font-weight: 600; }
        .ptr-submitted { background: #d4edda; color: #155724; }
        .ptr-pending { background: #f8d7da; color: #721c24; }
        .ptr-na { background: #e2e3e5; color: #383d41; }
        .employee-welcome { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; border: 1px solid rgba(210,193,182,0.3); text-align: center; }
        .employee-welcome h2 { color: #1B3C53; margin-bottom: 10px; }
        .unit-info { background: #e8f4fd; padding: 10px 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1B3C53; }
        @media (max-width: 768px) { .charts-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                <div class="user-info"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($username) ?> | <strong><?= ucfirst($role) ?></strong></div>
            </div>
            
            <?php if ($role === 'unit_head' && $current_user): ?>
            <div class="unit-info">
                <i class="fas fa-building"></i> 
                Managing Unit: <strong><?= htmlspecialchars($current_user['division'] ?? '') ?></strong> / 
                <strong><?= htmlspecialchars($current_user['department'] ?? '') ?></strong> / 
                <strong><?= htmlspecialchars($current_user['section'] ?? '') ?></strong> / 
                <strong><?= htmlspecialchars($current_user['unit'] ?? '') ?></strong>
            </div>
            <?php endif; ?>
            
            <div class="dashboard-widgets">
                <div class="widget">
                    <i class="fas fa-users"></i>
                    <h3>TOTAL STAFF</h3>
                    <p><?= $total_employees ?></p>
                </div>
                <div class="widget">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>TOTAL TRAININGS</h3>
                    <p><?= $total_trainings ?></p>
                </div>
                <div class="widget">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>INTERNAL</h3>
                    <p><?= $internal_count ?></p>
                </div>
                <div class="widget">
                    <i class="fas fa-globe"></i>
                    <h3>EXTERNAL</h3>
                    <p><?= $external_count ?></p>
                </div>
                <div class="widget">
                    <i class="fas fa-clock"></i>
                    <h3>OB/OT COUNT</h3>
                    <p><?= $ob_ot_count ?></p>
                </div>
                <div class="widget">
                    <i class="fas fa-file-alt"></i>
                    <h3>PTR SUBMITTED</h3>
                    <p><?= $ptr_submitted ?> / <?= $ptr_total ?> (<?= $ptr_percentage ?>%)</p>
                </div>
            </div>
            
            <?php if ($role !== 'employee'): ?>
            <div class="filters-bar">
                <form method="get" class="filters-form">
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Division</label>
                        <select name="division">
                            <option value="">All Divisions</option>
                            <?php if ($role === 'admin'): ?>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= htmlspecialchars($d['name']) ?>" <?= ($_GET['division'] ?? '') == $d['name'] ? 'selected' : '' ?>><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            <?php elseif ($role === 'unit_head' && $current_user): ?>
                                <option value="<?= htmlspecialchars($current_user['division']) ?>" selected><?= htmlspecialchars($current_user['division']) ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-sitemap"></i> Department/Section/Unit</label>
                        <select name="dept_section_unit">
                            <option value="">All Departments/Sections/Units</option>
                            <?php foreach ($deptSectionUnitOptions as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>" <?= ($_GET['dept_section_unit'] ?? '') == $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-user-tag"></i> Employee Name</label>
                        <select name="employee">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $e): ?>
                                <option value="<?= htmlspecialchars($e['employee_name']) ?>" <?= ($_GET['employee'] ?? '') == $e['employee_name'] ? 'selected' : '' ?>><?= htmlspecialchars($e['employee_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Date Range</label>
                        <div class="date-range-inputs">
                            <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                            <span>to</span>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="filter-group">
                        <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
                    </div>
                    <?php if (!empty($_GET)): ?>
                    <div class="filter-group">
                        <a href="dashboard.php" class="clear-btn"><i class="fas fa-times"></i> Clear</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="charts-row">
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Training Attendance</h3>
                    <canvas id="pieChart"></canvas>
                    <p style="text-align:center; margin-top:10px;">
                        Attended: <?= $attended_employees ?> employees (<?= $attended_percentage ?>%)<br>
                        Not Attended: <?= $not_attended ?> employees
                    </p>
                </div>
                <div class="chart-container">
                    <h3><i class="fas fa-chart-bar"></i> Attendance by Department</h3>
                    <canvas id="barChart"></canvas>
                </div>
            </div>
            
            <div class="data-table">
                <div class="table-header">
                    <i class="fas fa-table"></i> Training Data (Filtered Results)
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Division</th>
                            <th>Department/Section/Unit</th>
                            <th>Employee Name</th>
                            <th>Type</th>
                            <th>Title of Activity</th>
                            <th>Date Range</th>
                            <th>OB/OT</th>
                            <th>PTR Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($training_data) > 0): ?>
                            <?php foreach ($training_data as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['division'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(($row['department'] ?? '') . ' / ' . ($row['section'] ?? '') . ' / ' . ($row['unit'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                    <td>
                                        <span style="padding:4px 8px; border-radius:12px; background:<?= $row['training_type'] == 'Internal' ? '#e3f2fd' : '#e8f5e9' ?>; font-size:11px;">
                                            <?= $row['training_type'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['title_of_activity']) ?></td>
                                    <td><?= date('M d, Y', strtotime($row['date_from'])) ?> - <?= date('M d, Y', strtotime($row['date_to'])) ?></td>
                                    <td><?= $row['ob_ot'] ?: '-' ?></td>
                                    <td>
                                        <?php if ($row['training_type'] == 'External'): ?>
                                            <span class="ptr-badge <?= $row['ptr_submitted'] ? 'ptr-submitted' : 'ptr-pending' ?>">
                                                <?= $row['ptr_submitted'] ? 'Submitted' : 'Pending' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="ptr-badge ptr-na">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px;">
                                    <i class="fas fa-inbox"></i> No training records found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
                const pieCtx = document.getElementById('pieChart').getContext('2d');
                new Chart(pieCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Attended Training', 'Not Attended'],
                        datasets: [{
                            data: [<?= $attended_employees ?>, <?= $not_attended ?>],
                            backgroundColor: ['#234C6A', '#D2C1B6'],
                            borderColor: ['#1B3C53', '#456882'],
                            borderWidth: 1
                        }]
                    },
                    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
                });
                
                const barCtx = document.getElementById('barChart').getContext('2d');
                new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($bar_labels) ?>,
                        datasets: [
                            { label: 'Attended Training', data: <?= json_encode($bar_attended) ?>, backgroundColor: '#234C6A', borderRadius: 4 },
                            { label: 'Not Attended', data: <?= json_encode($bar_not_attended) ?>, backgroundColor: '#D2C1B6', borderRadius: 4 }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Employees' } }, x: { title: { display: true, text: 'Department' } } },
                        plugins: { legend: { position: 'top' } }
                    }
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
</body>
</html>