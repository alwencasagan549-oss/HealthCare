# Integrated Patient Information and Health Report System for City Health Center (IPIHRS-CHC)

## Overview

IPIHRS-CHC is a multi-district health management platform designed for City Health Center operations. It streamlines patient record management, health survey data collection, and district-level reporting through role-based access control.

## User Roles

- **Admin**: Full system access. Manages districts, nurses, views all district data, and generates consolidated reports.
- **Nurse (District In-Charge)**: Manages one assigned district's patients, reviews submitted surveys, and creates DPWH accounts.
- **DPWH (Data Processor/Health Worker)**: Submits health surveys, views own submissions, and is required to change their temporary password on first login.

## Features

- Role-based authentication with session management
- District-based data isolation
- Audit logging for all mutations
- CSRF protection and XSS prevention
- Pagination for all list pages
- Soft delete for data integrity
- Responsive Bootstrap 5 interface

## Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher / MariaDB 10.3+
- Composer (optional, not required for core app)
- Web server with URL rewriting support (Apache .htaccess included)

## Quick Setup

1. **Clone or copy** the project into your web root:
   ```
   C:\xampp\htdocs\Health Report Sytem\
   ```

2. **Create the database**:
   - Open phpMyAdmin or MySQL CLI
   - Create a database named `health_report_db`

3. **Import the schema**:
   ```bash
   mysql -u root -p health_report_db < database/schema.sql
   ```

4. **Seed initial data**:
   ```bash
   php database/seed.php
   ```

5. **Configure database connection**:
   - Edit `config/database.php` if your MySQL credentials differ from the default `root / (empty)`.

6. **Access the application**:
   - URL: `http://localhost/Health%20Report%20Sytem/login.php`

## Default Credentials

| Role  | Username      | Password    |
|-------|---------------|-------------|
| Admin | `admin`       | `Admin@123` |
| Nurse | `nurse.pob`   | `Nurse@123` |
| Nurse | `nurse.west`  | `Nurse@123` |
| Nurse | `nurse.east`  | `Nurse@123` |
| DPWH  | `dpwh.pob`    | `Dpwh@123`  |
| DPWH  | `dpwh.west`   | `Dpwh@123`  |
| DPWH  | `dpwh.east`   | `Dpwh@123`  |

> **Note**: All DPWH accounts are created with `force_password_change = 1`. They will be redirected to the password change page on first login.

## Seed Districts

| Code    | Name       |
|---------|------------|
| `POB`   | Poblacion  |
| `WST`   | West       |
| `EST`   | East       |

## Directory Structure

```
Health Report Sytem/
├── admin/
│   ├── dashboard.php
│   ├── districts.php
│   ├── district_form.php
│   ├── district_view.php
│   ├── assign_nurse.php
│   ├── nurses.php
│   ├── reports.php
│   └── audit_logs.php
├── config/
│   ├── config.php
│   └── database.php
├── includes/
│   ├── helpers.php
│   ├── auth.php
│   ├── middleware.php
│   ├── audit.php
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── database/
│   ├── schema.sql
│   └── seed.php
├── nurse/
│   ├── dashboard.php
│   ├── patients.php
│   ├── patient_form.php
│   ├── survey_results.php
│   ├── survey_review.php
│   ├── dpwh_accounts.php
│   ├── dpwh_form.php
│   └── reports.php
├── dpwh/
│   ├── dashboard.php
│   ├── surveys_new.php
│   └── surveys_mine.php
├── login.php
├── logout.php
├── change_password.php
├── .htaccess
└── README.md
```

## Access Control Matrix

| Page | Admin | Nurse | DPWH |
|------|-------|-------|------|
| Dashboard | ✅ | ✅ | ✅ |
| Districts | ✅ | ❌ | ❌ |
| Assign Nurse | ✅ | ❌ | ❌ |
| Nurses | ✅ | ❌ | ❌ |
| District View | ✅ | ❌ | ❌ |
| Patients | ❌ | ✅ | ❌ |
| Survey Results | ❌ | ✅ | ❌ |
| Survey Review | ❌ | ✅ | ❌ |
| DPWH Accounts | ❌ | ✅ | ❌ |
| Create DPWH | ❌ | ✅ | ❌ |
| Reports | ✅ | ✅ | ❌ |
| Audit Logs | ✅ | ❌ | ❌ |
| New Survey | ❌ | ❌ | ✅ |
| My Surveys | ❌ | ❌ | ✅ |

## Security

- CSRF tokens on all forms
- Prepared statements via PDO
- bcrypt password hashing (cost 12)
- Secure session configuration
- Login rate limiting (5 attempts per 15 minutes)
- Audit logging for CREATE/UPDATE/DELETE operations
- .htaccess access restrictions for sensitive directories
- Output escaping via `e()` helper

## Password Policy

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

## Tech Stack

- PHP 8+
- MySQL
- Bootstrap 5
- Vanilla JavaScript
- PDO
- bcrypt

## Version

v1.0.0
