<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query("SELECT * FROM employees WHERE user_id=$uid")->fetch_assoc();
$eid = $emp['employee_id'];

$payslips = $conn->query("SELECT * FROM payroll WHERE employee_id=$eid ORDER BY month DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payslips — EMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Payslips</h1>
            <p>Your payslip history</p>
        </div>
        <div class="page-body">
            <div class="card">
                <table>
                    <thead>
                        <tr><th>Period</th><th>Basic Salary</th><th>Net Salary</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($payslips)): ?>
                        <tr><td colspan="4"><div class="empty-state"><div class="empty-icon">💰</div><p>No payslips generated yet.</p></div></td></tr>
                    <?php else: foreach ($payslips as $p): ?>
                        <tr>
                            <td style="color:#6b7280;"><?= date('F Y', strtotime($p['month'])) ?></td>
                            <td>ZMW <?= number_format($p['basic_salary'], 2) ?></td>
                            <td><strong>ZMW <?= number_format($p['net_salary'], 2) ?></strong></td>
                            <td><a href="../admin/generate_payslip.php?id=<?= $p['payroll_id'] ?>" class="btn btn-outline btn-sm" target="_blank">⬇ Download</a></td>
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
