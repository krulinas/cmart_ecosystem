# Phase 3.10 — Public Category Layout and Visitor Navigation

## 1. Objective

Deliver a publication-gated, navigation-only event map for public visitors. The map uses canonical categories, real event layout rows, and physical site labels without exposing vendor identity, bookings, occupancy, payments, overrides, audits, or Organizer locks.

## 2. Repository continuity

- Branch: `main`, ahead of `origin/main` by one commit at start.
- Working tree at start: clean.
- Phase 3.6 commit: `6890d6e Update Organizer Layout Management UI`.
- Phase 3.7–3.9 implementation: present in the repository; Phase 3.9 commit `7f03c4d Enhanced Vendor Site Selection UX` includes the accepted category/booking/E2E continuity.
- Merge conflicts: none.
- Initial `git diff --check`: passed.
- No reset, clean, branch switch, commit, push, or `cmart_db` migration was performed.

## 3. Public API route

```http
GET /api/events/{event}/layout
```

The route follows the existing public event convention (`/api/events`) and requires no authentication. Guest and authenticated Community, Organizer, CMart Management, and Super Admin callers receive the same allowlisted response.

## 4. Publication fields and operations

The accepted ADR defines `carboot_events.public_layout_published_at` as the publication source of truth. These fields were missing before Phase 3.10, so additive migration `2026_07_18_000001_add_public_layout_publication_to_carboot_events_table` adds:

- `public_layout_published_at` nullable timestamp
- `public_layout_entrance_note` nullable text

Organizer/Super Admin operations:

```http
POST /api/organizer/events/{event}/layout/publish
POST /api/organizer/events/{event}/layout/unpublish
```

Publish requires `EventLayoutReadinessService::assess(...).public_ready === true`. Both actions write append-only `event_layout_audit_logs` entries. The migration was applied and rollback/re-forward validated only on `cmart_test` and `cmart_e2e_db`; it was never applied to `cmart_db`.

## 5. Publication rules

Current/future layout response requires:

1. Event exists.
2. `public_layout_published_at` is non-null.
3. Existing `EventLayoutReadinessService` reports `public_ready=true`.
4. The allowlisted public projection contains at least one row with at least one visible site.

Rows must be active, public, and not archived. Their category must be active, public, and not archived. Sites must be active, linked to the row, linked to the same event, and ordered deterministically. Unresolved, disabled, hidden-row, inactive-row, archived-row, and non-public-category records are omitted.

## 6. Event lifecycle behaviour

ADR-003 §K is authoritative:

- Current/future published event: HTTP 200, `historical=false`.
- Chronologically ended or `status=Closed` event with non-null publication timestamp: HTTP 200 static map, `historical=true`.
- Unpublished current, ended, closed, or public-cancelled event: HTTP 404 safe unavailable envelope.
- Missing/deleted event: HTTP 404 `PUBLIC_EVENT_NOT_FOUND`.
- There is no separate `Cancelled` event enum; public cancellation is represented by unpublishing, normally with `Closed`.

The existing public event list/detail continues to exclude ended and Closed events. The layout endpoint retains the ADR historical exception for previously published maps.

## 7. Unavailable contract

HTTP 404:

```json
{
  "layout_available": false,
  "event": {
    "id": 10,
    "name": "Carboot Weekend"
  },
  "rows": [],
  "message": "Susun atur acara belum diterbitkan.",
  "error": "PUBLIC_LAYOUT_NOT_AVAILABLE"
}
```

No readiness blocker, row/site failure ID, SQL detail, or internal error is returned.

## 8. Public projection

Allowlisted fields:

- Event: `id`, `name`, `status`, `starts_at`, `ends_at`
- Layout: `layout_available`, `published`, `historical`, `entrance_note`
- Category: `id`, `slug`, `label`, `description`, `display_order`, `row_count`, `site_count`
- Row: `id`, `label`, `description`, `display_order`, category, `site_count`, sites
- Site: `id`, `label`, `display_order`, `position_number`, `grid_row`, `grid_column`
- Space: `name` (`spaces.space_size`) only

The event schema has no canonical public venue field, so Phase 3.10 does not invent one.

## 9. Privacy boundary

`PublicEventLayoutService` is an allowlist presenter and never queries bookings, allocations, invoices, overrides, audits, or lock services. Deliberately excluded:

- User/vendor IDs, names, email, phone, profile, and business identity
- Booking and allocation IDs
- Reserved/confirmed/withdrawn/paid/availability state
- Invoice and payment state
- Product details
- Override reason, actor, snapshots, and status
- Booking/layout audit history
- `active_lock`, Organizer lock summaries, actor stamps
- Readiness blockers and unresolved identifiers

