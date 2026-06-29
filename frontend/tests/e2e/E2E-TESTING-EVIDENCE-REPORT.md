# CMart Ecosystem Selenium E2E Testing Evidence Report

**Document type:** Automated browser-level regression testing evidence  
**Scope:** Full Selenium E2E layer — login smokes through Test 8A (Public Route Safety)  
**Last updated:** 29 June 2026  
**Related docs:** [README.md](./README.md) · [TEST-7-ACCESS-CONTROL.md](./TEST-7-ACCESS-CONTROL.md)

---

## 1. Purpose of This Report

This report documents the **automated browser-level regression testing evidence** for the CMart Ecosystem carboot booking platform. It is intended for internship evaluation, project handoff, and ongoing regression confidence.

The Selenium E2E suite validates integrated user-facing behavior across:

- **Major user journeys** — vendor booking, staff review, manager approval, payment, and withdrawal
- **Vendor booking flow** — event selection, submission, and dashboard visibility
- **Staff review workflow** — safe review of E2E-marked bookings
- **Manager approval workflow** — forward queue, approve/reject, and boss-level APIs
- **Payment and pass/receipt behavior** — invoice visibility, proof submission, verification gate, and unlock after Paid
- **Withdrawal handling** — eligible unpaid bookings can be withdrawn; terminal states are guarded
- **Role-based access boundaries** — staff vs manager, vendor ownership, guest protection (Test 7)
- **Destructive action protection** — delete, duplicate payment, terminal withdraw, duplicate approve (Test 7E)
- **Public route safety** — guests can browse intended public pages without over-locking (Test 8A)

All claims in this document are tied to **implemented spec files** under `frontend/tests/e2e/specs/` and supporting helpers under `frontend/tests/e2e/helpers/`.

---

## 2. Testing Layer Summary

### Layer 1 — Selenium E2E (this report)

| Attribute | Detail |
|-----------|--------|
| **Technology** | Selenium WebDriver 4, Mocha, Chrome (headless or headed) |
| **Runner** | `node tests/e2e/run.js` via `npm run test:e2e:headless` |
| **What it simulates** | Real browser interaction — login forms, dashboard navigation, modals, file upload, in-browser API probes |
| **What it validates** | UI rendering, route guards, role-visible controls, and end-to-end integration with the Laravel API from the user perspective |
| **Preflight** | `helpers/preflight.js` — credentials, port 5175, API reachability, bookable events (when required) |

Layer 1 is the **slowest but most realistic** regression layer. It catches routing mistakes, missing UI elements, token/session issues, and cross-role integration failures that unit tests alone may miss.

### Layer 2 — Planned (Laravel Feature/API tests)

Layer 2 is **not yet the focus of this report** but is the recommended next step:

- **Laravel Feature/API tests** (`php artisan test` in `backend/`)
- **Faster** execution than browser automation
- **More focused** authorization and business-rule assertions at the HTTP/API layer
- **Less flaky** for pure permission and state-machine checks

Layer 2 complements Selenium E2E: E2E proves flows work in a browser; feature tests prove policies and controllers enforce rules directly.

---

## 3. Final Verified Status

### Recorded checkpoints

| Check | Result | Notes |
|-------|--------|-------|
| **Full combined E2E suite** | **34 / 34 passing** (~18 min) | Verified **29 June 2026** via `npm run test:e2e:headless` — includes original 28 Test 7-era cases + 6 Test 8A cases (see §12) |
| Login smokes (vendor, staff, manager) | **Passing** | Included in full suite; specs: `auth.login.spec.js`, `auth.staff-login.spec.js`, `auth.manager-login.spec.js` |
| Test 7A–7E (access control) | **Passing** | Included in full suite; 7E = **6 / 6** cases |
| Test 8A (public route safety) | **6 / 6 passing** | Included in full suite; also verified in focused rerun (40s) **29 June 2026** |
| Full E2E suite (Test 7 era only) | **28 / 28 passing** | Prior milestone **28 June 2026** — [TEST-7-ACCESS-CONTROL.md](./TEST-7-ACCESS-CONTROL.md) |
| `npm run build` (frontend) | **Passing** | Re-run **29 June 2026** during evidence report publication (see §12) |
| `php artisan test` (backend) | **Passing** | From prior verified checkpoint; **not re-run** with the **34/34** full E2E run |

### Honest scope note

