<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query("SELECT * FROM employees WHERE user_id=$uid")->fetch_assoc();
$eid = $emp['employee_id'];

$msg = ''; $msg_type = '';

// Clock in / Clock out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $today_att = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid AND date='$today'")->fetch_assoc();

    if ($action === 'clock_in') {
        if ($today_att) {
            $msg = 'You have already clocked in today.'; $msg_type = 'warning';
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, clock_in_time) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $eid, $today, $now);
            $stmt->execute();
            $msg = 'Clocked in at ' . date('h:i A', strtotime($now)); $msg_type = 'success';
        }
    } elseif ($action === 'clock_out') {
        if (!$today_att || $today_att['clock_out_time']) {
            $msg = 'You have not clocked in or already clocked out.'; $msg_type = 'warning';
        } else {
            $in = strtotime($today_att['clock_in_time']);
            $out = strtotime($now);
            $hours = round(($out - $in) / 3600, 2);
            $stmt = $conn->prepare("UPDATE attendance SET clock_out_time=?, total_hours=? WHERE employee_id=? AND date=?");
            $stmt->bind_param("sdis", $now, $hours, $eid, $today);
            $stmt->execute();
            $msg = 'Clocked out at ' . date('h:i A', strtotime($now)) . ' — ' . $hours . ' hours worked.'; $msg_type = 'success';
        }
    }
}

// Today's status
$today_att = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid AND date=CURDATE()")->fetch_assoc();

// Stats
$days_present = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE employee_id=$eid AND MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())")->fetch_assoc()['c'];
$avg_hours = $conn->query("SELECT AVG(total_hours) as avg FROM attendance WHERE employee_id=$eid AND total_hours>0")->fetch_assoc()['avg'];

// Recent records
$records = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid ORDER BY date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$can_clock_in = !$today_att;
$can_clock_out = $today_att && !$today_att['clock_out_time'];
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
            <h1>Attendance</h1>
            <p>Track your work hours and daily check-ins</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;margin-bottom:20px;">
                <!-- Clock Panel -->
                <div class="clock-panel">
                    <div class="clock-time" id="liveClock">--:--:--</div>
                    <div class="clock-date" id="liveDate"><?= date('l, F j, Y') ?></div>

                    <?php if ($today_att && $today_att['clock_in_time']): ?>
                    <div style="margin-bottom:16px;font-size:13px;color:rgba(255,255,255,0.7);">
                        Clocked in at <?= date('h:i A', strtotime($today_att['clock_in_time'])) ?>
                        <?php if ($today_att['clock_out_time']): ?>
                        <br>Clocked out at <?= date('h:i A', strtotime($today_att['clock_out_time'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?php if ($can_clock_in): ?>
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" class="clock-btn clock-btn-in">Clock In — Start your work day</button>
                        <?php elseif ($can_clock_out): ?>
                        <input type="hidden" name="action" value="clock_out">
                        <button type="submit" class="clock-btn clock-btn-out">Clock Out — End your work day</button>
                        <?php else: ?>
                        <div style="background:rgba(255,255,255,0.1);padding:12px 20px;border-radius:10px;font-size:13px;color:rgba(255,255,255,0.7);">
                            ✅ Attendance marked for today
                        </div>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Stats -->
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div class="stat-card">
                        <div class="stat-info"><div class="stat-label">Days Present (This Month)</div><div class="stat-value"><?= $days_present ?></div></div>
                        <div class="stat-icon">✅</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info"><div class="stat-label">Avg. Work Hours</div><div class="stat-value"><?= round($avg_hours ?? 0, 1) ?> hrs</div></div>
                        <div class="stat-icon">⏱️</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info"><div class="stat-label">Leave Balance</div><div class="stat-value"><?= $emp['annual_leave_balance'] ?> days</div></div>
                        <div class="stat-icon">🏖️</div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header"><h3>Recent Activity</h3></div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Working Hours</th>
                            <th>Day Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📭</div><p>No attendance records yet.</p></div></td></tr>
                    <?php else: foreach ($records as $r):
                        $h = $r['total_hours'];
                        $day_type = $h >= 7 ? 'Full Day' : ($h >= 4 ? 'Half Day' : ($h > 0 ? 'Short' : 'In Progress'));
                    ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($r['date'])) ?></td>
                            <td><?= $r['clock_in_time'] ? date('h:i A', strtotime($r['clock_in_time'])) : '—' ?></td>
                            <td><?= $r['clock_out_time'] ? date('h:i A', strtotime($r['clock_out_time'])) : '—' ?></td>
                            <td><?= $h > 0 ? floor($h).'h '.round(($h-floor($h))*60).'m' : '—' ?></td>
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
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2,'0');
    const m = String(now.getMinutes()).padStart(2,'0');
    const s = String(now.getSeconds()).padStart(2,'0');
    document.getElementById('liveClock').textContent = h+':'+m+':'+s;
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
