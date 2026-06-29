# CMart E2E Tests (Selenium WebDriver)

Phase 1 smoke tests for the CMart frontend using Selenium WebDriver, Mocha, and Chrome.

## Final E2E Testing Evidence Report

**[E2E-TESTING-EVIDENCE-REPORT.md](./E2E-TESTING-EVIDENCE-REPORT.md)** is the consolidated evidence document for the full Selenium E2E automation layer — from login smokes and vendor booking (Phase 1–2) through payment/withdrawal workflows (Phases 4–6), access control (Test 7), and public route safety (Test 8A).

**Latest verified checkpoint (29 June 2026):** full combined suite **`npm run test:e2e:headless`** — **34/34 passing** in **~18 minutes** (28 Test 7-era cases + 6 Test 8A cases). Use the report for internship/project evaluation, regression handoff, and verification commands.

## Prerequisites

1. **MySQL running** — XAMPP MySQL (or equivalent) on `127.0.0.1:3306`. E2E login fails with HTTP 500 when the database is down.
2. **Backend running** — Laravel API at `http://127.0.0.1:8000` (or your local API URL).
3. **Frontend running** — E2E Vite dev server at `http://localhost:5175` (`npm run dev:e2e`). Do not use the default `npm run dev` port (5173) unless you update `E2E_BASE_URL`.
4. **Test users seeded** — Run `cd backend && php artisan migrate --seed` so vendor/staff/manager accounts exist.
5. **Upcoming bookable event** — Booking specs need at least one carboot event with `ends_at` in the future and status not `Closed`. The seeder creates events at +7, +14, and +21 days; re-run `php artisan db:seed` if dates go stale.
6. **Google Chrome** — Installed on your machine. Selenium 4 downloads a matching ChromeDriver automatically.

## E2E port and preflight

The suite expects **`E2E_BASE_URL=http://localhost:5175`** in `tests/e2e/.env.e2e`.

Start the dedicated E2E frontend server (do not rely on a random Vite port):

```bash
# Terminal 1 — backend
cd backend && php artisan serve

# Terminal 2 — E2E frontend on the expected port
cd frontend && npm run dev:e2e
```

Before any spec runs, the suite performs a **preflight check** that:

- validates required `.env.e2e` credentials and that `E2E_BASE_URL` uses port **5175**
- confirms the frontend login page is reachable at `E2E_BASE_URL`
- confirms the backend API is reachable at `E2E_API_BASE_URL` (default `http://127.0.0.1:8000/api`)
- POSTs to `/api/auth/login` with the configured vendor credentials and fails fast when the backend returns HTTP 500 (database down) or 401/422 (wrong credentials)
- when booking specs are included, GETs `/api/events` and fails fast if no upcoming bookable carboot events exist

Login-only smoke specs (`auth.login.spec.js`, `auth.staff-login.spec.js`, `auth.manager-login.spec.js`) skip the bookable-event check. Full-suite and booking specs require upcoming events.

If preflight fails, the run stops immediately with a clear message instead of failing every login spec.

### Seeding for E2E booking specs

From the `backend` folder:

```bash
php artisan migrate
php artisan db:seed
```

The seeder uses **relative future dates** for carboot events (+7, +14, +21 days) and keeps rentable spaces available. Re-seed periodically if your local database events have passed.

Always start the E2E frontend on port **5175**:

```bash
cd frontend && npm run dev:e2e
```

Do not use `npm run dev` (port 5173) unless you also update `E2E_BASE_URL` in `tests/e2e/.env.e2e`.

### Troubleshooting: manager approve timeout

| Symptom | Fix |
|---------|-----|
| Approve test hits **300s** timeout in full suite | Ensure `npm run dev:e2e` (5175) and Laravel are running; the suite uses **eager** page-load strategy and 60s page-load timeout to avoid Selenium's default ~300s hang |
| Approve click but status stays **Awaiting Manager** | Re-run `php artisan db:seed`; check artifacts under `tests/e2e/artifacts/approve-*` for row/API status |
| Preflight: `no upcoming bookable carboot events found` | `cd backend && php artisan db:seed` |

### Troubleshooting: “No available event”

| Symptom | Fix |
|---------|-----|
| Preflight: `no upcoming bookable carboot events found` | `cd backend && php artisan db:seed` |
| Booking spec: `No available event found for E2E booking test` | Same — events in DB are past or `Closed`; re-seed or create an upcoming event in staff dashboard |
| Login specs pass but booking specs fail | Confirm `GET http://127.0.0.1:8000/api/events` returns at least one event with future `ends_at` |

