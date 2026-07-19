# Phase 4.1 — Item Listing Foundation

**Document date:** 2026-07-18  
**Repository:** CMart / Carboot@CMart (`cmart_ecosystem`)  
**Status:** Complete  
**Depends on:** `docs/phase-4/phase-4-0-item-reservation-architecture-audit.md`

---

## 1. Objective

Prepare the existing `VendorItem` catalogue and `CarbootEvent` aggregate for the later item-reservation engine without creating reservation schema, APIs, concurrency, charge actions, or reservation UI.

Phase 4.1 keeps the marketplace preview-only.

---

## 2. Architecture corrections applied

`docs/phase-4/phase-4-0-item-reservation-architecture-audit.md` now records and consistently applies:

1. Zero-fee reservations will use `charge_status=not_required`, not `waived`.
2. Future public reservation references are opaque and non-sequential, for example `RSV-7K4M9Q2P`.
3. Reservation-history delete blocking is deferred to Phase 4.2. The exact extension point is `VendorItemController::destroy()` before `$vendor_item->delete()`.

No reservation status, model, table, reference generator, or delete-history query was implemented.

---

## 3. Files inspected

Material inspection included:

- `backend/app/Models/CarbootEvent.php`
- `backend/app/Http/Controllers/Api/CarbootEventController.php`
- `backend/app/Services/EventPresenter.php`
- `backend/routes/api.php`
- `backend/app/Models/VendorItem.php`
- `backend/app/Http/Controllers/Api/VendorItemController.php`
- `backend/app/Services/VendorItemPresenter.php`
- `backend/app/Services/MarketplaceItemPresenter.php`
- `backend/app/Services/MarketplaceEligibility.php`
- `backend/app/Models/VendorCategory.php`
- `backend/app/Services/VendorCategoryResolver.php`
- `backend/app/Support/Migrations/CategoryLegacyMapper.php`
- `backend/app/Models/ReuseItemImage.php`
- Phase 3 category migrations and tests
- `frontend/src/components/VendorItemFormModal.vue`
- `frontend/src/views/dashboards/staff/StaffEventsPanel.vue`
- `frontend/src/services/vendorCategoriesApi.js`
- `frontend/src/components/public/MarketplaceItemCard.vue`
- `frontend/src/components/MarketplaceItemDetailsModal.vue`
- existing backend and frontend test infrastructure

---

## 4. Files changed

| Path | Purpose |
|------|---------|
| `docs/phase-4/phase-4-0-item-reservation-architecture-audit.md` | Mandatory architecture corrections |
| `docs/phase-4/phase-4-1-item-listing-foundation.md` | Phase 4.1 implementation record |
| `docs/phase-3/phase-3-debt-register.md` | Mark vendor-item canonical writes resolved |
| `backend/database/migrations/2026_07_18_000002_add_item_reservation_service_fee_to_carboot_events.php` | Nullable event fee + named non-negative CHECK |
| `backend/app/Models/CarbootEvent.php` | Fillable field and `decimal:2` cast |
| `backend/app/Http/Controllers/Api/CarbootEventController.php` | Validation and Organizer-only response exposure |
| `backend/app/Services/EventPresenter.php` | Explicit Organizer configuration boundary |
| `backend/app/Http/Controllers/Api/VendorItemController.php` | Canonical category resolution and derived legacy label |
| `backend/app/Services/VendorItemPresenter.php` | Owner category ID + disabled readiness flags |
| `backend/app/Services/MarketplaceItemPresenter.php` | Public disabled readiness flags |
| `frontend/src/components/VendorItemFormModal.vue` | API-backed canonical category selection |
| `frontend/src/views/dashboards/staff/StaffEventsPanel.vue` | Organizer fee configuration field |
| `backend/tests/Feature/Phase41EventReservationFeeTest.php` | Fee schema, validation, authorization, privacy, isolation |
| `backend/tests/Feature/Phase41VendorItemFoundationTest.php` | Category, presenter, delete, image cleanup tests |
| `frontend/tests/unit/phase41ItemListingFoundation.test.js` | Form contract and no-reservation UI tests |

