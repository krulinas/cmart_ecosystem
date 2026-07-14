# Phase 2A.2 — Physical Parking-Site and Booking Allocation Architecture Decision Record

**Document date:** 2026-07-14  
**Repository:** CMart / Carboot@CMart (`cmart_ecosystem`)  
**Status:** Accepted for implementation planning  
**Supersedes for architecture scope:** Phase 2A.1 audit recommendations where they conflict with approved Phase 2A business invariants (notably: reservation timing, explicit `event_days`, and local dummy cleanup instead of backfill)

**Scope of this task:** Document-only. No migrations, schema changes, production code, routes, UI, tests, or database records were modified.

---

## 1. Decision Status

**Accepted.**

This ADR locks the foundational architecture for:

- physical event-scoped parking sites;
- operational event days;
- booking-day allocations;
- reservation and confirmation lifecycle;
- concurrency / uniqueness strategy;
- compatibility dual-write;
- synthetic label retirement path;
- local dummy-data cleanup safety;
- Phase 2A subphase boundaries.

Implementation begins only after this ADR is reviewed. Phase 2A.3 is the next approved task (local dummy-data cleanup design/execution boundaries as defined here).

---

## 2. Context

Phase 2A.1 established that the current system can remain the **approval and financial parent**, but cannot safely support:

```text
one booking → one or more parking sites → allocated across operational event days
→ with future per-day release capability
```

Verified gaps from the audit and re-checked against code:

| Finding | Evidence |
|---------|----------|
| `spaces` is a booth-type catalogue | `Space.php`; seeder creates “Standard (1 Parking Lot)” / “Large (2 Parking Lots)” |
| Labels such as `A-07` are synthetic | `VendorBookingPresenter::boothNumber()`; `bookingDisplay.js::boothLabelForBooking()` |
| No vendor site conflict check | `BookingController::store()` creates booking without occupancy query |
| `booking_date` is event start date only | `store()` sets `booking_date = $event->starts_at->toDateString()` |
| `max_slots` is RSVP capacity | `CarbootEvent::syncCapacityStatus()`; `EventRegistrationController` |
| Payment separate from approval | `invoices.payment_status` vs `bookings.approval_status` |
| Booking + invoice suitable as parents | 1:1 invoice create in `store()` |
| Pass/check-in are whole-booking | `VendorEventPassService`; single `checked_in_at` |
| CMart Management blocked from booking ops | `GovernanceAccessBoundaryTest`; route middleware |
| Hard delete cascades invoice | `create_invoices_table` uses `onDelete('cascade')` |

Approved Phase 2A business invariants (Section 4) must not be reopened unless a technical impossibility is proven. None was found.

---

## 3. Repository Evidence Reviewed

### Documentation
- `docs/phase-2/phase-2a-booking-architecture-audit.md` (primary diagnosis)
- `docs/governance/phase-1-3*.md` (canonical roles and direct Organizer workflow)

### Backend (material to decisions)
- `backend/app/Models/Booking.php`, `Space.php`, `CarbootEvent.php`, `Invoice.php`, `BookingAuditLog.php`
- `backend/app/Http/Controllers/Api/BookingController.php`
- `backend/app/Http/Controllers/Api/EventRegistrationController.php` (transaction + `lockForUpdate` reference)
- `backend/app/Services/VendorBookingPresenter.php`, `VendorEventPassService.php`, `BookingAuditLogger.php`
- `backend/routes/api.php`
- Migrations for bookings, spaces, invoices, carboot_events, withdrawal, payment proof, status ENUM
- Feature tests: organizer workflow, governance boundary, vendor demo payment, community booking access

### Frontend (material to decisions)
- `frontend/src/views/auth/Registration.vue` (typed `tapak_quantity` / `total_price`)
- `frontend/src/utils/bookingDisplay.js` (synthetic booth labels)
- `frontend/src/utils/eventDisplay.js` (Asia/Kuala_Lumpur naive datetime handling)
- Organizer bookings panel, vendor booking details, payment/pass/check-in components
- E2E booking helpers and specs under `frontend/tests/e2e/`

### Workspace note
`frontend/tests/e2e/helpers/organizer-bookings.js` has a pre-existing local modification. This ADR does not touch it.

---

## 4. Business Invariants

These are locked for Phase 2A and later Phase 2 unless an ADR amendment is approved.

1. **Canonical roles:** `community`, `organizer`, `cmart_management`, `super_admin`. No database role named `vendor`.
2. **Organizer** owns Carboot booking operations; **cmart_management** does not.
3. **Canonical approval statuses:** `Pending_Organizer`, `Needs_Revision`, `Approved`, `Rejected`, `Cancelled`, `Withdrawn`. No staff/manager two-tier flow.
4. **One Booking** is the parent for ownership, event link, approval, invoice, payment, receipt, and overall status.
5. **One Invoice** per booking remains the financial parent.
6. **Physical parking bays** are real event-scoped positions (`A01`, `A05`, …), not booth types.
7. **Vendor selects sites at booking creation**; submission reserves them transactionally.
8. **Multiple sites** allowed; quantity derived from validated site IDs; price computed by backend.
9. **Adjacent sites** (same row, consecutive positions) required for multi-site selection.
10. **Full event duration** is the default allocation scope for selected sites.
11. **One shared layout** per event for Phase 2A.
12. **Availability is derived**, not stored as a permanent `Available` flag on the physical site.
13. **`Needs_Revision` retains Reserved allocations**; no automatic expiry in Phase 2A.
14. **Local dummy booking cleanup** is authorized later; no production/history deletion is authorized here.
15. **Verified payment history must remain** after any later withdrawal (no automatic refund).

