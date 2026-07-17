# Phase 3.7 — Vendor Category Eligibility and Booking Write Migration

## 1. Objective

Connect the Phase 3 canonical category and layout-row foundation to the real vendor booking pipeline so that:

1. Vendors select a canonical category.
2. Availability returns only compatible row sites.
3. Booking creation revalidates category ↔ site compatibility inside the reservation transaction.
4. Bookings persist `vendor_category_id`, immutable `category_label_snapshot`, and a canonical `product_category` mirror.

Frontend filtering alone is not security. Backend enforcement is mandatory.

## 2. Vendor Category Endpoint

```http
GET /api/vendor-categories
```

- **Authorization:** public (registration / profile / booking may call before or after login).
- **Filter:** `is_active = true`, `archived_at IS NULL`, `is_public = true`.
- **Order:** `display_order`, then `id`.
- **Fields:** `id`, `slug`, `label`, `description`, `display_order`.
- **Excluded:** usage counts, layout/booking counts, migration audits, organizer metadata.

Organizer-only enriched lookup remains at `GET /api/organizer/vendor-categories`.

## 3. Category Resolution Rules

Service: `App\Services\VendorCategoryResolver`

| Input | Result |
| ----- | ------ |
| Canonical ID (active) | Category model |
| Exact canonical label | Category model |
| `Others` | `Mixed / Others` |
| `Food & Drinks` | Reject `UNKNOWN_LEGACY_CATEGORY` |
| Case-only variant | Reject `UNKNOWN_LEGACY_CATEGORY` |
| Inactive | Reject `CATEGORY_INACTIVE` |
| Archived | Reject `CATEGORY_ARCHIVED` |
| ID + string mismatch | Reject `CATEGORY_FIELDS_MISMATCH` |
| Missing both | Reject `CATEGORY_REQUIRED` |

No fuzzy matching. No silent fallback to Mixed / Others.

## 4. Profile Category Behaviour

- Profile category is a **suggestion only** for booking preselection.
- Writes: `vendor_business_profiles.vendor_category_id` + canonical `business_category` mirror.
- Accepts `vendor_category_id` and/or exact label / approved `Others` alias.
- Does **not** restrict which category a vendor may choose for a specific booking.

Controllers: `VendorBusinessProfileController`, `VendorProfileController`.

## 5. Availability Category Contract

```http
GET /api/vendor/events/{event}/site-availability?vendor_category_id={id}
```

Legacy transitional query: `product_category` (exact / alias).

Optional owned-booking context: `booking_id` (cross-user → 403 `BOOKING_CONTEXT_FORBIDDEN`).

**New booking precedence**

1. Explicit `vendor_category_id`
2. Exact legacy `product_category` during transition
3. Profile category is never silently applied as the requested category
4. If no category → `category_required=true`, empty `sites`, readiness `category_required`

**Compatibility period:** existing clients that omit category receive an empty selectable set rather than all sites.

## 6. Site Eligibility Rules

A site is category-compatible only when:

1. Belongs to the event
2. Active operational status
3. Has `event_layout_row_id`
4. Row belongs to the same event, is active, not archived
5. Row has an active, non-archived category
6. Row category ID equals selected booking category ID
7. Valid Space
8. Free across every active EventDay (existing full-event occupancy rule)
9. Satisfies existing same-row / contiguous / same-space rules at reservation time

Eligibility is never inferred from `row_label`, site label prefix, Space type, or profile string.

## 7. Booking Write Contract

Preferred payload:

```json
{
  "event_id": 10,
  "vendor_category_id": 2,
  "event_site_ids": [101, 102],
  "product_details": "Drinks and snacks"
}
```

Legacy transitional: `product_category` exact label or `Others`.

On success:

| Column | Value |
| ------ | ----- |
| `vendor_category_id` | Canonical ID |
| `category_label_snapshot` | Canonical label |
| `product_category` | Canonical label mirror (`Mixed / Others`, never `Others`) |

## 8. Snapshot Behaviour

- Snapshot written on create.
- Snapshot updates only when an authorized category change succeeds (`PATCH /vendor/bookings/{id}` in Pending_Organizer / Needs_Revision).
- Historical audits are not rewritten.
- Presenter prefers snapshot label for display.

## 9. Legacy Compatibility

- `Others` → `Mixed / Others` on new writes only.
- Unknown / fuzzy values rejected.
- `product_category` remains in API responses during the compatibility period.
- Frontend continues to send canonical label mirror alongside `vendor_category_id`.

