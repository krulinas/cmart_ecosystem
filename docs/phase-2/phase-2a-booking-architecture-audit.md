# Phase 2A.1 — Booking Architecture Audit and Diagnosis

**Audit date:** 2026-07-13  
**Repository:** CMart / Carboot@CMart (`cmart_ecosystem`)  
**Scope:** Audit-only — no production code, schema, route, UI, or test behaviour was changed.

---

## 1. Executive Summary

The current booking system implements a **direct Organizer approval workflow** (Phase 1.3C) with canonical statuses, invoice-based payments, computed event passes, and a booking audit log. It is suitable as a **financial and approval parent** for Phase 2, but it **does not yet implement physical site allocation, per-event-day occupancy, or site conflict detection**.

The most significant architectural gap is that **`spaces` represents booth *types* (catalog SKUs), not physical layout positions (e.g. Site A12)**. Booth labels are **synthetically computed** from booking IDs. Multiple vendors can book the same event with no backend conflict check. `max_slots` on events governs **community RSVP** (`event_user`), not vendor booth bookings.

**Primary audit question answer:** The current system **cannot safely support** “one booking → one site → allocated across multiple event days → with individual days releasable” without introducing new allocation and site structures. Existing payment, pass, and analytics modules assume **one booking = one event = one `booking_date` = one synthetic booth label**.

**Audit result classification:** **Ready with prerequisites**

**Top prerequisites before Phase 2A.2:**
1. Introduce a physical **event site** model (or repurpose `spaces` with explicit event-scoped layout — not recommended without evidence).
2. Introduce **operational event days** (generated from `starts_at`/`ends_at` or explicit table).
3. Introduce **`booking_day_allocations`** (or equivalent) as the occupancy/conflict unit.
4. Define how synthetic booth numbers transition to real site labels.
5. Add concurrency protection for allocation creation.

---

## 2. Audit Scope and Method

### In scope
- Laravel backend (`backend/`)
- Vue SPA frontend (`frontend/`)
- Database migrations and schema (read-only)
- Backend feature/unit tests and frontend E2E test inspection
- Governance documentation in `docs/governance/`
- Read-only local database diagnostics (development DB)

### Out of scope (explicitly not performed)
- Migrations, schema changes, code fixes, test modifications
- Withdrawal workflow implementation
- Full E2E suite execution (inspected coverage; focused backend tests run)

### Method
1. Repository orientation via directory inspection and content search
2. End-to-end lifecycle tracing (frontend → API → controller → model)
3. Schema and migration review
4. Authorization and governance verification against Phase 1 docs
5. Read-only DB queries via `php artisan tinker --execute`
6. Focused test execution: `php artisan test --filter=OrganizerBookingWorkflow|GovernanceAccessBoundary|VendorDemoPayment|CommunityVendorBooking`

### Evidence confidence labels used
- **Verified** — direct file/DB/test evidence
- **Inferred** — logical conclusion from code paths without runtime proof
- **Unable to verify** — not testable in this environment

---

## 3. Repository Modules Inspected

| Module | Path | Phase 2A relevance |
|--------|------|-------------------|
| Booking API | `backend/app/Http/Controllers/Api/BookingController.php` | Full vendor/organizer lifecycle |
| Booking model | `backend/app/Models/Booking.php` | Core entity; `space_id`, `booking_date`, `carboot_event_id` |
| Event model | `backend/app/Models/CarbootEvent.php` | `starts_at`/`ends_at`; RSVP capacity |
| Space model | `backend/app/Models/Space.php` | Booth **type** catalog, not layout position |
| Invoice model | `backend/app/Models/Invoice.php` | 1:1 payment parent |
| Pass service | `backend/app/Services/VendorEventPassService.php` | Computed passes; booking-level check-in |
| Presenter | `backend/app/Services/VendorBookingPresenter.php` | Synthetic booth numbers |
| Audit log | `backend/app/Services/BookingAuditLogger.php`, `BookingAuditLog` | Status history |
| RSVP | `backend/app/Http/Controllers/Api/EventRegistrationController.php` | Separate capacity system with row locking |
| Analytics | `backend/app/Http/Controllers/Api/BossAnalyticsController.php` | Revenue from invoices |
| Operations | `backend/app/Http/Controllers/Api/StaffOperationsController.php` | Queue counts |
| RBAC | `backend/app/Support/ManagementRole.php`, `ManagementCapability.php`, middleware | Phase 1 governance |
| API routes | `backend/routes/api.php` | All booking endpoints |
| Vendor booking UI | `frontend/src/views/auth/Registration.vue` | Booking submission |
| Organizer UI | `frontend/src/views/dashboards/organizer/OrganizerBookingsPanel.vue` | Approval, payment verify |
| Status utils | `frontend/src/utils/bookingDisplay.js` | Canonical + legacy badge maps |
| Event date utils | `frontend/src/utils/eventDisplay.js` | Malaysia TZ handling |
| Pass UI | `frontend/src/components/VendorEventPassesPanel.vue`, `StaffVerifyBooking.vue` | QR/check-in |
| E2E helpers | `frontend/tests/e2e/helpers/booking.js`, `organizer-bookings.js` | Regression protection |
| Governance docs | `docs/governance/phase-1-3*.md` | Confirms direct Organizer model |