An internally confirmed site remains visible only as a physical label; its occupancy is not represented.

## 10. Ordering

- Categories: `vendor_categories.display_order`, then category ID.
- Rows: `event_layout_rows.display_order`, then row ID.
- Sites: `event_sites.display_order`, then `position_number`, then site ID.

Frontend normalization repeats these deterministic orders but does not infer categories or rows.

## 11. Caching

No new cache was added. Public reads reflect Organizer publication and layout changes immediately and cannot retain role-sensitive data.

## 12. Public event integration

`PublicEventLayoutSection.vue` is rendered once inside the shared `EventDetailsModal.vue`. The same section is therefore available from:

- Public landing event cards
- Public calendar event previews
- Community portal event previews

No duplicate event detail page or parallel public event system was created.

## 13. Category navigation

- Heading: `Cari Mengikut Kategori`
- Default: `Semua Kategori`
- Source: `response.categories` only
- Behaviour: client-side filtering without page reload
- Multiple rows per category: all matching rows remain visible
- Selected semantics: native buttons with `aria-pressed`
- Feedback: polite live announcement includes visible row count
- Mobile: wrapping controls with 44px minimum touch height

No category taxonomy is hardcoded.

## 14. TGV-style visitor map

Each row card shows:

- Real row label
- Canonical category label
- Optional public description
- Visible physical-site count
- Non-interactive site-label tiles
- Public-safe Space name when present

Site markers are semantic list items, not buttons. The section has no site selection, price, availability status, booking action, Organizer control, or vendor identity.

## 15. Public states

| State | Bahasa Melayu copy |
| --- | --- |
| Loading | `Memuatkan susun atur acara…` |
| Unpublished/unavailable | `Susun atur acara belum diterbitkan.` |
| Empty public projection | `Tiada susun atur awam tersedia buat masa ini.` |
| Empty selected category | `Tiada baris tersedia untuk kategori ini.` |
| Request failure | `Susun atur acara tidak dapat dimuatkan.` |
| Retry | `Cuba Lagi` |

## 16. Accessibility

- Existing event title remains the modal `h2`; public section uses `h3`; filters and row cards use logical subordinate headings.
- Category filters are keyboard-native buttons with `aria-pressed`, accessible group label, visible focus ring, and practical touch targets.
- Filter result changes use `aria-live=polite`.
- Loading/unavailable/empty states use status semantics; failures use alert semantics.
- Site labels use semantic lists and descriptive `aria-label` text.
- Meaning is conveyed by text, not colour alone.
- Reading order follows event context → category controls → row cards → sites.

## 17. Responsive design

- Category controls wrap without horizontal scrolling.
- Row cards use a single-column flow.
- Site grids adapt from two columns to three, four, and five.
- Tiles have `min-width: 0` and practical minimum height.
- Headless mobile E2E verifies controls, tile size, and document overflow.

## 18. Backend tests

`backend/tests/Feature/PublicEventLayoutTest.php` covers:

- Guest and all authenticated roles receive identical output.
- Deterministic category/row/site ordering.
- Multiple rows under one category.
- Hidden, inactive, archived, and unresolved exclusion.
- Non-public/inactive category publication failure.
- Unpublished, empty, deleted, upcoming, active, ended, and Closed event behaviour.
- Internal booking, paid invoice, confirmed allocation, active override, audit, and vendor PII non-disclosure.
- Organizer publish readiness, publish, unpublish, and publication audit.

Focused result: 8 passed, 72 assertions.

## 19. Frontend tests

`frontend/tests/unit/publicEventLayout.test.js` verifies:

- Allowlist normalization ignores injected booking, occupancy, override, and lock fields.
- Deterministic ordering.
- API-sourced category filtering and multiple matching rows.
- Shared event modal integration.
- Required states and BM copy.
- `Semua Kategori`, selected semantics, live announcements, non-interactive sites.
- No booking authority, Organizer controls, occupancy rendering, or price.
- Responsive classes and practical touch targets.

Full frontend unit result: 80 passed, 0 failed, 0 skipped.

## 20. E2E fixture architecture

Commands:

```text
php artisan e2e:public-layout-fixtures create --json --env=e2e
php artisan e2e:public-layout-fixtures status --json --env=e2e
php artisan e2e:public-layout-fixtures cleanup --json --env=e2e
```

Fixture data:

- Upcoming published event with two active EventDays
- Public Row A (Pre-loved / Thrift) and Row B (Food & Beverages)
- Private active row and site
- Disabled unresolved legacy site
- Confirmed internal allocations on a visible site
- Paid invoice
- Vendor business profile with private name, email, phone, and profile text
- Active category override with private reason
- Booking audit
- Public-ready unpublished event
- Previously published ended event
- Previously published Closed event

Create starts by purging prior marker data. Cleanup is idempotent and follows FK-safe order: overrides → allocations → invoices/audits → bookings → layout audits → sites → rows → days → events → profile/tokens/users → fixture Space.

## 21. Browser scenarios

`public.event-layout.spec.js`:

1. Guest published layout: pass.
2. Food category filter and `Semua Kategori` restore: pass.
3. Private row/unresolved site/vendor PII/occupancy/override exclusion: pass.
4. Public event with unpublished layout safe state: pass.
5. Mobile viewport controls/grid/overflow: pass.
6. Ended/Closed historical contract and unpublished lifecycle: pass.

Final run: 6 passing in 22 seconds.

## 22. Test database safety

- PHPUnit: `APP_ENV=testing`, MySQL, exact database `cmart_test`, `TestingDatabaseGuard` active.
- Browser: `APP_ENV=e2e`, MySQL, exact database `cmart_e2e_db`, `E2EDatabaseGuard` active.
- `cmart_e2e_db` clean baseline after E2E: seven seeded categories and zero fixture-controlled users, profiles, events, days, rows, sites, spaces, bookings, allocations, invoices, audits, and overrides.
- `cmart_db` read-only baseline remained users 4, profiles 1, events 6, spaces 2, and zero event days/sites/bookings/allocations/invoices/booking audits. Phase 3 tables remain missing.
- Development database mutated: no.

## 23. Compatibility and full validation

- Organizer layout focused: 29 passed, 137 assertions.
- Phase 3.7 eligibility isolated rerun: 10 passed, 49 assertions.
- Phase 3.8 reassignment: 13 passed, 104 assertions.
- Allocation/payment/withdrawal/attendance compatibility: passed.
- Full backend: 288 passed, 19 skipped, 0 failed, 1441 assertions, 329.20 seconds.
- Full frontend unit: 80 passed.
- Production build: passed on retry; existing >500 kB chunk warning remains.
- Phase 3.10 targeted Oxlint/ESLint: 0 errors.
- Repository-wide lint: blocked by 12 pre-existing Oxlint unused-variable errors outside Phase 3.10.
- `git diff --check`: passed.

Observed validation retries:

- One combined focused backend sequence saw the known global-count isolation symptom in `Phase37VendorCategoryEligibilityTest::test_stale_row_category_change_rejected`; the isolated suite immediately passed, and the final full suite passed.
- First production build attempt hit a transient Windows `EPERM` while clearing `dist`; immediate retry passed.
- E2E preflight initially required valid credentials even for a public spec; the fixture runner now supplies isolated fixture credentials and classifies the public spec correctly.
- Browser helper gained an explicit wait for asynchronously loaded event cards.
- Mobile assertion now compares component width to actual Chrome inner width rather than the requested outer-window width.

## 24. Migration and rollback boundary

The additive Phase 3.10 migration was rolled back with `--step=1` and re-applied successfully on both `cmart_test` and `cmart_e2e_db`. The Phase 3.4A rollback boundary was not crossed. No earlier migration was rolled back.

## 25. Known limitations

- Existing public event listing/detail still excludes ended and Closed events; historical layout remains available through its ADR-defined API URL.
- Public venue is omitted because the current event schema has no canonical venue field.
- No complex cache, URL-synchronized category filter, or standalone layout route was added.
- Repository-wide lint debt remains 12 pre-existing unused-variable errors.
- Transitional category string mirrors and nullable Phase 3 FKs remain.

## 26. Phase 3.11 entry criteria

Met:

- Public API and explicit publication source of truth
- Readiness-gated current publication
- Historical ended/Closed contract
- Privacy allowlist and regression tests
- Public event modal integration
- API-sourced category navigation
- Real row/site labels without occupancy
- Responsive and accessible UI
- Backend/frontend/build/browser validation
- Deterministic fixture cleanup
- `cmart_db` invariance

Phase 3.11 may proceed with hardening, migration readiness, skip-fixture repair, legacy-field retirement planning, final NOT NULL verification, and release closure. It must not add item reservation, walk-in vendor flows, ESG analytics, generated reports, or other Phase 4 scope.
