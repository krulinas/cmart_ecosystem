# Cursor Agent Prompt — Safe `cmart_db_rebuild` Execution

You are acting as a **senior Laravel 11 + MariaDB 10.4 database recovery and validation engineer** inside the `cmart_ecosystem` repository.

Your task is to restore a clean, working development database **without modifying or deleting the damaged original `cmart_db`**.

This prompt authorizes only:

- forensic backup preparation;
- creation of a separate clean database named `cmart_db_rebuild`;
- migration review and execution against the rebuild only;
- reviewed seeding against the rebuild only;
- database and application validation;
- complete audit reporting.

This prompt does **not** authorize original-data salvage, `innodb_force_recovery`, `IMPORT TABLESPACE`, final cutover, renaming, dropping the damaged database, or permanently replacing the `.env` connection.

---

## 1. Repository and required context

Workspace root:

```text
D:\Program Files\xampp\htdocs\cmart_ecosystem
```

Read these files before executing any change:

```text
report/00_database_recovery_summary.md
report/01_environment_and_connection_audit.md
report/02_schema_and_data_source_inventory.md
report/03_database_corruption_findings.md
report/04_recovery_options_and_risk_matrix.md
report/05_proposed_recovery_runbook.md
report/command_log.md
report/09_project_and_database_recovery_progress_2026-07-20.md
```

Inspect relevant source files, including:

```text
backend/.env
backend/.env.example
backend/config/database.php
backend/database/migrations/**
backend/database/seeders/**
backend/database/factories/**
backend/app/Models/**
backend/routes/**
backend/tests/**
frontend/tests/**
frontend/tests/e2e/**
python_analytics/**
```

Do not display or commit secret `.env` values.

---

## 2. Verified incident context

The current original database is:

```text
cmart_db
```

Environment:

```text
MariaDB 10.4.32
127.0.0.1:3306
Datadir: D:\Program Files\xampp\mysql\data\
```

All 21 visible tables in `cmart_db` fail with:

```text
ERROR 1932: Table 'cmart_db.<table>' doesn't exist in engine
```

Physical `.frm` and `.ibd` files remain, but the InnoDB dictionary has no `cmart_db/*` entries. The original database must remain untouched as forensic evidence.

The repository contains approximately 65 Laravel migrations and trusted seeders sufficient to reconstruct the current development schema and demo data.

---

## 3. Non-negotiable safety constraints

Do not run or perform any of the following:

```text
php artisan migrate:fresh
php artisan migrate:refresh
DROP DATABASE cmart_db
DROP TABLE on cmart_db
TRUNCATE on cmart_db
DELETE on cmart_db
REPAIR TABLE
IMPORT TABLESPACE
innodb_force_recovery
replace/delete ibdata1
replace/delete ib_logfile*
replace/delete *.ibd or *.frm
```

Do not:

- copy recovered files into the original datadir;
- modify the original `cmart_db` folder;
- start a second salvage server;
- perform final cutover;
- permanently change the application to `cmart_db_rebuild` without stopping for approval;
- invent or fabricate original user/booking data;
- expose passwords, keys, tokens, or raw `.env` values in reports.

If any required action would violate these constraints, stop and report it.

---

## 4. Execution scope

Execute in the following order.

# Phase A — Preflight verification

1. Record current timestamp, Git branch, working-tree status, Laravel/PHP/MariaDB versions, and current database connection target.
2. Confirm `cmart_db` still exhibits the same 1932 failure and that no unexpected changes occurred after Stage A.
3. Confirm `cmart_db_rebuild` does not already exist.
4. If `cmart_db_rebuild` already exists:
   - do not drop or overwrite it;
   - audit its creation time, tables, migration state, and row counts;
   - stop and report whether it appears to be an earlier authorized or unauthorized rebuild.
5. Confirm sufficient disk space for a full datadir forensic copy and the rebuild.
6. Put Laravel into maintenance mode or otherwise stop application writes before copying database evidence.

Safe checks may include:

```powershell
Set-Location "D:\Program Files\xampp\htdocs\cmart_ecosystem\backend"
php artisan about
php artisan env
php artisan down
```

Record every command and result.

# Phase B — Forensic backup

Create a timestamped directory outside the XAMPP datadir, for example:

```text
D:\cmart_forensic_backup_YYYYMMDD_HHMMSS\
```

Before copying, stop MariaDB cleanly through XAMPP or an equivalent controlled service stop so the datadir copy is consistent.

Copy:

```text
D:\Program Files\xampp\mysql\data\
D:\Program Files\xampp\mysql\bin\my.ini
relevant MariaDB error logs
current report directory
```

For `.env`:

