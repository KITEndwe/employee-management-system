<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'generate') {
    $month = sanitize($conn, $_POST['month']);
    $month_date = $month . '-01';
    $emps = $conn->query("SELECT * FROM employees WHERE is_active=1")->fetch_all(MYSQLI_ASSOC);
    $generated = 0;

    foreach ($emps as $emp) {
        $eid = $emp['employee_id'];
        $salary = $emp['basic_salary'];
        $exists = $conn->query("SELECT payroll_id FROM payroll WHERE employee_id=$eid AND month='$month_date'")->num_rows;
        if ($exists) continue;

        // Calculate leave deduction for unpaid leaves that month
        $yr = date('Y', strtotime($month_date));
        $mo = date('m', strtotime($month_date));
        $leave_days = 0;
        $leaves = $conn->query("SELECT start_date, end_date FROM leave_requests WHERE employee_id=$eid AND status='approved' AND YEAR(start_date)=$yr AND MONTH(start_date)=$mo")->fetch_all(MYSQLI_ASSOC);
        foreach ($leaves as $l) {
            $leave_days += (strtotime($l['end_date']) - strtotime($l['start_date'])) / 86400 + 1;
        }

        $daily_rate = $salary / 22;
        $deduction = round($leave_days * $daily_rate, 2);
        $allowances = round($salary * 0.05, 2); // 5% allowance
        $net = $salary + $allowances - $deduction;

        $stmt = $conn->prepare("INSERT INTO payroll (employee_id, month, basic_salary, allowances, leave_deduction, net_salary) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdddd", $eid, $month_date, $salary, $allowances, $deduction, $net);
        $stmt->execute();
        $generated++;
    }
    $msg = "Payroll generated for $generated employee(s) for " . date('F Y', strtotime($month_date)) . ".";
    $msg_type = 'success';
}

$month_filter = sanitize($conn, $_GET['month'] ?? '');
$sql = "SELECT p.*, e.full_name, d.department_name
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id";
if ($month_filter) $sql .= " WHERE DATE_FORMAT(p.month,'%Y-%m') = '$month_filter'";
$sql .= " ORDER BY p.month DESC, e.full_name";
$payrolls = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Payslips</h1>
            <p>Generate and manage employee payslips</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Generate Payroll -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h3>Generate Monthly Payroll</h3></div>
                <div class="card-body">
                    <form method="POST" style="display:flex;gap:14px;align-items:flex-end;">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-group" style="margin:0;">
                            <label>Select Month</label>
                            <input type="month" name="month" class="form-control" required value="<?= date('Y-m') ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">+ Generate Payslip</button>
                    </form>
                </div>
            </div>

            <!-- Filter -->
            <div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px;">
                <div class="form-group" style="margin:0;">
                    <label>Filter by Month</label>
                    <input type="month" id="monthFilter" class="form-control" value="<?= $month_filter ?>" onchange="location.href='payroll.php?month='+this.value">
                </div>
                <?php if ($month_filter): ?>
                <a href="payroll.php" class="btn btn-outline btn-sm">Clear</a>
                <?php endif; ?>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Basic Salary</th>
                            <th>Allowances</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($payrolls)): ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">💰</div><p>No payroll records. Generate payroll above.</p></div></td></tr>
                    <?php else: foreach ($payrolls as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['full_name']) ?></strong><br>
                                <small style="color:#9ca3af;"><?= htmlspecialchars($p['department_name']) ?></small>
                            </td>
                            <td style="color:#6b7280;"><?= date('F Y', strtotime($p['month'])) ?></td>
                            <td>ZMW <?= number_format($p['basic_salary'], 2) ?></td>
                            <td style="color:#10b981;">+ZMW <?= number_format($p['allowances'], 2) ?></td>
                            <td style="color:#ef4444;">-ZMW <?= number_format($p['leave_deduction'], 2) ?></td>
                            <td><strong>ZMW <?= number_format($p['net_salary'], 2) ?></strong></td>
                            <td>
                                <a href="generate_payslip.php?id=<?= $p['payroll_id'] ?>" class="btn btn-outline btn-sm" target="_blank">⬇ Download</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