### Session stability

Between specs, the global setup clears cookies, `localStorage`, and `sessionStorage` to prevent vendor/staff/manager token bleed.

Login helpers:

- always open a clean `/login` page before authenticating
- wait for the role-specific dashboard root test id
- retry once on transient failure
- save diagnostics under `tests/e2e/artifacts/` (screenshot + JSON) when login still fails

## Setup

From the `frontend` folder:

```bash
npm install
```

Copy the example env file and add your local test password:

```bash
cp tests/e2e/.env.e2e.example tests/e2e/.env.e2e
```

Edit `tests/e2e/.env.e2e`:

```env
E2E_BASE_URL=http://localhost:5175
E2E_API_BASE_URL=http://127.0.0.1:8000/api
E2E_VENDOR_EMAIL=vendor@cmart.com
E2E_VENDOR_PASSWORD=your-local-password
```

Do not commit `.env.e2e`. Real passwords stay on your machine only.

## How to run

Start the backend and frontend in separate terminals, then:

```bash
# Headed Chrome (default)
npm run test:e2e

# Explicit headed mode
npm run test:e2e:headed

# Focused spec (single or multiple)
npm run test:e2e:headless -- auth.login.spec.js
npm run test:e2e:headless -- auth.login.spec.js access.staff-action-guard.spec.js
```

## Current tests

| Spec | What it checks |
|------|----------------|
| `auth.login.spec.js` | Vendor/community user logs in and reaches `/dashboard` |
| `auth.staff-login.spec.js` | Staff user logs in and reaches `/admin` |
| `auth.manager-login.spec.js` | Manager user logs in and reaches `/admin` |
| `vendor.booking.spec.js` | Vendor logs in, submits a booking for an available event, and verifies it in My Bookings |
| `staff.booking-review.spec.js` | Staff safely reviews an E2E-marked booking only |
| `staff.booking-forward.spec.js` | Staff forwards an E2E-marked booking to the manager queue |
| `manager.booking-approval.spec.js` | Manager approves an E2E-marked Pending_Boss booking |
| `vendor.booking-approved.spec.js` | Vendor sees E2E-marked booking as Approved in My Bookings |
| `vendor.booking-withdraw.spec.js` | Regression: vendor withdraws an eligible E2E-marked booking and sees Withdrawn status |
| `vendor.invoice-visible-after-approval.spec.js` | Phase 5A: vendor sees invoice/payment record after manager approval |
| `vendor.payment-submit.spec.js` | Phase 5B: vendor submits payment proof and sees Pending Verification |
| `vendor.receipt-pass-after-paid.spec.js` | Phase 5C: vendor sees Paid receipt/pass after staff verifies payment |
| `vendor.payment-verification-pass-unlock.spec.js` | Test 6: full payment verification gate — receipt/pass locked until Verify Paid |
| `access.staff-action-guard.spec.js` | Test 7A: staff can use staff-safe booking actions but cannot access manager-only/destructive controls or APIs |
| `access.manager-confirmation.spec.js` | Test 7B: managers can access manager-only booking actions and API routes, including approve/reject |
| `access.vendor-ownership-guard.spec.js` | Test 7C: vendor data ownership isolation — Vendor A cannot access Vendor B booking, receipt, pass, or payment resources |
| `access.guest-protection.spec.js` | Test 7D: guest/unauthenticated protection — protected dashboards, APIs, and resources require login |
| `access.destructive-action-protection.spec.js` | Test 7E: destructive/state-changing action protection — wrong roles, guests, wrong owners, terminal statuses, duplicate payment |
| `public.public-route-safety.spec.js` | Test 8A: public route safety — guests can access intended public pages without over-locking after Test 7 |

## Test 7 — Access Control & Role Security Suite (wrap-up)

**[TEST-7-ACCESS-CONTROL.md](./TEST-7-ACCESS-CONTROL.md)** is the final milestone document for Test 7. It includes:

- Final verified status (**34/34** full E2E suite, login smokes, build; backend tests from Test 7 milestone)
- Coverage summary table (7A–7E)
- Access control security matrix and role-vs-action table
- What the suite proves and what it does **not** overclaim
- Environment notes (port 5175, Vendor B in `.env.e2e`, event seeding)
- Verification commands

Per-spec detail, markers, and troubleshooting for 7A–7E remain in the sections below.

## Test 8A — Public Route Safety & No Over-Locking

