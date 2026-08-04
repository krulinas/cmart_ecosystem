# Vendor Dashboard UX Navigation — Implementation Report

**Date:** 2026-08-04  
**Basis:** `report/vendor-dashboard-ux-navigation-audit.md` — Option C (clean architecture)  
**Scope:** Frontend-only. No backend/API/contract changes. No commit/push.

---

## 1. Final architecture

Vendor global navigation is now:

1. **Vendor Dashboard** → `/dashboard` (action-first home)
2. **Manage** → My Bookings, Event Passes, My Items, Customer Reservations
3. **Explore CMart** → Carboot Preview, Community, Events, **My Reservations** (buyer-side)
4. **Account** → Business Profile, Payment History, Insights, Logout

The eight-chip in-dashboard navigation row is **removed**, not restyled.

`/dashboard` keeps only: onboarding banner (when needed), greeting, focus card, announcements, ≤2 upcoming booking summaries, and booking/payment modals.

---

## 2. Routes added or changed

| Path | Name | Purpose |
|------|------|---------|
| `/dashboard` | `vendor-dashboard` | Slim home (unchanged path; new composition) |
| `/vendor/manage/bookings` | `vendor-manage-bookings` | Full bookings list |
| `/vendor/manage/event-passes` | `vendor-manage-event-passes` | Event passes + PDF |
| `/vendor/manage/items` | `vendor-manage-items` | My Items |
| `/vendor/manage/customer-reservations` | `vendor-manage-customer-reservations` | Seller reservations |
| `/my-reservations` | `my-reservations` | Buyer marketplace reservations |
| `/vendor/insights` | `vendor-insights` | Analytics & reports |
| `/vendor/payment-history` | `vendor-payment-history` | Full receipt/invoice history |
| `/profile` | `vendor-profile` | Unchanged canonical Business Profile |

**Auth meta:** All new routes use the same gate as today’s dashboard: `requiresAuth: true`, `roles: ['community']`.  
**Product note (unchanged):** Access is still community-role, not `isVendorUser`. Navbar vendor chrome still requires `isVendorUser`. No silent policy change.

**Legacy:** Visiting `/dashboard#<legacy-id>` redirects (`replace`) to the discrete route via `resolveVendorDashboardLegacyHash`.

---

## 3. Exact files changed

### Must / primary

- `frontend/src/config/navigation.js`
- `frontend/src/components/navigation/AppNavbar.vue`
- `frontend/src/router/router.js`
- `frontend/src/views/dashboards/VendorDashboard.vue`
- `frontend/src/utils/itemReservationDisplay.js` (`myReservationsPath`)
- `frontend/src/views/vendor/VendorCheckoutPage.vue`
- `frontend/tests/unit/vendorAnalyticsCurrentBookingCleanup.test.js`
- `frontend/tests/unit/vendorDashboardIaNavigation.test.js` *(new)*

### New views / helpers

- `frontend/src/utils/vendorDashboardLegacy.js`
- `frontend/src/components/vendor/VendorPageShell.vue`
- `frontend/src/views/vendor/VendorManageBookingsPage.vue`
- `frontend/src/views/vendor/VendorManageEventPassesPage.vue`
- `frontend/src/views/vendor/VendorManageItemsPage.vue`
- `frontend/src/views/vendor/VendorManageCustomerReservationsPage.vue`
- `frontend/src/views/vendor/MyReservationsPage.vue`
- `frontend/src/views/vendor/VendorInsightsPage.vue`
- `frontend/src/views/vendor/VendorPaymentHistoryPage.vue`

### May change (labels / CTA / copy)

- `frontend/src/components/vendor/VendorDashboardFocus.vue` (View Event Pass CTA)
- `frontend/src/components/VendorItemManager.vue` (label → My Items)
- `frontend/src/components/VendorItemReservationsPanel.vue` (label → Customer Reservations)
- `frontend/src/utils/vendorOnboarding.js` (active-state copy points to Manage/Account)

### Intentionally not deleted

- `frontend/src/components/VendorBusinessProfileManager.vue` — unused after this change; retained pending confirmation

### Not changed

- Backend controllers, models, migrations, seeders, middleware
- Organizer/admin `workspaceNav.js`
- Public reservation API contracts / `itemReservationsApi.js` method signatures

---

## 4. Components relocated