- either omit it from the report backup;
- or copy it only into the private forensic directory;
- do not call it “redacted” unless the content has actually been sanitized;
- never commit it.

After copying:

1. Verify source and destination exist.
2. Record directory sizes and file counts.
3. Generate a file manifest and SHA-256 hashes for critical files where practical:
   - `ibdata1`
   - `ib_logfile0`
   - `ib_logfile1`
   - `mysql_error.log`
   - `cmart_db\users.ibd`
   - `cmart_db\users.frm`
4. Restart the original MariaDB instance normally.
5. Do not modify the copied backup.

If the full backup fails or cannot be verified, stop. Do not proceed to database creation.

# Phase C — Inspect migrations and seeders

Before creating the rebuild:

1. Inventory all migrations in chronological order.
2. Identify migrations that:
   - perform data transformations;
   - assume legacy rows already exist;
   - create temporary audit tables;
   - have destructive or guarded rollback logic;
   - use database-specific SQL;
   - might fail on a completely empty database.
3. Review `DatabaseSeeder` and all invoked seeders.
4. Confirm seeders are suitable for a clean development rebuild and identify demo/test data they create.
5. Confirm no seeder reads from the damaged `cmart_db`.
6. Record any migration or seeder risk before execution.

Do not edit migrations merely to force them to pass. If a migration is genuinely incompatible with a clean database, stop, document exact evidence, and propose the smallest safe correction without applying it unless clearly non-destructive and within scope.

# Phase D — Create clean rebuild database

Create only:

