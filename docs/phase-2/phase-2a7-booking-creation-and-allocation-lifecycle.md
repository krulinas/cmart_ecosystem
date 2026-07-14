# Phase 2A.7 — Booking Creation Integration and Allocation Lifecycle

## Phase objective

Integrate the Phase 2A.6 reservation engine into the real vendor booking lifecycle:

- require physical `event_site_ids` on booking submission;
- create Booking, Invoice, and `booking_day_allocations` atomically;
- derive compatibility fields and pricing on the backend;
- keep allocations **reserved** through revision and unpaid approval;
- **confirm** allocations only after payment verification (or demo payment);
- **release** allocations on rejection, cancellation, and unpaid withdrawal;
- expose real site/day data through booking presenters.

> **EventDays and EventSites must be configured by the Organizer before vendor booking submission.**

> **The backend booking contract now requires physical EventSite selection, but the cinema-style frontend site picker is not implemented in Phase 2A.7.**

> **Paid withdrawal and no-refund handling remain deferred.**

---

## Lifecycle summary

```text
Vendor booking submission
→ Booking created
→ EventSites reserved across all active EventDays
→ Invoice created from selected site prices
→ Organizer approval keeps reservation
→ Payment verification confirms allocation
→ Rejection/cancellation releases allocation
```

---

## Updated vendor booking contract

**Endpoint:** `POST /api/bookings` (unchanged route; `role:community` middleware)

**Required request fields:**

| Field | Validation |
|-------|------------|
| `event_id` | required, integer, exists on `carboot_events` |
| `event_site_ids` | required, array, min 1 item, integer IDs, distinct |
| `product_category` | required, enum (existing categories) |
| `product_details` | required, string, max 5000 |

**Example:**

```json
{
  "event_id": 12,
  "event_site_ids": [45, 46],
  "product_category": "Food & Beverages",
  "product_details": "Nasi lemak and drinks"
}
```

**Legacy fields ignored for authority:**

- `tapak_quantity`, `total_price`, `space_id`, `amount`, `booking_date`, booth labels

Missing or invalid `event_site_ids` returns **422**. Duplicate IDs are rejected by validation (not silently deduplicated).

---

## Event readiness

Before reservation, the event must have:

- at least one **active** `EventDay`;
- at least one selected **active** `EventSite` with a valid `Space` relationship.

When zero active EventDays exist:

```text
This event has no active operational days configured. The Organizer must configure the event schedule before bookings can be accepted.
```

HTTP **422**, error code `no_active_event_days`.

EventDays and EventSites are **not** auto-generated during booking submission.

---

## Atomic creation flow

All steps run inside one outer `DB::transaction()` in `BookingController::store()`:

1. Validate request shape (`event_site_ids` required).
2. Authorize vendor (`auth:sanctum` + `role:community`).
3. Lock `CarbootEvent`.
4. Create `Booking` (`Pending_Organizer`).
5. Call `BookingAllocationReservationService::reserveForBookingInExistingTransaction()`.
6. Update Booking compatibility fields from reservation result.
7. Create `Invoice` from derived amount.
8. Write `BookingAuditLogger` entry (`vendor_submitted_booking`).
9. Commit and return presenter response.

On any failure: no Booking, Invoice, allocation, or orphan audit row remains.

---

## Booking compatibility fields

| Field | Derivation |
|-------|------------|
| `space_id` | Shared `space_id` of selected EventSites (Phase 2A.6 enforces one Space type) |
| `tapak_quantity` | Exposed in API response as `site_selection.site_count` / `tapak_quantity` (no DB column; quantity = selected site count) |
| `booking_date` | First active `EventDay.operational_date` (sorted by date). Occupancy authority remains `booking_day_allocations`. |

---

## Invoice amount

```text
amount = sum(selected EventSite.space.price)
```

Not multiplied by active EventDay count. Decimal-safe `bcadd` in reservation service.

---

## Reservation integration

`BookingAllocationReservationService`:

- `reserveForBooking()` — standalone transaction (Phase 2A.6 tests).
- `reserveForBookingInExistingTransaction()` — nested in booking creation transaction (Phase 2A.7).

Creates `reserved` allocations with `active_lock = 1` for every selected site × every active EventDay.

Conflict mapping:

| Condition | HTTP |
|-----------|------|
| Validation (adjacency, mixed types, inactive site, no days) | 422 |
| Occupancy race / duplicate booking allocation | 409 |

---

## Allocation lifecycle service

**Class:** `App\Services\BookingAllocationLifecycleService`

| Method | Purpose |
|--------|---------|
| `confirmForBooking(Booking $booking)` | `reserved` → `confirmed`, sets shared `confirmed_at` |
| `releaseForBooking(Booking $booking, ?User $releasedBy, string $reason)` | `reserved`/`confirmed` → `released`, clears `active_lock` |

**Release reasons:**

- `booking_rejected`
- `booking_cancelled`
- `booking_withdrawn`

