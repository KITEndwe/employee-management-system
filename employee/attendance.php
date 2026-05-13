<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];

// ── Fetch employee with fresh salary ─────────────────────
$emp = $conn->query(
    "SELECT e.*, d.department_name
     FROM employees e
     JOIN departments d ON e.department_id = d.department_id
     WHERE e.user_id = $uid"
)->fetch_assoc();

if (!$emp) { session_destroy(); header('Location: ../index.php'); exit(); }

$eid          = (int)   $emp['employee_id'];
$basic_salary = (float) $emp['basic_salary'];

$msg = ''; $msg_type = '';

// ── Ensure daily_pay column exists ───────────────────────
$conn->query(
    "ALTER TABLE attendance
     ADD COLUMN IF NOT EXISTS daily_pay DECIMAL(10,2) NOT NULL DEFAULT 0.00"
);

// ── Back-fill any rows that have hours but no pay ────────
if ($basic_salary > 0) {
    $rate = round($basic_salary / 22 / 8, 10);
    $conn->query(
        "UPDATE attendance
         SET    daily_pay = ROUND(total_hours * $rate, 2)
         WHERE  employee_id = $eid
           AND  total_hours > 0
           AND  daily_pay   = 0"
    );
}

// ── CLOCK IN / CLOCK OUT ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $today     = date('Y-m-d');
    $now_time  = date('H:i:s');

    // Always re-read today's record fresh
    $today_att = $conn->query(
        "SELECT * FROM attendance
         WHERE employee_id = $eid AND date = '$today'"
    )->fetch_assoc();

    // ── CLOCK IN ─────────────────────────────────────────
    if ($action === 'clock_in') {
        if ($today_att) {
            $msg = 'You have already clocked in today.';
            $msg_type = 'warning';
        } else {
            $conn->query(
                "INSERT INTO attendance
                     (employee_id, date, clock_in_time, total_hours, daily_pay)
                 VALUES
                     ($eid, '$today', '$now_time', 0, 0)"
            );
            $msg      = 'Clocked in at ' . date('h:i A') . '. Have a great day!';
            $msg_type = 'success';
        }

    // ── CLOCK OUT ────────────────────────────────────────
    } elseif ($action === 'clock_out') {
        if (!$today_att) {
            $msg = 'You have not clocked in yet today.';
            $msg_type = 'warning';
        } elseif (!empty($today_att['clock_out_time'])) {
            $msg = 'You have already clocked out today.';
            $msg_type = 'warning';
        } else {
            // Calculate hours worked
            $in_secs   = strtotime($today_att['clock_in_time']);
            $out_secs  = strtotime($now_time);
            $diff_secs = max(0, $out_secs - $in_secs);
            $hours     = round($diff_secs / 3600, 4);

            // Calculate pay
            if ($basic_salary > 0) {
                $hourly_rate = $basic_salary / 22 / 8;
                $daily_pay   = round($hourly_rate * $hours, 2);
            } else {
                $hourly_rate = 0;
                $daily_pay   = 0;
            }

            // Use direct query — no bind_param to go wrong
            $safe_now   = $conn->real_escape_string($now_time);
            $safe_today = $conn->real_escape_string($today);

            $conn->query(
                "UPDATE attendance
                 SET    clock_out_time = '$safe_now',
                        total_hours    = $hours,
                        daily_pay      = $daily_pay
                 WHERE  employee_id    = $eid
                   AND  date           = '$safe_today'"
            );

            $h_disp = floor($hours);
            $m_disp = round(($hours - $h_disp) * 60);

            if ($daily_pay > 0) {
                $msg = "Clocked out at " . date('h:i A', $out_secs)
                     . " — {$h_disp}h {$m_disp}m worked"
                     . " — ZMW " . number_format($daily_pay, 2) . " earned today.";
            } else {
                $msg = "Clocked out at " . date('h:i A', $out_secs)
                     . " — {$h_disp}h {$m_disp}m worked."
                     . ($basic_salary <= 0 ? " (Salary not set — contact admin.)" : "");
            }
            $msg_type = 'success';
        }
    }
}

// ── Re-read state after any POST ─────────────────────────
$today_att     = $conn->query(
    "SELECT * FROM attendance WHERE employee_id = $eid AND date = CURDATE()"
)->fetch_assoc();
$can_clock_in  = !$today_att;
$can_clock_out = $today_att && empty($today_att['clock_out_time']);

// ── Stats ─────────────────────────────────────────────────
$days_present = (int) $conn->query(
    "SELECT COUNT(*) AS c FROM attendance
     WHERE employee_id = $eid
       AND MONTH(date) = MONTH(CURDATE())
       AND YEAR(date)  = YEAR(CURDATE())"
)->fetch_assoc()['c'];

$avg_row = $conn->query(
    "SELECT AVG(total_hours) AS avg FROM attendance
     WHERE employee_id = $eid AND total_hours > 0"
)->fetch_assoc();
$avg_hours = round((float)($avg_row['avg'] ?? 0), 1);

$month_earned = (float) $conn->query(
    "SELECT COALESCE(SUM(daily_pay), 0) AS total FROM attendance
     WHERE employee_id = $eid
       AND MONTH(date) = MONTH(CURDATE())
       AND YEAR(date)  = YEAR(CURDATE())"
)->fetch_assoc()['total'];

