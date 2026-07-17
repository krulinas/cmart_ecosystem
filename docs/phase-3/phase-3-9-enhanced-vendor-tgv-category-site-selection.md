# Phase 3.9 — Enhanced Vendor TGV Category Site Selection

## 1. Objective

Complete the vendor category-first booking experience while retaining Phase 3.7 backend enforcement and the separate Phase 3.8 Organizer override workflow.

## 2. Existing Phase 3.7 Contract

`GET /api/vendor-categories` is the selectable taxonomy. Availability requires `vendor_category_id`; booking creation revalidates event, category, row, site, Space, adjacency, occupancy, and all active EventDays inside the backend transaction. Successful writes freeze `vendor_category_id`, `category_label_snapshot`, and canonical `product_category`.

## 3. Category-First Flow

`Registration.vue` remains the real `/vendor-booking` flow because it already owns event selection, saved preferences, product details, availability, submission, and success redirect. Renaming or moving the complete workflow was not justified.

`VendorBookingCategorySelector.vue` loads API categories, visibly preselects the profile suggestion when available, permits another active category, and blocks site display until a category is selected.

## 4. Profile Suggestion Behaviour

The profile `vendor_category_id` is marked “Cadangan daripada profil anda”. It is a visible convenience, not an authorization or eligibility source. The vendor may select any active category returned by the API.

## 5. Row-Grouped Availability

Availability is requested from:

```text
GET /api/vendor/events/{event}/site-availability?vendor_category_id={id}
```

The UI consumes `rows[].id` and nested `rows[].sites`; it does not infer rows from site prefixes, row-label strings, or coordinates. The response now includes safe `description`, `display_order`, and `occupancy_status` (`reserved` or `confirmed`) while retaining `availability_status=occupied` for backward compatibility.

## 6. Site-State Presentation

The grid presents text and visual states for available, selected, occupied/reserved, confirmed, unavailable, and disabled sites. Incompatible rows are excluded by the backend. Removed stale selections are shown explicitly after refresh. No vendor identity, booking ID, allocation ID, lock, payment, override reason, or audit data is exposed.

## 7. Selection Rules

The existing helper remains the single frontend selection engine. It guides same-row selection using `event_layout_row_id`, same-Space selection using `space_id`, contiguous positions, and edge-only deselection. Backend reservation and category validation remain authoritative and reserve all active EventDays.

## 8. Selection Summary

The summary shows category, real row, labels, quantity, active EventDay count, Space type, unit price, and total. Total is the sum of selected site prices and is not multiplied by EventDay count, matching the current backend pricing contract.

## 9. Conflict Recovery

Availability requests use a monotonically increasing token; stale responses cannot overwrite current state. Category changes clear only site state and preserve product/event/profile fields. A `409` or layout change refreshes availability, prunes only invalid selections, lists removed labels, preserves category and product details, and requires explicit vendor review. There is no automatic resubmission.

## 10. Accessibility

Category choices use labelled radio semantics. Site buttons support keyboard activation, visible focus, `aria-pressed`, `aria-disabled`, descriptive labels, text status, and practical touch targets. Category changes, loads, and conflict recovery use live/status announcements. No state relies on colour alone.

## 11. Responsive Behaviour

The category and summary sections are single-column on mobile and two-column where space permits. Site grids scale from two to six columns without horizontal page overflow. Cards use minimum touch heights and wrapping metadata suitable for desktop, tablet, and mobile.

## 12. API Integration

The shared Axios client now supports `VITE_API_BASE_URL` while preserving the existing localhost default. Booking sends `event_id`, `vendor_category_id`, `event_site_ids`, `product_details`, and the transitional canonical `product_category` mirror. It does not send quantity, amount, total, `space_id`, row category, or synthetic site labels as authority.

## 13. Phase 3.8 Metadata Verification

`OrganizerBookingSiteReassignmentService` looks up an existing `(booking_id, event_day_id, event_site_id)` allocation before insert. Reactivation sets `reserved`, `active_lock=1`, refreshes `reserved_at`, clears `confirmed_at`, and clears `released_by`, `released_at`, and `release_reason`.

