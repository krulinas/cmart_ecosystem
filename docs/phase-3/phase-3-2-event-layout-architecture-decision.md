# Phase 3.2 — Event Layout Architecture Decision Record

| Field | Value |
|-------|-------|
| **Title** | Phase 3 — Event Layout & Category-Based Slot Planning |
| **Status** | Accepted — Amended after Phase 3.3 validation |
| **Date** | 2026-07-15 |
| **Amended** | 2026-07-16 (Phase 3.3A) |
| **Depends on** | Phase 2A–2B (physical allocation foundation), Phase 3.1 audit, Phase 3.3 test isolation |
| **Blocks** | Phase 3.4+ (implementation) — only after this ADR’s amendments and skipped-test classification |
| **ADR ID** | ADR-003 |

### Amendment history

| Amendment | Date | Reason |
|-----------|------|--------|
| Phase 3.3A | 2026-07-16 | Correct category migration (no silent Others mapping), canonical taxonomy including Household Items and Mixed / Others, vendor-safe category endpoint, closed/ended public layout, row rename locking, immutable reassignment snapshots, category deactivation impact checks, public publication readiness, Super Admin endpoint semantics, and Phase 3.4 entry gate |

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

### Test environment risk (Phase 3.1 → resolved in Phase 3.3)

Phase 3.1 risk: PHPUnit inherited `cmart_db`. **Phase 3.3 complete:** `phpunit.xml` forces `DB_DATABASE=cmart_test`; `TestingDatabaseGuard` rejects `cmart_db` before mutation. See `docs/phase-3/phase-3-3-test-environment-isolation-and-baseline-safety.md`.

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
11. **Unknown legacy categories** are migration blockers — never silently mapped to `Mixed / Others`.
12. **Row labels are identity fields** — rename is blocked after any allocation history.
13. **Operational readiness ≠ public publication readiness** — separate gates.
14. **Super Admin** uses Organizer internal layout/occupancy endpoints — never vendor-session availability semantics.

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
| `label` | string(128) unique | Canonical display label |
| `description` | text nullable | Public/Organizer description |
| `display_order` | unsigned int | Sort order |
| `is_active` | boolean default true | Inactive = not selectable for **new** rows/bookings/profile defaults |
| `is_public` | boolean default true | Hide from public layout when false |
| `created_at`, `updated_at` | timestamps | |
| `archived_at` | timestamp nullable | Soft archival; never hard-delete if referenced |

Both `is_active` and `archived_at` are required concepts: `is_active=false` blocks new selection; `archived_at` marks permanent retirement while remaining queryable for Organizer history.

#### Canonical Phase 3 MVP taxonomy (selectable)

Legacy repository strings are **migration inputs**, not automatically the complete Phase 3 taxonomy.

| Canonical Slug | Canonical Label | Source | Active for MVP |
|----------------|-----------------|--------|---------------:|
| `pre-loved-thrift` | Pre-loved / Thrift | Legacy exact | Yes |
| `food-beverages` | Food & Beverages | Legacy exact | Yes |
| `clothing-apparel` | Clothing & Apparel | Legacy exact | Yes |
| `handicrafts-art` | Handicrafts & Art | Legacy exact | Yes |
| `electronics-gadgets` | Electronics & Gadgets | Legacy exact | Yes |
| `household-items` | Household Items | Stakeholder layout example (new) | Yes |
| `mixed-others` | Mixed / Others | Replaces legacy `Others` | Yes |

Do **not** merge `Pre-loved / Thrift` with `Clothing & Apparel` without explicit stakeholder evidence.

#### Legacy persisted labels → approved migration mapping

| Legacy / input value (exact after normalization) | Maps to slug | Maps to label |
|--------------------------------------------------|--------------|---------------|
| `Pre-loved / Thrift` | `pre-loved-thrift` | Pre-loved / Thrift |
| `Food & Beverages` | `food-beverages` | Food & Beverages |
| `Clothing & Apparel` | `clothing-apparel` | Clothing & Apparel |
| `Handicrafts & Art` | `handicrafts-art` | Handicrafts & Art |
| `Electronics & Gadgets` | `electronics-gadgets` | Electronics & Gadgets |
| `Others` | `mixed-others` | Mixed / Others |