**Verified status:** Included in full combined suite **34/34 passing** in **~18 minutes** (**29 June 2026**, `npm run test:e2e:headless`). Test 8A alone also passed **6/6** in a focused rerun (40s). Details in [E2E-TESTING-EVIDENCE-REPORT.md](./E2E-TESTING-EVIDENCE-REPORT.md).

`public.public-route-safety.spec.js` verifies that **intended public pages remain accessible to guests** after Test 7 access-control hardening. It complements Test 7:

- **Test 7** — unauthorized users are blocked from protected dashboards, APIs, and destructive actions.
- **Test 8A** — guests are **not** accidentally blocked from legitimate public browsing.

### Covered public routes

| Route | What guests should see |
|-------|------------------------|
| `/` | Landing page (`public-landing-root`), hero, upcoming events section |
| `/calendar` | Public event calendar (`public-calendar-root`) |
| `/#news` | News section on landing (`public-news-root`) — no dedicated `/news` route |
| `/marketplace` | Carboot Reuse Preview (`marketplace-preview-root`), preview-only notice |

There is **no standalone public news URL**; news is embedded on the landing page at `/#news`.

### What it checks

| Flow | Guest expectation |
|------|-------------------|
| 8A-1 Landing | No redirect to `/login`; `Carboot@CMart` visible; public APIs `GET /events`, `/news`, `/marketplace/items` return **200** |
| 8A-2 Calendar | `/calendar` loads; `CMart Carboot Schedule` visible |
| 8A-3 News | `/#news` loads; `News & Updates` visible; `GET /news` returns **200** |
| 8A-4 Marketplace preview | `/marketplace` loads; preview-only wording visible; no checkout |
| 8A-5 Public details | Opens event/news/item detail modals when seeded cards exist; **skipped** if no cards (empty DB) |
| 8A-6 Control check | `/dashboard` and `/admin` still redirect guests to login (reuses Test 7D helpers) |

### What it proves

- Security hardening did not over-lock public marketing/browse routes.
- Public list APIs remain unauthenticated where intended.
- Protected dashboards stay protected (lightweight regression against Test 7D).

### What it does not overclaim

- Does not test every public route (`/community`, `/register` are out of scope for this spec).
- 8A-5 detail modals require seeded events/news/marketplace items; otherwise the case is **skipped**, not failed.
- Does not prove SEO, performance, or full content correctness — only access and basic visible content.

### Run focused

```bash
npm run test:e2e:headless -- public.public-route-safety.spec.js
```

### Environment notes

- Use `npm run dev:e2e` (port **5175**) and `php artisan serve`.
- Preflight skips the bookable-events requirement for this spec (unlike booking specs).
- Standard `.env.e2e` credentials are still validated by preflight.
- For full 8A-5 coverage, seed upcoming events, news posts, and marketplace items.

## Test 7E: Destructive action protection

`access.destructive-action-protection.spec.js` verifies destructive and state-changing actions are protected against wrong roles, guests, wrong owners, terminal statuses, and duplicate payment attempts.

### What it checks

| Flow | Guard |
|------|-------|
| 7E-A Staff delete | `DELETE /api/bookings/{id}` returns **403**; Delete UI absent; booking persists |
| 7E-B Guest delete | `DELETE /api/bookings/{id}` denied (**401** preferred; **403/404** accepted); booking unchanged |
| 7E-C Wrong vendor | Vendor A `PATCH /bookings/{id}/withdraw`, `POST /vendor/bookings/{id}/submit-payment`, `PATCH /vendor/bookings/{id}` denied (**403/404**); Vendor B snapshot unchanged |
| 7E-D Terminal withdraw | Owning vendor cannot `PATCH /bookings/{id}/withdraw` when **Approved**, **Rejected**, **Withdrawn**, or **Paid** — **422**; status/timestamps unchanged |
| 7E-E Duplicate payment | Owning vendor cannot re-submit proof when invoice is **Paid** — **422**; payment status unchanged |
| 7E-F Duplicate approve | Manager cannot `PATCH /bookings/{id}` to **Approved** when already **Approved** — **422**; status unchanged |

### Markers

- `E2E-T7E-STAFF-DELETE-GUARD-{timestamp}`
- `E2E-T7E-GUEST-DELETE-GUARD-{timestamp}`
- `E2E-T7E-VENDOR-OTHER-MUTATION-{timestamp}`
- `E2E-T7E-TERMINAL-WITHDRAW-{state}-{timestamp}`
- `E2E-T7E-DUPLICATE-PAYMENT-{timestamp}`
- `E2E-T7E-DUPLICATE-APPROVE-{timestamp}`

### Run focused

