# Phase 3.2 — Event Layout Architecture Decision Record

| Field | Value |
|-------|-------|
| **Title** | Phase 3 — Event Layout & Category-Based Slot Planning |
| **Status** | Accepted |
| **Date** | 2026-07-15 |
| **Depends on** | Phase 2A–2B (physical allocation foundation), Phase 3.1 audit |
| **Blocks** | Phase 3.3 (test isolation) → Phase 3.4+ (implementation) |
| **ADR ID** | ADR-003 |

---

## Context

Phase 2 delivered a tested physical allocation engine: event-scoped sites (`event_sites`), operational days (`event_days`), atomic day-level reservations (`booking_day_allocations`), multi-site same-row adjacency, Organizer approval lifecycle, payment verification, withdrawal, and governance boundaries.

Phase 3.1 confirmed that category-based layout planning, eligibility enforcement, Organizer layout workspace, public navigation layout, and canonical category taxonomy are **not yet implemented**. Sites currently carry a denormalized `row_label` string; categories are hardcoded six-value enums in controllers and `frontend/src/constants/productCategories.js`.

This ADR converts locked product decisions and repository evidence into an implementation-ready contract for extending Phase 2 without redesigning the allocation engine.

---

## Existing Foundation

### Verified Phase 2 contracts

| Domain | Key paths | Behaviour |
|--------|-----------|-----------|
| Event sites | `backend/app/Models/EventSite.php`, `backend/database/migrations/2026_07_14_000001_create_event_sites_table.php` | Physical bays per event; unique `(event, label)` and `(event, row_label, position_number)` |
| Event days | `backend/app/Models/EventDay.php`, `2026_07_14_000003_create_event_days_table.php` | Operational days; full-event-duration default |
| Allocations | `backend/app/Models/BookingDayAllocation.php`, `BookingAllocationReservationService.php` | Atomic reserve; `active_lock` uniqueness; same-row adjacency |
| Lifecycle | `BookingAllocationLifecycleService.php` | Release on reject/withdraw; confirm on payment verify |
| Vendor availability | `VendorEventSiteAvailabilityService.php`, `VendorEventSiteAvailabilityController.php` | `GET /api/vendor/events/{event}/site-availability`; no category filter |
| Site generation | `EventSiteLayoutGenerator.php`, `EventSiteController.php` | Bulk row-based generation; Organizer-only |
| Booking create | `BookingController::store` | `event_site_ids` + `product_category`; reserves in transaction |
| Governance | `ManagementRole.php`, `ManagementCapability.php` | Carboot ops: organizer, super_admin only |
| Audit | `BookingAuditLogger.php`, `booking_audit_logs` | Booking status transitions |

### Current category strings (repository-verified)

Hardcoded in `BookingController`, `VendorProfileController`, `VendorBusinessProfileController`, `VendorItemController`, and `frontend/src/constants/productCategories.js`:

1. `Pre-loved / Thrift`
2. `Food & Beverages`
3. `Clothing & Apparel`
4. `Handicrafts & Art`
5. `Electronics & Gadgets`
6. `Others`

No separate `vendor` database role exists; community users become vendors via `vendor_business_profiles`.

### Test environment risk (Phase 3.1)

`backend/phpunit.xml` sets `APP_ENV=testing` but does **not** override `DB_CONNECTION` or `DB_DATABASE`. Tests run against persistent MySQL (`cmart_db`). Phase 3.3 must establish isolated test database before schema work.

---

## Problem Statement

Organizers cannot assign categories to layout rows, vendors cannot see category-compatible sites, the backend does not reject category-incompatible site selections, there is no layout readiness gate, no layout locking model, no Organizer reassignment override contract, and no public-safe layout projection. The denormalized `event_sites.row_label` is insufficient for category enforcement, row lifecycle, and public navigation.

**Primary question:** How should the Phase 2 foundation be extended to support one-category-per-row layout planning, booking-category eligibility, Organizer reassignment override, layout readiness, layout locking, and a simplified public layout without breaking booking, payment, withdrawal, multi-day, multi-site, or governance behaviour?

---

## Locked Product Decisions

These are canonical and must not be reopened without strong repository evidence:

1. **Row** is the canonical MVP layout grouping (`event_layout_rows` / `EventLayoutRow`). No competing Zone database abstraction.
2. **One category per row** for MVP. No many-to-many row-category pivot.
3. **Booking category** is the operational enforcement source, not vendor profile category.
4. **Vendor cannot submit incompatible sites** — backend rejects manipulated requests.
5. **Organizer-only reassignment override** with mandatory reason and audit. No silent bypass.
6. **Public layout** is navigation-only. No live occupancy, vendor identity, or booking state.
7. **Layout readiness gate** before vendor bookability.
8. **Structured table/grid MVP** — no drag-and-drop.
9. **Reuse Phase 2 allocation engine** — do not redesign reservation/lifecycle.
10. **CMart Management** has no Carboot layout authority.

