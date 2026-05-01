<?php
session_start();
require_once '../database/config.php';
requireAdmin();

$msg = ''; $msg_type = '';

// Add employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $full_name = sanitize($conn, $_POST['full_name']);
        $email = sanitize($conn, $_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $dept = (int)$_POST['department_id'];
        $position = sanitize($conn, $_POST['position']);
        $salary = (float)$_POST['basic_salary'];
        $joining = sanitize($conn, $_POST['joining_date']);
        $leave_bal = (float)($_POST['annual_leave_balance'] ?? 12.0);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, 'employee')");
            $stmt->bind_param("ss", $email, $password);
            $stmt->execute();
            $user_id = $conn->insert_id;

            $stmt2 = $conn->prepare("INSERT INTO employees (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isisdd s", $user_id, $full_name, $dept, $position, $salary, $joining, $leave_bal);
            // Fix bind
            $stmt2 = $conn->prepare("INSERT INTO employees (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isiisds", $user_id, $full_name, $dept, $position, $salary, $joining, $leave_bal);
            $stmt2 = $conn->prepare("INSERT INTO employees (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt2->bind_param("isisdsd", $user_id, $full_name, $dept, $position, $salary, $joining, $leave_bal);
            $stmt2->execute();
            $conn->commit();
            $msg = 'Employee added successfully!'; $msg_type = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $msg = 'Error: ' . $e->getMessage(); $msg_type = 'danger';
        }
    }

    if ($_POST['action'] === 'toggle') {
        $eid = (int)$_POST['employee_id'];
        $conn->query("UPDATE employees SET is_active = NOT is_active WHERE employee_id=$eid");
        $msg = 'Employee status updated.'; $msg_type = 'success';
    }

    if ($_POST['action'] === 'edit') {
        $eid = (int)$_POST['employee_id'];
        $full_name = sanitize($conn, $_POST['full_name']);
        $dept = (int)$_POST['department_id'];
        $position = sanitize($conn, $_POST['position']);
        $salary = (float)$_POST['basic_salary'];
        $leave_bal = (float)$_POST['annual_leave_balance'];
        $stmt = $conn->prepare("UPDATE employees SET full_name=?, department_id=?, position=?, basic_salary=?, annual_leave_balance=? WHERE employee_id=?");
        $stmt->bind_param("sisddi", $full_name, $dept, $position, $salary, $leave_bal, $eid);
        $stmt->execute();
        $msg = 'Employee updated successfully!'; $msg_type = 'success';
    }
}

// Fetch departments for dropdown
$departments = $conn->query("SELECT * FROM departments ORDER BY department_name");
$depts_arr = $departments->fetch_all(MYSQLI_ASSOC);

// Search/filter
$search = sanitize($conn, $_GET['search'] ?? '');
$dept_filter = (int)($_GET['dept'] ?? 0);

$sql = "SELECT e.*, d.department_name, u.email FROM employees e 
        JOIN departments d ON e.department_id = d.department_id
        JOIN users u ON e.user_id = u.user_id
        WHERE 1=1";
