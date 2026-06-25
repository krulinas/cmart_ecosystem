# CMart E2E Tests (Selenium WebDriver)

Phase 1 smoke tests for the CMart frontend using Selenium WebDriver, Mocha, and Chrome.

## Prerequisites

1. **Backend running** — Laravel API at `http://127.0.0.1:8000` (or your local API URL).
2. **Frontend running** — Vite dev server at `http://localhost:5173`.
3. **Test user in database** — A community/vendor account must exist locally (e.g. after `php artisan db:seed`).
4. **Google Chrome** — Installed on your machine. Selenium 4 downloads a matching ChromeDriver automatically.

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
E2E_BASE_URL=http://localhost:5173
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

# Headless Chrome
npm run test:e2e:headless
```

## Current tests

| Spec | What it checks |
|------|----------------|
| `auth.login.spec.js` | Vendor/community user logs in and reaches `/dashboard` |
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
