# CMart Ecosystem Repository Cleanup — Phase 0 Audit + Phase 1 Execution

**Phase 0 date:** 13 August 2026 — investigation only.  
**Phase 1 date:** 13 August 2026 — high-confidence deletions executed. See **Phase 1 Execution Result** at the end of this document.

The purpose is NOT merely to reduce the number of files.

The goals are:

1. Identify code/files that are genuinely no longer used by the current CMart system.
2. Identify legacy, duplicated, temporary, generated, abandoned, or obsolete implementation left behind by previous development iterations.
3. Preserve everything that is still required directly or indirectly by current functionality.
4. Build a clear map showing where each major CMart functionality is implemented across frontend and backend.
5. Eventually remove dead code safely, but NOT during this first diagnostic phase.

---

# 1. Verdict on `UumDashboard.vue`

**Classification: DEAD — HIGH CONFIDENCE**

### A. Is the file reachable?

**No.** There is no runtime path that mounts this component.

Evidence:

- `frontend/src/router/router.js` **never imports** `UumDashboard.vue`.
- `/uum` is a **redirect-only** route. It does not render any component:

```js
path: '/uum',
redirect: () => {
  const auth = useAuthStore();
  if (auth.isAuthenticated && isOrganizerEquivalent(auth.role)) {
    return '/admin';
  }
  return '/management/login';
},
```

Source: `frontend/src/router/router.js` (approximately lines 208–216).

- `homeForUser()` never returns `/uum`. Management users go to `/admin`; community users go to `/dashboard` or `/community`.
- Repository search for `UumDashboard` as an import/string found **no consumers**.

Visiting `/uum` sends an organizer-equivalent user to `AdminDashboard.vue` at `/admin`, or an unauthenticated user to `/management/login`. The planned “UUM Oversight” page is never shown.

### B. What references it?

| Reference | File | Role |
| --- | --- | --- |
| Self only | `frontend/src/views/dashboards/UumDashboard.vue` | Placeholder UI; nav target `{ to: '/uum' }` |
| Path `/uum` (not the Vue file) | `frontend/src/router/router.js` | Redirect away from this page |
| Path `/uum` allowlist | `frontend/src/utils/postAuthRedirect.js` | Dead `auth.role === 'uum'` branch — **removed in Phase 1**. `/uum` router redirect kept. |
| Legacy role string `'uum'` | `frontend/src/utils/managementRoles.js`, `backend/app/Support/ManagementRole.php` | Normalizes `uum` → `organizer` |
| Historical ENUM / remap | several `backend/database/migrations/*` | Applied history; remaps `uum` → `organizer` |
| Tests asserting **no** `uum` users remain | `GovernanceAccessBoundaryTest.php`, `OrganizerBookingWorkflowTest.php`, `ManagementCapabilityTest.php` | Prove the role is gone, not that the dashboard is used |

Unrelated “UUM” strings (SOC UUM branding, `uum_student` / `uum_staff` **feedback** labels, theme `accentName: 'uum'`) are university/venue copy, not this dashboard.

### C. If only `UumDashboard.vue` were deleted?

**No runtime breakage.** Nothing imports it. `/uum` would still redirect. Tests would still pass because they never load this file.

### D. Is the UUM **role** still part of the current system?

**No as a live business role. Yes as legacy compatibility.**

Canonical DB roles (from `ManagementRole.php`): `community`, `organizer`, `cmart_management`, `super_admin`.

`uum` is explicitly deprecated and normalized to `organizer`. Migrations remapped existing `uum` users. Tests assert zero remaining `uum` rows.

**Do not delete** `ManagementRole::LEGACY_UUM`, `normalizeRole('uum')`, the `/uum` redirect, or role migrations. Those protect old sessions/bookmarks. Only the **Vue page** is unused.

### E. Classification

**DEAD — HIGH CONFIDENCE**

Placeholder “Planned Module” UI, never registered in the router, superseded by `/admin` + organizer workspace.

---

# Deliverable 1 — Cleanup Candidate Report

## 1. High-confidence deletion candidates (15) — **completed in Phase 1**

