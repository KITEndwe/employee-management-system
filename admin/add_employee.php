<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$success = '';
$error   = '';

$depts = $conn->query("SELECT * FROM departments ORDER BY department_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name  = sanitize($conn, $_POST['full_name']    ?? '');
    $email      = sanitize($conn, $_POST['email']        ?? '');
    $password   =               ($_POST['password']      ?? '');
    $dept_id    = (int)         ($_POST['department_id'] ?? 0);
    $position   = sanitize($conn, $_POST['position']     ?? '');
    $salary     = (float)       ($_POST['basic_salary']  ?? 0);
    $joining    = sanitize($conn, $_POST['joining_date'] ?? '');
    $leave_bal  = (float)       ($_POST['leave_balance'] ?? 12.0);

    // ── Validation ────────────────────────────────────────
    if (!$full_name || !$email || !$password || !$dept_id || !$position || !$joining) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($salary <= 0) {
        $error = 'Basic salary must be greater than zero.';
    } else {
        $conn->begin_transaction();
        try {
            // Check email not already taken
            $chk = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $chk->bind_param("s", $email);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                throw new Exception('This email address is already registered.');
            }

            // 1. Create user account
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $s1   = $conn->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'employee')");
            $s1->bind_param("ss", $email, $hash);
            $s1->execute();
            $user_id = (int)$conn->insert_id;

            // 2. Create employee record
            // Type string: i=INT  s=VARCHAR  i=INT  s=VARCHAR  d=DECIMAL  s=DATE  d=DECIMAL
            //              user_id full_name dept_id position  salary     joining  leave_bal
            $s2 = $conn->prepare(
                "INSERT INTO employees
                    (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $s2->bind_param("isisdsd",
                $user_id,   // i  user_id      INT
                $full_name, // s  full_name     VARCHAR
                $dept_id,   // i  department_id INT
                $position,  // s  position      VARCHAR
                $salary,    // d  basic_salary  DECIMAL(10,2)
                $joining,   // s  joining_date  DATE
                $leave_bal  // d  leave_balance DECIMAL(5,1)
            );
            $s2->execute();

            // Verify salary was stored correctly
            $new_eid    = (int)$conn->insert_id;
            $saved      = $conn->query("SELECT basic_salary FROM employees WHERE employee_id = $new_eid")->fetch_assoc();
            $saved_sal  = (float)$saved['basic_salary'];

            if ($saved_sal <= 0) {
                throw new Exception("Salary was not saved correctly (stored as $saved_sal). Please try again.");
            }

            $conn->commit();
            $success = "Employee <strong>" . htmlspecialchars($full_name) . "</strong> added successfully."
                     . " Salary stored: ZMW " . number_format($saved_sal, 2) . ".";

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
<link rel="stylesheet" href="assets/add_employee.css">
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sb-brand">
      <div class="sb-brand-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      </div>
      <div class="sb-brand-text"><strong>Employee MS</strong><span>Management System</span></div>
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
        <div class="form-card-head">
          <div class="head-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#5b5ef4" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
            </svg>
          </div>
          <div>
            <h2>Employee Information</h2>
            <p>Personal details, role assignment, and compensation</p>
          </div>
        </div>

        <form method="POST" action="" autocomplete="off">
          <div class="form-body">

            <div class="section-label">Personal Details</div>
            <div class="grid-2">
              <div class="field">
                <label>Full Name <span class="req">*</span></label>
                <input type="text" name="full_name" placeholder="e.g. John Doe"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
              </div>
              <div class="field">
                <label>Email Address <span class="req">*</span></label>
                <input type="email" name="email" placeholder="john@company.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
              </div>
              <div class="field">
                <label>Password <span class="req">*</span></label>
                <div class="pw-wrap">
                  <input type="password" name="password" id="pwField"
                         placeholder="Min. 6 characters" required minlength="6">
                  <button type="button" class="pw-toggle" onclick="togglePw()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <span class="field-hint">Employees use this to sign in.</span>
              </div>
              <div class="field">
                <label>Joining Date <span class="req">*</span></label>
                <input type="date" name="joining_date"
                       value="<?= htmlspecialchars($_POST['joining_date'] ?? date('Y-m-d')) ?>" required>
              </div>
            </div>

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
                <input type="text" name="position" placeholder="e.g. Software Developer"
                       value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" required>
              </div>
            </div>

            <div class="section-label">Compensation &amp; Leave</div>
            <div class="grid-2">
              <div class="field">
                <label>Basic Monthly Salary (ZMW) <span class="req">*</span></label>
                <input type="number" name="basic_salary" id="salaryInput"
                       placeholder="e.g. 8000"
                       value="<?= htmlspecialchars($_POST['basic_salary'] ?? '') ?>"
                       min="1" step="0.01" required
                       oninput="updatePreview()">
                <span class="field-hint">Enter full monthly salary. Must be greater than 0.</span>
                <div class="salary-preview" id="salaryPreview">
                  <strong>💡 Pay Rate Breakdown</strong>
                  <table>
                    <tr><td>Monthly salary</td>    <td id="p_monthly">—</td></tr>
                    <tr><td>Daily rate  (÷ 22 days)</td> <td id="p_daily">—</td></tr>
                    <tr><td>Hourly rate (÷ 8 hrs)</td>  <td id="p_hourly">—</td></tr>
                    <tr><td>Full 8-hr day earned</td>   <td id="p_full">—</td></tr>
                  </table>
                </div>
              </div>
              <div class="field">
                <label>Annual Leave Balance (days)</label>
                <input type="number" name="leave_balance"
                       placeholder="12"
                       value="<?= htmlspecialchars($_POST['leave_balance'] ?? '12') ?>"
                       min="0" step="0.5">
                <span class="field-hint">Default is 12 days (Zambian labour law).</span>
              </div>
            </div>

          </div>

          <div class="form-footer">
            <a href="employees.php" class="btn btn-outline">← Cancel</a>
            <div style="display:flex;gap:10px;">
              <button type="reset" class="btn btn-outline"
                      onclick="setTimeout(()=>{document.getElementById('salaryPreview').style.display='none'},10)">
                Clear
              </button>
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

function updatePreview() {
  const salary = parseFloat(document.getElementById('salaryInput').value);
  const preview = document.getElementById('salaryPreview');
  if (!salary || salary <= 0) { preview.style.display = 'none'; return; }

  const daily  = salary / 22;
  const hourly = daily / 8;
  const fmt    = n => 'ZMW ' + n.toLocaleString('en-ZM', {minimumFractionDigits:2, maximumFractionDigits:2});

  document.getElementById('p_monthly').textContent = fmt(salary);
  document.getElementById('p_daily').textContent   = fmt(daily);
  document.getElementById('p_hourly').textContent  = fmt(hourly);
  document.getElementById('p_full').textContent    = fmt(hourly * 8);
  preview.style.display = 'block';
}

window.addEventListener('DOMContentLoaded', updatePreview);
</script>
</body>
</html>