---

## 5. Architectural Drivers

| Driver | Implication |
|--------|-------------|
| Prevent false hope | Reserve at submission; recheck under lock |
| Concurrency safety | DB uniqueness + transactions; reject app-only checks |
| Multi-day future release | Stable `event_day` identity, not only ad-hoc date strings |
| Financial integrity | Invoice cascade risk requires deletion policy |
| Cinema-style UI later | Store deterministic row/position/order data now |
| Additive rollout | Dual-write compatibility fields; additive API payloads |
| Governance lock | Organizer-only site and release operations |
| Minimize overbuild | Shared layout; no freeform pixel designer; defer aisles |

---

## 6. Options Considered

### 6.1 Physical sites vs overloading `spaces`
| Option | Decision |
|--------|----------|
| A. Keep `spaces` as type catalogue; add `event_sites` | **Selected** |
| B. Replace `spaces` with physical sites | Rejected — breaks pricing/seeders/`bookings.space_id` |
| C. Rename `spaces` | Rejected — unnecessary migration churn |
| D. Compatibility-only retain `spaces` with no type link | Rejected — pricing still needs a type source |

### 6.2 Operational event days
| Option | Decision |
|--------|----------|
| A. Generate dates dynamically only | Rejected as sole occupancy identity — weak FK for release/check-in |
| B. Explicit `event_days` table | **Selected** |
| C. Store only `operational_date` on allocations | Rejected — duplicates day identity; poor reuse for per-day hours/status |

### 6.3 Uniqueness under historical releases
| Option | Decision |
|--------|----------|
| A. Nullable `active_lock` unique index | **Selected** |
| B. Generated/virtual active-lock column | Acceptable variant of A if MySQL version supports it; not required initially |
| C. Separate active + history tables | Rejected for Phase 2A — extra join complexity |
| D. Service-only conflict checks | **Rejected** — race-unsafe |

### 6.4 Cross-midnight
| Option | Decision |
|--------|----------|
| Calendar-date auto-split only | Rejected as silent and unsafe |
| Organizer-defined `day_generation_mode` + explicit `event_days` | **Selected** |
| Always one day regardless of range | Rejected — blocks Saturday–Sunday events |

### 6.5 Local data transition
| Option | Decision |
|--------|----------|
| Backfill existing local bookings into allocations | Rejected — owner authorized dummy cleanup instead |
| Guarded local dummy cleanup before new schema use | **Selected** |

---

## 7. Final Architecture Decision

### Selected model

```text
CarbootEvent
├── EventDays          (explicit operational participation days)
└── EventSites         (physical parking bays for this event; shared across days)

Space                  (booth/site TYPE catalogue — retained)

Booking                (approval + ownership + event parent)
├── Invoice            (1:1 financial parent)
└── BookingDayAllocations
    ├── EventDay
    └── EventSite
```

### Occupancy unit

One `booking_day_allocations` row =

```text
one booking + one event day + one event site + one allocation status
```

### Default multi-day / multi-site example

```text
Booking #101
├── Sat + A05 (reserved/confirmed)
├── Sat + A06
├── Sun + A05
└── Sun + A06

Invoice: one amount for the booking
```

### Authority split

| Concern | Authority |
|---------|-----------|
| Approval | `bookings.approval_status` |
| Money | `invoices.payment_status` + `amount` |
| Occupancy | `booking_day_allocations.allocation_status` |
| Physical bay usability | `event_sites.operational_status` |
| Day usability | `event_days.operational_status` |
| Display label | `event_sites.label` |

---

## 8. Entity Responsibility Matrix

| Entity | Responsibility | Must not own |
|--------|----------------|--------------|
| **Booking** | Vendor ownership; event link; overall approval status; product details; withdrawal/cancel parent; dual-write compatibility fields | Physical occupancy uniqueness; invoice line items; synthetic site labels |
| **Invoice** | Amount; payment status; proof path; payment submitted/verified history | Site occupancy; adjacency; day allocation |
| **Space** | Booth/site type catalogue (`space_size`, default type price, type status) | Event layout positions; occupancy |
| **EventSite** | Event-scoped physical parking bay; label; row/position/grid; operational enable/disable; optional type link | Booking approval; payment; RSVP capacity |
| **EventDay** | Stable operational day identity for an event; MYT operational date and window; day enable/disable | Payment; vendor ownership |
| **BookingDayAllocation** | One booking’s occupancy of one site on one day; reserved/confirmed/released/cancelled history | Invoice/payment fields; product details; QR payload redesign |
| **BookingAuditLog** | Actor + booking status/action trail | Be the only history for site release (allocations retain release metadata; audit may record action later) |

---

## 9. Physical Event-Site Model

### Selected table: `event_sites`

| Field | Responsibility | Required now | Optional | Index / constraint | Audience |
|-------|----------------|--------------|----------|--------------------|----------|
| `id` | Primary key | Yes | — | PK | All |
| `carboot_event_id` | Event ownership | Yes | — | FK + part of unique | Business |
| `space_id` | Type catalogue link for pricing | Yes for Phase 2A pricing | Nullable later only if pricing strategy changes | FK | Compatibility / pricing |
| `label` | Display label e.g. `A05` | Yes | — | Unique per `(carboot_event_id, label)` | UI + business |
| `row_label` | Adjacency row key e.g. `A` | Yes | — | Index with position | Business / layout |
| `position_number` | Order within row (integer) | Yes | — | Unique per `(carboot_event_id, row_label, position_number)` | Business / adjacency |
| `grid_row` | Visual grid row | Yes | — | Index with `grid_column` | Layout |
| `grid_column` | Visual grid column | Yes | — | — | Layout |
| `display_order` | Deterministic list/order fallback | Yes | — | Index | Layout |
| `operational_status` | `active` \| `unavailable` \| `disabled` | Yes | — | Index | Business |
| `metadata` | Lightweight JSON for future markers | No | Yes | — | Future layout |
| timestamps | Audit timestamps | Yes | — | — | Ops |

