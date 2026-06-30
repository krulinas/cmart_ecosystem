# 05 — Vendor Dashboard Workflow

> **Bahasa:** Bahasa Melayu / Manglish | **Fokus:** Portal community/vendor di `/dashboard`

---

## Apa modul ini buat (What this module does)

**Vendor Dashboard** ialah hub utama untuk user role `community`. Dari sini vendor boleh:

- Lihat & manage **bookings** (my bookings table)
- Access **event passes** (QR untuk check-in)
- Manage **business profile** (nama kedai, logo)
- List **reuse items** untuk marketplace
- View **analytics** (sales/activity summary)
- Access **payment history & receipts**
- Navigate ke **profile** edit

Route utama: `/dashboard` → `VendorDashboard.vue`

---

## Fail penting (Important files)

### Pages (routes)

| Route | Component | Access |
|-------|-----------|--------|
| `/dashboard` | `frontend/src/views/dashboards/VendorDashboard.vue` | `community` role |
| `/profile` | `frontend/src/views/vendor/VendorProfile.vue` | `community` role |
| `/vendor-booking` | `frontend/src/views/auth/Registration.vue` | `community` + `vendorApproved` |

### Components (dalam dashboard)

| Component | Fungsi |
|-----------|--------|
| `VendorBookingDetailsModal.vue` | View/edit booking, resubmit, withdraw, PDF |
| `WithdrawBookingModal.vue` | Confirm withdraw |
| `VendorEventPassesPanel.vue` | Senarai pass + open modal |
| `VendorPassModal.vue` | Full pass screen dengan QR |
| `VendorBusinessProfileManager.vue` | Edit business profile & logo |
| `VendorItemManager.vue` | CRUD reuse marketplace items |
| `VendorItemFormModal.vue` | Form add/edit item |
| `VendorItemDetailsModal.vue` | View item detail |
| `VendorAnalyticsDashboard.vue` | Charts & metrics |
| `VendorHistoryReceipts.vue` | Payment records + receipt PDF |
| `VendorPaymentModal.vue` | Upload payment proof |
| `VendorProfileEditModal.vue` | Edit personal profile (di `/profile`) |

### Navigation & config

| Fail | Fungsi |
|------|--------|
| `frontend/src/config/navigation.js` | `VENDOR_LINKS` — dashboard, marketplace, community, calendar, profile |
| `frontend/src/components/navigation/AppNavbar.vue` | Navbar variant vendor |
| `frontend/src/stores/auth.js` | `isApprovedVendor` computed |

### API endpoints vendor guna

| Endpoint | Feature |
|----------|---------|
| `GET /api/vendor/bookings` | My bookings |
| `GET/PATCH /api/vendor/bookings/{id}` | Detail & edit |
| `GET /api/vendor/event-passes` | Passes |
| `GET /api/vendor/history-receipts` | Receipts |
| `GET/PUT /api/vendor/business-profile` | Business profile |
| `GET/POST /api/vendor/items` | Reuse items |
| `GET /api/vendor/analytics/me` | Analytics |

---

## Workflow step-by-step

### 1. Login & land di dashboard
```
Login (community) → auth.homeForUser() → /dashboard
VendorDashboard mount → fetch bookings, passes, etc.
```

### 2. Buat booking baru
```
Klik "Book Tapak" / navigate /vendor-booking
(Blocked kalau vendor_status !== approved)
Fill form → POST /api/bookings
Redirect balik dashboard → booking muncul dalam table
```

### 3. Track booking status
```
My Bookings table → status chip (Pending_Staff, etc.)
Klik row → VendorBookingDetailsModal
  - View pipeline steps (bookingDisplay.js)
  - Edit kalau Needs_Revision
  - Resubmit
  - Withdraw (kalau allowed)
  - View invoice PDF
```

### 4. Selepas approved — bayar
```
VendorHistoryReceipts section → Submit Payment
VendorPaymentModal → upload proof
Status: Pending Verification → tunggu staff
```

### 5. Selepas paid — pass & receipt
```
VendorHistoryReceipts → "View Receipt" → PDF
VendorEventPassesPanel → open pass → QR code
```

### 6. Manage business & items
```
Business Profile tab → update name, phone, logo
Reuse Items tab → add/edit/delete items untuk marketplace
Items appear di public /marketplace
```

### 7. Profile page
```
/profile → VendorProfile.vue
Edit name, phone, etc. via VendorProfileEditModal
PATCH /api/vendor/profile
```

---

## Access control / permission notes

| Feature | Guard |
|---------|-------|
| `/dashboard`, `/profile` | `meta.roles: ['community']` |
| `/vendor-booking` | + `vendorApproved: true` |
| Booking create API | `vendor.approved` middleware |
| Pass QR visible | Approved + Paid (backend compute) |
| Edit booking | Owner + allowed status |
| Analytics | `role:community` |

**Nota:** Vendor yang `vendor_status = pending` atau `suspended` masih boleh login dashboard tapi **tak boleh** access `/vendor-booking`.

---

## Apa nak cakap kalau lecturer tanya

> "Vendor dashboard ialah single-page hub yang orchestrate semua feature vendor — bookings, payment, passes, business profile, reuse items, dan analytics. Semua data dari Laravel API dengan Sanctum token. UI guard check role community, manakala booking submission ada extra vendor.approved gate. Component structure guna modal pattern untuk details supaya user tak perlu navigate banyak page."

---

## Common bugs atau risks

| Risk | Detail |
|------|--------|
| Booking button visible tapi API 403 | Vendor not approved — UI should reflect `isApprovedVendor` |
| Stale booking list | Perlu refresh selepas staff action — check if polling/refetch wujud |
| Orphan components | `VendorBusinessProfileModal.vue`, `VendorEventInsights.vue` wujud tapi **tidak di-import** — dead code |
| Image upload fail | `storage:link` atau file size limit |
| Cross-vendor data leak | API enforce `user_id` — verified E2E Test 7C |

---

## Macam mana verify ia berfungsi

### Manual checklist
- [ ] Login `vendor@cmart.com` → `/dashboard` load
- [ ] My Bookings table ada data
- [ ] `/vendor-booking` accessible
- [ ] Business profile save works
- [ ] Add reuse item → visible di `/marketplace`
- [ ] Payment history section load

### E2E
```bash
cd frontend
npm run test:e2e:headless -- --spec specs/auth.login.spec.js
npm run test:e2e:headless -- --spec specs/vendor.booking.spec.js
npm run test:e2e:headless -- --spec specs/access.vendor-ownership-guard.spec.js
```

---

## Diagram vendor hub

```
                    VendorDashboard (/dashboard)
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
   My Bookings      Event Passes         Payment History
        │                   │                   │
   Details Modal        Pass Modal         Payment Modal
   Withdraw Modal          QR              Receipt PDF
        │
   /vendor-booking (new)
```

### Public pages vendor boleh access (tanpa extra role)

Dari navbar `VENDOR_LINKS`:
- `/marketplace` — browse semua reuse items
- `/community` — community portal
- `/calendar` — event calendar