---

## Architecture Alternatives

### Alternative 1 — Keep `row_label` only

| Aspect | Assessment |
|--------|------------|
| Advantages | No new table; minimal migration |
| Disadvantages | Category on site or inferred from label; row rename breaks uniqueness semantics; no row-level locking; poor public projection |
| Repository fit | Current state only; `EventSiteLayoutGenerator` already treats `row_label` as generation input, not a managed entity |
| Migration impact | Low |
| Operational impact | Organizer cannot assign category per row; label edits are destructive |
| **Decision** | **Reject** |

### Alternative 2 — First-class Event Layout Row

| Aspect | Assessment |
|--------|------------|
| Advantages | FK integrity; one category per row; readiness/locking at row level; clean public projection; backfill from `event_id + row_label` |
| Disadvantages | Migration + backfill; generator refactor |
| Repository fit | Aligns with `EventSiteLayoutGenerator` row definitions and `EventSite::orderedForLayout()` |
| Migration impact | Moderate; additive |
| Operational impact | Matches Organizer workflow in product brief |
| **Decision** | **Accept (recommended)** |

### Alternative 3 — Generic Zone with multiple categories

| Aspect | Assessment |
|--------|------------|
| Advantages | Future flexibility |
| Disadvantages | M2M pivot; eligibility rules; exceeds MVP |
| Repository fit | No existing zone model |
| **Decision** | **Defer / reject for MVP** |

### Alternative 4 — Category on each site

| Aspect | Assessment |
|--------|------------|
| Advantages | Simple per-site queries |
| Disadvantages | Duplication; row category drift; Organizer burden |
| **Decision** | **Reject** — site inherits category from row exclusively |

### Alternative 5 — Vendor profile category as enforcement source

| Aspect | Assessment |
|--------|------------|
| Advantages | Pre-fills vendor form |
| Disadvantages | Event-specific selling category differs from profile; historical inconsistency on profile change |
| **Decision** | **Reject as sole source** — profile is default/suggestion only |

### Alternative 6 — Booking category snapshot as enforcement source

| Aspect | Assessment |
|--------|------------|
| Advantages | Event-specific; frozen at submission; audit-friendly |
| Disadvantages | Requires taxonomy FK + snapshot fields |
| **Decision** | **Accept (recommended)** |

---

## Decision Matrix

Scores 1–5 (higher = better). Weighted total out of 5.

| Criterion | Weight | Alt 1 row_label | Alt 2 LayoutRow | Alt 3 Zone M2M | Alt 4 Site cat | Alt 5 Profile | Alt 6 Booking snap |
|-----------|-------:|----------------:|----------------:|---------------:|---------------:|--------------:|-------------------:|
| Requirement fit | 20% | 2 | 5 | 4 | 3 | 2 | 5 |
| MVP simplicity | 15% | 4 | 4 | 2 | 3 | 4 | 4 |
| Data integrity | 15% | 2 | 5 | 4 | 2 | 2 | 5 |
| Repo compatibility | 10% | 4 | 5 | 2 | 3 | 3 | 4 |
| Migration safety | 10% | 5 | 4 | 3 | 4 | 5 | 4 |
| Organizer usability | 10% | 2 | 5 | 4 | 3 | 3 | 4 |
| Vendor usability | 10% | 2 | 5 | 4 | 3 | 2 | 5 |
| Public layout | 5% | 2 | 5 | 4 | 3 | 2 | 4 |
| Extensibility | 5% | 2 | 4 | 5 | 3 | 2 | 4 |
| Testability | 5% | 3 | 5 | 3 | 3 | 3 | 5 |
| **Weighted total** | | **2.75** | **4.80** | **3.45** | **2.95** | **2.85** | **4.55** |

**Selected architecture:** Alternative 2 (Event Layout Row) + Alternative 6 (Booking category snapshot). Combined weighted fit: **4.80** domain model + **4.55** enforcement model.

---

## Final Architecture

### Domain hierarchy

```text
CarbootEvent
├── EventDays
├── EventLayoutRows
│   ├── VendorCategory (one per row)
│   └── EventSites
│       └── BookingDayAllocations (unchanged Phase 2 engine)
├── Bookings
│   ├── vendor_category_id + category_label_snapshot
│   └── BookingSiteReassignmentOverrides (Organizer exceptions)
├── Public Layout Projection (read-only, publication-gated)
└── EventLayoutAuditLogs (layout mutations, separate from booking audit)
```

### Services (new or extended)