Pre-existing Phase 3 working-tree changes were preserved and are not Phase 4.1 implementation files.

---

## 5. Event fee schema

`carboot_events.item_reservation_service_fee`:

- `DECIMAL(10,2)`
- nullable
- no default
- existing rows remain `NULL`
- named CHECK: `carboot_events_item_reservation_fee_non_negative`

Semantics:

| Value | Meaning |
|-------|---------|
| `NULL` | Reservation service fee is not configured; reservations remain closed |
| `0.00` | Configured; future charge is `not_required` |
| `> 0.00` | Future reservation snapshots this Organizer-owned fee |

The CHECK follows the repository's proven MariaDB/MySQL named-constraint convention.

---

## 6. Event fee validation and authorization

`CarbootEventController::validateEvent()` enforces:

- nullable
- numeric
- zero to two decimal places
- minimum `0`
- maximum `99999999.99`

The existing `/api/carboot-events` resource remains inside `ManagementRole::carbootOperationalRoles()`. Organizer and the existing Super Admin operational convention are permitted; Community and CMart Management receive `403`.

`EventPresenter::fromModel()` includes the fee only when the controller explicitly requests Organizer configuration. Public `/api/events` payloads do not expose it.

The Organizer Carboot Events form supports blank, zero, and positive decimal values without calculating authoritative money in the browser.

---

## 7. Canonical category write flow

The authoritative write flow is:

```text
vendor_category_id
→ VendorCategoryResolver
→ active + not archived + public checks
→ vendor_items.vendor_category_id
→ canonical VendorCategory.label copied to legacy vendor_items.category
```

The backend no longer trusts an arbitrary item category string as persisted display truth.

Rejected cases:

- unknown ID: `CATEGORY_NOT_FOUND`
- inactive category: `CATEGORY_INACTIVE`
- archived category: `CATEGORY_ARCHIVED`
- non-public category: `CATEGORY_NOT_PUBLIC`
- ID/label mismatch: `CATEGORY_FIELDS_MISMATCH`
- unknown legacy string: `UNKNOWN_LEGACY_CATEGORY`

No unknown value maps silently to `Mixed / Others`.

---

## 8. Legacy compatibility behaviour

The old `category` input remains a temporary strict compatibility adapter for existing clients:

- exact canonical labels and approved Phase 3 aliases only;
- the resolver still produces a canonical category row;
- both `vendor_category_id` and the canonical label are persisted;
- unknown values fail with `422`;
- when both ID and label are supplied they must identify the same category.

Existing legacy rows with nullable `vendor_category_id` remain readable. Editing them through the current frontend requires selecting a canonical category if the Phase 3 backfill could not resolve them.

The Vue item form now obtains categories from `/api/vendor-categories`, binds the ID, and submits `vendor_category_id`.

---

## 9. Delete/archive readiness decision

Outcome A was selected: the existing delete path is already centralized and safe for extension.

Current flow:

1. `VendorItemController::destroy()` verifies owner access.
2. It calls `$vendor_item->delete()`.
3. `VendorItem::deleting` deletes gallery models and the legacy image.
4. `ReuseItemImage::deleting` deletes gallery files from the public disk.

Phase 4.1 adds no service, fake history method, reservation count, archive status, or SoftDeletes.

Phase 4.2 adds the real reservation-history guard in `VendorItemController::destroy()` immediately after ownership authorization and before `$vendor_item->delete()`.

Item statuses remain `active` and `inactive`.

---

## 10. Presenter contract

Public marketplace items now include:

```json
{
  "is_reservable": false,
  "has_active_reservation": false
}
```

Owner item payloads include the same booleans plus `vendor_category_id` for form editing.

The values are literal booleans. No reservation ID, user identity, charge note, audit metadata, or payment data exists or is exposed. Existing marketplace eligibility remains unchanged and no Reserve CTA was added.

---

## 11. Booking, Invoice, and allocation isolation

Phase 4.1 does not write:

- `bookings`
- `invoices`
- `booking_day_allocations`
- booking approval status
- invoice amount/status/proof fields
- booking audit logs

Focused tests snapshot booking status, invoice financial/proof fields, and allocation status/lock fields before and after event-fee updates and prove they are unchanged.

---

## 12. Backend tests

### Focused Phase 4.1

```text
php artisan test tests/Feature/Phase41EventReservationFeeTest.php tests/Feature/Phase41VendorItemFoundationTest.php
11 passed, 85 assertions
```

### Affected regression set

```text
53 passed, 287 assertions
```

This covered Phase 4.1 tests plus vendor private items, marketplace public access, event layout/days, governance boundaries, category lookup, and Phase 3.7 eligibility.

### Full backend

```text
php artisan test
331 passed, 0 failed, 0 skipped, 1655 assertions
```

Baseline was 320 tests / 1570 assertions. Phase 4.1 adds 11 tests / 85 assertions with no reduction or skips.

---

## 13. Frontend tests

```text
npm run test:unit
85 passed, 0 failed, 0 skipped
```

Phase 4.1 adds five tests for canonical category loading/submission/preselection, validation display, event fee payloads, and absence of reservation CTAs.

Baseline was 80 tests.

---

## 14. Lint and build results

```text
npm run lint:oxlint
0 warnings, 0 errors

npm run lint:eslint
0 errors

npm run build
passed
```

The pre-existing Vite advisory for a main chunk above 500 kB remains; it is already recorded as frontend performance debt and is not an error.

Relevant PHP files were formatted with Laravel Pint.

---

## 15. Migration and rollback notes

Test database identity and migration status were inspected before migration.

Validation sequence:

1. Apply `2026_07_18_000002_add_item_reservation_service_fee_to_carboot_events`.
2. Run schema/feature tests.
3. Roll back one migration.
4. Confirm the fee column is absent while `carboot_events` remains.
5. Reapply the migration.
6. Run full backend tests.

Rollback drops the named CHECK first, then only `item_reservation_service_fee`.

No booking, invoice, item-price, space-price, or category schema was altered.

---

## 16. Persistent-data cleanup

Tests run against the guarded `cmart_test` database.

Cleanup order for Phase 4.1 fixtures:

1. Vendor items through Eloquent, firing image cleanup hooks
2. Bookings
3. Events through Eloquent
4. Users
5. Test-created vendor categories

An initial focused-test teardown syntax defect left identifiable Phase 4.1 test rows. Those exact IDs were removed from `cmart_test`, teardown was corrected, and the suites were rerun.

Final marker checks:

```text
phase41_events: 0
phase41_items: 0
phase41_categories: 0
item_reservations table: absent
```

No pre-existing developer records or production database rows were deleted.

---

## 17. E2E result

The existing relevant spec was attempted:

```text
npm run test:e2e:headless -- public.public-route-safety.spec.js
```

Dedicated frontend (`5175`) and backend (`8011`) servers became reachable. The suite then stopped in preflight because the configured local E2E vendor credentials were rejected with HTTP `422`; no browser test or mutation ran. The temporary servers were stopped.

Phase 4.1 adds no reservation E2E fixture or flow. Backend public-presenter tests and the full frontend unit suite cover the changed contract.

---

## 18. Deferred Phase 4.2 work

- `item_reservations` and `item_reservation_audits` schema
- Opaque public-reference generator
- one-active-reservation unique constraint and `active_lock`
- transactional reservation service
- duplicate-key-to-409 handling
- reservation-history item delete guard
- reservation APIs
- manual charge lifecycle
- reservation UI
- scheduler expiry

---

## 19. Unresolved issues

None in Phase 4.1 implementation.

The local E2E credential mismatch is an environment preflight issue, not a Phase 4.1 product or architecture blocker.

---

## 20. Phase 4.1 verdict

```text
PHASE 4.1 COMPLETE — READY FOR PHASE 4.2
```

Phase 4.2 may implement the reservation schema and concurrency engine against this foundation. It must continue to exclude manual charge actions, full reservation UI, and scheduler expiry until their designated subphases.
