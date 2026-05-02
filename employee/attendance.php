<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query("SELECT * FROM employees WHERE user_id=$uid")->fetch_assoc();
$eid = $emp['employee_id'];

$msg = ''; $msg_type = '';

// ── Ensure daily_pay column exists ───────────────────────
$conn->query("ALTER TABLE attendance ADD COLUMN IF NOT EXISTS daily_pay DECIMAL(10,2) DEFAULT 0.00");

// ── Back-fill daily_pay for any existing rows where it is 0 but hours > 0 ─
// This handles seed data rows imported before the column existed
$backfill_rate = round((float)$emp['basic_salary'] / 22 / 8, 6);
$conn->query(
    "UPDATE attendance
     SET daily_pay = ROUND(total_hours * $backfill_rate, 2)
     WHERE employee_id = $eid
       AND total_hours > 0
       AND (daily_pay IS NULL OR daily_pay = 0)"
);

// ── CLOCK IN / CLOCK OUT ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $today  = date('Y-m-d');
    $now    = date('H:i:s');
    $today_att = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid AND date='$today'")->fetch_assoc();

    if ($action === 'clock_in') {
        if ($today_att) {
            $msg = 'You have already clocked in today.';
            $msg_type = 'warning';
        } else {
            $stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, clock_in_time) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $eid, $today, $now);
            $stmt->execute();
            $msg      = 'Clocked in at ' . date('h:i A', strtotime($now)) . '. Have a great day!';
            $msg_type = 'success';
        }

    } elseif ($action === 'clock_out') {
        if (!$today_att) {
            $msg = 'You have not clocked in yet today.';
            $msg_type = 'warning';
        } elseif ($today_att['clock_out_time']) {
            $msg = 'You have already clocked out today.';
            $msg_type = 'warning';
        } else {
            $in_ts    = strtotime($today_att['clock_in_time']);
            $out_ts   = strtotime($now);
            $hours    = round(($out_ts - $in_ts) / 3600, 2);

            $monthly_salary = (float) $emp['basic_salary'];
            $hourly_rate    = $monthly_salary / 22 / 8;
            $daily_pay      = round($hourly_rate * $hours, 2);

            $stmt = $conn->prepare(
                "UPDATE attendance
                 SET clock_out_time = ?, total_hours = ?, daily_pay = ?
                 WHERE employee_id = ? AND date = ?"
            );
            $stmt->bind_param("sddis", $now, $hours, $daily_pay, $eid, $today);
            $stmt->execute();

            $h_disp = floor($hours);
            $m_disp = round(($hours - $h_disp) * 60);
            $msg      = "Clocked out at " . date('h:i A', strtotime($now))
                      . " — {$h_disp}h {$m_disp}m worked"
                      . " — ZMW " . number_format($daily_pay, 2) . " earned today.";
            $msg_type = 'success';
        }
    }
}

// ── REFRESH STATE AFTER POST ──────────────────────────────
$today_att     = $conn->query("SELECT * FROM attendance WHERE employee_id=$eid AND date=CURDATE()")->fetch_assoc();
$can_clock_in  = !$today_att;
$can_clock_out = $today_att && !$today_att['clock_out_time'];

// ── STATS ─────────────────────────────────────────────────
$days_present = $conn->query(
    "SELECT COUNT(*) as c FROM attendance
     WHERE employee_id=$eid
       AND MONTH(date)=MONTH(CURDATE())
       AND YEAR(date)=YEAR(CURDATE())"
)->fetch_assoc()['c'];

$avg_hours = $conn->query(
    "SELECT AVG(total_hours) as avg FROM attendance
     WHERE employee_id=$eid AND total_hours > 0"
)->fetch_assoc()['avg'];

$month_earned = $conn->query(
    "SELECT COALESCE(SUM(daily_pay), 0) as total FROM attendance
     WHERE employee_id=$eid
       AND MONTH(date)=MONTH(CURDATE())
       AND YEAR(date)=YEAR(CURDATE())"
)->fetch_assoc()['total'];