```bash
npm run test:e2e:headless -- access.destructive-action-protection.spec.js
```

## Test 7D: Guest / unauthenticated protection

`access.guest-protection.spec.js` verifies unauthenticated users cannot access protected dashboards, booking resources, payment/receipt/pass endpoints, or management APIs.

### What it checks

| Area | Guest expectation |
|------|-------------------|
| Frontend routes | `/dashboard`, `/profile`, `/vendor-booking`, `/admin`, `/admin#bookings`, `/staff/verify-booking/{id}` redirect to login or show login form |
| Protected UI | Vendor dashboard, staff dashboard, and bookings panel roots are not visible |
| Vendor booking APIs | `GET /vendor/bookings`, `/vendor/history-receipts`, `/vendor/event-passes`, `/vendor/analytics/me` denied |
| Staff booking APIs | `GET /staff/bookings`, `/bookings`, `/invoices` denied |
| Resource-by-ID | `GET /vendor/bookings/{id}`, `/vendor/event-passes/{id}`, `/bookings/{id}`, `/bookings/{id}/pdf` denied |
| Payment proof | `POST /vendor/bookings/{id}/submit-payment` denied |
| Management APIs | `GET /boss/analytics/revenue`, `/boss/audit-logs`, `DELETE /bookings/{id}` denied |
| Booking integrity | E2E booking still exists for vendor after guest denied DELETE |

### Session setup

Each check runs after full session cleanup (cookies, localStorage, sessionStorage). No auth token is present.

### Expected denial behavior

- **Frontend:** redirect to `/login` (with optional `redirect` query) or visible login form; protected dashboard roots hidden.
- **API:** **401 Unauthorized** (Sanctum default for unauthenticated requests). **403** or **404** also accepted for resource-specific probes.

### Run focused

```bash
npm run test:e2e:headless -- access.guest-protection.spec.js
```

## Test 7C: Vendor data ownership guard

`access.vendor-ownership-guard.spec.js` verifies vendor data ownership isolation. **Vendor A cannot access Vendor B booking, receipt, pass, or payment resources** by UI search or direct API ID guessing.

### What it checks

| Area | Vendor A expectation |
|------|----------------------|
| My Bookings UI | Vendor B marker and booking row are not visible |
| List APIs | `GET /vendor/bookings`, `/vendor/history-receipts`, `/vendor/event-passes` exclude Vendor B data |
| Direct booking access | `GET /vendor/bookings/{id}` denied (**403** or **404**) |
| Event pass | `GET /vendor/event-passes/{id}` denied |
| Receipt/PDF | `GET /bookings/{id}/pdf` denied |
| Staff route probe | `GET /bookings/{id}` denied (community role) |
| Payment proof | `POST /vendor/bookings/{id}/submit-payment` denied |
| Vendor B integrity | Booking still exists for Vendor B after denied attempts |

### Test users

- **Vendor A** — existing `E2E_VENDOR_EMAIL` (default seed: `vendor@cmart.com`)
- **Vendor B** — `E2E_VENDOR_B_EMAIL` / `E2E_VENDOR_B_PASSWORD` (seed: `vendor_b@cmart.com`)

Run `php artisan db:seed` after pulling if Vendor B is missing.

### Marker

Uses `E2E-T7C-VENDOR-B-OWNERSHIP-{timestamp}` via `e2eT7CVendorBOwnershipMarker()`.

### Run focused

```bash
npm run test:e2e:headless -- access.vendor-ownership-guard.spec.js
```

## Test 7B: Manager access confirmation

`access.manager-confirmation.spec.js` verifies that a **manager** user can access the management workspace, use manager-level booking actions on forwarded bookings, and reach representative manager-only APIs.

### What it checks

| Area | Manager expectation |
|------|---------------------|
| Approve flow | `Pending_Staff` → staff forward → manager **Approve** → **Approved** |
| Reject flow | Separate booking forwarded to manager → **Reject** → **Rejected** |
| Manager queue UI | **Approve** and **Reject** visible for `Pending_Boss` bookings |
| Registry UI | **Delete** control visible (not clicked) |
| Manager-only APIs | `GET /api/boss/analytics/revenue` and `GET /api/boss/audit-logs` return **200** |

### Markers

- `E2E-T7B-MANAGER-APPROVE-{timestamp}`
- `E2E-T7B-MANAGER-REJECT-{timestamp}`

### Run focused

```bash
node node_modules/mocha/bin/mocha.js tests/e2e/specs/access.manager-confirmation.spec.js --timeout 300000 --file tests/e2e/setup.js
```