| File | Type | Classification | Referenced By | Purpose | Recommendation | Confidence |
| --- | --- | --- | --- | --- | --- | --- |
| `frontend/src/views/dashboards/UumDashboard.vue` | Vue page | DELETED — PHASE 1 | none (self only) | Planned UUM oversight workspace | **Deleted.** `/uum` redirect kept | High |
| `frontend/src/views/dashboards/management/ManagementReportsPanel.vue` | Vue panel | DELETED — PHASE 1 | docs called it “Retired stub” | Replaced by `CMartReportCentrePanel` | **Deleted** | High |
| `frontend/src/views/dashboards/boss/BossRevenuePanel.vue` | Vue panel | DELETED — PHASE 1 | none; `#revenue` redirects into Analytics Hub | Old boss revenue UI | **Deleted** | High |
| `frontend/src/views/dashboards/boss/BossWordCloudPanel.vue` | Vue panel | DELETED — PHASE 1 | none; `#analytics` redirects into Analytics Hub | Old boss word-cloud UI | **Deleted** | High |
| `frontend/src/components/ImpactDashboard.vue` | Vue component | DELETED — PHASE 1 | none | Public “Our Impact” metrics | **Deleted** | High |
| `frontend/src/components/management/StaffOperationalSnapshot.vue` | Vue component | DELETED — PHASE 1 | none | Live queue KPI cards | **Deleted UI.** `/organizer/operations-summary` kept | High |
| `frontend/src/components/BilingualHelpText.vue` | Vue component | DELETED — PHASE 1 | none (`InfoHelpTip.vue` remains) | BM/EN help block | **Deleted** | High |
| `frontend/src/components/VendorBusinessProfileModal.vue` | Vue component | DELETED — PHASE 1 | none | Old business-profile modal | **Deleted.** Live editor is `VendorProfileEditModal` | High |
| `frontend/src/components/VendorBusinessProfileManager.vue` | Vue component | DELETED — PHASE 1 | unit test asserts it is **not** in `VendorDashboard` | Inline profile editor | **Deleted** | High |
| `frontend/src/components/VendorEventInsights.vue` | Vue component | DELETED — PHASE 1 | none | Old vendor insights cards | **Deleted.** Live page uses `VendorAnalyticsDashboard` | High |
| `frontend/src/components/analytics/SurveyImportCard.vue` | Vue component | DELETED — PHASE 1 | none | Old survey upload card | **Deleted.** Live UI is `AnalyticsDataSourceManager` | High |
| `frontend/src/components/analytics/VendorsSalesPanel.vue` | Vue component | DELETED — PHASE 1 | none | Duplicate of survey charts | **Deleted.** Live UI is `SurveyResultsPanel` | High |
| `frontend/src/stores/counter.js` | Pinia store | DELETED — PHASE 1 | none | Vue starter demo store | **Deleted** | High |
| `frontend/src/constants/productCategories.js` | Config | DELETED — PHASE 1 | none; live copy is `utils/bookingDisplay.js` | Duplicate category list | **Deleted.** Empty `frontend/src/constants/` removed | High |
| `python_analytics/advanced_analytics.py` | Script | DELETED — PHASE 1 | none | Hardcoded `cmart_db` + TextBlob notebook | **Deleted** | High |

Evidence chain (typical for the Vue set):

`UumDashboard.vue` → no imports → router does not mount it → `/uum` redirects elsewhere → no tests load it → **DEAD — HIGH CONFIDENCE**

---

## 2. Requires manual review (~26)

| File | Type | Classification | Referenced By | Purpose | Recommendation | Confidence |
| --- | --- | --- | --- | --- | --- | --- |
| `backend/app/Http/Controllers/Api/InvoiceController.php` | API controller | POSSIBLY DEAD | `api.php` `invoices` resource only; **no frontend calls**, no dedicated tests | CRUD on invoices | Review: `Invoice` **model** is required for payment/PDF; this **controller** may be leftover | Medium |
| `backend/app/Http/Controllers/Api/EventRegistrationController.php` | API controller | POSSIBLY DEAD | `POST /api/events/{id}/register`; **no frontend caller, no tests** | Community RSVP under `max_slots` | Review: events still have `max_slots` UI in `StaffEventsPanel`; public RSVP UI is missing | Medium |
| `backend/app/Http/Controllers/AnalyticsController.php` | Web controller | POSSIBLY DEAD | `web.php` + `WebAnalyticsSecurityTest` | Blade admin analytics proxy | Parallel to Vue Analytics Hub; reachable but not used by SPA | Medium |
| `backend/resources/views/admin/analytics.blade.php` | Blade | POSSIBLY DEAD | `AnalyticsController::index` | Old HTML analytics dashboard | Same bundle as above | Medium |
| `backend/resources/views/welcome.blade.php` | Blade | POSSIBLY DEAD | `web.php` `GET /` | Laravel default splash | Vue landing is a separate origin; Laravel `/` is still this page | Medium |
| `backend/app/Http/Controllers/Api/ManagementReportsController.php` | API controller | POSSIBLY DEAD | route + governance test (Forbidden for CMart) | Thin wrap of `operationsSummary` | UI gone; endpoint still live for organizer | Medium |
| `backend/app/Http/Controllers/Api/EventSiteController.php` | API controller | POSSIBLY DEAD | tests (`EventSiteFoundationTest`, `EventLayoutAndDaysTest`); **no Vue client** | Phase 2 site CRUD | Superseded in UI by layout-site API; still a live, tested API | Medium |
| `backend/app/Services/EventSiteLayoutGenerator.php` | Service | POSSIBLY DEAD | `EventSiteController::generate` | Legacy bulk site generation | Domain audit already labels it Phase 2 / no row binding | Medium |
| `backend/app/Http/Controllers/Api/EventDayController.php` | API controller | POSSIBLY DEAD | tests; **no Vue calls** | Event-day CRUD | Days still exist in domain; UI may rely on auto-generation | Medium |
| `backend/app/Http/Middleware/EnsureVendorApproved.php` | Middleware | POSSIBLY DEAD | Kernel alias only; **no route uses `vendor.approved`** | Intentionally dormant | Keep until onboarding policy is decided | Medium |
| `backend/app/Providers/BroadcastServiceProvider.php` | Provider | POSSIBLY DEAD | commented out in `config/app.php` | Laravel broadcasting | Typical unused scaffold | Medium |
| `backend/app/Http/Middleware/TrustHosts.php` | Middleware | POSSIBLY DEAD | commented out in Kernel | Laravel default | Keep unless tightening HTTP host trust | Low |
| `backend/routes/console.php` | Console | POSSIBLY DEAD | `inspire` only; schedule commented | Laravel default | Harmless | Low |
| `backend/app/Console/Commands/CleanupLocalDummyBookings.php` | Artisan | POSSIBLY DEAD | none in app runtime | One-off local dummy cleanup | Keep as ops tool or archive | Medium |
| `backend/scripts/restore_demo_visibility.php` | Script | POSSIBLY DEAD | none | One-off media restore | Ops tool, not runtime | Medium |
| `backend/scripts/count_*.php` (4 files) | Scripts | POSSIBLY DEAD | none | Baseline row counters | Ops/debug | Medium |
| `backend/scripts/ensure_test_database.php` | Script | POSSIBLY DEAD | none in composer scripts | Test DB helper | Confirm vs `TestingDatabaseGuard` | Medium |
| `backend/scripts/remove_test_user_residue.php` | Script | POSSIBLY DEAD | none | Residue cleanup | Ops tool | Medium |
| `python_analytics/generate_analytics.py` | Script | POSSIBLY DEAD | writes tracked CSVs; not used by FastAPI | Offline CSV export | README documents FastAPI `main.py` only | Medium |
| `python_analytics/seed_wordcloud_data.py` | Script | POSSIBLY DEAD | none | Dummy booking/feedback seeder | Destructive demo tool | Medium |
| `python_analytics/feedback_word_cloud.csv` | Fixture | POSSIBLY DEAD | generated by `generate_analytics.py` | Static word-cloud export | FastAPI queries DB live | Medium |
| `python_analytics/vendor_word_cloud.csv` | Fixture | POSSIBLY DEAD | same | Same | Same | Medium |
| `frontend/src/services/organizerEventLayoutApi.js` `getSpaceCatalogue()` | Dead export | REMOVED — PHASE 1 | defined, never called | Comment: “boss tools only” | **Function removed.** `GET /spaces` kept | High for the export |
| `frontend/src/utils/postAuthRedirect.js` `auth.role === 'uum'` | Dead branch | REMOVED — PHASE 1 | live file | Allow `/uum` for a role that no longer exists | **Branch removed.** `/uum` router redirect kept | High for the branch |
| Kernel `'manager'` alias | Alias | POSSIBLY DEAD | Kernel only; no `middleware('manager')` | Duplicate of `'boss'` | Remove alias later | Medium |
| `/api/staff/*` compatibility routes | Routes | POSSIBLY DEAD | `api.php` comment “remove after external clients migrate”; **no frontend** | PR2 aliases | Confirm no external clients | Medium |
| Frontend e2e specs | Missing | POSSIBLY DEAD infra | `package.json` / `eslint.config.js` list many `tests/e2e/specs/*`; **directory is absent from git** | Selenium e2e | Restore or drop scripts; not a source-file delete | High that specs are gone |