**Not found:** `Site`, `Slot`, `Layout`, `Pass`, `Receipt` models; Laravel Policy classes; dedicated availability API.

---

## 4. Current Booking Lifecycle

### Flow diagram (as implemented)

```text
Vendor (community) → POST /api/bookings
  → BookingController::store()
  → validates event_id, tapak_quantity, total_price, product fields
  → assigns first Space where space_size = 'Standard (1 Parking Lot)'
  → sets booking_date = event.starts_at date
  → approval_status = Pending_Organizer
  → creates Invoice (Unpaid)

Organizer → PUT /api/bookings/{id} { approval_status }
  → Pending_Organizer → Approved | Rejected | Needs_Revision
  → BookingAuditLogger::log()

Vendor (Approved) → POST submit-payment OR demo-payment
  → invoice: Unpaid → Pending Verification | Paid

Organizer → PATCH /api/bookings/{id}/verify-payment
  → Pending Verification → Paid (booking status unchanged)

Pass unlock → Approved + Paid (computed, not stored)

Organizer → POST /api/organizer/bookings/{id}/check-in
  → sets bookings.checked_in_at
```

### Lifecycle Q&A

| # | Question | Answer | Evidence |
|---|----------|--------|----------|
| 1 | Where is a booking created? | `BookingController::store()` | `backend/app/Http/Controllers/Api/BookingController.php` L37–98 |
| 2 | Who can create? | `community` role via `middleware('role:community')` | `backend/routes/api.php` L72–104 |
| 3 | Vendor owner? | `$request->user()->id` → `bookings.user_id` | `BookingController::store()` L77 |
| 4 | Event selection? | `event_id` validated against `carboot_events`; frontend preselect via `?event_id=` | `Registration.vue`, `store()` L40 |
| 5 | Site selection? | **No vendor choice.** Auto-assigns standard space type | `store()` L72–74 |
| 6 | Site stored in bookings? | `space_id` FK → `spaces` (type catalog) | `Booking` model |
| 7 | One site per booking? | One `space_id`; tapak quantity affects invoice only | `store()` L65–90 |
| 8 | One date per booking? | Yes — `booking_date` = `starts_at` date only | `store()` L80 |
| 9 | What confirms booking? | Organizer `Approved`; payment verified separately | `update()`, `verifyBookingPayment()` |
| 10 | After approval? | Invoice exists; vendor can pay; audit log; pass stays locked until Paid | `VendorEventPassService` |
| 11 | After payment verify? | Invoice → Paid; pass/receipt unlock; audit `organizer_verified_payment` | L871–883 |
| 12 | Dependent modules | Passes, receipts/PDF, marketplace eligibility, analytics, operations counts | Multiple services |

### Authorization on vendor routes
- Ownership: `authorizeVendorBooking()` checks `user_id` match — **Verified**
- No vendor approval required to book — **Verified** (`CommunityVendorBookingAccessTest`)

---

## 5. Current Database and Model Relationships

### Core tables

```text
users ──< bookings >── spaces (booth TYPE)
              │
              ├──< invoices (1:1, cascade delete)
              ├──< booking_audit_logs
              └──> carboot_events (nullable FK, nullOnDelete)

carboot_events ──< bookings
carboot_events >──< users (event_user pivot — RSVP only)
```

### Key columns

**`bookings`:** `user_id`, `space_id`, `carboot_event_id`, `booking_date`, `approval_status` (ENUM), `checked_in_at`, withdrawal fields, `product_category`, `product_details`

**`carboot_events`:** `starts_at`, `ends_at` (datetime), `status`, `max_slots`

**`invoices`:** `booking_id`, `amount`, `payment_status` (ENUM: Unpaid, Pending Verification, Paid, Refunded), `payment_proof_path`

**`spaces`:** `space_size`, `price`, `status` (Available/Full) — global catalog, 2 seeded types

### Cascade risks
- `bookings.user_id` → cascade delete (**Verified** — migration `create_bookings_table`)
- `invoices.booking_id` → cascade delete (**Verified** — destroys payment evidence if booking hard-deleted)
- `bookings.carboot_event_id` → nullOnDelete (**Verified**)

### Soft deletes
- Not used on `Booking`, `Invoice`, or `CarbootEvent` — **Verified**

---

## 6. Event Date Architecture

### Current representation
- Events use **`starts_at` + `ends_at`** datetime columns (`carboot_events` migration)
- Bookings store **`booking_date`** (date-only), copied from `event.starts_at` at creation
- Frontend treats naive datetimes as **Asia/Kuala_Lumpur** wall clock (`frontend/src/utils/eventDisplay.js`)

### Event date Q&A

| # | Question | Answer |
|---|----------|--------|
| 1 | Can backend identify every operational day? | **No explicit helper.** Would require date-range expansion from `starts_at`/`ends_at`. |
| 2 | Frontend derives dates differently? | Displays full range for events; bookings use single `booking_date`. |
| 3 | Continuous range vs separate days? | Continuous datetime range; no `event_days` table. |
| 4 | Friday-night-to-Saturday could count as 2 days? | **Yes, if `DATE(starts_at) < DATE(ends_at)`** — no business rule prevents it. |
| 5 | Different operating hours per day? | **Not supported.** Single window per event. |
| 6 | Cancel one event day without whole event? | **Not supported.** |
| 7 | Storage timezone? | MySQL datetime (naive); app casts to Carbon; frontend assumes MYT for naive strings. |
| 8 | TZ conversion risks? | `eventDisplay.js` explicitly handles naive strings as +08:00; backend uses Laravel `now()` without always forcing MYT. |
| 9 | Shared date helpers? | Frontend: `eventDisplay.js`; backend: inline Carbon in controllers/services — **partially shared conceptually**. |
| 10 | Multi-day in seeders/data? | Seeder creates same-day events (+6h, +10h spans). **Local DB: 6 events, 0 multi-day** (Verified). |

