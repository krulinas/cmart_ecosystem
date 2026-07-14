# Phase 2A.6 — Booking-Day Allocations and Transactional Reservation Engine

**Status:** Completed  
**Date:** 2026-07-14  
**Repository:** CMart / Carboot@CMart (`cmart_ecosystem`)  
**Scope:** Backend occupancy foundation only — not wired into `POST /api/bookings`.

---

## 1. Phase objective

Introduce the durable occupancy model:

```text
Booking
└── BookingDayAllocations
    ├── EventDay
    └── EventSite
```

One allocation row represents one Booking occupying one EventSite on one EventDay.

The reservation service exists and is tested, but public booking creation does not call it yet.

Phase 2A.7 must create Booking, Invoice, and BookingDayAllocations inside one complete transaction.

---

## 2. Table schema (`booking_day_allocations`)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | |
| `booking_id` | FK → bookings | RESTRICT on delete |
| `event_day_id` | FK → event_days | RESTRICT on delete |
| `event_site_id` | FK → event_sites | RESTRICT on delete |
| `allocation_status` | string(20) | `reserved` \| `confirmed` \| `released` \| `cancelled` |
| `reserved_at` | datetime | |
| `confirmed_at` | nullable datetime | |
| `released_at` | nullable datetime | |
| `released_by` | nullable FK → users | SET NULL on user delete |
| `release_reason` | nullable string | |
| `active_lock` | nullable unsigned tinyint | `1` or `NULL` (never `0`) |
| timestamps | | |

Migrations:

- `2026_07_14_000004_create_booking_day_allocations_table.php`
- `2026_07_14_000005_fix_booking_day_allocations_status_lock_check.php` (NULL-safe CHECK)

---

## 3. Foreign keys and delete restrictions

| FK | On delete |
|----|-----------|
| `booking_id` | RESTRICT |
| `event_day_id` | RESTRICT |
| `event_site_id` | RESTRICT |
| `released_by` | SET NULL |

Normal application flows must not hard-delete allocation history. Controllers also block destructive actions before relying on FK failures.

---

## 4. Allocation statuses and `active_lock`

| Status | Occupies site | `active_lock` |
|--------|--------------:|--------------|
| `reserved` | Yes | `1` |
| `confirmed` | Yes | `1` |
| `released` | No | `NULL` |
| `cancelled` | No | `NULL` |

Centralized on `BookingDayAllocation::activeLockForStatus()` and enforced in the model `saving` hook.

---

## 5. Unique constraints and indexes

| Name | Definition |
|------|------------|
| `bda_day_site_active_lock_unique` | UNIQUE `(event_day_id, event_site_id, active_lock)` |
| `bda_booking_day_site_unique` | UNIQUE `(booking_id, event_day_id, event_site_id)` |
| `bda_booking_status_index` | INDEX `(booking_id, allocation_status)` |
| `bda_event_site_id_index` | INDEX `(event_site_id)` |
| `bda_allocation_status_index` | INDEX `(allocation_status)` |

Redundant single-column indexes for `booking_id` / `event_day_id` / `(event_day_id, event_site_id)` were omitted because unique composites already provide those prefixes.

---

## 6. CHECK-constraint decision

MariaDB 10.4 enforces CHECK constraints. An initial formulation incorrectly accepted `reserved + active_lock NULL` because SQL three-valued logic treats that expression as UNKNOWN (accepted by CHECK).

Fixed constraint uses `COALESCE(active_lock, 0) = 1` for occupying statuses so invalid pairs fail closed. Application layer still enforces the same invariant.

---

## 7. Model relationships

| Model | Relationship |
|-------|--------------|
| `BookingDayAllocation` | `booking()`, `eventDay()`, `eventSite()`, `releasedBy()` |
| `Booking` | `bookingDayAllocations()` |
| `EventDay` | `bookingDayAllocations()` |
| `EventSite` | `bookingDayAllocations()` |
| `User` | `releasedBookingDayAllocations()` |

Scopes used/tested: `forBooking`, `activeOccupancy`, `reserved`, `confirmed`, `historical`.

Compatibility fields retained unchanged: `bookings.space_id`, `bookings.booking_date`, `bookings.carboot_event_id`.

---

## 8. Full-event allocation rule

