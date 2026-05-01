<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query("SELECT e.*, u.email FROM employees e JOIN users u ON e.user_id=u.user_id WHERE e.user_id=$uid")->fetch_assoc();

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['form'] === 'profile') {
        $full_name = sanitize($conn, $_POST['full_name']);
        $bio       = sanitize($conn, $_POST['bio'] ?? '');
        $stmt = $conn->prepare("UPDATE employees SET full_name=?, bio=? WHERE user_id=?");
        $stmt->bind_param("ssi", $full_name, $bio, $uid);
        $stmt->execute();
        $_SESSION['full_name'] = $full_name;
        $msg = 'Profile updated!'; $msg_type = 'success';
        $emp['full_name'] = $full_name; $emp['bio'] = $bio;
    }
    if ($_POST['form'] === 'password') {
        $cur = $_POST['current_password'];
        $new = $_POST['new_password'];
        $cnf = $_POST['confirm_password'];
        $user = $conn->query("SELECT password_hash FROM users WHERE user_id=$uid")->fetch_assoc();
        if (!password_verify($cur, $user['password_hash'])) { $msg = 'Current password incorrect.'; $msg_type = 'danger'; }
        elseif ($new !== $cnf) { $msg = 'Passwords do not match.'; $msg_type = 'danger'; }
        elseif (strlen($new) < 6) { $msg = 'Password too short.'; $msg_type = 'danger'; }
        else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $conn->query("UPDATE users SET password_hash='$hash' WHERE user_id=$uid");
            $msg = 'Password updated!'; $msg_type = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings — EMS</title>
<link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Settings</h1>
            <p>Manage your account and preferences</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Public Profile -->
            <div class="card" style="max-width:600px;margin-bottom:20px;">
                <div class="card-header"><h3>Public Profile</h3></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="form" value="profile">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($emp['full_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($emp['email']) ?>" disabled style="background:#f9fafb;color:#9ca3af;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Position</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($emp['position']) ?>" disabled style="background:#f9fafb;color:#9ca3af;">
                        </div>
                        <div class="form-group">
                            <label>Bio</label>
                            <textarea name="bio" class="form-control" rows="3" placeholder="Write a brief bio..."><?= htmlspecialchars($emp['bio'] ?? '') ?></textarea>
                            <small style="color:#9ca3af;font-size:12px;">This will be displayed on your profile.</small>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- Password -->
            <div class="card" style="max-width:600px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:18px;">🔒</span>
                        <div><h3 style="margin:0;">Password</h3><p style="font-size:12px;color:#9ca3af;margin:0;">Update your account password</p></div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="form" value="password">
                        <div class="form-group"><label>Current Password</label><input type="password" name="current_password" class="form-control" required></div>
                        <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control" required></div>
                        <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
