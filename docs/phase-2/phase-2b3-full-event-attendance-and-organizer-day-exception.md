# Phase 2B.3 — Full-Event Attendance and Organizer Day Exception

## Result and objective

**Completed — full-event rule and Organizer attendance exception verified.**

This phase keeps standard vendor bookings as full-event bookings while adding one Organizer/Super Admin operation that may release selected future EventDay allocations. Booking approval, physical EventSites, Invoice amount, payment state, and proof history remain unchanged.

> Vendor-created bookings continue to cover every active EventDay by default.

> An Organizer attendance exception may only reduce future EventDay coverage and may not re-add previously released days.

> Attendance reduction does not reduce the Invoice amount and does not create a refund.

> Released EventDay slices are not yet exposed through the standard full-event vendor availability selector.

## Repository findings and documentation differences

- `BookingAllocationReservationService` already creates the Cartesian product `selected EventSites × active EventDays`, ordered and locked by ID.
- Laravel validation previously ignored unknown day-selection aliases. Phase 2B.3 explicitly prohibits `event_day_ids`, `booking_day_ids`, `selected_days`, `attendance_days`, `excluded_day_ids`, and `day_exception`.
- Vendor editing previously allowed the compatibility `booking_date` field to change without changing allocations. It is now prohibited and removed from the Vendor edit UI.
- EventDay timing authority is `starts_at`/`ends_at`, cast as application-timezone datetimes. `config/app.php` uses `Asia/Kuala_Lumpur`.
- `booking_audit_logs` has no JSON metadata field. Contrary to a possible audit-only implementation, it could not reliably preserve structured exception state.
- The Phase 2B.2 document called the Organizer detail modal read-only and suggested a report/queue next slice. The approved Phase 2B.3 scope supersedes that recommendation only for the attendance-exception action; withdrawal reconciliation itself remains read-only.
- Phase 2A documentation described partial release as deferred. The per-day allocation model and nullable active lock were already suitable, so this phase implements the controlled reduction without changing allocation authority.

## Canonical rules

Normal creation accepts event, physical site IDs, category, and product details. It creates:

```text
allocation rows = selected EventSites × every active EventDay
```

The same EventSite IDs are used on every day. Vendors have no EventDay selector and cannot change the compatibility booking date.

Attendance exceptions require:

- `calendar_days` mode;
- at least two active event days;
- a non-terminal Booking in `Pending_Organizer`, `Needs_Revision`, or `Approved`;
- active allocations;
- at least one currently retained EventDay;
- retained IDs from the same event and current retained set;
- every excluded day to have `starts_at > now()` in the configured timezone.

`single_session`, one-day, terminal, empty-allocation, cross-event, duplicate-day, started-day release, and released-day restoration requests are blocked.

## Endpoint and governance

```text
PATCH /api/organizer/bookings/{booking}/attendance-exception
```

Request:

```json
{
  "retained_event_day_ids": [21, 22],
  "reason": "Emergency family commitment on the final event day.",
  "acknowledge_no_refund": true
}
```

The existing `auth:sanctum` and Organizer-equivalent role middleware apply. Organizer and Super Admin are allowed. Community, CMart Management, and unauthenticated callers receive `403`, `403`, and `401`.

Paid and `Pending Verification` bookings require acknowledgement:

> **Pengecualian hari tidak mengubah jumlah bayaran. Tiada bayaran balik akan diberikan bagi hari yang dilepaskan.**

Unpaid bookings do not require the acknowledgement.

## Persistence decision and schema

Append-only normalized history was added because `booking_audit_logs` has no structured metadata:

```text
booking_attendance_exceptions
├── booking_id              RESTRICT FK
├── applied_by              RESTRICT FK
├── applied_by_name         actor snapshot
├── reason
├── payment_state
├── no_refund_acknowledged
├── previous/retained/released day counts
└── applied_at

booking_attendance_exception_days
├── booking_attendance_exception_id  CASCADE FK
├── event_day_id                     RESTRICT FK
└── disposition                      retained | released
```

