<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query(
    "SELECT e.*, d.department_name
     FROM employees e
     JOIN departments d ON e.department_id = d.department_id
     WHERE e.user_id = $uid"
)->fetch_assoc();
$eid = $emp['employee_id'];

// Stats
$days_present   = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE employee_id=$eid AND MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())")->fetch_assoc()['c'];
$pending_leaves = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id=$eid AND status='pending'")->fetch_assoc()['c'];
$latest_payslip = $conn->query("SELECT net_salary FROM payroll WHERE employee_id=$eid ORDER BY month DESC LIMIT 1")->fetch_assoc();

// Ensure daily_pay column exists, then sum earnings this month
$conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS daily_pay DECIMAL(10,2) DEFAULT 0.00");
$month_earned = $conn->query(
    "SELECT COALESCE(SUM(daily_pay), 0) as total
     FROM attendance
     WHERE employee_id = $eid
       AND MONTH(date) = MONTH(CURDATE())
       AND YEAR(date)  = YEAR(CURDATE())"
)->fetch_assoc()['total'];

// Today's clock status
$today_att = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid AND date=CURDATE()")->fetch_assoc();
$is_clocked_in  = $today_att && !$today_att['clock_out_time'];
$is_clocked_out = $today_att &&  $today_att['clock_out_time'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .stat-value.green { color: #10b981; }
        .today-card {
            background: #fff;
            border: 1px solid #e8eaf0;
            border-radius: 14px;
            max-width: 520px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .today-card-head {
            padding: 16px 22px;
            border-bottom: 1px solid #f0f0f8;
            font-family: 'Sora', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #1e2340;
        }
        .today-card-body {
            padding: 20px 22px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 16px;
            text-align: center;
        }
        .today-item-label {
            font-size: 11px;
            color: #9ca3af;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .today-item-value {
            font-family: 'Sora', sans-serif;
            font-weight: 700;
            font-size: 16px;
        }
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(16,185,129,0.1);
            color: #059669;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
            vertical-align: middle;
        }
        .live-dot {
            width: 6px; height: 6px;
            background: #10b981;
            border-radius: 50%;
            animation: blink 1.2s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.3; }
        }
    </style>
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

            <!-- ── STAT CARDS ── -->
            <div class="stats-grid">
                <!-- Days Present -->
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Days Present</div>
                        <div class="stat-value"><?= $days_present ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                </div>

                <!-- Pending Leaves -->
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Pending Leaves</div>
                        <div class="stat-value"><?= $pending_leaves ?></div>
                    </div>
                    <div class="stat-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                </div>

                <!-- Latest Payslip -->
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Latest Payslip</div>
                        <div class="stat-value" style="font-size:18px;">
                            <?= $latest_payslip ? 'ZMW ' . number_format($latest_payslip['net_salary'], 0) : '—' ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                </div>

                <!-- Earned This Month -->
                <div class="stat-card">
                    <div class="stat-info">
                        <div class="stat-label">Earned This Month</div>
                        <div class="stat-value green" style="font-size:18px;">
                            ZMW <?= number_format($month_earned, 2) ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- ── QUICK ACTIONS ── -->
            <div style="display:flex;gap:12px;margin-bottom:24px;">
                <a href="attendance.php" class="btn btn-primary">
                    <?php if ($is_clocked_in): ?>
                        Clock Out →
                    <?php elseif ($is_clocked_out): ?>
                        View Attendance →
                    <?php else: ?>
                        Clock In →
                    <?php endif; ?>
                </a>
                <a href="leave.php" class="btn btn-outline">Apply for Leave</a>
            </div>

            <!-- ── TODAY'S ATTENDANCE SUMMARY ── -->
            <?php if ($today_att): ?>
            <div class="today-card">
                <div class="today-card-head">
                    Today's Attendance
                    <?php if ($is_clocked_in): ?>
                    <span class="live-badge"><span class="live-dot"></span> In Progress</span>
                    <?php endif; ?>
                </div>
                <div class="today-card-body">
                    <div>
                        <div class="today-item-label">Clock In</div>
                        <div class="today-item-value" style="color:#10b981;">
                            <?= $today_att['clock_in_time'] ? date('h:i A', strtotime($today_att['clock_in_time'])) : '—' ?>
                        </div>
                    </div>
                    <div>
                        <div class="today-item-label">Clock Out</div>
                        <div class="today-item-value" style="color:<?= $is_clocked_out ? '#ef4444' : '#9ca3af' ?>;">
                            <?= $is_clocked_out ? date('h:i A', strtotime($today_att['clock_out_time'])) : 'Pending' ?>
                        </div>
                    </div>
                    <div>
                        <div class="today-item-label">Hours</div>
                        <div class="today-item-value" style="color:#5b5ef4;" id="dashHours">
                            <?php if ($today_att['total_hours'] > 0):
                                $hh = floor($today_att['total_hours']);
                                $mm = round(($today_att['total_hours'] - $hh) * 60);
                                echo "{$hh}h {$mm}m";
                            else: ?>—<?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="today-item-label">Pay Today</div>
                        <div class="today-item-value" style="color:#10b981;" id="dashPay">
                            <?php
                                $pay = $today_att['daily_pay'] ?? 0;
                                echo $pay > 0 ? 'ZMW ' . number_format($pay, 2) : '—';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Leave Balance -->
            <div class="stat-card" style="max-width:260px;">
                <div class="stat-info">
                    <div class="stat-label">Annual Leave Balance</div>
                    <div class="stat-value"><?= $emp['annual_leave_balance'] ?> days</div>
                </div>
                <div class="stat-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
<?php if ($is_clocked_in): ?>
// Live hours + pay counter on the dashboard today card
(function () {
    const clockInTime = new Date();
    const parts = '<?= $today_att['clock_in_time'] ?>'.split(':');
    clockInTime.setHours(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]), 0);

    const hourlyRate = <?= (float)$emp['basic_salary'] ?> / 22 / 8;

    function tick() {
        const diffSec = Math.max(0, Math.floor((new Date() - clockInTime) / 1000));
        const hh  = Math.floor(diffSec / 3600);
        const mm  = Math.floor((diffSec % 3600) / 60);
        const pay = (hourlyRate * (diffSec / 3600)).toFixed(2);

        const hoursEl = document.getElementById('dashHours');
        const payEl   = document.getElementById('dashPay');
        if (hoursEl) hoursEl.textContent = hh + 'h ' + mm + 'm';
        if (payEl)   payEl.textContent   = 'ZMW ' + parseFloat(pay).toLocaleString('en-ZM', { minimumFractionDigits: 2 });
    }
    tick();
    setInterval(tick, 1000);
})();
<?php endif; ?>
</script>
</body>
</html>