<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php'));
    exit();
}

$error  = '';
$portal = $_GET['portal'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database/config.php';

    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';
    $portal   =      $_POST['portal']   ?? 'employee';
    $role     = ($portal === 'admin') ? 'admin' : 'employee';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // Use a prepared statement — no manual escaping of email needed
        $stmt = $conn->prepare(
            "SELECT u.user_id, u.password_hash, u.role,
                    e.employee_id, e.full_name
             FROM   users u
             LEFT JOIN employees e ON e.user_id = u.user_id
             WHERE  u.email = ? AND u.role = ?
             LIMIT  1"
        );
        $stmt->bind_param('ss', $email, $role);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id']     = $row['user_id'];
            $_SESSION['role']        = $row['role'];
            $_SESSION['email']       = $email;
            $_SESSION['full_name']   = $row['full_name'] ?? 'Admin';
            $_SESSION['employee_id'] = $row['employee_id'] ?? null;

            header('Location: ' . ($row['role'] === 'admin' ? 'admin/dashboard.php' : 'employee/dashboard.php'));
            exit();
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employee Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/index.css">
</head>
<body>
<div class="page">

  <!-- LEFT -->
  <div class="left">
    <div class="left-inner">
      <h1>Employee<br>Management<br>System</h1>
      <p>Streamline your workforce operations, track attendance, manage payroll, and empower your team securely.</p>
    </div>
  </div>

  <!-- RIGHT -->
  <div class="right">
    <div class="right-inner fade">

      <?php if (empty($portal)): ?>
      <!-- ── PORTAL SELECTION ── -->
      <div class="portal-hd">
        <h2>Welcome Back</h2>
        <p>Select your portal to securely access the system.</p>
      </div>
      <a href="?portal=admin"    class="portal-btn">Admin Portal    <span class="arr">→</span></a>
      <a href="?portal=employee" class="portal-btn">Employee Portal <span class="arr">→</span></a>

      <?php else: ?>
      <!-- ── LOGIN FORM ── -->
      <a href="index.php" class="back-link">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
        Back to portals
      </a>

      <div class="form-title"><?= $portal === 'admin' ? 'Admin Portal' : 'Employee Portal' ?></div>
      <div class="form-sub">Sign in to access your account</div>

      <?php if ($error): ?>
      <div class="error-box">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" autocomplete="off">
        <input type="hidden" name="portal" value="<?= htmlspecialchars($portal) ?>">

        <div class="field">
          <label>Email address</label>
          <input type="email" name="email"
                 placeholder="<?= $portal === 'admin' ? 'adminitsupport@ems.com' : 'jacksonpunta@ems.com' ?>"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autocomplete="username">
        </div>

        <div class="field">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pwField"
                   placeholder="•••••••••••" required autocomplete="current-password">
            <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Show password">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit" class="signin-btn">Sign in</button>
      </form>

      <!-- Demo credentials -->
      <div class="cred-hint">
        <strong>Demo credentials</strong>
        <?php if ($portal === 'admin'): ?>
          Email: <b>adminSuperv@ems.com</b><br>
          Password: <b>Human@Resource123</b>
        <?php else: ?>
          Email: <b>example@ems.com</b><br>
          Password: <b>KABEmba@123</b>
        <?php endif; ?>
      </div>

      <?php endif; ?>

    </div>
    <div class="footer">© 2026 ems. All rights reserved.</div>
  </div>

</div>
<script>
function togglePw() {
  const f = document.getElementById('pwField');
  f.type = f.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