## 10. Revision / Resubmission Rules

- `resubmit` still forbids `event_site_ids` changes; category retained unless changed via `vendorUpdate`.
- Category change allowed only in Pending_Organizer / Needs_Revision.
- Retained allocation sites must all be compatible with the new category; otherwise `SITE_CATEGORY_INCOMPATIBLE`.
- No silent site release/rebook.
- Terminal statuses unchanged.

## 11. Frontend Integration

Minimal wiring in `Registration.vue` (Phase 3.9 redesign deferred):

- Load categories from `GET /api/vendor-categories`
- Hold `vendor_category_id` in form state
- Preselect profile category as suggestion
- Reload availability when category changes
- Clear selected sites on category change
- Pass `vendor_category_id` to availability and booking create
- BM error messages via `vendorCategoriesApi.js`

## 12. Error Contract

| Code | HTTP | Meaning |
| ---- | ---: | ------- |
| `CATEGORY_REQUIRED` | 422 | No category selected |
| `CATEGORY_NOT_FOUND` | 422 | Unknown ID |
| `CATEGORY_INACTIVE` | 422 | Inactive category |
| `CATEGORY_ARCHIVED` | 422 | Archived category |
| `CATEGORY_FIELDS_MISMATCH` | 422 | ID/label disagree |
| `UNKNOWN_LEGACY_CATEGORY` | 422 | Unrecognized string |
| `EVENT_LAYOUT_NOT_READY` | 409 | Operational layout not ready |
| `SITE_MISSING_LAYOUT_ROW` | 422 | Site lacks row |
| `SITE_ROW_INACTIVE` | 422 | Row inactive/archived |
| `SITE_ROW_CATEGORY_MISSING` | 422 | Row missing category |
| `SITE_CATEGORY_INCOMPATIBLE` | 422 | Site/row category mismatch |
| `MIXED_CATEGORY_SITE_SELECTION` | 422 | Mixed row categories |
| `LAYOUT_CHANGED` | 409 | Row/event mismatch under lock |
| `BOOKING_CONTEXT_FORBIDDEN` | 403 | Cross-user booking_id |

## 13. Audit Behaviour

Create audit (`vendor_submitted_booking`) stores safe context in `revision_comment`:

```text
vendor_category_id=…; category_label_snapshot=…; sites=…; rows=…
```

Category change audit (`vendor_category_change`) stores previous/new ID and snapshot.

Uses existing `booking_audit_logs` — no new audit table.

## 14. Concurrency Behaviour

Availability is informational. Booking create revalidates under event + site + row locks inside the existing reservation transaction. Stale row-category changes fail with `SITE_CATEGORY_INCOMPATIBLE` and leave no Booking / Invoice / Allocation residue.

## 15. Tests

- Unit: `VendorCategoryResolverTest`
- Feature: `Phase37VendorCategoryEligibilityTest`
- Compatibility updates: booking creation, availability, lifecycle, withdrawal, attendance, recovery, community access fixtures attach layout rows via `EnsuresCanonicalLayoutForSites`

## 16. E2E

Browser E2E against persistent `cmart_db` remains **blocked**: Phase 3 tables (`vendor_categories`, `event_layout_rows`, …) are missing on `cmart_db`, and this phase must not migrate `cmart_db`.

Substitute validation: full PHPUnit suite on `cmart_test` + frontend unit + production build.

## 17. Persistent-data Validation

`cmart_db` baseline counts unchanged (Phase 3 tables still MISSING). No destructive migrations run against development.

## 18. Known Limitations

- Organizer mismatch override not implemented (Phase 3.8).
- Vendor TGV-style UX enhancement deferred (Phase 3.9).
- Public layout endpoint/UI deferred (Phase 3.10).
- Legacy `product_category` string not retired.
- Category FKs remain nullable globally.
- Hardcoded category arrays may remain in non-booking surfaces (marketplace/profile forms) until a follow-up migrates those UIs to the API list.

## 19. Phase 3.8 Entry Criteria

Phase 3.8 may proceed when:

- Backend category compatibility cannot be bypassed via crafted payloads.
- Canonical FK + snapshot are written on successful create.
- Availability is category-aware.
- Full backend suite and frontend unit/build pass.
- `cmart_db` untouched.

Next phase: **Phase 3.8 — Organizer Category Mismatch Override and Site Reassignment**.
