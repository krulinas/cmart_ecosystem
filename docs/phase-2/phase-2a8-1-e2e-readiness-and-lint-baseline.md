# Phase 2A.8.1 — Browser E2E Readiness, Vendor Eligibility Verification, and Lint Baseline Proof

## Result

**Completed — browser E2E verified and ready for Phase 2B.**

Real Selenium/Chrome browser flows (success + `409` conflict) were executed end-to-end
against temporary test-scoped `EventSite`/`EventDay` fixtures. All persistent database
counts returned exactly to baseline. All Phase 2A.8 and Phase 2A.8.1 files pass lint with
zero errors. One real conflict-handling defect was discovered by the browser conflict flow
and fixed.

---

## 1. Phase Objective

Verify and stabilize Phase 2A.8 (vendor event-site availability + cinema-style selection)
by proving, with browser-level and database-level evidence, that:

- vendor availability authorization matches the project's canonical booking-eligibility rule;
- the cinema selector works against real API data in a browser;
- booking submission sends real `event_site_ids` and creates real allocations;
- the `409` conflict path refreshes and prunes safely;
- temporary fixtures are fully cleaned and persistent counts are unchanged;
- Phase 2A.8 files introduce no new lint errors.

## 2. Original Phase 2A.8 Limitation

Phase 2A.8 could not run the browser booking flow because the persistent local database had
`event_sites = 0` and `event_days = 0`. There was no bookable event with a configured layout,
so the cinema selector had nothing to render. Phase 2A.8.1 removes this dependency by
introducing a **test-only fixture command** that provisions a self-contained bookable event
with active days and a contiguous active site row, then removes everything afterward.

## 3. Vendor-Eligibility Verification

### Canonical rule (unchanged)

The project's canonical booking-eligibility gate is **`role:community` membership only**:

- `App\Http\Middleware\EnsureVendorApproved` exists but is **intentionally dormant**
  ("Registered but intentionally not applied in Phase 1. Pending community vendors must
  retain /dashboard access during onboarding.").
- Both `POST /api/bookings` and `GET /api/vendor/events/{carboot_event}/site-availability`
  live inside the same `Route::middleware('role:community')` group in `backend/routes/api.php`.
- `Tests\Feature\CommunityVendorBookingAccessTest::test_community_visitor_can_submit_booking_without_vendor_approval`
  proves a `vendor_status = 'none'` community user may submit a booking.

Therefore the availability endpoint **already reuses the exact same eligibility gate** as
booking creation. Introducing a stricter `vendor_status = approved` check would create a
competing definition of vendor eligibility and break parity with `POST /api/bookings`, which
the phase explicitly forbids.

### Authorization decision

**No authorization change was required.** Parity tests were added to prove the rule.

### Availability authorization matrix (verified by tests)

| Actor                                              | Expected | Result |
| -------------------------------------------------- | -------: | -----: |
| Approved community vendor (`vendor_status=approved`) |    `200` |   `200` |
| Registered community user (`vendor_status=none`)   |    `200` |   `200` (parity with booking creation, by design) |
| Unauthenticated                                    |    `401` |   `401` |
| `cmart_management`                                 |    `403` |   `403` |
| Organizer on vendor-only route                     |    `403` |   `403` (policy preserved) |
| Super admin on vendor-only route                   |    `403` |   `403` (policy preserved) |

> Note on the task's assumed matrix: this project deliberately allows a **registered
> non-vendor community user** to load availability (they can also book), because
> `EnsureVendorApproved` is dormant during onboarding. Blocking them would diverge from
> `POST /api/bookings`. This is documented here as an intentional parity decision, not a gap.

`role:community` blocks organizer and super_admin because `ManagementRole::matches()` only
elevates `super_admin` to the `organizer` role, never to `community`.

### Authorization fix

None. Only tests were added (`backend/tests/Feature/VendorEventSiteAvailabilityTest.php`):

- `test_non_approved_community_user_can_load_availability` (`vendor_status=none` → `200`);
- `test_organizer_is_blocked_on_vendor_availability_route` (`403`);
- `test_super_admin_is_blocked_on_vendor_only_availability_route` (`403`).

## 4. E2E Fixture Strategy

A dedicated **test-only artisan command** provisions and removes fixtures. It has no HTTP
surface (CLI only, so it is not production-accessible) and refuses to run outside the `local`
environment (except under PHPUnit).

```
php artisan e2e:site-fixtures create --json   # provisions, prints dynamic IDs as JSON
php artisan e2e:site-fixtures cleanup          # removes everything by marker (idempotent)
```

File: `backend/app/Console/Commands/E2ESiteFixtures.php`.

The E2E harness drives this command from `frontend/tests/e2e/helpers/site-fixtures.js`
(`createSiteFixtures()` / `cleanupSiteFixtures()`), so the browser flow never depends on
permanent local demo layout data.

### Fixture data created (dynamic IDs)

| Item             | Value                                                        |
| ---------------- | ----------------------------------------------------------- |
| Approved vendor  | `e2e-site-fix-vendor@example.com` (role `community`, `vendor_status=approved`) |
| Bookable event   | `E2E-SITE-FIX Carboot Weekend` (`status=Open`, starts +9 days) |
| Active EventDays | 2 consecutive active days                                   |
| Space            | shared `Standard (1 Parking Lot)` (reused, RM30.00)         |
| EventSites       | `A01`, `A02`, `A03` — row `A`, positions 1–3, contiguous, active, same `space_id` |
| Markers          | event title / description contain `E2E-SITE-FIX`; email `e2e-site-fix%` |

All IDs are assigned by the database at creation time and emitted as JSON; nothing is
hardcoded. `create` first purges any stale fixture so IDs are always fresh and creation is
idempotent.

## 5. Fixture Cleanup Strategy

`cleanup` (and the implicit purge before every `create`) deletes in foreign-key-safe order
and is idempotent:

```
booking_day_allocations  (by fixture booking_id, and defensively by fixture event_site_id)
invoices                 (by fixture booking_id)
booking_audit_logs       (by fixture booking_id and actor_user_id)
bookings                 (fixture event bookings + fixture user bookings)
event_sites              (by fixture carboot_event_id)
event_days               (by fixture carboot_event_id)
carboot_events           (title LIKE 'E2E-SITE-FIX%')
personal_access_tokens / user_booking_preferences / vendor_business_profiles
users                    (email LIKE 'e2e-site-fix%')
```

- The shared `Space` catalogue is **never** deleted (baseline `spaces` stays intact).
- Cleanup runs from the spec `afterEach` hook and is best-effort so a failing assertion never
  leaks fixtures and never masks the original failure.
- No `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, or table truncation is used.

## 6. Browser Success Flow

- **Browser/driver:** Chrome (headless `--headless=new`) via `selenium-webdriver`, Vite E2E
  server on `http://localhost:5175`, API on `http://127.0.0.1:8000/api`.
- **Spec:** `frontend/tests/e2e/specs/vendor.site-selection.spec.js`
  → "loads real availability, selects adjacent sites, and submits event_site_ids".

Steps executed and asserted:

1. fixtures created programmatically (`before`/`beforeEach`);
2. logged in as the approved fixture vendor;
3. opened `/vendor-booking?event_id=<dynamic>`;
4. cinema selector rendered from real API data;
5. active EventDay summary displayed;
6. real fixture site tiles `A01`/`A02`/`A03` displayed;
7. selected two adjacent sites (`A01`, `A02`);
8. preview total asserted **RM 60.00** (2 × RM30, **not** multiplied by day count);
9. submit enabled only for a valid contiguous selection;
10. submitted → redirected to `/dashboard`, `vendor-dashboard-root` visible.

**Result: PASS.**

### Backend persistence assertions (live-API evidence)

Verified directly through the running Laravel server during development of the flow
(real HTTP + Sanctum auth):

- `POST /api/bookings` with `event_site_ids: [A01, A02]` → `201`, `approval_status=Pending_Organizer`;
- exactly **1 Booking**, **1 Invoice** (`amount = 60.00`);
- exactly **4 `BookingDayAllocation`** rows (2 sites × 2 active days), all with active occupancy;
- `site_selection` returned authoritative labels, day count, and `allocation_status=reserved`.

The submitted request carried only `event_site_ids` — no client `tapak_quantity`, `amount`,
`total_price`, `space_id`, or synthetic booth labels.

## 7. Browser Conflict Flow

- **Spec:** same file → "refreshes availability and prunes the selection when submission hits a 409 conflict".
- **Competing reservation:** created through a second authenticated API client
  (`POST /api/bookings` for the same site) — server state, not faked frontend state.

Steps executed and asserted:

1. vendor loaded availability and selected an available site (`A01`);
2. a competing reservation occupied `A01` before submission (asserted `201`);
3. vendor submitted the now-stale selection → backend returned `409`;
4. the conflict message appeared next to the selector (`event-site-selection-error`);
5. availability refreshed and `A01` became **disabled** and **unselected** (`aria-pressed=false`);
6. the invalid selection was pruned (selection summary removed);
7. the form remained on `/vendor-booking` — **no automatic resubmission**;
8. unrelated product fields (product details) were preserved.

**Result: PASS. True browser-level conflict coverage achieved.**

### Defect discovered and fixed

The conflict flow surfaced a real defect in `frontend/src/views/auth/Registration.vue`:
`handleBookingConflict()` set `siteSelectionError` **before** calling `loadSiteAvailability()`,
which resets `siteSelectionError = ''` on every load — wiping the conflict message so the user
saw no explanation. Fixed by applying the conflict message **after** the availability refresh
completes. This was the only application behavior change required by the verification.

## 8. Lint Baseline

`npm run lint` runs `run-s lint:*` = **oxlint (`--fix`) then eslint**. oxlint is the gate that
actually executes; eslint only runs if oxlint passes.

| Metric                              | Count |
| ----------------------------------- | ----: |
| Repository-wide oxlint errors before | 13 |
| Repository-wide oxlint errors after  | 12 |
| Pre-existing unrelated errors        | 12 |
| Phase 2A.8 errors (after)            | 0 |
| Phase 2A.8.1 errors (after)          | 0 |

The single error removed was in a phase-touched file (`tests/e2e/specs/vendor.booking.spec.js`,
an unused `catch (error)` binding → optional catch binding).

### Remaining 12 pre-existing unrelated oxlint errors (`no-unused-vars`)

| File | Rule |
| ---- | ---- |
| `src/composables/useManagementAccess.js` | unused import `canManageCmartActivities` |
| `src/stores/auth.js` | unused import `defaultManagementHashForRole` |
| `tests/e2e/specs/access.guest-protection.spec.js` | unused import `assert` |
| `tests/e2e/specs/organizer.booking-approval.spec.js` | unused import `loginAsVendor` |
| `tests/e2e/helpers/auth.js` (×3) | unused `firstError` / `retryCount` / `firstError` |
| `tests/e2e/helpers/access-guards.js` (×2) | unused `loginAsCmartManagement` / `marker` |
| `tests/e2e/helpers/destructive-guards.js` | unused import `requireCmartManagementCredentials` |
| `tests/e2e/helpers/payment-verification.js` | unused `uiError` |
| `tests/e2e/helpers/organizer-bookings.js` | unused `uiError` |

None of these are Phase 2A.8 / 2A.8.1 files. Per scope, unrelated repository-wide lint debt was
**not** modified.

### Changed-file lint result

- **oxlint** on all Phase 2A.8 + 2A.8.1 files: **0 errors**.
- **eslint** on all non-spec Phase files (`Registration.vue`, `EventSiteSelector.vue`,
  `eventSiteSelection.js`, `VendorBookingDetailsModal.vue`, `bookingDisplay.js`,
  `OrganizerBookingsPanel.vue`, `eventSiteSelection.test.js`, `booking.js`, `site-fixtures.js`):
  **0 errors** (`Registration.vue` given a multi-word component name to satisfy
  `vue/multi-word-component-names`).

> eslint config gap (pre-existing, repo-wide): `frontend/eslint.config.js` declares only
> browser globals, so a standalone eslint run reports `no-undef` for mocha/node globals
> (`describe`, `it`, `before`, `process`) in **every** e2e spec — including pre-existing specs
> such as `vendor.booking.spec.js`. This is not introduced by this phase, and eslint is never
> reached by `npm run lint` because oxlint (the first gate) fails on the 12 unrelated errors.
> The reachable, enforced gate (oxlint) passes with zero errors on all phase files.

## 9. Repository-wide lint debt statement

> Repository-wide lint debt remains separate from Phase 2A.8 only because all Phase 2A.8 and
> Phase 2A.8.1 files pass lint with zero errors. The 12 remaining oxlint errors are pre-existing
> and live entirely in unrelated files.

## 10. Tests and Build

| Command | Result |
| ------- | ------ |
| `php artisan migrate:status` | all migrations Ran (none pending) |
| `php artisan test --filter=VendorEventSiteAvailability` | 12 passed (32 assertions) |
| `php artisan test --filter="BookingCreationWithAllocations\|BookingAllocationLifecycle"` | 21 passed (87 assertions) |
| `php artisan test --filter="BookingDayAllocationReservation\|AllocationHistoryProtection"` | 23 passed (102 assertions) |
| `php artisan test --filter="GovernanceAccessBoundary\|CommunityVendorBookingAccess"` | 9 passed (24 assertions) |
| `php artisan test` (full backend) | 150 passed, 2 skipped (530 assertions) |
| `npm run test:unit` | 12 passed |
| `npm run lint` | 12 pre-existing unrelated errors; 0 phase errors |
| `npm run build` | built successfully (pre-existing chunk-size warning only) |
| `npm run test:e2e -- --headless vendor.site-selection.spec.js` | 2 passing (success + conflict) |

## 11. Persistent Data Count Matrix

| Table                   | Baseline | During fixture (+ live booking) | Final after cleanup |
| ----------------------- | -------: | ------------------------------: | ------------------: |
| users                   |        4 |                               5 |                   4 |
| carboot_events          |        6 |                               7 |                   6 |
| event_sites             |        0 |                               3 |                   0 |
| event_days              |        0 |                               2 |                   0 |
| spaces                  |        2 |                               2 |                   2 |
| bookings                |        0 |                               1 |                   0 |
| invoices                |        0 |                               1 |                   0 |
| booking_day_allocations |        0 |                               4 |                   0 |
| booking_audit_logs      |        0 |                               1 |                   0 |
| news                    |        1 |                               1 |                   1 |
| feedback                |        5 |                               5 |                   5 |

Final counts equal the baseline exactly, confirmed again after the full backend suite.
Temporary rows exist only during the E2E execution window.

## 12. Known Limitations

- Browser E2E requires the Vite E2E server (`npm run dev:e2e`, port 5175), the Laravel API
  (`php artisan serve`, port 8000), and Chrome. selenium-manager fetches the matching
  chromedriver on first run (network access required).
- eslint mocha/node globals are not configured repo-wide (pre-existing); not addressed here to
  avoid modifying shared config outside phase scope.

## 13. Readiness for Phase 2B

The repository is ready for **Phase 2B — Withdrawal, No-Refund, and Post-Booking Operational
Rules**. Phase 2B was not started.

## Explicit statements

> Browser E2E uses temporary test-scoped EventSites and EventDays and does not require
> permanent local demo layout data.

> Repository-wide lint debt remains separate from Phase 2A.8 only when all Phase 2A.8 and
> Phase 2A.8.1 files pass lint with zero errors.

## Deferred (out of scope, not implemented)

paid withdrawal · no-refund handling · refund processing · site reallocation · site swapping ·
partial-site release · partial-day release · allocation expiration · waitlist ·
manual Organizer assignment · payment redesign · pass/check-in redesign · analytics ·
new database schema · new allocation status · production seed data.
