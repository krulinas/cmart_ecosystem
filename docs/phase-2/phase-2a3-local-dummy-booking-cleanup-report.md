# Phase 2A.3 — Local Dummy Booking Data Cleanup Report

**Executed at:** 2026-07-14T09:30:33+08:00
**Environment:** `local`
**Database:** `cmart_db`
**Snapshot file:** `D:\Program Files\xampp\htdocs\cmart_ecosystem\backend\storage\app/phase-2a3-cleanup/snapshot-20260714-093032.json`

## Scope

- Identification rule: all remaining local bookings (owner-authorized Phase 2A.3 dummy baseline cleanup)
- Target booking IDs: `194, 195, 218, 525, 526, 527, 528, 529`
- Unrelated tables preserved: users, carboot_events, spaces, news_posts, feedbacks, event_user, user_booking_preferences

## Before / After Counts

| Metric | Before | After | Delta |
| ------ | -----: | ----: | ----: |
| bookings | 8 | 0 | -8 |
| invoices | 8 | 0 | -8 |
| booking_audit_logs | 10 | 0 | -10 |
| users | 6 | 6 | 0 |
| carboot_events | 6 | 6 | 0 |
| spaces | 2 | 2 | 0 |
| news_posts | 1 | 1 | 0 |
| feedbacks | 5 | 5 | 0 |
| event_user | 0 | 0 | 0 |
| user_booking_preferences | 1 | 1 | 0 |
| status_migration_audit | 3 | 0 | -3 |

## Deleted Record Counts

- Booking audit logs: **10**
- Status migration audit rows: **3**
- Invoices: **8**
- Bookings: **8**
- Payment-proof files deleted: **0**

## Payment Proof Handling

| Path | Reason skipped |
| ---- | -------------- |
| `demo-gateway/demo_fpx` | demo-gateway marker path (not a stored file) |

## Target Bookings Snapshot (summary)

| ID | Email | Event | Status | Payment | Details |
| --: | ----- | ----- | ------ | ------- | ------- |
| 194 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Approved | Paid | Jersey |
| 195 | vendor@cmart.com | CARBOOT SALE CHANGLUN | Withdrawn | Unpaid | Jersey |
| 218 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Approved | Paid | E2E-STAFF-PORTAL-ASSIST 1783475088736 |
| 525 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Approved | Unpaid | Automated Selenium booking test 1783824454243 |
| 526 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Pending_Organizer | Unpaid | Automated Selenium booking test 1783824506150 |
| 527 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Pending_Organizer | Unpaid | Automated Selenium booking test 1783825771134 |
| 528 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Approved | Unpaid | Automated Selenium booking test 1783825899587 |
| 529 | vendor@cmart.com | CMart Weekly Carboot (Almost Full) | Pending_Organizer | Unpaid | Automated Selenium booking test 1783825935981 |

## Integrity Checks

- Remaining bookings: **0** (expected 0 for clean baseline)
- Remaining invoices: **0**
- Users unchanged: **yes**
- Events unchanged: **yes**
- Spaces unchanged: **yes**
- News unchanged: **yes**
- Feedback unchanged: **yes**

## Commands

```bash
php artisan cmart:cleanup-local-dummy-bookings --force
```

## Regression Tests

```bash
php artisan test --filter="OrganizerBookingWorkflow|GovernanceAccessBoundary|VendorDemoPayment|CommunityVendorBooking|StaffOperationsSummary"
```

**Result:** 33 passed (85 assertions)

## Hard-Delete Guard Recommendation (for Phase 2A.9)

`DELETE /api/bookings/{id}` still hard-deletes and cascades invoices. Before durable allocation/financial history lands:

1. block operator hard-delete when an invoice or payment proof exists;
2. prefer terminal statuses over delete;
3. keep `cmart:cleanup-local-dummy-bookings` as the only local bulk wipe path.

## Notes

- No `migrate:fresh`, `db:wipe`, or table truncation was used.
- Demo gateway proof markers (`demo-gateway/...`) are not filesystem files and were skipped.
- Safety snapshots remain under `backend/storage/app/phase-2a3-cleanup/`.
- Reusable command: `php artisan cmart:cleanup-local-dummy-bookings` (requires `--force`; refuses non-local).
- Phase 2A.4+ may proceed against this clean booking baseline.
- Phase 2A.4 was not started.
