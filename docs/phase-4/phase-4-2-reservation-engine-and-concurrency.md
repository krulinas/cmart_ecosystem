# Phase 4.2 — Reservation Engine and Concurrency

**Document date:** 2026-07-19  
**Repository:** CMart / Carboot@CMart (`cmart_ecosystem`)  
**Status:** Complete  
**Depends on:** Phase 4.0 architecture and Phase 4.1 item-listing foundation

## 1. Objective

Implement the backend item-reservation aggregate, history, one-active-reservation
database invariant, community/vendor read and pending-cancel APIs, and dynamic
marketplace readiness without introducing Organizer charge actions, expiry,
completion, notifications, payment integration, or reservation UI.

## 2. Phase 4.1 entry baseline

The guarded `cmart_test` database had the Phase 4.1 event-fee migration applied.
The reservation-adjacent pre-change suite passed with 24 tests and 130
assertions. The documented full baseline was 331 tests, 1655 assertions, and
zero skips. Frontend baseline was 85 tests.

## 3. Files inspected

Inspection covered the Phase 4.0/4.1 documents; marketplace controller,
eligibility and presenters; vendor item, user, booking and event models;
vendor-item deletion; API routes and role middleware; allocation migrations,
model, service and conflict tests; Phase 3 append-oriented audits; database
guard configuration; and persistent fixture-cleanup conventions.

The deterministic marketplace context remains the earliest upcoming event by
`starts_at`, with booking ID as the stable tie-breaker.

## 4. Files changed

- `backend/database/migrations/2026_07_19_000001_create_item_reservations_table.php`
  — reservation schema and database invariants.
- `backend/database/migrations/2026_07_19_000002_create_item_reservation_audits_table.php`
  — append-oriented reservation history.
- `backend/app/Models/ItemReservation.php` and
  `backend/app/Models/ItemReservationAudit.php` — constants, casts,
  relationships, status-lock normalization, and audit mutation guards.
- `backend/app/Models/VendorItem.php`, `User.php`, `Booking.php`, and
  `CarbootEvent.php` — focused reservation relationships.
- `backend/app/Services/ItemReservationReferenceGenerator.php` — opaque random
  references.
- `backend/app/Services/ItemReservationDuplicateKeyDetector.php` — exact
  MySQL/MariaDB duplicate classification.
- `backend/app/Services/ItemReservationService.php` — locked creation
  transaction and creation audit.
- `backend/app/Services/ItemReservationCancellationService.php` — locked,
  authorized pending cancellation and cancellation audit.
- `backend/app/Services/ItemReservationPresenter.php` — community/vendor
  privacy boundaries.
- `backend/app/Services/MarketplaceEligibility.php` — deterministic eligible
  booking and event context.
- `backend/app/Services/MarketplaceItemPresenter.php` and
  `VendorItemPresenter.php` — dynamic reservation booleans.
- `backend/app/Http/Controllers/Api/ItemReservationController.php` and
  `VendorItemReservationController.php` — thin community/vendor APIs.
- `backend/app/Http/Controllers/Api/MarketplaceController.php` — batched
  booking/event eager loading and active-reservation `withExists`.
- `backend/app/Http/Controllers/Api/VendorItemController.php` — reservation
  history delete guard.
- `backend/routes/api.php` — community-only reservation routes.
- `backend/tests/Feature/Phase42ReservationEngineTest.php` and
  `backend/tests/Unit/ItemReservationDuplicateKeyDetectorTest.php` — schema,
  API, privacy, lifecycle, isolation and collision coverage.
- `backend/tests/Feature/Phase41VendorItemFoundationTest.php` — Phase 4.2-aware
  expectation while preserving the no-history deletion assertion.
- `docs/phase-3/phase-3-debt-register.md` — Phase 4.2 engine complete, manual
  charge lifecycle still pending.
- This document — implementation and validation record.

## 5. Reservation schema

`item_reservations` contains the frozen lifecycle fields:

`id`, opaque `public_reference`, item/reserver/vendor/event/optional-booking
foreign keys, `reservation_status`, nullable `active_lock`,
`DECIMAL(10,2) service_fee_amount`, `CHAR(3) service_fee_currency` defaulting to
`MYR`, charge lifecycle fields, cancellation/expiry/completion actor and
timestamp fields, immutable `item_name_snapshot`, and timestamps.

Indexes support reserver/status, vendor/status, event/status, item/status and
charge-status access paths. Named unique constraints are:

- `item_reservations_public_reference_unique`
- `item_reservations_item_active_lock_unique`