- The **current implemented Selenium E2E suite is 34 test cases** across **19 spec files**: **28** from the Test 7-era milestone plus **6** Test 8A public route safety cases.
- **Full combined suite verified:** `npm run test:e2e:headless` passed **34/34** in **~18 minutes** (**29 June 2026**) with Laravel and `npm run dev:e2e` running.
- **Test 8A** was also confirmed earlier the same day in a focused run (**6/6** in 40s).
- **`php artisan test` was not re-run** as part of the **34/34** E2E checkpoint; backend status remains from the Test 7 milestone unless separately verified.
- Passing E2E tests provide **regression confidence** for documented flows; they do **not** guarantee zero bugs outside covered scenarios (see §10).

---

## 4. Full E2E Milestone Timeline

Milestone order reconstructed from [README.md](./README.md), spec files, and phase naming in the suite.

| Milestone | Spec file(s) | Area | Main purpose | Test cases | Result / status |
|-----------|--------------|------|--------------|------------|-----------------|
| **Phase 1 — Login smokes** | `auth.login.spec.js` | Authentication | Vendor/community login → `/dashboard` | 1 | Passing |
| | `auth.staff-login.spec.js` | Authentication | Staff login → `/admin` | 1 | Passing |
| | `auth.manager-login.spec.js` | Authentication | Manager login → `/admin` | 1 | Passing |
| **Phase 2 — Vendor booking** | `vendor.booking.spec.js` | Vendor journey | Submit booking for available event; verify in My Bookings | 1 | Passing |
| **Phase 3 — Staff review** | `staff.booking-review.spec.js` | Staff workflow | Staff safely reviews E2E-marked booking (Revision action) | 1 | Passing |
| **Phase 4A — Staff forward** | `staff.booking-forward.spec.js` | Staff workflow | Forward E2E-marked booking to manager queue (`Pending_Boss`) | 1 | Passing |
| **Phase 4B — Manager approval** | `manager.booking-approval.spec.js` | Manager workflow | Manager approves E2E-marked `Pending_Boss` booking | 1 | Passing |
| **Phase 4C — Vendor approved** | `vendor.booking-approved.spec.js` | Vendor journey | Vendor sees **Approved** in My Bookings after pipeline | 1 | Passing |
| **Phase 4+ — Withdraw regression** | `vendor.booking-withdraw.spec.js` | Booking withdrawal | Vendor withdraws eligible unpaid E2E-marked booking | 1 | Passing |
| **Phase 5A — Invoice visible** | `vendor.invoice-visible-after-approval.spec.js` | Payment / receipt | Invoice/payment record visible after approval (**Unpaid**) | 1 | Passing |
| **Phase 5B — Payment submit** | `vendor.payment-submit.spec.js` | Payment | Vendor submits proof → **Pending Verification** | 1 | Passing |
| **Phase 5C — Receipt/pass after Paid** | `vendor.receipt-pass-after-paid.spec.js` | Payment / pass | Staff **Verify Paid** → vendor sees receipt + event pass | 1 | Passing |
| **Test 6 — Payment verification gates** | `vendor.payment-verification-pass-unlock.spec.js` | Payment guards | Pass/receipt locked until verify; invalid submit/verify blocked | 4 | Passing |
| **Test 7A — Staff action guard** | `access.staff-action-guard.spec.js` | Access control | Staff cannot use manager-only UI/APIs | 1 | Passing |
| **Test 7B — Manager confirmation** | `access.manager-confirmation.spec.js` | Access control | Manager approve/reject + boss APIs | 3 | Passing |
| **Test 7C — Vendor ownership** | `access.vendor-ownership-guard.spec.js` | Data ownership | Vendor A blocked from Vendor B resources | 1 | Passing |
| **Test 7D — Guest protection** | `access.guest-protection.spec.js` | Guest security | Protected routes/APIs require auth | 1 | Passing |
| **Test 7E — Destructive guards** | `access.destructive-action-protection.spec.js` | Destructive protection | Delete, wrong-owner mutate, terminal withdraw, duplicates | 6 | **6 / 6** passing |
| **Test 8A — Public route safety** | `public.public-route-safety.spec.js` | Public experience | Guests access public pages; dashboards stay protected | 6 | **Passing** (included in **34/34** full suite) |

**Suite totals:** **34 / 34 passing** (full combined suite verified **29 June 2026**). Comprises 28 Test 7-era cases + 6 Test 8A cases.

---

## 5. Coverage Matrix

