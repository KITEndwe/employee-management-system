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
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --navy:     #151c3b;
    --accent:   #5b5ef4;
    --accent-h: #4a4de0;
    --white:    #ffffff;
    --gray-200: #e8eaf0;
    --gray-400: #9aa0b4;
    --gray-600: #5c6280;
    --gray-800: #1e2340;
  }

  html, body { height: 100%; font-family: 'Sora', sans-serif; overflow: hidden; }

  .page { display: flex; height: 100vh; }

  /* ── LEFT ── */
  .left {
    width: 48%;
    background: var(--navy);
    position: relative;
    display: flex;
    align-items: flex-end;
    padding: 56px 52px;
    overflow: hidden;
  }
  .left::before {
    content: '';
    position: absolute; top: -100px; left: -100px;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(91,94,244,.22) 0%, transparent 65%);
    pointer-events: none;
  }
  .left::after {
    content: '';
    position: absolute; bottom: -60px; right: -60px;
    width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(91,94,244,.13) 0%, transparent 65%);
    pointer-events: none;
  }
  .left-inner { position: relative; z-index: 1; }
  .left h1 {
    font-size: clamp(30px, 3.8vw, 50px);
    font-weight: 800; line-height: 1.1;
    color: var(--white); letter-spacing: -1px; margin-bottom: 18px;
  }
  .left p {
    font-size: 14px; font-weight: 300;
    color: rgba(255,255,255,.5); line-height: 1.7; max-width: 360px;
  }

  /* ── RIGHT ── */
  .right {
    width: 52%;
    background: var(--white);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 60px 72px;
    position: relative;
  }
  .right-inner { width: 100%; max-width: 380px; }

  /* Portal selection */
  .portal-hd h2 { font-size: 24px; font-weight: 700; color: var(--gray-800); margin-bottom: 6px; }
  .portal-hd p  { font-size: 13px; font-weight: 300; color: var(--gray-400); margin-bottom: 32px; }

  .portal-btn {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 20px 22px;
    border: 1.5px solid var(--gray-200); border-radius: 12px;
    background: var(--white); color: var(--gray-800);
    font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600;
    cursor: pointer; text-decoration: none; margin-bottom: 12px;
    transition: border-color .2s, box-shadow .2s, transform .15s;
  }
  .portal-btn:hover { border-color: var(--accent); box-shadow: 0 4px 16px rgba(91,94,244,.1); transform: translateY(-1px); }
  .portal-btn .arr { color: var(--gray-400); font-size: 17px; transition: color .2s, transform .2s; }
  .portal-btn:hover .arr { color: var(--accent); transform: translateX(4px); }

  /* Login form */
  .back-link {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 400; color: var(--gray-400);
    text-decoration: none; margin-bottom: 28px; transition: color .2s;
  }
  .back-link:hover { color: var(--accent); }

  .form-title { font-size: 24px; font-weight: 700; color: var(--gray-800); margin-bottom: 4px; }
  .form-sub   { font-size: 13px; font-weight: 300; color: var(--gray-400); margin-bottom: 28px; }

  .field { margin-bottom: 16px; }
  .field label { display: block; font-size: 12.5px; font-weight: 600; color: var(--gray-600); margin-bottom: 7px; }
  .field input {
    width: 100%; padding: 13px 16px;
    border: 1.5px solid var(--gray-200); border-radius: 10px;
    font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 300;
    color: var(--gray-800); background: var(--white); outline: none;
    transition: border-color .2s, box-shadow .2s;
  }
  .field input::placeholder { color: var(--gray-400); }
  .field input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(91,94,244,.1); }

  .pw-wrap { position: relative; }
  .pw-wrap input { padding-right: 44px; }
  .pw-toggle {
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; padding: 0;
    color: var(--gray-400); display: flex; align-items: center; transition: color .2s;
  }
  .pw-toggle:hover { color: var(--gray-600); }

  .signin-btn {
    width: 100%; padding: 14px; margin-top: 6px;
    background: var(--accent); color: var(--white); border: none; border-radius: 10px;
    font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600;
    cursor: pointer; letter-spacing: .3px;
    transition: background .2s, box-shadow .2s, transform .15s;
  }
  .signin-btn:hover { background: var(--accent-h); box-shadow: 0 6px 20px rgba(91,94,244,.3); transform: translateY(-1px); }
  .signin-btn:active { transform: translateY(0); }

  /* Error */
  .error-box {
    display: flex; align-items: center; gap: 8px;
    background: #fff5f5; border: 1px solid #fecaca; color: #991b1b;
    padding: 11px 14px; border-radius: 9px; font-size: 13px; margin-bottom: 16px;
  }

  /* Demo credentials hint */
  .cred-hint {
    margin-top: 18px;
    background: #f7f8fc;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 12px;
    color: var(--gray-600);
    line-height: 1.8;
  }
  .cred-hint strong { display: block; font-size: 11px; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 4px; }

  .footer { position: absolute; bottom: 26px; left: 0; right: 0; text-align: center; font-size: 12px; font-weight: 300; color: var(--gray-400); }

  .fade { animation: fadeUp .3s ease both; }
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
</style>
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