#### Approved aliases (migration only — not separate DB categories)

| Alias (after normalization) | Maps to | Justification |
|-----------------------------|---------|---------------|
| *(none approved beyond exact legacy table above)* | — | Stakeholder wording such as `Food & Drinks`, `Preloved Clothes` remains **UI copy / documentation examples only** until explicit data or stakeholder approval adds them as aliases |

#### Stakeholder-friendly wording (not database categories)

| Wording | Treatment |
|---------|-----------|
| Food & Drinks | Documentation / UI copy variant of Food & Beverages — **not** a seed row |
| Preloved Clothes | Documentation / UI copy variant of Pre-loved / Thrift — **not** a seed row |
| Mixed / Others | **Is** the canonical label for `mixed-others` |
| Household Items | **Is** the canonical label for `household-items` |

#### Normalization rules (before exact match)

1. Trim leading/trailing whitespace.
2. Collapse internal runs of whitespace to a single space.
3. Do **not** case-fold for matching (labels are case-sensitive after trim).
4. Do **not** strip or normalize punctuation (`/`, `&`, `-`) beyond whitespace collapse.
5. Do **not** apply fuzzy / Levenshtein / synonym matching.

#### Unknown, malformed, ambiguous, or unsupported values

```text
Known exact value or explicitly approved alias
→ map to canonical category_id

Unknown, malformed, ambiguous, or unsupported value
→ write to migration audit table/log
→ leave vendor_category_id NULL (unresolved)
→ block final NOT NULL / FK enforcement
→ require explicit Organizer/ops resolution before Phase 3.4 constraint closure
```

**Unknown values auto-map to Mixed / Others: No**

#### Migration audit & constraint stop condition

- Persist unresolved rows: source table, PK, original string, normalized string, reason (`unknown` / `ambiguous`).
- Verification gate: `COUNT(*) WHERE vendor_category_id IS NULL AND product_category IS NOT NULL` (and profile equivalents) must be **0** before adding NOT NULL.
- Rerun backfill: idempotent — only fills NULL FKs from known map; never overwrites resolved IDs; re-lists remaining unknowns.
- Rollback: drop FKs/constraints; retain string columns; audit log retained for review.
- Nullable transitional period: required until unresolved = 0.

**Relationships:**

- `VendorBusinessProfile.vendor_category_id` — optional FK; suggestion/default only
- `Booking.vendor_category_id` — operational category FK
- `EventLayoutRow.vendor_category_id` — row category FK

**Historical strategy:** Bookings store `category_label_snapshot` at submit/resubmit-with-sites. Profile changes do not mutate existing bookings. Archived categories remain queryable for Organizer history.

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

**Category deactivated (global):** See §A category deactivation impact checks. Deactivating a category used by active/future layout rows is **blocked** until rows are remapped or events closed/archived. Historical bookings retain `vendor_category_id` (where FK still valid) and `category_label_snapshot`.

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

**Services:**

| Service | Purpose |
|---------|---------|
| `EventLayoutReadinessService` | Operational bookability (vendor booking gate) |
| `EventPublicLayoutReadinessService` | Public publication gate (may extend readiness service) |

#### Operational readiness

**Computed states:**

| Status | `is_ready` | Meaning |
|--------|------------|---------|
| `not_configured` | false | No days and no rows |
| `incomplete` | false | One or more blocking checks fail |
| `ready` | true | All operational checks pass |
| `locked` | true | Ready + event has confirmed/paid allocation history affecting layout policy (informational) |

**Operational blocking checks:**

1. `NO_ACTIVE_EVENT_DAYS` — at least one active `event_days` row
2. `NO_ACTIVE_LAYOUT_ROWS` — at least one active `event_layout_rows` row
3. `ROW_WITHOUT_CATEGORY` — each active row has `vendor_category_id`
4. `NO_ACTIVE_EVENT_SITES` — at least one active site
5. `SITE_WITHOUT_ROW` — each active site has `event_layout_row_id`
6. `DUPLICATE_SITE_LABEL` — structural validation
7. `INACTIVE_CATEGORY_ON_ROW` — active row references inactive/archived category

**Operational exposure:**

