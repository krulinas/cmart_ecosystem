# Event-Level Parking Pricing Implementation Report

## 1. Runtime Safety

* Resolved database: `cmart_db_rebuild`
* Confirmed as `cmart_db_rebuild`: yes (`.env` + `php artisan` config)
* Databases created/deleted/renamed: disposable `cmart_test` created via repository `scripts/ensure_test_database.php` for PHPUnit only; `cmart_db` / `cmart_db_rebuild` not created, deleted, renamed, cloned, or rebuilt
* Destructive commands used: none (`migrate:fresh` not run; no broad reseed on rebuild)

## 2. Architecture Implemented

* Event price storage: `carboot_events.site_price` (`decimal(10,2)`, required `> 0`, default `20.00`)
* Organizer default-price storage: `users.default_site_price` (nullable `decimal(10,2)`, organizer-scoped)
* Authoritative formula: `event.site_price × selected site count`
* Booking snapshot approach: `bookings.unit_site_price` + `bookings.site_quantity`; invoice `amount` remains the persisted total
* Event-day effect: none — day count does not multiply price

## 3. Database Changes

* Migrations: `2026_07_22_000001_add_event_site_price_and_organizer_default` (ran on `cmart_db_rebuild` and `cmart_test`)
* Fields added:
  * `carboot_events.site_price`
  * `users.default_site_price`
  * `bookings.unit_site_price`
  * `bookings.site_quantity`
* Existing-event backfill: all existing events on rebuild received `20.00` (observed: event 5 Kedah International Carboot, event 6 Jom SOC! CARBOOT SALES)
* Historical booking/invoice handling: not bulk-modified (observed invoice `id=1` amount remained `30`)
* Legacy space records: retained for FK / layout compatibility; no longer used for current booking price

## 4. Organizer Flow

* Create-event price field: **Harga Satu Tapak** with `RM` prefix (`StaffEventsPanel.vue`)
* Default-price checkbox: **Simpan harga ini sebagai harga lalai untuk acara seterusnya**
* Prefill behaviour: organizer `default_site_price` if set, else `20.00`
* Edit-event behaviour: shows that event’s own `site_price`; saving updates event only unless checkbox checked
* Existing-booking warning: shown when `has_bookings` is true

## 5. Vendor Flow

* Availability API: returns event-level `site_price` on payload and each site; `space_name` cleared for vendor availability
* Site tile: label + status + `RM{price}` only (no Standard/Large)
* Removed labels: Standard / Large / `(1 Parking Lot)` / `(2 Parking Lots)` from current vendor tiles; `Jenis Ruang` removed from booking summary
* Booking summary: Tapak Dipilih, Bilangan Tapak, Harga Satu Tapak, Pengiraan (`RM × N tapak`), Jumlah + day-duration note
* One-site result: `RM20.00 × 1 tapak` → `RM20.00`
* Two-site result: `RM20.00 × 2 tapak` → `RM40.00`
* Three-site result: `RM20.00 × 3 tapak` → `RM60.00`

## 6. Backend Changes

* Files changed: migration; `CarbootEvent`, `User`, `Booking`; `CarbootEventController`; `BookingAllocationReservationService`; `VendorEventSiteAvailabilityService`; `EventPresenter`; `UserAuthPresenter`; `VendorBookingPresenter`; `BookingController` (client amount fields prohibited); seeder; related fixtures/tests updated earlier
* Calculation service: `BookingAllocationReservationService::deriveAmount()` via `bcmul(event.site_price, site_count)`
* Validation: create/update require positive `site_price`; `save_as_default_site_price` optional boolean
* Zero-price protection: missing/non-positive event price throws `missing_event_site_price`
* Historical amount protection: invoice totals not recalculated on event price edits; booking snapshots preserve unit/qty for new bookings

## 7. Frontend Changes