| Risk area | Covered by | What risk it reduces | Status |
|-----------|------------|----------------------|--------|
| **Authentication** | `auth.login.spec.js`, `auth.staff-login.spec.js`, `auth.manager-login.spec.js` | Broken login or wrong post-auth redirect for vendor/staff/manager | Passing |
| **Vendor booking journey** | `vendor.booking.spec.js`, `vendor.booking-approved.spec.js` | Vendors cannot book or confirm approved status | Passing |
| **Staff workflow** | `staff.booking-review.spec.js`, `staff.booking-forward.spec.js`, `access.staff-action-guard.spec.js` | Staff review/forward broken or staff gains manager powers | Passing |
| **Manager workflow** | `manager.booking-approval.spec.js`, `access.manager-confirmation.spec.js` | Manager cannot approve/reject or reach boss APIs | Passing |
| **Payment verification** | `vendor.payment-submit.spec.js`, `vendor.receipt-pass-after-paid.spec.js`, `vendor.payment-verification-pass-unlock.spec.js` | Proof upload skips verification; invalid verify paths succeed | Passing |
| **Receipt/pass access** | `vendor.receipt-pass-after-paid.spec.js`, `vendor.payment-verification-pass-unlock.spec.js` (6A) | Pass/receipt unlock before **Paid** | Passing |
| **Booking withdrawal** | `vendor.booking-withdraw.spec.js`, `access.destructive-action-protection.spec.js` (7E-D) | Withdraw on ineligible or terminal bookings | Passing |
| **Vendor data ownership** | `access.vendor-ownership-guard.spec.js`, `access.destructive-action-protection.spec.js` (7E-C) | Cross-vendor ID guessing or mutation | Passing |
| **Guest protection** | `access.guest-protection.spec.js`, `public.public-route-safety.spec.js` (8A-6) | Unauthenticated access to dashboards/APIs | Passing |
| **Destructive action protection** | `access.destructive-action-protection.spec.js` | Unauthorized delete, duplicate payment/approve | **6 / 6** passing |
| **Public route accessibility** | `public.public-route-safety.spec.js` (8A-1–8A-5) | Over-locking blocks marketing/browse pages | **6 / 6** passing |
| **No over-locking regression** | `public.public-route-safety.spec.js` + Test 7D helpers | Security hardening accidentally blocks `/`, `/calendar`, `/#news`, `/marketplace` | Passing |

---

## 6. Role-Based and Security Evidence

Test 7 is documented in depth in **[TEST-7-ACCESS-CONTROL.md](./TEST-7-ACCESS-CONTROL.md)**. Summary:

### Staff vs manager boundary (7A, 7B)

- **Staff** see **Forward** and **Revision** for `Pending_Staff` bookings; **Approve**, **Reject**, and **Delete** are absent.
- Staff receive **403** on `DELETE /api/bookings/{id}` and boss analytics/audit APIs.
- **Managers** can approve/reject forwarded bookings and reach `GET /api/boss/analytics/revenue` and `/boss/audit-logs` (**200**).

### Manager authority confirmation (7B)

- Approve flow: `Pending_Staff` → staff forward → manager **Approve** → **Approved**.
- Reject flow: separate booking → **Reject** → **Rejected**.
- Registry **Delete** control visible (not clicked in destructive tests).

### Vendor ownership guard (7C, 7E-C)

- **Vendor A** (`E2E_VENDOR_EMAIL`) cannot list, read, PDF-export, pass-fetch, or payment-submit against **Vendor B** resources.
- Direct ID probes return **403/404**; Vendor B records remain unchanged.

### Guest / unauthenticated protection (7D)

- Protected frontend routes (`/dashboard`, `/admin`, `/vendor-booking`, etc.) redirect to login or hide protected roots.
- Vendor, staff, boss, and resource-by-ID APIs return **401** (403/404 accepted where applicable).

### Destructive action protection (7E)

| Flow | Guard |
|------|-------|
| 7E-A | Staff `DELETE` → **403**; booking persists |
| 7E-B | Guest `DELETE` denied; booking unchanged |
| 7E-C | Vendor A cannot mutate Vendor B booking |
| 7E-D | Terminal withdraw (**Approved**, **Rejected**, **Withdrawn**, **Paid**) → **422** |
| 7E-E | Duplicate payment after **Paid** → **422** |
| 7E-F | Duplicate manager approve → **422** |

