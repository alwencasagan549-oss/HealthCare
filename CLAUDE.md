# IPIHRS-CHC — Project Instructions for AI

## What This Project Is

Integrated Patient Information and Health Report System for City Health Center (IPIHRS-CHC).
Multi-district health management platform for City Health Center operations: patient records, health survey collection, district-level reporting, and role-based access control.

## User Roles

- **Admin**: full system access, manages districts/nurses, generates consolidated reports.
- **Nurse (District In-Charge)**: manages one assigned district's patients, reviews surveys, creates DPWH accounts.
- **DPWH (Data Processor/Health Worker)**: submits health surveys, views own submissions, forced password change on first login.

## Language & Stack

- PHP 8.2+
- Bootstrap 5 + Bootstrap Icons
- Vanilla JavaScript
- PDO for database access
- bcrypt password hashing (cost 12)

## Database

- Primary target: PostgreSQL on Render free tier
- Also supports MySQL/MariaDB for local development
- Database class auto-detects via `DATABASE_URL` scheme:
  - `pgsql://` or `postgresql://` → PostgreSQL
  - otherwise → MySQL
- PostgreSQL BOOLEAN columns must use `TRUE`/`FALSE`, never `1`/`0`
- PostgreSQL string literals use single quotes; double quotes are identifiers
- PostgreSQL dollar-quoted strings `$...$` must not be split blindly on `;`

## Current Deployment

- Live URL: https://healthcaresurvey.cc.cd
- Platform: Render (Docker)
- Runtime: `php:8.2-apache` with `pdo_pgsql`, `gd`, `intl`, `zip`, `opcache`, `bcmath`
- Render database: `HealtCare-DB` (PostgreSQL free tier)
- Database name: `healthcare_3op3`
- Render service name: `ipihrs-chc`
- Env vars injected by Render: `DATABASE_URL`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `SESSION_SECURE_COOKIE=1`, `APP_URL=https://healthcaresurvey.cc.cd`

## Directory Structure

```
├── admin/              # Admin panel
├── config/
│   ├── config.php      # App constants/session config
│   └── database.php    # PDO connection, PostgreSQL + MySQL support
├── includes/
│   ├── helpers.php     # e(), url(), asset_path(), paginate(), etc.
│   ├── auth.php        # Auth class
│   ├── middleware.php   # Role-based access guards
│   ├── audit.php       # Audit logging
│   ├── header.php
│   ├── sidebar.php
│   └── footer.php
├── database/
│   ├── schema.sql      # PostgreSQL-compatible schema
│   ├── seed.php        # Seed data
│   └── import_schema.php # Render schema importer
├── nurse/              # Nurse workflows
├── dpwh/               # DPWH workflows
├── login.php           # Shared login form
├── logout.php
├── change_password.php # Forced DPWH password change
├── index.php           # Landing page with 3 role cards
├── .htaccess           # Security headers + access rules
├── Dockerfile          # Render Docker build
├── render.yaml         # Render service + database config
└── README.md
```

## Key Files

- `config/database.php` — PDO singleton, auto-detects PostgreSQL vs MySQL
- `render.yaml` — Render deployment config, references `HealtCare-DB`
- `Dockerfile` — `php:8.2-apache` + `pdo_pgsql`
- `index.php` — Public landing page with Admin/Nurse/DPWH login buttons
- `login.php` — Single login form for all roles
- `includes/middleware.php` — Role guards: `admin()`, `nurse()`, `dpwh()`, `authenticated()`

## Authentication & Session Rules

- Shared login at `login.php` for all roles
- Role selected via URL parameter `?role=admin|nurse|dpwh`
- Session timeout: 5 minutes inactivity (`SESSION_INACTIVITY_TIMEOUT` in config)
- No automatic forwarding after logout
- DPWH first login: `force_password_change = TRUE` → redirected to `change_password.php`
- CSRF tokens on all forms
- Login rate limiting: 5 attempts per 15 minutes

## PostgreSQL Compatibility Rules

1. Boolean columns: use `TRUE`/`FALSE`, not `1`/`0`
2. String literals: use single quotes `'active'`, not double quotes `"active"`
3. Dollar-quoted blocks `$func$...$func$`: preserve as whole units when splitting SQL
4. `pdo_pgsql` extension required in Docker/PHP
5. `force_password_change` is BOOLEAN — always `TRUE`/`FALSE`
6. `is_active` is BOOLEAN — always `TRUE`/`FALSE`
7. `status` columns are VARCHAR — use `'active'` not `"active"`

## Known Gotchas

- `.htaccess` blocks direct access to `config/`, `includes/`, and sensitive extensions
- Render free tier has no pgAdmin; use `database/import_schema.php` for schema imports
- `SESSION_SECURE_COOKIE=1` on Render — cookies require HTTPS
- `APP_URL` must be `https://healthcaresurvey.cc.cd` for cookie/session security
- Bootstrap CDN: `cdn.jsdelivr.net`
- Google Font: Figtree
- Icons: Bootstrap Icons 1.11.3

## Default Credentials (local / seeded)

| Role  | Username      | Password    |
|-------|---------------|-------------|
| Admin | `admin`       | `Admin@123` |
| Nurse | `nurse.pob`   | `Nurse@123` |
| Nurse | `nurse.west`  | `Nurse@123` |
| Nurse | `nurse.east`  | `Nurse@123` |
| DPWH  | `dpwh.pob`    | `Dpwh@123`  |
| DPWH  | `dpwh.west`   | `Dpwh@123`  |
| DPWH  | `dpwh.east`   | `Dpwh@123`  |

DPWH accounts have `force_password_change = TRUE` and must change password on first login.

## Recent Critical Fixes

- PostgreSQL boolean mismatches across 6 files (`TRUE`/`FALSE` instead of `1`/`0`)
- SQL string quoting in `nurse/patients.php` (`'active'` not `"active"`)
- Render database name mismatch in `render.yaml`
- Schema importer dollar-quote splitting bug
- Dockerfile missing `pdo_pgsql` extension
- Admin dashboard logout button broken
- Navigation `file:///` errors
- Landing page created at `index.php`

## Working With This Project

- Always use PostgreSQL-compatible SQL when editing queries
- Test locally with XAMPP/MySQL, but verify PostgreSQL compatibility before pushing
- Render auto-deploys on push to `master`
- Never commit secrets; Render injects `DATABASE_URL` and related env vars
- Use `render psql <database-id>` for direct PostgreSQL access on Render if needed
- Use `database/import_schema.php` for schema updates on Render (no psql/pgAdmin needed)