Named CHECK constraints are:

- `item_reservations_status_active_lock_check`
- `item_reservations_service_fee_non_negative`
- `item_reservations_charge_status_check`

No `SoftDeletes`, quantity, inventory, invoice or payment-proof field exists.

## 6. Audit schema

`item_reservation_audits` contains the reservation, nullable actor, action,
from/to reservation and charge states, bounded note, JSON metadata, and
`created_at`. It has reservation/created-time and action indexes.

Supported Phase 4.2 actions are `reservation_created` and
`reservation_cancelled`. There are no audit update/delete APIs. Model update
and delete events throw, the parent FK is restrictive, production services
only insert, and test cleanup alone uses direct database deletion.

## 7. Foreign-key deletion rules

Item, reserving user, vendor user and event use `RESTRICT`. Optional booking and
future lifecycle actors use `SET NULL`. Audit parent uses `RESTRICT`; audit
actor uses `SET NULL`. Cancellation never deletes either the reservation or its
audits.

## 8. Status and charge constants

Reservation constants are exactly `pending_charge`, `confirmed`, `cancelled`,
`expired`, and `completed`. Charge constants are exactly `required`,
`confirmed`, `waived`, `not_required`, and `cancelled`.

Phase 4.2 creates only `pending_charge + required` or
`confirmed + not_required`, and transitions only
`pending_charge + required` to `cancelled + cancelled`.

## 9. Opaque public-reference generation

References use `RSV-` plus eight cryptographically selected uppercase,
URL-safe characters from an ambiguity-reduced alphabet. They are not derived
from the numeric ID. The database unique constraint is authoritative.

An exact public-reference duplicate retries generation at most five times
inside the transaction. Exhaustion returns
`reservation_reference_generation_failed`; it is never translated to
`item_already_reserved`. Unrelated database errors are rethrown.

## 10. Reservation creation transaction

The service verifies the community role, starts a transaction, locks
`VendorItem` with `FOR UPDATE`, hides missing/inactive/ineligible items as 404,
rejects self-reservation, resolves and locks the existing deterministic
approved upcoming booking/event context, requires a configured event fee, checks active
history, snapshots item/vendor/event/booking/name/fee/currency, inserts the
reservation, inserts one creation audit, and commits atomically.

The lock order is:

`VendorItem -> eligible Booking/Event context -> reservation insert -> audit insert`.

## 11. Active-lock invariant and duplicate handling

`pending_charge` and `confirmed` require `active_lock=1`; terminal states
require `NULL`. `UNIQUE(vendor_item_id, active_lock)` permits multiple terminal
history rows but only one active row.

Only driver duplicate code `1062` containing
`item_reservations_item_active_lock_unique` becomes HTTP 409 with
`item_already_reserved`. Public-reference collisions have their own bounded
path, and all other query exceptions propagate.

## 12. Eligibility and fee snapshots

Creation requires a community actor, non-owned active item, the existing public
marketplace rule, an approved upcoming vendor booking, a non-null eligible
event fee, and no active reservation.

`NULL` fee returns 422
`item_reservation_fee_not_configured`. `0.00` creates
`confirmed + not_required` without waiver/confirmation actor data. Positive
fees create `pending_charge + required`. Historical amount, item name, event,
vendor and booking snapshots do not change when source records later change.

## 13. Community API

- `POST /api/reservations`
- `GET /api/reservations/me`
- `GET /api/reservations/{public_reference}`
- `POST /api/reservations/{public_reference}/cancel`

All routes require Sanctum and `role:community`. Reads are reserver-scoped,
lists are newest-first and paginated, numeric reservation IDs do not bind, and
payloads omit internal IDs, contacts, booking financial data and audits.
References outside the actor's scope return 404 to avoid existence disclosure.

## 14. Vendor API

- `GET /api/vendor/item-reservations`
- `GET /api/vendor/item-reservations/{public_reference}`
- `POST /api/vendor/item-reservations/{public_reference}/cancel`

The immutable vendor snapshot enforces ownership. Out-of-scope references
return 404. Payloads may show the reserving user's display name but never email
or phone.

## 15. Pending cancellation lifecycle

The reservation row is locked first. Authorization and state are checked under
that lock. Valid cancellation sets reservation and charge statuses to
`cancelled`, clears `active_lock`, records optional bounded reason, actor and
time, then inserts one cancellation audit.

Repeated cancellation and all non-pending statuses return deterministic 409
`reservation_not_pending`; timestamps and audits are not rewritten. Zero-fee
confirmed reservations cannot use this path.