### Decisions
- **Freeform pixel X/Y coordinates:** not required in Phase 2A. Grid row/column is sufficient.
- **Aisles / entry markers:** deferred; may later use `metadata` or a separate `event_layout_elements` table (Option A deferred; JSON Option B only if needed).
- **One shared layout across all event days:** ADR-018 — same `event_sites` for Saturday and Sunday.
- **Label source of truth:** `event_sites.label` only. Never derive from booking ID.

---

## 10. Existing Space-Type Compatibility

### Decision: Option A (ADR-003)

Retain `spaces` as the **booth/site-type catalogue**.

| Concern | Handling |
|---------|----------|
| Current pricing | Type price remains on `spaces.price`; booking invoice sums linked site types |
| `bookings.space_id` | Remains as temporary compatibility/type summary field (typically the primary or first selected type) |
| Seeders | Keep Standard/Large types; Organizer generates `event_sites` referencing a type |
| API payloads | Continue exposing `space` where present; add `selected_sites` / `allocations` additively |
| Frontend | Stop treating typed `tapak_quantity` as authority once site selection ships |
| Migration risk | Low — additive tables only; no rename/drop of `spaces` in Phase 2A |

`spaces.status` (`Available`/`Full`) is **not** the vendor occupancy signal and must not be reused for physical site availability.

---

## 11. Operational Event-Day Model

### Decision: explicit `event_days` table (ADR-004)

| Field | Responsibility | Required now |
|-------|----------------|--------------|
| `id` | Stable FK for allocations | Yes |
| `carboot_event_id` | Event ownership | Yes |
| `operational_date` | Calendar day in MYT (`YYYY-MM-DD`) | Yes |
| `starts_at` | Day operational start | Yes |
| `ends_at` | Day operational end | Yes |
| `operational_status` | `active` \| `cancelled` \| `disabled` | Yes |
| `display_order` | Ordering | Yes |
| timestamps | Ops | Yes |

**Unique constraint:** `(carboot_event_id, operational_date)`.

### Why not dynamic-only generation
Future partial release, per-day check-in, and day cancellation need a stable foreign key. Dynamic date expansion alone makes historical allocation identity fragile when `starts_at`/`ends_at` are edited.

### Creation rule for Phase 2A
Organizer (or a controlled generation service during Phase 2A.6) creates `event_days` according to the event’s **day generation mode** (Section 12). Booking submission **consumes existing active event days**; it does not invent ad-hoc dates.

---

## 12. Cross-Midnight Event Rule

### Decision (ADR-004 companion)

Each `CarbootEvent` carries an Organizer-controlled `day_generation_mode`:

| Mode | Meaning | Example |
|------|---------|---------|
| `calendar_days` | One `event_day` per distinct MYT calendar date between event window inclusive | Sat 08:00 → Sun 17:00 → two days |
| `single_session` | Exactly one `event_day`, even if the window crosses midnight | Fri 22:00 → Sat 01:00 → one day |

### Rules
1. Mode is **explicit and auditable**, not inferred silently at booking time.
2. Default for new Carboot events in Phase 2A: `calendar_days` when Organizer confirms a multi-day carboot; Organizer must set `single_session` for overnight one-session operations.
3. Changing mode after active reservations exist is a controlled Organizer action with conflict checks (Risk R-03).
4. Frontend date helpers remain Malaysia-timezone aware (`eventDisplay.js` / backend MYT policy).

---

## 13. Booking-Day Allocation Model

### Selected table: `booking_day_allocations`

| Field | Now / later | Nullable | Notes |
|-------|-------------|----------|-------|
| `id` | Now | No | PK |
| `booking_id` | Now | No | FK to parent booking |
| `event_day_id` | Now | No | FK — occupancy day identity |
| `event_site_id` | Now | No | FK — physical bay |
| `allocation_status` | Now | No | `reserved` \| `confirmed` \| `released` \| `cancelled` |
| `reserved_at` | Now | No | Set at booking submission |
| `confirmed_at` | Now | Yes | Set when payment verified (and demo-paid path) |
| `released_at` | Now | Yes | Set when released |
| `released_by` | Now | Yes | Actor user id when released |
| `release_reason` | Later enrichment | Yes | Required text can deepen in withdrawal phases |
| `active_lock` | Now | Yes | `1` when reserved/confirmed; `NULL` when released/cancelled |
| timestamps | Now | No | — |

### Must not live on allocations
- Invoice amount / payment status / payment proof
- Product category/details
- Overall `approval_status`
- QR / pass redesign fields

Release metadata on the allocation is **operational**, not financial.

---

## 14. Allocation Status and Lifecycle

### Status set

| Status | Meaning | Occupies site? |
|--------|---------|----------------|
| `reserved` | Held by a submitted booking awaiting confirmation | Yes (`active_lock = 1`) |
| `confirmed` | Payment verified (or demo paid) for an approved path | Yes (`active_lock = 1`) |
| `released` | Occupancy ended; historical row retained | No (`active_lock = NULL`) |
| `cancelled` | Terminal occupancy cancel synonymous with released semantics for availability; retained for clarity of cause if desired | No (`active_lock = NULL`) |

