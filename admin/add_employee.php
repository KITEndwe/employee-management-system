<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$success = '';
$error   = '';

// Load departments
$depts = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = sanitize($conn, $_POST['full_name']   ?? '');
    $email       = sanitize($conn, $_POST['email']       ?? '');
    $password    = $_POST['password'] ?? '';
    $dept_id     = (int)($_POST['department_id']         ?? 0);
    $position    = sanitize($conn, $_POST['position']    ?? '');
    $salary      = (float)($_POST['basic_salary']        ?? 0);
    $joining     = sanitize($conn, $_POST['joining_date']?? '');
    $leave_bal   = (float)($_POST['leave_balance']       ?? 12.0);

    if (!$full_name || !$email || !$password || !$dept_id || !$position || !$salary || !$joining) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $conn->begin_transaction();
        try {
            // Check email not already used
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) throw new Exception('Email address is already registered.');

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $s1 = $conn->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'employee')");
            $s1->bind_param("ss", $email, $hash);
            $s1->execute();
            $user_id = $conn->insert_id;

            $s2 = $conn->prepare(
                "INSERT INTO employees
                 (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $s2->bind_param("isiisds", $user_id, $full_name, $dept_id, $position, $salary, $joining, $leave_bal);
            $s2->execute();

            $conn->commit();
            $success = "Employee <strong>" . htmlspecialchars($full_name) . "</strong> added successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Employee — EMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:     #151c3b;
    --accent:   #5b5ef4;
    --accent-h: #4a4de0;
    --white:    #ffffff;
    --bg:       #f4f5fb;
    --border:   #e8eaf0;
    --text:     #1e2340;
    --muted:    #8892b4;
    --gray:     #6b7280;
    --sidebar-w: 228px;
  }

  html, body { height: 100%; font-family: 'Sora', sans-serif; background: var(--bg); color: var(--text); }

  /* ── LAYOUT ── */
  .layout { display: flex; height: 100vh; overflow: hidden; }

  /* ── SIDEBAR (same as dashboard) ── */
  .sidebar {
    width: var(--sidebar-w); background: var(--navy);
    display: flex; flex-direction: column; flex-shrink: 0;
    height: 100vh; position: fixed; left: 0; top: 0; z-index: 50;
  }
  .sb-brand {
    padding: 22px 20px 18px; display: flex; align-items: center; gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,.06);
  }
  .sb-brand-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(91,94,244,.25);
    display: flex; align-items: center; justify-content: center; font-size: 15px;
  }
  .sb-brand-text strong { display: block; font-size: 13px; font-weight: 700; color: #fff; line-height: 1.2; }
  .sb-brand-text span   { font-size: 10.5px; font-weight: 300; color: rgba(255,255,255,.4); }
  .sb-user { padding: 14px 18px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,.06); }
  .sb-avatar { width: 34px; height: 34px; border-radius: 8px; background: rgba(91,94,244,.3); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
  .sb-user-name { font-size: 12.5px; font-weight: 600; color: #fff; display: block; }
  .sb-user-role { font-size: 10.5px; font-weight: 300; color: rgba(255,255,255,.4); }
  .sb-nav { flex: 1; padding: 14px 10px; overflow-y: auto; }
  .sb-nav-label { font-size: 9.5px; font-weight: 600; color: rgba(255,255,255,.28); letter-spacing: 1.2px; text-transform: uppercase; padding: 10px 10px 6px; }
  .sb-link { display: flex; align-items: center; gap: 9px; padding: 10px 12px; border-radius: 9px; font-size: 13px; font-weight: 400; color: rgba(255,255,255,.48); text-decoration: none; margin-bottom: 2px; transition: background .2s, color .2s; }
  .sb-link:hover  { background: rgba(255,255,255,.06); color: rgba(255,255,255,.85); }
  .sb-link.active { background: var(--accent); color: #fff; font-weight: 500; }
  .sb-footer { padding: 14px 10px; border-top: 1px solid rgba(255,255,255,.06); }
  .sb-logout { display: flex; align-items: center; gap: 9px; padding: 10px 12px; border-radius: 9px; font-size: 13px; color: rgba(255,255,255,.35); text-decoration: none; transition: background .2s, color .2s; }
  .sb-logout:hover { background: rgba(239,68,68,.12); color: #f87171; }

  /* ── MAIN ── */
  .main { margin-left: var(--sidebar-w); flex: 1; overflow-y: auto; height: 100vh; }
  .page-head { padding: 32px 36px 0; }
  .page-head h1 { font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
  .page-head p  { font-size: 13px; font-weight: 300; color: var(--muted); margin-bottom: 28px; }
  .page-body { padding: 0 36px 48px; }

  /* ── FORM CARD ── */
  .form-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 16px;
    max-width: 760px;
    overflow: hidden;
  }

  .form-card-head {
    padding: 22px 28px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 14px;
  }
  .form-card-head .head-icon {
    width: 42px; height: 42px; border-radius: 11px;
    background: rgba(91,94,244,.08);
    display: flex; align-items: center; justify-content: center;
  }
  .form-card-head h2 { font-size: 15px; font-weight: 600; color: var(--text); margin-bottom: 2px; }
  .form-card-head p  { font-size: 12px; font-weight: 300; color: var(--muted); }

  .form-body { padding: 28px; }

  /* section divider */
  .section-label {
    font-size: 10px; font-weight: 700;
    color: var(--muted); text-transform: uppercase; letter-spacing: 1.1px;
    margin-bottom: 16px; margin-top: 24px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
  }
  .section-label:first-child { margin-top: 0; }

  /* grid */
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
  .full   { grid-column: 1 / -1; }

  /* field */
  .field { display: flex; flex-direction: column; }
  .field label {
    font-size: 12px; font-weight: 600; color: var(--gray);
    margin-bottom: 7px; letter-spacing: .1px;
  }
  .field label .req { color: var(--accent); margin-left: 2px; }
  .field input,
  .field select {
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 9px;
    font-family: 'Sora', sans-serif;
    font-size: 13px; font-weight: 300;
    color: var(--text);
    background: var(--white);
    outline: none;
    transition: border-color .2s, box-shadow .2s;
    appearance: none;
  }
  .field input::placeholder { color: var(--muted); }
  .field input:focus,
  .field select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(91,94,244,.1);
  }

  /* select arrow */
  .select-wrap { position: relative; }
  .select-wrap select { width: 100%; padding-right: 36px; cursor: pointer; }
  .select-wrap::after {
    content: '';
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    width: 0; height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid var(--muted);
    pointer-events: none;
  }

  /* pw toggle */
  .pw-wrap { position: relative; }
  .pw-wrap input { width: 100%; padding-right: 40px; }
  .pw-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: var(--muted);
    display: flex; align-items: center; transition: color .2s;
  }
  .pw-toggle:hover { color: var(--gray); }

  /* hint text */
  .field-hint { font-size: 11px; font-weight: 300; color: var(--muted); margin-top: 5px; }

  /* form footer */
  .form-footer {
    padding: 20px 28px;
    border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
  }

  .btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 22px; border-radius: 9px;
    font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600;
    cursor: pointer; text-decoration: none; border: none;
    transition: all .2s;
  }
  .btn-primary {
    background: var(--accent); color: var(--white);
  }
  .btn-primary:hover { background: var(--accent-h); box-shadow: 0 5px 18px rgba(91,94,244,.3); transform: translateY(-1px); }
  .btn-outline {
    background: transparent; color: var(--gray);
    border: 1.5px solid var(--border);
  }
  .btn-outline:hover { border-color: var(--accent); color: var(--accent); }

  /* alerts */
  .alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 13px 16px; border-radius: 10px;
    font-size: 13px; margin-bottom: 20px;
  }
  .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
  .alert-error   { background: #fff5f5; border: 1px solid #fecaca; color: #991b1b; }
</style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
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
          'leave.php'       => ['Leave',       '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'],
          'payroll.php'     => ['Payslips',    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'],
          'settings.php'    => ['Settings',    '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
        ];
        foreach ($links as $file => [$label, $icon]):
      ?>
      <a href="<?= $file ?>" class="sb-link <?= ($cur === $file || ($file === 'employees.php' && $cur === 'add_employee.php')) ? 'active' : '' ?>">
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

  <!-- MAIN -->
  <main class="main">
    <div class="page-head">
      <h1>Add Employee</h1>
      <p>Fill in the details below to register a new team member.</p>
    </div>

    <div class="page-body">

      <?php if ($success): ?>
      <div class="alert alert-success">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        <span><?= $success ?> <a href="employees.php" style="color:inherit;font-weight:600;">View all employees →</a></span>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
      <div class="alert alert-error">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <div class="form-card">
        <!-- Card header -->
        <div class="form-card-head">
          <div class="head-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div>
            <h2>Employee Information</h2>
            <p>Personal details, role assignment, and compensation</p>
          </div>
        </div>

        <!-- Form -->
        <form method="POST" action="" autocomplete="off">
          <div class="form-body">

            <!-- ── PERSONAL DETAILS ── -->
            <div class="section-label">Personal Details</div>
            <div class="grid-2">
              <div class="field">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="full_name"
                       placeholder="e.g. John Doe"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       required>
              </div>
              <div class="field">
                <label>Email Address <span class="req">*</span></label>
                <input type="email" name="email"
                       placeholder="john@company.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required>
              </div>
              <div class="field">
                <label>Password <span class="req">*</span></label>
                <div class="pw-wrap">
                  <input type="password" name="password" id="pwField"
                         placeholder="Min. 6 characters" required minlength="6">
                  <button type="button" class="pw-toggle" onclick="togglePw()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                      <circle cx="12" cy="12" r="3"/>
                    </svg>
                  </button>
                </div>
                <span class="field-hint">Employees will use this to sign in.</span>
              </div>
              <div class="field">
                <label>Joining Date <span class="req">*</span></label>
                <input type="date" name="joining_date"
                       value="<?= htmlspecialchars($_POST['joining_date'] ?? date('Y-m-d')) ?>"
                       required>
              </div>
            </div>

            <!-- ── ROLE & DEPARTMENT ── -->
            <div class="section-label">Role &amp; Department</div>
            <div class="grid-2">
              <div class="field">
                <label>Department <span class="req">*</span></label>
                <div class="select-wrap">
                  <select name="department_id" required>
                    <option value="" disabled <?= empty($_POST['department_id']) ? 'selected' : '' ?>>Select department</option>
                    <?php foreach ($depts as $d): ?>
                    <option value="<?= $d['department_id'] ?>"
                      <?= (($_POST['department_id'] ?? '') == $d['department_id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($d['department_name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="field">
                <label>Position / Job Title <span class="req">*</span></label>
                <input type="text" name="position"
                       placeholder="e.g. Software Developer"
                       value="<?= htmlspecialchars($_POST['position'] ?? '') ?>"
                       required>
              </div>
            </div>

            <!-- ── COMPENSATION ── -->
            <div class="section-label">Compensation &amp; Leave</div>
            <div class="grid-3">
              <div class="field">
                <label>Basic Salary (ZMW) <span class="req">*</span></label>
                <input type="number" name="basic_salary"
                       placeholder="e.g. 8000"
                       value="<?= htmlspecialchars($_POST['basic_salary'] ?? '') ?>"
                       min="0" step="0.01" required>
              </div>
              <div class="field">
                <label>Annual Leave Balance</label>
                <input type="number" name="leave_balance"
                       placeholder="12"
                       value="<?= htmlspecialchars($_POST['leave_balance'] ?? '12') ?>"
                       min="0" step="0.5">
                <span class="field-hint">Default is 12 days per Zambian labour law.</span>
              </div>
              <div class="field" style="justify-content:flex-end;padding-bottom:22px;">
                <!-- spacer / can add more fields here -->
              </div>
            </div>

          </div><!-- /form-body -->

          <div class="form-footer">
            <a href="employees.php" class="btn btn-outline">← Cancel</a>
            <div style="display:flex;gap:10px;">
              <button type="reset" class="btn btn-outline">Clear form</button>
              <button type="submit" class="btn btn-primary">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Save Employee
              </button>
            </div>
          </div>
        </form>
      </div>

    </div>
  </main>

</div>
<script>
function togglePw() {
  const f = document.getElementById('pwField');
  f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
