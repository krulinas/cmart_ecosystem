# Phase 2B.4 — Released-Day Recovery and Operational Hardening

## Result and objective

**Completed — released-day recovery queue verified and Phase 2B closed.**

This phase adds a read-only Organizer recovery queue for partial EventDay/EventSite slices released through Organizer attendance exceptions. It derives operational recoverability without inventing replacement pricing, Vendor acceptance, or recovery Invoice rules.

> A partial EventDay release does not weaken the standard full-event Vendor booking rule.

> The recovery queue is read-only and does not create a replacement Booking, Invoice, payment obligation, or active allocation.

> Replacement assignment remains deferred until pricing, Vendor acceptance, payment, and operational ownership rules are approved.

## Full-release versus partial-release recovery

### Full-booking release

Vendor withdrawal, cancellation, or rejection releases all EventDays for selected sites. Those sites reopen through the normal full-event Vendor selector. They are **not** listed as partial recovery opportunities.

Classification:

```text
recovery_channel = standard_full_event_availability
```

### Partial EventDay release

An Organizer attendance exception retains some EventDays and releases others. Released Day N slices are operationally free for that day only, but retained days still occupy the same sites. The standard Vendor selector continues to mark those sites unavailable.

Classification:

```text
recovery_channel = released_day_queue
```

## Standard full-event availability boundary

`GET /api/vendor/events/{event}/site-availability` is unchanged. Occupancy remains:

```text
site is occupied when any active_lock = 1 allocation exists on any active EventDay
```

Verified:

- partial Day 3 release → A01/A02 remain `occupied` for full-event booking
- complete withdrawal → A01/A02 become `available`
- queue exposes `standard_full_event_available` using the same occupancy principle
- no per-day Vendor selector was added

## Recovery source

Primary source rows:

```text
booking_day_allocations
  allocation_status = released
  release_reason = organizer_day_exception
  active_lock = NULL
```

Queue inclusion requires the same booking/site to still have an active allocation on a different EventDay. This excludes full withdrawals where no retained occupancy remains.

Also inspected and classified without placing them in the partial queue:

```text
booking_withdrawn
booking_cancelled
booking_rejected
```

## Persistence decision

No new migration was required. Recovery state is query-time derived from existing tables:

```text
booking_day_allocations
booking_attendance_exceptions
booking_attendance_exception_days
bookings
invoices
event_days
event_sites
carboot_events
```

## Grouping strategy

Groups:

```text
source Booking + EventDay + release reason + release actor + released_at
```

Multiple sites released together for one Booking/EventDay produce one queue row.

Ordering:

```text
future EventDay starts_at ascending
release timestamp descending
source Booking ID ascending
```

## Recovery-state derivation

Derived states (not persisted):

| State | Meaning |
| --- | --- |
| `recoverable` | Day not started; day/site/event operationally active; no conflicting active occupancy |
| `partially_blocked` | Some released sites recoverable, others blocked or unavailable |
| `fully_blocked` | All released sites occupied by another active allocation |
| `expired` | EventDay `starts_at <= now()` in app timezone |
| `operationally_unavailable` | EventDay/EventSite/event no longer bookable, without replacement occupancy required |

Timezone authority: `config/app.php` → `Asia/Kuala_Lumpur`.

## Active replacement detection

A released historical allocation is blocked when another allocation exists with:

```text
same event_day_id
same event_site_id
active_lock = 1
```

The historical released row itself is never treated as active. Safe blocker copy:

```text
Occupied by another active booking
```

Replacement Vendor identity is not exposed.

## Organizer endpoint

```text
GET /api/organizer/released-day-recovery
```

Middleware: `auth:sanctum` + Organizer-equivalent operational roles.

| Actor | Result |
| --- | ---: |
| Organizer | `200` |
| Super Admin | `200` (established authority) |
| CMart Management | `403` |
| Community | `403` |
| Unauthenticated | `401` |

### Filters

```text
event_id, event_day_id, recovery_state, payment_state,
release_reason, date_from, date_to, search, page, per_page
```

`include_audit_timeline` may be requested for detail loads.