### Clarifications
- **`cancelled` vs `released`:** both free the site. Use `released` for Organizer/rejection/cancel/withdrawal releases. Use `cancelled` only if a distinct terminal mapping is needed for analytics; Phase 2A may map Rejected/Cancelled booking outcomes to `released` uniformly to reduce status noise. **Selected Phase 2A mapping:** Rejected and Cancelled → set allocations to `released` (one historical terminal for occupancy). Keep enum value `cancelled` reserved for later precision if needed, but Phase 2A lifecycle uses `released` for reject/cancel.
- **Historical rows remain forever** for occupancy audit; never hard-deleted by normal operators.
- **Replacement vendor:** always creates **new** allocation rows; never reassigns ownership of an old row.
- **`Needs_Revision`:** does **not** change allocation status; remains `reserved`.
- **Payment verification:** transitions `reserved` → `confirmed` for the booking’s active allocations (requires booking `Approved` and invoice path already enforced by existing payment rules).
- **Reactivation:** not allowed. Create new allocations on a new booking.

### Lifecycle diagram

```text
(no active allocation) ──submit──► reserved ──payment verified──► confirmed
                                   │                                │
                                   └──────── reject/cancel ─────────┴──► released
                                   │
                          (later withdrawal phases)
                                   confirmed ──full/partial withdraw──► released
```

---

## 15. Site Availability Derivation

A physical site is **available** for an event day when and only when:

1. `event_sites.operational_status = active`;
2. `event_days.operational_status = active`;
3. no allocation exists for that `(event_day_id, event_site_id)` with `active_lock = 1` (i.ato status `reserved` or `confirmed`).

### Availability-state matrix

| Physical site state | Allocation state | Vendor-facing API state | Notes |
|---------------------|------------------|-------------------------|-------|
| active | none | `available` | Selectable |
| active | reserved | `reserved` | Not selectable |
| active | confirmed | `confirmed` | Not selectable |
| unavailable / disabled | any | `unavailable` | Not selectable |
| active | (browser only) | `selected` | **Not stored in DB** |

Internal statuses remain on tables; API may expose the vendor-facing labels above for layout rendering.

---

## 16. Multi-Site and Adjacency Rules

### Quantity
`tapak_quantity = count(unique validated event_site_ids)` for the booking’s event.

### Adjacency (authoritative on backend)

Multiple selected sites are adjacent when:

1. all belong to the same `carboot_event_id`;
2. all are unique;
3. all are operationally `active`;
4. all share the same `row_label`;
5. their `position_number` values form a contiguous integer sequence with no gaps;
6. site types are compatible — Phase 2A rule: **all selected sites must share the same `space_id` (type)** unless a later Organizer override is approved.

**Valid:** `A05+A06`, `A05+A06+A07`  
**Invalid:** `A05+A07`, `A05+B05`, sites from another event, duplicates, disabled sites

Frontend adjacency is guidance only.

---

## 17. Quantity and Pricing Authority

### Authority
| Input | Client | Backend |
|-------|--------|---------|
| `event_site_ids` | Submit claim | **Authoritative validation** |
| `tapak_quantity` | Deprecated as source of truth | **Derived** |
| `total_price` | Reject as authoritative | **Derived** |
| Invoice `amount` | — | Written inside transaction |

### Pricing formula (Phase 2A)
```text
amount = sum(event_site.space.price for each selected site)
```
Because adjacency requires same type in Phase 2A, this equals:

```text
count(sites) × common_type_price
```

Mixed-type multi-site selections are **rejected** in Phase 2A.

Hard-coded `tapak_quantity * 20` in `BookingController::store()` is replaced in Phase 2A.9 by type-based calculation while preserving one invoice per booking.

---

## 18. Full-Event Allocation Rule

For each validated selected `event_site`:

- create one `booking_day_allocations` row for **every active `event_days` row** of that event;
- use the same `event_site_id` across all days;
- create all rows in the same transaction as booking + invoice;
- fail entirely if any site-day is conflicted.

**Single-day:** 2 sites × 1 day = 2 allocations  
**Two-day:** 2 sites × 2 days = 4 allocations

Vendors do not re-select per day in the normal Phase 2A UI.

---

## 19. Database Uniqueness and Concurrency Strategy

### Decision: Strategy A — nullable `active_lock` (ADR-012)

```text
active_lock = 1   when allocation_status IN ('reserved', 'confirmed')
active_lock = NULL when allocation_status IN ('released', 'cancelled')
```

**Unique index:**

```text
UNIQUE (event_day_id, event_site_id, active_lock)
```

MySQL allows multiple rows with `active_lock = NULL`, so historical released rows may coexist; only one active occupant may exist per site-day.

### Supporting locks
At booking submission:

1. `DB::transaction`
2. `lockForUpdate()` on candidate `event_sites` rows (ordered by id to avoid deadlock)
3. Optional shared lock pattern on the parent `carboot_events` row if needed for day generation stability
4. Re-query absence of `active_lock = 1` allocations for each `(event_day, event_site)`
5. Insert booking + invoice + allocations
6. Commit or full rollback

Reference pattern: `EventRegistrationController::register()`.

### Strategy D rejected
Application-only conflict checks are insufficient under concurrent vendors.

---

## 20. Booking Submission Transaction Boundary

```text
Vendor POST { event_id, event_site_ids[], product_* }
  → validate auth + event bookable + event_days exist
  → begin transaction
  → lock event_sites (ordered)
  → validate sites belong to event, active, unique, adjacent, same type
  → lock/read event_days (active)
  → recheck no active allocations for all (day × site) pairs
  → derive quantity + amount
  → create Booking (Pending_Organizer; dual-write space_id + booking_date)
  → create Invoice (Unpaid; amount derived)
  → create BookingDayAllocations (reserved, active_lock=1) for all day×site
  → commit
  → return booking + selected_sites + allocations summary
```