## Test 7A: Staff vs manager action guard

`access.staff-action-guard.spec.js` verifies that a **staff** user can access the management workspace and staff-safe booking workflow actions, but cannot use manager-only destructive controls or privileged APIs.

### What it checks

| Area | Staff expectation |
|------|-------------------|
| Management login | Staff reaches `/admin` and bookings panel loads |
| E2E booking visibility | Unique marker is searchable in staff bookings |
| Staff-safe actions | **Forward** and **Revision** are visible for `Pending_Staff` bookings |
| Manager-only UI | **Approve** and **Delete** controls are not visible |
| DELETE API | `DELETE /api/bookings/{id}` returns **403 Forbidden** |
| Booking persistence | Booking still exists after denied DELETE |
| Manager-only APIs | `GET /api/boss/analytics/revenue` and `GET /api/boss/audit-logs` return **403** |

### Marker

Uses `E2E-T7A-STAFF-GUARD-{timestamp}` via `e2eT7AStaffGuardMarker()`.

### Run focused

```bash
node node_modules/mocha/bin/mocha.js tests/e2e/specs/access.staff-action-guard.spec.js --timeout 180000 --file tests/e2e/setup.js
```

### Troubleshooting

- Requires a fresh `Pending_Staff` booking (`allowReuse: false`).
- Staff credentials: `E2E_STAFF_EMAIL` / `E2E_STAFF_PASSWORD` in `tests/e2e/.env.e2e`.
- Manager success flows are covered separately in Test 7B (not this spec).

## Phase 5C: Vendor receipt/pass after verified paid payment

`vendor.receipt-pass-after-paid.spec.js` extends the Phase 5B flow with **management-side payment verification**. It confirms that final receipt and event pass visibility unlock only after CMart marks the payment **Paid** — not when the vendor uploads proof.

### Distinction: Pending Verification vs Paid

| Stage | Who acts | Payment status | Receipt / pass |
|-------|----------|----------------|----------------|
| After manager approval | — | **Unpaid** | Invoice visible only |
| After vendor submits proof | Vendor | **Pending Verification** | Invoice only; pass locked |
| After staff/manager verifies | CMart staff or manager | **Paid** | **View Receipt** + event pass unlocked |

Vendor proof upload must **never** jump directly to Paid. Phase 5C fails if pass/receipt unlock without the management **Verify Paid** action.

### Flow validated

1. Unique E2E-marked booking is created and approved (staff forward → manager approve).
2. Vendor submits payment proof (Phase 5B helpers).
3. Status becomes **Pending Verification**.
4. Staff logs in, locates the booking in the **All Bookings Registry**, and clicks **Verify Paid**.
5. Vendor logs back in and sees **Paid** in **Booking Receipts**.
6. **View Receipt** is available (not just View Invoice).
7. **My Event Passes** shows **View Full Pass**; modal includes booking reference, event, booth/tapak, and **Paid** indicator.

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Database migrated (`php artisan migrate`) including invoice payment-proof columns
- Public storage linked (`php artisan storage:link`) so proof uploads and links work
- Approved vendor, staff, and manager credentials in `.env.e2e`
- Fixture: `tests/e2e/fixtures/payment-proof.png`
- At least one upcoming bookable event

### Troubleshooting

- **Pending Verification stuck:** Confirm vendor proof upload succeeded (Phase 5B). Check `storage/app/public` and run `php artisan storage:link` if proof links 404.
- **Verify Paid button missing:** Registry row must be **Approved** with invoice status **Pending Verification**. Refresh the staff bookings registry.
- **Vendor still shows Pending Verification:** Dashboard payment history may need a refresh; the test reloads the dashboard and searches by booking ID.
- **Pass button missing after Paid:** Event pass QR unlock requires **Approved + Paid**. Ensure staff verification completed via UI/API (not direct DB edits).
- **Stale UI / debounced search:** Staff registry search is debounced (~300ms). Helpers wait for matching rows explicitly.
- **Headless modal clicks:** Verify Paid uses JavaScript clicks (same pattern as withdraw and payment submit modals).

## Phase 5B: Vendor payment submission

`vendor.payment-submit.spec.js` runs the full safe approval pipeline, then logs in as the vendor and submits **payment proof** for the E2E-marked booking from **Booking Receipts**.