The four `count_*.php` scripts are:

- `backend/scripts/count_baseline.php`
- `backend/scripts/count_dev_baseline.php`
- `backend/scripts/count_phase39_baseline.php`
- `backend/scripts/count_test_database.php`

---

## 3. Legacy but required

| File / area | Classification | Why keep |
| --- | --- | --- |
| All `backend/database/migrations/*` including role ENUM history | LEGACY BUT REQUIRED | Already-applied schema; `uum` remap is history, not dead code |
| `ManagementRole::LEGACY_UUM` + `normalizeRole('uum')` | LEGACY BUT REQUIRED | Old tokens/payloads |
| `frontend/src/router/router.js` `/uum` redirect | LEGACY BUT REQUIRED | Bookmarks |
| `LEGACY_ANALYTICS_HASH_REDIRECTS` (`#revenue`, `#analytics`) | LEGACY BUT REQUIRED | Old management hashes → Analytics Hub |
| `EnsureBossOnly` / `middleware('boss')` | LEGACY BUT REQUIRED | Name is legacy; still gates wordcloud, audit, spaces writes, profitability |
| `spaces.price` column (zeroed) | LEGACY BUT REQUIRED | Migration kept it for FKs |
| `CategoryMigrationAudit` model | LEGACY BUT REQUIRED | Migration audit + tests |
| `frontend/src/utils/vendorDashboardLegacy.js` | LEGACY BUT REQUIRED | Old `/dashboard#…` → discrete vendor routes |
| `/staff/verify-booking` and `/verify-booking` redirects | LEGACY BUT REQUIRED | Old pass-verify URLs |

---

## 4. Generated / build artifacts

| Path | Classification | Git |
| --- | --- | --- |
| `frontend/dist/` | GENERATED / BUILD ARTIFACT | **Not tracked** (`frontend/.gitignore` includes `dist`). Local build output was seen during search; do not treat as source. |
| `backend/vendor/`, `frontend/node_modules/` | dependencies | Ignored |
| `backend/public/build` | GENERATED | Ignored in `backend/.gitignore` |
| Laravel `storage/framework/*` | GENERATED | Ignored via nested gitignores |

No evidence that `dist` is accidentally committed.

---

## 5. Tests: keep vs obsolete

**Keep (VALID TEST)** — still protect current behaviour, including remaining legacy APIs:

- Role/capability: `ManagementCapabilityTest`, `GovernanceAccessBoundaryTest`
- Booking/payment/layout/reservations/reports: the Phase 3x–4x feature tests, `OrganizerBookingWorkflowTest`, `VendorDemoPaymentTest`, `EventReportV1*` tests, layout lifecycle tests
- `WebAnalyticsSecurityTest` — still matches live `web.php` analytics routes
- `EventSiteFoundationTest`, `EventLayoutAndDaysTest`, `EventDayAutomationTest` — still hit live organizer site/day APIs
- `StaffOperationsSummaryTest` — live `/organizer/operations-summary`
- Frontend unit tests (`eventSitePricing`, `canGenerateSitesForRow`, `postEventSummaryView`, `vendorDashboardIaNavigation`, `vendorAnalyticsCurrentBookingCleanup`) — they assert current source, including that `VendorBusinessProfileManager` is **not** on the dashboard
- E2E **fixture command** tests (`Phase45ItemReservationFixturesTest`, `E2EDatabaseGuardTest`) — commands still exist even though frontend e2e specs are missing

**Obsolete / proposed for deletion (2):**

| Test | Reason |
| --- | --- |
| `backend/tests/Unit/ExampleTest.php` | **Deleted in Phase 1.** Laravel scaffold: `assertTrue(true)` — no CMart behaviour |
| `backend/tests/Feature/ExampleTest.php` | **Kept in Phase 1.** Only asserts Laravel `GET /` welcome splash (200). Delete **together with** `welcome.blade.php` if that route is retired |

Do **not** delete `WebAnalyticsSecurityTest` or EventSite/EventDay tests just because the Vue UI moved on. Those APIs are still registered.

No test file exists for `UumDashboard.vue`.

---

## 6. Looked unused, actually required

| Item | Why it looked dead | Why it must stay |
| --- | --- | --- |
| `/uum` route | Same name as dead dashboard | Redirect for old links |
| `BossAnalyticsController` | “Boss” UI panels unused | `eventAnalyticsApi.getEventWordcloud` still calls `/boss/analytics/wordcloud/{source}?event_id=` |
| `invoices/booking.blade.php` | Invoice **controller** unused | `BookingController::generatePdf` loads this view |
| `Invoice` model / invoices table | CRUD API unused | Payment, demo-pay, PDF, analytics revenue |
| `Space` + `GET /spaces` | Space-type pricing removed | FK default physical site; `EventSitePricingTest` |
| `StaffOperationsController` | Snapshot Vue unused | `/organizer/operations-summary` + tests |
| `python_analytics/main.py` + `AnalyticsPythonClient` | Blade proxy looks old | Survey validate/aggregate + word clouds for Analytics Hub |
| `E2E*Fixtures` commands | Frontend `tests/e2e` folder missing | PHPUnit still uses them |
| `WorkspaceShell.vue` | Shared by dead UUM page | Live `AdminDashboard` |
| `config/locale.js` | `BilingualHelpText` unused (deleted Phase 1) | `InfoHelpTip.vue` uses `SHOW_BM_COPY` |
| `EnsureBossOnly` | Role `boss` removed | Middleware still used on analytics/audit routes |

---

# Deliverable 2 — Current CMart Function Map

Canonical management roles: **community (vendor/visitor)**, **organizer**, **cmart_management**, **super_admin**. There is **no live UUM role**. All management UIs share **`AdminDashboard.vue` at `/admin`**, gated by capabilities.

## Public

| Function | UI | Vue | Frontend API | Endpoint | Route | Controller | Service | Model | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Landing | `/` | `PublicLanding.vue` → `UpcomingEventsCarousel`, `EventDetailsModal`, `AppNavbar`, `SiteFooter` | `api.get('/events')`, `/news` | `GET /api/events`, `GET /api/news` | `api.php` | `CarbootEventController::publicIndex`, `NewsPostController::publicIndex` | `EventPresenter`, `NewsPostPresenter` | `carboot_events`, `news_posts` | `PublicContentIntegrityTest` |
| Event discovery | `/`, `/community`, `/calendar` | `CommunityPortal.vue`, `EventCalendar.vue` | same + calendar | same | same | same | same | same | `PublicContentIntegrityTest` |
| Public event view | modal on landing/calendar | `EventDetailsModal.vue` → `PublicEventLayoutSection`, `MediaImageGallery` | `publicEventLayoutApi` | `GET /api/events/{event}/layout`, `GET /api/events/{id}` | `api.php` | `PublicEventLayoutController`, `CarbootEventController::publicShow` | `PublicEventLayoutService` | `event_sites`, `event_layout_rows` | `PublicEventLayoutTest` |
| Become a Vendor | `/community#become-vendor` → `/vendor-booking` | `CommunityPortal.vue`, `Registration.vue`, `EventSiteSelector`, `VendorBookingCategorySelector` | `POST /bookings`, site-availability, preferences | community booking routes | `api.php` | `BookingController::store`, `VendorEventSiteAvailabilityController`, `UserBookingPreferenceController` | allocation + category services | `bookings`, `event_sites`, `user_booking_preferences` | `CommunityVendorBookingAccessTest`, `Phase37VendorCategoryEligibilityTest` |
| Share Your Voice | `/community#share-feedback` | `CommunityFeedback.vue` | `POST /feedback/submit`, `GET /feedbacks` | `api.php` | `FeedbackController` | — | `feedbacks` | `FeedbackModerationTest` |
| Reuse marketplace | `/marketplace` | `ReuseMarketplace.vue` | `GET /marketplace/items` | `api.php` | `MarketplaceController` | `MarketplaceItemPresenter` | `vendor_items`, `reuse_item_images` | `MarketplacePublicAccessTest` |