### Recommendation: **Option A — Reuse `starts_at`/`ends_at` and generate operational days**

**Justification:** No `event_days` table exists. All seeded and test events are single-calendar-day. Generating days from the datetime range is the smallest Phase 2A.2 step.

**Trade-offs:**

| Option | Pros | Cons |
|--------|------|------|
| **A. Generated operational days** | No new table initially; backward compatible | Cross-midnight events need explicit rules; per-day hours need later extension |
| **B. Explicit `event_days` table** | Clean per-day cancellation, hours, capacity | Migration + backfill + API surface |
| **C. Reuse existing structure only** | None fit per-day occupancy | Insufficient alone |

**Phase 2A.2 suggestion:** Start with **generated days** in a service; add explicit `event_days` table when per-day cancellation or hours are required (likely Phase 2B).

---

## 7. Site and Layout Architecture

### Critical finding
The repository **does not model physical vendor sites (A12, B07, etc.)**. The word “site” in Phase 2 business language maps today to:

| Concept | Current implementation |
|---------|------------------------|
| Booth/site **type** | `spaces.space_size` (e.g. "Standard (1 Parking Lot)") |
| Booth **label** | Synthetic: `VendorBookingPresenter::boothNumber()` → `A-{id}` pattern |
| Tapak count | Request input `tapak_quantity`; stored only in `invoices.amount` |
| Layout | **Not modeled** (seeder copy mentions "Clean layout" in feedback only) |

### Site architecture Q&A

| # | Question | Answer |
|---|----------|--------|
| 1 | Physical site model? | **`spaces` table — global type catalog, not per-event position** |
| 2 | Global or event-specific? | **Global** (2 rows seeded) |
| 3 | Copied per event layout? | **No** |
| 4 | Site label? | Synthetic from booking ID (backend + frontend duplicate logic) |
| 5 | Different layout per day? | **No** |
| 6 | Same layout full event? | N/A — no layout |
| 7 | Site attached to booking? | `space_id` = type, not position |
| 8 | Multiple sites per booking? | **No** |
| 9 | Pivot/allocation table? | **None** |
| 10 | Walk-in vendors? | **Not found in codebase** |

**Classification:** **Requires replacement** for physical site-day allocation (Severity: **Critical**)

---

## 8. Site Availability and Conflict Detection

### Finding: No vendor booking conflict detection exists

**Verified** by full review of `BookingController::store()` — no query checks existing bookings for event/site/status before create.

### Availability Q&A

| # | Question | Answer |
|---|----------|--------|
| 1 | By `event_id + site_id`? | **No check at all** |
| 2 | Based on booking status? | **Not for availability** (only for payment/pass gating) |
| 3 | Statuses blocking site? | **None enforced** |
| 4 | Rejected/Cancelled/Withdrawn release? | **Conceptually yes, but nothing is blocked to begin with** |
| 5 | Pending_Organizer blocks? | **No** |
| 6 | Approved unpaid blocks? | **No** |
| 7 | Temporary reservation? | **No** |
| 8 | Simultaneous duplicate possible? | **Yes** — no transaction/lock on booking create |
| 9 | DB-level protection? | **No unique constraint** on `(carboot_event_id, space_id)` or similar |
| 10 | Frontend-only disabling? | **No site picker exists** |
| 11 | For `event day + site` conflicts? | Requires new allocation table + unique partial index on active allocations |
| 12 | MySQL uniqueness with historical releases? | Feasible via `active` flag or `released_at` + unique `(event_day_id, site_id, active)` pattern |

### Separate RSVP concurrency (reference pattern)
`EventRegistrationController::register()` uses **`DB::transaction` + `lockForUpdate()`** on event row — **Verified**. This pattern should be reused for allocation creation in Phase 2A.2.

### Frontend note
E2E README states vendor booking test may pass if vendor **already has booking for selected event** — treats as success, not conflict failure — **Verified** (`frontend/tests/e2e/README.md`).

---

## 9. Booking Status Model

### Canonical statuses (DB ENUM after migration `2026_07_12_000003`)
`Pending_Organizer`, `Needs_Revision`, `Approved`, `Rejected`, `Cancelled`, `Withdrawn`

### Legacy values (remapped, still referenced in UI/PDF)
`Pending_Staff`, `Pending_Boss` — in migrations, `bookingDisplay.js`, `ManagementStatusChip.vue`, `invoices/booking.blade.php`

### Status usage matrix (Appendix C expanded)