| Service | Responsibility |
|---------|----------------|
| `EventLayoutReadinessService` | Computed readiness; blocking reasons |
| `EventLayoutLockService` | Row/site operation permissions |
| `EventLayoutRowService` | Row CRUD, ordering, archive |
| `BookingCategoryEligibilityService` | Category vs row validation before reserve |
| `OrganizerBookingReassignmentService` | Override + atomic allocation switch |
| `PublicEventLayoutPresenter` | Public-safe projection |
| `EventSiteLayoutGenerator` | **Extended** — creates/links `EventLayoutRow` records |
| `VendorEventSiteAvailabilityService` | **Extended** — category-aware states |
| `BookingAllocationReservationService` | **Extended** — calls eligibility; engine unchanged |

---

## Domain Model — Data Contracts

### A. Canonical Category Taxonomy

**Table:** `vendor_categories`  
**Model:** `VendorCategory`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | Stable reference |
| `slug` | string(64) unique | API/programmatic key (e.g. `pre-loved-thrift`) |
| `label` | string(128) unique | Display label (matches legacy string exactly for seed) |
| `description` | text nullable | Public/Organizer description |
| `display_order` | unsigned int | Sort order |
| `is_active` | boolean default true | Inactive = not selectable for new rows/bookings |
| `is_public` | boolean default true | Hide from public layout when false |
| `created_at`, `updated_at` | timestamps | |
| `archived_at` | timestamp nullable | Soft archival; never hard-delete if referenced |

**Initial seed (from repository):**

| slug | label |
|------|-------|
| `pre-loved-thrift` | Pre-loved / Thrift |
| `food-beverages` | Food & Beverages |
| `clothing-apparel` | Clothing & Apparel |
| `handicrafts-art` | Handicrafts & Art |
| `electronics-gadgets` | Electronics & Gadgets |
| `others` | Others |

**Relationships:**

- `VendorBusinessProfile.vendor_category_id` — optional FK; suggestion/default only
- `Booking.vendor_category_id` — operational category FK
- `EventLayoutRow.vendor_category_id` — row category FK

**Historical strategy:** Bookings store `category_label_snapshot` at submit/resubmit-with-sites. Profile changes do not mutate existing bookings.

**Legacy string fields (transitional):**

| Field | Transitional role | Future source | Removal condition |
|-------|-------------------|---------------|-------------------|
| `bookings.product_category` | Mirror on write | `category_label_snapshot` | All APIs/tests use FK + snapshot |
| `vendor_business_profiles.business_category` | Mirror on write | `vendor_category_id` | Profile UI migrated |
| `user_booking_preferences.product_category` | Mirror | `vendor_category_id` | Preferences UI migrated |
| `vendor_items.category` | Mirror | `vendor_category_id` | Item UI migrated |

---

### B. Event Layout Row Contract

**Table:** `event_layout_rows`  
**Model:** `EventLayoutRow`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | bigint PK | |
| `carboot_event_id` | FK → carboot_events cascade | Parent event |
| `vendor_category_id` | FK → vendor_categories restrict | One category per row (required when active) |
| `label` | string(32) | Display label (e.g. `Row A`) |
| `slug` | string(64) | Internal key unique per event |
| `description` | text nullable | Organizer/public row note |
| `display_order` | unsigned int | Presentation order |
| `is_active` | boolean default true | Inactive rows excluded from booking |
| `is_public` | boolean default true | Hidden from public layout when false |
| `created_by`, `updated_by` | FK → users nullable | Audit |
| `archived_at` | timestamp nullable | Soft archive |
| `created_at`, `updated_at` | timestamps | |

**Constraints:**

- Unique `(carboot_event_id, label)`
- Unique `(carboot_event_id, slug)`
- Active rows must have non-null `vendor_category_id` (enforced at readiness + write validation)

**`event_sites.row_label` transitional behaviour:**

- Remain populated on all writes as **compatibility mirror** of `EventLayoutRow.label`
- Backfill: distinct `(carboot_event_id, row_label)` → `event_layout_rows`
- Retire as write source in Phase 3.11 after verification; column retained for historical reports

**Deletion:** Hard delete blocked when row has allocation history; use `archived_at` + `is_active=false`.

**Category deactivated:** Row fails readiness; existing bookings retain snapshot; new bookings cannot select inactive category.

---

### C. Event Site Contract Changes

**Additive column:** `event_sites.event_layout_row_id` FK → `event_layout_rows` nullable initially, then NOT NULL after backfill.

