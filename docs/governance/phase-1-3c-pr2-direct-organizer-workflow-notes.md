# Phase 1.3C PR2 — Direct Organizer Booking Workflow Backend Cutover

**Date:** 2026-07-12  
**Scope:** Backend workflow cutover only. Frontend UI/E2E cleanup deferred to PR3.

---

## 1. What changed

PR2 completes the backend transition from the legacy two-stage booking pipeline (Staff → Manager/Boss) to **direct Organizer review**.

- Remaining `staff` users remapped to `cmart_management`
- `users.role` ENUM shrunk to four canonical roles
- Booking statuses `Pending_Staff` / `Pending_Boss` remapped to `Pending_Organizer`
- `bookings.approval_status` ENUM shrunk to six final values
- `BookingController` state machine rewritten for direct Organizer transitions
- Staff Portal Assist / manager final-stage logic removed from backend
- Carboot operational routes restricted to `organizer` + `super_admin`
- CMart Management retains generated reports + CMart activity management only
- Backend tests replaced/updated; `BookingStaffStageAssistTest` → `OrganizerBookingWorkflowTest`
- Minimal frontend helper sync (`managementRoles.js`, `managementCapabilities.js`, `bookingDisplay.js`)

---

## 2. Migrations added

| Migration | Audit table | Purpose |
|-----------|-------------|---------|
| `2026_07_12_000002_finalize_canonical_user_roles.php` | `role_cleanup_audit_202607_pr2` | Remap `staff→cmart_management`, safety-catch `manager/uum→organizer`, shrink `users.role` ENUM |
| `2026_07_12_000003_direct_organizer_booking_statuses.php` | `booking_status_migration_audit_202607` | Remap `Pending_Staff/Pending_Boss→Pending_Organizer`, shrink `bookings.approval_status` ENUM |

**Rollback limitation:** Users or bookings created after the audit snapshot may not restore to exact legacy roles/statuses on rollback. Audit tables preserve pre-migration rows for known IDs.

---

## 3. Role ENUM before / after

**Before PR2:**
```
enum('community','staff','manager','organizer','cmart_management','super_admin','uum')
```

**After PR2:**
```
enum('community','organizer','cmart_management','super_admin')
```

---

## 4. Booking status ENUM before / after

**Before PR2:**
```
enum('Pending_Staff','Needs_Revision','Pending_Boss','Approved','Rejected','Cancelled','Withdrawn')
```

**After PR2:**
```
enum('Pending_Organizer','Needs_Revision','Approved','Rejected','Cancelled','Withdrawn')
```

Default for new bookings: `Pending_Organizer`

---

## 5. Data remap results (local DB post-migrate)

**Users by role:**
```json
{"community":1,"organizer":2,"cmart_management":2,"super_admin":1}
```

- `staff@cmart.com` → `cmart_management` (demo CMart Management account retained)
- No users remain with `staff`, `manager`, or `uum`

**Bookings by status:**
```json
{"Approved":2,"Withdrawn":1}
```

- No bookings remain in `Pending_Staff` or `Pending_Boss` (none existed locally pre-migration)

---

## 6. Backend workflow before / after

### Before (removed)
```
Vendor → Pending_Staff → (staff forward) → Pending_Boss → (manager approve) → Approved
```

### After (current)
```
Vendor → Pending_Organizer → (organizer approve/reject/revise) → Approved | Rejected | Needs_Revision
```

**Organizer transitions from `Pending_Organizer`:**
- `Approved` → audit: `organizer_approved_booking`
- `Rejected` → audit: `organizer_rejected_booking`
- `Needs_Revision` → audit: `organizer_requested_revision`

**Payment verification:** `organizer_verified_payment` (Organizer + Super Admin only)

**Vendor resubmit:** `Needs_Revision` → `Pending_Organizer`

---

## 7. Route / capability changes

### Carboot operational routes (`organizer`, `super_admin` only)
- `/api/bookings` (list/show/update)
- `/api/bookings/{id}/verify-payment`
- `/api/staff/bookings`, `/api/staff/operations-summary` *(legacy URL prefix retained)*
- `/api/staff/bookings/{id}/verify`, `/api/staff/bookings/{id}/check-in`
- `/api/staff/feedbacks`, feedback moderation routes
- `/api/carboot-events`

### Generated reports (`organizer`, `cmart_management`, `super_admin`)
- `/api/management/reports/operational-overview`

