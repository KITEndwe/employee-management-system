<?php
$current = basename($_SERVER['PHP_SELF']);
$initials = '';
$name = $_SESSION['full_name'] ?? 'Admin';
$parts = explode(' ', $name);
foreach ($parts as $p) $initials .= strtoupper(substr($p, 0, 1));
$initials = substr($initials, 0, 2);
?>
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">Employee MS</div>
        <div class="brand-sub">Management System</div>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= $initials ?></div>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($name) ?></span>
            <span class="user-role">Administrator</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-label">Navigation</div>
        <a href="dashboard.php" class="nav-item <?= $current === 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">⊞</span><span>Dashboard</span>
        </a>
        <a href="employees.php" class="nav-item <?= $current === 'employees.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span><span>Employees</span>
        </a>
        <a href="departments.php" class="nav-item <?= $current === 'departments.php' ? 'active' : '' ?>">
            <span class="nav-icon">🏢</span><span>Departments</span>
        </a>
        <a href="attendance.php" class="nav-item <?= $current === 'attendance.php' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span><span>Attendance</span>
        </a>
        <a href="leave.php" class="nav-item <?= $current === 'leave.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span><span>Leave</span>
        </a>
        <a href="payroll.php" class="nav-item <?= $current === 'payroll.php' ? 'active' : '' ?>">
            <span class="nav-icon">💰</span><span>Payslips</span>
        </a>
        <a href="settings.php" class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>" >
            <span class="nav-icon">⚙️</span><span>Settings</span>
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn" style="font-size: 15px;">
            <span>⎋</span><span>Log out</span>
        </a>
    </div>
</div>