### Failure behaviour
Any validation or uniqueness failure rolls back **all** of booking, invoice, and allocations. API returns a clear conflict/validation error; client refreshes availability.

### Before submission
`selected` exists only in the browser. Sites may become unavailable after the UI was painted — backend revalidation is authoritative.

---

## 21. Organizer Decision and Payment Transitions

### Site lifecycle matrix

| Trigger | Booking status | Payment status | Allocation result | Site availability |
|---------|----------------|----------------|-------------------|-------------------|
| Successful booking submission | Pending_Organizer | Unpaid | All day×site → `reserved` | Unavailable to others |
| Needs_Revision | Needs_Revision | Unpaid (typical) | Remain `reserved` | Still unavailable |
| Approved and unpaid | Approved | Unpaid | Remain `reserved` | Still unavailable |
| Payment verified / demo paid | Approved | Paid | `reserved` → `confirmed` | Still unavailable |
| Rejected | Rejected | Unchanged | Active → `released` | Available again |
| Cancelled (vendor pending cancel) | Cancelled | Unchanged | Active → `released` | Available again |
| Future full withdrawal | Withdrawn | Paid remains Paid | Active → `released` (deferred rules) | Available again (**deferred**) |
| Future partial withdrawal | Approved or Withdrawn (policy later) | Paid remains Paid | Selected day×site → `released` (**deferred**) | Partial (**deferred**) |

### Needs_Revision operational risk
Reserved sites may remain held indefinitely while a vendor delays resubmission. **Expiry is a future enhancement**, not Phase 2A scope. Organizers may reject to release sites.

### Audit actions
Continue using `BookingAuditLogger` for approval/payment actions. Allocation release should emit an audit action in Phase 2A.10 (e.g. `allocations_released_on_reject`) without replacing allocation history rows.

---

## 22. API Evolution

### Additive request (Phase 2A.9+)

```json
{
  "event_id": 10,
  "event_site_ids": [15, 16],
  "product_category": "Food & Beverages",
  "product_details": "..."
}
```

Do not trust client `tapak_quantity` / `total_price` as authoritative. During dual compatibility, accept them only for temporary clients if needed, but still **recalculate** and reject mismatch.

### Additive response fields

```json
{
  "booking": { "...existing fields..." },
  "invoice": { "...existing fields..." },
  "selected_sites": [
    { "id": 15, "label": "A05", "row_label": "A", "position_number": 5 }
  ],
  "allocations": [
    {
      "event_day_id": 1,
      "operational_date": "2026-07-18",
      "event_site_id": 15,
      "site_label": "A05",
      "status": "reserved"
    }
  ],
  "derived": {
    "tapak_quantity": 2,
    "amount": 40.0
  }
}
```

### Availability endpoint (Phase 2A.11 prerequisite; may land with 2A.5/2A.8)
Organizer-managed listing and vendor layout feed must expose derived vendor-facing states without inventing DB `selected`.

Existing routes remain; new fields/endpoints are additive. Governance middleware patterns unchanged.

---

## 23. Synthetic Booth-Label Retirement

### Current generators (must be retired)
| Module | Location |
|--------|----------|
| Backend presenter | `VendorBookingPresenter::boothNumber()` |
| Frontend util | `bookingDisplay.js::boothLabelForBooking()` |
| Downstream consumers | Vendor booking details, checkout, history receipts, analytics booth snapshot, event pass DTO (`booth_label`), Organizer UI that displays space/booth, E2E assertions expecting synthetic patterns |

### Source-of-truth order during transition

1. Labels from **active allocations’ `event_sites.label`** (deduped selected sites);
2. Else explicit **“Site not assigned”** / equivalent i18n string;
3. **Never** invent a label from booking ID.

### Staged plan (implementation phases, not this document task)
1. Dual-write allocations populate real labels on new bookings.
2. Presenters/resources prefer real labels when allocations exist.
3. Frontend switches to API-provided labels.
4. Remove `boothNumber` / `boothLabelForBooking` ID algorithms.
5. Update PDF/receipt/pass/E2E to use real labels or “Site not assigned”.

No production code changes in Phase 2A.2.

---

## 24. Compatibility and Dual-Write Strategy

### Compatibility matrix

| Existing field/module | Current responsibility | Phase 2A transition | Future disposition |
|-----------------------|------------------------|---------------------|--------------------|
| `bookings.space_id` | Forced Standard type | Dual-write primary/common selected type | Deprecate after all consumers use allocations |
| `bookings.booking_date` | Event start date | Dual-write first `event_days.operational_date` | Deprecate when day lists are universal |
| `bookings.carboot_event_id` | Event link | Remains authoritative | Keep |
| `bookings.approval_status` | Approval parent | Remains authoritative | Keep |
| Client `tapak_quantity` | User-typed quantity | Compatibility input only; backend derives | Remove from clients |
| Client `total_price` | Client-checked RM20×qty | Ignored for authority; backend derives | Remove from clients |
| Synthetic booth number | Fake label | Dual present → real labels | Delete generators |
| `bookings.checked_in_at` | Whole-booking check-in | Unchanged in Phase 2A | Per-day check-in later |
| `invoices.payment_status` | Payment authority | Unchanged | Keep; no refund auto-flow |

### Dual-write period
New booking flow writes bookings + invoices **and** allocations until Organizer UI, vendor UI, pass, and E2E consume allocations.

