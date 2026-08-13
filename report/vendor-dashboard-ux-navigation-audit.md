# Vendor Dashboard UX & Navigation Architecture Audit

**Date:** 2026-08-04  
**Scope:** Read-only audit of the vendor-facing frontend. No implementation.  
**Primary sources:** `frontend/src/views/dashboards/VendorDashboard.vue`, `frontend/src/config/navigation.js`, `frontend/src/components/navigation/AppNavbar.vue`, `frontend/src/router/router.js`, related vendor panel components and tests.  
**Git state at audit time:** `main` @ `e7fd1f6`, working tree clean (`git status` reports nothing to commit). Conversation-start snapshot listed many previously untracked frontend/backend paths; those are **not** present as uncommitted changes now. Recent relevant commit: `e7fd1f6 Update the Vendor Analytics Dashboard`.

---

## 1. Executive verdict

1. **The dashboard is still an information-architecture problem, not primarily a styling problem.** Action-first work (`VendorDashboardFocus`, compact bookings, progressive analytics/receipts) is real, but `/dashboard` still mounts eight distinct workspaces on one scroll page.
2. **The eight-chip row should be removed (or reduced to zero primary chips).** Chips are mostly in-page scroll targets plus two disclosure toggles; they wrap, sit below content they navigate to, and duplicate Account → Business Profile. Code evidence shows no serious technical blocker to removing them.
3. **Hypothesis mostly holds.** Keep a short focus surface (next booking, approval/booth, actionable payment, one CTA, announcements, short booking summary). Move item prep, seller reservations, buyer reservations, profile editing, analytics, and full receipt history off the default dashboard body.
4. **Recommended model:** `Vendor Dashboard` · `Manage` · `Explore CMart` · `Account`, with seller tools under `Manage` and buyer “My Reservations” under `Explore CMart`. Prefer label **Manage** over Seller Tools / My Store (matches onboarding copy; latter labels are absent from product vocabulary).
5. **Redesign can stay frontend-only.** Existing APIs and components are reusable; risks are deep-link hashes, source-string unit tests, dual profile surfaces (`/profile` vs dashboard panel), and unnecessary eager API loads from always-mounted children.

---

## 2. Current architecture

### 2.1 Router

| Path | Name | Component | Auth |
|------|------|-----------|------|
| `/dashboard` | `vendor-dashboard` | `VendorDashboard.vue` | `requiresAuth`, `roles: ['community']` |
| `/profile` | `vendor-profile` | `VendorProfile.vue` | same |
| `/dashboard/checkout/:bookingId` | `vendor-checkout` | `VendorCheckoutPage.vue` | same |
| `/vendor-booking` | `vendor-booking` | `Registration.vue` | same |
| `/marketplace`, `/community`, `/calendar` | public explore | various | public |

**Note:** Dashboard gate is **community role**, not `isVendorUser`. Navbar vendor chrome requires `auth.isVendorUser` (`AppNavbar.vue`).

`scrollBehavior` in `router.js` scrolls to `to.hash` when present. Dashboard also implements its own hash sync (`syncSectionFromHash`).

### 2.2 Global navigation (vendor)

Configured in `navigation.js`, rendered by `AppNavbar.vue`:

| Slot | Source | Items |
|------|--------|-------|
| Primary link | `VENDOR_DASHBOARD_LINK` | Vendor Dashboard → `/dashboard` |
| Dropdown | `VENDOR_EXPLORE_MENU` | Carboot Preview, Community, Events |
| Dropdown | `VENDOR_ACCOUNT_MENU` | Business Profile → `/profile`, plus Logout button |

Desktop: dropdowns with click-outside / Escape. Mobile: accordion-style grouped lists under Explore / Account headings. No vendor “Manage” menu exists today.

### 2.3 In-dashboard chip navigation

`VENDOR_DASHBOARD_SECTION_LINKS` + two hard-coded buttons in `VendorDashboard.vue` (`data-testid="vendor-dashboard-section-nav"`).