| Status | Created by | Transitioned by | Blocks site? | Enables payment? | Enables pass? | Used by reports? |
|--------|------------|-----------------|--------------|------------------|---------------|------------------|
| Pending_Organizer | `store()` default | Organizer approve/reject/revision; vendor resubmit | **No enforcement** | No | No (pending_approval) | Yes (queue counts) |
| Needs_Revision | Organizer | Vendor resubmit → Pending_Organizer | No | No | No | Yes |
| Approved | Organizer | — | No | Yes (if Unpaid) | Yes (if Paid) | Yes (revenue, analytics) |
| Rejected | Organizer | Terminal | No | No | No (cancelled pass) | Yes |
| Cancelled | Vendor `vendorCancel` (pending only) | Terminal | No | No | No | Yes |
| Withdrawn | Vendor `withdraw` (pending/unpaid only) | Terminal | No | Blocked if Paid | No | Yes |

### Participation state recommendation
**Derive from active daily allocations** where possible; keep **`approval_status` on booking** as overall organizer decision. Avoid `Approved_Day_1` status proliferation — **Confirmed safe approach** given current ENUM migration cost.

### Payment vs approval
Payment state lives on **`invoices.payment_status`**, not booking — **Verified**. Verification does **not** change `approval_status`.

---

## 10. Payment, Invoice, Receipt, and Revenue Dependencies

### Financial lifecycle

```text
Booking created → Invoice (Unpaid)
  → vendor submit-payment → Pending Verification
  → organizer verify-payment → Paid
  OR vendor demo-payment → Paid (skips verification)
```

### Payment Q&A

| # | Question | Answer |
|---|----------|--------|
| 1 | Payment on overall booking? | Yes — via 1:1 `Invoice` |
| 2 | One invoice per booking? | Yes — created in `store()` |
| 3 | Attached to? | **Booking** (via invoice FK) |
| 4 | Verified payment field? | `invoices.payment_status = 'Paid'` |
| 5 | Who verifies? | Organizer/super_admin — `verifyBookingPayment()` |
| 6 | Verification modifies booking status? | **No** — stays Approved |
| 7 | Cancellation modifies payment? | **No automatic change** |
| 8 | Cascade delete risk? | **Yes** — hard delete booking cascades invoice |
| 9 | Paid + Withdrawn valid today? | **Withdraw blocked when Paid** (`withdraw()` L599–603). Paid withdrawal not implemented. |
| 10 | Partial day release → invoice recalc? | **Not applicable** — single invoice per booking; would need policy decision |
| 11 | Pricing basis? | Per booking: `tapak_quantity × RM 20` |
| 12 | Two-day event charge? | **Once per booking** (no per-day pricing) |
| 13 | Receipt depends on? | **Approved + Paid** (`VendorHistoryController`, pass service) |
| 14 | Revenue excludes withdrawn? | Revenue sums invoices for **Approved** bookings only — withdrawn typically not Approved |
| 15 | Paid withdrawal reduce revenue? | **Risk in future** if payment stays Paid and booking Withdrawn — revenue would still count unless query adjusted |
| 16 | Replacement vendor second payment? | **Possible** — no allocation conflict prevention |
| 17 | Reports base? | **Invoices** (paid/unpaid sums) + approved booking counts |

### Refund status recommendation
**Defer `refund_status` column.** Phase 2 principle is no automatic refund. When paid withdrawal is implemented, prefer:
- Keep `invoices.payment_status = Paid` (historical truth)
- Optional future: `withdrawal_records` or metadata on allocation release
- **Not** on booking `approval_status`

---

## 11. Authorization and Governance Findings

### No Laravel Policies — middleware + inline checks only

### Authorization matrix (Appendix D)

| Action | community | organizer | cmart_management | super_admin |
|--------|:---------:|:---------:|:----------------:|:-----------:|
| Create own booking | ✅ | ❌ | ❌ | ❌ |
| View own booking | ✅ | ❌ | ❌ | ❌ |
| Approve booking | ❌ | ✅ | ❌ | ✅ |
| Verify payment | ❌ | ✅ | ❌ | ✅ |
| Release a site-day | ❌ | N/A (not implemented) | ❌ | N/A |
| Process withdrawal | ✅ (own, unpaid/pending) | ❌ | ❌ | ❌ |
| View raw booking operations | ❌ | ✅ | ❌ | ✅ |

### Governance regression check
- **CMart Management blocked** from `/api/bookings`, verify-payment, analytics — **Verified** (`GovernanceAccessBoundaryTest`, `OrganizerBookingWorkflowTest`)
- **Organizer has required powers** — **Verified**
- **super_admin bypass** on organizer routes — **Verified**
- **IDOR:** Vendor routes check ownership — **Verified**; organizer routes use ID only with role middleware (acceptable)

### Minor findings
- PDF download allows `ManagementRole::isCmartWorker()` (includes cmart_management) — **Verified** (`generatePdf()` L219). May be intentional for venue staff document access.
- `destroy()` booking uses `boss` middleware (organizer analytics capability) — hard delete risk for audit/finance history.

---

## 12. Event Pass and Check-In Dependencies

