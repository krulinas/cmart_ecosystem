# Phase 2A.8 — Vendor Event-Site Availability and Cinema-Style Site Selection

## Phase objective

Expose a vendor-safe EventSite availability API and integrate a cinema-style physical site selector into the vendor booking form. Vendors select real `event_site_ids`, submit through the existing `POST /api/bookings` contract, and see authoritative `site_selection` data in booking views.

**Flow:**

```text
Organizer configures EventSites and EventDays
→ Vendor loads live availability
→ Vendor selects contiguous physical sites
→ Frontend submits event_site_ids
→ Backend atomically reserves sites across all active days
→ Successful response returns authoritative site_selection
```

> **Availability displayed in the browser is advisory.** The booking transaction and database uniqueness constraint remain authoritative.

> **The selected site price covers the complete event duration and is not multiplied by the number of active EventDays.**

---

## Availability endpoint

| Item | Value |
|------|-------|
| Method / path | `GET /api/vendor/events/{carboot_event}/site-availability` |
| Controller | `VendorEventSiteAvailabilityController@show` |
| Service | `VendorEventSiteAvailabilityService` |
| Route group | `auth:sanctum` + `role:community` (same vendor booking surface) |

### Authorization

| Actor | Result |
|-------|--------|
| Authenticated approved community/vendor user | `200` |
| Unauthenticated | `401` |
| `cmart_management` | `403` |
| Organizer / super admin | Not exposed on this vendor route |

Vendor eligibility matches booking creation — no new role capabilities were added.

---

## Availability derivation

A site is selectable only when **all** are true:

1. Belongs to the requested event
2. `operational_status = active`
3. Has a valid Space relationship
4. Event has at least one active EventDay
5. No active `BookingDayAllocation` (`active_lock = 1`) exists for that site on any active EventDay

**Full-event rule:** if a site is occupied on any active EventDay, it is unavailable for full-event selection.

Released/cancelled historical allocations do not block availability. Booking approval status alone is not used.

### Query strategy

- Single query for active EventDays (`forEvent` + `active` + `ordered`)
- Single query for EventSites with eager-loaded `space`
- One grouped query for occupied site IDs across active day IDs (`whereIn` + `whereNotNull('active_lock')`)
- No per-site allocation N+1 queries
- No caching in this phase

---

## API response

```json
{
  "event": { "id": 7, "title": "...", "status": "Available", "day_generation_mode": "calendar_days" },
  "operational_days": [
    {
      "id": 21,
      "operational_date": "2026-08-01",
      "starts_at": "...",
      "ends_at": "...",
      "operational_status": "active",
      "display_order": 1
    }
  ],
  "selection_rules": {
    "same_row_required": true,
    "consecutive_positions_required": true,
    "same_space_type_required": true,
    "full_event_duration": true
  },
  "sites": [
    {
      "id": 101,
      "label": "A05",
      "row_label": "A",
      "position_number": 5,
      "availability_status": "available",
      "is_selectable": true,
      "price": "30.00"
    }
  ],
  "readiness": { "status": "no_event_sites", "message": "..." }
}
```

### Excluded private fields

Not returned: `active_lock`, `booking_id`, vendor identity, allocation IDs, release metadata, payment/audit data.

### Response codes

| Code | Meaning |
|------|---------|
| `200` | Availability payload (may include empty `sites` + readiness) |
| `401` | Unauthenticated |
| `403` | CMart Management / non-vendor |
| `422` | No active EventDays or event not bookable |

---

## Site selector component

| Item | Value |
|------|-------|
| Component | `EventSiteSelector.vue` |
| Path | `frontend/src/components/vendor/EventSiteSelector.vue` |
| Utilities | `frontend/src/utils/eventSiteSelection.js` |

### Visual states

Legend covers: Available, Selected, Occupied, Unavailable, Disabled — each with text label + color swatch (not color-only).

Sites render in row groups using `row_label` and ordered `position_number`.

### Selection rules (UX guidance)

- First site: any available site
- Additional sites: same row, same `space_id`, contiguous positions
- Deselection: only from range edges; middle deselection blocked with message
- Clear selection action provided

### Price preview

`preview amount = sum(selected sites' API prices)` — **not** multiplied by EventDay count. Labelled as preview; server invoice is authoritative.

### EventDay summary

Shows operational dates from `operational_days`. Full-event message: sites reserved for all active event days.

---

## Booking form integration

| Item | Value |
|------|-------|
| Form | `frontend/src/views/auth/Registration.vue` (`/vendor-booking`) |
| API client | `frontend/src/services/api.js` |

