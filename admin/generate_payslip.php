<?php
session_start();
require_once '../database/config.php';
requireLogin();

$id = (int)$_GET['id'];

// Security: employees can only download their own payslips
if ($_SESSION['role'] === 'employee') {
    $emp = $conn->query("SELECT employee_id FROM employees WHERE user_id={$_SESSION['user_id']}")->fetch_assoc();
    $p = $conn->query("SELECT * FROM payroll WHERE payroll_id=$id AND employee_id={$emp['employee_id']}")->fetch_assoc();
} else {
    $p = $conn->query("SELECT * FROM payroll WHERE payroll_id=$id")->fetch_assoc();
}

if (!$p) { die('Payslip not found or access denied.'); }

$emp_data = $conn->query("SELECT e.*, d.department_name, u.email FROM employees e JOIN departments d ON e.department_id=d.department_id JOIN users u ON e.user_id=u.user_id WHERE e.employee_id={$p['employee_id']}")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - <?= htmlspecialchars($emp_data['full_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f4f5fb; display: flex; justify-content: center; padding: 40px 20px; }
        .payslip { background: white; width: 700px; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 30px rgba(0,0,0,0.08); }
        .ps-header { background: linear-gradient(135deg, #0f1535, #1a2560); color: white; padding: 36px 40px; }
        .ps-header .company { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .ps-header .subtitle { font-size: 13px; color: rgba(255,255,255,0.5); }
        .ps-header .payslip-title { margin-top: 20px; font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600; background: rgba(91,94,244,0.3); display: inline-block; padding: 6px 16px; border-radius: 20px; }
        .ps-body { padding: 36px 40px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 32px; }
        .info-block label { font-size: 11px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.8px; display: block; margin-bottom: 4px; }
        .info-block span { font-size: 14px; color: #111827; font-weight: 500; }
        .divider { border: none; border-top: 1px solid #f0f0f0; margin: 24px 0; }
        .salary-section h4 { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 14px; }
        .salary-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f9fafb; }
        .salary-row .label { font-size: 13.5px; color: #4b5563; }
        .salary-row .amount { font-size: 13.5px; color: #111827; font-weight: 500; }
        .salary-row .amount.green { color: #10b981; }
        .salary-row .amount.red { color: #ef4444; }
        .net-row { display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, #0f1535, #1a2560); color: white; padding: 18px 20px; border-radius: 12px; margin-top: 20px; }
        .net-row .label { font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 600; }
        .net-row .amount { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 700; }
        .ps-footer { padding: 24px 40px; border-top: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .ps-footer p { font-size: 12px; color: #9ca3af; }
        .sig-line { width: 180px; border-top: 2px solid #d1d5db; text-align: center; padding-top: 8px; font-size: 12px; color: #6b7280; }
        @media print {
            body { background: white; padding: 0; }
            .payslip { box-shadow: none; border-radius: 0; }
            .no-print { display: none; }
        }
        .action-bar { text-align: center; margin-bottom: 20px; }
        .btn-print { background: linear-gradient(135deg, #5b5ef4, #7b7ef7); color: white; border: none; padding: 12px 28px; border-radius: 10px; font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; margin: 0 6px; }
        .btn-back { background: white; color: #374151; border: 1.5px solid #e5e7eb; padding: 12px 24px; border-radius: 10px; font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin: 0 6px; }
    </style>
</head>
<body>
<div style="width:700px;">
    <div class="action-bar no-print">
        <a href="javascript:history.back()" class="btn-back">← Back</a>
        <button onclick="window.print()" class="btn-print">🖨 Print / Download PDF</button>
    </div>

    <div class="payslip">
        <div class="ps-header">
            <div class="company">Employee Management System</div>
            <div class="subtitle">Cavendish University Zambia · EMS Platform</div>
            <div class="payslip-title">PAYSLIP — <?= date('F Y', strtotime($p['month'])) ?></div>
        </div>

        <div class="ps-body">
            <div class="info-grid">
                <div class="info-block">
                    <label>Employee Name</label>
                    <span><?= htmlspecialchars($emp_data['full_name']) ?></span>
                </div>
                <div class="info-block">
                    <label>Email</label>
                    <span><?= htmlspecialchars($emp_data['email']) ?></span>
                </div>
                <div class="info-block">
                    <label>Department</label>
                    <span><?= htmlspecialchars($emp_data['department_name']) ?></span>
                </div>
                <div class="info-block">
                    <label>Position</label>
                    <span><?= htmlspecialchars($emp_data['position']) ?></span>
                </div>
                <div class="info-block">
                    <label>Joining Date</label>
                    <span><?= date('d M Y', strtotime($emp_data['joining_date'])) ?></span>
                </div>
                <div class="info-block">
                    <label>Pay Period</label>
                    <span><?= date('F Y', strtotime($p['month'])) ?></span>
                </div>
            </div>

            <hr class="divider">

            <div class="salary-section">
                <h4>Earnings & Deductions</h4>
                <div class="salary-row">
                    <span class="label">Basic Salary</span>
                    <span class="amount">ZMW <?= number_format($p['basic_salary'], 2) ?></span>
                </div>
                <div class="salary-row">
                    <span class="label">Allowances</span>
                    <span class="amount green">+ ZMW <?= number_format($p['allowances'], 2) ?></span>
                </div>
                <div class="salary-row">
                    <span class="label">Leave Deductions</span>
                    <span class="amount red">- ZMW <?= number_format($p['leave_deduction'], 2) ?></span>
                </div>
            </div>

            <div class="net-row">
                <span class="label">NET SALARY</span>
                <span class="amount">ZMW <?= number_format($p['net_salary'], 2) ?></span>
            </div>
        </div>

        <div class="ps-footer">
            <div>
                <p>Generated on: <?= date('d M Y, h:i A', strtotime($p['generated_on'])) ?></p>
                <p style="margin-top:4px;">This is a computer-generated payslip and is valid without a signature.</p>
            </div>
            <div class="sig-line">Authorised Signatory</div>
        </div>
    </div>
</div>
</body>
</html>
