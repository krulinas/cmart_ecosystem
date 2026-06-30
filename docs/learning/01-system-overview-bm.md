# 01 — Gambaran Keseluruhan Sistem CMart Ecosystem

> **Bahasa:** Bahasa Melayu / Manglish | **Tahap:** First-year CS | **Status:** Berdasarkan codebase sebenar (Jun 2026)

---

## Apa modul ini buat (What this module does)

**CMart Ecosystem** ialah sistem web untuk mengurus **carboot sale / vendor marketplace** di universiti (konteks UUM). Sistem ini menghubungkan:

1. **Community / Vendor** — orang yang nak jual barang di tapak carboot
2. **CMart Staff** — pekerja operasi yang semak booking pertama
3. **CMart Manager / HQ** — pengurus yang buat kelulusan akhir, analytics, audit
4. **UUM** — peranan oversight (read-only, modul masih terhad)
5. **Public visitor** — boleh lihat marketplace, kalendar event, news, feedback tanpa login

**Stack teknikal:**

| Lapisan | Teknologi | Folder |
|---------|-----------|--------|
| Backend API | Laravel + Sanctum | `backend/` |
| Frontend SPA | Vue 3 + Pinia + Vue Router | `frontend/` |
| Database | MySQL | via XAMPP |
| E2E Tests | Selenium + Mocha + Chrome | `frontend/tests/e2e/` |
| Analytics (optional) | Python service | `python_analytics/` |

**Aliran data ringkas:**

```
Browser (Vue)  →  HTTP + Bearer Token  →  Laravel API (/api/*)  →  MySQL
```

---

## Fail penting (Important files)

| Fail | Fungsi |
|------|--------|
| `backend/routes/api.php` | Semua REST API endpoint |
| `backend/app/Http/Kernel.php` | Daftar middleware (role, boss, vendor.approved) |
| `backend/app/Support/ManagementRole.php` | Helper role hierarchy |
| `frontend/src/router/router.js` | Route guard frontend |
| `frontend/src/stores/auth.js` | Session login, role detection |
| `frontend/src/main.js` | Bootstrap Vue app |
| `backend/database/seeders/DatabaseSeeder.php` | Demo users untuk development/E2E |
| `frontend/tests/e2e/README.md` | Panduan test automasi |

---

## Workflow step-by-step (Big picture)

### 1. User datang ke website
- Public page: `/`, `/community`, `/marketplace`, `/calendar`
- Tak perlu login untuk browse

### 2. Vendor register & login
- Register → role default `community`, `vendor_status` default `approved` (demo)
- Login → dapat Sanctum token → simpan di frontend
- Redirect ke `/dashboard`

### 3. Vendor buat booking tapak
- Pergi `/vendor-booking` (perlu `vendor_status = approved`)
- Submit form → API `POST /api/bookings`
- Status mula: `Pending_Staff`

### 4. Staff & Manager semak
- Staff forward / revise / reject di `/admin#bookings`
- Manager approve / revise / reject
- Bila `Approved` → vendor boleh bayar

### 5. Payment & pass
- Vendor upload bukti bayaran
- Staff verify payment → status `Paid`
- Receipt PDF + Event Pass QR unlock

### 6. Hari event
- Staff scan QR di `/staff/verify-booking/:id`
- Check-in vendor → set `checked_in_at`

---

## Access control / permission notes

Sistem guna **dua dimensi akses**:

1. **`users.role`** — siapa user tu (community, staff, manager, super_admin, uum)
2. **`users.vendor_status`** — untuk community sahaja (none, pending, approved, suspended)

**Tiada Laravel Policy** — authorization buat melalui:
- Middleware (`EnsureRole`, `EnsureBossOnly`, `EnsureVendorApproved`)
- Check inline dalam controller (contoh: booking owner `user_id`)

**Legacy role masih diterima** dalam code: `cmart_staff` → `staff`, `cmart_admin`/`boss` → `manager`

---

## Apa nak cakap kalau lecturer tanya

> "CMart Ecosystem ialah full-stack web app untuk manage carboot vendor booking. Backend Laravel expose REST API dengan Sanctum token auth. Frontend Vue SPA consume API tu. Sistem guna role-based access control — vendor community, CMart staff tier 1, manager tier 2, super_admin HQ. Booking ada two-tier approval pipeline: staff dulu, lepas tu manager. Lepas approve, vendor submit payment proof, staff verify, baru receipt dan event pass QR unlock. Kami juga ada Selenium E2E test suite dengan 34 test cases untuk verify workflow end-to-end."

---

## Common bugs atau risks

| Risk | Penerangan |
|------|------------|
| Frontend guard ≠ backend guard | User boleh bypass UI tapi API tetap reject — ini betul, tapi jangan assume UI sahaja cukup |
| Legacy role strings | Code masih accept `boss`, `cmart_staff` — migration data mungkin ada mixed values |
| `uum` role tiada API dedicated | Frontend ada `/uum` tapi **Needs verification** — tiada route khusus dalam `api.php` |
| `web.php` analytics proxy | Route analytics di `web.php` **tiada role middleware** — perlu verify security di production |
| Demo default `vendor_status=approved` | Registration auto-approve vendor — sesuai demo, bukan production policy |
| Event dates stale | E2E booking fail kalau carboot events dah lepas — perlu re-seed |

---

## Macam mana verify ia berfungsi

1. **Backend:** `cd backend && php artisan serve` → buka `http://127.0.0.1:8000/api/events`
2. **Frontend:** `cd frontend && npm run dev` → buka `http://localhost:5173`
3. **Seed data:** `cd backend && php artisan migrate --seed`
4. **Login demo accounts** (dari seeder):
   - `vendor@cmart.com` → community vendor
   - `staff@cmart.com` → staff
   - `admin@cmart.com` → manager
   - `hq@cmart.com` → super_admin
5. **E2E full suite:** `cd frontend && npm run test:e2e:headless` (perlu backend + `npm run dev:e2e` on port 5175)

---

## Rujukan dokumen seterusnya

| Dokumen | Topik |
|---------|-------|
| `02-user-roles-access-control.md` | Role & middleware detail |
| `03-booking-workflow.md` | Booking approval pipeline |
| `04-payment-receipt-pass-workflow.md` | Payment → receipt → pass |
| `07-api-route-map.md` | Semua API endpoint |
| `10-selenium-e2e-test-guide.md` | Cara run E2E tests |