| Rule | Detail |
|------|--------|
| Category inheritance | Site eligibility category = `event_layout_row.vendor_category_id` exclusively |
| No `category_id` on site | Rejected for MVP |
| Generator | `EventSiteLayoutGenerator` creates/finds row by `row_label`/slug, sets `event_layout_row_id`, mirrors `row_label` |
| Uniqueness | Existing `(event, label)` and `(event, row_label, position_number)` preserved |
| Nullable period | Sites without row fail readiness; excluded from vendor selection |

---

### D. Booking Category Snapshot Contract

**Additive columns on `bookings`:**

- `vendor_category_id` FK → `vendor_categories` restrict
- `category_label_snapshot` string(128) — copy of `vendor_categories.label` at freeze point

**Mutation matrix:**

| Booking State | Vendor Can Change Category? | Site Reselection Required? | Organizer Action Required? |
|---------------|----------------------------:|---------------------------:|---------------------------:|
| Draft / not submitted (client-only) | Yes | Yes if category changes | No |
| Pending Organizer | No | No | No (reject or request revision) |
| Needs Revision | Yes (via `vendorUpdate` + future site reselect endpoint) | Yes — category change clears incompatible reserved sites | No until resubmit |
| Approved | No | No | Reassignment override only |
| Payment Submitted | No | No | Reassignment override only |
| Paid / Confirmed | No | No | Reassignment override + extra confirmation |
| Rejected | No (new booking required) | N/A | No |
| Withdrawn | No (new booking required) | N/A | No |
| Cancelled | No | N/A | No |

**Freeze point:** `vendor_category_id` + `category_label_snapshot` set at successful `store` (initial submit). Updated only when vendor explicitly changes category **before** reservation on Needs_Revision resubmit-with-sites flow (Phase 3.7).

**Current `resubmit`:** Does not allow `event_site_ids` (repository-verified). Phase 3.7 adds `resubmitWithSites` or extends resubmit when revision requires new sites.

**`product_category`:** Written in parallel with snapshot during transition.

---

### E. Category Eligibility Contract

**Formula (per site, before reserve):**

```text
booking.vendor_category_id == event_site.event_layout_row.vendor_category_id
AND event_site.carboot_event_id == booking.carboot_event_id
AND event_site.operational_status == 'active'
AND event_layout_row.is_active == true
AND event_layout_row.vendor_category_id IS NOT NULL
AND vendor_category.is_active == true
AND existing Phase 2 rules (space type, adjacency, all active days available)
```

**Execution order (inside `DB::transaction`):**

1. Lock event (`lockForUpdate`)
2. `EventLayoutReadinessService::assertBookable($event)` — fail if not `ready`
3. Validate `vendor_category_id` active
4. Load sites with rows + categories
5. Per-site eligibility check
6. `BookingAllocationReservationService::reserveForBookingInExistingTransaction` (unchanged adjacency/day logic)
7. Write booking + snapshot

**Multi-site atomicity:** Any single ineligible or unavailable site fails entire request (existing Phase 2 behaviour).

**HTTP mapping (reconcile with `AllocationValidationException` 422, `DomainConflictException` 409):**

| Condition | HTTP | error code |
|-----------|------|------------|
| Unknown/inactive category | 422 | `booking_category_inactive` |
| Layout not ready | 422 | `event_layout_not_ready` |
| Site without row | 422 | `site_row_required` |
| Category mismatch | 422 | `site_category_mismatch` |
| Site occupied / stale | 409 | `site_already_reserved` |
| No active days | 422 | `no_active_event_days` |
| Auth failure | 403 | — |

---

### F. Vendor Availability Projection

**Endpoint:** `GET /api/vendor/events/{event}/site-availability`

**Query parameters (Phase 3.7):**

