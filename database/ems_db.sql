-- ============================================================
-- Employee Management System Database
-- Cavendish University Zambia — Mbwisha Kabemba (105-286)
-- ============================================================
-- CREDENTIALS
--   Admin   : admin@ems.com        / admin123
--   Employee: kalengamuma@ems.com  / emp123
--   Employee: elijahmwange@ems.com / emp123
--   Employee: davidmwape@ems.com   / emp123
-- ============================================================

CREATE DATABASE IF NOT EXISTS ems_db;
USE ems_db;

-- ── DEPARTMENTS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS departments (
    department_id   INT          PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) UNIQUE NOT NULL
);

-- ── USERS (authentication) ────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    user_id       INT          PRIMARY KEY AUTO_INCREMENT,
    email         VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','employee') NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ── EMPLOYEES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS employees (
    employee_id          INT            PRIMARY KEY AUTO_INCREMENT,
    user_id              INT            UNIQUE NOT NULL,
    full_name            VARCHAR(100)   NOT NULL,
    department_id        INT            NOT NULL,
    position             VARCHAR(100)   NOT NULL,
    basic_salary         DECIMAL(10,2)  NOT NULL,
    joining_date         DATE           NOT NULL,
    annual_leave_balance DECIMAL(5,1)   DEFAULT 12.0,
    bio                  TEXT           NULL,
    is_active            TINYINT(1)     DEFAULT 1,
    FOREIGN KEY (user_id)        REFERENCES users(user_id)             ON DELETE CASCADE,
    FOREIGN KEY (department_id)  REFERENCES departments(department_id)
);