| Chip label | Behaviour | Mechanism |
|------------|-----------|-----------|
| My Bookings | Scroll target | `scrollToDashboardSection('vendor-my-bookings')` |
| Event Passes | Scroll target | `#vendor-event-passes` |
| Item Preparation | Scroll target | `#vendor-reuse-listings` |
| Item Reservations | Scroll target | `#vendor-item-reservations` |
| My Reservations | Scroll target | `#my-item-reservations` |
| Business Profile | Scroll target | `#vendor-business-profile` |
| View insights | Component toggle + scroll | `showAnalytics` → mount `VendorAnalyticsDashboard` |
| Payment history | Component toggle + scroll | `showReceipts` → mount `VendorHistoryReceipts` (chip only if `hasPaymentHistoryEntry`) |

**Chip classification:** mixture of **scroll targets** (six) and **component toggles** (two). They are **not** Vue Router routes. Chip clicks **do not write** `route.hash` (only `activeSectionId` + `scrollIntoView`). Hash deep links **into** the page work via `watch(() => route.hash)` + `syncSectionFromHash`. `IntersectionObserver` updates the active chip while scrolling.

### 2.4 Page composition order (top → bottom)

1. `AppNavbar` (vendor variant when `isVendorUser`)
2. `VendorOnboardingBanner` (when onboarding ≠ `active`)
3. Greeting header + **Book a Space** / **Refresh**
4. `VendorDashboardFocus` (focus card)
5. Announcements (if any)
6. **My Bookings** section (compact; expandable full table)
7. **Chip row** (after bookings already on screen)
8. Always-mounted: `VendorItemManager`, `VendorItemReservationsPanel`, `MyItemReservationsPanel`, `VendorEventPassesPanel` + `VendorBusinessProfileManager`
9. Collapsed stubs / expanded panels: Analytics, Payment history
10. Modals: booking details, payment

### 2.5 Permission / role visibility

| Concern | Rule |
|---------|------|
| Route access | Any authenticated `community` user |
| Navbar vendor chrome | `role === 'community' && isVendorUser` |
| Community “My Reservations” in global nav | Community visitors only (`COMMUNITY_PRIMARY_LINKS`) |
| `MyItemReservationsPanel` on Community portal | `isAuthenticated && isCommunityMember && !isVendorUser` |
| Vendor dashboard always includes buyer reservations panel | Vendors only see it on `/dashboard` (not Community) |
| Payment history chip/section | Hidden when no records and no load error |

---

## 3. Current feature and route map

### 3.1 Inventory

| User-facing name | Source component | Parent | Route / anchor | API / data | Visibility | Duplicated elsewhere? | Reusable off-dashboard? | Backend impact if moved |
|------------------|------------------|--------|----------------|------------|------------|----------------------|-------------------------|-------------------------|
| Focus card | `VendorDashboardFocus.vue` | `VendorDashboard` | `/dashboard` (no id) | Props from parent bookings + payment + analytics booth fields | Always | Overlaps booking/status with My Bookings & passes | Yes (presentational) | None |
| Onboarding banner | `VendorOnboardingBanner.vue` | `VendorDashboard` | `/dashboard` | Derived from bookings via `resolveVendorOnboardingState` | When state ≠ `active` | CTAs overlap header/focus | Yes | None |
| Announcements | inline section | `VendorDashboard` | `/dashboard` | `GET /news` (top 2) | If rows exist | Full news on `/community` | Yes | None |
| My Bookings | inline section | `VendorDashboard` | `#vendor-my-bookings` | `GET /vendor/bookings` | Always | Focus card; checkout deep-links here | Logic tied to parent modals | None for move; keep modal wiring |
| Event Passes | `VendorEventPassesPanel.vue` | `VendorDashboard` | `#vendor-event-passes` | `GET /vendor/event-passes`; PDF via parent `GET /bookings/:id/pdf` | Always mounted | Pass actions also in booking modal | Yes | None |
| Item Preparation | `VendorItemManager.vue` | `VendorDashboard` | `#vendor-reuse-listings` | `GET/POST/PUT/DELETE /vendor/items` | Always mounted | Analytics “Manage Reuse Listings” scrolls here | Yes | None |
| Item Reservations (seller) | `VendorItemReservationsPanel.vue` | `VendorDashboard` | `#vendor-item-reservations` | `itemReservationsApi` → `/vendor/item-reservations*` | Always mounted | Organizer has separate admin panel | Yes | None |
| My Reservations (buyer) | `MyItemReservationsPanel.vue` | `VendorDashboard` **and** `CommunityPortal` | `#my-item-reservations`; helper `myReservationsPath()` | `GET /reservations/me` | Dashboard: always for vendors; Community: non-vendors only | Same component dual-hosted | Yes (already) | None |
| Business Profile (inline) | `VendorBusinessProfileManager.vue` | `VendorDashboard` | `#vendor-business-profile` | `GET/PUT /vendor/business-profile`, logo POST | Always mounted | **Account → `/profile`** uses different page/API (`/vendor/profile`) | Partially; two editors today | None to relocate; consolidate carefully |
| Analytics & Reports | `VendorAnalyticsDashboard.vue` | `VendorDashboard` | `#vendor-analytics` | Parent `GET /vendor/analytics/me`; report export `GET /vendor/analytics/report` | UI collapsed by default; **data still fetched on load** | None as route | Yes | None |
| Payment history | `VendorHistoryReceipts.vue` | `VendorDashboard` | `#vendor-history-receipts` | Parent `GET /vendor/history-receipts` | Entry hidden if empty; panel collapsed; **data still fetched on load** | Focus card promotes unpaid/failed for focus booking | Yes | None |
| Booking details / payment modals | `VendorBookingDetailsModal`, `VendorPaymentModal` | `VendorDashboard` | modal state | Booking/payment endpoints | On demand | Checkout page separate | Yes | None |
| Account Business Profile page | `VendorProfile.vue` | router | `/profile` | `GET /vendor/profile` + edit modal | Via Account menu | Duplicates purpose of dashboard profile panel | Already a page | None |