- `GET /api/organizer/events/{event}/layout-readiness` — full blocking list
- Vendor availability — structured error or empty with `layout_readiness` when not ready
- `BookingController::store` — reject with `event_layout_not_ready`

#### Public publication readiness (separate)

Publication requires operational readiness **plus**:

1. `NO_PUBLIC_ROWS` — at least one active row with `is_public=true`
2. `NO_PUBLIC_SITES` — at least one active public-visible site in a public row
3. `NO_PUBLIC_CATEGORY` — that row’s category is active and `is_public=true`
4. `EMPTY_PUBLIC_PROJECTION` — projected public payload would contain ≥1 row
5. Valid public `display_order` among public rows

A layout **must not** be publishable if the public response would contain zero visible rows.

**Publication vs operational readiness:**

- Event may be **publicly listed** (marketing card) while layout incomplete
- Event is **not vendor-bookable** until operational `ready`
- Event may be operationally ready while public layout remains unpublished
- **Unpublishing does not stop vendor booking** if operational readiness remains valid
- Repository event statuses (`CarbootEventController::STATUSES`): `Available`, `Almost Full`, `Closed`

#### Category deactivation impact checks

Before setting `is_active=false` or `archived_at`:

1. Inspect active/future layout rows using the category
2. Inspect open bookings (Pending Organizer, Needs Revision, Approved, payment pending)
3. Inspect active reservations (`reserved` / `confirmed` allocations)

**Block deactivation** while the category is operationally used by an open or future event unless:

- A replacement category migration for affected rows is completed, or
- Affected events/rows are Closed/archived safely

**Service:** `VendorCategoryImpactService` (or Organizer category deactivate endpoint pre-check).

**Error:** `CATEGORY_IN_OPERATIONAL_USE` (422) with Organizer-facing list of blocking event/row/booking counts.

**Hard delete:** Forbidden while any FK reference exists (bookings, rows, profiles). Use archive only.

**Historical:** Bookings retain category ID (if FK allows) + label snapshot; Organizer reports may query archived categories.

---

### H. Layout Publication Contract

**Event-level fields (additive on `carboot_events`):**

- `public_layout_published_at` timestamp nullable — **source of truth** for “previously published”
- `public_layout_entrance_note` text nullable

**Previously published determination:**

```text
previously_published ≡ public_layout_published_at IS NOT NULL
```

Unpublish sets `public_layout_published_at = NULL` and writes a publication audit row. Chronologically ended / Closed events that retain a non-null `public_layout_published_at` remain historically published.

**Rules:**

| Actor | Publish | Unpublish |
|-------|---------|-----------|
| Organizer | Yes | Yes |
| CMart Management | No | No |
| Super Admin | Yes (technical) | Yes |

- Publish requires `EventPublicLayoutReadinessService` pass (operational ready + public projection non-empty)
- Unpublish does **not** affect vendor booking of eligible sites
- Row-level `is_public=false`: row omitted from public projection; **vendor booking still allowed** if operational
- Category `is_public=false`: row omitted from public projection even if row is public

**Publish blocking reasons (examples):** `NO_PUBLIC_ROWS`, `NO_PUBLIC_SITES`, `NO_PUBLIC_CATEGORY`, `EMPTY_PUBLIC_PROJECTION`, `EVENT_LAYOUT_NOT_READY`

---

### I. Layout Locking Contract

**Lock levels:**

| Level | Trigger |
|-------|---------|
| `editable` | No allocation history on row/site |
| `partially_locked` | Active `reserved` allocations on row/site |
| `history_locked` | Any confirmed **or released** allocation history, including paid / withdrawn-paid booking history |

**Row rename rule (MVP — corrected):**

`event_layout_rows.label` is an **identity field** mirrored to `event_sites.row_label` and may appear in booking presentation, reports, audit, and vendor summaries.

| Allocation State | Rename Row Label |
|------------------|------------------|
| No active or historical allocation | Allowed |
| Active reserved allocation | **Blocked** |
| Confirmed allocation history | **Blocked** |
| Released historical allocation | **Blocked** |
| Paid or withdrawn-paid booking history | **Blocked** |

Organizer may still update non-identity fields when otherwise allowed: `description`, `is_public`, `display_order`.