After submission, the invoice status becomes **Pending Verification** (the system’s actual post-submit status). This phase does **not** test staff verification, final **Paid** status, or QR pass/receipt unlock (see Phase 5C).

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Database migrated (`php artisan migrate`) including invoice payment-proof columns
- Public storage linked (`php artisan storage:link`) for proof uploads
- Approved vendor, staff, and manager credentials in `.env.e2e`
- Fixture file present: `tests/e2e/fixtures/payment-proof.png`
- At least one upcoming bookable event

### Expected result

- Vendor **Booking Receipts** shows **Unpaid** before submission.
- After uploading `payment-proof.png` and clicking **Submit payment**, status becomes **Pending Verification**.
- **Submit Payment** is no longer available for that booking row.

### Safety note

- Only the booking with the unique E2E marker from this test is created, approved, and paid.
- Marker is cross-checked in **My Bookings** before payment actions run.
- No unrelated bookings or invoices are modified.

## Phase 5A: Vendor invoice/payment record after approval

`vendor.invoice-visible-after-approval.spec.js` runs the full safe approval pipeline (vendor booking → staff forward → manager approve), then logs back in as the vendor and confirms the related **invoice/payment record** is visible in **Booking Receipts** on the dashboard.

This phase verifies that an approved booking surfaces in the payment workflow. It does **not** test payment proof upload, marking paid, or final receipt/pass visibility (see Phase 5B and 5C).

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- Staff credentials in `.env.e2e`
- Manager credentials in `.env.e2e`
- At least one upcoming bookable event

### Expected result

- Vendor **My Bookings** shows **Approved** for the unique E2E-marked booking.
- **Booking Receipts** shows a matching payment record with event label, booth/tapak, **Unpaid** status, amount, and **View Invoice** action.

### Safety note

- Only the booking with the unique E2E marker from this test is created, forwarded, approved, and verified.
- The test cross-checks the E2E marker in **My Bookings** before locating the payment record by booking reference.
- It does not upload payment proof, mark paid, or open QR passes.

## Phase 4 Plus / Regression: Vendor withdraw booking flow

`vendor.booking-withdraw.spec.js` is a **regression test** that extends the Phase 4 booking pipeline. It creates a fresh E2E-marked vendor booking in an eligible unpaid status (typically **Pending_Staff**), then withdraws **only that marked booking** from **My Bookings**.

This is **not** Phase 5. The Phase 5 milestone is reserved for invoice/payment/receipt/pass workflows:

- **Phase 5A:** Vendor can view invoice/payment record after approval
- **Phase 5B:** Vendor payment action / upload proof / submit payment
- **Phase 5C:** Vendor receipt / pass visibility after verified paid payment

This withdraw spec does **not** test staff/manager workflows, invoice, payment, or QR pass flows.

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- At least one upcoming bookable event

### Expected result

- Vendor **My Bookings** shows **Withdrawn** for the unique E2E-marked booking created in this test run.
- The **Withdraw Booking** action is no longer visible in booking details after withdrawal.

### Safety note

- Only the booking with the unique E2E marker from this test is created and withdrawn.
- Marker is checked before opening details, withdrawing, and verifying status.
- Withdraw is allowed only for eligible unpaid statuses (`Pending_Staff`, `Pending_Boss`, `Needs_Revision`).
- No unrelated bookings are touched.

## Phase 4C: Vendor-side Approved confirmation

`vendor.booking-approved.spec.js` runs the full safe approval pipeline (vendor booking → staff forward → manager approve), then logs back in as the vendor and confirms **Approved** in **My Bookings** for the same E2E marker.

This phase does **not** test invoice, payment, QR pass, or other post-approval flows.

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- Staff credentials in `.env.e2e`
- Manager credentials in `.env.e2e`
- At least one upcoming bookable event

### Expected result

- Vendor **My Bookings** shows **Approved** for the unique E2E-marked booking created in this test run.

### Safety note

- Only the booking with the unique E2E marker from this test is created, forwarded, approved, and verified.
- Marker is checked before staff forward, manager approve, and vendor confirmation.
- No unrelated bookings are touched.

## Phase 4B: Manager final approval

`manager.booking-approval.spec.js` creates a fresh E2E-marked vendor booking, staff forwards it to the manager queue, then a manager logs in and approves **only that marked booking**.

This phase does **not** test vendor-side Approved confirmation yet (see Phase 4C). It does not test invoice, payment, or QR pass flows.

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- Staff credentials in `.env.e2e`
- Manager credentials in `.env.e2e` (`E2E_MANAGER_EMAIL`, `E2E_MANAGER_PASSWORD`)
- At least one upcoming bookable event

### Expected result

- Booking status becomes **Approved** after manager approval.

### Safety note

