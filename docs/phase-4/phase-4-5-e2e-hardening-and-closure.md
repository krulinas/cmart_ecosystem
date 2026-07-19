# Phase 4.5 — E2E, Hardening, and Closure

## Objective

Prove the Phase 4 item-reservation MVP end-to-end in a deterministic browser
environment, harden only reproducible integration defects, verify fixture
cleanup and financial isolation, then close Phase 4 when every release gate
passes.

## Exact Environment

| Surface | Value |
|---|---|
| Frontend | `http://127.0.0.1:5175` via `npm run dev:e2e` (`vite --mode e2e`) |
| Frontend API target | `VITE_API_BASE_URL=http://127.0.0.1:8011/api` (`frontend/.env.e2e`) |
| Backend | `php artisan serve --host=127.0.0.1 --port=8011 --env=e2e` |
| App env / DB | `APP_ENV=e2e`, `DB_DATABASE=cmart_e2e_db` |
| Analytics | port `8001` unrelated and unused |
| Fixture marker | `E2E-P45` |
| Focused command | `npm run test:e2e:phase45` |

Fixture credentials are provisioned by
`php artisan e2e:item-reservation-fixtures create --json --env=e2e` and injected
into the Mocha child environment by `frontend/tests/e2e/run.js` before
preflight. Cleanup always runs after success, failure, or interruption.

## Per-Spec Results

| Spec | Journey | Result |
|---|---|---|
| `phase45.reserve-confirm.spec.js` | Community reserve → Organizer confirm charge → community/vendor refresh | PASS |
| `phase45.stale-conflict.spec.js` | Second reserver `409 item_already_reserved`, one active row | PASS |
| `phase45.pending-cancel.spec.js` | Pending cancel → item reopens → second reserver succeeds | PASS |
| `phase45.confirmed-cancel-expire.spec.js` | Confirmed no-refund cancel + Organizer manual expiry + audits | PASS (2 cases) |
| `phase45.vendor-completion.spec.js` | Vendor complete → marketplace 404, charge evidence retained, repeat 409 | PASS |
| `phase45.access-privacy.spec.js` | Guest/owner/management/community/unrelated-vendor boundaries | PASS |

Focused suite: **7 passing / 0 failing** (~3 minutes headless).

## Defects Found and Fixes

1. **Stale Phase 3.9 credentials in preflight** — Phase 4.5 runner now creates
   fixture-owned users and injects `E2E_VENDOR_*`, organizer, and management
   credentials before Mocha starts.
2. **Browser API pointed at `:8000` while fixtures lived on `:8011`** —
   `npm run dev:e2e` now uses Vite `--mode e2e` with
   `frontend/.env.e2e` → `VITE_API_BASE_URL=http://127.0.0.1:8011/api`.
3. **Reserver treated as vendor** — fixture reserver/competitor
   `vendor_status` set to `none` so community visitor home `/community` works.
4. **Organizer modal interaction flakiness** — hardened Selenium helpers:
   scroll/JS click for detail open and actions, Vue-compatible note/checkbox
   input, stale-safe form/audit waits.

No frozen lifecycle or authorization rules were changed.

## Authorization / Privacy / Concurrency / Isolation Evidence

* Guest reserve CTA routes to login; owner item privacy and unrelated-vendor
  reservation access return `403`/`404`.
* CMart Management cannot call Organizer reservation APIs and does not see
  Item Reservations nav (`workspace-nav-item-reservations` absent).
* Organizer operational queue retains reserver/vendor email; community/vendor
  presenters remain free of invoices/payment proof.
* Conflict path asserts one active held reservation and
  `error=item_already_reserved`.
* Each relevant journey snapshots the fixture booking via Organizer
  `GET /bookings/{id}` and asserts approval/withdrawal/invoice/site-selection/
  audit counts unchanged across reservation flows.

## Cleanup Counters

After the focused Phase 4.5 suite and explicit status check:

```json
{
  "database": "cmart_e2e_db",
  "users": 0,
  "events": 0,
  "items": 0,
  "reservations": 0,
  "audits": 0,
  "orphan_audits": 0,
  "active_locks": 0,
  "bookings": 0,
  "spaces": 0,
  "fixture_images": 0
}
```

Backend fixture command tests also prove create/status/cleanup JSON contracts,
marketplace eligibility, image removal, idempotent recreate, and reverse-FK
audit cleanup on `cmart_test`.

## Release Gates

| Gate | Result |
|---|---|
| `Phase45ItemReservationFixturesTest` | 4 passed |
| `php artisan test` | **374 passed**, 0 failed, 0 skipped, **2166 assertions** |
| `npm run test:unit` | **95 passed** |
| `npm run test:e2e:phase45` | **7 passing** |
| `npm run build` | passed |
| Oxlint | 0 errors |
| ESLint | 0 errors |
| Laravel Pint (`--dirty`) | passed |
| Fixture residue (`status` on `cmart_e2e_db`) | all zeros |

## Limitations (Explicitly Out of Scope)

* Automatic expiry scheduler / timeouts
* Payment gateway, proof upload, refunds, payouts, escrow
* Email / push notifications, chat, delivery
* New analytics dashboards
* Granting CMart Management reservation operational access

## Final Verdict

**PHASE 4 COMPLETE — RESOLVED**

Phase 4.0–4.5 acceptance criteria are met: listing foundation, concurrency
engine, manual charge lifecycle, role-safe UIs/completion, and deterministic
browser E2E with cleanup/isolation evidence all pass.
