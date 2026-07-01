# 09 — Frontend Component Map

> **Bahasa:** Bahasa Melayu / Manglish | **Framework:** Vue 3 + Pinia + Vue Router

---

## Apa modul ini buat (What this module does)

Dokumen ini map **struktur frontend Vue SPA** — routes, pages, components, stores, dan utils. Frontend consume Laravel API via axios/fetch dengan Sanctum token.

**Entry point:** `frontend/src/main.js` → `App.vue` → `<router-view />`

---

## Fail penting (Important files)

### Core bootstrap

| Fail | Fungsi |
|------|--------|
| `frontend/src/main.js` | Create app, Pinia, router |
| `frontend/src/App.vue` | Root shell + IdleSessionWatcher |
| `frontend/src/router/router.js` | Route definitions + guards |

### State management (Pinia stores)

| Store | Path | Fungsi |
|-------|------|--------|
| auth | `stores/auth.js` | Login, token, role helpers |
| bossPreview | `stores/bossPreview.js` | Manager view-as-staff |

### Config

| Fail | Fungsi |
|------|--------|
| `config/navigation.js` | Public & vendor nav links |
| `config/workspaceNav.js` | Management sidebar sections |
| `config/managementWorkspaceTheme.js` | Tier UI themes |

### Composables

| Composable | Fungsi |
|------------|--------|
| `useManagementAccess.js` | Staff vs manager permissions |
| `useWorkspaceNav.js` | Build admin nav URLs |
| `useSectionCache.js` | Panel load caching |

### Utils

| Util | Fungsi |
|------|--------|
| `managementRoles.js` | Role normalize & helpers |
| `bookingDisplay.js` | Booking status labels |
| `vendorPass.js` | Pass QR & badges |
| `vendorBooking.js` | Booking navigation |
| `vendorCatalog.js` | Reuse item helpers |
| `vendorReport.js` | Analytics export |

---

## Route → Page map

### Zone 1: Public (tak perlu login)

| Route | Page component |
|-------|----------------|
| `/` | `views/public/PublicLanding.vue` |
| `/community` | `views/public/CommunityPortal.vue` |
| `/login` | `views/auth/Login.vue` |
| `/register` | `views/auth/Register.vue` |
| `/marketplace` | `views/public/ReuseMarketplace.vue` |
| `/calendar` | `components/EventCalendar.vue` |

### Zone 2: Vendor hub

| Route | Page component | Meta |
|-------|----------------|------|
| `/dashboard` | `views/dashboards/VendorDashboard.vue` | roles: community |
| `/profile` | `views/vendor/VendorProfile.vue` | roles: community |
| `/vendor-booking` | `views/auth/Registration.vue` | + vendorApproved |

### Zone 3: Management

| Route | Page component |
|-------|----------------|
| `/admin` | `views/dashboards/AdminDashboard.vue` |
| `/staff/verify-booking/:id` | `views/staff/StaffVerifyBooking.vue` |

### Zone 4: UUM

| Route | Page component |
|-------|----------------|
| `/uum` | `views/dashboards/UumDashboard.vue` |

---

## Component map by feature

### Navigation & session

| Component | Fungsi |
|-----------|--------|
| `navigation/AppNavbar.vue` | Main navbar (public/vendor variants) |
| `IdleSessionWatcher.vue` | Auto logout on idle |

### Booking (vendor)

| Component | Used in |
|-----------|---------|
| `VendorBookingDetailsModal.vue` | VendorDashboard |
| `WithdrawBookingModal.vue` | VendorBookingDetailsModal |
| `Registration.vue` | /vendor-booking page |

### Payment & receipts

| Component | Used in |
|-----------|---------|
| `VendorPaymentModal.vue` | VendorHistoryReceipts |
| `VendorHistoryReceipts.vue` | VendorDashboard |

### Event passes

| Component | Used in |
|-----------|---------|
| `VendorEventPassesPanel.vue` | VendorDashboard |
| `VendorPassModal.vue` | VendorEventPassesPanel |

### Vendor profile & business

| Component | Used in |
|-----------|---------|
| `VendorProfileEditModal.vue` | VendorProfile page |
| `VendorBusinessProfileManager.vue` | VendorDashboard |
| `VendorAnalyticsDashboard.vue` | VendorDashboard |

### Reuse marketplace (vendor manage + public browse)

| Component | Used in |
|-----------|---------|
| `VendorItemManager.vue` | VendorDashboard |
| `VendorItemFormModal.vue` | VendorItemManager |
| `VendorItemDetailsModal.vue` | VendorItemManager |
| `ReuseItemImageGallery.vue` | Item displays |
| `MarketplaceItemDetailsModal.vue` | ReuseMarketplace page |

### Public community features

