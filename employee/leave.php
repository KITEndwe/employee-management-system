<?php
session_start();
require_once '../database/config.php';
requireEmployee();

$uid = $_SESSION['user_id'];
$emp = $conn->query("SELECT * FROM employees WHERE user_id=$uid")->fetch_assoc();
$eid = $emp['employee_id'];

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = sanitize($conn, $_POST['leave_type']);
    $start      = sanitize($conn, $_POST['start_date']);
    $end        = sanitize($conn, $_POST['end_date']);
    $reason     = sanitize($conn, $_POST['reason'] ?? '');

    if (strtotime($end) < strtotime($start)) {
        $msg = 'End date cannot be before start date.'; $msg_type = 'danger';
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $eid, $leave_type, $start, $end, $reason);
        $stmt->execute();
        $msg = 'Leave request submitted successfully!'; $msg_type = 'success';
    }
}

// Stats
$sick_taken   = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id=$eid AND leave_type='sick' AND status='approved'")->fetch_assoc()['c'];
$casual_taken = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id=$eid AND leave_type='casual' AND status='approved'")->fetch_assoc()['c'];
$annual_taken = $conn->query("SELECT COUNT(*) as c FROM leave_requests WHERE employee_id=$eid AND leave_type='annual' AND status='approved'")->fetch_assoc()['c'];

$leaves = $conn->query("SELECT * FROM leave_requests WHERE employee_id=$eid ORDER BY requested_on DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leave — EMS</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Leave Management</h1>
            <p>Your leave history and requests</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
                <div class="stat-card"><div class="stat-info"><div class="stat-label">Sick Leave</div><div class="stat-value"><?= $sick_taken ?> <span style="font-size:14px;color:#9ca3af;">taken</span></div></div><div class="stat-icon">🤒</div></div>
                <div class="stat-card"><div class="stat-info"><div class="stat-label">Casual Leave</div><div class="stat-value"><?= $casual_taken ?> <span style="font-size:14px;color:#9ca3af;">taken</span></div></div><div class="stat-icon">☂️</div></div>
                <div class="stat-card"><div class="stat-info"><div class="stat-label">Annual Leave</div><div class="stat-value"><?= $annual_taken ?> <span style="font-size:14px;color:#9ca3af;">taken</span></div></div><div class="stat-icon">🏖️</div></div>
            </div>

            <div class="flex-between mb-2">
                <div></div>
                <button class="btn btn-primary" onclick="document.getElementById('applyModal').classList.add('open')">+ Apply for Leave</button>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr><th>Type</th><th>Dates</th><th>Reason</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr><td colspan="4"><div class="empty-state"><div class="empty-icon">📋</div><p>No leave requests yet.</p></div></td></tr>
                    <?php else: foreach ($leaves as $l):
                        $badge = $l['status']==='approved'?'badge-success':($l['status']==='rejected'?'badge-danger':'badge-warning');
                    ?>
                        <tr>
                            <td><span class="badge badge-info"><?= strtoupper($l['leave_type']) ?></span></td>
                            <td style="font-size:13px;"><?= date('M j', strtotime($l['start_date'])) ?> — <?= date('M j, Y', strtotime($l['end_date'])) ?></td>
                            <td style="color:#6b7280;font-size:13px;"><?= htmlspecialchars($l['reason'] ?? '—') ?></td>
                            <td><span class="badge <?= $badge ?>"><?= strtoupper($l['status']) ?></span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal-overlay" id="applyModal">
    <div class="modal" style="max-width:420px;">
        <div class="modal-header">
            <h3>Apply for Leave</h3>
            <button class="modal-close" onclick="document.getElementById('applyModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-group">
                    <label>Leave Type</label>
                    <select name="leave_type" class="form-control" required>
                        <option value="annual">Annual Leave</option>
                        <option value="casual">Casual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="paternity">Paternity Leave</option>
                    </select>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reason (optional)</label>
                    <textarea name="reason" class="form-control" rows="3" placeholder="Briefly explain your reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('applyModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>