// ── Recent records ────────────────────────────────────────
$records = $conn->query(
    "SELECT * FROM attendance
     WHERE employee_id = $eid
     ORDER BY date DESC
     LIMIT 15"
)->fetch_all(MYSQLI_ASSOC);

// ── Hourly rate for JS live counter ──────────────────────
$hourly_rate_js = ($basic_salary > 0) ? ($basic_salary / 22 / 8) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="css/attendance.css">
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

            <?php if ($basic_salary <= 0): ?>
            <div class="no-salary-warn">
                ⚠️ <strong>Salary not configured.</strong>
                Your basic salary is missing. Clock-in/out will work but
                pay will show ZMW 0.00 until an administrator sets your salary.
            </div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;margin-bottom:20px;">

                <!-- CLOCK PANEL -->
                <div class="clock-panel">
                    <div class="clock-time" id="liveClock">--:--:--</div>
                    <div class="clock-date"><?= date('l, F j, Y') ?></div>

                    <?php if ($can_clock_in): ?>
                        <div class="live-badge">No session active today</div>
                        <form method="POST" style="width:100%;">
                            <input type="hidden" name="action" value="clock_in">
                            <button type="submit" class="clock-btn clock-btn-in" style="width:100%;">
                                Clock In — Start your work day
                            </button>
                        </form>

                    <?php elseif ($can_clock_out): ?>
                        <div class="live-badge">
                            Working since <?= date('h:i A', strtotime($today_att['clock_in_time'])) ?>
                            &nbsp;·&nbsp;<span id="liveHours">0h 0m</span>
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

                    <?php else:
                        $h_done   = floor((float)$today_att['total_hours']);
                        $m_done   = round(((float)$today_att['total_hours'] - $h_done) * 60);
                        $pay_done = (float)($today_att['daily_pay'] ?? 0);
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

                <!-- STAT CARDS -->
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
                            <div class="stat-value"><?= $avg_hours ?> hrs</div>
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

            <!-- RECENT ACTIVITY TABLE -->
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
                                    <div class="empty-icon">📅</div>
                                    <p>No attendance records yet. Clock in to get started.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else:
                        foreach ($records as $r):
                            $h        = (float) $r['total_hours'];
                            $h_int    = (int)   floor($h);
                            $m_int    = (int)   round(($h - $h_int) * 60);
                            $pay      = (float) $r['daily_pay'];
                            $is_today = ($r['date'] === date('Y-m-d'));
                            $clocked_out = !empty($r['clock_out_time']);

                            if (!$clocked_out)       $day_type = 'In Progress';
                            elseif ($h >= 7)         $day_type = 'Full Day';
                            elseif ($h >= 4)         $day_type = 'Half Day';
                            elseif ($h > 0)          $day_type = 'Short';
                            else                     $day_type = '—';
                    ?>
                        <tr <?= $is_today ? 'style="background:#f5f7ff;"' : '' ?>>
                            <td>
                                <?= date('M j, Y', strtotime($r['date'])) ?>
                                <?php if ($is_today): ?>
                                <span style="font-size:10px;background:#e0e1ff;color:#5b5ef4;
                                             padding:2px 7px;border-radius:10px;
                                             margin-left:4px;font-weight:600;">TODAY</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($r['clock_in_time'])
                                    ? date('h:i A', strtotime($r['clock_in_time']))
                                    : '—' ?>
                            </td>
                            <td>
                                <?php if ($clocked_out): ?>
                                    <?= date('h:i A', strtotime($r['clock_out_time'])) ?>
                                <?php else: ?>
                                    <span style="color:#f59e0b;font-weight:500;">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= ($h > 0 && $clocked_out) ? "{$h_int}h {$m_int}m" : '—' ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?= $day_type ?></span>
                            </td>
                            <td class="pay-cell">
                                <?= ($pay > 0 && $clocked_out)
                                    ? 'ZMW ' . number_format($pay, 2)
                                    : '<span style="color:#9ca3af;">—</span>' ?>
                            </td>
                            <td>
                                <?php if (!$clocked_out): ?>
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

        </div><!-- /page-body -->
    </div><!-- /main-content -->
</div><!-- /app-layout -->

<script>
// ── Live clock ────────────────────────────────────────────
(function () {
    function tick() {
        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        document.getElementById('liveClock').textContent =
            pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
})();

<?php if ($can_clock_out && $hourly_rate_js > 0): ?>
// ── Live hours + pay ticker ───────────────────────────────
(function () {
    // Parse clock-in time as today's Date object
    const parts = '<?= $today_att["clock_in_time"] ?>'.split(':');
    const clockIn = new Date();
    clockIn.setHours(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]), 0);

    const hourlyRate = <?= number_format($hourly_rate_js, 10, '.', '') ?>;

    function update() {
        const elapsed = Math.max(0, (Date.now() - clockIn.getTime()) / 1000);
        const hh  = Math.floor(elapsed / 3600);
        const mm  = Math.floor((elapsed % 3600) / 60);
        const pay = (hourlyRate * elapsed / 3600).toFixed(2);

        const hoursEl = document.getElementById('liveHours');
        const payEl   = document.getElementById('livePay');
        if (hoursEl) hoursEl.textContent = hh + 'h ' + mm + 'm';
        if (payEl)   payEl.textContent   = 'ZMW ' + parseFloat(pay).toLocaleString(
            'en-ZM', { minimumFractionDigits: 2 }
        );
    }
    update();
    setInterval(update, 1000);
})();
<?php endif; ?>
</script>
</body>
</html>