### 3.2 Chip vs global duplication

| Feature | Global nav | Chip / page |
|---------|------------|-------------|
| Business Profile | Account → `/profile` | Chip → `#vendor-business-profile` **and** inline panel |
| My Reservations | Community visitors: top-level → `/community#my-item-reservations`; **vendors: not in global nav** | Chip → dashboard hash |
| Explore destinations | Explore CMart | Not in chips |
| Bookings / passes / items / seller reservations | Not in global nav | Chips + full sections |

---

## 4. UX diagnosis

### 4.1 Hypothesis validation

| # | Hypothesis | Verdict | Evidence |
|---|------------|---------|----------|
| 1 | Dashboard combines too many workspaces | **Confirmed** | One route mounts bookings, passes, items, seller reservations, buyer reservations, profile, analytics, receipts |
| 2 | Chip row duplicates global nav and should be removed | **Partially confirmed; removal still recommended** | Only Business Profile truly duplicates Account. Chips still add a second nav layer, wrap, and scroll to sections that inflate the page. No code dependency requires keeping eight chips |
| 3 | Main dashboard should focus on next booking, status, payment, one CTA, announcements, short booking summary | **Confirmed as target; partially already built** | `VendorDashboardFocus` + announcements + compact bookings exist; remainder of page contradicts the target |
| 4 | Prep / reservations / profile / analytics / full history should leave main dashboard | **Confirmed** | Large always-mounted panels + empty states consume scroll; secondary for day-of carboot attention |
| 5 | Nav model: Dashboard · Manage · Explore · Account | **Feasible & recommended** | Explore/Account already exist; Manage is a natural home for seller ops; no conflicting vendor “Manage” menu |
| 6 | Proposed feature placement | **Mostly confirmed** | Buyer reservations already live on Community for visitors; vendor buyers need a non-dashboard home. Profile already has `/profile` |
| 7 | Rename Item Preparation → My Items | **Supported** | Panel manages vendor items; analytics copy uses “My Items Reused” / “reuse listings”; “Preparation” is jargon |
| 8 | Rename Item Reservations → Customer Reservations | **Supported** | Panel copy: holds on **your** listed items vs My Reservations (“holds **you** placed”) |
| 9 | Hide full receipt history; promote unpaid/failed into focus | **Already largely implemented; keep** | `showReceipts` + `hasPaymentHistoryEntry`; focus payment card marks unpaid/failed actionable with Pay Now |

### 4.2 Problems (evidence-based)