---

## 25. Hard-Delete and Financial-History Protection

### Decision (ADR-017)

Routine application behaviour must **not** hard-delete bookings that have operational or financial history.

Future policy direction:

1. Prefer terminal statuses (`Rejected`, `Cancelled`, `Withdrawn`) over delete.
2. Block `DELETE /api/bookings/{id}` when an invoice exists or allocations exist (or always block outside explicit local tooling).
3. Soft deletes are **optional** and must not break uniqueness semantics; if introduced later, soft-deleted bookings must not hold `active_lock`.
4. Separate local dummy cleanup tooling from operator delete UX.

Cascade reality today (`invoices.booking_id` → `onDelete('cascade')`) is a known risk (audit F-013). Protection ships in an implementation subphase before schema rollout that creates durable financial allocations, or as part of Phase 2A.3/2A.9 guards.

No schema or controller change in Phase 2A.2.

---

## 26. Local Dummy-Data Cleanup Safety Design

### Decision (ADR-016)

Later Phase 2A.3 cleanup uses a **one-time Artisan command** (preferred), not `migrate:fresh`, not broad truncation, not production-capable SQL scripts without guards.

### Mandatory safety rules

1. Verify `app()->environment()` is `local` (or explicitly allowlisted development); **refuse** production and staging unless separately authorized (default refuse).
2. Print **before** counts (bookings, invoices, audit logs, payment-proof files referenced).
3. Identify dummy records by explicit criteria (e.g. known demo titles/emails/IDs documented in the command, or `--booking-ids=` allowlist). Unmatched records are skipped.
4. Delete only related dummy invoices, booking audit logs, and confirmed-dummy payment-proof files on the public disk.
5. Do not delete unrelated users, feedback, news, events, images, or non-dummy bookings.
6. Print **after** counts and write a Markdown/console report.
7. Never run `db:wipe`, `migrate:fresh`, or truncate of shared catalogue tables (`spaces`, `users`, `carboot_events`) as part of this cleanup.

No records are deleted in Phase 2A.2.

Note: the Phase 2A.1 backfill-oriented path is **not** used for local dummy bookings. Clean baseline first, then create allocations on new bookings.

---

## 27. Authorization and Governance Boundaries

| Action | community | organizer | cmart_management | super_admin |
|--------|:---------:|:---------:|:----------------:|:-----------:|
| Select sites on own booking create | ✅ | ❌ | ❌ | ❌ |
| Reserve sites via own booking submission | ✅ | ❌ | ❌ | ❌ |
| Review allocations / layout ops | ❌ | ✅ | ❌ | ✅ technical |
| Approve/reject/revise booking | ❌ | ✅ | ❌ | ✅ technical |
| Verify payment → confirm allocations | ❌ | ✅ | ❌ | ✅ technical |
| Release via reject/cancel lifecycle | via own cancel / org reject | ✅ | ❌ | ✅ technical |
| Raw analytics | ❌ | ✅ | ❌ | ✅ |
| Generated reports only | — | ✅ | ✅ | ✅ |

API enforcement remains middleware + controller checks (no Policy layer today). Frontend hiding is insufficient. CMart Management must not receive site assign/release endpoints.

---

## 28. Test Architecture

No tests are written in Phase 2A.2. Later subphases must add:

### Event-site
- Unique label per event; same label allowed on another event
- Stable row/position uniqueness
- Disabled site excluded from availability

### Event-day
- Single-day → one day
- Sat–Sun `calendar_days` → two days
- `single_session` overnight → one day
- Unsafe event update with active reservations blocked or conflict-reported

### Allocation
- One site one day; one site multi-day; multi-site multi-day
- Unique active occupancy
- Released history remains
- Replacement booking creates new rows

### Reservation / concurrency
- Sites reserve on submission
- Concurrent/duplicate rejected
- Non-adjacent rejected; price derived server-side
- Full transaction rollback on conflict

### Lifecycle
- Needs_Revision retains reserved
- Approved unpaid remains reserved
- Payment verify confirms
- Rejected/Cancelled releases

### Governance
- Community ownership only
- Organizer can review; cmart_management cannot assign/release
- Direct API blocked for forbidden roles

### Compatibility
- Existing fields still present
- One invoice per booking
- Synthetic label path removed only when real labels available
- Existing single-day approval/payment E2E suite remains green

Preserve existing protective tests listed in the Phase 2A.1 audit.

---

## 29. Phase 2A Subphase Plan

| Subphase | Narrow responsibility |
|----------|----------------------|
| **2A.3** | Guarded local dummy booking/invoice/audit/payment-proof cleanup + report; deletion guard recommendations for operator hard-delete |
| **2A.4** | `event_sites` schema, model, indexes, basic CRUD/read APIs for Organizer |
| **2A.5** | Organizer site generation / basic layout definition backend (labels, rows, positions, grid, type link) — no cinema UI |
| **2A.6** | `event_days` schema; `day_generation_mode`; generation service; MYT/cross-midnight rules |
| **2A.7** | `booking_day_allocations` schema + relationships + `active_lock` uniqueness |
| **2A.8** | Reservation conflict service: locks, adjacency, availability derivation, concurrency tests |
| **2A.9** | Booking create + invoice integration: `event_site_ids`, derived qty/price, dual-write compatibility fields |
| **2A.10** | Organizer decision and payment transitions: reserve hold, confirm on Paid, release on Rejected/Cancelled |
| **2A.11** | Vendor cinema-style parking selection UI (original Carboot branding), selected summary, refresh-on-conflict |