Future rename-after-immutable-snapshot is **deferred** (not MVP).

**Operation matrix:**

| Operation | No History | Active Reservation | Confirmed/Paid/Released History |
|-----------|:----------:|:------------------:|:-------------------------------:|
| Add row | Allowed | Allowed | Allowed |
| Rename unused row | Allowed | **Blocked** | **Blocked** |
| Rename used row | Allowed | **Blocked** | **Blocked** |
| Change row category | Allowed | **Blocked** | **Blocked** |
| Reorder row | Allowed | Allowed | Allowed |
| Update row description / is_public | Allowed | Allowed | Allowed |
| Archive row | Allowed if empty | **Blocked** | Archive only (`is_active=false`) |
| Delete row | Allowed if empty | **Blocked** | **Blocked** |
| Add site | Allowed | Allowed | Allowed |
| Rename site label | Allowed | **Blocked** if allocated | **Blocked** |
| Move site to another row | Allowed | **Blocked** | **Blocked** |
| Deactivate site | Allowed | Allowed if not allocated | Archive only |
| Delete site | Allowed if empty | **Blocked** | **Blocked** |
| Regenerate row sites | Allowed | **Blocked** | **Blocked** |
| Regenerate full layout | Allowed | **Blocked** | **Blocked** |

**Enforcement:** `EventLayoutLockService` called from Organizer layout controllers before mutating writes. Error: `LAYOUT_ROW_LABEL_LOCKED` (422).

---

### J. Organizer Reassignment Override Contract

**Table:** `booking_site_reassignment_overrides`

Relational columns (indexed / queryable):

| Column | Purpose |
|--------|---------|
| `id` | PK |
| `booking_id` | FK |
| `organizer_user_id` | Actor |
| `vendor_category_id` | Booking category ID at override time |
| `target_vendor_category_id` | Target row category ID |
| `override_reason` | text required (min length enforced) |
| `snapshot_version` | unsigned int (schema version of JSON payload) |
| `snapshot` | JSON — immutable human-readable payload (see below) |
| `created_at` | Timestamp |

**Immutable snapshot payload (`snapshot`, append-only, never mutated after insert):**

| Field | Purpose |
|-------|---------|
| `booking_category_id` | Booking category ID |
| `booking_category_label` | Booking category label snapshot |
| `previous_event_site_ids` | Previous site IDs |
| `previous_event_site_labels` | Previous site label snapshots |
| `previous_row_ids` | Previous row IDs |
| `previous_row_labels` | Previous row label snapshots |
| `previous_row_category_ids` | Previous row category IDs |
| `previous_row_category_labels` | Previous row category label snapshots |
| `new_event_site_ids` | New site IDs |
| `new_event_site_labels` | New site label snapshots |
| `new_row_ids` | New row IDs |
| `new_row_labels` | New row label snapshots |
| `new_row_category_ids` | New row category IDs |
| `new_row_category_labels` | New row category label snapshots |
| `affected_event_day_ids` | Affected event-day IDs |
| `affected_event_dates` | Affected operational date snapshots (`Y-m-d`) |
| `previous_allocation_statuses` | Per allocation status before switch |
| `new_allocation_statuses` | Per allocation status after switch |
| `payment_status` | Invoice/payment status snapshot |
| `booking_approval_status` | Booking approval status snapshot |
| `organizer_user_id` | Actor ID (duplicated for snapshot self-containment) |
| `override_reason` | Reason text snapshot |
| `recorded_at` | ISO-8601 timestamp |

Do **not** resolve labels dynamically from live row/site records when presenting audit history.

**Flow:**

```text
Organizer POST .../site-reassignment
→ detect category mismatch (booking.category != row.category)
→ require override_reason (min 10 chars)
→ if paid: require confirm_paid_reassignment=true
→ DB::transaction:
    capture immutable snapshot from current rows/sites/days/allocations
    release old allocations (lifecycle service)
    reserve new sites (reservation service, skip category check with override flag)
    write override record (relational + snapshot JSON)
    write booking_audit_logs reference
```

**Vendor visibility:** Sanitized message: "Your site assignment was updated by the Organizer." No override reason. No internal snapshots.

