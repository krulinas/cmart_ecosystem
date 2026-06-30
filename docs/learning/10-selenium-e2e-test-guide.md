# 10 — Selenium E2E Test Guide

> **Bahasa:** Bahasa Melayu / Manglish | **Stack:** Selenium WebDriver + Mocha + Chrome

---

## Apa modul ini buat (What this module does)

E2E test suite automate **browser testing** untuk verify CMart workflows end-to-end — dari login sampai booking approval, payment, access control, dan public route safety.

**Lokasi:** `frontend/tests/e2e/`  
**Runner:** `frontend/tests/e2e/run.js`  
**Latest checkpoint:** 34/34 passing (~18 min headless, 29 Jun 2026)

---

## Fail penting (Important files)

### Entry & config

| Fail | Fungsi |
|------|--------|
| `tests/e2e/run.js` | Mocha runner, spec resolution |
| `tests/e2e/setup.js` | Global hooks, driver lifecycle |
| `tests/e2e/config/env.js` | Load `.env.e2e` |
| `tests/e2e/.env.e2e.example` | Template credentials |
| `tests/e2e/fixtures/payment-proof.png` | Upload fixture |

### Documentation

| Fail | Fungsi |
|------|--------|
| `tests/e2e/README.md` | Full setup guide |
| `tests/e2e/E2E-TESTING-EVIDENCE-REPORT.md` | Evidence report |
| `tests/e2e/TEST-7-ACCESS-CONTROL.md` | Access control milestone |

### npm scripts (`frontend/package.json`)

| Script | Command |
|--------|---------|
| `test:e2e` | `node tests/e2e/run.js` |
| `test:e2e:headed` | Run with visible browser |
| `test:e2e:headless` | Run headless Chrome |

---

## Prerequisites (workflow step-by-step)

### 1. Start MySQL
XAMPP MySQL on `127.0.0.1:3306`

### 2. Migrate & seed backend
```bash
cd backend
php artisan migrate
php artisan db:seed
```

### 3. Start Laravel API
```bash
cd backend
php artisan serve
# http://127.0.0.1:8000
```

### 4. Start E2E frontend (port 5175!)
```bash
cd frontend
npm run dev:e2e
# NOT npm run dev (port 5173)
```

### 5. Configure E2E env
```bash
cd frontend
cp tests/e2e/.env.e2e.example tests/e2e/.env.e2e
# Edit passwords to match seeder
```

### 6. Run tests
```bash
cd frontend
npm run test:e2e:headless
```

---

## Spec files (19 specs → 34 test cases)

### Phase 1: Auth smoke

| Spec | Apa dia test |
|------|--------------|
| `auth.login.spec.js` | Vendor login → `/dashboard` |
| `auth.staff-login.spec.js` | Staff login → `/admin` |
| `auth.manager-login.spec.js` | Manager login → `/admin` |

### Phase 2-4: Booking pipeline

| Spec | Apa dia test |
|------|--------------|
| `vendor.booking.spec.js` | Vendor create booking |
| `staff.booking-review.spec.js` | Staff request revision |
| `staff.booking-forward.spec.js` | Staff forward to manager |
| `manager.booking-approval.spec.js` | Manager approve |
| `vendor.booking-approved.spec.js` | Vendor sees Approved |
| `vendor.booking-withdraw.spec.js` | Vendor withdraw |

### Phase 5-6: Payment & pass

| Spec | Apa dia test |
|------|--------------|
| `vendor.invoice-visible-after-approval.spec.js` | Invoice visible after approve |
| `vendor.payment-submit.spec.js` | Upload payment proof |
| `vendor.receipt-pass-after-paid.spec.js` | Receipt + pass after verify |
| `vendor.payment-verification-pass-unlock.spec.js` | Pass unlock gate |

### Test 7: Access control

| Spec | Apa dia test |
|------|--------------|
| `access.staff-action-guard.spec.js` | Staff cannot do manager actions |
| `access.manager-confirmation.spec.js` | Manager approve/reject + boss APIs |
| `access.vendor-ownership-guard.spec.js` | Vendor A cannot access Vendor B |
| `access.guest-protection.spec.js` | Guest blocked from protected routes |
| `access.destructive-action-protection.spec.js` | Delete/duplicate/terminal guards |

### Test 8: Public safety