| Issue | Type | Evidence |
|-------|------|----------|
| Too many workspaces on one URL | **Information architecture** | Section inventory above |
| Chip row as second nav | **IA** (+ minor visual wrap) | `VENDOR_DASHBOARD_SECTION_LINKS` + toggles; `flex-wrap` |
| Chips below My Bookings scroll *up* for “My Bookings” | **IA / hierarchy** | Template order: bookings section then chip nav |
| Booking info in focus + list | **IA** (acceptable if list is short) | Intentional overlap; worsens when expanded table open |
| Dual Business Profile editors | **IA + component architecture** | `/profile` vs `VendorBusinessProfileManager` / different APIs |
| Item Reservations vs My Reservations naming | **IA / terminology** | Adjacent chips and panels; different roles |
| Competing primary CTAs | **IA / hierarchy** | Header Book a Space, focus CTA, onboarding CTAs, empty-state Book a Space, My Bookings “New booking” |
| Empty panels still occupy large vertical space | **IA + visual** | Item prep / reservations / passes empty states use `p-8`–`p-10` cards |
| Excessive scrolling | **IA** (primary), visual secondary | Always-mounted panels below fold |
| Eager API load for collapsed analytics/receipts | **Data/API-loading** | Parent `onMounted` always calls `fetchVendorInsights` + `fetchPaymentHistory` |
| Always-mounted child fetches even when unused | **Data/API-loading** | Each panel `onMounted(load*)` |
| Chip click does not update URL hash | **A11y / deep-link consistency** | `scrollToDashboardSection` only sets local state |
| Sticky header may obscure anchors | **A11y / visual** | `scroll-mt-24` only on analytics/receipts wrappers; other section ids lack it |
| Mobile: long page + chip wrap + hamburger without Manage | **IA + mobile** | Mobile mirrors global menus only |
| Important actions insufficiently exclusive | **IA** | Pay/revise exist in focus, but diluted by parallel CTAs and long page |

**Not the main problem:** pure card styling / gradients. Restyling chips alone would not fix workspace overload.

---

## 5. Technical coupling and risks

### 5.1 Safe to move without backend changes

All listed panels already call existing REST endpoints. Relocating or routing them is a frontend composition change.

### 5.2 Dashboard-level state dependencies

| Dependency | Detail |
|------------|--------|
| Focus payment | Joins `focusBooking` to `paymentRecords` from history API |
| Focus booth fields | From `vendorAnalytics.booth` even when analytics UI hidden |
| Booking modal / payment modal | Owned by `VendorDashboard` |
| Pass PDF download | Parent handler; panel emits `@download-pass` |
| Profile → insights refresh | `onBusinessProfileUpdated` → `fetchVendorInsights` |
| Items/reservations → insights | `@changed` handlers reload items/insights |
| Bookings refresh → passes | `eventPassesRef.loadPasses()` |

Moving panels requires re-homing these callbacks or accepting slightly less live cross-refresh.

### 5.3 Requests on dashboard load

**Parent (`onMounted`):** `auth.fetchMe` (if token), `GET /vendor/bookings`, `GET /vendor/analytics/me`, `GET /vendor/history-receipts`, `GET /news`.

**Always-mounted children:** `GET /vendor/items`, `GET /vendor/item-reservations`, `GET /reservations/me`, `GET /vendor/event-passes`, `GET /vendor/business-profile`.

**Collapsed UI still loads data:** Yes — analytics and payment history are fetched even when `showAnalytics` / `showReceipts` are false. Child panels always fetch.

**Moving to separate routes:** Can **reduce** duplicate load on dashboard; risk of duplicate calls only if dashboard still mounts summaries that hit the same endpoints *and* destination pages remount full panels without cache.

### 5.4 Deep links & tests

| Dependency | Location |
|------------|----------|
| `#vendor-my-bookings` | `VendorCheckoutPage.vue` (links + `router.push/replace`) |
| `#my-item-reservations` | `utils/itemReservationDisplay.js` → `myReservationsPath()` for vendors |
| Other hashes | Supported by `syncSectionFromHash` if bookmarked |
| Source-string unit test | `frontend/tests/unit/vendorAnalyticsCurrentBookingCleanup.test.js` asserts My Bookings, View insights, Payment history progressive disclosure strings in `VendorDashboard.vue` |

### 5.5 Authorization risks

No new backend permissions required for IA moves. Do **not** expose vendor item/reservation APIs on public routes. Keep `requiresAuth` + community role (or tighter vendor check if product later requires it). Buyer reservation panel on Explore must remain authenticated.

### 5.6 Removing chips: technical severity

**Low.** Chips are presentation + scroll helpers. Removing them does not break APIs. Preserve hash handlers or replace with real routes for checkout/reservation deep links.

---

## 6. Architecture alternatives

### Option A — Minimal change

