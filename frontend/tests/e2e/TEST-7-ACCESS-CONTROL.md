# Test 7 — Access Control & Role Security Suite

Final wrap-up documentation for the CMart Ecosystem Selenium E2E access-control milestone. Suitable for internship / project evaluation and regression handoff.

**Related specs:** `access.staff-action-guard.spec.js`, `access.manager-confirmation.spec.js`, `access.vendor-ownership-guard.spec.js`, `access.guest-protection.spec.js`, `access.destructive-action-protection.spec.js`

**Key helpers:** `access-guards.js`, `vendor-ownership.js`, `guest-access.js`, `destructive-guards.js`, `session.js`, `preflight.js`

---

## Purpose

Test 7 validates **role-based access control (RBAC)** and **data ownership** across four actor types in the CMart carboot booking platform:

- **Guest / unauthenticated** — no session token
- **Vendor / community** — approved vendor accounts (`role: community`, `vendor_status: approved`)
- **Staff** — CMart staff workspace (`cmart_staff`)
- **Manager** — CMart manager / boss workspace (`cmart_admin` / boss middleware)

The suite exercises **browser UI visibility**, **authenticated API probes** (via in-browser `fetch` with Sanctum tokens), and **state-transition guards** on high-risk booking and payment flows. It confirms that security hardening did not break login smokes or the wider E2E pipeline (Phases 1–6).

---

## Final Verified Status

Recorded **28 June 2026** after full regression:

| Check | Result |
|-------|--------|
| Full E2E suite | **28 / 28 passing** |
| Login smokes (vendor, staff, manager) | **Passing** |
| Test 7A regression | **Passing** |
| Test 7B regression | **Passing** |
| Test 7C regression | **Passing** |
| Test 7D regression | **Passing** |
| Test 7E focused | **6 / 6 passing** |
| `npm run build` (frontend) | **Passing** |
| `php artisan test` (backend) | **Passing** |

---

## Test 7 Coverage Summary