if ($search) $sql .= " AND (e.full_name LIKE '%$search%' OR e.position LIKE '%$search%')";
if ($dept_filter) $sql .= " AND e.department_id = $dept_filter";
$sql .= " ORDER BY e.full_name";
$employees = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - EMS</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>Employees</h1>
            <p>Manage your team members</p>
        </div>
        <div class="page-body">
            <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
            <?php endif; ?>

            <!-- Toolbar -->
            <div class="flex-between mb-2">
                <div class="search-bar" style="margin-bottom:0;">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Search employees..." value="<?= htmlspecialchars($search) ?>" oninput="filterCards()">
                    <select onchange="location.href='employees.php?dept='+this.value" class="form-control" style="width:auto;padding:10px 14px;">
                        <option value="0">All Departments</option>
                        <?php foreach ($depts_arr as $d): ?>
                        <option value="<?= $d['department_id'] ?>" <?= $dept_filter==$d['department_id']?'selected':'' ?>><?= htmlspecialchars($d['department_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Employee</button>
            </div>

            <!-- Employee Cards -->
            <div class="grid-3" id="empGrid">
                <?php if (empty($employees)): ?>
                <div class="empty-state" style="grid-column:1/-1;">
                    <div class="empty-icon">👤</div>
                    <p>No employees found.</p>
                </div>
                <?php else: foreach ($employees as $e): ?>
                <div class="employee-card" data-name="<?= strtolower($e['full_name']) ?>" onclick="openViewModal(<?= htmlspecialchars(json_encode($e)) ?>)">
                    <span class="emp-dept-badge"><?= htmlspecialchars($e['department_name']) ?></span>
                    <div class="emp-avatar"><?= strtoupper(substr($e['full_name'],0,1).substr(strstr($e['full_name'],' '),1,1)) ?></div>
                    <div class="emp-name"><?= htmlspecialchars($e['full_name']) ?></div>
                    <div class="emp-position"><?= htmlspecialchars($e['position']) ?></div>
                    <?php if (!$e['is_active']): ?>
                    <br><span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ADD EMPLOYEE MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Add New Employee</h3>
            <button class="modal-close" onclick="closeModal('addModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="john@company.com">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Initial password">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" class="form-control" required>
                            <?php foreach ($depts_arr as $d): ?>
                            <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control" required placeholder="Job title">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label>Basic Salary (ZMW)</label>
                        <input type="number" name="basic_salary" class="form-control" required step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Joining Date</label>
                        <input type="date" name="joining_date" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Annual Leave Balance (days)</label>
                    <input type="number" name="annual_leave_balance" class="form-control" value="12" step="0.5" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Employee</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW/EDIT EMPLOYEE MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header">
            <h3>Employee Details</h3>
            <button class="modal-close" onclick="closeModal('viewModal')">✕</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="employee_id" id="edit_id">
            <div class="modal-body">
                <div style="text-align:center;margin-bottom:20px;">
                    <div class="emp-avatar" id="view_avatar" style="margin:0 auto 12px;width:80px;height:80px;font-size:26px;"></div>
                    <div style="font-size:12px;color:#9ca3af;" id="view_email"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department_id" id="edit_dept" class="form-control">
                            <?php foreach ($depts_arr as $d): ?>
                            <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" id="edit_position" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Basic Salary (ZMW)</label>
                        <input type="number" name="basic_salary" id="edit_salary" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Leave Balance (days)</label>
                    <input type="number" name="annual_leave_balance" id="edit_leave" class="form-control" step="0.5">
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" style="margin:0;" id="toggleForm">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="employee_id" id="toggle_id">
                    <button type="submit" class="btn btn-outline btn-sm" id="toggleBtn">Deactivate</button>
                </form>
                <button type="button" class="btn btn-outline" onclick="closeModal('viewModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function openViewModal(emp) {
    document.getElementById('edit_id').value = emp.employee_id;
    document.getElementById('toggle_id').value = emp.employee_id;
    document.getElementById('edit_name').value = emp.full_name;
    document.getElementById('edit_dept').value = emp.department_id;
    document.getElementById('edit_position').value = emp.position;
    document.getElementById('edit_salary').value = emp.basic_salary;
    document.getElementById('edit_leave').value = emp.annual_leave_balance;
    document.getElementById('view_email').textContent = emp.email;
    const n = emp.full_name.split(' ');
    document.getElementById('view_avatar').textContent = (n[0][0]+(n[1]?n[1][0]:'')).toUpperCase();
    document.getElementById('toggleBtn').textContent = emp.is_active == 1 ? 'Deactivate' : 'Activate';
    openModal('viewModal');
}

function filterCards() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.employee-card').forEach(card => {
        card.style.display = card.dataset.name.includes(q) ? '' : 'none';
    });
}

// Close on backdrop click
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});
</script>
</body>
</html>
