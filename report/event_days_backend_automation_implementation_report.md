# Event Days Backend Automation Implementation Report

## 1. Executive Summary

E.D.1 implements **backend-first automatic `event_days` materialization** on Carboot event create/update, reusing and extending `EventDayGenerator`. Organizers continue to enter only start/end times; the system creates the correct internal day rows transactionally. Date-affecting edits are blocked with HTTP `409` when booking-day allocation history exists. A dry-run-default Artisan command identifies existing zero-day events (including Kedah id=4) for a later approved apply. Persistent backfill was **not** executed. Feature tests were written but **could not run** because isolated database `cmart_test` is missing.

## 2. Approved Product Rules Implemented

| Rule | Status |
|------|--------|
| Keep persistent `event_days` / allocations architecture | Implemented |
| Organizer enters dates only; no Event Days Management UI | Implemented |
| Same-calendar-day → exactly one day | Implemented (`calendar_days` / materialize) |
| Multi-day → inclusive calendar dates | Implemented |
| Vendor per-day selection deferred | Not changed |
| Create + days atomic | Implemented |
| Sync on date change without allocations | Implemented |
| Block date/mode change with allocations | Implemented |
| Manual day APIs remain | Implemented + range validation strengthened |
| Backfill dry-run default; no persistent apply | Implemented / apply not executed |

## 3. Repository Baseline

| Item | Value |
|------|--------|
| Branch | `main` |
| Commit | `2f0d91399a37763d91d0b75ecd9df168b3556520` |
| PHP | 8.2.12 |
| Laravel | 11.51.0 |
| Node / npm | v24.15.0 / 11.12.1 |
| App DB | `cmart_db_rebuild` |
| PHPUnit DB | `cmart_test` (**missing**) |
| Config cache | Not cached |
| Pre-existing untracked | `report/event_days_deep_audit_and_decision_report.md` (unchanged content by this task) |

Audit report behaviour still matched repository state before coding (no generator on create/update; readiness requires active days; no Organizer day UI).

## 4. Previous Behaviour

- `CarbootEventController::store/update` saved the event only.
- Days required separate `POST /api/organizer/events/{id}/days/generate`.
- Layout readiness showed `NO_ACTIVE_EVENT_DAYS` with no actionable UI.
- Manual day create/update did not enforce parent event date range.
- Rebuild seed left **0** `event_days` for all events.

## 5. Implemented Architecture

```text
Organizer save event
  → validate payload
  → (update) if schedule fields change + allocation history → 409, no writes
  → DB::transaction
       → create/update carboot_events
       → EventDayGenerator::materializeForEvent()
            → plan days (calendar_days | single_session)
            → if match existing → unchanged
            → else replace only when no allocation history
  → commit
  → images attached after successful commit (unchanged pattern)
```

Core service: `backend/app/Services/EventDayGenerator.php`

New methods:

- `materializeForEvent()` — idempotent create/sync
- `eventHasAllocationHistory()`
- `assertDayFitsEvent()` — shared range guard
- `planForEvent()` — public planning used by backfill

Manual `generate()` retained for advanced/API use.

## 6. Event Creation Behaviour

- Validated create runs inside a transaction.
- `materializeForEvent` always runs after insert.
- Failure rolls back the event row.
- API response shape unchanged (`message` + `event`).

## 7. Event Update and Synchronization Behaviour

Schedule fields watched: `starts_at`, `ends_at`, `day_generation_mode`.

| Case | Behaviour |
|------|-----------|
| Schedule unchanged (including resave of same datetimes) | Event metadata updates; days untouched |
| Schedule changed, no allocation history | Event updated; days rematerialized |
| Schedule changed, allocation history | **409** before any update |
| Metadata-only with allocations | Allowed |

## 8. Allocation-History Protection

- Pre-check in `CarbootEventController::update`
- Defense in `materializeForEvent` / `generate(replace)`
- Manual generate mode change also blocked when history exists
- Error code: `event_operating_dates_locked_by_allocations`

## 9. Manual API Range Validation

`EventDayController` store/update now call `assertDayFitsEvent`:

- `operational_date` within event calendar range (MYT)
- day `starts_at`/`ends_at` within parent event window

Error code: `event_day_outside_event_range` (HTTP 422).

## 10. Existing-Data Backfill Command

```text
php artisan event-days:backfill-missing
php artisan event-days:backfill-missing --apply
```

- Default: dry-run (no writes)
- Prints resolved database name
- Targets only events with **zero** total `event_days`
- Uses `planForEvent` / `materializeForEvent`
- Skips invalid ranges / existing days
- Non-zero exit when apply failures occur

File: `backend/app/Console/Commands/BackfillMissingEventDaysCommand.php`

## 11. Single-Day Behaviour

Same calendar date under `calendar_days` → one active day preserving event start/end times (generator contract).

## 12. Multi-Day Behaviour

Inclusive MYT calendar dates; first/last day keep event wall-clock edges; middle days use 00:00:00–23:59:59.

`single_session` still yields one day (overnight supported).

## 13. Transaction and Failure Safety

