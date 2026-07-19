# Phase 3 Migration Rollout Runbook

## Scope and approval gate

This runbook applies to the controlled migration of `cmart_db`. Phase 3.11 did not execute it.

Required gate:

```text
PHASE3_DEV_MIGRATION_APPROVED=true
```

The operator must set the flag deliberately in the release shell after backup verification. Application code does not infer or create approval.

## 1. Backup

1. Stop writes or establish a maintenance window.
2. Record current branch/commit and `php artisan migrate:status`.
3. Create a full logical backup including routines and triggers:

```bash
mysqldump --single-transaction --routines --triggers cmart_db > cmart_db_pre_phase3.sql
```

4. Verify the dump is non-empty and test restoration into a separate database.
5. Record row counts for users, profiles, preferences, items, events, sites, spaces, bookings, invoices, and audit logs.

Stop if backup or restore verification fails.

## 2. Database and privilege preflight

```bash
cd backend
php artisan phase3:preflight --json
```

Expected before first rollout:

- Database is exactly `cmart_db`.
- Engine is supported MySQL or MariaDB.
- Version is recorded.
- `create_trigger_privilege=true`.
- 14 Phase 3 migrations are pending.
- Phase 3 schema/trigger may be absent.
- No command-side mutation occurs.

Confirm `SHOW GRANTS FOR CURRENT_USER` includes `TRIGGER` or `ALL PRIVILEGES`. The tested environment is MariaDB 10.4.32. MySQL-compatible trigger syntax is used, but the target version must still be recorded.

## 3. Emergency stop conditions

Do not start, or stop before the next migration, if:

- the resolved database is not `cmart_db`;
- backup/restore verification is incomplete;
- trigger privilege is missing;
- unknown categories would require fuzzy mapping;
- storage or connection errors occur;
- a migration reports data truncation, FK failure, or unexpected schema;
- category audit history would need deletion;
- the migration account cannot query `information_schema`;
- an application process writes during the protected migration window.

## 4. Migration command

After explicit approval:

```bash
set PHASE3_DEV_MIGRATION_APPROVED=true
php artisan migrate --force
```

On PowerShell:

```powershell
$env:PHASE3_DEV_MIGRATION_APPROVED = "true"
php artisan migrate --force
```

Do not use `migrate:fresh`, `db:wipe`, or destructive reset commands on `cmart_db`.

## 5. Expected schema

Expected additions:

- `vendor_categories`
- `category_migration_audits`
- `event_layout_rows`
- `event_layout_audit_logs`
- `booking_category_overrides`
- canonical category FKs on bookings, profiles, preferences, and items
- booking category label snapshot
- event-site row FK and restrictive relationship
- public layout publication columns
- append-only audit unique index:
  `category_migration_audits_append_only_unique`
- trigger:
  `cmart_before_delete_carboot_event_layout`

Expected taxonomy: exactly 7 canonical categories.

## 6. Post-migration validation

```bash
php artisan migrate:status
php artisan phase3:preflight --json
php artisan test --env=testing --filter=Phase34ASchemaIntegrity
php artisan test --env=testing --filter=PublicEventLayout
```

Require:

- zero pending migrations;
- trigger present and definition verified;
- 7 canonical categories;
- unknown categories remain unresolved and audited;
- no FK/legacy or snapshot mismatch;
- no active site without a row;
- no active row without a category;
- no allocation or override invariant violation;
- published layouts remain public-ready.

Perform role smoke tests:

1. Organizer can open layout management.
2. Vendor can list categories and compatible site availability.
3. Existing bookings remain readable.
4. Public unpublished layouts remain unavailable.
5. Protected Organizer APIs deny community and CMart Management.

## 7. Rollback boundaries

Phase 3.4A rollback is guarded. If multiple observations share the legacy source identity, rollback throws before schema mutation. It never deletes or merges audit history.

Do not bypass the guard. Recovery options are:

1. restore the verified pre-migration backup; or
2. forward-fix the deployment while preserving the current append-only schema.

Rollback of the trigger drops database-side event deletion ordering. Rollback of later migrations may also be blocked by FK-protected operational history. Treat rollback as restore-or-forward-fix, not as an assumption that every migration can be reversed after live use.

## 8. Operational result template

```text
Backup verified:
Database/version:
Migration account:
Trigger privilege:
Preflight result:
Approval present:
Migration command:
Post-migration preflight:
Smoke tests:
Rollback required:
Operator:
Timestamp:
```

Phase 3.11 result:

```text
Rollout readiness: Prepared
Development migration executed: No
Development rollout prepared but not executed
```