| Component | New home |
|-----------|----------|
| Full bookings UI | `VendorManageBookingsPage` |
| `VendorEventPassesPanel` | Manage → Event Passes |
| `VendorItemManager` | Manage → My Items |
| `VendorItemReservationsPanel` | Manage → Customer Reservations |
| `MyItemReservationsPanel` | `/my-reservations` (Explore); Community portal embed for non-vendors preserved |
| `VendorAnalyticsDashboard` | Account → Insights |
| `VendorHistoryReceipts` | Account → Payment History |
| Business Profile | `/profile` only (inline dashboard editor removed) |

---

## 5. Legacy-link compatibility

| Old hash | New path |
|----------|----------|
| `#vendor-my-bookings` | `/vendor/manage/bookings` |
| `#vendor-event-passes` | `/vendor/manage/event-passes` |
| `#vendor-reuse-listings` | `/vendor/manage/items` |
| `#vendor-item-reservations` | `/vendor/manage/customer-reservations` |
| `#my-item-reservations` | `/my-reservations` |
| `#vendor-business-profile` | `/profile` |
| `#vendor-analytics` | `/vendor/insights` |
| `#vendor-history-receipts` | `/vendor/payment-history` |

Also updated:

- `myReservationsPath()` → vendors `/my-reservations`
- Checkout back/success navigations → `/vendor/manage/bookings`
- `VENDOR_DASHBOARD_SECTION_LINKS` exported as `[]` (no chip nav)

---

## 6. Dashboard API calls before and after

### Before (`/dashboard` open)

- `GET /vendor/bookings`
- `GET /vendor/analytics/me`
- `GET /vendor/history-receipts`
- `GET /news`
- Child mounts: `/vendor/items`, `/vendor/item-reservations`, `/reservations/me`, `/vendor/event-passes`, `/vendor/business-profile`

### After (`/dashboard` open)

- `GET /vendor/bookings`
- `GET /news`
- (+ `auth.fetchMe` when token present, unchanged)

### Focus card without history/analytics

Booking payload already supplies:

- `invoice.payment_status` / `payment_status` → Pay Now via `canVendorProceedToDemoPayment`
- `invoice.amount` → amount for payment modal
- Event/site labels via booking fields

**Dropped** eager `history-receipts` and `analytics/me` on dashboard. Focus uses `paymentRecord: null` and booking-derived booth/event props.

**Trade-off documented:** Receipt-only cues that depended solely on history-row flags (`receipt_available`) are no longer primary CTAs; approved+paid now prefers **View Event Pass** → Manage Event Passes (no full pass list fetch on dashboard).

Relocated pages fetch their own data on visit only (no duplicate dashboard preload).

---

## 7. Tests and commands run

```bash
node --test tests/unit/vendorAnalyticsCurrentBookingCleanup.test.js tests/unit/vendorDashboardIaNavigation.test.js
npx eslint <changed frontend files> --max-warnings=0
npm run build
```

---

## 8. Results

| Check | Result |
|-------|--------|
| Unit tests (8) | **Pass** |
| ESLint on changed files | **Pass** (exit 0) |
| `npm run build` | **Pass** (chunk size warning only, pre-existing pattern) |

---

## 9. Browser verification and screenshots

**Not performed in this session.** Live browser verification at 1280/1440 and 375/390, console checks, and network waterfalls were unavailable / not run here. Do not treat UI/runtime verification as passed until manually confirmed in the running app (`npm run dev`).

Suggested manual checklist:

- [ ] `/dashboard` — no chips; focus + ≤2 bookings only
- [ ] Manage destinations load panels with empty/loading/error states
- [ ] Explore → My Reservations
- [ ] Account → Profile / Payment History / Insights
- [ ] Checkout returns to Manage Bookings
- [ ] Focus Pay Now opens payment modal
- [ ] Focus View Event Pass navigates to Event Passes; PDF download works there
- [ ] `/dashboard#vendor-reuse-listings` → `/vendor/manage/items`
- [ ] Vendor vs non-vendor navbar
- [ ] Network: dashboard does not call items/reservations/analytics/history on load

---

## 10. Remaining risks / unresolved questions

1. **Community role vs `isVendorUser`:** New routes share the historical community-role gate; product may later want vendor-only enforcement.
2. **`VendorBusinessProfileManager` orphaned** but not deleted — confirm unused, then remove in a follow-up.
3. **Focus payment without history:** Edge cases where unpaid/failed exists only on history rows (not booking invoice) may be less visible until Payment History is opened.
4. **Insights “Close”** returns to `/dashboard` (component still emits close).
5. **My Reservations for community visitors** remains on Community primary + portal embed; vendors use Explore → `/my-reservations` (intentional role split).
6. **Live browser QA** still required before considering the redesign production-verified.

---

*Implementation complete pending manual browser confirmation.*