- Create/update wrap event + materialize in `DB::transaction`
- Nested generator transaction uses Laravel savepoints
- Conflict path performs no partial schedule update
- Images remain post-commit side effects (pre-existing pattern)

## 14. Authorization and Security Impact

- No role middleware changes
- Day mutation routes remain organizer/super_admin
- CMart Management / community still denied
- No ownership/branch redesign (deferred)
- Manual APIs not removed; range validation strengthened

## 15. Tests Added or Updated

| File | Coverage |
|------|----------|
| `tests/Feature/EventDayAutomationTest.php` | Create single/multi, idempotency, auth, update sync/shorten/extend, allocation lock, metadata-with-history, out-of-range manual days, readiness without `NO_ACTIVE_EVENT_DAYS`, single_session overnight |
| `tests/Feature/BackfillMissingEventDaysCommandTest.php` | Dry-run no writes, apply eligible, skip existing, skip invalid range, idempotent re-apply |

Existing Phase 2A.5 tests left in place; model-direct fixtures still create days manually where needed.

## 16. Validation Commands and Results

```text
php -l (EventDayGenerator, CarbootEventController, EventDayController,
        BackfillMissingEventDaysCommand, both new test files)
→ No syntax errors detected

php artisan event-days:backfill-missing
→ Resolved database: cmart_db_rebuild
→ Mode: DRY-RUN
→ scanned=4 eligible=4 generated=0 skipped=0 failed=0
→ Eligible IDs: 1, 2, 3, 4 (Kedah planned_days=1)

php artisan test --filter=EventDayAutomationTest
→ FAIL: SQLSTATE[HY000] [1049] Unknown database 'cmart_test'
→ Tests correctly targeted phpunit DB (not app DB)
```

## 17. Test Database Status

| Check | Result |
|-------|--------|
| `phpunit.xml` DB | `cmart_test` |
| Database exists | **No** |
| Feature tests executed successfully | **No — blocked** |
| Tests pointed at `cmart_db_rebuild` | **No** (guard/phpunit keep `cmart_test`) |

Creating `cmart_test` was not approved in this task.

## 18. Backfill Dry-Run Results

Against `cmart_db_rebuild`:

| Event ID | Title | Planned days | Notes |
|---------:|-------|-------------:|-------|
| 1 | CMart Weekly Carboot | 1 | Eligible |
| 2 | CMart Weekly Carboot (Almost Full) | 1 | Eligible |
| 3 | Changlun Mega Carboot | 1 | Eligible |
| 4 | Kedah International Kedah | 1 | Eligible; layout ready; still missing days until apply |

Post dry-run verification: `event_days` count remained **0**. Kedah readiness still `NO_ACTIVE_EVENT_DAYS` only.

## 19. Persistent Database Changes

**None.** No `--apply`, no seed, no migrate, no manual inserts, no `.env` switch.

Live events (including Kedah) are **not yet repaired**.

## 20. Files Modified

**Modified**

- `backend/app/Services/EventDayGenerator.php`
- `backend/app/Http/Controllers/Api/CarbootEventController.php`
- `backend/app/Http/Controllers/Api/EventDayController.php`
- `frontend/src/views/dashboards/staff/StaffEventsPanel.vue` (409 copy + strip status prefix)

**Added**

- `backend/app/Console/Commands/BackfillMissingEventDaysCommand.php`
- `backend/tests/Feature/EventDayAutomationTest.php`
- `backend/tests/Feature/BackfillMissingEventDaysCommandTest.php`
- `report/event_days_backend_automation_implementation_report.md` (this file)

**Not modified**

- `report/event_days_deep_audit_and_decision_report.md`
- Migrations, seeders, allocation/readiness core rules (except consuming auto days)
- Layout Management UI / event-day management screens

## 21. Deferred Scope

- Vendor day-selection / “select all days” UX
- Per-day pricing
- Organizer advanced day management screen
- Session morning/evening model
- Ownership/branch tenancy
- Persistent `--apply` backfill
- Automatic deploy-time backfill

## 22. Known Limitations

1. Existing zero-day events remain until an approved `--apply`.
2. Feature tests unexecuted without `cmart_test`.
3. Metadata-only updates do not auto-heal legacy zero-day events (by design; use backfill).
4. Shared-tenant Organizer access to all events unchanged.
5. Image upload still occurs after the event/days transaction (pre-existing).

## 23. Repository Change Verification

`git status` after implementation (excluding this report until written):

- Modified backend controllers/services + StaffEventsPanel
- New command + two Feature test files
- Pre-existing untracked audit report retained
- Unrelated `PublicLanding.vue` local edit was reverted and is not part of this change set

## 24. Final Implementation Verdict

**Implemented:** automatic transactional `event_days` materialization on event create and safe date sync/block on update, plus manual range validation and a dry-run backfill command.  
**Tested:** syntax + dry-run against `cmart_db_rebuild`; Feature tests authored but **unable to test** without `cmart_test`.  
**Persistent data:** **not yet repaired** — Kedah and other events still have zero days until explicit apply approval.
