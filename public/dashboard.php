<?php
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role = $_SESSION['role'];

$filter_division = $_GET['division'] ?? '';
$filter_department = $_GET['department'] ?? '';
$filter_section = $_GET['section'] ?? '';
$filter_unit = $_GET['unit'] ?? '';

$where_clauses = [];
$params = [];

if (!empty($filter_division)) {
    $where_clauses[] = "division = ?";
    $params[] = $filter_division;
}
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

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";
$filter_sql = !empty($where_clauses) ? "AND " . implode(" AND ", $where_clauses) : "";

$divisions = $pdo->query("SELECT DISTINCT division FROM users WHERE division IS NOT NULL AND division != '' ORDER BY division")->fetchAll();
$departments = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll();
$sections = $pdo->query("SELECT DISTINCT section FROM users WHERE section IS NOT NULL AND section != '' ORDER BY section")->fetchAll();
$units = $pdo->query("SELECT DISTINCT unit FROM users WHERE unit IS NOT NULL AND unit != '' ORDER BY unit")->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM trainings $where_sql");
$stmt->execute($params);
$total_trainings = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT training_type, COUNT(*) as count FROM trainings $where_sql GROUP BY training_type");
$stmt->execute($params);
$type_counts = $stmt->fetchAll();
$internal_count = 0;
$external_count = 0;
foreach ($type_counts as $tc) {
    if ($tc['training_type'] == 'Internal') $internal_count = $tc['count'];
    if ($tc['training_type'] == 'External') $external_count = $tc['count'];
}

$ob_ot_params = $params;
$ob_ot_sql = "SELECT COUNT(*) as total FROM trainings $where_sql";
if (!empty($where_clauses)) {
    $ob_ot_sql .= " AND ob_ot IS NOT NULL AND ob_ot != ''";
} else {
    $ob_ot_sql .= " WHERE ob_ot IS NOT NULL AND ob_ot != ''";
}
$stmt = $pdo->prepare($ob_ot_sql);
$stmt->execute($ob_ot_params);
$ob_ot_count = $stmt->fetch()['total'];

$ptr_params = $params;
$ptr_sql = "SELECT COUNT(*) as total, SUM(ptr_submitted) as submitted FROM trainings $where_sql";
if (!empty($where_clauses)) {
    $ptr_sql .= " AND training_type = 'External'";
} else {
    $ptr_sql .= " WHERE training_type = 'External'";
}
$stmt = $pdo->prepare($ptr_sql);
$stmt->execute($ptr_params);
$ptr_data = $stmt->fetch();
$ptr_total = $ptr_data['total'] ?? 0;
$ptr_submitted = $ptr_data['submitted'] ?? 0;
$ptr_percentage = $ptr_total > 0 ? round(($ptr_submitted / $ptr_total) * 100) : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as total_employees FROM users WHERE role != 'admin'");
$stmt->execute();
$total_employees = $stmt->fetch()['total_employees'];

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
if (!empty($filter_division)) {
    $bar_sql .= " AND u.division = '" . addslashes($filter_division) . "'";
}
if (!empty($filter_department)) {
    $bar_sql .= " AND u.department = '" . addslashes($filter_department) . "'";
}
if (!empty($filter_section)) {
    $bar_sql .= " AND u.section = '" . addslashes($filter_section) . "'";
}
if (!empty($filter_unit)) {
    $bar_sql .= " AND u.unit = '" . addslashes($filter_unit) . "'";
}
$bar_sql .= " GROUP BY COALESCE(u.department, 'Unassigned') ORDER BY name";
$bar_data = $pdo->query($bar_sql)->fetchAll();

$bar_labels = [];
$bar_attended = [];
$bar_not_attended = [];
foreach ($bar_data as $row) {
    $bar_labels[] = $row['name'];
    $bar_attended[] = $row['attended'];
    $bar_not_attended[] = $row['total_employees'] - $row['attended'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .filter-group button:hover {
            background: #234C6A;
        }
        .clear-filter {
            background: #666;
        }
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
        .chart-container h3 {
            margin-bottom: 15px;
            color: #1B3C53;
            font-size: 1rem;
        }
        canvas {
            max-height: 300px;
        }
        @media (max-width: 768px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
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
            
            <div class="dashboard-widgets">
                <div class="widget">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>TOTAL TRAININGS</h3>
                    <p><?= $total_trainings ?></p>
                </div>
                <div class="widget">
                    <i class="fas fa-chart-pie"></i>
                    <h3>INT / EXT</h3>
                    <p>Int: <?= $internal_count ?> | Ext: <?= $external_count ?></p>
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
            
            <div class="filters-bar">
                <form method="get" class="filters-form">
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Division</label>
                        <select name="division">
                            <option value="">All Divisions</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?= htmlspecialchars($d['division']) ?>" <?= $filter_division == $d['division'] ? 'selected' : '' ?>><?= htmlspecialchars($d['division']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-building"></i> Department</label>
                        <select name="department">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= htmlspecialchars($d['department']) ?>" <?= $filter_department == $d['department'] ? 'selected' : '' ?>><?= htmlspecialchars($d['department']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-layer-group"></i> Section</label>
                        <select name="section">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $s): ?>
                                <option value="<?= htmlspecialchars($s['section']) ?>" <?= $filter_section == $s['section'] ? 'selected' : '' ?>><?= htmlspecialchars($s['section']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-users"></i> Unit</label>
                        <select name="unit">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= htmlspecialchars($u['unit']) ?>" <?= $filter_unit == $u['unit'] ? 'selected' : '' ?>><?= htmlspecialchars($u['unit']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit"><i class="fas fa-filter"></i> Apply Filters</button>
                    </div>
                    <div class="filter-group">
                        <a href="dashboard.php" style="background:#666; color:white; padding:8px 20px; border-radius:6px; text-decoration:none; display:inline-block;"><i class="fas fa-times"></i> Clear</a>
                    </div>
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
        </div>
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
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        const barCtx = document.getElementById('barChart').getContext('2d');
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($bar_labels) ?>,
                datasets: [
                    {
                        label: 'Attended Training',
                        data: <?= json_encode($bar_attended) ?>,
                        backgroundColor: '#234C6A',
                        borderRadius: 4
                    },
                    {
                        label: 'Not Attended',
                        data: <?= json_encode($bar_not_attended) ?>,
                        backgroundColor: '#D2C1B6',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Employees'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Department'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>
</body>
</html>