**Public:** No indication.

**Reversal:** New forward reassignment only; no silent undo. Prior override rows remain append-only history.

---

### K. Public Layout Projection Contract

**Endpoint:** `GET /api/events/{event}/layout` (no auth)

**Controller:** `PublicEventLayoutController` or `CarbootEventController::publicLayout` using `PublicEventLayoutPresenter`

**Repository event status model** (`CarbootEventController::STATUSES`): `Available` | `Almost Full` | `Closed`.
Chronologically **ended:** `ends_at < now()`.
There is **no** separate `Cancelled` event status enum today. Product “cancelled event” for public layout means: Organizer unpublished the layout (`public_layout_published_at = NULL`) as part of taking the event out of public navigation (typically also `Closed`).

**Deterministic public behaviour (no optional wording):**

| Condition | HTTP | Body |
|-----------|------|------|
| Layout incomplete / not operationally ready | 404 | `{ "message": "Layout not available" }` |
| Layout unpublished (`public_layout_published_at` null) | 404 | Same (no leak) |
| Cancelled for public purposes (unpublished; see above) | 404 | Same |
| Current or future published event (`ends_at >= now()`, published) | 200 | Navigation-only layout; no occupancy; no booking status |
| Ended (`ends_at < now()`) **or** `status = Closed`, and previously published | 200 | Static historical navigation layout; **no booking CTA**; no live availability; no reservation/payment data |

**Previously published:** `public_layout_published_at IS NOT NULL`.

**Response (200 published):**