| # | Question | Answer |
|---|----------|--------|
| 1 | Pass per? | **Per booking** (computed DTO, no `passes` table) |
| 2 | Multi-day event passes? | **One pass** using full event window |
| 3 | Valid event days on pass? | **No** — single `event_date` from `booking_date` |
| 4 | Check-in records attendance date? | **No** — only `checked_in_at` timestamp |
| 5 | Withdrawn Sunday, pass Sunday? | Whole-booking pass cancelled when status Withdrawn — **no per-day granularity** |
| 6 | Eligibility? | **Approved + Paid** for QR active |
| 7 | Per-day allocation needs per-day validation? | **Yes** — future requirement |
| 8 | QR payload? | URL to `/organizer/verify-booking/{bookingId}` — booking ID only |
| 9 | Check-in tied to allocation dates? | **No** |
| 10 | Phase 2A.2 essential vs defer | **Essential:** allocation model. **Defer:** per-day QR validation, multi-pass |

**Classification:** Pass module **Requires modification** for partial participation (Severity: **High**)

---

## 13. Analytics and Reporting Dependencies

### Current metrics assumptions
- **One booking = one participation unit** — **Verified** (`BossAnalyticsController`, `VendorAnalyticsService`)
- **Capacity/utilization** compares approved bookings to `max_slots` (RSVP capacity) — **misaligned with vendor bookings** (Severity: **Medium**)
- **Site-days** not tracked — a 2-day event would count as **1 booking**, not 2 site-days
- **Withdrawn/cancelled** excluded from upcoming counts in vendor analytics — **Verified**
- **Revenue** from paid invoices linked to approved bookings
- **CMart Management** receives generated reports without raw analytics — **Verified**

### Future metrics (not implemented)
`booked site-days`, `active site-days`, `released site-days`, `reallocated site-days`, `paid withdrawals`, `partial participation`

---

## 14. Audit-Trail Readiness

### Existing: `booking_audit_logs`
Columns: `booking_id`, `actor_user_id`, `action`, `from_status`, `to_status`, `revision_comment`, `ip_address`

| Capability | Supported? |
|------------|------------|
| Reusable audit mechanism | **Partial** — booking status only |
| Actor ID | Yes |
| Actor role | **No** — not stored |
| Previous/new values | Status fields only |
| Reference allocation | **No** |
| Reasons/metadata | `revision_comment` / action string |
| UI visible | Vendor booking detail shows audit logs |
| Permanent business history | Suitable for approval/payment actions; not allocation release |

### Recommendation: **Option E — Hybrid**
- **Extend** `booking_audit_logs` (or sibling `booking_activity_logs`) for allocation/release actions with `allocation_id` nullable
- **Separate** withdrawal/release records when paid withdrawal ships (Phase 2B+)
- Do **not** rely on Laravel log files for business audit

---

## 15. Existing Data Diagnosis

**Database:** Local development MySQL — **available** (Verified via tinker)

| Metric | Count | Notes |
|--------|------:|-------|
| Total events | 6 | Verified |
| Single-day events (`DATE(starts_at) = DATE(ends_at)`) | 6 | Verified |
| Multi-day events | 0 | Verified |
| Total bookings | 8 | Verified |
| By status: Pending_Organizer | 3 | Verified |
| By status: Approved | 4 | Verified |
| By status: Withdrawn | 1 | Verified |
| Missing `carboot_event_id` | 0 | Verified |
| Missing `space_id` | 0 | Verified |
| Approved without invoice | 0 | Verified |
| Duplicate active booking same event+site | **Not queried** — conflict model absent | Unable to verify meaningfully |

### Schema/code-level notes
- All events in local DB are single-day — multi-day backfill untested on real data
- ENUM columns on `approval_status` and `payment_status` will require migration discipline for new values
- `booking_status_migration_audit_202607` table preserves pre-Phase-1.3C status snapshot

---

## 16. Migration and Backfill Risks

### Proposed backfill rules (Phase 2A.2 — not executed)

| Record type | Backfill strategy |
|-------------|-------------------|
| Single-day active booking | 1 active allocation: operational day + current synthetic site (TBD physical site FK) |
| Multi-day active booking | 1 allocation per generated operational day, same site |
| Rejected | No active allocation; optional historical inactive row |
| Cancelled / Withdrawn | No active allocation; retain booking + invoice history |
| Pending_Organizer | **Policy decision:** reserve site-day or not — today nothing reserved |
| Missing relationships | Quarantine report; do not silently create allocations |

### `bookings.space_id` transition
**Recommendation:** **Remain as compatibility field** through Phase 2A.2/2B; points to booth type until physical sites exist. Deprecate after allocation rollout.

### Reversibility
Keep `booking_date`, `space_id`, `carboot_event_id` populated; dual-write allocations alongside; verification report post-backfill.

---

## 17. Frontend and API Impact Matrix

| Component / Endpoint | Classification |
|---------------------|----------------|
| `Registration.vue` (booking form) | **Compatibility update** — eventual site picker, multi-day summary |
| `POST /api/bookings` | **Compatibility update** — allocation creation |
| `OrganizerBookingsPanel.vue` | **Compatibility update** — show allocations |
| `PATCH /verify-payment` | **No immediate change** |
| `VendorPaymentModal.vue` / checkout | **No immediate change** |
| `WithdrawBookingModal.vue` | **Deferred** — paid withdrawal + BM copy |
| `VendorEventPassesPanel.vue` / QR | **Deferred** — per-day pass validation |
| `StaffVerifyBooking.vue` | **Deferred** — per-day check-in |
| `bookingDisplay.js` booth labels | **Compatibility update** — real site labels |
| `BossRevenuePanel.vue` | **Deferred** — site-day metrics |
| `ManagementReportsPanel.vue` | **No immediate change** (counts still valid) |
| Layout view | **Potential blocker** — no layout UI exists |
| E2E booking specs | **Compatibility update** — must keep passing |

