<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_pw = $_POST['current_password'];
    $new_pw = $_POST['new_password'];
    $confirm_pw = $_POST['confirm_password'];

    $user = $conn->query("SELECT password_hash FROM users WHERE user_id={$_SESSION['user_id']}")->fetch_assoc();
    if (!password_verify($current_pw, $user['password_hash'])) {
        $msg = 'Current password is incorrect.'; $msg_type = 'danger';
    } elseif ($new_pw !== $confirm_pw) {
        $msg = 'New passwords do not match.'; $msg_type = 'danger';
    } elseif (strlen($new_pw) < 6) {
        $msg = 'Password must be at least 6 characters.'; $msg_type = 'danger';
    } else {
        $hash = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
        $stmt->bind_param("si", $hash, $_SESSION['user_id']);
        $stmt->execute();
        $msg = 'Password updated successfully!'; $msg_type = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - EMS</title>
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

            <div class="card" style="max-width:520px;">
                <div class="card-header">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:20px;">🔒</span>
                        <div>
                            <h3 style="margin:0;">Password</h3>
                            <p style="font-size:12px;color:#9ca3af;margin:0;">Update your account password</p>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" class="form-control" required placeholder="Enter current password">
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" class="form-control" required placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