**Scope honesty:** Test 7 provides **scoped regression evidence** for implemented high-risk flows. It is **not** a full penetration test, fuzzing campaign, or proof that every future endpoint is secure.

---

## 7. Public Experience Regression Evidence

**Test 8A** — `public.public-route-safety.spec.js` — complements Test 7 by ensuring security hardening did **not** over-restrict legitimate public browsing.

| Case | Route / behavior | Guest expectation |
|------|------------------|-------------------|
| 8A-1 | `/` | Landing visible; public APIs `GET /events`, `/news`, `/marketplace/items` → **200** |
| 8A-2 | `/calendar` | Public calendar loads |
| 8A-3 | `/#news` | News section on landing (no standalone `/news` route) |
| 8A-4 | `/marketplace` | Carboot Reuse Preview; preview-only wording |
| 8A-5 | Detail modals | Event/news/item modals when seeded cards exist (**skipped** if empty DB) |
| 8A-6 | `/dashboard`, `/admin` | Still blocked for guests (reuses Test 7D helpers) |

**Helpers:** `public-routes.js`, `guest-access.js` (8A-6 control check).

**What 8A does not overclaim:** Does not cover every public route (`/community`, `/register` out of scope); 8A-5 depends on seeded content; no SEO/performance testing.

---

## 8. Business Rule Evidence

Business rules enforced by **implemented** E2E specs (source of truth: spec files + README):

| Business rule | Evidence |
|---------------|----------|
| Approved vendors can submit bookings for upcoming events | `vendor.booking.spec.js` |
| Staff can review E2E-marked bookings safely | `staff.booking-review.spec.js` |
| Staff can forward `Pending_Staff` bookings to manager queue | `staff.booking-forward.spec.js` |
| Managers can approve `Pending_Boss` bookings | `manager.booking-approval.spec.js`, `access.manager-confirmation.spec.js` |
| Vendors see **Approved** status after manager approval | `vendor.booking-approved.spec.js` |
| Approved bookings surface **Unpaid** invoice in Booking Receipts | `vendor.invoice-visible-after-approval.spec.js` |
| Payment proof submission → **Pending Verification** (not instant **Paid**) | `vendor.payment-submit.spec.js`, Test 6A |
| Staff/manager **Verify Paid** unlocks receipt and event pass | `vendor.receipt-pass-after-paid.spec.js`, Test 6A |
| Withdraw allowed only for eligible unpaid statuses | `vendor.booking-withdraw.spec.js` |
| Terminal bookings cannot be withdrawn | Test 7E-D, Test 6B (payment submit blocked on Withdrawn/Rejected) |
| Vendor cannot submit payment for Withdrawn/Rejected bookings | Test 6B |
| Staff cannot verify payment unless **Pending Verification** | Test 6C |
| Paid booking cannot be verified twice | Test 6D |
| Duplicate payment proof after **Paid** blocked | Test 7E-E |
| Duplicate manager approve blocked | Test 7E-F |
| Staff cannot delete bookings | Test 7A, 7E-A |
| Guests cannot access protected dashboards/APIs | Test 7D, 8A-6 |
| Vendor A cannot access Vendor B data | Test 7C, 7E-C |
| Public marketing routes remain guest-accessible | Test 8A |

---

## 9. What This E2E Layer Proves

The Selenium E2E layer provides evidence that:

1. **Major user-facing flows work through the browser** — from vendor login and booking through staff review, manager approval, payment, and withdrawal.
2. **Important role boundaries are enforced** — staff vs manager separation, vendor ownership, guest blocking.
3. **Key business rules are guarded** — payment verification gate, terminal-state mutations rejected, duplicate actions blocked.
4. **Public pages remain accessible** after access-control hardening (Test 8A).
5. **Regressions can be detected automatically** when the suite is run against a seeded local environment with backend + `npm run dev:e2e`.

Each workflow spec uses **unique E2E markers** (timestamps or configurable detail strings) so only intentionally created bookings are modified.

---

## 10. What This E2E Layer Does Not Overclaim

| Limitation | Detail |
|------------|--------|
| **Not a penetration test** | No injection fuzzing, rate-limit abuse, or session-fixation campaigns |
| **Not performance/load testing** | No concurrent users, latency SLAs, or stress scenarios |
| **Not every endpoint** | Representative high-risk routes only; future modules need their own tests |
| **Does not replace backend tests** | `php artisan test` remains authoritative for policies, units, and controllers |
| **Environment-dependent** | Requires MySQL, Laravel, Vite on port 5175, seeded users/events |
| **Flakiness possible** | UI timing, headless Chrome, debounced search — helpers mitigate but do not eliminate |
| **Regression confidence, not zero-bug guarantee** | Passing tests mean covered flows worked at run time; uncovered paths may still fail |