---

## 18. Automated Test Coverage and Gaps

### Protective tests (must not delete)
- `OrganizerBookingWorkflowTest.php` — direct Organizer pipeline
- `GovernanceAccessBoundaryTest.php` — CMart Management boundaries
- `VendorDemoPaymentTest.php` — payment guards
- `CommunityVendorBookingAccessTest.php` — unapproved vendor booking
- E2E: `vendor.booking.spec.js`, `organizer.booking-approval.spec.js`, `vendor.payment-verification-pass-unlock.spec.js`, `access.cmart-management-boundary.spec.js`, `access.destructive-action-protection.spec.js`

### Tests encoding obsolete governance
- E2E README still references `Pending_Boss` in one revision scenario — legacy narrative only

### Assumption gaps
- All tests use **same-day** events (`starts_at` + hours)
- **No multi-day fixtures**
- **No site conflict tests**
- **No concurrency tests** for vendor booking create
- **No paid withdrawal tests**
- **No allocation backfill tests**

### Validation performed
```bash
php artisan route:list --path=booking          # Exit 0
php artisan migrate:status                     # All migrations Ran
php artisan test --filter=OrganizerBookingWorkflow|GovernanceAccessBoundary|VendorDemoPayment|CommunityVendorBooking
# Result: 28 passed (61 assertions)
```
Full E2E not run — **inspected** 17 spec files under `frontend/tests/e2e/specs/`.

---

## 19. Key Findings Register

| ID | Area | Finding | Evidence | Classification | Severity | Phase 2A.2 implication |
|----|------|---------|----------|----------------|----------|------------------------|
| F-001 | Site model | No physical site/layout; `spaces` is type catalog | `Space.php`, `BookingController::store()` L72–74 | Requires replacement | Critical | Introduce event-scoped sites before meaningful allocation |
| F-002 | Availability | No vendor booking conflict detection | `BookingController::store()` | Requires modification | Critical | Add allocation-level uniqueness + transactional create |
| F-003 | Booth label | Synthetic `A-{id}` not tied to layout | `VendorBookingPresenter::boothNumber()` | Requires modification | High | Bind labels to physical site records |
| F-004 | Event days | No operational day entity | `CarbootEvent` model | Requires modification | High | Generate or add `event_days` |
| F-005 | Booking date | Single `booking_date` only | `bookings.booking_date` | Requires modification | High | Add `booking_day_allocations` |
| F-006 | Multi-day | 0 multi-day events in local DB | DB query | Informational | Low | Foundation tests needed |
| F-007 | RSVP vs vendor | `max_slots` applies to RSVP not vendors | `CarbootEvent::syncCapacityStatus()`, `EventRegistrationController` | Requires modification | Medium | Separate vendor capacity from RSVP |
| F-008 | Paid withdrawal | Blocked when invoice Paid | `BookingController::withdraw()` L599–603 | Requires modification | High | Deferred to Phase 2B with no-refund rules |
| F-009 | Pass/check-in | Booking-level only; one `checked_in_at` | `VendorEventPassService` | Requires modification | High | Defer per-day validation |
| F-010 | Revenue | Counts approved bookings vs RSVP max_slots | `BossAnalyticsController::revenue()` L73–76 | Requires modification | Medium | Defer site-day metrics |
| F-011 | Governance | CMart Management correctly blocked from booking ops | `GovernanceAccessBoundaryTest` | Safe to reuse | Informational | Maintain |
| F-012 | Audit | `booking_audit_logs` lacks role/allocation | `BookingAuditLog` model | Requires modification | Medium | Extend for release actions |
| F-013 | Cascade | Hard delete booking destroys invoice | `create_invoices_table` migration | Potential blocker | High | Avoid hard delete; soft-delete policy |
| F-014 | Legacy UI | PDF/CSS still references Pending_Staff/Boss | `invoices/booking.blade.php` | Requires modification | Low | Cosmetic compatibility |
| F-015 | Concurrency | RSVP has lockForUpdate; bookings do not | `EventRegistrationController` vs `store()` | Requires modification | High | Reuse transaction pattern |

---

## 20. Recommended Target Architecture

### Conceptual model

```text
CarbootEvent (starts_at, ends_at)
  └── EventDay (generated or explicit): operational_date, starts_at, ends_at
        └── EventSite (event-scoped physical position): label e.g. "A12"
              └── BookingDayAllocation: booking_id, event_day_id, event_site_id,
                                         status (active|released|...), release metadata

Booking (unchanged as parent)
  ├── user_id, approval_status (overall)
  ├── Invoice (1:1) — financial parent
  └── hasMany BookingDayAllocation
```

### Design decisions

1. **Financial/approval parent:** `Booking` + `Invoice` — **keep**
2. **Operational event day:** `EventDay` (generated from event range initially)
3. **Site occupied one day:** `BookingDayAllocation` unique active per `(event_day_id, event_site_id)`
4. **Single-day compatibility:** Events with one operational day → one allocation
5. **Full-duration default:** On approval (or booking create), create allocation for **each** operational day with same site
6. **Partial release:** Mark allocation `released`; booking remains Approved/Withdrawn at header level as appropriate
7. **Audit history:** Released allocations remain rows; never hard-delete paid invoice
8. **Duplicate prevention:** Partial unique index on active allocations + `lockForUpdate` in service
9. **Transition:** Keep `bookings.space_id` (type), `booking_date`, `carboot_event_id` during dual-write
10. **Phase 2A.2 vs defer:** Schema + service + backfill + API fields in 2A.2; withdrawal UI, paid withdrawal, per-day passes deferred

