-- ============================================================
-- Employee Management System Database
-- Cavendish University Zambia — Mbwisha Kabemba (105-286)
-- ============================================================
-- CREDENTIALS
--   Admin   : admin@ems.com      / admin123
--   Employee: kalengamuma@ems.com / emp123
--   Employee: elijahmwange@ems.com / emp123
--   Employee: davidmwape@ems.com  / emp123
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
CREATE TABLE IF NOT EXISTS attendance (
    attendance_id  INT           PRIMARY KEY AUTO_INCREMENT,
    employee_id    INT           NOT NULL,
    date           DATE          NOT NULL,
    clock_in_time  TIME          NULL,
    clock_out_time TIME          NULL,
    total_hours    DECIMAL(4,2)  DEFAULT 0.00,
    UNIQUE KEY uq_attendance (employee_id, date),
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);

-- ── LEAVE REQUESTS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leave_requests (
    leave_id     INT      PRIMARY KEY AUTO_INCREMENT,
    employee_id  INT      NOT NULL,
    leave_type   ENUM('annual','casual','sick','maternity','paternity') DEFAULT 'casual',
    start_date   DATE     NOT NULL,
    end_date     DATE     NOT NULL,
    reason       TEXT     NULL,
    status       ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_comment TEXT    NULL,
    requested_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_on TIMESTAMP NULL,
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

-- ── USERS ────────────────────────────────────────────────
-- Admin password : admin123
-- Employee password: emp123
-- (All hashes generated with bcrypt cost=10, PHP-compatible $2y$)

INSERT INTO users (email, password_hash, role) VALUES
-- admin123
('admin@ems.com',
 '$2y$10$KlSD0pNr5Ph.UC7dtFSI8O3weEvdmWtt7J6cjBkxg0bzYt0ECv74G',
 'admin');

INSERT INTO users (email, password_hash, role) VALUES
-- emp123
('kalengamuma@ems.com',
 '$2y$10$kZnPAh0QeSoEG2e4rQ9z0.xWCJSI3YDiJM885r1JnU5Udem5g1WtW',
 'employee'),
('elijahmwange@ems.com',
 '$2y$10$kZnPAh0QeSoEG2e4rQ9z0.xWCJSI3YDiJM885r1JnU5Udem5g1WtW',
 'employee'),
('davidmwape@ems.com',
 '$2y$10$kZnPAh0QeSoEG2e4rQ9z0.xWCJSI3YDiJM885r1JnU5Udem5g1WtW',
 'employee');

-- ── EMPLOYEES ────────────────────────────────────────────
INSERT INTO employees
    (user_id, full_name, department_id, position, basic_salary, joining_date, annual_leave_balance)
VALUES
    (2, 'Kalenga Muma',  1, 'Senior Software Developer',  9500.00, '2023-01-15', 10.0),
    (3, 'Elijah Mwange', 1, 'Software Developer',         7500.00, '2023-06-01', 12.0),
    (4, 'David Mwape',   2, 'Associate Business Support', 5500.00, '2024-02-10', 12.0);

-- ── ATTENDANCE ────────────────────────────────────────────
INSERT INTO attendance (employee_id, date, clock_in_time, clock_out_time, total_hours) VALUES
    (1, CURDATE() - INTERVAL 1 DAY, '08:00:00', '17:00:00', 9.00),
    (1, CURDATE() - INTERVAL 2 DAY, '08:15:00', '17:00:00', 8.75),
    (1, CURDATE() - INTERVAL 3 DAY, '08:00:00', '16:30:00', 8.50),
    (2, CURDATE() - INTERVAL 1 DAY, '08:30:00', '17:00:00', 8.50),
    (3, CURDATE() - INTERVAL 1 DAY, '09:00:00', '17:30:00', 8.50);

-- ── LEAVE REQUESTS ────────────────────────────────────────
INSERT INTO leave_requests
    (employee_id, leave_type, start_date, end_date, reason, status, processed_on)
VALUES
    (1, 'annual', '2026-03-27', '2026-03-29', 'Out for a trip',          'approved', '2026-03-25 10:00:00'),
    (2, 'casual', '2026-03-23', '2026-03-24', 'Going For Vacations',     'rejected', '2026-03-22 14:00:00'),
    (1, 'casual', '2026-03-27', '2026-03-28', 'Going to visit a temple', 'pending',  NULL),
    (3, 'sick',   '2026-03-15', '2026-03-16', 'I had a fracture on leg', 'approved', '2026-03-14 09:00:00');

-- ── PAYROLL ───────────────────────────────────────────────
INSERT INTO payroll
    (employee_id, month, basic_salary, allowances, leave_deduction, net_salary)
VALUES
    (1, '2026-02-01', 9500.00, 500.00,   0.00, 10000.00),
    (2, '2026-02-01', 7500.00, 400.00,   0.00,  7900.00),
    (3, '2026-02-01', 5500.00, 300.00,   0.00,  5800.00),
    (1, '2026-01-01', 9500.00, 500.00,   0.00, 10000.00),
    (2, '2026-01-01', 7500.00, 400.00,   0.00,  7900.00),
    (3, '2026-01-01', 5500.00, 300.00, 250.00,  5550.00);