**Nav:** Keep Dashboard · Explore · Account. Remove or collapse chip row to 0–2 optional links (“More tools”). Hide secondary panels behind a single “Booth tools” disclosure on `/dashboard` (lazy mount).

| | |
|--|--|
| Feature placement | Same URL; progressive disclosure |
| Files | `VendorDashboard.vue`, `navigation.js` (section links), possibly lazy `defineAsyncComponent` |
| New routes | No |
| Effort | S (1–3 days) |
| Risks | Still one mega-page; deep links awkward; limited cognitive gain |
| Pros | Fast; low regression |
| Cons | Does not fix IA; empty tools still compete when expanded |

### Option B — Moderate change

**Nav:** Add `Manage` dropdown on vendor navbar. Dashboard keeps focus + announcements + compact bookings only. Manage routes (or one Manage hub with hash/tabs) host items, customer reservations, bookings list/passes. Account gains Payment History + Insights. My Reservations → Explore (or Account).

| | |
|--|--|
| Feature placement | Split across Dashboard / Manage / Account / Explore |
| Files | `navigation.js`, `AppNavbar.vue`, `VendorDashboard.vue`, new thin view(s), `itemReservationDisplay.js`, checkout deep links, unit test |
| New routes | Yes (e.g. `/vendor/manage`, or discrete `/vendor/items`, etc.) **or** one hub with hashes |
| Effort | M (about 1–2 weeks) |
| Risks | Deep-link migration; dual profile until consolidated; navbar density on mobile |
| Pros | Matches busy-vendor mental model; reusable components; frontend-only |
| Cons | More routing/tests than Option A |

### Option C — Recommended clean architecture

**Nav:** `Vendor Dashboard` · `Manage` · `Explore CMart` · `Account`.

**Dashboard (`/dashboard`):** Greeting, optional onboarding, **one** focus card, announcements, short upcoming-booking summary (≤2), optional “today’s pass” teaser if approved — **no chip row**, no full item/reservation/profile/analytics/history panels.

**Manage** (new dropdown): My Bookings (full), Event Passes, My Items, Customer Reservations.

**Explore CMart:** existing + **My Reservations** (buyer role).

**Account:** Business Profile (`/profile` as single editor), Payment History, Insights/Analytics, Logout.

| | |
|--|--|
| Feature placement | See §8 |
| Files | See §9 |
| New routes | Yes — at least a Manage hub; prefer discrete paths for clarity |
| Effort | M–L (2–3 weeks including polish, deep-link redirects, tests) |
| Risks | Hash compatibility redirects; profile consolidation; updating source-string tests |
| Pros | Lowest cognitive load; clear seller vs buyer roles; aligns with 10-second next-action goal; chips unnecessary |
| Cons | More upfront IA work than A/B |

**Do not recommend** keeping eight chips with only styling fixes — no evidence that removal creates a serious technical problem.

---

## 7. Recommended target architecture

**Choose Option C**, phased through Option B patterns.

**Why:** Code already has an action-first focus card and progressive analytics/receipts, but the page still eagerly mounts every secondary workspace. The product context (busy carboot, limited attention) needs a **home for next action** and a **separate place for booth operations**. Explore/Account already exist; adding **Manage** matches onboarding language (“Manage your listings, receipts, and event passes”) better than inventing “Seller Tools” or “My Store” (neither appears in vendor UI vocabulary).

**Label choice:** **Manage** (recommended) > Seller Tools > My Store.

**Dashboard success criteria (≈10 seconds):** identify next event; see approval/booth; see if payment needed; one obvious CTA; skim announcements; optionally see 1–2 other bookings.

---

## 8. Current-to-proposed mapping