Migration `2026_07_15_000001_create_booking_attendance_exceptions_tables.php` is applied as batch 10. The parent and child models expose deterministic relationships. These tables preserve exception history; `booking_day_allocations` remain the sole current occupancy authority.

## Transaction and locking

One database transaction:

1. locks the Booking;
2. validates approval lifecycle;
3. locks the event;
4. verifies `calendar_days`;
5. locks all event days by ID;
6. locks all Booking allocations by ID;
7. validates retained ownership/current occupancy and derives excluded IDs;
8. returns an idempotent no-op for an exact retained set;
9. blocks historical or started-day release;
10. derives payment state and validates acknowledgement;
11. writes parent and per-day exception history;
12. calls `BookingAllocationLifecycleService::releaseForBookingDays`;
13. writes one `organizer_applied_attendance_exception` audit;
14. commits and returns a freshly loaded Organizer presenter response.

A simulated lifecycle-service failure proved rollback of exception parent/children, allocation changes, audit, and financial state.

## Allocation and financial behavior

Only active allocations for excluded EventDay IDs change:

```text
reserved | confirmed → released
active_lock          → NULL
released_at          → one shared timestamp
released_by          → Organizer/Super Admin actor
release_reason       → organizer_day_exception
```

Retained allocation statuses and locks are unchanged. `reserved_at` and `confirmed_at` are preserved. EventSite operational status and labels are not modified.

Invoice row, amount, payment status, submission time, and proof path are untouched. No refund, refunded state, negative transaction, credit, reversal, repricing, or Invoice recalculation exists.

## Presenter and audit contract

Organizer and Vendor responses include `attendance_policy` with:

- full-event default and exception mode;
- original, retained, and released counts;
- safe retained/released dates and aggregate allocation states;
- unchanged real EventSite labels;
- reason and application time;
- payment/no-refund interpretation.

Organizer responses additionally include safe actor identity, complete exception history, mutation eligibility, and the audit timeline. Vendor responses expose only the safe Organizer label and omit exception history, raw audits, metadata, allocation IDs, active locks, release reason/actor IDs, IP addresses, and payment-proof paths.

Audit action:

```text
organizer_applied_attendance_exception
```

Safe label:

```text
Organizer applied attendance exception
```

The summary is derived from the normalized exception row, not rendered from raw metadata or notes.

## Organizer and Vendor UX

Organizer booking details now show:

- full-event default;
- retained/released real dates and times;
- A01/A02-style physical labels;
- payment state and Invoice amount;
- exception reason/history and safe timeline;
- an eligible-only `Apply Attendance Exception` action.

The exception modal initializes retained days selected, disables already released days, locks started/completed retained days, validates a ten-character reason, requires one retained and one released day, shows counts/sites/financial state, enforces Malay no-refund acknowledgement when applicable, reports API errors inline, disables while submitting, and blocks double-submit. Cancel sends no request. The authoritative response replaces local detail state.

Vendor details show the full-event statement or a read-only approved-exception summary with retained/released days, reason, unchanged site labels, and no-refund result. No day checkbox, attendance action, restoration, or site-change control exists.

## Tests

### Backend

`OrganizerAttendanceExceptionTest.php`: **9 passed, 117 assertions**.

Coverage includes full-event multiplication, prohibited Vendor fields, partial reserved/confirmed release, timestamp/lock/reason/actor integrity, Paid/Pending Verification/Unpaid preservation, acknowledgement, modes and lifecycle conflicts, foreign/duplicate/empty IDs, started-day safety, idempotency, repeated monotonic reduction, restoration rejection, Organizer/Super Admin/community/CMart/guest governance, safe presenters, and full transaction rollback.

Required focused regressions passed:

- booking creation: 11 / 43 assertions;
- reservation: 15 / 60;
- lifecycle: 10 / 44;
- withdrawal: 10 / 72;
- reconciliation: 6 / 57;
- availability: 12 / 32;
- governance/workflow/payment/summary group: 33 / 85.

