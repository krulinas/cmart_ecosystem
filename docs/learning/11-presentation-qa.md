# 11 — Presentation Q&A (Soalan Lecturer / Supervisor)

> **Bahasa:** Bahasa Melayu / Manglish | **Tujuan:** Jawapan cepat untuk demo, viva, atau presentation

Dokumen ini compile soalan lazim dan jawapan berdasarkan codebase sebenar. Kalau tak pasti, jawapan ditanda **Needs verification**.

---

## Apa modul ini buat (What this module does)

"Cheatsheet" untuk presentation — cover system overview, technical decisions, security, testing, dan limitations.

---

## Fail penting untuk rujukan semasa presentation

| Dokumen | Bila guna |
|---------|-----------|
| `01-system-overview-bm.md` | Opening / big picture |
| `02-user-roles-access-control.md` | Soalan security & RBAC |
| `03-booking-workflow.md` | Demo booking flow |
| `04-payment-receipt-pass-workflow.md` | Demo payment & pass |
| `10-selenium-e2e-test-guide.md` | Soalan testing |
| `frontend/tests/e2e/E2E-TESTING-EVIDENCE-REPORT.md` | Bukti 34/34 pass |

---

## Q&A: System Overview

### Q: Apa masalah sistem ni solve?

**Jawapan:**
> CMart Ecosystem digitalize carboot vendor booking untuk universiti. Dulu mungkin manual WhatsApp/paper — sekarang ada structured workflow: vendor book online, staff review, manager approve, payment verify, digital pass untuk check-in.

### Q: Apa tech stack korang guna?

**Jawapan:**
> Backend Laravel dengan Sanctum API authentication. Frontend Vue 3 SPA. Database MySQL. E2E testing guna Selenium WebDriver. Optional Python analytics service dalam folder `python_analytics/`.

### Q: Berapa banyak user role?

**Jawapan:**
> Lima role dalam database: `community` (vendor), `staff`, `manager`, `super_admin`, `uum`. Plus `vendor_status` sebagai gate tambahan untuk booking. Legacy role strings masih supported untuk backward compatibility.

---

## Q&A: Architecture

### Q: Kenapa Vue SPA, bukan blade template?

**Jawapan:**
> SPA bagi better UX untuk dashboard-heavy app — vendor dashboard, management workspace dengan multiple panels. API decoupled supaya same backend boleh serve mobile app in future.

### Q: Macam mana frontend communicate dengan backend?

**Jawapan:**
> REST API di `/api/*`. Login dapat Sanctum bearer token. Setiap protected request hantar header `Authorization: Bearer <token>`. Frontend store token dalam auth Pinia store.

### Q: Kenapa management guna hash navigation (`/admin#bookings`) bukan separate routes?

**Jawapan:**
> Single admin route dengan hash sections elak complex nested routing. Sidebar navigate ke `/admin#revenue` etc. Router guard block manager-only hashes untuk staff. Panels lazy-loaded untuk performance.

---

## Q&A: Security & Access Control

### Q: Macam mana korang implement authorization?

**Jawapan:**
> Tiga layer: (1) Laravel middleware pada API routes — `role`, `boss`, `vendor.approved`, (2) Vue Router guards — `meta.roles` dan `vendorApproved`, (3) Controller ownership checks — vendor hanya access booking sendiri. Tiada Laravel Policy class — semua dalam middleware + controller.

### Q: Boleh tak staff approve booking terus?

**Jawapan:**
> Tidak untuk final approval. Staff handle tier 1 — forward, revise, reject. Hanya manager/super_admin boleh set status `Approved`. Ini enforced dalam `BookingController::STATE_TRANSITIONS` dan verified E2E Test 7A.

### Q: Boleh tak vendor A tengok booking vendor B?

**Jawapan:**
> Tidak. API check `user_id` ownership. E2E Test 7C verify cross-vendor isolation untuk UI dan API.

### Q: Guest boleh access apa?

**Jawapan:**
> Public routes: landing, community portal, marketplace, calendar, news, feedback list. Protected routes redirect ke login. E2E Test 7D dan 8A verify ini.

### Q: Apa risiko security yang korang aware?

**Jawapan:**
> (1) Token dalam localStorage — standard SPA XSS risk, (2) `web.php` analytics proxy tiada role middleware — perlu review untuk production, (3) `uum` role ada frontend page tapi limited API — incomplete module, (4) Demo registration auto-approve vendor — sesuai development, bukan production policy.

---

## Q&A: Booking Workflow

### Q: Explain booking lifecycle.

**Jawapan:**
> Vendor submit → `Pending_Staff` → staff forward → `Pending_Boss` → manager approve → `Approved` → vendor pay → staff verify → receipt + pass unlock → event day staff check-in. Side paths: revision loop (`Needs_Revision`), reject (terminal), withdraw (terminal).

### Q: Berapa harga tapak?

**Jawapan:**
> Dari code: `amount = tapak_quantity × RM 20` bila booking create. **Needs verification** — sama ada harga space table digunakan atau flat rate sahaja.

### Q: Ada audit trail tak?