| Current feature | Current location | Proposed label | Proposed destination | Keep summary on dashboard? | Technical risk |
|-----------------|------------------|----------------|----------------------|----------------------------|----------------|
| Focus card | `/dashboard` | (keep) Next booking / status / payment | `/dashboard` | Yes — primary | Low |
| Announcements | `/dashboard` | Announcements | `/dashboard` (+ link Community) | Yes | Low |
| My Bookings (compact) | `/dashboard` + chip | My Bookings | Summary on Dashboard; full list under **Manage** | Yes (short) | Medium — modal/checkout deep links |
| Event Passes | `/dashboard` + chip | Event Passes | **Manage**; optional today-pass teaser on Dashboard | Optional teaser only | Low–medium (PDF handler) |
| Item Preparation | `/dashboard` + chip | **My Items** | **Manage > My Items** | No (badge/count later optional) | Low |
| Item Reservations | `/dashboard` + chip | **Customer Reservations** | **Manage > Customer Reservations** | No (optional count badge) | Low |
| My Reservations | `/dashboard` + chip | My Reservations | **Explore CMart > My Reservations** (reuse `MyItemReservationsPanel`) | No | Medium — update `myReservationsPath()` |
| Business Profile (inline) | `/dashboard` + chip | Business Profile | **Account > Business Profile** (`/profile` only) | No | Medium — consolidate APIs/UI |
| Analytics | `/dashboard` toggle | Insights | **Account > Insights** (or secondary entry) | No | Low — stop eager fetch on dashboard |
| Payment history | `/dashboard` toggle | Payment History | **Account > Payment History** | No — keep unpaid/failed in focus card | Low–medium — focus still needs payment row or booking invoice fields |
| Onboarding banner | `/dashboard` | (keep) | `/dashboard` until active | Yes | Low |
| Book a Space CTA | Header + many empties | Book a Space | One primary when no actionable focus CTA | Single primary | Low |
| Chip row | `/dashboard` | — | **Remove** | — | Low |

### Explicit placement for the three reservation/item functions

| Function | Go to |
|----------|--------|
| Item Preparation | **Manage → My Items** |
| Item Reservations | **Manage → Customer Reservations** |
| My Reservations | **Explore CMart → My Reservations** (buyer journey; mirrors community visitor primary link pattern) |

---

## 9. Exact files affected

### Must change

- `frontend/src/config/navigation.js` — add Manage menu; Explore My Reservations for vendors; retire or empty `VENDOR_DASHBOARD_SECTION_LINKS`
- `frontend/src/components/navigation/AppNavbar.vue` — render Manage; mobile groups
- `frontend/src/views/dashboards/VendorDashboard.vue` — slim page; remove chip nav and relocated mounts; retain focus/announcements/summary
- `frontend/src/router/router.js` — new Manage / Insights / Payment History routes (or hub)
- `frontend/src/utils/itemReservationDisplay.js` — `myReservationsPath()` for vendors
- `frontend/src/views/vendor/VendorCheckoutPage.vue` — post-checkout destinations if bookings leave bare `#vendor-my-bookings`
- `frontend/tests/unit/vendorAnalyticsCurrentBookingCleanup.test.js` — assertions tied to current dashboard strings/structure

### May change

- New view(s) under `frontend/src/views/vendor/` (Manage hub, items, reservations, passes, insights, payment history)
- `frontend/src/components/VendorItemManager.vue` — title “My Items”; optional `scroll-mt`
- `frontend/src/components/VendorItemReservationsPanel.vue` — title “Customer Reservations”
- `frontend/src/components/MyItemReservationsPanel.vue` — mount point on Explore destination
- `frontend/src/views/public/CommunityPortal.vue` — only if shared reservation route strategy changes
- `frontend/src/components/VendorBusinessProfileManager.vue` / `VendorProfile.vue` / `VendorProfileEditModal.vue` — profile consolidation. **`VendorBusinessProfileManager.vue` was later deleted in Phase 1 cleanup.**
- `frontend/src/components/vendor/VendorDashboardFocus.vue` — minor CTA/copy
- `frontend/src/utils/vendorOnboarding.js` — copy if dashboard no longer hosts listings/receipts
- `frontend/src/components/VendorAnalyticsDashboard.vue` — close/edit-profile navigation targets
- Hash redirect helpers (small util) for old `#vendor-*` bookmarks

### Should not change

- Backend controllers, models, migrations, seeders, permission middleware (for this IA redesign)
- `frontend/src/services/itemReservationsApi.js` contract
- Organizer/admin workspace nav (`workspaceNav.js`)
- Public marketplace reserve flow APIs

### Frontend-only confirmation

**Yes — redesign can remain frontend-only.** No controller/API/database/migration/model/seeder/permission change is required for the recommended IA, assuming existing endpoints continue to be called from new views.

**Flag only if consolidating profile:** `/vendor/profile` vs `/vendor/business-profile` are separate today; unifying editors is still frontend-preferable but may need product confirmation on which API is canonical — not a blocker for moving the dashboard panel off `/dashboard`.

