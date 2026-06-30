# 02 — User Roles & Access Control

> **Bahasa:** Bahasa Melayu / Manglish | **Fokus:** Siapa boleh buat apa, dan di mana guard tu enforce

---

## Apa modul ini buat (What this module does)

Modul access control tentukan **siapa boleh akses route/API mana**. CMart guna:

1. **Authentication** — Sanctum bearer token (login dulu)
2. **Authorization** — role middleware + vendor approval gate + ownership check

Tiada Laravel Policy class — semua logic dalam middleware dan controller.

---

## Fail penting (Important files)

### Backend

| Fail | Fungsi |
|------|--------|
| `backend/app/Support/ManagementRole.php` | Role constants, normalize legacy, hierarchy helpers |
| `backend/app/Http/Middleware/EnsureRole.php` | Middleware `role:*` |
| `backend/app/Http/Middleware/EnsureBossOnly.php` | Middleware `boss` / `manager` |
| `backend/app/Http/Middleware/EnsureVendorApproved.php` | Middleware `vendor.approved` |
| `backend/app/Http/Kernel.php` | Register middleware aliases |
| `backend/routes/api.php` | Route groups ikut role |
| `backend/app/Models/User.php` | Column `role`, `vendor_status` |
| `backend/app/Services/UserAuthPresenter.php` | Shape response `/auth/me` |
| `backend/database/migrations/2026_06_15_000001_standardize_management_roles.php` | Role enum terkini |
| `backend/database/seeders/DatabaseSeeder.php` | Demo users |

### Frontend

| Fail | Fungsi |
|------|--------|
| `frontend/src/utils/managementRoles.js` | Role constants & helpers (mirror backend) |
| `frontend/src/router/router.js` | `beforeEach` guard — `meta.roles`, `vendorApproved` |
| `frontend/src/stores/auth.js` | `hasAnyRole()`, `homeForUser()`, computed role flags |
| `frontend/src/composables/useManagementAccess.js` | Staff vs manager UI permissions |
| `frontend/src/composables/useWorkspaceNav.js` | Hide manager-only nav items |
| `frontend/src/config/workspaceNav.js` | Manager-only hash sections |
| `frontend/src/stores/bossPreview.js` | Manager "view as staff" toggle |

---

## Senarai role (dari database enum)

| Role | Siapa | Home page selepas login |
|------|-------|-------------------------|
| `community` | Vendor / ahli komuniti | `/dashboard` |
| `staff` | CMart operasi tier 1 | `/admin` |
| `manager` | CMart pengurus cawangan | `/admin` |
| `super_admin` | HQ admin | `/admin` |
| `uum` | UUM oversight | `/uum` |

### Legacy aliases (masih diterima dalam code)

| Legacy | Normalize ke |
|--------|--------------|
| `cmart_staff` | `staff` |
| `cmart_admin` | `manager` |
| `boss` | `manager` |

### Vendor status (bukan role, tapi gate booking)

| Value | Maksud |
|-------|--------|
| `none` | Bukan vendor |
| `pending` | Menunggu kelulusan vendor |
| `approved` | Boleh buat booking |
| `suspended` | Diblok dari booking |

---

## Workflow step-by-step

### 1. User login
```
POST /api/auth/login → dapat token
GET /api/auth/me → dapat { role, vendor_status, ... }
Frontend simpan token → auth store update
```

### 2. User navigate ke page
```
router.beforeEach:
  - Kalau public route → pass
  - Kalau requiresAuth tapi tiada token → redirect /login
  - Kalau meta.roles tak match → redirect homeForUser()
  - Kalau vendorApproved fail → redirect homeForUser()
  - Kalau /admin + manager-only hash + user staff → redirect #bookings
```

### 3. User call API
```
Request header: Authorization: Bearer <token>
Laravel auth:sanctum → identify user
Middleware role/boss/vendor.approved → allow atau 403
Controller → extra check (ownership, workflow role)
```

### 4. Booking workflow role key
- `super_admin` dianggap `manager` untuk state transition booking
- Helper: `ManagementRole::workflowRoleKey()`

---

## Access control / permission notes

### Backend middleware groups (ringkasan)

