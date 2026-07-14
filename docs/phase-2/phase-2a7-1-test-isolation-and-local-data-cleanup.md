# Phase 2A.7.1 — Test Isolation and Local Database Cleanup

## Original symptom

After Phase 2A.7, running the full backend suite left persistent rows in the local MySQL database:

```text
users: 6 → 8
carboot_events: 6 → 7   (observed once; not reproduced after cleanup hardening)
```

Booking-related tables remained clean (`bookings`, `invoices`, `booking_day_allocations` = 0).

---

## PHPUnit database connection

| Setting | Value |
|---------|--------|
| `APP_ENV` (phpunit.xml) | `testing` |
| `DB_CONNECTION` | `mysql` (from `.env`; **not** sqlite) |
| Database name | `cmart_db` |

`phpunit.xml` does **not** override `DB_CONNECTION` or `DB_DATABASE`. Feature tests therefore write to the same persistent local database used for development.

SQLite isolation was **not** introduced in this phase because:

- the repository already relies on MySQL-specific constraints (allocation `active_lock` uniqueness, enum alterations);
- switching connections would require a separate migrated test database and broader CI/local setup;
- the smallest safe fix is reliable fixture cleanup on the existing connection.

---

## Root cause

### Primary: provisioned users without teardown

**Files:** `GovernanceAccessBoundaryTest.php`, `WebAnalyticsSecurityTest.php`

**Helper:** `requireUser()` (now replaced by `provisionUser()`)

When seeded accounts `organizer@cmart.com` or `venue@cmart.com` were absent, tests created them with `User::create()` and **never deleted them**. No `tearDown()` existed in these classes.

**Confirmed residue removed:**

| Table | ID | Marker | Creating tests | Safe to delete |
|-------|-----|--------|----------------|----------------|
| `users` | 363 | `organizer@cmart.com`, name "Carboot Organizer" | `GovernanceAccessBoundaryTest`, `WebAnalyticsSecurityTest`, `StaffOperationsSummaryTest` (lookup only) | Yes — PHPUnit-provisioned duplicate; canonical seeded organizer is `admin@cmart.com` |
| `users` | 364 | `venue@cmart.com`, role `cmart_management` | `GovernanceAccessBoundaryTest`, `WebAnalyticsSecurityTest` | Yes — test-only provisioning email; canonical demo is `staff@cmart.com` |

**Preserved (not deleted):**

- `admin@cmart.com`, `staff@cmart.com`, `vendor@cmart.com`, `super_admin`, community users
- All six real `carboot_events` (IDs 1–3, 38–40)
- `news`, `feedback`, `spaces`

### Secondary: incomplete manual cleanup in Phase 2A.7 tests

**Files:** `BookingCreationWithAllocationsTest.php`, `BookingAllocationLifecycleTest.php`, `OrganizerBookingWorkflowTest.php`

Weaknesses in the original manual `tearDown()`:

1. **Missing `booking_audit_logs` cleanup** before deleting bookings — vendor submission now writes audit rows; orphaned logs could block user deletion via FK on `actor_user_id`.
2. **`OrganizerBookingWorkflowTest::createBooking()`** created invoices without tracking IDs for teardown.
3. **No `finally` reset** of tracking arrays — a failed delete could leave stale IDs for the next test method in the same class.
4. **No centralized user dependency cleanup** (`personal_access_tokens`, `vendor_business_profiles`, audit actor FKs).

These issues could leave users/events behind when cleanup partially failed. After hardening, full-suite runs no longer change counts.

### Event count drift (+1)

Re-investigation did **not** reproduce a persistent `carboot_events` increase after the cleanup trait was applied. The single observed `7` was likely transient residue from an interrupted run or a row since cleaned by another test's teardown. No event rows matching test title patterns remained at investigation time.

---

## Fix implemented

### New test infrastructure

| File | Purpose |
|------|---------|
| `tests/Concerns/CleansUpTestFixtures.php` | Central reverse-FK cleanup with `try/finally` array reset |
| `tests/Concerns/TracksProvisionedUsers.php` | Tracks `provisionUser()` creations and removes them in `tearDown()` |

**Cleanup order:**

```text
booking_day_allocations → invoices → booking_audit_logs → bookings
→ event_days → event_sites → carboot_events
→ personal_access_tokens / preferences / vendor profiles / audit actor logs → users
```

### Tests updated

- `BookingCreationWithAllocationsTest` — uses `CleansUpTestFixtures`
- `BookingAllocationLifecycleTest` — uses `CleansUpTestFixtures`
- `OrganizerBookingWorkflowTest` — uses `CleansUpTestFixtures`; tracks invoices from `createBooking()`
- `GovernanceAccessBoundaryTest` — uses `TracksProvisionedUsers`
- `WebAnalyticsSecurityTest` — uses `TracksProvisionedUsers`
- `StaffOperationsSummaryTest` — uses `admin@cmart.com` instead of removed `organizer@cmart.com` for demo lookup

### phpunit.xml

Added comment documenting that tests use the default MySQL connection and require fixture cleanup.

### Helper scripts (non-test)

- `scripts/count_baseline.php` — count verification without exposing credentials
- `scripts/remove_test_user_residue.php` — one-time removal of confirmed residue (already executed)

---

## Validation results

### Focused Phase 2A suites

| Command | Result |
|---------|--------|
| `--filter=BookingCreationWithAllocations\|BookingAllocationLifecycle\|BookingDayAllocationReservation\|AllocationHistoryProtection` | 44 passed |
| `--filter=OrganizerBookingWorkflow\|CommunityVendorBookingAccess\|GovernanceAccessBoundary\|VendorDemoPayment\|StaffOperationsSummary` | 33 passed |

### Full suite stability (two consecutive runs)

**Corrected baseline after residue removal:** `users=4`, `carboot_events=6`

| Run | Tests | Assertions | Skipped | Result | users after | events after |
|-----|------:|-----------:|--------:|--------|------------:|-------------:|
| 1 | 137 | 492 | 3 | Pass | 4 | 6 |
| 2 | 138 | 498 | 2 | Pass | 4 | 6 |

After `StaffOperationsSummaryTest` fix, final verification run: **138 passed, 2 skipped, 498 assertions** — counts unchanged.

---

## Final persistent data matrix

| Table | Corrected baseline | After run 1 | After run 2 |
|-------|-------------------:|------------:|------------:|
| `users` | 4 | 4 | 4 |
| `carboot_events` | 6 | 6 | 6 |
| `event_sites` | 0 | 0 | 0 |
| `event_days` | 0 | 0 | 0 |
| `spaces` | 2 | 2 | 2 |
| `bookings` | 0 | 0 | 0 |
| `invoices` | 0 | 0 | 0 |
| `booking_day_allocations` | 0 | 0 | 0 |
| `booking_audit_logs` | 0 | 0 | 0 |
| `news` | 1 | 1 | 1 |
| `feedback` | 5 | 5 | 5 |

---

## Remaining limitations

- Tests still run against `cmart_db`, not an isolated database — cleanup discipline is mandatory for every new fixture-creating test.
- Two tests may skip when specific seeded demo users are absent (`staff@cmart.com`, community vendor) — skips do not mutate counts.
- A dedicated `cmart_test` database remains a future Option A improvement for CI/local hardening.

---

## Readiness

Repository is **ready for Phase 2A.8 — Frontend Event-Site Availability and Cinema-Style Site Selection Integration**.

No application feature behavior was changed. No frontend files were modified.