- Only bookings containing the unique E2E marker from this test run are searched and approved.
- The test verifies the marker in the row before clicking **Approve**.
- It does not delete bookings or touch unrelated records.
- If the booking is already **Approved** with the same verified marker, the test passes without re-approving.

### UI path vs API fallback

The test clicks **Approve** in the manager queue first. If headless Chrome does not reflect the status update in time, an authenticated API fallback approves the same verified E2E-marked `Pending_Boss` booking. The fallback refuses non-E2E or non-manager-pending bookings.

## Phase 4A: Staff forward to manager queue

`staff.booking-forward.spec.js` creates a fresh vendor booking with a unique E2E marker, logs in as staff, and forwards **only that marked booking** to the manager/boss queue.

This phase does **not** test manager login or manager approval (see Phase 4B).

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- Staff credentials in `.env.e2e`
- At least one upcoming bookable event

### Expected result

- Booking status becomes **Pending_Boss** (UI label: **Awaiting Manager** or equivalent manager-pending status).

### Safety note

- Only bookings containing the E2E marker text (for example `Automated Selenium booking test`) are searched and modified.
- The test verifies the marker in the row before clicking **Forward**.
- It does not delete bookings, approve bookings, or touch unrelated records.
- If the booking is already in manager-pending status with the same marker, the test passes without re-forwarding.

### UI path vs API fallback

The test clicks **Forward** in the staff queue first. If headless Chrome does not reflect the status update in time, an authenticated API fallback forwards the same verified E2E-marked booking to `Pending_Boss`.

## Phase 3: Staff booking review

`staff.booking-review.spec.js` creates (or reuses) a vendor booking with a unique E2E marker, then logs in as staff and updates **only that marked booking**.

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- Staff credentials in `.env.e2e`
- At least one upcoming bookable event

### Default safe action

Phase 3 always uses the **Revision** action (`needs_revision`) and expects status **Needs Revision**. The env variable below is kept for documentation and optional custom scripts:

```env
E2E_STAFF_BOOKING_ACTION=needs_revision
```

### Optional manager approval

```env
E2E_STAFF_BOOKING_ACTION=approve
```

This only works when the logged-in user can see the **Approve** button (manager-level access). Standard staff accounts should keep the default `needs_revision` action.

### Safety note

- The test searches for bookings containing the E2E marker text (for example `Automated Selenium booking test`).
- It refuses to act on rows that do not contain the marker.
- It does not delete bookings or touch unrelated records.

### Recommended setup

1. Start backend and frontend.
2. Fill in vendor and staff credentials in `tests/e2e/.env.e2e`.
3. Ensure at least one upcoming event exists for vendor booking.
4. Run `npm run test:e2e`.

## Phase 2: Vendor booking flow

`vendor.booking.spec.js` proves that an approved vendor/community user can book an upcoming event and see the booking on the dashboard.

### Requirements

- The test vendor must be **approved** (`vendor_status = approved`) so `/vendor-booking` is accessible.
- At least **one upcoming active/bookable event** must exist in the database (`status` not `Closed`, `ends_at` in the future).

### Recommended setup

1. Start backend and frontend.
2. Log in as staff and open the admin workspace.
3. Create an upcoming carboot event with status **Available** (or **Almost Full**).
4. Confirm the event appears on the vendor booking page (`/vendor-booking`).
5. Run `npm run test:e2e`.

### Optional env values

```env
E2E_BOOKING_EVENT_NAME=CMart Weekly Carboot
E2E_BOOKING_BUSINESS_NAME=E2E Test Vendor
E2E_BOOKING_CATEGORY=Food & Beverages
E2E_BOOKING_DETAILS=Automated Selenium booking test
```

- If `E2E_BOOKING_EVENT_NAME` is set, the test targets that event.
- If it is empty, the test picks the first bookable event in the dropdown.
- If no bookable event exists, the test fails with a clear setup message.

### Duplicate bookings

If the vendor already has a booking for the selected event, the test treats an existing row in **My Bookings** as a pass instead of failing immediately.

## Troubleshooting

### Browser not opening

- Confirm Google Chrome is installed.
- Try headed mode: `npm run test:e2e:headed`
- On Windows, allow Chrome through any firewall or security software blocking automation.

### Login failed / stuck on login page

- Verify `E2E_VENDOR_EMAIL` and `E2E_VENDOR_PASSWORD` in `.env.e2e`.
- Confirm the user exists in your local MySQL database and has role `community`.
- Check the Laravel API is reachable from the frontend (network tab or API logs).

### Backend not running

