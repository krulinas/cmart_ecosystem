# Canonical Project Memory and Database Recovery State

**Project:** Carboot@CMart Digital Platform for Smart Community Engagement  
**Repository:** `krulinas/cmart_ecosystem`  
**Default branch:** `main`  
**Canonical snapshot date:** 2026-07-20 (UTC+8, Asia/Kuala_Lumpur)  
**Purpose:** Durable source of truth for future ChatGPT/Cursor sessions, project continuation, database recovery, audit, and final reporting.  
**Security:** No passwords, API keys, tokens, private `.env` values, or raw personal documents are stored here.

---

## 1. Project identity and purpose

The project is an organizer-led digital platform for the **Carboot@CMart** community reuse programme in Changlun. It is not merely a generic CMart management system and not merely a parking booking application.

The platform exists to:

1. Support coordination, participation, and systematic data collection for Carboot@CMart.
2. Digitize vendor registration, approval, site booking, payment-recording instructions, withdrawal, check-in, item listing, reservation, walk-ins, and event layout operations.
3. Evaluate usability and user experience among vendors, students, organizers, and community participants.
4. Analyse participation, economic outcomes, reuse/recycle activity, environmental indicators, social indicators, and broader ESG evidence.
5. Generate reports and analytics that make the programme's impact visible to organizers, researchers, CMart management, and stakeholders.
6. Support the Smart Changlun living-lab direction and future academic outputs, including usability/analytics reporting, publication, and IP documentation.

The platform does **not** act as a real payment gateway in the current project scope. It may record payment instructions, proof, verification state, invoices, estimated transactions, and revenue-related data, but actual financial processing is outside the current core scope.

---

## 2. Canonical technology and repository structure

Repository root: `cmart_ecosystem`

Primary folders:

- `backend/` — Laravel 11.51.0, Sanctum authentication, MySQL/MariaDB connection
- `frontend/` — Vue 3, Vite, Pinia, Axios, Tailwind CSS, vue-toastification
- `python_analytics/` — Python-based analytics using project database data
- `report/` — project audit, recovery, and continuity documentation

Testing stack:

- Laravel backend tests
- Vue frontend unit tests
- Mocha + `selenium-webdriver` + Headless Chrome for E2E

Local development environment at the time of database audit:

- Windows 10
- XAMPP
- PHP 8.2.12
- MariaDB 10.4.32
- Host: `127.0.0.1`
- Port: `3306`
- Main database name: `cmart_db`
- MariaDB datadir: `D:\Program Files\xampp\mysql\data\`
- Main MariaDB config: `D:\Program Files\xampp\mysql\bin\my.ini`
- Workspace: `D:\Program Files\xampp\htdocs\cmart_ecosystem`

Laravel and phpMyAdmin were verified to target the same MariaDB instance and the same `cmart_db`. The current database failure is not caused by a wrong Laravel connection, wrong port, stale config cache, or another hidden MariaDB instance.

---

## 3. User workflow and Cursor prompt preferences

For this project, future AI-assisted development should follow these working rules:

- Use Cursor Pro Agent with inspect-first, senior-engineering prompts.
- Prompts should be 100% Markdown and copy-paste ready.
- Prompts should explicitly define repository anchors, scope, constraints, execution order, acceptance criteria, validation commands, persistent-data checks, exact final report format, and stop conditions.
- Cursor should inspect the codebase before editing and should never guess that a feature is absent or broken without evidence.
- Distinguish clearly between implemented, tested, deferred, unresolved, and assumed work.
- Prefer honest/no-mercy review over vague success claims.
- Avoid broad or ambiguous instructions such as “fix the database.”
- Keep Cursor context focused: reference relevant files, close noisy files, avoid unnecessary long chat history, and re-anchor important context in new sessions.
- For risky database or migration work, require explicit safety controls and staged approval.
- Normal explanations to the user should use basic, direct Manglish unless a full technical prompt/report is requested.
- For actual Cursor prompts, output the prompt itself without unnecessary introduction or outro.

---

## 4. Canonical governance and role model

The project underwent a scope correction away from a CMart-heavy management system toward an organizer-led Carboot@CMart platform.

### `organizer`

Represents UUM / the Carboot organizer and owns the broadest daily Carboot authority, including:

- vendor approval and operational review;
- bookings and site allocation;
- payment verification/recording workflows;
- passes and check-in;
- event layout and walk-in operations;
- raw operational analytics;
- report generation for CMart/stakeholders.

### `cmart_management`

Represents CMart venue-side management with intentionally limited authority:

- CMart provides the venue and may manage side activities/programmes such as fun runs, concerts, or other non-Carboot activities;
- may CRUD its permitted activities/news/programme records;
- may receive generated reports;
- must not interfere with organizer-controlled Carboot operations;
- must not approve vendor bookings, verify Carboot payments, perform Carboot check-in, or access full raw analytics.

### `community`

Registered public/vendor-side account role:

- vendor identity is represented through `vendor_status`, business/vendor profile, bookings, and related state;
- no separate canonical `vendor` database role is required;
- community accounts may become approved vendors through the vendor lifecycle.

### `super_admin`

Reserved technical/system role, not a daily business operator.

### Public visitor

Unauthenticated visitor, not stored as a role in the users table.

Canonical final role values expected by migrations:

- `community`
- `organizer`
- `cmart_management`
- `super_admin`

---

## 5. Proposal and stakeholder requirements

The proposal anchors the project around three objectives:

1. Develop a digital platform for Carboot@CMart coordination, participation, and data collection.
2. Evaluate the platform's usability and user experience.
3. Analyse participation data, economic outcomes, and ESG indicators using dashboards/visualisations.

Meeting/ICC feedback established these product requirements:

- Paid vendors may withdraw, but there is no refund; their booked site becomes available again.
- Items may be reserved, with an extra charge mechanism.
- Event layout should make category placement clear, including category-based rows such as clothing and food.
- Important public/vendor flows must include Bahasa Melayu.
- Full-system dual language is not required due to time and cost.
- Reuse and recycle concepts must be visible in the system.
- Vendor information and consent/data collection must support processing, research, and governance needs.
- Multi-day events should prioritize bookings covering the full event, while controlled exceptions may exist for emergencies or conflicting events.
- The system must support or account for walk-in vendors.
- Organizer replaces the former overly broad CMart operational role.
- CMart management receives generated reports instead of direct access to raw analytics.
- The system must avoid noisy, overloaded functions and remain easy for ordinary public users.
- Analytics should cover environmental and social outcomes, including reused items and estimated waste reduction.

---

## 6. Selective bilingual decision for Phase 5

The canonical language strategy is **selective bilingual**, not fully bilingual.

- Bahasa Melayu is the default for critical public/vendor flows.
- Users should have a Bahasa Melayu / English toggle in critical flows.
- Admin, helper, technical, and non-critical internal areas may remain English unless a specific need is identified.
- Do not use “main English with tiny BM translation underneath” as the primary pattern; it creates visual noise and weakens the BM-first requirement.

Critical toggle-enabled flows include:

- vendor registration;
- site booking;
- payment instructions;
- withdrawal and no-refund warnings;
- reservation and extra-charge warnings;
- reuse/recycle explanations;
- layout instructions;
- walk-in vendor forms;
- consent and data collection notices.

The strongest justification is usability and accessibility for ordinary local users while controlling development cost and avoiding a visually overloaded interface.

---

## 7. Canonical phase history and accepted states

### The New Beginning / scope correction

The project reset is treated as a governance and scope correction rather than a simple feature-addition phase. The proposal objectives remain the anchor, but daily Carboot authority moved to the organizer role.

### Phase 1 — Organizer-led governance restructure

Canonical governance decisions were established as described in Section 4. CMart management is venue-side and report-receiving; organizer owns Carboot operations and analytics.

### Phase 2

Phase 2 forms part of the completed historical implementation leading into the hardened Phase 3 state. Future work must inspect current repository evidence before changing Phase 2 behaviour; do not assume historical features are missing merely because the live local database is damaged.

### Phase 3 — Closed

Phase 3.11, **Hardening, Migration Readiness and Closure**, is complete.

Official verdict:

> `PHASE 3.11 COMPLETE — PHASE 3 CLOSED, READY FOR PHASE 4`

Accepted closure evidence:

- Phase 3 product work formally closed.
- Phase 3.4A rollback corrected so append-only audit history cannot be silently deleted.
- Guarded rollback refuses collisions safely.
- Duplicate-specific audit insertion suppresses only exact MySQL/MariaDB duplicate-key errors and rethrows other database errors.
- All 19 previously skipped backend tests converted to deterministic fixtures.
- Repository-wide lint clean: Oxlint 0 errors, ESLint 0 errors.
- Backend: 320 passed, 0 failed, 0 skipped, 1570 assertions.
- Frontend unit: 80 passed, 0 failed.
- E2E: Phase 3.9 = 5 passed; Phase 3.10 = 6 passed; Phase 3.11 = 7 passed.

### Phase 4 — Complete and resolved

Phase 4, **Item Reservation & Extra Charge**, is complete and resolved.

Phase 4.5 final closure:

- Backend: 374 passed, 0 failed, 0 skipped, 2166 assertions.
- Frontend unit: 95 passed.
- Phase 4.5 E2E: 7/7 passing.
- Fixture cleanup on `cmart_e2e_db`: all zero residue.

### Post-Phase-4 visual parking layout builder — Accepted

A shared real-data visual parking layout component was completed and accepted for organizer, vendor, and public modes.

Canonical layout:

- 4 rows: A, B, C, D
- 16 sites per row
- 64 sites total
- Exit above Row A
- Vehicle aisle between Rows B and C
- Entrance below Row D

Organizer mode:

- English-only internal mode is acceptable.
- Visual layout is the primary interaction.
- Site statuses are displayed directly on tiles.
- Organizer can generate and manage real parking sites.

Vendor/public modes use the same real layout data according to their permissions and booking/viewing flows.

### Phase 5 — Started / next product phase

Phase 5 is **BM-First Critical Flow & UX Simplification**.

Purpose:

- make the system feel less academic and less heavy;
- prioritize Bahasa Melayu for important public/vendor journeys;
- reduce noisy or overloaded functionality;
- make critical flows easy for ordinary users.

The selective bilingual decision in Section 6 is canonical for this phase.

---

## 8. Database incident: current verified state

### User-visible symptom

Laravel login/query fails with:

```text
SQLSTATE[42S02]: Base table or view not found: 1932
Table 'cmart_db.users' doesn't exist in engine
(Connection: mysql, SQL: select * from `users` where `email` = vendor@cmart.com limit 1)
```

phpMyAdmin displays table names in the navigation tree, but opening tables returns:

```text
#1932 - Table 'cmart_db.<table>' doesn't exist in engine
```

### Scope

The problem affects all 21 visible tables in the current `cmart_db`, including `users` and `migrations`.

### Physical evidence

Directory:

```text
D:\Program Files\xampp\mysql\data\cmart_db\
```

Observed:

- 21 `.frm` files;
- 21 `.ibd` files;
- `db.opt`;
- total database folder size approximately 2.35 MB;
- table files still physically exist.

### Engine/dictionary evidence

- `SHOW TABLE STATUS FROM cmart_db` reports `ENGINE=NULL` for all tables.
- `information_schema.TABLES` shows all 21 entries with null engine/collation state.
- `information_schema.INNODB_SYS_TABLES WHERE NAME LIKE 'cmart_db/%'` returns 0 rows.
- `SHOW CREATE TABLE cmart_db.users` fails with error 1932.
- No table is currently healthy/readable.

### Root-cause diagnosis

The most likely cause is an incomplete or incompatible physical database-folder recovery after deletion:

- the old `cmart_db` table files were copied/restored without the matching InnoDB system state;
- or `ibdata1`/redo logs were replaced or reset while old `.ibd`/`.frm` files remained;
- therefore SQL metadata/file names remain visible, but InnoDB no longer recognizes the tablespaces.

MariaDB error-log evidence includes a log sequence number mismatch and the message that an InnoDB tablespace may have been copied without the matching InnoDB log files.

### Timeline reconstructed from logs

- 2026-06-28 09:20 — Laravel reports `Unknown database 'cmart_db'`.
- 2026-06-28 09:21 onward — database/folder appears recreated; migration/table state incomplete.
- 2026-07-07 — database appears to have functioned temporarily and accepted writes.
- 2026-07-08 03:18 — MariaDB logs repeated InnoDB LSN/tablespace mismatch warnings.
- 2026-07-20 04:31 — error 1932 begins appearing broadly.
- 2026-07-20 around 10:35–10:43 — timestamps suggest another physical recovery attempt or system-file change.

### Connection diagnosis

This is not caused by:

- wrong `.env` database name;
- wrong host/port;
- stale Laravel config cache;
- a second hidden MariaDB instance;
- a simple missing `users` migration.

Laravel and phpMyAdmin both target `cmart_db` at `127.0.0.1:3306` on MariaDB 10.4.32.

---

## 9. Database audit reports already produced

The following Stage A reports exist in `report/`:

- `00_database_recovery_summary.md`
- `01_environment_and_connection_audit.md`
- `02_schema_and_data_source_inventory.md`
- `03_database_corruption_findings.md`
- `04_recovery_options_and_risk_matrix.md`
- `05_proposed_recovery_runbook.md`
- `command_log.md`
- this canonical file: `09_project_and_database_recovery_progress_2026-07-20.md`

Stage A was read-only. No `DROP`, `TRUNCATE`, `migrate:fresh`, data-directory replacement, or destructive repair was performed during the audit.

---

## 10. Schema and data recovery sources

### Schema

The repository contains 65 Laravel migration files. The schema can be reconstructed with very high confidence.

Expected tables include, among others:

- `users`
- `password_resets`
- `failed_jobs`
- `personal_access_tokens`
- `spaces`
- `bookings`
- `invoices`
- `feedbacks`
- `carboot_events`
- `news_posts`
- `event_user`
- `jobs`
- `booking_audit_logs`
- `vendor_business_profiles`
- `vendor_items`
- `management_profiles`
- `reuse_item_images`
- `event_images`
- `news_images`
- `user_booking_preferences`
- `vendor_categories`
- `event_sites`
- `event_days`
- `booking_day_allocations`
- `booking_attendance_exceptions`
- `booking_attendance_exception_days`
- `category_migration_audits`
- `event_layout_rows`
- `event_layout_audit_logs`
- `booking_category_overrides`
- `item_reservations`
- `item_reservation_audits`

The damaged `cmart_db` contains only 21 visible pre-later-phase tables. Several Phase 3/4 tables were never present in that local database and will be created normally when current migrations run against a clean database.

### Demo/seed data

`DatabaseSeeder` uses idempotent patterns such as `updateOrCreate` and can recreate trusted demo/system data, including:

- `vendor@cmart.com` — community, approved vendor
- `vendor_b@cmart.com` — community, approved vendor
- `admin@cmart.com` — organizer
- `staff@cmart.com` — cmart_management
- `hq@cmart.com` — super_admin
- `organizer@cmart.com` — organizer
- `venue@cmart.com` — cmart_management

It also creates selected demo spaces, Carboot events, news posts, booking/invoice data, and management profiles. Additional seeders include feedback and vendor categories.

### Original/manual data

Migrations can restore structure, not deleted rows. Seeders can restore only known demo data. Original/manual users, bookings, feedback, profiles, tokens, and operational rows can be restored only if:

- a logical backup is found; or
- the surviving `.ibd` files can be salvaged successfully.

No relevant `.sql`, `.sql.gz`, `.dump`, or Git-tracked database backup was found in the locations inspected during Stage A.

---

## 11. Recovery probability and difficulty

Practical estimates based on current evidence:

- Restore a working development application: **90–97% success probability**.
- Rebuild current schema from migrations: **95%+ success probability**.
- Recreate trusted demo data: **95%+ success probability**.
- Recover every original/manual row exactly: **approximately 30–60%**, depending on `.ibd` integrity.
- `IMPORT TABLESPACE` fallback: high-risk, with lower estimated success and strict prerequisites.

Difficulty rating:

- Restore development functionality: approximately **25% difficulty**.
- Restore the exact original database and all manual rows: approximately **82% difficulty**.

The project source code and accepted development work are not considered lost merely because the local live database is damaged.

---

## 12. Approved safe strategy

The preferred strategy has two separate tracks.

### Track A — Restore development safely

1. Preserve a full forensic copy.
2. Create a new clean database named `cmart_db_rebuild`.
3. Run current migrations against the new database.
4. Review and run seeders.
5. Validate backend, frontend integration, API, authentication, layout, booking, reservation, and other critical flows.
6. Keep the damaged `cmart_db` untouched until the rebuild is proven.
7. Later, after explicit approval, export/import the validated rebuild into a clean final database named `cmart_db`, or retain the rebuild name through `.env` if appropriate.

### Track B — Attempt original-data salvage separately

1. Work only from a cloned full datadir.
2. Use an isolated MariaDB 10.4-compatible instance, for example port 3307.
3. Start with `innodb_force_recovery=1`.
4. Increase only to levels 2 and 3 if needed and documented.
5. Do not use level 4+ without separate explicit approval and risk warning.
6. Export any readable tables immediately.
7. Import recovered rows into the clean rebuild only after schema and foreign-key validation.

Track A does not depend on Track B succeeding. This allows development to continue while preserving the possibility of recovering original data.

---

## 13. Non-negotiable database safety constraints

Never perform the following on the original damaged database/datadir without explicit, separate approval:

```text
php artisan migrate:fresh
php artisan migrate:refresh
DROP DATABASE cmart_db
DROP TABLE ...
TRUNCATE ...
DELETE without a verified backup and explicit scope
```

Never delete or replace the original:

```text
ibdata1
ib_logfile0
ib_logfile1
*.ibd
*.frm
*.sdi
MariaDB redo/undo/system files
```

Additional rules:

- Stop application writes before copying the datadir.
- Make a timestamped full datadir copy before any recovery or rebuild activity.
- Keep the original damaged database as evidence until successful cutover and backup verification.
- Do not treat `innodb_force_recovery` as a repair mechanism; it is salvage/read-access only.
- Do not expose `.env` secrets in logs, reports, commits, or chat.
- A file renamed `*.redacted` is not actually redacted unless its contents are sanitized.
- Never improvise `IMPORT TABLESPACE`; verify exact compatible schema, version, page size, row format, file-per-table state, encryption/compression, and metadata prerequisites first.

---

## 14. Previous unauthorized rebuild correction

An earlier unauthorized temporary database rebuild was removed and configuration was restored:

- Exact temporary database removed: `cmart_rebuilt_db`.
- Deletion was verified through `INFORMATION_SCHEMA`.
- `cmart_db` was preserved.
- Laravel `.env` was restored to `DB_DATABASE=cmart_db`.
- Laravel config cache was cleared and runtime resolution confirmed `cmart_db`.
- Other database connection settings were preserved.
- No remaining temporary rebuild bootstrap file was found.

This correction does not repair the current `cmart_db` InnoDB damage. Future rebuild work must use the approved name `cmart_db_rebuild`, preserve forensic evidence, and follow the staged plan in this file.

---

## 15. Current canonical status

Completed:

- [x] Proposal, draft proposal, and ICC feedback reviewed.
- [x] Organizer-led governance model established.
- [x] Phase 3 closed with accepted tests and hardening.
- [x] Phase 4 / 4.5 closed with accepted tests.
- [x] Shared visual parking layout builder accepted.
- [x] Phase 5 selective bilingual direction established.
- [x] Database Stage A read-only audit completed.
- [x] Database root cause classified as InnoDB dictionary/tablespace mismatch.
- [x] Migration and seeder recovery sources inventoried.
- [x] Recovery risk matrix and runbook prepared.
- [x] Canonical progress record stored in GitHub.

Not yet completed:

- [ ] Timestamped forensic copy of full MariaDB datadir/config/logs.
- [ ] Clean `cmart_db_rebuild` creation.
- [ ] Migration execution against the clean rebuild.
- [ ] Seeder review and execution.
- [ ] Application and database post-rebuild validation.
- [ ] Separate original-data salvage attempt on an isolated clone.
- [ ] Import of any salvaged original rows.
- [ ] Final cutover to a clean database named `cmart_db`.
- [ ] Final backup automation and recovery documentation closure.

---

## 16. Immediate next action

Proceed with a **safe database rebuild track** only after creating the forensic backup.

The next Cursor execution should:

1. Re-read reports `00`–`05`, `command_log.md`, and this canonical file.
2. Verify no new writes or database changes occurred after Stage A.
3. Create and verify a timestamped forensic backup.
4. Create `cmart_db_rebuild` without touching `cmart_db`.
5. run migration inspection, `--pretend`, then migration;
6. review and run seeders;
7. perform comprehensive database/application validation;
8. write execution and validation reports;
9. stop before salvage, cutover, rename, drop, or `.env` permanent switch unless separately approved.

---

## 17. Source-of-truth rule for future sessions

For future ChatGPT/Cursor sessions:

1. Treat this file as the canonical continuity snapshot as of 2026-07-20.
2. Inspect the latest repository state and newer reports before assuming this remains current.
3. Never overwrite accepted phase history or governance decisions without explicit user approval.
4. Record every completed recovery step, command, validation result, limitation, and new commit in `report/`.
5. Update this canonical file whenever the database status, phase status, or major project decision changes.