```json
{
  "event": { "id": 1, "title": "...", "status": "Available" },
  "published": true,
  "historical": false,
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

For ended/Closed historical responses, set `"historical": true` and omit any booking CTA fields.

**Excluded:** booking IDs, vendor data, occupancy, overrides, audit, invoices, notes, payment data.

---

### L. Organizer / Vendor / Public API Contract

| Method | Route | Auth | Role / Audience | Request | Response | Main validation | Audit |
|--------|-------|------|-----------------|---------|----------|-----------------|-------|
| GET | `/api/vendor-categories` | Optional Sanctum | Public registration + authenticated community vendors | — | Active selectable categories only | `is_active=true`, `archived_at` null; ordered by `display_order` | — |
| GET | `/api/organizer/categories` | Sanctum | organizer, super_admin | — | All categories incl. inactive/archived + impact metadata | Organizer only | — |
| GET | `/api/organizer/events/{event}/layout` | Sanctum | organizer, super_admin | — | Internal rows, sites, lock state, categories | event access | — |
| GET | `/api/organizer/events/{event}/occupancy` | Sanctum | organizer, super_admin | — | Internal occupancy (reservation/confirmed counts; no vendor PII required for MVP) | event access | — |
| GET | `/api/organizer/events/{event}/layout-readiness` | Sanctum | organizer, super_admin | — | Operational + public publication readiness | — | — |
| POST | `/api/organizer/events/{event}/layout-rows` | Sanctum | organizer, super_admin | label, category_id, description | row | unique label, active category | `event_layout_audit_logs` |
| PATCH | `/api/organizer/layout-rows/{row}` | Sanctum | organizer, super_admin | description, order, is_active, is_public; label only if unlocked | row | lock service | audit |
| DELETE | `/api/organizer/layout-rows/{row}` | Sanctum | organizer, super_admin | — | 204 | no history | audit |
| POST | `/api/organizer/layout-rows/{row}/sites/generate` | Sanctum | organizer, super_admin | space_id, count, positions | sites | lock service | audit |
| POST | `/api/organizer/events/{event}/layout/publish` | Sanctum | organizer, super_admin | entrance_note? | publish state | public publication readiness | publication audit |
| POST | `/api/organizer/events/{event}/layout/unpublish` | Sanctum | organizer, super_admin | — | publish state | — | audit |
| POST | `/api/organizer/bookings/{booking}/site-reassignment` | Sanctum | organizer, super_admin | event_site_ids, override_reason, confirm_paid? | booking | override contract | booking + override audit |
| GET | `/api/vendor/events/{event}/site-availability` | Sanctum | **community vendor session only** | category_id, booking_id? | vendor-safe eligibility | readiness + category | — |
| GET | `/api/events/{event}/layout` | none | public | — | public navigation layout | publication + lifecycle rules | — |

#### Vendor-safe category endpoint vs Organizer category endpoint

| Aspect | `GET /api/vendor-categories` | `GET /api/organizer/categories` |
|--------|------------------------------|---------------------------------|
| Audience | Registration + community vendors | Organizer / Super Admin |
| Fields | `id`, `slug`, `label`, `description`, `display_order` | Above + `is_active`, `is_public`, `archived_at`, usage/impact counts |
| Filters | Active + selectable only | All including inactive/archived |
| Replaces | Hardcoded `frontend/src/constants/productCategories.js` after controlled compatibility period | N/A |

Example vendor-safe payload:

```json
{
  "categories": [
    {
      "id": 1,
      "slug": "food-beverages",
      "label": "Food & Beverages",
      "description": null,
      "display_order": 2
    }
  ]
}
```

#### Super Admin endpoint semantics (corrected)

| Actor | Availability / occupancy path |
|-------|-------------------------------|
| Community vendor | `GET /api/vendor/events/{event}/site-availability` — may include `booking_id`, category eligibility, vendor-safe disabled reasons |
| Organizer | `GET /api/organizer/events/{event}/layout` and `/occupancy` |
| Super Admin | Same **Organizer** internal endpoints when permitted — **must not** impersonate vendor-session availability |

**Extended existing routes:**

- `POST /api/organizer/events/{event}/sites/generate` — delegate to row-aware generator
- `GET /api/vendor/events/{event}/site-availability?category_id=` — category projection for vendors only

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
| View internal occupancy | Yes | No | No | No | Yes |
| View readiness status | Yes | No | No | No | Yes |
| Create layout row | Yes | No | No | No | Yes |
| Edit unused row | Yes | No | No | No | Yes |
| Edit locked row | Partial (description / order / visibility only; **no label rename**) | No | No | No | Yes |
| Assign row category | Yes (if unlocked) | No | No | No | Yes |
| Generate row sites | Yes | No | No | No | Yes |
| List vendor-safe categories | Yes (via vendor endpoint or organizer) | Yes (public-safe list only) | Yes | Yes (active selectable) | Yes |
| Manage categories (inactive/impact) | Yes | No | No | No | Yes |
| View vendor-safe availability | No | No | Yes (own session) | No | **No** — use Organizer occupancy/layout |
| Submit eligible site booking | No | No | Yes | No | No |
| Submit incompatible site booking | No | No | No | No | No |
| Reassign booking site | Yes | No | No | No | Yes |
| Override category mismatch | Yes (with reason) | No | No | No | Yes |
| View internal override reason / snapshots | Yes | No | No | No | Yes |
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
| `LAYOUT_ROW_LABEL_LOCKED` | 422 | Rename after allocation history | — | Row label cannot be renamed after allocations exist | — |
| `CATEGORY_IN_OPERATIONAL_USE` | 422 | Deactivate category still used | — | Category is used by open/future events or bookings | — |
| `PUBLIC_LAYOUT_NOT_PUBLISHABLE` | 422 | Publish with empty public projection | — | Public layout would have zero visible rows | — |
| `CATEGORY_MIGRATION_UNRESOLVED` | 422 / migrate stop | Unknown legacy category remains | — | Resolve unmapped category strings before constraint | — |

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

**Order (no implementation in Phase 3.2 / 3.3A):**

1. Create `vendor_categories` + seed **seven** canonical MVP labels (including `household-items`, `mixed-others`)
2. Add nullable `vendor_category_id` to `vendor_business_profiles`, `bookings` (and related string fields)
3. Backfill IDs using **exact + approved-alias map only**; write unknown values to migration audit; leave unresolved as NULL
4. Add `category_label_snapshot` to bookings; backfill from mapped labels only where FK resolved
5. Create `event_layout_rows`
6. Add nullable `event_layout_row_id` to `event_sites`
7. Backfill rows from distinct `(carboot_event_id, row_label)`
8. Link sites to rows; mirror `row_label` from row.label
9. Add `event_layout_audit_logs`, override table with immutable snapshot JSON, publication fields
10. **Stop condition:** unresolved category counts must be 0 before NOT NULL / FK enforcement
11. Compatibility presenters dual-read strings
12. Switch write paths to FK + snapshot
13. Switch read paths; vendors consume `GET /api/vendor-categories`
14. Deprecate string-only writes after regression

**Verification queries:** count sites without row; count bookings/profiles with unmapped category; duplicate row labels per event; list migration audit unknowns.

**Rollback:** Drop constraints and nullable FKs; keep string columns; retain audit of unknowns until Phase 3.11.

**Empty DB:** Seeders create categories + demo event layout in Phase 3.11.

---

## Testing Strategy

### Phase 3.3 prerequisite (complete)

- Dedicated `cmart_test` database — **done**
- `phpunit.xml` sets `DB_DATABASE=cmart_test` — **done**
- `TestingDatabaseGuard` rejects `cmart_db` — **done**
- Documented in `docs/phase-3/phase-3-3-test-environment-isolation-and-baseline-safety.md`

### Phase 3.3A prerequisite (this amendment)

- All mandatory ADR corrections integrated — **this document**
- All 22 skipped tests classified — `docs/phase-3/phase-3-3a-adr-corrections-and-skipped-test-classification.md`
- No critical skipped path hides schema/backfill or canonical governance without replacement coverage

### Phase 3.4 entry gate (mandatory)

Phase 3.4 may begin only when:

1. Phase 3.3 test isolation is complete.
2. All mandatory Phase 3.2 ADR corrections are integrated.
3. All skipped tests are classified.
4. No critical skipped regression path is left unexplained.
5. Canonical taxonomy and migration mapping are documented.
6. Unknown legacy categories are handled as blockers, not silently remapped.

Skipped tests need **not** all be repaired before Phase 3.4 unless classification proves they protect schema-critical behaviour (see Phase 3.3A report: none block 3.4).

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
| Invalid legacy category strings | Medium | Low | Exact map + migration audit; **block NOT NULL** until unresolved = 0 | 3.4 |
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
- Row rename after immutable row-label snapshots exist
- Additional migration aliases (`Food & Drinks`, `Preloved Clothes`) without evidence
- Repair of seed-dependent skipped tests (Phase 3.11 fixture hardening; see Phase 3.3A)

---

## Rejected Approaches

- `row_label`-only layout (no first-class row)
- Zone with multiple categories (MVP)
- Per-site `category_id` for eligibility
- Vendor profile as enforcement source
- Vendor-submitted incompatible sites pending approval
- Silent Organizer override
- CMart Management layout configuration
- Silent mapping of unknown categories to `Mixed / Others`
- Row rename after allocation history (MVP)
- Super Admin using vendor-session availability as inspection path
- Optional / ambiguous ended-event public layout behaviour

---

## Implementation Roadmap

### Phase 3.3 — Test Environment Isolation and Baseline Safety

**Status:** Complete.
**DoD:** Tests never write to `cmart_db`; guard active.

### Phase 3.3A — ADR Corrections and Skipped-Test Classification

**Status:** Complete (this amendment + classification report).
**DoD:** ADR consistent; 22 skips classified; Phase 3.4 entry gate honest.

### Phase 3.4 — Canonical Category and Layout Schema

**Objective:** Tables, FKs, backfill, seeders.
**Dependencies:** 3.3 + 3.3A.
**Files:** migrations, `VendorCategory`, `EventLayoutRow`, models.
**Exclusions:** No API/UI. Unknown categories must remain unresolved blockers until fixed.

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
- `docs/phase-3/phase-3-3-test-environment-isolation-and-baseline-safety.md`
- `docs/phase-3/phase-3-3a-adr-corrections-and-skipped-test-classification.md`
- `docs/phase-2/phase-2a-architecture-decision-record.md`
- `docs/phase-2/phase-2a7-booking-creation-and-allocation-lifecycle.md`
- `docs/phase-2/phase-2a7-1-test-isolation-and-local-data-cleanup.md`
- Repository event statuses: `backend/app/Http/Controllers/Api/CarbootEventController.php` (`Available`, `Almost Full`, `Closed`)
- Hardcoded legacy categories: `frontend/src/constants/productCategories.js`, `BookingController` validation rules
