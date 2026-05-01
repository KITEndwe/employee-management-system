<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_id = (int)$_POST['leave_id'];
    $action = $_POST['action'];
    $comment = sanitize($conn, $_POST['admin_comment'] ?? '');

    if ($action === 'approve' || $action === 'reject') {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE leave_requests SET status=?, admin_comment=?, processed_on=NOW() WHERE leave_id=?");
        $stmt->bind_param("ssi", $status, $comment, $leave_id);
        $stmt->execute();

        // If approved, deduct from leave balance
        if ($action === 'approve') {
            $lr = $conn->query("SELECT * FROM leave_requests WHERE leave_id=$leave_id")->fetch_assoc();
            $days = (strtotime($lr['end_date']) - strtotime($lr['start_date'])) / 86400 + 1;
            $conn->query("UPDATE employees SET annual_leave_balance = GREATEST(0, annual_leave_balance - $days) WHERE employee_id={$lr['employee_id']}");
        }

        $msg = 'Leave request ' . $status . '.';
        $msg_type = $action === 'approve' ? 'success' : 'danger';
    }
}

$filter = sanitize($conn, $_GET['filter'] ?? 'all');
$sql = "SELECT lr.*, e.full_name, d.department_name 
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        JOIN departments d ON e.department_id = d.department_id";
if ($filter !== 'all') $sql .= " WHERE lr.status='$filter'";
$sql .= " ORDER BY lr.requested_on DESC";
$leaves = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Leave Management</h1>
            <p>Manage leave applications</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div style="display:flex;gap:8px;margin-bottom:20px;">
                <?php foreach (['all','pending','approved','rejected'] as $f): ?>
                <a href="?filter=<?= $f ?>" class="btn <?= $filter===$f?'btn-primary':'btn-outline' ?> btn-sm" style="text-transform:capitalize;"><?= $f ?></a>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($leaves)): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📋</div><p>No leave requests found.</p></div></td></tr>
                    <?php else: foreach ($leaves as $l): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($l['full_name']) ?></strong><br>
                                <small style="color:#9ca3af;"><?= htmlspecialchars($l['department_name']) ?></small>
                            </td>
                            <td><span class="badge badge-info"><?= strtoupper($l['leave_type']) ?></span></td>
                            <td style="font-size:12.5px;">
                                <?= date('M j', strtotime($l['start_date'])) ?> — <?= date('M j, Y', strtotime($l['end_date'])) ?>
                            </td>
                            <td style="max-width:200px;font-size:13px;color:#4b5563;"><?= htmlspecialchars($l['reason'] ?? '—') ?></td>
                            <td>
                                <?php if ($l['status'] === 'approved'): ?>
                                    <span class="badge badge-success">APPROVED</span>
                                <?php elseif ($l['status'] === 'rejected'): ?>
                                    <span class="badge badge-danger">REJECTED</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($l['status'] === 'pending'): ?>
                                <div class="action-btns">
                                    <button class="icon-btn icon-btn-approve" title="Approve" onclick="processLeave(<?= $l['leave_id'] ?>, 'approve')">✓</button>
                                    <button class="icon-btn icon-btn-reject" title="Reject" onclick="processLeave(<?= $l['leave_id'] ?>, 'reject')">✕</button>
                                </div>
                                <?php else: ?>
                                <span style="font-size:12px;color:#9ca3af;">Processed</span>
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

<!-- Process Leave Modal -->
<div class="modal-overlay" id="processModal">
    <div class="modal" style="max-width:400px;">
        <div class="modal-header">
            <h3 id="processTitle">Process Leave</h3>
            <button class="modal-close" onclick="closeModal('processModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="leave_id" id="process_leave_id">
            <input type="hidden" name="action" id="process_action">
            <div class="modal-body">
                <div class="form-group">
                    <label>Admin Comment (optional)</label>
                    <textarea name="admin_comment" class="form-control" rows="3" placeholder="Add a comment..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('processModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="processSubmitBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
function processLeave(id, action) {
    document.getElementById('process_leave_id').value = id;
    document.getElementById('process_action').value = action;
    document.getElementById('processTitle').textContent = action === 'approve' ? 'Approve Leave Request' : 'Reject Leave Request';
    const btn = document.getElementById('processSubmitBtn');
    btn.className = action === 'approve' ? 'btn btn-success' : 'btn btn-danger';
    btn.textContent = action === 'approve' ? 'Approve' : 'Reject';
    document.getElementById('processModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>