Community RSVP (`EventRegistrationController`) has **backend + `max_slots` on events**, but **no public register button** in `EventDetailsModal`.

## Authentication & roles

| Function | UI | Vue / JS | API | Controller | Support | Tests |
| --- | --- | --- | --- | --- | --- | --- |
| Public login | `/login` | `PublicLogin.vue`, `auth.js` | `POST /auth/login` | `AuthController` | Sanctum | e2e scripts named; specs missing from git |
| Management login | `/management/login` | `ManagementLogin.vue` | same | same | `isCmartWorker` | `GovernanceAccessBoundaryTest` |
| Registration | `/register` | `Register.vue` | `POST /auth/register` | `AuthController` | — | — |
| Google OAuth | login/register buttons | `config/auth.js` | `/auth/google`, callback | `AuthController` | Socialite | env-gated |
| Post-auth redirect | — | `postAuthRedirect.js`, `auth.homeForUser()` | `GET /auth/me` | `AuthController::me` | `UserAuthPresenter` | — |
| Roles | — | `managementRoles.js` | user `role` | — | `ManagementRole.php` | `ManagementCapabilityTest` |
| Capabilities | — | `managementCapabilities.js`, `useManagementAccess.js` | `governance_capabilities` on `/me` | — | `ManagementCapability.php` | `GovernanceAccessBoundaryTest` |

## Vendor

| Function | UI | Components | API wrapper | Endpoint | Controller | Service | Model | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Dashboard | `/dashboard` | `VendorDashboard.vue`, `VendorDashboardFocus`, `VendorOnboardingBanner` | `api` bookings | `GET /vendor/bookings` | `BookingController::mine` | `VendorBookingPresenter` | `bookings` | community access tests |
| Bookings list | `/vendor/manage/bookings` | `VendorManageBookingsPage`, `VendorBookingDetailsModal`, `WithdrawBookingModal` | same | vendor booking routes | `BookingController` | lifecycle services | `bookings`, allocations | withdrawal tests |
| Site selection | `/vendor-booking` | `Registration.vue`, `EventSiteSelector` | site-availability | `GET /vendor/events/{id}/site-availability` | `VendorEventSiteAvailabilityController` | `VendorEventSiteAvailabilityService` | `event_sites` | `VendorEventSiteAvailabilityTest` |
| Checkout / demo pay | `/dashboard/checkout/:id` | `VendorCheckoutPage.vue` | `POST .../demo-payment` | same | `BookingController::vendorDemoPayment` | invoice create | `invoices` | `VendorDemoPaymentTest` |
| Proof of payment | dashboard + payment history | `VendorPaymentModal.vue` | `POST .../submit-payment` | same | `BookingController::vendorSubmitPayment` | — | booking payment fields | named in eslint e2e list |
| Receipts / history | `/vendor/payment-history` | `VendorHistoryReceipts.vue` | `GET /vendor/history-receipts`, PDF | `VendorHistoryController`, `BookingController::generatePdf` | — | `invoices` | — |
| Event passes | `/vendor/manage/event-passes` | `VendorEventPassesPanel`, `VendorPassModal` | `/vendor/event-passes` | `VendorEventPassController` | `VendorEventPassService` | bookings | — |
| Items | `/vendor/manage/items` | `VendorItemManager` + form/details modals | `/vendor/items` | `VendorItemController` | `VendorItemPresenter` | `vendor_items` | `Phase41VendorItemFoundationTest` |
| Customer reservations | `/vendor/manage/customer-reservations` | `VendorItemReservationsPanel` | `/vendor/item-reservations` | `VendorItemReservationController` | reservation services | `item_reservations` | Phase 42–45 tests |
| My reservations | `/my-reservations` (+ community) | `MyItemReservationsPanel` | `/reservations/me` | `ItemReservationController` | same | same | same |
| Analytics | `/vendor/insights` | `VendorAnalyticsDashboard` | `/vendor/analytics/me` | `VendorAnalyticsController` | `VendorAnalyticsService` | bookings/profile | frontend unit cleanup test |
| Account | `/profile` | `VendorProfile.vue`, `VendorProfileEditModal` | `/vendor/profile`, `/vendor/business-profile` | `VendorProfileController`, `VendorBusinessProfileController` | `VendorProfilePresenter` | `users`, `vendor_business_profiles` | `CommunityVendorIntentTest` |

## Organizer (and super_admin override)

All via `/admin#…` in `AdminDashboard.vue`.