Validated payment states: `unpaid`, `payment_submitted`, `paid`.

Validated recovery states: the five derived states above.

### Query strategy

1. Filter partial exception released rows with one `whereExists` retained-occupancy check.
2. Eager-load booking, invoice, event, day, site, released-by.
3. Batch load active replacement conflicts for day/site pairs.
4. Batch load active EventDays and occupied sites per event for `standard_full_event_available`.
5. Batch load attendance-exception reasons.
6. Group in memory and paginate the grouped collection.

Avoids per-row Booking/EventDay/EventSite/Invoice/replacement queries.

### Response privacy

Excluded:

```text
active_lock
allocation IDs
payment-proof path
raw audit notes
IP address
internal exception metadata
```

Booking references use `BKG-####` formatting for Organizer display.

## Organizer UI

- Tab inside `OrganizerBookingsPanel.vue`: **Released-Day Recovery**
- `OrganizerReleasedDayRecoveryPanel.vue` — paginated queue, filters, state chips
- `OrganizerReleasedDayRecoveryModal.vue` — read-only detail

Displays:

- event, released EventDay, source Booking reference/status
- Vendor/business name when already Organizer-visible
- released site labels, payment state, recovery state, blockers
- exception reason, release actor/time, standard full-event availability
- safe audit timeline on detail reload

Forbidden controls absent:

```text
Assign Vendor, Create Booking, Create Invoice, Reserve Site,
Restore Allocation, Reopen Day, Refund, Change Price
```

Explanatory copy covers recoverable, blocked, expired, and empty states.

## Source Booking lifecycle handling

If the source Booking is later fully withdrawn or cancelled:

- remaining active allocations release
- retained occupancy disappears
- the partial queue entry is excluded
- historical attendance-exception rows remain stored
- `standard_full_event_available` is recomputed from current occupancy

Repeated recovery reads create no audit or exception rows.

## Edge-case hardening

Covered by feature tests and/or query safety:

- missing attendance-exception child (queue still derives from allocation release)
- later withdrawal / cancellation interpretation
- disabled EventDay / EventSite
- started EventDay → expired
- partial and full replacement occupancy
- repeated reads, pagination, filters
- missing Invoice amount (`null`)
- missing release actor fallback
- full-withdrawal exclusion from partial queue

## Vendor privacy

Vendor booking presenters expose no recovery queue, recovery state, replacement occupancy, or competing Booking references. Vendor UI continues to show retained/released EventDays and attendance-exception reason only.

## Tests

### Backend

`backend/tests/Feature/OrganizerReleasedDayRecoveryTest.php`

- 8 tests / 75 assertions
- queue inclusion/grouping, state classification, availability boundary
- later withdrawal interpretation, governance, privacy, non-mutation, filters/pagination

### Frontend unit

`frontend/tests/unit/organizerReleasedDayRecovery.test.js`

- queue structure/test IDs, labels, filters, detail modal privacy, Vendor isolation

### Browser E2E

`frontend/tests/e2e/specs/organizer.released-day-recovery.spec.js`

Fixture: `php artisan e2e:site-fixtures create-released-day-recovery --json`

Helpers:

```text
create-released-day-recovery
recovery-add-competing-allocation --site=A0N --json
recovery-status
cleanup
```

Verified recoverable → partially blocked (A02) → fully blocked (A01+A02) against real backend state, then cleanup.

## Known limitations

- Queue does not assign replacement Vendors
- No day-specific Vendor booking or pricing
- No recovery Invoice/payment
- In-memory pagination of grouped rows is acceptable for current Organizer volume; revisit cursor/SQL pagination if the inventory grows large
- Replacement Booking references are intentionally withheld until governance rules allow them

## Replacement-assignment decision gate

Do not begin reassignment until the product defines:

1. price for one released day
2. whether a replacement Vendor pays
3. effect of the original Vendor payment
4. acceptance flow
5. whether a new Invoice is required

## Phase 2B closure readiness

Phase 2B.1–2B.4 are complete with regression proof. Recommended next major phase is a separately scoped **replacement assignment** or Phase 3 product stream after the decision gate above.