`OrganizerBookingSiteReassignmentTest::test_reassignment_back_reuses_released_rows_and_clears_release_metadata` verifies away-and-back reassignment, allocation ID reuse, one row per pair, cleared release metadata, and two retained reassignment audit entries. No service correction was required; focused suite result: 13 passed, 104 assertions.

## 14. `cmart_e2e_db` Architecture

Laravel uses ignored `backend/.env.e2e`, with tracked placeholders in `.env.e2e.example`. The complete migration chain runs only against `cmart_e2e_db`. The development database remains `cmart_db` and lacks Phase 3 tables by design.

## 15. E2E Safety Guard

`E2EDatabaseGuard` runs during application boot whenever `APP_ENV=e2e`. It requires MySQL and the exact approved database name `cmart_e2e_db`; it rejects `cmart`, `cmart_db`, production-like names, empty names, unknown names, wrong drivers, and wrong environments. Unit coverage: 8 passed.

## 16. Fixture Lifecycle

Commands:

```text
php artisan e2e:vendor-category-booking-fixtures create --json --env=e2e
php artisan e2e:vendor-category-booking-fixtures occupy --site=B02 --json --env=e2e
php artisan e2e:vendor-category-booking-fixtures status --json --env=e2e
php artisan e2e:vendor-category-booking-fixtures cleanup --json --env=e2e
```

The deterministic fixture creates isolated actors, a profile suggestion, two EventDays, Row A (Pre-loved / Thrift), Row B (Food & Beverages), six sites, one fixture Space, and one occupied site. Cleanup removes overrides, allocations, invoices, booking audits, bookings, layout audits, sites, rows, days, event, profile, tokens, users, and fixture Space in FK-safe order. Create starts with cleanup and cleanup is idempotent.

## 17. Browser Scenarios

`vendor.category-site-selection.spec.js` covers:

1. Category-first Food booking, real Row B, B01/B02, RM60 summary, success redirect, canonical booking snapshot, one invoice, and four day allocations.
2. Thrift A01/A02 selection followed by Food category change, preserving product details.
3. A competing B02 reservation producing `409`, stale-site pruning, no auto-submit, and zero partial vendor records.
4. Crafted Food-category payload with A01 rejected atomically.
5. Organizer and CMart Management denied; guest unauthorized.

Result: 5 passing in 47 seconds on the final headless Chrome run.

## 18. Test Results

- Frontend unit: 73 passed, 0 failed, 0 skipped.
- Phase 3.7 availability/category focused backend: 24 passed, 94 assertions.
- Phase 3.8 reassignment focused backend: 13 passed, 104 assertions.
- Full backend: 280 passed, 19 skipped, 0 failed, 1369 assertions.
- Production build: passed; existing large-chunk warning remains.
- Phase 3.9 targeted Oxlint/ESLint: 0 errors.
- Repository `npm run lint`: fails on 12 pre-existing Oxlint unused-variable errors before ESLint runs.

## 19. Persistent-Data Validation

After E2E cleanup, `cmart_e2e_db` contains only seven canonical migration-seeded categories; fixture-controlled users, profiles, events, days, rows, sites, spaces, bookings, allocations, invoices, audits, and overrides are zero.

Read-only `cmart_db` counts after validation remain: users 4, profiles 1, events 6, spaces 2, and zero days/sites/bookings/allocations/invoices/booking audits. Phase 3 tables remain missing. E2E fixture residue: no. Development database mutation: no.

## 20. Known Limitations

- Repository-wide lint remains blocked by unrelated pre-existing unused variables and E2E ESLint environment configuration; Phase 3.9 files pass targeted lint.
- The transitional `product_category` mirror remains.
- Global category FKs remain nullable pending final hardening.
- Existing older browser specs may still depend on legacy seeded data; the Phase 3.9 suite is isolated and deterministic.
- Public layout endpoint/UI, vendor self-override, paid reassignment, and price/quantity-changing reassignment remain out of scope.

## 21. Phase 3.10 Entry Criteria

The category-first UI, API-backed taxonomy, real-row grouping, responsive grid, backend-enforced selection rules, summary pricing, safe conflict recovery, Phase 3.8 metadata consistency, full backend suite, frontend unit/build, isolated browser scenarios, fixture cleanup, and `cmart_db` invariance are proven. Phase 3.10 may add public navigation-only category layout without exposing occupancy or reusing vendor availability semantics.