Additional optional subphase if needed: **2A.12** presenter/API label cutover + synthetic generator removal + PDF/pass label alignment.

---

## 30. Deferred Phase 2 Capabilities

- Paid withdrawal with no-refund acknowledgement (BM copy)
- Partial day withdrawal / emergency one-day release
- Organizer manual release queue and replacement vendor offering
- Per-day pass validation and per-day check-in records
- Site-day analytics metrics
- Automatic reservation expiry for Needs_Revision
- Organizer adjacency override
- Per-day different layouts or per-day site disable UI
- Full layout designer / freeform pixel editor
- Refund workflow

---

## 31. Risks and Mitigations

| ID | Risk | Likelihood | Impact | Mitigation | Subphase |
|----|------|------------|--------|------------|----------|
| R-01 | Double booking under concurrency | High without controls | Critical | `active_lock` unique + transaction locks | 2A.7–2A.8 |
| R-02 | Indefinite Needs_Revision hold | Medium | Medium | Organizer reject to release; future expiry | 2A.10 / later |
| R-03 | Event date edits after reservation | Medium | High | Block unsafe day regeneration; require Organizer resolution | 2A.6 |
| R-04 | Delete sites with active allocations | Medium | High | Restrict delete/disable rules when `active_lock` exists | 2A.4–2A.5 |
| R-05 | Delete events with financial history | Medium | Critical | Cascade/policy guards; no casual event wipe | 2A.3 / 2A.9 |
| R-06 | Mixed site types / pricing | Low (blocked) | Medium | Same-type adjacency rule in Phase 2A | 2A.8–2A.9 |
| R-07 | Layout changes after reservation | Medium | High | Freeze occupied layout labels; controlled edits | 2A.5 |
| R-08 | Cross-midnight ambiguity | Medium | High | Explicit `day_generation_mode` | 2A.6 |
| R-09 | Stale frontend availability | High | Medium | Backend revalidate; refresh API on 409 | 2A.8–2A.11 |
| R-10 | Synthetic-label compatibility | High until cutover | Medium | Dual present order; then remove generators | 2A.9–2A.12 |
| R-11 | Hard-delete invoice cascade | Medium | Critical | Block hard delete; local-only cleanup command | 2A.3 / 2A.9 |
| R-12 | Future partial withdrawal | Certain later | High | Allocation rows already per day×site | Deferred |
| R-13 | Future per-day check-in | Certain later | Medium | `event_day_id` ready; pass redesign deferred | Deferred |
| R-14 | RSVP/vendor capacity confusion | High | Medium | Keep `max_slots` RSVP-only; document vendor capacity as site count | Docs + analytics later |
| R-15 | Dummy cleanup outside local | Low if guarded | Critical | Environment refuse; allowlists; report | 2A.3 |

---

## 32. Final Decision Register

| ID | Decision | Selected approach | Reason | Deferred consequence |
|----|----------|-------------------|--------|----------------------|
| ADR-001 | Booking remains parent | One booking for multi-site/multi-day | Matches invoice/approval design and E2E | Never split into per-day bookings |
| ADR-002 | Physical event-site representation | New `event_sites` table | `spaces` is type-only | Layout designer deferred |
| ADR-003 | Existing `spaces` responsibility | Retain as type catalogue; link from `event_sites` | Pricing/seeders/compatibility | Rename/remove later if ever needed |
| ADR-004 | Operational event-day representation | Explicit `event_days` + Organizer `day_generation_mode` | Stable FK for release/check-in | Dynamic-only expansion rejected |
| ADR-005 | Reservation timing | Reserve on successful booking submission | Prevents false hope; business invariant | Soft-hold timers deferred |
| ADR-006 | Needs_Revision site hold | Keep `reserved`; no auto-expiry in 2A | Allows vendor correction | Expiry enhancement later |
| ADR-007 | Payment-confirmation transition | Paid verification (and demo-paid) → `confirmed` | Aligns with pass unlock (`Approved`+`Paid`) | Refund states deferred |
| ADR-008 | Multi-site quantity rule | Derive count from validated site IDs | Client quantity untrustworthy | Mixed-type pricing deferred |
| ADR-009 | Adjacency rule | Same row + contiguous `position_number` + same type | Enforceable with stored fields | Organizer override later |
| ADR-010 | Full-duration allocation | Allocate all active event days for each selected site | Default business rule | Partial participation UI later |
| ADR-011 | Availability derivation | Active site + active day − active allocation | Avoids stale “Available” flags | — |
| ADR-012 | Database conflict strategy | Nullable `active_lock` unique index + row locks | MySQL-safe with history | Separate history table deferred |
| ADR-013 | Historical release strategy | Keep rows; new booking gets new allocations | Audit and reallocation clarity | Transfer of ownership forbidden |
| ADR-014 | Synthetic label retirement | Real labels → “Site not assigned” → remove generators | Ends false A-{id} labels | Cutover after allocations exist |
| ADR-015 | Compatibility fields | Keep `space_id`, `booking_date`, `carboot_event_id`, status dual-write | Additive rollout | Deprecate date/type after consumers migrate |
| ADR-016 | Dummy-data cleanup | Guarded local Artisan command | Owner-authorized; safer than backfill | No prod cleanup |
| ADR-017 | Hard-delete protection | Block/replace hard delete for bookings with history | Cascade destroys invoices | Soft delete optional later |
| ADR-018 | Shared layout across days | One `event_sites` set per event | Phase 2A scope | Per-day layouts deferred |

---

## 33. Phase 2A.2 Completion Gate