// ── RECENT RECORDS ────────────────────────────────────────
$records = $conn->query(
    "SELECT * FROM attendance WHERE employee_id=$eid ORDER BY date DESC LIMIT 15"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .clock-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .clock-status-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            width: 100%;
            margin: 10px 0 16px;
        }
        .clock-status-item {
            background: rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 10px 14px;
            text-align: center;
        }
        .clock-status-label {
            font-size: 10px;
            color: rgba(255,255,255,0.45);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }
        .clock-status-value           { font-size: 15px; font-weight: 700; color: #fff; }
        .clock-status-value.green     { color: #34d399; }
        .clock-status-value.red       { color: #f87171; }

        .clock-done-box {
            width: 100%;
            background: rgba(52,211,153,0.12);
            border: 1px solid rgba(52,211,153,0.3);
            border-radius: 10px;
            padding: 14px 18px;
            text-align: center;
        }
        .clock-done-box .done-label  { font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 4px; }
        .clock-done-box .done-pay    { font-size: 22px; font-weight: 700; color: #34d399; }
        .clock-done-box .done-hours  { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px; }

        .live-hours-badge {
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 4px;
        }
        .live-hours-badge span { color: #fff; font-weight: 700; }

        td.pay-cell  { font-weight: 600; color: #10b981; }
    </style>
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
            <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;margin-bottom:20px;">

                <!-- ── CLOCK PANEL ── -->
                <div class="clock-panel">
                    <div class="clock-time" id="liveClock">--:--:--</div>
                    <div class="clock-date"><?= date('l, F j, Y') ?></div>

                    <?php if ($can_clock_in): ?>
                    <div class="live-hours-badge">No session active today</div>
                    <form method="POST" style="width:100%;">
                        <input type="hidden" name="action" value="clock_in">
                        <button type="submit" class="clock-btn clock-btn-in" style="width:100%;">
                            Clock In — Start your work day
                        </button>
                    </form>

                    <?php elseif ($can_clock_out): ?>
                    <div class="live-hours-badge">
                        Working since <?= date('h:i A', strtotime($today_att['clock_in_time'])) ?>
                        &nbsp;·&nbsp; <span id="liveHours">0h 0m</span>
                    </div>
                    <div class="clock-status-row">
                        <div class="clock-status-item">
                            <div class="clock-status-label">Clock In</div>
                            <div class="clock-status-value green">
                                <?= date('h:i A', strtotime($today_att['clock_in_time'])) ?>
                            </div>
                        </div>
                        <div class="clock-status-item">
                            <div class="clock-status-label">Est. Pay So Far</div>
                            <div class="clock-status-value green" id="livePay">ZMW 0.00</div>
                        </div>
                    </div>
                    <form method="POST" style="width:100%;">
                        <input type="hidden" name="action" value="clock_out">
                        <button type="submit" class="clock-btn clock-btn-out" style="width:100%;">
                            Clock Out — End your work day
                        </button>
                    </form>

                    <?php else: ?>
                    <?php
                        $h_done   = floor($today_att['total_hours']);
                        $m_done   = round(($today_att['total_hours'] - $h_done) * 60);
                        $pay_done = $today_att['daily_pay'] ?? 0;
                    ?>
                    <div class="clock-status-row">
                        <div class="clock-status-item">
                            <div class="clock-status-label">Clock In</div>
                            <div class="clock-status-value green">
                                <?= date('h:i A', strtotime($today_att['clock_in_time'])) ?>
                            </div>
                        </div>
                        <div class="clock-status-item">
                            <div class="clock-status-label">Clock Out</div>
                            <div class="clock-status-value red">
                                <?= date('h:i A', strtotime($today_att['clock_out_time'])) ?>
                            </div>
                        </div>
                    </div>
                    <div class="clock-done-box">
                        <div class="done-label">Today's Earnings</div>
                        <div class="done-pay">ZMW <?= number_format($pay_done, 2) ?></div>
                        <div class="done-hours"><?= $h_done ?>h <?= $m_done ?>m worked</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ── STAT CARDS ── -->
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-label">Days Present (This Month)</div>
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
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-label">Avg. Work Hours / Day</div>
                            <div class="stat-value"><?= round($avg_hours ?? 0, 1) ?> hrs</div>
                        </div>
                        <div class="stat-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-info">
                            <div class="stat-label">Earned This Month</div>
                            <div class="stat-value" style="font-size:18px;color:#10b981;">
                                ZMW <?= number_format($month_earned, 2) ?>
                            </div>
                        </div>
                        <div class="stat-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── RECENT ACTIVITY TABLE ── -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                    <span style="font-size:12px;color:#9ca3af;">Showing last 15 days</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Working Hours</th>
                            <th>Day Type</th>
                            <th>Pay Earned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                                            <line x1="16" y1="2" x2="16" y2="6"/>
                                            <line x1="8" y1="2" x2="8" y2="6"/>
                                            <line x1="3" y1="10" x2="21" y2="10"/>
                                        </svg>
                                    </div>
                                    <p>No attendance records yet.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: foreach ($records as $r):
                        $h     = (float)$r['total_hours'];
                        $h_int = floor($h);
                        $m_int = round(($h - $h_int) * 60);
                        $day_type = $h >= 7 ? 'Full Day' : ($h >= 4 ? 'Half Day' : ($h > 0 ? 'Short' : 'In Progress'));
                        $pay   = isset($r['daily_pay']) ? (float)$r['daily_pay'] : 0;

                        // Highlight today's row
                        $is_today = ($r['date'] === date('Y-m-d'));
                    ?>
                        <tr <?= $is_today ? 'style="background:#f5f7ff;"' : '' ?>>
                            <td>
                                <?= date('M j, Y', strtotime($r['date'])) ?>
                                <?php if ($is_today): ?>
                                <span style="font-size:10px;background:#e0e1ff;color:#5b5ef4;padding:2px 7px;border-radius:10px;margin-left:4px;font-weight:600;">TODAY</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['clock_in_time']  ? date('h:i A', strtotime($r['clock_in_time']))  : '—' ?></td>
                            <td>
                                <?php if ($r['clock_out_time']): ?>
                                    <?= date('h:i A', strtotime($r['clock_out_time'])) ?>
                                <?php else: ?>
                                    <span style="color:#f59e0b;font-weight:500;">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $h > 0 ? "{$h_int}h {$m_int}m" : '—' ?></td>
                            <td><span class="badge badge-info"><?= $day_type ?></span></td>
                            <td class="pay-cell">
                                <?= $pay > 0
                                    ? 'ZMW ' . number_format($pay, 2)
                                    : '<span style="color:#9ca3af;">—</span>' ?>
                            </td>
                            <td>
                                <?php if (!$r['clock_out_time']): ?>
                                    <span class="badge badge-warning">IN PROGRESS</span>
                                <?php else: ?>
                                    <span class="badge badge-success">PRESENT</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<script>
// ── Live clock ────────────────────────────────────────────
function updateClock() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    document.getElementById('liveClock').textContent =
        pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
}
updateClock();
setInterval(updateClock, 1000);

// ── Live hours + pay ticker (only while clocked in) ───────
<?php if ($can_clock_out): ?>
(function () {
    const clockInTime = new Date();
    const parts = '<?= $today_att['clock_in_time'] ?>'.split(':');
    clockInTime.setHours(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]), 0);

    const hourlyRate = <?= round((float)$emp['basic_salary'] / 22 / 8, 6) ?>;

    function tick() {
        const diffSec = Math.max(0, Math.floor((new Date() - clockInTime) / 1000));
        const hh  = Math.floor(diffSec / 3600);
        const mm  = Math.floor((diffSec % 3600) / 60);
        const pay = (hourlyRate * (diffSec / 3600)).toFixed(2);

        const hoursEl = document.getElementById('liveHours');
        const payEl   = document.getElementById('livePay');
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