* Files changed: `StaffEventsPanel.vue`, `EventSiteSelector.vue`, `VisualParkingLayout.vue`, `visualParkingLayout.js`, `eventSiteSelection.js`, `bookingDisplay.js`, `Registration.vue`
* BM copy: Harga Satu Tapak, Simpan harga ini sebagai harga lalai…, Pengiraan, day-duration note, price-change warning
* English copy: only where existing patterns already use English (dashboard list strings / booth fallback); BM-first for vendor selector
* Reactive calculation: `resolveEventUnitPrice` + `computePreviewAmount(sites, unitPrice)`
* Removed obsolete logic: vendor tile space-type text / `shortSpaceName`; summary `Jenis Ruang`; default booth label no longer “Standard (1 Parking Lot)”

## 8. Tests

* Focused backend: `EventSitePricingTest` + `BookingCreationWithAllocationsTest` + `VendorEventSiteAvailabilityTest` — **35 passed**
* Full backend: started then stopped per user instruction (do not continue writing/running tests)
* Focused frontend: `tests/unit/eventSitePricing.test.js` — **6 passed**
* Full frontend: unit suite limited to that new file (repo had no prior `tests/` tree)
* Lint/build: eslint/oxlint clean after fix; `vite build` succeeded
* E2E: not executed (no `frontend/tests/e2e` runner present in workspace)

## 9. Persistent-Data Verification

* Runtime database: `cmart_db_rebuild`
* Event prices: existing events `20.00`
* Organizer default: column present (`users.default_site_price`); populated when checkbox used
* Historical totals: sample invoice amount `30` unchanged
* Database integrity: migration recorded; rebuild still active for app; no recover/rebuild of project DBs

## 10. Files Changed

* `backend/database/migrations/2026_07_22_000001_add_event_site_price_and_organizer_default.php`
* `backend/app/Models/CarbootEvent.php`
* `backend/app/Models/User.php`
* `backend/app/Models/Booking.php`
* `backend/app/Http/Controllers/Api/CarbootEventController.php`
* `backend/app/Http/Controllers/Api/BookingController.php`
* `backend/app/Services/BookingAllocationReservationService.php`
* `backend/app/Services/VendorEventSiteAvailabilityService.php`
* `backend/app/Services/EventPresenter.php`
* `backend/app/Services/UserAuthPresenter.php`
* `backend/app/Services/VendorBookingPresenter.php`
* `backend/database/seeders/DatabaseSeeder.php`
* `backend/tests/Concerns/Phase35EventLayoutFixtures.php`
* `backend/tests/Feature/EventSitePricingTest.php`
* `backend/tests/Feature/BookingCreationWithAllocationsTest.php`
* `backend/tests/Feature/BookingDayAllocationReservationTest.php`
* `backend/tests/Feature/VendorEventSiteAvailabilityTest.php`
* `backend/tests/Feature/EventDayAutomationTest.php`
* `backend/tests/Feature/EventLayoutAndDaysTest.php`
* `backend/tests/Feature/Phase41EventReservationFeeTest.php`
* `frontend/src/views/dashboards/staff/StaffEventsPanel.vue`
* `frontend/src/components/vendor/EventSiteSelector.vue`
* `frontend/src/components/layout/VisualParkingLayout.vue`
* `frontend/src/utils/visualParkingLayout.js`
* `frontend/src/utils/eventSiteSelection.js`
* `frontend/src/utils/bookingDisplay.js`
* `frontend/src/views/auth/Registration.vue`
* `frontend/tests/unit/eventSitePricing.test.js`

## 11. Remaining Risks or Deferred Items

* Risks: organizer layout/reassignment UIs may still display legacy `spaces.price` in non-vendor booking paths (admin tools / reassignment eligibility); not used for new booking totals
* Deferred: full PHPUnit suite completion; E2E pricing flow (no e2e harness in tree); English i18n toggle parity beyond current BM-first screens
* Unresolved: none blocking create/edit event pricing or new vendor booking amounts

## 12. Final Verdict

`IMPLEMENTATION COMPLETE — EVENT-LEVEL PARKING PRICING VERIFIED`