**Jawapan:**
> Ya. `booking_audit_logs` table record setiap status transition dengan actor, from/to status, IP. Manager boleh view dalam `/admin#audit` panel.

---

## Q&A: Payment & Pass

### Q: Ada payment gateway integration?

**Jawapan:**
> Belum. Vendor upload screenshot proof manually. Staff verify dalam admin panel. Status flow: Unpaid → Pending Verification → Paid.

### Q: Pass simpan dalam database ke?

**Jawapan:**
> Tidak. Pass ialah computed payload dari `VendorEventPassService` — check approval, payment, event timing, check-in window. QR encode URL ke staff verify page.

### Q: Bila QR pass active?

**Jawapan:**
> Booking Approved + payment Paid + dalam check-in window (event start minus 3 jam hingga event end plus 2 jam).

---

## Q&A: Testing

### Q: Korang ada automated testing?

**Jawapan:**
> Ya. Selenium E2E suite dengan 34 test cases. Latest run 34/34 passing headless ~18 minit. Cover login, booking pipeline, payment, access control, public routes.

### Q: Macam mana run tests?

**Jawapan:**
> Start MySQL, `php artisan migrate --seed`, `php artisan serve`, `npm run dev:e2e` on port 5175, then `npm run test:e2e:headless`. Preflight validate environment sebelum run.

### Q: Kenapa port 5175, bukan 5173?

**Jawapan:**
> E2E config hardcode port 5175 dalam `.env.e2e`. Dedicated `npm run dev:e2e` script start Vite on that port supaya consistent dengan preflight check.

---

## Q&A: Database

### Q: Entity utama dalam database?

**Jawapan:**
> User, Booking, Invoice (1:1), Space, CarbootEvent, VendorItem, Feedback, NewsPost, plus profile tables dan audit logs. 15 Eloquent models total.

### Q: Kenapa tiada Receipt table?

**Jawapan:**
> Receipt generate on-demand sebagai PDF dari Blade template (`booking.blade.php`). Tak perlu store duplicate data — invoice record dah cukup untuk payment status.

---

## Q&A: Limitations & Future Work

### Q: Apa yang belum complete?

**Jawapan:**
> (1) `uum` oversight module — frontend route ada, API limited, (2) Real payment gateway (FPX/Stripe), (3) Email notifications — **Needs verification** if implemented, (4) Orphan Vue components not wired up.

### Q: Apa improvement untuk production?

**Jawapan:**
> Manual vendor approval flow (not auto-approve on register), HTTPS everywhere, rate limiting on sensitive endpoints, review analytics proxy security, email/SMS notifications for status changes, backup strategy for payment proofs.

---

## Demo script (5 minit)

### Minute 1: Public face
- Buka `/` → `/marketplace` → `/calendar`
- "Ini public zone, guest boleh browse"

### Minute 2: Vendor flow
- Login `vendor@cmart.com`
- `/dashboard` → show bookings
- `/vendor-booking` → explain form (optional: create booking)

### Minute 3: Staff flow
- Login `staff@cmart.com`
- `/admin#bookings` → forward booking
- Show payment verify button

### Minute 4: Manager flow
- Login `admin@cmart.com`
- Approve booking
- Show `#revenue` or `#audit` (manager only)

### Minute 5: Payment & testing
- Back to vendor → payment history → pass QR
- Mention E2E 34/34 passing

---

## Access control / permission notes (quick reference)

| Role | Home | Key actions |
|------|------|-------------|
| Guest | `/` | Browse public only |
| community | `/dashboard` | Book, pay, pass |
| staff | `/admin` | Review tier 1, verify payment |
| manager | `/admin` | Approve, delete, analytics |
| super_admin | `/admin` | Same as manager + HQ label |
| uum | `/uum` | Oversight (limited) |

---

## Common bugs atau risks (untuk honest Q&A)

| Soalan lecturer | Jawapan jujur |
|-----------------|---------------|
| "UI je ke protection?" | API juga enforce — frontend guard supplementary |
| "Database migration safe?" | Ada legacy role migration — tested via seed |
| "Tests flaky?" | Session cleared between specs; preflight catches env issues |
| "Scalable?" | Current design untuk single institution; multi-tenant needs work |

---

## Macam mana verify sebelum presentation

### Checklist hari demo

- [ ] MySQL running
- [ ] `php artisan migrate --seed` fresh
- [ ] `php artisan serve` running
- [ ] `npm run dev` or `dev:e2e` running
- [ ] `php artisan storage:link` done
- [ ] Demo passwords known
- [ ] At least one upcoming carboot event in DB
- [ ] Optional: run `npm run test:e2e:headless` night before

### Quick smoke
```bash
curl http://127.0.0.1:8000/api/events
curl http://localhost:5173/  # or 5175
```

---

## One-liner summary (penutup presentation)

> "CMart Ecosystem ialah full-stack vendor booking platform dengan two-tier approval, manual payment verification, digital event passes, dan comprehensive Selenium E2E test suite — built dengan Laravel, Vue, MySQL, dan role-based access control dari API hingga UI."
