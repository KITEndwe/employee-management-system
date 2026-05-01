<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query("SELECT e.*, d.department_name FROM employees e JOIN departments d ON e.department_id=d.department_id WHERE e.user_id=$uid")->fetch_assoc();
$eid = $emp['employee_id'];

// Stats
$days_present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE employee_id=$eid AND MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())")->fetch_assoc()['c'];
$pending_leaves = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id=$eid AND status='pending'")->fetch_assoc()['c'];
$latest_payslip = $conn->query("SELECT net_salary FROM payroll WHERE employee_id=$eid ORDER BY month DESC LIMIT 1")->fetch_assoc();

// Today's clock status
$today_att = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid AND date=CURDATE()")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Welcome, <?= htmlspecialchars(explode(' ', $emp['full_name'])[0]) ?>!</h1>
            <p><?= htmlspecialchars($emp['position']) ?> &mdash; <?= htmlspecialchars($emp['department_name']) ?></p>
        </div>
        <div class="page-body">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Days Present</div>
                        <div class="stat-value"><?= $days_present ?></div>
                    </div>
                    <div class="stat-icon">📅</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Pending Leaves</div>
                        <div class="stat-value"><?= $pending_leaves ?></div>
                    </div>
                    <div class="stat-icon">📋</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Latest Payslip</div>
                        <div class="stat-value" style="font-size:20px;">ZMW <?= $latest_payslip ? number_format($latest_payslip['net_salary'], 0) : '—' ?></div>
                    </div>
                    <div class="stat-icon">💵</div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Leave Balance</div>
                        <div class="stat-value"><?= $emp['annual_leave_balance'] ?> days</div>
                    </div>
                    <div class="stat-icon">🏖️</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="display:flex;gap:12px;margin-bottom:28px;">
                <a href="attendance.php" class="btn btn-primary">Mark Attendance →</a>
                <a href="leave.php" class="btn btn-outline">Apply for Leave</a>
            </div>

            <!-- Today's Status -->
            <?php if ($today_att): ?>
            <div class="card" style="max-width:500px;">
                <div class="card-header"><h3>Today's Attendance</h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;text-align:center;">
                        <div>
                            <div style="font-size:12px;color:#9ca3af;margin-bottom:6px;">Clock In</div>
                            <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:18px;color:#10b981;">
                                <?= $today_att['clock_in_time'] ? date('h:i A', strtotime($today_att['clock_in_time'])) : '—' ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#9ca3af;margin-bottom:6px;">Clock Out</div>
                            <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:18px;color:<?= $today_att['clock_out_time'] ? '#ef4444' : '#9ca3af' ?>;">
                                <?= $today_att['clock_out_time'] ? date('h:i A', strtotime($today_att['clock_out_time'])) : 'Pending' ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size:12px;color:#9ca3af;margin-bottom:6px;">Hours</div>
                            <div style="font-family:'Sora',sans-serif;font-weight:700;font-size:18px;color:#5b5ef4;">
                                <?= $today_att['total_hours'] > 0 ? $today_att['total_hours'].'h' : '—' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