-- ── ATTENDANCE ────────────────────────────────────────────
-- daily_pay = basic_salary / 22 working days / 8 hours * hours_worked
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id  INT           PRIMARY KEY AUTO_INCREMENT,
    employee_id    INT           NOT NULL,
    date           DATE          NOT NULL,
    clock_in_time  TIME          NULL,
    clock_out_time TIME          NULL,
    total_hours    DECIMAL(4,2)  DEFAULT 0.00,
    daily_pay      DECIMAL(10,2) DEFAULT 0.00,
    UNIQUE KEY uq_attendance (employee_id, date),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- ── LEAVE REQUESTS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leave_requests (
    leave_id      INT      PRIMARY KEY AUTO_INCREMENT,
    employee_id   INT      NOT NULL,
    leave_type    ENUM('annual','casual','sick','maternity','paternity') DEFAULT 'casual',
    start_date    DATE     NOT NULL,
    end_date      DATE     NOT NULL,
    reason        TEXT     NULL,
    status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_comment TEXT     NULL,
    requested_on  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_on  TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- ── PAYROLL ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payroll (
    payroll_id      INT           PRIMARY KEY AUTO_INCREMENT,
    employee_id     INT           NOT NULL,
    month           DATE          NOT NULL,
    basic_salary    DECIMAL(10,2) NOT NULL,
    allowances      DECIMAL(10,2) DEFAULT 0.00,
    leave_deduction DECIMAL(10,2) DEFAULT 0.00,
    net_salary      DECIMAL(10,2) NOT NULL,
    generated_on    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payroll (employee_id, month),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Departments
INSERT INTO departments (department_name) VALUES
    ('Engineering'),
    ('IT Support'),
    ('Human Resources'),
    ('Finance'),
    ('Marketing'),
    ('Operations'),
    ('Sales'),
    ('Legal'),
    ('Customer Service'),
    ('Administration');

-- ── USERS ─────────────────────────────────────────────────
-- admin123
INSERT INTO users (email, password_hash, role) VALUES
('admin@ems.com',
 '$2y$10$KlSD0pNr5Ph.UC7dtFSI8O3weEvdmWtt7J6cjBkxg0bzYt0ECv74G',
 'admin');

-- emp123
INSERT INTO users (email, password_hash, role) VALUES
('kalengamuma@ems.com',
 '$2y$10$kZnPAh0QeSoEG2e4rQ9z0.xWCJSI3YDiJM885r1JnU5Udem5g1WtW',
 'employee'),
('elijahmwange@ems.com',
 '$2y$10$kZnPAh0QeSoEG2e4rQ9z0.xWCJSI3YDiJM885r1JnU5Udem5g1WtW',
 'employee'),
('davidmwape@ems.com',
 '$2y$10$kZnPAh0QeSoEG2e4rQ9z0.xWCJSI3YDiJM885r1JnU5Udem5g1WtW',
 'employee');

-- ── EMPLOYEES ─────────────────────────────────────────────
-- Salary breakdown:
--   Kalenga  ZMW 9,500 → hourly ZMW 53.98 → daily (8h) ZMW 431.82
--   Elijah   ZMW 7,500 → hourly ZMW 42.61 → daily (8h) ZMW 340.91
--   David    ZMW 5,500 → hourly ZMW 31.25 → daily (8h) ZMW 250.00
INSERT INTO employees
    (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance)
VALUES
    (2, 'Kalenga Muma',  1, 'Senior Software Developer',  9500.00, '2023-01-15', 10.0),
    (3, 'Elijah Mwange', 1, 'Software Developer',         7500.00, '2023-06-01', 12.0),
    (4, 'David Mwape',   2, 'Associate Business Support', 5500.00, '2024-02-10', 12.0);

-- ── ATTENDANCE ────────────────────────────────────────────
-- 22 working days (Mon–Fri) per employee, 08:00–17:00, 8 hours worked.
-- daily_pay = basic_salary / 22 / 8 * 8 = basic_salary / 22
INSERT INTO attendance
    (employee_id, date, clock_in_time, clock_out_time, total_hours, daily_pay)
VALUES
    (1, '2026-05-01', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-30', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-29', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-28', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-27', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-24', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-23', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-22', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-21', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-20', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-17', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-16', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-15', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-14', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-13', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-10', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-09', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-08', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-07', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-06', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-03', '08:00:00', '17:00:00', 8.00, 431.82),
    (1, '2026-04-02', '08:00:00', '17:00:00', 8.00, 431.82),
    (2, '2026-05-01', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-30', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-29', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-28', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-27', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-24', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-23', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-22', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-21', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-20', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-17', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-16', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-15', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-14', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-13', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-10', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-09', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-08', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-07', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-06', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-03', '08:00:00', '17:00:00', 8.00, 340.91),
    (2, '2026-04-02', '08:00:00', '17:00:00', 8.00, 340.91),
    (3, '2026-05-01', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-30', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-29', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-28', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-27', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-24', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-23', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-22', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-21', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-20', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-17', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-16', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-15', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-14', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-13', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-10', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-09', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-08', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-07', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-06', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-03', '08:00:00', '17:00:00', 8.00, 250.00),
    (3, '2026-04-02', '08:00:00', '17:00:00', 8.00, 250.00);

-- ── LEAVE REQUESTS ────────────────────────────────────────
INSERT INTO leave_requests
    (employee_id, leave_type, start_date, end_date, reason, status, processed_on)
VALUES
    (1, 'annual', '2026-03-27', '2026-03-29', 'Out for a trip',          'approved', '2026-03-25 10:00:00'),
    (2, 'casual', '2026-03-23', '2026-03-24', 'Going For Vacations',     'rejected', '2026-03-22 14:00:00'),
    (1, 'casual', '2026-03-27', '2026-03-28', 'Going to visit a temple', 'pending',  NULL),
    (3, 'sick',   '2026-03-15', '2026-03-16', 'I had a fracture on leg', 'approved', '2026-03-14 09:00:00');

-- ── PAYROLL ───────────────────────────────────────────────
-- Net = basic + 5% allowance (no deductions in these sample months)
INSERT INTO payroll
    (employee_id, month, basic_salary, allowances, leave_deduction, net_salary)
VALUES
    (1, '2026-02-01', 9500.00, 475.00,   0.00,  9975.00),
    (2, '2026-02-01', 7500.00, 375.00,   0.00,  7875.00),
    (3, '2026-02-01', 5500.00, 275.00,   0.00,  5775.00),
    (1, '2026-01-01', 9500.00, 475.00,   0.00,  9975.00),
    (2, '2026-01-01', 7500.00, 375.00,   0.00,  7875.00),
    (3, '2026-01-01', 5500.00, 275.00, 250.00,  5525.00);