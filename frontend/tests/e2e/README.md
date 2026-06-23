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