For validated selected sites, create one allocation for **every active EventDay** of the booking’s event:

```text
allocation rows = selected sites × active event days
```

Exclude EventDays with `cancelled` or `disabled`. Same physical sites are used across all active days.

---

## 9. Adjacency and same-Space-type rules

When more than one EventSite is selected, all must:

- belong to the booking’s Carboot event;
- be unique;
- have `operational_status = active`;
- share the same `row_label`;
- have consecutive `position_number` values;
- share the same `space_id`.

---

## 10. Quantity and amount derivation

```text
tapak_quantity = count(selected EventSites)
amount = sum(selected EventSite.space.price)   // bcmath decimal string
```

Returned by the reservation service. Invoice amount is **not** written in this phase.

---

## 11. Reservation engine

**Service:** `App\Services\BookingAllocationReservationService`  
**Method:** `reserveForBooking(Booking $booking, array $eventSiteIds): BookingAllocationReservationResult`

### Transaction boundary

`DB::transaction(...)` — nestable for Phase 2A.7’s larger Booking + Invoice transaction.

### Locking order

1. Booking (`lockForUpdate`)
2. CarbootEvent (`lockForUpdate`)
3. EventSites ordered by ID (`lockForUpdate`)
4. EventDays ordered by ID (`lockForUpdate`)

### Insert order

`event_day_id ASC`, then `event_site_id ASC`, with one shared `reserved_at`.

### Conflict / rollback

- Application recheck of `active_lock = 1`
- Unique index as final concurrency boundary
- Duplicate-key races mapped to `DomainConflictException` (`site_day_occupied`) — HTTP 409-ready
- Validation failures use `AllocationValidationException` — HTTP 422-ready
- Any failure rolls back all newly created allocation rows (no partial reserve)

---

## 12. Historical release behaviour

Released/cancelled rows remain stored with `active_lock = NULL`.  
A replacement booking creates **new** allocation rows. Never reactivate or reassign historical rows.

---

## 13. History protection

| Action | Behaviour |
|--------|-----------|
| EventSite delete | 409 if allocation history exists |
| EventSite structural update | 409 for `space_id`, `label`, `row_label`, `position_number`, `grid_row`, `grid_column` |
| EventSite safe update | `operational_status`, `display_order`, `metadata` allowed |
| EventDay delete | 409 if history exists |
| EventDay structural update | 409 for `operational_date`, `starts_at`, `ends_at` |
| EventDay safe update | `operational_status`, `display_order` allowed |
| EventSite `replace_existing` | Blocked before delete when any site has history |
| EventDay `replace_existing` | Blocked before delete when any day has history |
| Booking hard delete | 409 if allocation history exists |

---

## 14. Tests added

| File | Focus |
|------|-------|
| `BookingDayAllocationReservationTest` | relationships, full-event allocate, adjacency, pricing, conflicts, rollback, unique/CHECK, duplicate booking reserve |
| `AllocationHistoryProtectionTest` | site/day/generator/booking protections + governance regression + no public allocation routes |

---

## 15. Concurrency-test approach

True parallel clients are not reliable in single-process PHPUnit. Verification used:

1. unique index enforcement for duplicate `active_lock = 1`;
2. sequential stale-availability simulation where the second call fails with `DomainConflictException`;
3. rollback assertion that conflicting multi-site/multi-day attempts leave zero challenger rows.

Not described as true concurrency.

---

## 16. Known limitations

- Reservation is service-only; `POST /api/bookings` unchanged.
- Invoice amount / payment confirmation / release lifecycle deferred to Phase 2A.7+.
- Local Space catalogue prices may differ from documentation examples (derivation uses live `spaces.price`).
- CHECK formulation required a follow-up migration for NULL-safe semantics.

---

## 17. Phase 2A.7 integration contract

Phase 2A.7 must:

1. Accept `event_site_ids` on booking create.
2. Inside **one** DB transaction: create Booking + Invoice + call `BookingAllocationReservationService::reserveForBooking()`.
3. Persist derived `amount` onto Invoice.
4. Map `AllocationValidationException` → 422 and `DomainConflictException` → 409.
5. Dual-write compatibility fields (`space_id`, `booking_date`) without treating them as occupancy authority.

Do not start Phase 2A.7 from this document alone without review.