| Middleware | Siapa lulus |
|------------|-------------|
| `auth:sanctum` | Mana-mana user login |
| `role:community` | Role `community` |
| `vendor.approved` | `community` + `vendor_status === approved` |
| `role:staff,manager,super_admin,...` | CMart workers (termasuk legacy) |
| `boss` | `manager` atau `super_admin` sahaja |

### Frontend manager-only sections (`/admin#...`)

Dari `workspaceNav.js` — **staff tak boleh access**:
- `#revenue` — revenue analytics
- `#analytics` — word cloud
- `#audit` — audit log

### Staff vs Manager dalam booking panel

| Capability | Staff | Manager |
|------------|-------|---------|
| Lihat queue `Pending_Staff` | Ya | Ya |
| Forward ke manager | Ya | Ya |
| Request revision / reject (tier 1) | Ya | Ya |
| Final approve (`Approved`) | **Tidak** | Ya |
| Delete booking | **Tidak** | Ya |
| Revenue / audit panels | **Tidak** | Ya |
| API endpoint list | `/staff/bookings` | `/bookings` |

### Ownership checks (contoh)
- Vendor hanya boleh edit booking sendiri (`user_id` match)
- Vendor B tak boleh access booking Vendor A (verified dalam E2E Test 7C)

---

## Apa nak cakap kalau lecturer tanya

> "Kami implement RBAC menggunakan Laravel middleware, bukan Policy class. Setiap API route group ada middleware `role` atau `vendor.approved`. Frontend ada second layer guard dalam Vue Router `beforeEach` — check `meta.roles` dan `vendorApproved`. Manager-only UI sections guna hash navigation dengan extra guard. Legacy role strings masih dinormalize supaya migration lama tak break. Super admin follow manager workflow untuk booking approval tapi ada tier label berbeza dalam UI."

---

## Common bugs atau risks

| Bug/Risk | Detail |
|----------|--------|
| UI-only protection | Staff boleh cuba navigate ke `#revenue` manually — router guard redirect, tapi API boss routes juga block |
| `read_only` staff mode | Staff registry API return `read_only` access mode — **Needs verification** untuk exact behavior bila staff cuba PATCH |
| Token di localStorage | XSS boleh curi token — standard SPA risk |
| `uum` incomplete | Route frontend wujud, API dedicated tiada |
| Boss preview toggle | Manager boleh "view as staff" — manager-only nav hidden semasa preview |

---

## Macam mana verify ia berfungsi

### Manual
1. Login sebagai `staff@cmart.com` → patut ke `/admin`, tak nampak Revenue/Audit
2. Cuba navigate `/admin#revenue` → patut redirect ke `#bookings`
3. Login sebagai `admin@cmart.com` → patut nampak semua sections
4. Login vendor → cuba `/admin` → patut redirect ke `/dashboard`

### Automated (E2E)
```bash
cd frontend
npm run test:e2e:headless -- --spec specs/access.staff-action-guard.spec.js
npm run test:e2e:headless -- --spec specs/access.guest-protection.spec.js
npm run test:e2e:headless -- --spec specs/access.vendor-ownership-guard.spec.js
```

### API test (curl example)
```bash
# Guest — patut 401
curl http://127.0.0.1:8000/api/bookings

# Staff token — patut 200
curl -H "Authorization: Bearer <staff_token>" http://127.0.0.1:8000/api/staff/bookings
```

---

## Diagram ringkas

```
                    ┌─────────────┐
                    │   Guest     │ → public routes sahaja
                    └─────────────┘
                           │
                    ┌──────▼──────┐
                    │  community  │ → /dashboard, /profile
                    │ + approved  │ → + /vendor-booking, POST /bookings
                    └─────────────┘
                           │
          ┌────────────────┼────────────────┐
          │                │                │
    ┌─────▼─────┐   ┌──────▼──────┐  ┌─────▼─────┐
    │   staff   │   │   manager   │  │super_admin│
    │  tier 1   │   │   tier 2    │  │    HQ     │
    └───────────┘   └─────────────┘  └───────────┘
         │                 │                │
         └──────── /admin ──┴────────────────┘
                    │
         staff: #bookings, #feedback, #events, #news, #tools
         manager+: + #revenue, #analytics, #audit
```