### Why not separate bookings per day
Would duplicate invoices, approval workflow, and payment — **contradicts repository evidence** of 1:1 booking-invoice design and E2E tests.

---

## 21. Recommended Phase 2A.2 Scope

### Include (minimum safe)
- [ ] `event_days` representation (table or computed with persisted cache)
- [ ] `event_sites` (physical positions per event)
- [ ] `booking_day_allocations` schema + models
- [ ] `BookingAllocationService` — create full-event default, conflict check
- [ ] Transaction + row locking on allocation create
- [ ] Backfill migration + verification report
- [ ] API: expose allocations on booking show/list (additive fields)
- [ ] Regression tests: single-day, multi-day foundation, conflict rejection
- [ ] Keep existing booking create flow working (auto-assign until site picker exists)

### Defer
- Vendor withdrawal modal + BM no-refund copy
- Paid withdrawal processing
- Day-specific exception UI
- Organizer released-slot reallocation queue
- Per-day pass/QR validation
- Site-day analytics overhaul
- Layout visual designer

---

## 22. Deferred Items for Later Phase 2 Subphases

| Item | Target subphase |
|------|-----------------|
| Paid withdrawal (no refund) | Phase 2B withdrawal |
| No-refund BM acknowledgement | Phase 2B |
| Emergency partial day withdrawal | Phase 2B/C |
| Organizer manual day release UI | Phase 2B |
| Vendor replacement on released site-day | Phase 2C |
| Per-day check-in validation | Phase 2C |
| Revenue/participation site-day metrics | Phase 2C |
| Physical layout editor | Future |

---

## 23. Risks and Unresolved Decisions

1. **Physical site source of truth** — New `event_sites` table vs importing from external layout tool?
2. **When to assign physical site** — At booking create, organizer approval, or payment verification?
3. **Pending_Organizer reservation** — Should pending bookings reserve site-days? (Currently: no)
4. **Tapak quantity vs single site** — Multiple parking lots may imply multiple adjacent sites; not modeled today
5. **Cross-midnight events** — Business rule for operational day boundaries
6. **Paid + Withdrawn revenue reporting** — Include in collected revenue? (Recommend: yes, with `paid_withdrawals` metric)
7. **Hard delete policy** — `destroy()` removes financial records; should be restricted or soft-deleted

---

## 24. Phase 2A.1 Completion Gate

| # | Question | Status |
|---|----------|--------|
| 1 | What currently represents an event day? | **Answered** — implicit: `booking_date` on booking; events use datetime range |
| 2 | How is a multi-day event identified? | **Answered** — `DATE(starts_at) < DATE(ends_at)`; none in local DB |
| 3 | What currently makes a site unavailable? | **Answered** — nothing for vendor bookings |
| 4 | Where is conflict validation enforced? | **Answered** — RSVP only (`EventRegistrationController`) |
| 5 | Is conflict protection safe against simultaneous booking? | **Answered** — **No** for vendor bookings |
| 6 | Is payment independent from booking approval? | **Answered** — Yes, on `invoices` |
| 7 | Can verified payment remain recorded after withdrawal? | **Partially answered** — Invoice persists; paid withdrawal not allowed yet |
| 8 | Can one site-day be released without deleting booking? | **Answered** — **Not today**; target architecture supports it |
| 9 | Are current bookings safely backfillable? | **Answered** — Yes (8 bookings, all single-day, FKs intact) |
| 10 | Which fields must remain for compatibility? | **Answered** — `space_id`, `booking_date`, `carboot_event_id`, `approval_status` |
| 11 | Are Organizer-only operations protected at backend? | **Answered** — Yes |
| 12 | Does CMart Management retain prohibited legacy access? | **Answered** — No (tests pass) |
| 13 | Which pass/check-in modules assume whole-booking validity? | **Answered** — `VendorEventPassService`, `StaffVerifyBooking` |
| 14 | Which analytics assume one booking = one occupied site? | **Answered** — Boss + vendor analytics |
| 15 | Which tests must pass unchanged after Phase 2A.2? | **Partially answered** — Approval/governance/payment tests; allocation tests additive |
| 16 | Unresolved blockers? | **Answered** — Physical site model must be designed (F-001) |

---

## Appendix A — Relevant Files