---

## 11. Environment and Test Stability Notes

### Frontend E2E server

```bash
cd frontend && npm run dev:e2e
```

- Dedicated Vite port **5175** (`--strictPort`)
- Set `E2E_BASE_URL=http://localhost:5175` in `tests/e2e/.env.e2e`
- Do **not** use default `npm run dev` (5173) unless `E2E_BASE_URL` is updated

### Backend and database

```bash
cd backend && php artisan serve
cd backend && php artisan migrate && php artisan db:seed
```

- API default: `http://127.0.0.1:8000/api` (`E2E_API_BASE_URL`)
- MySQL must be running — preflight fails fast on HTTP 500

### Session cleanup

Global setup (`tests/e2e/setup.js`) clears **cookies**, **localStorage**, and **sessionStorage** between specs to prevent vendor/staff/manager token bleed.

### Future event seeding

Seeder creates carboot events at **+7, +14, +21 days**. Re-run `php artisan db:seed` if events go stale.

### Vendor B for ownership tests (7C, 7E-C)

| Variable | Seed default |
|----------|--------------|
| `E2E_VENDOR_B_EMAIL` | `vendor_b@cmart.com` |
| `E2E_VENDOR_B_PASSWORD` | `password123` |

Vendor B must exist in the database and in `tests/e2e/.env.e2e`. **Do not commit** `.env.e2e` or real secrets.

### Full E2E prerequisites

1. MySQL running  
2. Laravel backend (`php artisan serve`)  
3. E2E frontend (`npm run dev:e2e`)  
4. `tests/e2e/.env.e2e` configured (copy from `.env.e2e.example`)  
5. Seeded test users and upcoming bookable events (for booking specs)

### Key helpers

| Helper | Purpose |
|--------|---------|
| `auth.js` | Role-specific login |
| `booking.js`, `vendor-bookings.js`, `staff-bookings.js` | Booking creation and registry actions |
| `approval-pipeline.js` | Safe multi-role approval flows |
| `payment-verification.js`, `payment-guards.js`, `vendor-payment-records.js` | Payment and verify-paid flows |
| `access-guards.js`, `guest-access.js`, `vendor-ownership.js`, `destructive-guards.js` | Test 7 security probes |
| `public-routes.js` | Test 8A public route assertions |
| `session.js`, `preflight.js`, `driver.js`, `wait.js` | Session hygiene, preflight, WebDriver |

---

## 12. Verification Commands

All commands from `frontend/package.json` and documented E2E workflow. Start `php artisan serve` and `npm run dev:e2e` before E2E runs.

### Frontend build

```bash
cd frontend
npm run build
```

### Full E2E suite (34 test cases)

```bash
cd frontend
npm run test:e2e:headless
```

### Login smokes only

```bash
npm run test:e2e:headless -- auth.login.spec.js auth.staff-login.spec.js auth.manager-login.spec.js
```

### Test 8A focused

```bash
npm run test:e2e:headless -- public.public-route-safety.spec.js
```

### Test 7 focused

```bash
npm run test:e2e:headless -- access.staff-action-guard.spec.js
npm run test:e2e:headless -- access.manager-confirmation.spec.js
npm run test:e2e:headless -- access.vendor-ownership-guard.spec.js
npm run test:e2e:headless -- access.guest-protection.spec.js
npm run test:e2e:headless -- access.destructive-action-protection.spec.js
```

### Backend tests

```bash
cd backend
php artisan test
```

> There are no separate `test:e2e:test7a` npm scripts. Pass spec filenames after `--` (handled by `tests/e2e/run.js`).

### Verification command results (29 June 2026)

| Command | Environment | Result |
|---------|-------------|--------|
| `npm run build` | Frontend only | **Passing** (~7s) |
| `npm run test:e2e:headless` | Laravel at `http://127.0.0.1:8000`; E2E Vite at `http://localhost:5175` (`npm run dev:e2e`) | **34 / 34 passing** in **~18 minutes** |
| `npm run test:e2e:headless -- public.public-route-safety.spec.js` | Same stack (earlier focused run) | **6 / 6 passing** in **40s** |
| `php artisan test` | — | **Not run** (prior Test 7 milestone checkpoint only) |