## 16. Marketplace presenter changes

`has_active_reservation` derives only from `active_lock=1`.
`is_reservable` requires existing public eligibility, a non-null fee, and no
active reservation. `0.00` is configured.

Public list queries eager-load eligible bookings/events in batches and use
`withExists` for active history. A test proves query count does not grow when
more items are presented. Public payloads expose only the two booleans; no
reservation identity or PII is added. The Vue marketplace remains preview-only
with no Reserve CTA.

## 17. Item deletion-history guard

After owner authorization and before `$vendor_item->delete()`,
`VendorItemController::destroy()` checks all reservation history. Any status
returns 409 `item_has_reservation_history`. Image deletion hooks are not
entered when blocked. Items without history retain existing hard-delete and
image-cleanup behaviour.

## 18. Booking, invoice and allocation isolation

Creation and cancellation read booking/event context only. Tests snapshot the
booking and prove no invoice, allocation or booking-audit row is created or
changed. Payment proof and verification fields are untouched. Reservations do
not own or mutate booth bookings.

## 19. Backend focused tests

`php artisan test tests/Feature/Phase42ReservationEngineTest.php
tests/Unit/ItemReservationDuplicateKeyDetectorTest.php`

Result: 17 passed, 0 failed, 0 skipped, 152 assertions.

Coverage includes schema/FKs, roles, eligibility, self-reservation, zero and
positive fees, immutable snapshots, exact duplicate classification,
application conflict, raw database invariant, bounded reference collisions,
unrelated errors, read privacy, both cancellation actors, repeat conflict,
deletion guard, append-only model guards, query growth, and financial
isolation.

## 20. Backend full-suite result

`php artisan test`

Result: 348 passed, 0 failed, 0 skipped, 1808 assertions. This is an increase of
17 tests and 153 assertions from the Phase 4.1 full baseline.

## 21. Frontend result

`npm run test:unit`

Result: 85 passed, 0 failed, 0 skipped. No production frontend file, route,
store, modal, form, or reservation CTA was added.

## 22. Lint and formatting

- Oxlint: 0 warnings, 0 errors.
- ESLint: 0 errors.
- Laravel Pint: passed after formatting affected PHP files.
- `git diff --check`: passed; local line-ending notices are non-failing.
- Frontend build: not required because no production frontend file changed in
  Phase 4.2.

## 23. Migration rollback and reapply

Both migrations applied successfully. The audit migration was rolled back
first and only its table disappeared. The reservation migration was then
rolled back; both new tables were absent while vendor items, events, the Phase
4.1 fee column, bookings, invoices and allocations remained.

Both migrations reapplied successfully and the focused/full suites passed.

## 24. Persistent fixture cleanup

The shared fixture-cleanup trait supports exact reservation IDs in
audit-before-reservation order. The Phase 4.2 test class also tracks exact
fixture IDs. Cleanup order is audits by direct test-only query, reservations,
items through Eloquent, bookings, events and users. The blocked-image test uses
a fake public disk.

Final markers were zero for Phase 4.2 reservations, audits, items, events,
users, all reservation rows, all reservation audits, and matching uploaded
files. No broad reset or pre-existing-data deletion was used.

## 25. Concurrency evidence and limitation

The application conflict test produces one success, one deterministic 409,
one active row and one creation audit. A raw second active insert proves the
named database unique constraint. Failed active/reference attempts leave no
partial audit or reservation.

As documented by the existing Phase 2 allocation suite, true parallel database
clients are not reliable in this Windows/PHPUnit process. Phase 4.2 therefore
uses the repository's accepted deterministic stale-race/constraint approach
and does not claim that a real parallel HTTP test ran.

## 26. E2E status

No Phase 4.2 E2E was run or added. Reservation browser UI is explicitly out of
scope, and the previously documented local credential mismatch remains an
environment preflight issue.

## 27. Deferred Phase 4.3 work

Organizer queue/detail APIs, manual charge confirmation, charge waiver,
Organizer cancellation, expiry, completion, scheduler work, notifications,
reservation UI, payment proof/gateway, refunds and payouts remain deferred.
No placeholder endpoint or class was created for them.

## 28. Unresolved issues

None. The true-parallel PHPUnit limitation is explicitly documented and is
covered by the accepted deterministic database-boundary test.

## 29. Phase 4.2 verdict

```text
PHASE 4.2 COMPLETE — READY FOR PHASE 4.3
```