### CMart activities (`organizer`, `cmart_management`, `super_admin`)
- `/api/news-posts`

### Raw analytics (`organizer`, `super_admin` via `boss` middleware)
- `/api/boss/analytics/*`, `/api/boss/audit-logs`

### Explicitly denied to `cmart_management`
- Booking list/mutation, approval, payment verify, pass check-in, raw analytics, operations summary

---

## 8. Tests run

Targeted filters (all passed after fixes):
- `ManagementCapabilityTest` — 6 tests
- `GovernanceAccessBoundaryTest` — 7 tests
- `WebAnalyticsSecurityTest` — 7 tests
- `CommunityVendorBookingAccessTest` — 2 tests
- `CommunityVendorIntentTest` — 7 tests
- `OrganizerBookingWorkflowTest` — 11 tests *(replaces BookingStaffStageAssistTest)*
- `StaffOperationsSummaryTest` — 5 tests
- `FeedbackModerationTest` — 11 tests

**Full suite:** `php artisan test` — **79 passed / 250 assertions**

**Frontend build:** `npm run build` — succeeded

---

## 9. Frontend compatibility aliases (for PR3)

| Location | Alias | Canonical key |
|----------|-------|---------------|
| `BookingController` summary | `pending_staff`, `pending_boss` (0) | `pending_organizer` |
| `StaffOperationsController` | `pending_staff_review` | `pending_organizer_review` |
| `managementCapabilities.js` | `STAFF_QUEUE_ASSIST` | `ORGANIZER_QUEUE` |
| `managementRoles.js` | `normalizeRole('staff')` → `cmart_management` | — |
| `bookingDisplay.js` | Legacy `Pending_Staff`/`Pending_Boss` display maps retained for historical audit rows | `Pending_Organizer` primary |

**Not changed in PR2 (PR3 scope):**
- `StaffBookingsPanel.vue`, Staff Portal Assist UI, E2E specs, `useManagementAccess.js` queue constants, `workspaceNav.js` staff assist nav item

---

## 10. Risks and rollback

1. **ENUM shrink is one-way in production** unless rollback migrations are run while audit tables exist.
2. **Historical audit log rows** may still reference `Pending_Staff`, `Pending_Boss`, or legacy role strings — display mapping is a PR3 concern.
3. **Legacy `/api/staff/*` URLs** remain as temporary Organizer-only compatibility paths; PR3 should rename or document permanently.
4. **E2E tests still assume two-stage workflow** — expected to fail until PR3 rewrites them.

**Rollback steps:**
```bash
php artisan migrate:rollback --step=2
```
Restores widened ENUMs and remaps from audit tables where rows exist.

---

## 11. Recommended PR3 follow-up items

1. Remove Staff Portal Assist UI and `StaffBookingsPanel.vue` forward/manager queue controls
2. Rename `/api/staff/*` routes to `/api/operations/*` or `/api/organizer/*`
3. Update all Vue status chips, pipeline progress, and filter dropdowns to `Pending_Organizer`
4. Remove deprecated API aliases (`pending_staff`, `pending_boss`, `pending_staff_review`)
5. Rewrite E2E specs (`staff.booking-forward`, `manager.booking-approval`, `manager.staff-portal-assist`, etc.)
6. Add audit log display mapping for legacy status/action labels
7. Update user-facing copy ("CMart staff will verify" → "Organizer will review")
8. Remove deprecated frontend helpers (`isStaffRole`, `workflowRoleKey` returning `manager`, etc.)

---

## Files modified (summary)

**Migrations:** `2026_07_12_000002_*`, `2026_07_12_000003_*`  
**Backend core:** `BookingController.php`, `ManagementRole.php`, `ManagementCapability.php`, `StaffOperationsController.php`, `VendorBookingPresenter.php`, `VendorAnalyticsService.php`, `VendorEventPassService.php`, `FeedbackController.php`, `EnsureRole.php`  
**Seeder:** `DatabaseSeeder.php`  
**Tests:** `OrganizerBookingWorkflowTest.php` (new), deleted `BookingStaffStageAssistTest.php`, updated governance/workflow tests  
**Frontend (minimal):** `managementRoles.js`, `managementCapabilities.js`, `bookingDisplay.js`  
**Views:** `resources/views/invoices/booking.blade.php`
