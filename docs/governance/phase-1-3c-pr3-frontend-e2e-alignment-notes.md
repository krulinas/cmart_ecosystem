# Phase 1.3C PR3 — Frontend Governance Alignment & E2E Rewrite

**Date:** 2026-07-12  
**Scope:** Frontend UI, API consumer paths, E2E suite — no DB ENUM or migration changes.

---

## 1. What changed

PR3 aligns visible product language, management workspace navigation, booking UI, API consumers, and E2E tests with the PR2 direct Organizer backend workflow.

- Removed Staff Portal Assist UI and `bossPreview` store
- Replaced two-stage Staff/Manager booking UI with `OrganizerBookingsPanel`
- Updated role/capability helpers to canonical roles only
- CMart Management UI limited to news/activities + generated reports
- Frontend switched to `/api/organizer/*` canonical routes
- Removed deprecated API response aliases (`pending_staff`, `pending_boss`, `pending_staff_review`)
- `/uum` redirects authenticated organizers to `/admin`
- Pass verify route: `/organizer/verify-booking/:id` (legacy `/staff/*` redirects)
- E2E suite rewritten for Organizer direct workflow

---

## 2. Staff/Manager/Boss wording removed

| Area | Before | After |
|------|--------|-------|
| Booking queue | Staff Approval Queue / Manager Approval Queue | Organizer Review Queue |
| Status chip | Awaiting Staff / Awaiting Manager | Pending Organizer Review |
| KPI cards | pending_staff / pending_boss | pending_organizer |
| Footer link | Staff Portal | Management Login |
| Vendor onboarding | CMart staff will review | Carboot Organizer will review |
| Admin dashboard | Staff Portal Assist banner/toggle | Removed |
| Workspace theme | staff/manager tier labels | organizer / cmart_management |

Historical audit display mapping retained in `legacyBookingStatusLabel()` and invoice PDF CSS for old log rows only.

---

## 3. New Organizer workflow UI

**Pipeline steps:** Submitted → Organizer Review → Approved / Revision / Rejected

**Organizer queue actions:** Approve, Reject, Revision (no Forward)

**Default pending status:** `Pending_Organizer`

**Component:** `frontend/src/views/dashboards/organizer/OrganizerBookingsPanel.vue`

**Test IDs:** `organizer-booking-*`, `management-dashboard-root`

---

## 4. CMart Management UI boundary

**Visible nav:** Venue News, Generated Reports (when analytics capability absent)

**Hidden nav:** Bookings, Feedback, Events, Revenue, Word Cloud, Audit Log

**Backend enforced:** 403 on `/api/bookings`, `/api/organizer/operations-summary`, raw analytics

**Demo accounts:** `staff@cmart.com`, `venue@cmart.com` → role `cmart_management`

---

## 5. Route aliases removed or retained

### Backend API

| Route | Status |
|-------|--------|
| `/api/organizer/operations-summary` | **Canonical** (frontend uses this) |
| `/api/organizer/feedbacks` | **Canonical** |
| `/api/organizer/bookings/registry` | **Canonical** |
| `/api/organizer/bookings/{id}/verify` | **Canonical** |
| `/api/organizer/bookings/{id}/check-in` | **Canonical** |
| `/api/staff/*` | **Retained** as deprecated compatibility aliases (same controllers) |

### Frontend routes

| Route | Status |
|-------|--------|
| `/organizer/verify-booking/:id` | **Canonical** |
| `/staff/verify-booking/:id` | Redirect → organizer path |
| `/uum` | Redirect → `/admin` (organizer) or management login |
| `/admin` | Unchanged |

---

## 6. Deprecated API aliases removed

Removed from backend JSON responses:

- `pending_staff`, `pending_boss` (BookingController summary)
- `pending_staff_review` (StaffOperationsController)

Canonical keys only:

- `pending_organizer` (booking summary)
- `pending_organizer_review` (operations summary / reports)

---

## 7. E2E specs added / updated / deleted

### Deleted (9)
- `staff.booking-forward.spec.js`
- `manager.staff-portal-assist.spec.js`
- `staff.booking-review.spec.js`
- `manager.booking-approval.spec.js`
- `access.staff-action-guard.spec.js`
- `access.manager-confirmation.spec.js`
- `auth.staff-login.spec.js`
- `auth.manager-login.spec.js`
- `access.staff-tools-snapshot.spec.js`
- `helpers/staff-bookings.js`

### Added (5)
- `auth.organizer-login.spec.js`
- `auth.cmart-management-login.spec.js`
- `organizer.booking-approval.spec.js`
- `organizer.booking-revision.spec.js`
- `access.cmart-management-boundary.spec.js`
- `helpers/organizer-bookings.js`

### Updated
- Vendor booking/payment specs → `Pending_Organizer`
- Access/destructive guard specs → Organizer / CMart Management boundaries
- `config/env.js` → `E2E_ORGANIZER_*`, `E2E_CMART_MANAGEMENT_*`

---

## 8. Tests run

**Backend:** `php artisan test` — **79 passed / 248 assertions**

**Frontend build:** `npm run build` — **succeeded**

**E2E:** Not executed in this session (requires local Selenium + `tests/e2e/.env.e2e` with seeded accounts and running dev servers on ports 5175/8000). Run:

```bash
cd frontend
npm run test:e2e:headless -- auth.organizer-login.spec.js
npm run test:e2e:headless -- auth.cmart-management-login.spec.js
npm run test:e2e:headless -- organizer.booking-approval.spec.js
npm run test:e2e:headless -- access.cmart-management-boundary.spec.js
```

---

## 9. Remaining technical debt

1. **Dead file names:** `StaffFeedbackPanel.vue`, `StaffEventsPanel.vue`, `StaffNewsPanel.vue`, `StaffVerifyBooking.vue` — internal names still say "Staff" but are Organizer/CMart Management operational panels; rename in a follow-up if desired.
2. **Backend `/api/staff/*` aliases** — retained for external compatibility; remove when no consumers remain.
3. **`UumDashboard.vue`** — no longer routed; safe to delete in cleanup PR.
4. **`BossRevenuePanel.vue` etc.** — internal "boss" naming for analytics panels; UI labels say Carboot Analytics.
5. **E2E README / evidence report** — may still mention old staff/manager phases; update when full E2E suite is re-run.

---

## 10. Recommended next phase

1. Delete `UumDashboard.vue` and backend `/api/staff/*` aliases after confirming no external consumers
2. Rename `staff/*` panel components to `organizer/*` or `management/*` filenames
3. Re-run full E2E suite and refresh `E2E-TESTING-EVIDENCE-REPORT.md`
4. Optional: rename `/admin` workspace path to `/management` in a dedicated low-risk PR