On event select:

1. Clears stale selection
2. Loads availability with request token (stale responses ignored)
3. Renders `EventSiteSelector`
4. Disables submit until valid selection exists

### Submitted payload

```json
{
  "event_id": 1,
  "event_site_ids": [12, 13],
  "product_category": "Food & Beverages",
  "product_details": "..."
}
```

Legacy client fields (`tapak_quantity`, `total_price`, `space_id`) are not submitted.

### Conflict handling (`409`)

1. Toast: sites no longer available; layout refreshed
2. Reload availability
3. Prune invalid selections via `pruneInvalidSelections`
4. Preserve valid contiguous remainder when possible
5. Unrelated form fields kept

### Validation (`422`)

Backend `event_site_ids` errors shown in selector via `siteSelectionError`.

---

## Booking display

### Vendor detail modal

`VendorBookingDetailsModal.vue` — read-only **Physical Site Selection** section when `site_selection` present: labels, count, allocation status, space type, event days.

### Organizer panel

`OrganizerBookingsPanel.vue` — Space / Sites column shows real labels + allocation status from `site_selection`.

### Legacy fallback

`boothLabelForBooking()` uses `site_selection` labels when present; legacy synthetic booth label only for approved bookings without allocation data.

Internal fields (`active_lock`, allocation IDs, etc.) are not shown.

---

## Accessibility and responsive behavior

- Each site is a `<button>` with `aria-pressed`, descriptive `aria-label`, keyboard focus ring
- Row map scrolls horizontally on narrow viewports
- Legend wraps; summary readable on mobile
- Error/readiness regions use `role="alert"` / `aria-live`

---

## Tests

### Backend

`backend/tests/Feature/VendorEventSiteAvailabilityTest.php` — 9 tests, 28 assertions:

- Vendor payload, auth boundaries, occupied/released/disabled sites, no days 422, no sites readiness, cancelled day exclusion

Regression filters (103 tests) and full suite (147 passed, 2 skipped, 526 assertions) executed successfully.

### Frontend unit

`frontend/tests/unit/eventSiteSelection.test.js` — 12 tests via `npm run test:unit`

Covers: row grouping, selection adjacency, row/space rejection, edge/middle deselection, preview amount, day summary, conflict pruning.

### E2E / integration

`frontend/tests/e2e/helpers/booking.js` and `vendor.booking.spec.js` updated to select first available site tile before submit.

**Limitation:** Local persistent DB has `event_sites=0` and `event_days=0` on production events. Browser E2E booking flow requires an event with Organizer-configured layout and active days. Helpers fail fast with a clear readiness message when sites are not configured. Full browser E2E was not executed in this pass due to missing local layout data on bookable events.

---

## Validation results

| Command | Result |
|---------|--------|
| `php artisan test --filter=VendorEventSiteAvailability` | 9 passed |
| Phase 2A regression filters | 103 passed |
| `php artisan test` | 147 passed, 2 skipped |
| `npm run test:unit` | 12 passed |
| `npm run lint` | **Failed** — 13 pre-existing oxlint errors in unrelated files (not introduced by 2A.8) |
| `npm run build` | Success |

---

## Persistent data verification

| Table | Before | After |
|-------|--------|-------|
| users | 4 | 4 |
| carboot_events | 6 | 6 |
| event_sites | 0 | 0 |
| event_days | 0 | 0 |
| spaces | 2 | 2 |
| bookings | 0 | 0 |
| invoices | 0 | 0 |
| booking_day_allocations | 0 | 0 |
| booking_audit_logs | 0 | 0 |
| news | 1 | 1 |
| feedback | 5 | 5 |

Counts remained stable across focused and full backend test runs.

---

## Known limitations

1. Availability is advisory until booking transaction completes
2. No site reallocation, partial release, or per-day site changes
3. E2E requires Organizer-configured sites/days on a bookable event
4. Repository-wide `npm run lint` has pre-existing oxlint failures unrelated to this phase

---

## Deferred work

Not implemented in Phase 2A.8:

- Site reallocation / swapping after booking
- Partial-site or partial-day release
- Paid withdrawal, no-refund handling, refund processing
- Waitlist, allocation expiration, manual Organizer assignment
- Payment / pass / check-in redesign
- Allocation analytics, per-day pricing
- Schema migrations or new allocation statuses

---

## Next readiness

**Recommended next phase:** post-booking withdrawal and operational enhancements (Phase 2B), building on the allocation lifecycle established in 2A.7 and the vendor site-selection UX from 2A.8.