| Test ID | Spec file | Area | Main purpose | Expected result | Status |
|---------|-----------|------|--------------|-----------------|--------|
| **7A** | `access.staff-action-guard.spec.js` | Staff vs manager action guard | Staff can use staff-safe booking actions but not manager-only UI or APIs | Forward + Revision visible; Approve + Delete absent; `DELETE /bookings/{id}` → **403**; boss analytics/audit → **403** | Passing |
| **7B** | `access.manager-confirmation.spec.js` | Manager access confirmation | Manager can approve/reject forwarded bookings and reach boss-only APIs | Approve → **Approved**; Reject → **Rejected**; Delete control visible in registry; `GET /boss/analytics/revenue` and `/boss/audit-logs` → **200** | Passing |
| **7C** | `access.vendor-ownership-guard.spec.js` | Vendor data ownership guard | Vendor A cannot read or mutate Vendor B booking, receipt, pass, or payment resources | List APIs exclude Vendor B data; direct ID probes → **403/404**; Vendor B record unchanged | Passing |
| **7D** | `access.guest-protection.spec.js` | Guest / unauthenticated protection | Guests cannot reach protected dashboards, resources, or management APIs | Routes redirect to login or hide protected roots; APIs → **401** (403/404 accepted); booking integrity preserved | Passing |
| **7E** | `access.destructive-action-protection.spec.js` | Destructive action protection | Wrong roles, guests, wrong owners, terminal states, and duplicate actions are blocked | See [7E flows](#test-7e-sub-flows) below; destructive mutations return **401/403/404/422** as applicable | **6 / 6** passing |

### Test 7E sub-flows

| Flow | Guard |
|------|-------|
| 7E-A Staff delete | `DELETE /api/bookings/{id}` → **403**; Delete UI absent; booking persists |
| 7E-B Guest delete | `DELETE` denied (**401** preferred; **403/404** accepted); booking unchanged |
| 7E-C Wrong vendor | Vendor A `PATCH` withdraw, `POST` submit-payment, `PATCH` vendor update on Vendor B booking → **403/404** |
| 7E-D Terminal withdraw | Owning vendor cannot withdraw **Approved**, **Rejected**, **Withdrawn**, or **Paid** bookings → **422** |
| 7E-E Duplicate payment | Owning vendor cannot re-submit proof when invoice is **Paid** → **422** |
| 7E-F Duplicate approve | Manager cannot re-approve an already **Approved** booking → **422** |

---

## Access Control Security Matrix

Matrix reflects **implemented** CMart behavior and what Test 7 (plus supporting Phases 1–6) exercises. Labels: **Allowed**, **Denied**, **Own record only**, **Manager only**, **Public**, **Protected**.

| Action | Guest / Unauthenticated | Vendor / Community | Staff | Manager |
|--------|-------------------------|-------------------|-------|---------|
| Access public landing page (`/`, `/community`) | **Public** | **Allowed** | **Allowed** | **Allowed** |
| Access vendor dashboard (`/dashboard`) | **Denied** | **Allowed** (own account) | **Denied** | **Denied** |
| Access staff booking list (`/admin`, staff registry APIs) | **Denied** | **Denied** | **Allowed** | **Allowed** |
| Review booking as staff (Revision, registry view) | **Denied** | **Denied** | **Allowed** | **Allowed** |
| Forward booking to manager (`Pending_Staff` → `Pending_Boss`) | **Denied** | **Denied** | **Allowed** | **Allowed** |
| Approve / reject booking as manager | **Denied** | **Denied** | **Denied** | **Manager only** |
| Verify payment (`PATCH /bookings/{id}/verify-payment`) | **Denied** | **Denied** | **Allowed** | **Allowed** |
| View own vendor booking | **Denied** | **Own record only** | — | — |
| View another vendor's booking (vendor APIs / UI) | **Denied** | **Denied** | — | — |
| View any booking in staff registry | **Denied** | **Denied** | **Allowed** | **Allowed** |
| Delete booking (`DELETE /bookings/{id}`) | **Denied** | **Denied** | **Denied** | **Manager only** |
| Withdraw own eligible booking | **Denied** | **Own record only** (`Pending_Staff`, `Pending_Boss`, `Needs_Revision`) | **Denied** | **Denied** |
| Withdraw terminal / paid booking | **Denied** | **Denied** (422) | **Denied** | **Denied** |
| Submit payment proof | **Denied** | **Own record only** | **Denied** | **Denied** |
| Resubmit payment proof after **Paid** | **Denied** | **Denied** (422) | **Denied** | **Denied** |

**Notes**

- Payment verification is routed under staff/manager middleware (`role:staff,manager,...`), not vendor or guest.
- Booking **delete** is behind **boss** middleware (manager-only in E2E seed: `admin@cmart.com`).
- Staff and manager share the `/admin` workspace; manager-only controls (Approve, Reject, Delete) are role-gated in UI and API.

---

## Role vs Action Table

Simplified rules the suite is designed to enforce:

| Role | Can do | Cannot do |
|------|--------|-----------|
| **Guest** | Browse public routes (landing, community portal, login/register) | Access `/dashboard`, `/admin`, vendor booking APIs, staff APIs, boss APIs, or mutate bookings without authentication |
| **Vendor** | Book events; view and manage **own** bookings, invoices, receipts, and passes; withdraw eligible unpaid bookings; submit payment proof for **own** approved bookings | Access staff/manager workspace; view or mutate **another vendor's** records; withdraw terminal/paid bookings; resubmit proof after **Paid** |
| **Staff** | Log into `/admin`; search staff registry; **Review** (Revision) and **Forward** `Pending_Staff` bookings; **Verify Paid** on pending-verification invoices | **Approve**, **Reject**, or **Delete** bookings; call boss-only analytics/audit APIs; delete bookings via API |
| **Manager** | Everything staff can do in the shared workspace, plus **Approve**, **Reject**, registry **Delete** (UI present), boss analytics/audit APIs, and `DELETE /bookings/{id}` | Duplicate-approve already **Approved** bookings (422); actions outside the state machine |

**Cross-cutting:** Destructive and terminal-state mutations are blocked regardless of UI affordances — Test 7E asserts API-level rejection even when buttons are hidden.

---

## What Test 7 Proves

The suite provides **browser- and API-level regression confidence** that:

1. **Staff and manager permissions are separated** — staff see Forward/Revision but not Approve/Delete; staff receive **403** on delete and boss endpoints; managers can approve/reject and reach boss APIs (**7A**, **7B**).
2. **Vendor ownership boundaries are enforced** — Vendor A cannot list, read, PDF-export, pass-fetch, or payment-submit against Vendor B resources by UI search or direct ID guessing (**7C**, **7E-C**).
3. **Guest access to protected areas is blocked** — protected frontend routes redirect or hide dashboard roots; list and resource APIs return unauthorized responses without leaking protected payloads (**7D**).
4. **Dangerous actions are guarded** — delete (wrong role/guest), invalid withdraw on terminal statuses, duplicate payment submission after **Paid**, and duplicate manager approval are rejected with appropriate HTTP statuses (**7E**).
5. **Security work did not break core flows** — vendor/staff/manager login smokes and the full **28-test** E2E suite remain green alongside frontend build and Laravel feature/unit tests.

Each Test 7 spec uses **unique E2E markers** (e.g. `E2E-T7A-STAFF-GUARD-{timestamp}`) so only intentionally created bookings are probed.

---

## What Test 7 Does Not Overclaim

This documentation is intentionally honest about scope limits:

- **Not a penetration test** — no fuzzing, injection campaigns, rate-limit abuse, or session-fixation attacks.
- **Does not replace backend authorization tests** — Laravel `php artisan test` remains the source of truth for policy/unit coverage; E2E complements it with integrated UI+API checks.
- **Does not prove every API endpoint is secure** — only representative high-risk routes exercised by the current specs (bookings, vendor resources, boss analytics, payment submit/verify, delete).
- **Validates selected high-risk flows** — booking pipeline, payment proof, ownership isolation, and management workspace; not marketplace, news, UUM, or every future module.
- **Future modules need their own access-control tests** — new features should add E2E guards and backend tests before claiming security coverage.

---

## Environment Notes

### E2E frontend port

Use the dedicated Vite server on port **5175**:

```bash
cd frontend && npm run dev:e2e
```

Set `E2E_BASE_URL=http://localhost:5175` in `tests/e2e/.env.e2e`. Preflight fails fast if the wrong port is configured.

### Credentials (`.env.e2e`)

Copy from `tests/e2e/.env.e2e.example` and fill **all** role passwords:

| Variable | Purpose |
|----------|---------|
| `E2E_VENDOR_EMAIL` / `E2E_VENDOR_PASSWORD` | Vendor A (default: `vendor@cmart.com`) — **7A**, **7C**, **7D**, **7E** |
| `E2E_VENDOR_B_EMAIL` / `E2E_VENDOR_B_PASSWORD` | Vendor B (seed: `vendor_b@cmart.com`, `password123`) — **required for 7C and 7E-C** |
| `E2E_STAFF_EMAIL` / `E2E_STAFF_PASSWORD` | Staff (`staff@cmart.com`) |
| `E2E_MANAGER_EMAIL` / `E2E_MANAGER_PASSWORD` | Manager (`admin@cmart.com`) |

**Vendor B** must exist in the database (`php artisan db:seed`) and in `.env.e2e` for ownership isolation tests.

### Database and events

```bash
cd backend
php artisan migrate
php artisan db:seed
```

The seeder creates carboot events at **+7, +14, +21 days** so booking specs do not fail on stale dates. Re-seed if events pass.

**Warning:** Do **not** run `migrate:fresh --seed` on `cmart_db` if you keep manual demo data (feedback, custom events, uploaded images). Use a separate database such as `cmart_e2e_db` for destructive E2E resets. See [Database safety](./README.md#database-safety-read-before-seeding) in the main E2E README.

### Session cleanup

Global setup clears **cookies**, **localStorage**, and **sessionStorage** between specs to prevent token bleed between vendor/staff/manager runs.

### Backend

Laravel API at `http://127.0.0.1:8000` (or `E2E_API_BASE_URL`). MySQL must be running — preflight catches HTTP 500 from a down database.

---

## Verification Commands

From the `frontend` directory. Start `php artisan serve`, `npm run dev:e2e`, and ensure `.env.e2e` is configured first.

### Full suite and login smokes

```bash
npm run test:e2e:headless
```

Login smokes only (vendor, staff, manager):

```bash
npm run test:e2e:headless -- auth.login.spec.js auth.staff-login.spec.js auth.manager-login.spec.js
```

### Test 7 focused runs

```bash
npm run test:e2e:headless -- access.staff-action-guard.spec.js
npm run test:e2e:headless -- access.manager-confirmation.spec.js
npm run test:e2e:headless -- access.vendor-ownership-guard.spec.js
npm run test:e2e:headless -- access.guest-protection.spec.js
npm run test:e2e:headless -- access.destructive-action-protection.spec.js
```

> **Note:** There are no separate `test:e2e:test7a` … `test:e2e:test7e` npm scripts. Pass the spec filename after `--` as shown above (handled by `tests/e2e/run.js`).

### Build and backend tests

```bash
npm run build
cd ../backend && php artisan test
```

---

## Spec ↔ Helper Map

| Spec | Primary helpers |
|------|-----------------|
| 7A | `access-guards.js`, `staff-bookings.js`, `auth.js` |
| 7B | `access-guards.js`, `staff-bookings.js`, `approval-pipeline.js` |
| 7C | `vendor-ownership.js`, `booking.js`, `auth.js` |
| 7D | `guest-access.js`, `session.js` |
| 7E | `destructive-guards.js`, `payment-guards.js`, `vendor-ownership.js` |

Per-flow troubleshooting and marker lists remain in [README.md](./README.md#test-7e-destructive-action-protection) (sections Test 7A–7E).

---

## Changelog

| Date | Event |
|------|-------|
| 2026-06-28 | Test 7 suite completed; 28/28 E2E, build, and `php artisan test` verified; wrap-up document published |