### Backend
- `backend/app/Http/Controllers/Api/BookingController.php`
- `backend/app/Http/Controllers/Api/BookingPassVerificationController.php`
- `backend/app/Http/Controllers/Api/CarbootEventController.php`
- `backend/app/Http/Controllers/Api/EventRegistrationController.php`
- `backend/app/Http/Controllers/Api/InvoiceController.php`
- `backend/app/Http/Controllers/Api/VendorEventPassController.php`
- `backend/app/Http/Controllers/Api/VendorHistoryController.php`
- `backend/app/Http/Controllers/Api/BossAnalyticsController.php`
- `backend/app/Http/Controllers/Api/StaffOperationsController.php`
- `backend/app/Http/Controllers/Api/ManagementReportsController.php`
- `backend/app/Models/Booking.php`, `Invoice.php`, `Space.php`, `CarbootEvent.php`, `BookingAuditLog.php`
- `backend/app/Services/VendorEventPassService.php`, `VendorBookingPresenter.php`, `BookingAuditLogger.php`, `MarketplaceEligibility.php`
- `backend/app/Support/ManagementRole.php`, `ManagementCapability.php`
- `backend/routes/api.php`
- `backend/database/migrations/2026_05_09_052330_create_bookings_table.php`
- `backend/database/migrations/2026_07_12_000003_direct_organizer_booking_statuses.php`
- `backend/resources/views/invoices/booking.blade.php`

### Frontend
- `frontend/src/views/auth/Registration.vue`
- `frontend/src/views/dashboards/organizer/OrganizerBookingsPanel.vue`
- `frontend/src/views/dashboards/VendorDashboard.vue`
- `frontend/src/components/VendorBookingDetailsModal.vue`
- `frontend/src/components/WithdrawBookingModal.vue`
- `frontend/src/components/VendorEventPassesPanel.vue`
- `frontend/src/views/staff/StaffVerifyBooking.vue`
- `frontend/src/utils/bookingDisplay.js`
- `frontend/src/utils/eventDisplay.js`
- `frontend/src/utils/vendorPass.js`

### Tests
- `backend/tests/Feature/OrganizerBookingWorkflowTest.php`
- `backend/tests/Feature/GovernanceAccessBoundaryTest.php`
- `backend/tests/Feature/VendorDemoPaymentTest.php`
- `frontend/tests/e2e/specs/vendor.booking.spec.js`
- `frontend/tests/e2e/specs/organizer.booking-approval.spec.js`

---

## Appendix B — Relevant Routes and Endpoints

| Method | Endpoint | Role | Purpose |
|--------|----------|------|---------|
| POST | `/api/bookings` | community | Create booking |
| GET | `/api/vendor/bookings` | community | List own bookings |
| PATCH | `/api/bookings/{id}/withdraw` | community | Withdraw |
| POST | `/api/vendor/bookings/{id}/submit-payment` | community | Upload proof |
| POST | `/api/vendor/bookings/{id}/demo-payment` | community | Demo pay |
| GET | `/api/bookings` | organizer | Registry |
| PUT | `/api/bookings/{id}` | organizer | Approve/reject/revision |
| PATCH | `/api/bookings/{id}/verify-payment` | organizer | Verify payment |
| GET | `/api/organizer/bookings/{id}/verify` | organizer | Pass verify |
| POST | `/api/organizer/bookings/{id}/check-in` | organizer | Check-in |
| GET | `/api/vendor/event-passes` | community | Pass list |
| GET | `/api/bookings/{id}/pdf` | community/CMart | PDF receipt |
| GET | `/api/boss/analytics/revenue` | organizer (boss) | Revenue |
| GET | `/api/management/reports/operational-overview` | capability | Generated report |
| POST | `/api/events/{id}/register` | community | RSVP (separate) |

---

## Appendix C — Status Usage Matrix

(See Section 9 for full matrix.)

**Legacy mapping locations:**
- `backend/database/migrations/2026_07_12_000003_direct_organizer_booking_statuses.php`
- `frontend/src/utils/bookingDisplay.js` L58–59
- `frontend/src/components/management/ManagementStatusChip.vue`
- `backend/resources/views/invoices/booking.blade.php` L67–68

---

## Appendix D — Authorization Matrix

(See Section 11 for full matrix.)

**Route middleware summary:**
- Vendor booking: `auth:sanctum` + `role:community`
- Organizer ops: `role:organizer,super_admin`
- Boss analytics: `boss` middleware
- Generated reports: `capability:generated_reports`

---

## Appendix E — Read-Only Diagnostic Queries

Executed on local development database (2026-07-13):

```sql
-- Event counts
SELECT COUNT(*) FROM carboot_events;
SELECT COUNT(*) FROM carboot_events WHERE DATE(starts_at) = DATE(ends_at);
SELECT COUNT(*) FROM carboot_events WHERE DATE(starts_at) < DATE(ends_at);

-- Booking status distribution
SELECT approval_status, COUNT(*) FROM bookings GROUP BY approval_status;

-- Integrity checks
SELECT COUNT(*) FROM bookings WHERE carboot_event_id IS NULL;
SELECT COUNT(*) FROM bookings WHERE space_id IS NULL;
SELECT COUNT(*) FROM bookings b
  LEFT JOIN invoices i ON i.booking_id = b.id
  WHERE b.approval_status = 'Approved' AND i.id IS NULL;
```

**Results (Verified):**

| Query | Result |
|-------|--------|
| Total events | 6 |
| Single-day events | 6 |
| Multi-day events | 0 |
| Total bookings | 8 |
| Pending_Organizer | 3 |
| Approved | 4 |
| Withdrawn | 1 |
| Missing event FK | 0 |
| Missing space_id | 0 |
| Approved without invoice | 0 |

**Not run (syntax/scope):** duplicate active booking per event+site — requires allocation model to be meaningful.

---

*End of Phase 2A.1 audit document.*