- `category_id` (required when layout has rows with categories)
- `booking_id` (optional — marks current vendor's reserved sites as `selected_by_current_vendor`)
- `space_id` (optional — filter by space type, existing rule)

**Site `availability_status` values:**

| Status | Returned | Selectable | Vendor message |
|--------|----------|------------|----------------|
| `available_eligible` | Yes | Yes | — |
| `available_incompatible` | Yes | No | Category does not match your booking category |
| `selected_by_current_vendor` | Yes | Yes | Your current selection |
| `occupied` | Yes | No | Already reserved |
| `disabled` | Yes | No | Site unavailable |
| `inactive` | Hidden or grouped | No | — |
| `unavailable_for_required_days` | Yes | No | Not available for all event days |

**Row grouping in response:**

```json
{
  "event": { },
  "operational_days": [ ],
  "booking_category": { "id": 1, "label": "Food & Beverages" },
  "rows": [
    {
      "id": 12,
      "label": "Row B",
      "category": { "id": 2, "label": "Food & Beverages" },
      "is_compatible": true,
      "sites": [ ]
    }
  ],
  "selection_rules": { },
  "layout_readiness": { "status": "ready", "is_ready": true }
}
```

---

### G. Layout Readiness Contract

**Service:** `EventLayoutReadinessService`

**Computed states:**

| Status | `is_ready` | Meaning |
|--------|------------|---------|
| `not_configured` | false | No days and no rows |
| `incomplete` | false | One or more blocking checks fail |
| `ready` | true | All checks pass |
| `locked` | true | Ready + event has confirmed/paid allocation history affecting layout policy (informational) |

**Blocking checks:**

1. `NO_ACTIVE_EVENT_DAYS` — at least one active `event_days` row
2. `NO_ACTIVE_LAYOUT_ROWS` — at least one active `event_layout_rows` row
3. `ROW_WITHOUT_CATEGORY` — each active row has `vendor_category_id`
4. `NO_ACTIVE_EVENT_SITES` — at least one active site
5. `SITE_WITHOUT_ROW` — each active site has `event_layout_row_id`
6. `DUPLICATE_SITE_LABEL` — structural validation
7. `INACTIVE_CATEGORY_ON_ROW` — row category deactivated

**Exposure:**

- `GET /api/organizer/events/{event}/layout-readiness` — full blocking list
- Vendor availability — structured error or empty with `layout_readiness` when not ready
- `BookingController::store` — reject with `event_layout_not_ready`
- Public layout — 404 or `{ "published": false }` when not ready/unpublished

**Publication vs readiness:**

- Event may be **publicly listed** while layout incomplete (marketing visibility)
- Event is **not vendor-bookable** until `ready`
- `carboot_events.status` active/closed rules unchanged; readiness is additional gate

---

### H. Layout Publication Contract

**Event-level fields (additive on `carboot_events`):**

- `public_layout_published_at` timestamp nullable
- `public_layout_entrance_note` text nullable

**Rules:**

| Actor | Publish | Unpublish |
|-------|---------|-----------|
| Organizer | Yes | Yes |
| CMart Management | No | No |
| Super Admin | Yes (technical) | Yes |

- Publication requires `layout_readiness.is_ready === true`
- Unpublish does not affect vendor booking of eligible sites
- Cancelled/closed events: public layout returns `{ "status": "unavailable" }` regardless of publish flag
- Row-level `is_public=false`: row omitted from public projection; **vendor booking still allowed** if operational

---

### I. Layout Locking Contract

**Lock levels:**

| Level | Trigger |
|-------|---------|
| `editable` | No allocation history on row/site |
| `partially_locked` | Active `reserved` allocations on row/site |
| `history_locked` | Any confirmed/released allocation history |

**Operation matrix:**

| Operation | No History | Active Reservation | Confirmed/Paid History |
|-----------|:----------:|:------------------:|:----------------------:|
| Add row | Allowed | Allowed | Allowed |
| Rename unused row | Allowed | Allowed | Allowed with warning (label snapshot on bookings uses site label not row label) |
| Rename used row | Allowed | Allowed with warning | Allowed with warning |
| Change row category | Allowed | **Blocked** | **Blocked** |
| Reorder row | Allowed | Allowed | Allowed |
| Archive row | Allowed if empty | **Blocked** | Archive only (`is_active=false`) |
| Delete row | Allowed if empty | **Blocked** | **Blocked** |
| Add site | Allowed | Allowed | Allowed |
| Rename site label | Allowed | **Blocked** if allocated | **Blocked** |
| Move site to another row | Allowed | **Blocked** | **Blocked** |
| Deactivate site | Allowed | Allowed if not allocated | Archive only |
| Delete site | Allowed if empty | **Blocked** | **Blocked** |
| Regenerate row sites | Allowed | **Blocked** | **Blocked** |
| Regenerate full layout | Allowed | **Blocked** | **Blocked** |

**Enforcement:** `EventLayoutLockService` called from Organizer layout controllers before mutating writes.

---

### J. Organizer Reassignment Override Contract

**Table:** `booking_site_reassignment_overrides`

| Column | Purpose |
|--------|---------|
| `id` | PK |
| `booking_id` | FK |
| `organizer_user_id` | Actor |
| `vendor_category_id` | Booking category at time of override |
| `target_vendor_category_id` | Row category of new site(s) |
| `previous_event_site_ids` | JSON array |
| `new_event_site_ids` | JSON array |
| `override_reason` | text required |
| `previous_approval_status` | snapshot |
| `payment_status_snapshot` | snapshot |
| `created_at` | |

**Flow:**

```text
Organizer POST .../site-reassignment
→ detect category mismatch (booking.category != row.category)
→ require override_reason (min 10 chars)
→ if paid: require confirm_paid_reassignment=true
→ DB::transaction:
    release old allocations (lifecycle service)
    reserve new sites (reservation service, skip category check with override flag)
    write override record
    write booking_audit_logs + layout audit reference
```

**Vendor visibility:** Sanitized message: "Your site assignment was updated by the Organizer." No override reason.

**Public:** No indication.

**Reversal:** New forward reassignment only; no silent undo.

---

### K. Public Layout Projection Contract

**Endpoint:** `GET /api/events/{event}/layout` (no auth)

**Controller:** `PublicEventLayoutController` or `CarbootEventController::publicLayout` using `PublicEventLayoutPresenter`

**Response (when published + ready):**

```json
{
  "event": { "id": 1, "title": "...", "status": "Active" },
  "published": true,
  "entrance_note": "Main gate, left of food court",
  "rows": [
    {
      "label": "Row A",
      "category_label": "Pre-loved / Thrift",
      "category_description": "...",
      "display_order": 1,
      "site_label_range": "A1–A12",
      "site_count": 12
    }
  ]
}
```

**Excluded:** booking IDs, vendor data, occupancy, overrides, audit, invoices, notes.

**Empty behaviours:**

| Condition | HTTP | Body |
|-----------|------|------|
| Layout incomplete | 404 | `{ "message": "Layout not available" }` |
| Unpublished | 404 | Same (no leak) |
| Cancelled event | 404 | Same |
| Ended event | 200 optional | May show static layout without booking CTA |

---

### L. Organizer API Contract

| Method | Route | Auth | Role | Request | Response | Main validation | Audit |
|--------|-------|------|------|---------|----------|-----------------|-------|
| GET | `/api/organizer/categories` | Sanctum | organizer, super_admin | — | `{ categories: [...] }` | — | — |
| GET | `/api/organizer/events/{event}/layout` | Sanctum | organizer, super_admin | — | rows, sites, lock state | event access | — |
| GET | `/api/organizer/events/{event}/layout-readiness` | Sanctum | organizer, super_admin | — | readiness DTO | — | — |
| POST | `/api/organizer/events/{event}/layout-rows` | Sanctum | organizer, super_admin | label, category_id, description | row | unique label, active category | `event_layout_audit_logs` |
| PATCH | `/api/organizer/layout-rows/{row}` | Sanctum | organizer, super_admin | label, category_id, order, is_active, is_public | row | lock service | audit |
| DELETE | `/api/organizer/layout-rows/{row}` | Sanctum | organizer, super_admin | — | 204 | no history | audit |
| POST | `/api/organizer/layout-rows/{row}/sites/generate` | Sanctum | organizer, super_admin | space_id, count, positions | sites | lock service | audit |
| POST | `/api/organizer/events/{event}/layout/publish` | Sanctum | organizer, super_admin | entrance_note? | publish state | readiness ready | publication audit |
| POST | `/api/organizer/events/{event}/layout/unpublish` | Sanctum | organizer, super_admin | — | publish state | — | audit |
| POST | `/api/organizer/bookings/{booking}/site-reassignment` | Sanctum | organizer, super_admin | event_site_ids, override_reason, confirm_paid? | booking | override contract | booking + override audit |

**Extended existing routes:**

- `POST /api/organizer/events/{event}/sites/generate` — delegate to row-aware generator
- `GET /api/vendor/events/{event}/site-availability?category_id=` — category projection

---

### M. Frontend Data Contract (DTOs only)

**Organizer layout workspace:**

- `LayoutReadinessDTO`, `LayoutRowDTO`, `LayoutSiteSummaryDTO`, `RowLockStateDTO`
- Table columns: label, category, site count, display order, active, public, lock badge, actions

**Vendor booking:**

- `BookingCategoryOptionDTO`, `LayoutRowAvailabilityDTO`, `SiteAvailabilityDTO` with `availability_status` + `disabled_reason`

**Public layout:**

- `PublicLayoutDTO`, `PublicLayoutRowDTO` — no occupancy fields

---

## Authorization Contract

| Capability | Organizer | CMart Mgmt | Community Vendor | Public | Super Admin |
|------------|:---------:|:----------:|:----------------:|:------:|:-----------:|
| View internal layout | Yes | No | No | No | Yes |
| View readiness status | Yes | No | No | No | Yes |
| Create layout row | Yes | No | No | No | Yes |
| Edit unused row | Yes | No | No | No | Yes |
| Edit locked row | Partial (rename/order only) | No | No | No | Yes |
| Assign row category | Yes (if unlocked) | No | No | No | Yes |
| Generate row sites | Yes | No | No | No | Yes |
| View vendor-safe availability | No | No | Yes (own session) | No | Yes |
| Submit eligible site booking | No | No | Yes | No | No |
| Submit incompatible site booking | No | No | No | No | No |
| Reassign booking site | Yes | No | No | No | Yes |
| Override category mismatch | Yes (with reason) | No | No | No | Yes |
| View internal override reason | Yes | No | No | No | Yes |
| View public layout | Yes | Yes (public endpoint) | Yes | Yes | Yes |
| Publish public layout | Yes | No | No | No | Yes |

---

## Error Contract

| Code | HTTP | Trigger | Vendor message | Organizer message | Public |
|------|------|---------|----------------|-------------------|--------|
| `EVENT_LAYOUT_NOT_READY` | 422 | Book when layout incomplete | Event not ready for booking | Layout incomplete — see readiness | Layout not available |
| `EVENT_HAS_NO_ACTIVE_DAYS` | 422 | No days | Schedule not configured | Add operational days | — |
| `EVENT_HAS_NO_ACTIVE_ROWS` | 422 | No rows | — | Add layout rows | — |
| `LAYOUT_ROW_CATEGORY_REQUIRED` | 422 | Row missing category | — | Assign category to row | — |
| `LAYOUT_ROW_LOCKED` | 422 | Mutate locked row | — | Row locked — active reservations | — |
| `LAYOUT_ROW_HAS_ACTIVE_ALLOCATIONS` | 422 | Delete/archive | — | Release or reassign first | — |
| `SITE_ROW_REQUIRED` | 422 | Site without row | — | Link site to row | — |
| `SITE_CATEGORY_MISMATCH` | 422 | Ineligible site | Selected site does not match your category | — | — |
| `BOOKING_CATEGORY_INACTIVE` | 422 | Bad category_id | Category unavailable | — | — |
| `BOOKING_CATEGORY_CHANGE_REQUIRES_SITE_RESELECTION` | 422 | Category changed with reserved sites | Reselect sites for new category | — | — |
| `SITE_ALREADY_RESERVED` | 409 | Occupancy conflict | Site no longer available | — | — |
| `SITE_NOT_AVAILABLE_FOR_ALL_EVENT_DAYS` | 422 | Day coverage | Site unavailable for full event | — | — |
| `ORGANIZER_OVERRIDE_REASON_REQUIRED` | 422 | Missing reason | — | Override reason required | — |
| `ORGANIZER_OVERRIDE_NOT_ALLOWED` | 422 | Invalid state | — | Reassignment not permitted | — |
| `PUBLIC_LAYOUT_NOT_PUBLISHED` | 404 | Public request | — | — | Not found |

---

## Audit and History Contract

| Domain | Store | Events |
|--------|-------|--------|
| Booking audit | `booking_audit_logs` (existing) | Status changes, vendor submit, organizer approve/reject/revision, reassignment side-effects |
| Layout audit | `event_layout_audit_logs` (new) | Row CRUD, category change, site generate/move, archive |
| Publication history | `event_layout_publication_logs` (new) or layout audit with `action=publish` | Publish/unpublish |

Do not overload `booking_audit_logs` with row rename or site generation.

---

## Migration Strategy

**Order (no implementation in Phase 3.2):**

1. Create `vendor_categories` + seed six labels
2. Add nullable `vendor_category_id` to `vendor_business_profiles`, `bookings`
3. Backfill booking/profile IDs from `product_category` / `business_category` strings
4. Add `category_label_snapshot` to bookings; backfill from string
5. Create `event_layout_rows`
6. Add nullable `event_layout_row_id` to `event_sites`
7. Backfill rows from distinct `(carboot_event_id, row_label)`
8. Link sites to rows; mirror `row_label` from row.label
9. Add `event_layout_audit_logs`, override table, publication fields
10. Add indexes; NOT NULL constraints after verification
11. Compatibility presenters dual-read strings
12. Switch write paths to FK + snapshot
13. Switch read paths
14. Deprecate string-only writes after regression

**Verification queries:** count sites without row; count bookings with unmapped category; duplicate row labels per event.

**Rollback:** Drop constraints and nullable FKs; keep string columns until Phase 3.11.

**Empty DB:** Seeders create categories + demo event layout in Phase 3.11.

---

## Testing Strategy

### Phase 3.3 prerequisite (mandatory before 3.4)

- Dedicated `cmart_test` database (or sqlite only if constraints replicated — **MySQL test DB recommended**)
- `phpunit.xml` sets `DB_DATABASE=cmart_test`
- `TestingEnvironmentGuard` aborts if `APP_ENV=testing` and database name equals production/dev
- Documented `php artisan migrate --env=testing` workflow
- CI uses isolated DB

### Per-phase tests (summary)

| Phase | Backend | Frontend unit | E2E |
|-------|---------|---------------|-----|
| 3.4 | Migration + model tests | — | — |
| 3.5 | Readiness, lock, row CRUD | — | — |
| 3.6 | — | layout DTO helpers | — |
| 3.7 | Eligibility, availability | eventSiteSelection category | vendor booking |
| 3.8 | Override, reassignment | — | organizer reassignment |
| 3.9 | — | EventSiteSelector states | vendor.site-selection |
| 3.10 | Public layout | — | public layout smoke |
| 3.11 | Full regression | full unit | E2E suite |

---

## Risks

| Risk | Severity | Likelihood | Mitigation | Phase |
|------|----------|------------|------------|-------|
| Tests mutate dev DB | High | High | Phase 3.3 isolation | 3.3 |
| Invalid legacy category strings | Medium | Low | Seed mapping + unknown bucket to Others | 3.4 |
| Sites without row after backfill | Medium | Medium | Verification gate before NOT NULL | 3.4 |
| Category change during booking TX | High | Low | Event lock + readiness assert first | 3.7 |
| CMart route leakage | High | Low | ManagementCapability on new routes | 3.5 |
| Public occupancy leak | High | Low | Dedicated presenter allowlist | 3.10 |
| Vendor category after site select | Medium | Medium | Frontend reset + backend validate | 3.9 |

---

## Consequences

### Positive

- Clear Organizer layout workflow aligned with physical allocation
- Vendors book compatible sites only
- Public visitors get navigation map without privacy leak
- Historical bookings preserve category at submission

### Negative

- Migration complexity across six legacy string fields
- Organizer must complete layout before bookings
- Additional services to maintain

---

## Deferred Work

- Multiple categories per row (M2M pivot)
- Zone abstraction separate from Row
- Drag-and-drop layout editor
- Per-site category overrides
- Live public occupancy map
- Check-in / event pass (Phase 4+)
- Hierarchical category taxonomy

---

## Rejected Approaches

- `row_label`-only layout (no first-class row)
- Zone with multiple categories (MVP)
- Per-site `category_id` for eligibility
- Vendor profile as enforcement source
- Vendor-submitted incompatible sites pending approval
- Silent Organizer override
- CMart Management layout configuration

---

## Implementation Roadmap

### Phase 3.3 — Test Environment Isolation and Baseline Safety

**Objective:** Safe migration testing.  
**Scope:** `phpunit.xml`, `.env.testing`, guard trait, docs.  
**Dependencies:** None.  
**Stop condition:** Tests never write to `cmart_db`.  
**DoD:** CI + local documented; guard test passes.

### Phase 3.4 — Canonical Category and Layout Schema

**Objective:** Tables, FKs, backfill, seeders.  
**Dependencies:** 3.3.  
**Files:** migrations, `VendorCategory`, `EventLayoutRow`, models.  
**Exclusions:** No API/UI.

### Phase 3.5 — Organizer Layout Backend and Readiness

**Objective:** Row CRUD, readiness, lock services, row site generation.  
**Dependencies:** 3.4.  
**Files:** `EventLayoutRowController`, services, routes.  
**Tests:** Feature tests for readiness/lock.

### Phase 3.6 — Organizer Layout Management UI

**Objective:** Staff/Organizer table workspace.  
**Dependencies:** 3.5.  
**Files:** new Organizer layout view/panel.  
**Exclusions:** Drag-and-drop.

### Phase 3.7 — Vendor Category Eligibility Enforcement

**Objective:** Availability projection + booking validation.  
**Dependencies:** 3.5.  
**Files:** `BookingCategoryEligibilityService`, `VendorEventSiteAvailabilityService`, `BookingController`.  

### Phase 3.8 — Organizer Reassignment Override and Audit

**Objective:** Override table + atomic reassignment.  
**Dependencies:** 3.7.  

### Phase 3.9 — Vendor Site Selection UX

**Objective:** Category-first UI, disabled states.  
**Dependencies:** 3.7.  
**Files:** `EventSiteSelector.vue`, `Registration.vue`.

### Phase 3.10 — Public Simplified Layout

**Objective:** `GET /api/events/{event}/layout` + public view.  
**Dependencies:** 3.5, 3.6 publication fields.

### Phase 3.11 — Migration, Regression, and E2E Hardening

**Objective:** Seed layouts, retire synthetic booth fallback where safe, full regression.  
**Dependencies:** 3.7–3.10.

---

## References

- Phase 3.1 audit (conversation transcript `7e3c7b40-f236-4497-a083-d21a7871c9e2`)
- `docs/phase-2/phase-2a-architecture-decision-record.md`
- `docs/phase-2/phase-2a7-booking-creation-and-allocation-lifecycle.md`
- `docs/phase-2/phase-2a7-1-test-isolation-and-local-data-cleanup.md`