| Spec | Apa dia test |
|------|--------------|
| `public.public-route-safety.spec.js` | Public routes stay guest-accessible |

---

## Helpers (18 files)

| Helper | Fungsi |
|--------|--------|
| `auth.js` | Login/logout semua roles |
| `driver.js` | Chrome WebDriver setup |
| `session.js` | Clear cookies/storage between specs |
| `preflight.js` | Env + API + event validation |
| `wait.js` | Wait for test-id / URL |
| `actions.js` | Click helpers, E2E markers |
| `booking.js` | Fill form, ensureE2EBookingExists |
| `approval-pipeline.js` | Full vendor→staff→manager chain |
| `vendor-bookings.js` | My bookings assertions |
| `staff-bookings.js` | Staff registry actions |
| `vendor-payment-records.js` | Payment + receipt checks |
| `payment-verification.js` | Staff verify paid |
| `access-guards.js` | Access control assertions |
| `guest-access.js` | Guest denial checks |
| `vendor-ownership.js` | Cross-vendor isolation |
| `destructive-guards.js` | Destructive action blocks |
| `public-routes.js` | Public route safety |
| `diagnostics.js` | Screenshots on failure |
| `prompt.js` | Stub window.prompt |

---

## Access control / permission notes

E2E verify **both UI and API** guards:
- Staff action guard — UI button hidden + API 403
- Manager confirmation — boss endpoints accessible
- Vendor ownership — token A cannot GET booking B
- Guest protection — redirect to login
- Destructive — no duplicate approve/payment on terminal states

---

## Apa nak cakap kalau lecturer tanya

> "Kami guna Selenium WebDriver dengan Mocha test runner untuk automate end-to-end testing. Suite ada 34 test cases covering login, booking two-tier approval, payment verification, access control, dan public route safety. Preflight check validate environment sebelum run — port 5175 frontend, API reachable, seeded users, upcoming events. Session cleared between specs untuk elak token bleed. Failures capture screenshots dalam artifacts folder."

---

## Common bugs atau risks

| Symptom | Fix |
|---------|-----|
| Preflight: port wrong | Use `npm run dev:e2e` (5175) |
| Preflight: no bookable events | `php artisan db:seed` |
| Login HTTP 500 | MySQL not running |
| Login 401 | Wrong password in `.env.e2e` |
| Manager approve timeout 300s | Ensure servers running, check artifacts |
| Token bleed between specs | setup.js clears session — jangan skip |
| Stale event dates | Re-seed periodically |

---

## Macam mana verify ia berfungsi

### Full suite
```bash
cd frontend
npm run test:e2e:headless
# Expect: 34/34 passing
```

### Single spec
```bash
cd frontend
npm run test:e2e:headless -- --spec specs/auth.login.spec.js
```

### Headed (nampak browser)
```bash
cd frontend
npm run test:e2e:headed -- --spec specs/vendor.booking.spec.js
```

### Check artifacts on failure
```
frontend/tests/e2e/artifacts/
```

### Evidence report
Baca `frontend/tests/e2e/E2E-TESTING-EVIDENCE-REPORT.md` untuk consolidated results.

---

## E2E environment variables

From `.env.e2e.example` (typical):

| Variable | Default | Purpose |
|----------|---------|---------|
| `E2E_BASE_URL` | `http://localhost:5175` | Frontend URL |
| `E2E_API_BASE_URL` | `http://127.0.0.1:8000/api` | Backend API |
| `E2E_VENDOR_EMAIL` | `vendor@cmart.com` | Vendor A |
| `E2E_VENDOR_B_EMAIL` | — | Vendor B (ownership tests) |
| `E2E_STAFF_EMAIL` | `staff@cmart.com` | Staff |
| `E2E_MANAGER_EMAIL` | `admin@cmart.com` | Manager |
| `E2E_PASSWORD` | — | Shared password |

**Needs verification** — exact variable names dalam `.env.e2e.example` semasa run.

---

## Test pipeline diagram

```
preflight.js
    │
    ├── env valid?
    ├── frontend :5175 up?
    ├── API up?
    └── events available? (booking specs)
         │
         ▼
setup.js (per spec)
    ├── clear session
    ├── login as role
    └── run test actions
         │
         ▼
    assertions (UI + API)
         │
    fail? → diagnostics.js → artifacts/
```