```sql
CREATE DATABASE cmart_db_rebuild
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Verify:

```sql
SHOW CREATE DATABASE cmart_db_rebuild;
SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'cmart_db_rebuild';
```

Do not create, drop, rename, or alter `cmart_db`.

# Phase E — Temporary Laravel connection

Preserve the exact original `.env` before editing.

Temporarily change only:

```text
DB_DATABASE=cmart_db_rebuild
```

Preserve:

```text
DB_CONNECTION
DB_HOST
DB_PORT
DB_USERNAME
DB_PASSWORD
```

Run:

```powershell
php artisan config:clear
php artisan cache:clear
```

Verify through Laravel runtime that the resolved database name is exactly `cmart_db_rebuild` without printing credentials.

Record the pre-change and temporary state with secrets redacted.

# Phase F — Migration dry run and execution

Run:

```powershell
php artisan migrate:status
php artisan migrate --pretend
```

Review the complete pretend output for:

- accidental references to `cmart_db`;
- destructive statements against existing databases;
- invalid foreign-key order;
- unexpected data updates;
- SQL incompatible with MariaDB 10.4.32.

Only if the pretend output is safe, run:

```powershell
php artisan migrate --force
```

This is acceptable because the target is a newly created local rebuild database, not the damaged original.

After migration:

1. Capture migration status.
2. Count migration rows.
3. Inventory every table, engine, collation, and row count.
4. Check for tables with null engine or error 1932.
5. Check primary keys, foreign keys, unique indexes, and expected role/schema changes.
6. Confirm all current Phase 3/4 schema tables exist, including layout and reservation tables expected by the current repository.

# Phase G — Seeder execution

Before running seeders, state exactly which records/categories they are expected to create.

Run the reviewed canonical seeder:

```powershell
php artisan db:seed --class=DatabaseSeeder --force
```

Then run it a second time only if needed to verify idempotency. The second run must not create uncontrolled duplicates.

Validate expected demo accounts without exposing password hashes:

```text
vendor@cmart.com
vendor_b@cmart.com
admin@cmart.com
staff@cmart.com
hq@cmart.com
organizer@cmart.com
venue@cmart.com
```

Validate canonical roles:

```text
community
organizer
cmart_management
super_admin
```

Validate expected seeded spaces, events, news, bookings/invoices, categories, and profiles according to actual seeder code.

# Phase H — Database integrity validation

Run and document:

```sql
SELECT 1;
SHOW TABLE STATUS FROM cmart_db_rebuild;
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA='cmart_db_rebuild'
ORDER BY TABLE_NAME;
```

For every table:

- confirm it can be opened;
- record row count;
- confirm `ENGINE=InnoDB` where expected;
- confirm no 1932 error;
- inspect foreign-key constraints;
- inspect unique indexes;
- inspect auto-increment values where relevant.

Run application-critical queries such as:

```sql
SELECT id, name, email, role, vendor_status
FROM cmart_db_rebuild.users
WHERE email='vendor@cmart.com';
```

Do not expose password hashes or tokens.

# Phase I — Application validation

With Laravel still temporarily connected to `cmart_db_rebuild`, validate:

1. Laravel boot and database connection.
2. Authentication query for `vendor@cmart.com` no longer throws 1932.
3. API login behaviour using trusted demo credentials from the seeder.
4. Current user/profile endpoint.
5. Organizer, community/vendor, CMart management, and super-admin role access boundaries.
6. Core event/listing endpoints.
7. Booking/site/layout endpoints.
8. Item reservation and extra-charge-related endpoints.
9. Relevant Python analytics database connection or a safe smoke test, without running destructive analytics writes.
10. No new critical MariaDB errors.

Run the most relevant existing automated tests that can safely use the rebuild or isolated test databases. Do not point destructive tests at `cmart_db` or `cmart_db_rebuild` unless the test design is verified non-destructive.

At minimum, attempt:

- targeted backend authentication/database smoke tests;
- migration/schema-related backend tests;
- critical frontend unit tests if they do not require destructive database reset;
- relevant E2E only if their configured database is isolated and verified.

If full suites require missing `cmart_test` or `cmart_e2e_db`, do not silently repoint them to the rebuild. Report the requirement and stop that test path.

# Phase J — Restore safe stopped state

At the end of this authorized task:

- keep `cmart_db_rebuild` intact;
- keep the damaged `cmart_db` intact;
- keep the forensic backup intact;
- do not perform final cutover;
- do not drop either database;
- do not rename databases;
- do not start salvage.

Leave Laravel connected to `cmart_db_rebuild` only if necessary for immediate user validation and clearly report that temporary state.

Otherwise restore `.env` to its exact pre-execution value (`DB_DATABASE=cmart_db`), clear config cache, and leave the application in maintenance mode because the original database remains damaged.

Choose the safer ending based on evidence and state it explicitly. Never leave the connection target ambiguous.

---

## 5. Required reports

Create or update:

```text
report/06_recovery_execution_log.md
report/07_post_recovery_validation.md
report/08_unrecovered_data_and_limitations.md
report/recovered_data_manifest.md
report/RECOVERY_README.md
report/command_log.md
report/09_project_and_database_recovery_progress_2026-07-20.md
```

Because no original data salvage is authorized in this task, `recovered_data_manifest.md` must clearly distinguish:

- schema reconstructed from migrations;
- demo data recreated from seeders;
- original/manual data not yet recovered;
- tables pending separate salvage assessment.

Update the canonical status checklist in report `09` with exact facts only.

Do not commit:

- `.env`;
- database files;
- SQL dumps containing personal data or credentials;
- forensic backup contents;
- secrets.

Reports may be committed only after reviewing for secrets and machine-specific sensitive values.

---

## 6. Acceptance criteria

The authorized rebuild is successful only when all applicable criteria pass:

- forensic backup exists and is verified;
- original `cmart_db` and its physical files were not modified by the rebuild process;
- `cmart_db_rebuild` exists with correct charset/collation;
- all current migrations complete successfully;
- `php artisan migrate:status` is consistent;
- expected current tables exist;
- no rebuild table returns error 1932;
- expected demo users and canonical roles exist;
- seeders are verified idempotent or limitations are documented;
- the `vendor@cmart.com` authentication query works;
- critical API/application smoke tests pass or exact blockers are documented;
- MariaDB logs show no new critical corruption related to the rebuild;
- all commands, outputs, row counts, limitations, and final connection state are documented;
- no cutover, salvage, rename, or destructive original-database action occurred.

---

## 7. Stop conditions

Stop immediately and report before continuing if:

- forensic backup cannot be completed or verified;
- `cmart_db_rebuild` unexpectedly already exists;
- the original datadir changes during the procedure outside expected normal server activity;
- a migration references or modifies `cmart_db` explicitly;
- a migration attempts destructive operations outside the empty rebuild database;
- a migration fails due to an unresolved historical-data assumption;
- a seeder would overwrite unknown recovered/manual data;
- credentials or secrets appear in proposed report/commit output;
- MariaDB emits new critical InnoDB corruption errors;
- proceeding would require salvage, forced recovery, tablespace import, drop, rename, or final cutover.

---

## 8. Final response format

Return a strict final report with these headings:

```markdown
# Database Rebuild Execution Result

## Verdict
## Original Database Preservation
## Forensic Backup
## Rebuild Database
## Migration Results
## Seeder Results
## Database Integrity
## Application Validation
## Automated Tests
## Original Data Status
## Files and Reports Changed
## Current `.env` / Connection State
## Unresolved Risks
## Exact Next Recommended Step
## Stop Confirmation
```

For every claim, include exact command evidence, counts, file/report paths, and pass/fail status.

Begin now with the preflight and forensic backup. Do not skip directly to migrations.
