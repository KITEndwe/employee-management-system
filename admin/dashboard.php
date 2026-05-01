<?php
session_start();
require_once '../database/config.php';
requireAdmin();

// Stats
$total_employees  = $conn->query("SELECT COUNT(*) as c FROM employees WHERE is_active=1")->fetch_assoc()['c'];
$total_departments = $conn->query("SELECT COUNT(*) as c FROM departments")->fetch_assoc()['c'];
$today_attendance = $conn->query("SELECT COUNT(*) as c FROM attendance WHERE date=CURDATE()")->fetch_assoc()['c'];
$pending_leaves   = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE status='pending'")->fetch_assoc()['c'];

// Recent leave requests (for table)
$leaves = $conn->query("
    SELECT lr.*, e.full_name
    FROM leave_requests lr
    JOIN employees e ON lr.employee_id = e.employee_id
    ORDER BY lr.requested_on DESC LIMIT 5
");

// Today's attendance (for table)
$att_today = $conn->query("
    SELECT a.*, e.full_name
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    WHERE a.date = CURDATE()
    ORDER BY a.clock_in_time DESC LIMIT 5
");
$att_rows = $att_today->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — EMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin_dashboard.css">
</head>
<body>
<div class="layout">

  <!-- ── SIDEBAR ── -->
  <aside class="sidebar">
    <div class="sb-brand">
      <div class="sb-brand-icon">📊</div>
      <div class="sb-brand-text">
        <strong>Employee MS</strong>
        <span>Management System</span>
      </div>
    </div>

    <?php
      $initials = '';
      foreach (explode(' ', $_SESSION['full_name']) as $w) $initials .= strtoupper($w[0]);
      $initials = substr($initials, 0, 2);
    ?>
    <div class="sb-user">
      <div class="sb-avatar"><?= $initials ?></div>
      <div>
        <span class="sb-user-name"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
        <span class="sb-user-role">Administrator</span>
      </div>
    </div>

    <nav class="sb-nav">
      <div class="sb-nav-label">Navigation</div>

      <?php
        $cur = basename($_SERVER['PHP_SELF']);
        $links = [
          'dashboard.php'   => ['Dashboard',   '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>'],
          'employees.php'   => ['Employees',   '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
          'departments.php' => ['Departments', '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>'],
          'attendance.php'  => ['Attendance',  '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'],
          'leave.php'       => ['Leave',       '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
          'payroll.php'     => ['Payslips',    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'],
          'settings.php'    => ['Settings',    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
        ];
        foreach ($links as $file => [$label, $icon]):
      ?>
      <a href="<?= $file ?>" class="sb-link <?= $cur === $file ? 'active' : '' ?>">
        <?= $icon ?> <?= $label ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="sb-footer">
      <a href="../logout.php" class="sb-logout">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Log out
      </a>
    </div>
  </aside>

  <!-- ── MAIN ── -->
  <main class="main">
    <div class="page-head">
      <h1>Dashboard</h1>
      <p>Welcome back, Admin — here's your overview</p>
    </div>

    <div class="page-body">

      <!-- STAT CARDS -->
      <div class="stats">
        <!-- Total Employees -->
        <div class="stat-card">
          <div>
            <div class="stat-label">Total Employees</div>
            <div class="stat-val"><?= $total_employees ?></div>
          </div>
          <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
        </div>

        <!-- Departments -->
        <div class="stat-card">
          <div>
            <div class="stat-label">Departments</div>
            <div class="stat-val"><?= $total_departments ?></div>
          </div>
          <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
        </div>

        <!-- Today's Attendance -->
        <div class="stat-card">
          <div>
            <div class="stat-label">Today's Attendance</div>
            <div class="stat-val"><?= $today_attendance ?></div>
          </div>
          <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
          </div>
        </div>

        <!-- Pending Leaves -->
        <div class="stat-card">
          <div>
            <div class="stat-label">Pending Leaves</div>
            <div class="stat-val"><?= $pending_leaves ?></div>
          </div>
          <div class="stat-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="16" y1="13" x2="8" y2="13"/>
              <line x1="16" y1="17" x2="8" y2="17"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- TABLES ROW -->
      <div class="grid-2">

        <!-- Recent Leave Requests -->
        <div class="card">
          <div class="card-head">
            <h3>Recent Leave Requests</h3>
            <a href="leave.php" class="view-all">View all →</a>
          </div>
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Type</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $has = false;
              while ($l = $leaves->fetch_assoc()):
                $has = true;
                $badge = $l['status'] === 'approved' ? 'badge-green' : ($l['status'] === 'rejected' ? 'badge-red' : 'badge-yellow');
            ?>
              <tr>
                <td><?= htmlspecialchars($l['full_name']) ?></td>
                <td><span class="badge badge-blue"><?= strtoupper($l['leave_type']) ?></span></td>
                <td><span class="badge <?= $badge ?>"><?= strtoupper($l['status']) ?></span></td>
              </tr>
            <?php endwhile; ?>
            <?php if (!$has): ?>
              <tr><td colspan="3"><div class="empty-state">No leave requests yet.</div></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Today's Attendance -->
        <div class="card">
          <div class="card-head">
            <h3>Today's Attendance</h3>
            <a href="attendance.php" class="view-all">View all →</a>
          </div>
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Clock In</th>
                <th>Hours</th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($att_rows)): ?>
              <tr><td colspan="3"><div class="empty-state">No attendance recorded today.</div></td></tr>
            <?php else: foreach ($att_rows as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a['full_name']) ?></td>
                <td><?= $a['clock_in_time'] ? date('h:i A', strtotime($a['clock_in_time'])) : '—' ?></td>
                <td><?= $a['total_hours'] > 0 ? $a['total_hours'].'h' : '—' ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /grid-2 -->

    </div><!-- /page-body -->
  </main>

</div>
</body>
</html>