---

## 13. Recommended Next Testing Layer

### Layer 2 — Laravel Feature/API tests

Suggested focus areas that complement Selenium E2E:

| Area | Example assertion |
|------|-------------------|
| Guest blocked from protected APIs | `GET /api/vendor/bookings` without token → 401 |
| Vendor cannot access another vendor's booking | `GET /api/vendor/bookings/{id}` → 403/404 |
| Staff cannot delete booking | `DELETE /api/bookings/{id}` as staff → 403 |
| Manager can approve/reject | `PATCH /api/bookings/{id}` with valid state transition → 200 |
| Terminal booking states cannot be mutated | Withdraw on **Approved**/**Paid** → 422 |
| Paid booking cannot be withdrawn | `PATCH /api/bookings/{id}/withdraw` → 422 |
| Public APIs remain public | `GET /api/events`, `/api/news` without auth → 200 |

**Why Layer 2 complements E2E:** Feature tests run faster, isolate authorization logic without browser flakiness, and can cover edge cases (malformed payloads, policy matrix) that are expensive to automate in Selenium. E2E remains valuable for proving the **integrated user experience**; feature tests prove **server-side enforcement** at scale.

---

## 14. Meeting / Evaluation Summary

> The CMart Ecosystem **Selenium E2E suite** validates critical carboot booking workflows from the **end-user browser perspective**: vendor login and booking, staff review and forward, manager approval, invoice and payment proof submission, staff verification and pass/receipt unlock, booking withdrawal, role-based access control (Test 7), and public route safety (Test 8A).
>
> As of **29 June 2026**, the **full combined Selenium E2E suite passed 34/34** in **~18 minutes** (`npm run test:e2e:headless`), covering login smokes, Phases 2–6 booking/payment flows, Test 7 access control (7A–7E), and Test 8A public route safety. Frontend `npm run build` is passing. Backend `php artisan test` was green at the **28 June 2026** Test 7 milestone and was **not re-run** with the 34/34 E2E checkpoint.
>
> This evidence supports **regression confidence** for implemented flows and security boundaries. It does **not** replace penetration testing, load testing, or comprehensive API coverage — **Layer 2 Laravel feature tests** are the recommended next step for faster authorization and business-rule validation.

---

## Appendix: Spec inventory

| # | Spec file | `it()` count |
|---|-----------|--------------|
| 1 | `auth.login.spec.js` | 1 |
| 2 | `auth.staff-login.spec.js` | 1 |
| 3 | `auth.manager-login.spec.js` | 1 |
| 4 | `vendor.booking.spec.js` | 1 |
| 5 | `staff.booking-review.spec.js` | 1 |
| 6 | `staff.booking-forward.spec.js` | 1 |
| 7 | `manager.booking-approval.spec.js` | 1 |
| 8 | `vendor.booking-approved.spec.js` | 1 |
| 9 | `vendor.booking-withdraw.spec.js` | 1 |
| 10 | `vendor.invoice-visible-after-approval.spec.js` | 1 |
| 11 | `vendor.payment-submit.spec.js` | 1 |
| 12 | `vendor.receipt-pass-after-paid.spec.js` | 1 |
| 13 | `vendor.payment-verification-pass-unlock.spec.js` | 4 |
| 14 | `access.staff-action-guard.spec.js` | 1 |
| 15 | `access.manager-confirmation.spec.js` | 3 |
| 16 | `access.vendor-ownership-guard.spec.js` | 1 |
| 17 | `access.guest-protection.spec.js` | 1 |
| 18 | `access.destructive-action-protection.spec.js` | 6 |
| 19 | `public.public-route-safety.spec.js` | 6 |
| | **Total** | **34** |

---

## Changelog

| Date | Event |
|------|-------|
| 2026-06-28 | Test 7 suite completed; 28/28 E2E verified — [TEST-7-ACCESS-CONTROL.md](./TEST-7-ACCESS-CONTROL.md) |
| 2026-06-29 | Full-layer evidence report published; `npm run build` passing |
| 2026-06-29 | Test 8A focused rerun: `public.public-route-safety.spec.js` **6/6** in **40s** |
| 2026-06-29 | **Full combined E2E suite: 34/34 passing** in **~18 minutes** (`npm run test:e2e:headless`) |