| # | Question | Status |
|---|----------|--------|
| 1 | What represents a physical parking site? | **Decided** — `event_sites` |
| 2 | What represents a booth/site type? | **Decided** — `spaces` |
| 3 | What represents an operational event day? | **Decided** — `event_days` |
| 4 | How are cross-midnight events handled? | **Decided** — `day_generation_mode` (`calendar_days` \| `single_session`) |
| 5 | What represents one site occupied for one day? | **Decided** — `booking_day_allocations` |
| 6 | When are sites reserved? | **Decided** — successful booking submission |
| 7 | When are sites confirmed? | **Decided** — payment verified / demo paid while Approved path |
| 8 | Which statuses release sites? | **Decided** — Rejected and Cancelled now; withdrawal later |
| 9 | Does Needs_Revision retain sites? | **Decided** — yes, remain reserved |
| 10 | How is availability derived? | **Decided** — see Section 15 |
| 11 | How is multiple-site quantity calculated? | **Decided** — count of validated unique site IDs |
| 12 | How is adjacency determined? | **Decided** — same row, contiguous positions, same type |
| 13 | How are all event days allocated? | **Decided** — Cartesian of selected sites × active days |
| 14 | What database rule prevents double booking? | **Decided** — unique `(event_day_id, event_site_id, active_lock)` |
| 15 | How are released rows retained historically? | **Decided** — status + `active_lock = NULL`; no hard delete |
| 16 | What is included in the booking transaction? | **Decided** — locks, validation, booking, invoice, allocations |
| 17 | Which existing fields remain temporarily? | **Decided** — Section 24 |
| 18 | How are synthetic labels retired? | **Decided** — Section 23 |
| 19 | How is financial history protected? | **Decided** — ADR-017 + cascade awareness |
| 20 | How will local dummy data be removed safely? | **Decided** — ADR-016 / Section 26 |
| 21 | What belongs to every remaining Phase 2A subphase? | **Decided** — Section 29 |
| 22 | Are any foundational decisions unresolved? | **Decided** — no foundational items remain Not decided |

---

## Appendix A — Proposed Tables and Fields

### `event_sites`
See Section 9.

### `event_days`
See Section 11. Plus event-level `day_generation_mode` on `carboot_events` (new column in Phase 2A.6).

### `booking_day_allocations`
See Section 13.

### Unchanged parents
`bookings`, `invoices`, `spaces`, `booking_audit_logs` remain; dual-write rules apply.

Exact SQL migrations belong to later subphases; this ADR defines responsibility, not migration files.

---

## Appendix B — Proposed Indexes and Constraints

| Table | Constraint / index |
|-------|--------------------|
| `event_sites` | UNIQUE `(carboot_event_id, label)` |
| `event_sites` | UNIQUE `(carboot_event_id, row_label, position_number)` |
| `event_sites` | INDEX `(carboot_event_id, operational_status)` |
| `event_sites` | INDEX `(carboot_event_id, display_order)` |
| `event_days` | UNIQUE `(carboot_event_id, operational_date)` |
| `event_days` | INDEX `(carboot_event_id, operational_status)` |
| `booking_day_allocations` | UNIQUE `(event_day_id, event_site_id, active_lock)` |
| `booking_day_allocations` | INDEX `(booking_id)` |
| `booking_day_allocations` | INDEX `(allocation_status)` |
| `booking_day_allocations` | FK `booking_id`, `event_day_id`, `event_site_id` — **restrict** deletes while history matters |

Prefer `RESTRICT`/`PROTECT` over cascade for allocations and prefer not cascading invoice deletes from casual booking deletes (implementation detail in 2A.3/2A.9).

---

## Appendix C — Proposed State Matrices

### C.1 Site lifecycle
See Section 21.

### C.2 Availability
See Section 15.

### C.3 Concurrency-failure matrix

| Scenario | Expected transaction result | Expected API response |
|----------|----------------------------|------------------------|
| One selected site already reserved | Full rollback | 409/422 conflict; refresh availability |
| One selected site disabled/unavailable | Full rollback | 422 validation |
| Duplicate site IDs in request | Reject before/at validate | 422 |
| Sites from different events | Reject | 422 |
| Non-adjacent multiple sites | Reject | 422 |
| Invalid site ID | Reject | 422 |
| Event day missing/unavailable | Reject | 422 (event not bookable for sites) |
| Invoice creation failure | Full rollback | 500/422 with no orphan booking |
| Allocation insert uniqueness race | Full rollback | 409 conflict |
| Mixed site types in multi-select | Reject | 422 |

---

## Appendix D — Repository Files Affected in Later Tasks

### Backend (later)
- `BookingController.php` (store + lifecycle transitions)
- New services e.g. `BookingReservationService` / `EventDayGenerator`
- New models: `EventSite`, `EventDay`, `BookingDayAllocation`
- `VendorBookingPresenter.php`, `VendorEventPassService.php`
- `routes/api.php` (additive)
- Migrations for new tables + `day_generation_mode`
- Feature tests for reservation, concurrency, lifecycle, governance
- Possibly `BookingController::destroy` protection

### Frontend (later, mainly 2A.11+)
- `Registration.vue` → site selection layout
- `bookingDisplay.js` label source
- Vendor details / receipts / pass / organizer panel consumers
- E2E booking helpers and specs

### Tooling (2A.3)
- New Artisan cleanup command under `backend/app/Console/Commands/`
- Cleanup report markdown under `docs/phase-2/` if needed (report file only when executed)

### Must not change in 2A.2
All of the above remain untouched by this ADR task except this document.

---

*End of Phase 2A.2 Architecture Decision Record. Do not begin Phase 2A.3 until this ADR is reviewed and accepted.*