**Locking order:** Booking → allocations (by ID ascending).

**Idempotency:** Repeated confirm when already confirmed; repeated release when already released.

**Invalid transitions:** Cannot confirm released/cancelled allocations (409 via `DomainConflictException`).

---

## Lifecycle by booking action

| Action | Allocation behavior |
|--------|---------------------|
| Vendor submits booking | Create `reserved` |
| `Pending_Organizer` | Keep `reserved` |
| Organizer requests revision | Keep `reserved` |
| Vendor resubmits | Keep `reserved`; reject `event_site_ids` in payload |
| Organizer approves | Keep `reserved` (no `confirmed_at`) |
| Payment proof pending/correction | Keep `reserved` |
| Organizer verifies payment | `reserved` → `confirmed` (same transaction as invoice Paid) |
| Demo payment (Approved + Unpaid) | `reserved` → `confirmed` (same transaction) |
| Organizer rejects | → `released` (`booking_rejected`) |
| Vendor cancel (`/cancel`) | → `released` (`booking_cancelled`) |
| Unpaid withdraw | → `released` (`booking_withdrawn`) |

Legacy bookings without allocations: lifecycle methods no-op safely; payment flows unchanged.

---

## Presenter response shape

`VendorBookingPresenter` adds `site_selection` when allocations exist:

```json
{
  "site_selection": {
    "site_count": 2,
    "active_day_count": 2,
    "allocation_count": 4,
    "allocation_status": "reserved",
    "sites": [
      {
        "id": 12,
        "label": "A05",
        "row_label": "A",
        "position_number": 5,
        "space_id": 1,
        "space_name": "Standard (1 Parking Lot)",
        "price": "30.00"
      }
    ],
    "days": [
      {
        "id": 7,
        "operational_date": "2026-08-01",
        "starts_at": "...",
        "ends_at": "...",
        "operational_status": "active"
      }
    ]
  },
  "tapak_quantity": 2
}
```

- `presentForVendor()` — vendor detail/list enrichment
- `presentForOrganizer()` — organizer verify-payment and review responses

Internal fields **not** exposed: `active_lock`, release audit internals.

Approved bookings with real sites use actual `EventSite.label` for booth display when allocations exist.

---

## Authorization

| Role | Booking create | Approve/reject/verify | Allocation direct access |
|------|----------------|----------------------|--------------------------|
| `community` | Own bookings only | Denied | Denied |
| `organizer` | N/A | Allowed | Indirect via booking actions |
| `super_admin` | N/A | Allowed | Indirect |
| `cmart_management` | Denied (403) | Denied | Denied |
| Unauthenticated | 401 | 401 | 401 |

No new public allocation routes were added.

---

## Audit continuity

Existing `BookingAuditLogger` entries preserved and extended:

- `vendor_submitted_booking` on create
- Existing organizer/vendor actions unchanged
- Payment verification audit remains in same transaction as allocation confirmation

---

## Test coverage

| File | Tests | Focus |
|------|-------|-------|
| `BookingCreationWithAllocationsTest.php` | 11 | Creation, derivation, rollback, governance |
| `BookingAllocationLifecycleTest.php` | 10 | Revision, approval, payment, release, idempotency |

**Regression (all pass):**

- Phase 2A.6 reservation + history protection
- Event site/day foundation
- Organizer workflow, governance, demo payment, community access

**Full suite:** 140 tests, 502 assertions, 0 failures, ~84s

---

## Migration status

All Phase 2A.4–2A.6 migrations applied (`php artisan migrate` — nothing pending).

---

## Local persistent data verification

After implementation and full test run:

| Table | Count |
|-------|------:|
| users | 8 |
| carboot_events | 7 |
| event_sites | 0 |
| event_days | 0 |
| spaces | 2 |
| bookings | 0 |
| invoices | 0 |
| booking_day_allocations | 0 |

Note: `users` and `carboot_events` counts may differ from the Phase 2A.3 baseline (6/6) due to unrelated test residue; **no bookings, invoices, or allocations** remain after test cleanup.

---

## Known limitations

- True parallel concurrency not tested in PHPUnit; conflict coverage uses sequential stale-read simulation (Phase 2A.6 pattern).
- `tapak_quantity` is response-derived only (no dedicated bookings column in schema).
- Demo payment confirms allocations but is not the production payment path.
- Withdraw after payment remains blocked (existing behavior); paid withdrawal deferred.

---

## Deferred work

- Cinema-style frontend site picker
- Site reallocation during revision
- Partial-site/day release
- Paid withdrawal / no-refund / refund processing
- Allocation expiry, waitlist, manual Organizer assignment

---

## Next phase readiness

**Backend is ready for:** Frontend Event-Site Availability and Cinema-Style Site Selection Integration.

Frontend should:

1. Load Organizer-configured EventSites and EventDays for an event.
2. Submit `event_site_ids` with existing product fields to `POST /api/bookings`.
3. Render `site_selection` from booking detail responses.
