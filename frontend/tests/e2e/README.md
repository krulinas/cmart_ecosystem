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

## Phase 3: Staff booking review

`staff.booking-review.spec.js` creates (or reuses) a vendor booking with a unique E2E marker, then logs in as staff and updates **only that marked booking**.

### Requirements

- Backend running: `php artisan serve`
- Frontend running: `npm run dev`
- Approved vendor credentials in `.env.e2e`
- Staff credentials in `.env.e2e`
- At least one upcoming bookable event

### Default safe action

```env
E2E_STAFF_BOOKING_ACTION=needs_revision
```

This clicks **Revision** on the staff queue row and expects status **Needs Revision**.

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

- `needs_revision` requires a `Pending_Staff` booking in the staff queue.
- `approve` requires manager-level credentials and a visible Approve button.