- Start Laravel: `php artisan serve` from the backend project root.
- Login calls `/api/auth/login`; without the API, the form will not redirect to the dashboard.

### Missing env variables

If you see an error about `E2E_VENDOR_EMAIL` or `E2E_VENDOR_PASSWORD`, create `tests/e2e/.env.e2e` from the example file and fill in values.

### Frontend not running

- Start Vite: `npm run dev` from the `frontend` folder.
- If you use a different port, update `E2E_BASE_URL` in `.env.e2e`.

### No available events for booking test

- Create a future event from the staff admin dashboard.
- Confirm `GET /api/events` returns at least one non-closed event with a future `ends_at`.
- Optionally set `E2E_BOOKING_EVENT_NAME` to match the event title.

### Booking test cannot find submitted booking

- Ensure the vendor account is approved.
- Check My Bookings manually after a manual booking attempt.
- Re-run with a fresh `E2E_BOOKING_DETAILS` marker or clear old test bookings if needed.

### Staff review test cannot find E2E booking

- Confirm vendor booking creation succeeded (run Phase 2 spec alone first if needed).
- Search staff bookings manually for `Automated Selenium booking test`.
- Ensure the booking is still in `Pending_Staff` if you expect the Revision action to be available.

### Staff review action unavailable

- Phase 3 requires a `Pending_Staff` booking in the staff queue for the **Revision** action.
- `approve` (via `E2E_STAFF_BOOKING_ACTION`) requires manager-level credentials and a visible Approve button.

### Staff forward action unavailable (Phase 4A)

- **Forward** requires a `Pending_Staff` booking in the staff queue.
- If the E2E booking is already `Needs_Revision` or `Pending_Boss`, create a fresh booking (the spec uses `allowReuse: false`).

### Manager approval unavailable (Phase 4B)

- **Approve** requires a `Pending_Boss` booking in the manager queue.
- Ensure `E2E_MANAGER_EMAIL` and `E2E_MANAGER_PASSWORD` are set (manager or boss role).
- Seeded demo manager: `admin@cmart.com` (after `php artisan db:seed`).

### Vendor Approved status not visible (Phase 4C)

- Confirm manager approval succeeded (run Phase 4B spec alone if needed).
- Use the **All** bookings filter on My Bookings (default).
- Search using the full unique E2E marker from the test run.

### Vendor withdraw action unavailable (Phase 4 Plus / Regression)

- **Withdraw Booking** requires an eligible unpaid status: `Pending_Staff`, `Pending_Boss`, or `Needs_Revision`.
- Paid bookings cannot be withdrawn from the vendor dashboard.
- Approved, Rejected, or already **Withdrawn** bookings cannot be withdrawn again.
- The spec uses `allowReuse: false` so each run creates a fresh eligible booking.

### Vendor Withdrawn status not visible (Phase 4 Plus / Regression)

- Confirm the booking was created successfully (run Phase 2 spec alone if needed).
- Use the **All** bookings filter on My Bookings (default).
- Search using the full unique E2E marker from the test run.
- If the withdraw modal stays open, check the browser console and Laravel API logs for `/bookings/{id}/withdraw` errors.

### Vendor payment record not visible (Phase 5A)

- Confirm manager approval succeeded (run Phase 4C spec alone if needed).
- Scroll to **Booking Receipts** on the vendor dashboard (below My Bookings).
- Search payment records using the booking ID from **My Bookings** (for example `#123`).
- Ensure the booking is **Approved**; booth/tapak appears on the receipt row only after approval.
- Confirm `GET /api/vendor/history-receipts` returns a record with `invoice_available: true` and `payment_status: Unpaid`.
- Phase 5A does not require payment to be marked paid yet.

### Payment proof upload fails (Phase 5B)

- Confirm `tests/e2e/fixtures/payment-proof.png` exists (1×1 PNG used by Selenium `sendKeys` upload).
- Run `php artisan storage:link` so `storage/app/public/payment-proofs` is web-accessible.
- Ensure the booking is **Approved** and invoice status is **Unpaid** before submission.
- Headless Chrome must allow file upload to hidden inputs; the test targets `[data-testid="payment-proof-input"]`.
- If the modal stays open, check Laravel logs for `POST /api/vendor/bookings/{id}/submit-payment` validation errors.

### Pending Verification status not visible (Phase 5B)

- Refresh **Booking Receipts** or re-run the spec; the helper reloads the dashboard after submit.
- Search by booking ID (for example `#123`) in the receipt search field.
- Confirm the migration adding `Pending Verification` to `invoices.payment_status` has been applied.