| Component | Used in |
|-----------|---------|
| `CommunityFeedback.vue` | CommunityPortal |
| `EventCalendar.vue` | /calendar route |
| `EventDetailsModal.vue` | EventCalendar |
| `EventCalendarHoverCard.vue` | EventCalendar |
| `NewsDetailsModal.vue` | CommunityPortal |

### Management panels (lazy loaded dari AdminDashboard)

| Panel | Path |
|-------|------|
| StaffBookingsPanel | `views/dashboards/staff/StaffBookingsPanel.vue` |
| StaffFeedbackPanel | `views/dashboards/staff/StaffFeedbackPanel.vue` |
| StaffEventsPanel | `views/dashboards/staff/StaffEventsPanel.vue` |
| StaffNewsPanel | `views/dashboards/staff/StaffNewsPanel.vue` |
| StaffToolsPanel | `views/dashboards/staff/StaffToolsPanel.vue` |
| BossRevenuePanel | `views/dashboards/boss/BossRevenuePanel.vue` |
| BossWordCloudPanel | `views/dashboards/boss/BossWordCloudPanel.vue` |
| BossAuditLogsPanel | `views/dashboards/boss/BossAuditLogsPanel.vue` |

### Management shared UI

| Component | Fungsi |
|-----------|--------|
| `management/ManagementSectionLoader.vue` | Loading spinner |
| `management/ManagementEmptyState.vue` | No data state |
| `management/ManagementKpiCard.vue` | KPI display |
| `management/ManagementStatusChip.vue` | Status badge |

### Staff check-in

| Component | Fungsi |
|-----------|--------|
| `StaffVerifyBooking.vue` | QR verification page |

### Shared utilities

| Component | Fungsi |
|-----------|--------|
| `MultiImageUploadField.vue` | Staff events/news image upload |
| `MediaImageGallery.vue` | Image gallery display |
| `StaffOperationalSnapshot.vue` | Tier 1 staff tools panel — operational counts |
| `ImpactDashboard.vue` | Manager/HQ tools panel (not shown to Tier 1 staff) |

### Orphan components (wujud, tidak di-import)

| Component | Nota |
|-----------|------|
| `VendorBusinessProfileModal.vue` | Dead code — guna VendorBusinessProfileManager |
| `VendorEventInsights.vue` | Dead code |

---

## Workflow step-by-step (frontend boot)

### 1. App start
```
main.js → createApp → use(pinia) → use(router) → mount('#app')
```

### 2. Route navigation
```
User click link → router.push()
  → beforeEach guard
    → fetch /auth/me if needed
    → check roles / vendorApproved
    → allow or redirect
  → render page component
```

### 3. API call pattern
```
Component mount → call API with Bearer token from auth store
  → render data or show error toast
```

### 4. Admin hash navigation
```
Click sidebar → navigate /admin#bookings
  → AdminDashboard read hash
  → lazy import correct panel component
```

---

## Access control / permission notes

| Layer | Mechanism |
|-------|-----------|
| Router | `meta.requiresAuth`, `meta.roles`, `meta.vendorApproved` |
| Admin hash | `MANAGER_ONLY_HASHES` blocked for staff |
| Component | `useManagementAccess()` hide buttons |
| API | Backend middleware (final authority) |

---

## Apa nak cakap kalau lecturer tanya

> "Frontend kami Vue 3 SPA dengan single router file. Pages dalam views/, reusable components dalam components/. Management dashboard guna hash-based section switching instead of nested routes — ini simplify routing tapi maintain deep links. Pinia store handle auth state. Role guards ada di router level dan component level untuk defense in depth. Lazy loading digunakan untuk management panels supaya initial bundle kecil."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Token stale | User sees logged-in UI but API 401 — IdleSessionWatcher helps |
| Hash not updating | Browser back button behavior — scrollBehavior in router |
| Component not imported | Orphan .vue files confuse developers |
| API base URL | Dev proxy config — check vite.config |
| E2E test ids | Components need `data-testid` for Selenium |

---

## Macam mana verify ia berfungsi

```bash
cd frontend
npm install
npm run dev
# Buka http://localhost:5173

# Build check
npm run build
```

Navigate semua zones manually:
- [ ] Public pages load tanpa login
- [ ] Login redirect betul per role
- [ ] Vendor dashboard sections render
- [ ] Admin panels switch via hash
- [ ] Manager-only sections hidden for staff

---

## Architecture diagram

```
main.js
  └── App.vue
        ├── IdleSessionWatcher
        └── router-view
              ├── PublicLanding / CommunityPortal / ...
              ├── VendorDashboard
              │     ├── VendorBookingDetailsModal
              │     ├── VendorEventPassesPanel
              │     ├── VendorHistoryReceipts
              │     └── ...
              ├── AdminDashboard
              │     └── WorkspaceShell
              │           ├── StaffBookingsPanel
              │           ├── StaffEventsPanel
              │           └── BossRevenuePanel (manager+)
              └── StaffVerifyBooking

Stores: auth.js, bossPreview.js
```