| Function | Hash / UI | Panel / components | API | Controller | Service | Model | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Workspace shell | `/admin` | `AdminDashboard`, `WorkspaceShell`, `useWorkspaceNav` | `/auth/me`, notifications | `AuthController`, `ManagementNotificationController` | — | `users`, `management_profiles` | governance tests |
| Booking approval | `#bookings` | `OrganizerBookingsPanel`, withdrawal/reassignment/attendance modals | `/organizer/bookings/registry`, `/bookings/{id}`, verify-payment | `BookingController`, `OrganizerBookingSiteAssignmentController` | reassignment/fingerprint services | `bookings`, `booking_audit_logs` | `OrganizerBookingWorkflowTest` |
| Payment verification | same panel | proof lightbox | `GET /bookings/{id}/payment-proof`, `PATCH .../verify-payment` | `BookingController` | — | booking + storage | — |
| Event management | `#events` | `StaffEventsPanel` | `/carboot-events` | `CarbootEventController` | observer → email job | `carboot_events` | — |
| Parking layout | `#layout` | `OrganizerEventLayoutPanel` + layout/* modals | `organizerEventLayoutApi` | `OrganizerEventLayout{Controller,Row,Site}` | `EventLayoutService`, `StandardEventLayoutGenerator` | rows/sites/audit | layout lifecycle tests |
| Site open / capacity | layout panel | `setOpenSites`, publish/unpublish | layout routes | `OrganizerEventLayoutController` | readiness/lock services | `event_sites` | readiness/lock tests |
| Item reservations | `#item-reservations` | `OrganizerItemReservationsPanel` | organizer item-reservation routes | `OrganizerItemReservationController` | lifecycle/charge | `item_reservations` | Phase 43–45 |
| Feedback moderation | `#feedback` | `StaffFeedbackPanel`, `FeedbackDetailModal` | `/organizer/feedbacks` | `FeedbackController` | — | `feedbacks` | `FeedbackModerationTest` |
| Pass verify | `/organizer/verify-booking/:id` | `StaffVerifyBooking.vue` | verify/check-in | `BookingPassVerificationController` | `VendorEventPassService` | bookings | — |
| Analytics Hub | `#event-analytics` | `OrganizerEventAnalyticsPanel`, survey/wordcloud components | `eventAnalyticsApi` + `/boss/analytics/wordcloud` | `OrganizerEventAnalyticsController`, `BossAnalyticsController`, survey import | `EventAnalyticsService`, Python client | `analytics_results`, `raw_survey_uploads` | survey/data-source tests |
| Report Centre | `#report-centre` | `OrganizerReportCentrePanel`, `PostEventSummaryView` | `reportWorkflowApi` | `OrganizerReportRequestController`, `OrganizerGeneratedReportController` | draft/publish/aggregator | `report_requests`, `generated_reports` | Event report tests |
| Audit (HQ only) | `#audit` | `BossAuditLogsPanel` | `GET /boss/audit-logs` | `AuditLogController` | `BookingAuditPresenter` | `booking_audit_logs` | governance access |

## CMart Management (venue)

Same `AdminDashboard.vue`, **without** carboot ops panels.

| Function | Hash | Panel | API | Notes |
| --- | --- | --- | --- | --- |
| Venue news | `#news` | `StaffNewsPanel` | `news-posts` resource | Organizer can also manage news |
| Reports | `#reports` | `CMartReportCentrePanel` | `/cmart/report-requests`, `/cmart/generated-reports` | Request + consume published PDFs |
| Bookings / layout / analytics | — | not mounted | Forbidden by capability | Those modules are **hidden** by capability, not stubbed |

**Boss:** not a current role. “Boss” in code = organizer/super_admin analytics middleware.

**Admin:** `AdminDashboard.vue` is the shared management shell, not a separate super-user product. `super_admin` gets organizer ops + audit + reserved HQ banner.

**UUM:** not implemented. Dashboard file **deleted in Phase 1**; branding “SOC UUM” remains on organizer theme. `/uum` still redirects to `/admin` or management login.

## Post-event reporting

| Step | UI | Vue | API | Controller | Service | Model / view | Tests |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Request | CMart `#reports` | `CMartReportCentrePanel` | `POST /cmart/report-requests` | `CmartReportRequestController` | `ReportRequestTransitionService`, `ExternalAlertSimulationService` | `report_requests` | report tests |
| Organizer handle | `#report-centre` | `OrganizerReportCentrePanel` | acknowledge / start / decline | `OrganizerReportRequestController` | same | same | same |
| Snapshot / draft | same | same | `POST /organizer/generated-reports`, regenerate, narratives | `OrganizerGeneratedReportController` | `ReportDraftService`, `PostEventSummaryAggregator`, `CmartReportSnapshotFilter` | `generated_reports` | `EventReportV1DataFoundationTest` |
| Preview | same | `PostEventSummaryView.vue` | GET generated report JSON | same | `PostEventReportPresentation` | snapshot JSON | `postEventSummaryView.test.js` |
| Publish / version | same | same | publish / revise | same | `ReportPublicationService` | status/version | report tests |
| PDF | download links | `reportWorkflowApi` PDF URLs | `.../pdf` | Organizer + CMart generated-report controllers | DomPDF | `reports/post_event_summary.blade.php` | `EventReportV1PdfVerificationTest` |
| Privacy filter | PDF/preview | presentation utils | — | — | `CmartReportSnapshotFilter` | — | `CmartReportSnapshotFilterTest` |
| Notifications | shell badge | `ReportNotificationActivity` | `/management/notifications` | `ManagementNotificationController` | `ReportNotificationReadService` | notification records | — |

---

# Deliverable 3 — Architecture / Orphan Observations

**Duplicated / parallel implementations**

- **Layout:** Phase 2 `EventSiteController` + `EventSiteLayoutGenerator` vs Phase 3.5 row-aware `OrganizerEventLayout*` + `StandardEventLayoutGenerator`. Vue uses only the latter.
- **Analytics UI:** Blade `admin/analytics.blade.php` + `web.php` proxy vs Vue Analytics Hub. (Boss revenue/wordcloud Vue panels **deleted in Phase 1**.)
- **Word cloud:** FastAPI live endpoints vs offline `generate_analytics.py` CSVs. Vue Boss word-cloud panel **deleted in Phase 1**.
- **Vendor profile:** live `/profile` + `VendorProfileEditModal`. Unused Manager/Modal Vue files **deleted in Phase 1**. Two backend profile APIs (`/vendor/profile` and `/vendor/business-profile`) are both still used.
- **Survey charts:** live `SurveyResultsPanel` (duplicate `VendorsSalesPanel` **deleted in Phase 1**).
- **Product categories:** live `bookingDisplay.js` (`constants/productCategories.js` **deleted in Phase 1**).
- **Staff vs organizer URLs:** `/organizer/*` canonical; `/staff/*` still registered.

**Old/new coexisting**

- Role strings: `staff`/`manager`/`uum`/`boss` still normalized.
- Booking statuses: `legacyBookingStatusLabel` for `Pending_Staff` / `Pending_Boss`.
- Survey import: canonical hard-delete plus several **410 deprecated** routes still declared in `api.php`.
- Directory names `staff/`, `boss/` under dashboards still use legacy role names. After Phase 1, `boss/` contains only live `BossAuditLogsPanel.vue`; `management/` contains only live `CMartReportCentrePanel.vue`.

**Disconnected / naming**

- `UumDashboard.vue` (deleted Phase 1) claimed it was “protected by the uum role”; the router never mounted it. `/uum` redirect remains.
- `EnsureBossOnly` does not check role `boss`; it checks carboot analytics capability.
- `Staff*` / `Boss*` / `Management*` prefixes no longer match canonical roles.
- `AdminDashboard.vue` is organizer + CMart + HQ in one file.
- `Registration.vue` is vendor **booking**, not account registration (`Register.vue`).

**Fragmentation**

- Management workspace is one route (`/admin`) with hash sections rather than nested routes.
- Vendor IA split into many pages; leftover dashboard-era Vue files listed in Phase 0 were removed in Phase 1.
- Report workflow is well layered; operational overview was retired from UI but not from API.

**Cursor-ish / one-purpose leftovers**

- `stores/counter.js` — **deleted in Phase 1**.
- `ManagementReportsPanel.vue` — **deleted in Phase 1**.
- `backend/scripts/count_*.php` one-off counters — still present (manual review).
- `advanced_analytics.py` — **deleted in Phase 1**.

**Directories**

- `frontend/src/views/dashboards/boss/` — live `BossAuditLogsPanel.vue` only (dead panels deleted Phase 1).
- `frontend/src/views/dashboards/management/` — live `CMartReportCentrePanel.vue` only (retired stub deleted Phase 1).
- `frontend/src/constants/` — **removed** after deleting its only file.
- `backend/scripts/` — entirely ops/debug, not runtime.
- `frontend/tests/e2e/` — **missing from the repo** while `package.json` still points at it.

**Hard to follow**

- “Who is Boss / Staff / Manager / UUM / Admin?” — all fold into organizer / cmart_management / super_admin, but folders and middleware names lag.
- Site creation: two HTTP APIs for the same `event_sites` table.
- Payment PDF uses `invoices.booking` view while invoice REST CRUD appears unused.

**Generated directories accidentally tracked**

- `frontend/dist/` is gitignored and not tracked. Local build output may exist on disk; do not treat it as handwritten source.

---

# Final summary (Phase 0 diagnosis; Phase 1 executed below)

1. **`UumDashboard.vue`:** DEAD — HIGH CONFIDENCE. Unreachable. `/uum` redirects to `/admin` or management login. **UUM is not a current role**; keep legacy normalize + redirect. **File deleted in Phase 1.**

2. **High-confidence deletion candidates:** **15 files — all deleted in Phase 1.**

3. **Requiring manual review:** **26 items** still waiting (controllers, parallel APIs, scripts, Python offline tools, dormant middleware, missing e2e specs). Plus `Feature/ExampleTest.php` + welcome blade bundle.

4. **Tests:**
   - `backend/tests/Unit/ExampleTest.php` — **deleted in Phase 1**.
   - `backend/tests/Feature/ExampleTest.php` — **kept**; tied to Laravel welcome route.
   Do not delete EventSite/EventDay/WebAnalytics tests until those APIs are retired with replacement coverage.

5. **Looked dead, proven necessary:** `/uum` redirect, `BossAnalyticsController` (wordcloud), invoice **PDF view/model**, `Space` catalogue, `StaffOperationsController`, Python FastAPI `main.py`, E2E fixture commands, role normalize for `uum`.

6. **Function map:** see Deliverable 2. Current product is public + vendor + shared `/admin` management. UUM/Boss-as-role are not current modules.

7. **Cleanup sequence:**
   1. ~~Delete the 14 unused frontend files + `counter.js` / duplicate constants~~ **Done Phase 1.**
   2. ~~Remove dead branches (`postAuthRedirect` `uum`, unused `getSpaceCatalogue`)~~ **Done Phase 1.**
   3. Decide: retire Blade analytics + `welcome.blade.php` **with** their tests.
   4. Deprecate Phase 2 site/day HTTP APIs only after confirming no clients and folding coverage into layout tests.
   5. Review RSVP (`EventRegistrationController`) and `InvoiceController` as product questions, not drive-by deletes.
   6. Last: scripts, Python offline seeders, Kernel aliases, `/staff/*` routes.
   7. **Never** delete migrations, role remaps, or `/uum` redirect in the same pass as Vue leftovers.

---

# Phase 1 Execution Result

**Date:** 13 August 2026  
**Scope:** High-confidence deletions and two dead fragments inside active files. Manual-review candidates were not touched.

## Files deleted (16)

### Frontend (14)

1. `frontend/src/views/dashboards/UumDashboard.vue`
2. `frontend/src/views/dashboards/management/ManagementReportsPanel.vue`
3. `frontend/src/views/dashboards/boss/BossRevenuePanel.vue`
4. `frontend/src/views/dashboards/boss/BossWordCloudPanel.vue`
5. `frontend/src/components/ImpactDashboard.vue`
6. `frontend/src/components/management/StaffOperationalSnapshot.vue`
7. `frontend/src/components/BilingualHelpText.vue`
8. `frontend/src/components/VendorBusinessProfileModal.vue`
9. `frontend/src/components/VendorBusinessProfileManager.vue`
10. `frontend/src/components/VendorEventInsights.vue`
11. `frontend/src/components/analytics/SurveyImportCard.vue`
12. `frontend/src/components/analytics/VendorsSalesPanel.vue`
13. `frontend/src/stores/counter.js`
14. `frontend/src/constants/productCategories.js`

Empty directory removed: `frontend/src/constants/` (had no remaining files).

Directories **kept** because they still contain live files:

- `frontend/src/views/dashboards/boss/` → `BossAuditLogsPanel.vue`
- `frontend/src/views/dashboards/management/` → `CMartReportCentrePanel.vue`
- `frontend/src/stores/` → `auth.js`

### Python analytics (1)

15. `python_analytics/advanced_analytics.py`

### Tests (1)

16. `backend/tests/Unit/ExampleTest.php`

`backend/tests/Feature/ExampleTest.php` was **not** deleted.

## Code fragments removed from active files

| File | Removed |
| --- | --- |
| `frontend/src/utils/postAuthRedirect.js` | `if (auth.role === 'uum') { return pathname === '/uum'; }` |
| `frontend/src/services/organizerEventLayoutApi.js` | unused export `getSpaceCatalogue()` |

Preserved: `/uum` router redirect, `ManagementRole::LEGACY_UUM`, frontend `normalizeRole('uum')`, migrations, `GET /spaces`.

## Candidates skipped because of new evidence

None. All 15 Phase 0 high-confidence files still had zero runtime consumers on the Phase 1 re-check.

`frontend/tests/unit/vendorAnalyticsCurrentBookingCleanup.test.js` still asserts `VendorDashboard.vue` does **not** contain the string `VendorBusinessProfileManager`. That is a negative source check, not an import of the deleted file. Test remains valid.

## Dangling references cleaned

Historical docs that still described deleted files as present were updated in place (no new markdown files):

- `report/vendor-dashboard-ux-navigation-implementation.md`
- `report/vendor-dashboard-ux-navigation-audit.md`
- `docs/generated-report-workflow-progress.md`

## Verification

- Repository-wide search: no remaining imports of deleted Vue files, `useCounterStore`, `getSpaceCatalogue`, or `advanced_analytics`.
- `frontend/src/router/router.js` still has `/uum` redirect; does not import `UumDashboard.vue`.
- `phpunit.xml` discovers Unit tests by directory; deleting `ExampleTest.php` leaves other Unit tests intact.
- Frontend production build: `npm run build` in `frontend/` — **succeeded** (vite v8.0.10, 256 modules transformed).

## Newly discovered cleanup candidates (not deleted)

| Candidate | Why |
| --- | --- |
| `useManagementAccess.js` `operationsSummaryEndpoint` | After deleting `StaffOperationalSnapshot.vue`, this export has no frontend consumer. Backend `/organizer/operations-summary` and `StaffOperationsController` remain in use by tests and must stay. |
| `frontend/src/views/dashboards/boss/` and `staff/` directory names | Legacy role naming; still contain live panels. Rename is architecture, not this phase. |

## Remaining manual-review work

Unchanged from Phase 0 section 2, except `getSpaceCatalogue()` and the `uum` post-auth branch which are done:

- Invoice REST controller vs required Invoice model/PDF
- Event RSVP controller with no public UI
- Blade analytics (`AnalyticsController`, `admin/analytics.blade.php`, `WebAnalyticsSecurityTest`)
- Laravel welcome (`welcome.blade.php`, `GET /`, `Feature/ExampleTest.php`)
- `ManagementReportsController` + operations-summary API (UI gone)
- Phase 2 `EventSiteController` / `EventSiteLayoutGenerator` / `EventDayController`
- Dormant `EnsureVendorApproved`, commented `BroadcastServiceProvider` / `TrustHosts`, `inspire` console route
- Ops scripts (`backend/scripts/*`, `CleanupLocalDummyBookings`)
- Python offline `generate_analytics.py`, `seed_wordcloud_data.py`, tracked CSVs
- Kernel `'manager'` alias, `/api/staff/*` compatibility routes
- Missing frontend e2e specs vs `package.json` scripts

