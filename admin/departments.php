<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$msg = ''; $msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add') {
        $name = sanitize($conn, $_POST['department_name']);
        $stmt = $conn->prepare("INSERT INTO departments (department_name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) { $msg = 'Department added!'; $msg_type = 'success'; }
        else { $msg = 'Department already exists.'; $msg_type = 'danger'; }
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['department_id'];
        $in_use = $conn->query("SELECT COUNT(*) as c FROM employees WHERE department_id=$id")->fetch_assoc()['c'];
        if ($in_use > 0) { $msg = 'Cannot delete: department has employees.'; $msg_type = 'danger'; }
        else { $conn->query("DELETE FROM departments WHERE department_id=$id"); $msg = 'Department deleted.'; $msg_type = 'success'; }
    }
    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['department_id'];
        $name = sanitize($conn, $_POST['department_name']);
        $stmt = $conn->prepare("UPDATE departments SET department_name=? WHERE department_id=?");
        $stmt->bind_param("si", $name, $id);
        $stmt->execute();
        $msg = 'Department updated.'; $msg_type = 'success';
    }
}

$departments = $conn->query("
    SELECT d.*, COUNT(e.employee_id) as emp_count
    FROM departments d
    LEFT JOIN employees e ON d.department_id = e.department_id AND e.is_active=1
    GROUP BY d.department_id
    ORDER BY d.department_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Departments</h1>
            <p>Manage organisational departments</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <div class="flex-between mb-2">
                <div></div>
                <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">+ Add Department</button>
            </div>

            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Department Name</th>
                            <th>Employees</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($departments as $i => $d): ?>
                        <tr>
                            <td style="color:#9ca3af;"><?= $i+1 ?></td>
                            <td><strong><?= htmlspecialchars($d['department_name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= $d['emp_count'] ?> employees</span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="icon-btn icon-btn-edit" onclick="editDept(<?= $d['department_id'] ?>, '<?= htmlspecialchars($d['department_name'], ENT_QUOTES) ?>')">✏</button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this department?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="department_id" value="<?= $d['department_id'] ?>">
                                        <button type="submit" class="icon-btn icon-btn-reject">🗑</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <h3>Add Department</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Department Name</label>
                    <input type="text" name="department_name" class="form-control" required placeholder="e.g. Engineering">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal" style="max-width:380px;">
        <div class="modal-header">
            <h3>Edit Department</h3>
            <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="department_id" id="edit_dept_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Department Name</label>
                    <input type="text" name="department_name" id="edit_dept_name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function editDept(id, name) {
    document.getElementById('edit_dept_id').value = id;
    document.getElementById('edit_dept_name').value = name;
    document.getElementById('editModal').classList.add('open');
}
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>
