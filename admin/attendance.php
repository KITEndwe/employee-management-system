<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$date_filter = sanitize($conn, $_GET['date'] ?? date('Y-m-d'));
$emp_filter = (int)($_GET['emp'] ?? 0);

$sql = "SELECT a.*, e.full_name, d.department_name
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id
        WHERE a.date = '$date_filter'";
if ($emp_filter) $sql .= " AND a.employee_id = $emp_filter";
$sql .= " ORDER BY a.clock_in_time";
$records = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$employees = $conn->query("SELECT employee_id, full_name FROM employees WHERE is_active=1 ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

// Stats
$present = count($records);
$total_emp = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active=1")->fetch_assoc()['c'];
$avg_hours = $present > 0 ? round(array_sum(array_column($records, 'total_hours')) / $present, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Attendance Report</h1>
            <p>Track employee attendance records</p>
        </div>
        <div class="page-body">
            <!-- Filters -->
            <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;">
                <div class="form-group" style="margin:0;">
                    <label>Date</label>
                    <input type="date" id="dateFilter" class="form-control" value="<?= $date_filter ?>" onchange="applyFilter()">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Employee</label>
                    <select id="empFilter" class="form-control" onchange="applyFilter()">
                        <option value="0">All Employees</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['employee_id'] ?>" <?= $emp_filter==$e['employee_id']?'selected':'' ?>><?= htmlspecialchars($e['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div class="stat-info"><div class="stat-label">Present Today</div><div class="stat-value"><?= $present ?></div></div>
                    <div class="stat-icon">✅</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><div class="stat-label">Absent</div><div class="stat-value"><?= $total_emp - $present ?></div></div>
                    <div class="stat-icon">❌</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info"><div class="stat-label">Avg. Work Hours</div><div class="stat-value"><?= $avg_hours ?>h</div></div>
                    <div class="stat-icon">⏱️</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Attendance for <?= date('F j, Y', strtotime($date_filter)) ?></h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Working Hours</th>
                            <th>Day Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📭</div><p>No attendance records for this date.</p></div></td></tr>
                    <?php else: foreach ($records as $r): 
                        $hours = $r['total_hours'];
                        $day_type = $hours >= 7 ? 'Full Day' : ($hours >= 4 ? 'Half Day' : 'Short');
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($r['department_name']) ?></td>
                            <td><?= $r['clock_in_time'] ? date('h:i A', strtotime($r['clock_in_time'])) : '—' ?></td>
                            <td><?= $r['clock_out_time'] ? date('h:i A', strtotime($r['clock_out_time'])) : '—' ?></td>
                            <td><?= $r['clock_in_time'] ? floor($hours).'h '.round(($hours-floor($hours))*60).'m' : '—' ?></td>
                            <td><span class="badge badge-info"><?= $day_type ?></span></td>
                            <td><span class="badge badge-success">PRESENT</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
function applyFilter() {
    const date = document.getElementById('dateFilter').value;
    const emp = document.getElementById('empFilter').value;
    location.href = `attendance.php?date=${date}&emp=${emp}`;
}
</script>
</body>
</html>