---

## 10. Phased implementation plan

### Phase 0 — Prep (no user-facing IA change)

- Inventory hash consumers; add redirect map design (`#vendor-reuse-listings` → Manage My Items, etc.).
- Decide Manage route shape: one hub vs discrete paths.
- Confirm `/profile` as sole profile editor (deprecate inline manager on dashboard).

### Phase 1 — Dashboard slim-down (high UX value)

- Remove chip row.
- Stop mounting Item Manager, both reservation panels, Business Profile manager, full passes grid on `/dashboard`.
- Keep focus, announcements, compact bookings, modals.
- Lazy-load or defer analytics/history fetches until Account pages (focus payment: prefer booking invoice fields already on booking payload where possible; otherwise light payment fetch only when focus booking needs it).

### Phase 2 — Manage + Explore wiring

- Add `VENDOR_MANAGE_MENU` (or equivalent) to `navigation.js` + `AppNavbar`.
- Mount relocated panels on new routes.
- Move vendor My Reservations to Explore; update `myReservationsPath()`.
- Update checkout return URLs.

### Phase 3 — Account secondary tools

- Payment History and Insights under Account.
- Remove dashboard progressive stubs once Account pages exist.
- Profile consolidation polish.

### Phase 4 — Labels & cleanup

- Rename UI strings to My Items / Customer Reservations.
- Old hash redirects; update unit tests; remove dead section-link config.

---

## 11. Validation plan

| Area | Check |
|------|--------|
| Existing unit tests | Run `frontend/tests/unit/vendorAnalyticsCurrentBookingCleanup.test.js`; expect updates |
| Tests to update | Same file; any E2E/smoke referencing `dash-nav-*` or section ids (search repo before ship) |
| New tests worth adding | Nav config contains Manage items; `myReservationsPath` for vendor; dashboard does **not** mount `vendor-item-preparation-root` by default; hash redirects |
| Lint | Frontend eslint on touched files |
| Production build | `npm run build` in `frontend` |
| Route verification | `/dashboard`, Manage children, `/profile`, Explore My Reservations, `/vendor-booking`, checkout return |
| Desktop widths | 1280 / 1440 — no chip wrap (chips gone); focus readable in first viewport |
| Mobile widths | 375 / 390 — hamburger Manage groups; focus CTA thumb-friendly (`min-h-[44px]` retained) |
| Keyboard | Dropdowns Escape/click-outside; focus order; no orphan chip tab stops |
| Empty / loading / error | Each relocated panel; dashboard with zero bookings |
| Vendor authorization | Community visitor vs `isVendorUser` navbar; authenticated reservation pages |
| Booking & payment | Focus Pay Now → modal; Needs Revision → details; checkout → correct return |
| No duplicate API calls | Network panel: dashboard load should not hit items/reservations/profile/analytics/history unless required for focus |
| Accessibility | Sticky header vs scroll targets (`scroll-mt`); announcements region |
| Regression | Community visitor My Reservations on `/community` still works |

---

## 12. Open questions and blockers

1. **Manage route shape:** single `/vendor/manage#…` hub (closer to current mental model) vs discrete routes (`/vendor/items`, `/vendor/customer-reservations`, …)? Affects deep links and navbar item count.
2. **Profile canonical surface:** Keep both `/vendor/profile` and `/vendor/business-profile` editors temporarily, or force Account-only immediately?
3. **Event pass on dashboard:** Is a single “today’s pass” teaser required for check-in day, or is Manage → Event Passes enough?
4. **Dashboard access policy:** Route allows any `community` user; should `/dashboard` require `isVendorUser` (product/auth decision; not required for IA move)?
5. **Payment data for focus without full history fetch:** Confirm whether booking payload always includes enough invoice/payment fields when history endpoint is deferred.
6. **External bookmarks / docs:** Any printed or WhatsApp links to `#vendor-*` hashes beyond code references? (Code-known: checkout + `myReservationsPath`.)
7. **Unverified in this audit:** Live visual wrap of chips on specific vendor data densities (inferred from `flex-wrap` + eight controls); actual lighthouse/network waterfalls not measured in browser; backend response shapes for focus-only payment assumed from existing focus/`canVendorProceedToDemoPayment` usage.

---

*End of audit. Awaiting approval before any implementation.*