Full backend: **175 passed, 2 skipped, 776 assertions**.

### Frontend

`organizerAttendanceException.test.js` adds 12 tests. All frontend unit tests: **43 passed**.

Targeted ESLint and targeted oxlint pass for every Phase 2B.3 frontend file.

### Browser E2E

Fixture mode:

```text
php artisan e2e:site-fixtures create-paid-three-day-booking --json
```

It creates one Vendor, one three-day `calendar_days` event, three active sites, an Approved/Paid Booking selecting A01/A02, one preserved proof marker, and six confirmed allocations. A CLI-only `attendance-status` action provides backend assertions; no fixture HTTP endpoint was added.

Headless Chrome verified:

1. Vendor baseline has Paid, A01/A02, all three days, full-event statement, and no day controls.
2. Organizer sees all dates, sites, Invoice, and payment.
3. Cancel produces six active allocations and zero exception/audit rows.
4. Confirmation requires reason and no-refund acknowledgement.
5. Organizer result remains Approved/Paid with two retained and one released day.
6. Backend has six rows: four confirmed/active and two released/unlocked with the canonical reason and actor.
7. Exactly one exception and one audit action exist.
8. Vendor sees the safe reason/days/no-refund summary and no mutation controls.

Result: **1 passing browser test**.

## Persistent-data matrix

| Table | Baseline | During fixture | After exception/vendor verification | Final after cleanup/tests |
| --- | ---: | ---: | ---: | ---: |
| users | 4 | 5 | 5 | 4 |
| carboot_events | 6 | 7 | 7 | 6 |
| event_sites | 0 | 3 | 3 | 0 |
| event_days | 0 | 3 | 3 | 0 |
| spaces | 2 | 2 | 2 | 2 |
| bookings | 0 | 1 | 1 | 0 |
| invoices | 0 | 1 | 1 | 0 |
| booking_day_allocations | 0 | 6 | 6 | 0 |
| booking_audit_logs | 0 | 0 | 1 | 0 |
| booking_attendance_exceptions | 0 | 0 | 1 | 0 |
| booking_attendance_exception_days | 0 | 0 | 3 | 0 |
| news_posts | 1 | 1 | 1 | 1 |
| feedbacks | 5 | 5 | 5 | 5 |

Focused and full backend suites also ended at the baseline. Cleanup removes exception children/parents before allocation, Invoice, audit, Booking, event layout, event, and temporary user rows. Shared Spaces are preserved.

## Validation results

- `php artisan migrate:status`: all migrations ran; attendance migration batch 10.
- `php artisan test --filter=OrganizerAttendanceException`: 9 passed, 117 assertions.
- All required focused backend commands: passed.
- `php artisan test`: 175 passed, 2 skipped, 776 assertions.
- `npm run test:unit`: 43 passed.
- `npm run test:e2e:headless -- organizer.attendance-exception.spec.js`: 1 passed.
- `npm run build`: passed; existing chunk-size advisory only.
- `npm run lint`: repository baseline remains 12 pre-existing unrelated unused-variable errors.
- Targeted ESLint: passed.
- Targeted oxlint: 0 errors, 0 warnings across eight Phase 2B.3 files.
- `git diff --check`: passed; Git reported only platform line-ending notices.

## Known limitations and Phase 2B.4 readiness

The normal Vendor availability service still asks whether a site is free across every active EventDay. A site retained on any day therefore remains unavailable for a new standard full-event booking even though another day slice was released. This is intentional.

No released-day marketplace, replacement assignment, reallocation, site swap, day restoration, waitlist, allocation expiration, refund, payment redesign, pass/check-in redesign, export, or analytics was added.

The allocation history, canonical release reason, structured exception rows, and deterministic cleanup are ready for **Phase 2B.4 — Released-Day Recovery and Operational Hardening**, but that work has not started.
