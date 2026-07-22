# Event Days Deep Audit and Decision Report

## 1. Executive Summary

`event_days` is a **persistent per-event operational schedule** introduced in Phase 2A.5. It is the unit of site occupancy for `booking_day_allocations`, vendor availability, attendance exceptions, and layout booking readiness. Event `starts_at` / `ends_at` alone are **not** used as bookable inventory.

The Organizer message *“The physical layout is ready, but event days must be configured before vendors can book.”* is **accurate backend behaviour**, not a false positive. For event **id=4** (`Kedah International Kedah`), the layout is operationally complete (4 active public rows, 64 active sites) and the **sole** operational readiness blocker is `NO_ACTIVE_EVENT_DAYS`.

The configuration gap is **Confirmed**: backend generation/CRUD APIs exist and are authorization-gated, but the Organizer Vue workflow never calls them. Event create/update and seeders do not create days. Phase 2A.5 documentation explicitly scoped day APIs as **backend-only / no cinema UI**; Phase 3.6 completed Layout Management UI without adding an event-day configuration surface. Readiness later made days mandatory for booking.

**Recommended direction (decision-ready, not implemented):** short-term **Option B** — automatically generate and synchronize `event_days` from `starts_at`/`ends_at` (with allocation-history protection already present in `EventDayGenerator`) — plus an immediate UX path so Organizers are not stuck behind a non-actionable warning.

---

## 2. Audit Scope and Safety Constraints

| Constraint | Status |
|------------|--------|
| Audit-only; no application behaviour changes | Honoured |
| No migrations, seeders, inserts, updates, deletes, or day generation | Honoured |
| No `.env` / database identity changes | Honoured |
| Only repository write: this report under `report/` | Honoured |
| Database inspection read-only | Honoured |
| Tests run only if non-destructive | Not executed — `cmart_test` database missing |

---

## 3. Repository and Runtime Baseline

| Item | Value |
|------|--------|
| Branch | `main` |
| Commit SHA | `2f0d91399a37763d91d0b75ecd9df168b3556520` |
| Working-tree note | Conversation start showed pre-existing untracked recovery reports under `report/` (`00`–`08`, path JSON, `command_log.md`). Those files remain present (12 files before this audit report). This audit did not modify them. |
| Laravel | 11.51.0 |
| PHP | 8.2.12 |
| App timezone | `Asia/Kuala_Lumpur` |
| Config cache | **NOT CACHED** (`bootstrap/cache/config.php` absent; `php artisan about` agrees) |
| Resolved DB connection | `mysql` @ `127.0.0.1` |
| Resolved DB name | `cmart_db_rebuild` |
| Expected post-recovery DB | Matches Phase 2 rebuild report (`report/08_phase2_clean_rebuild_report.md`) |
| Views cache | Cached (irrelevant to readiness computation) |

### Migration status (relevant)

All of the following migrations show as **Ran** on the active connection:

- `carboot_events` and later event columns (`day_generation_mode`, public layout publication, item reservation fee)
- `event_sites`, `event_days`, `booking_day_allocations`
- layout rows / site FKs / layout audits
- attendance exception tables

### Partial implementation signal

| Layer | Event days | Layout |
|-------|------------|--------|
| Schema | Implemented | Implemented |
| Services / APIs | Implemented | Implemented |
| Organizer UI | **Missing** for create/generate/edit days | Implemented |
| Seeders | Do **not** create `event_days` | Layout may be created manually in UI |
| Readiness enforcement | Mandatory (`NO_ACTIVE_EVENT_DAYS`) | Mandatory |

---

## 4. Current User-Visible Problem

On Layout Management (`/admin#layout`, `OrganizerEventLayoutPanel.vue`), readiness badges show:

- **Not Ready for Booking** (`operational_ready === false`)
- **Not Ready for Public Display** (`public_ready === false`)

When the only operational blocker is missing days, the panel shows:

> The physical layout is ready, but event days must be configured before vendors can book.

**Evidence:**

- `frontend/src/utils/organizerEventLayoutMessages.js:45-46` — `missingEventDaysWarning`
- `frontend/src/components/organizer/layout/EventLayoutReadinessPanel.vue:33-40,95-100` — warning when `NO_ACTIVE_EVENT_DAYS` present; that code is filtered out of the generic blocker list
- `backend/app/Services/EventLayoutReadinessService.php:44-54` — emits `NO_ACTIVE_EVENT_DAYS`

**Impact:** Organizers can complete physical layout setup and still cannot open vendor booking, with no discoverable in-UI action to create days.

**Confidence:** Confirmed

---

## 5. What `event_days` Means in the Current Domain

From verified code and Phase 2A.5 documentation recovered from commit `d84488f`:

| Concept | Role |
|---------|------|
| **Event** (`carboot_events`) | Marketing/ops container: title, status, capacity, images, overall window |
| **`starts_at` / `ends_at`** | Event wall-clock window (MYT); used for public listing, booking_date default, day *generation planning* |
| **Event day** (`event_days`) | Explicit **operational bookable day/session** with its own `operational_date`, day `starts_at`/`ends_at`, status, order |
| **Booking date** (`bookings.booking_date`) | Legacy/display date; set from event `starts_at` date at create — **not** the occupancy key |
| **Booking-day allocation** | One booking occupying one site on one event day |
| **Parking site** (`event_sites`) | Physical bay in a layout row |
| **Reservation** | `allocation_status=reserved` (+ `active_lock=1`) |
| **Confirmed booking** | Lifecycle advances allocations to `confirmed` |
| **Released day** | Allocation released (e.g. attendance exception); recovery queue tracks recoverable site-day slices |
| **Withdrawn booking** | Booking withdrawal path; day allocations released/cancelled per lifecycle services |
| **Booking readiness** | `operational_ready` from layout + **active event days** |
| **Public-display readiness** | Requires operational readiness **and** public layout projection checks |

### Classification of `event_days` purpose

| Hypothesis | Verdict |
|------------|---------|
| 1. Every calendar day touched by an event | **Supported** via `calendar_days` generation mode |
| 2. Every day on which vendors may book | **Yes** — only `operational_status=active` days participate |
| 3. Individual operating sessions | **Partially** — `single_session` supports overnight as one day |
| 4. Per-day inventory | **Yes** — occupancy is day×site |
| 5. Per-day site allocation | **Yes** — `booking_day_allocations` |
| 6. Per-day payment/pricing | **No** — amount derived from sites; day exceptions do not refund |
| 7. Per-day attendance/check-in | **Partial** — day identity used in attendance exceptions; pass check-in remains booking-level |
| 8. Historical remnant only | **No** — actively enforced in reservation + readiness |
| 9. Future multi-day feature not surfaced in UI | **Yes** — multi-day backend exists; Organizer day UI absent |
| 10. Combination | **Confirmed combination of 1–5 + 9** |

**Evidence:**

- Migration comment Phase 2A.5 / ADR-004: `backend/database/migrations/2026_07_14_000003_create_event_days_table.php:7-8`
- Generator modes: `backend/app/Services/EventDayGenerator.php:18-24,91-143`
- Full-event reservation across all active days: `backend/app/Services/BookingAllocationReservationService.php:132-183`
- Vendor cannot choose days at create (`event_day_ids` prohibited): `backend/app/Http/Controllers/Api/BookingController.php:63-68`

---

## 6. Event, Event-Day, Layout, and Booking Data Model

```text
carboot_events
  ├─ starts_at / ends_at / day_generation_mode
  ├─ event_days (1:N) ── unique(carboot_event_id, operational_date)
  │     └─ booking_day_allocations (1:N) ── FK restrict on delete
  ├─ event_layout_rows (1:N)
  │     └─ event_sites (1:N)
  └─ bookings (1:N)
        └─ booking_day_allocations (1:N) ── (booking, event_day, event_site)
```

Key integrity features on allocations (`2026_07_14_000004_...`):

- Unique `(event_day_id, event_site_id, active_lock)` — one active occupant per site-day
- Unique `(booking_id, event_day_id, event_site_id)`
- CHECK tying occupying statuses to `active_lock=1`
- `restrictOnDelete` from allocations → `event_days` / `event_sites` / `bookings`

Models: `CarbootEvent::eventDays()`, `EventDay`, `BookingDayAllocation`.

---

## 7. Complete `event_days` Lifecycle Trace

```text
Organizer creates event (StaffEventsPanel → POST /api/carboot-events)
    ↓
Event row created with starts_at/ends_at
    day_generation_mode defaults to calendar_days (column default / optional payload)
    ↓
[EXPECTED] Organizer calls POST /api/organizer/events/{id}/days/generate
           OR POST .../days (manual)
    ↓
[ACTUAL] No frontend call; seeders create no days
    ↓  ✗ BROKEN / DISCONNECTED
Organizer builds layout (Layout Management) — works independently
    ↓
EventLayoutReadinessService → NO_ACTIVE_EVENT_DAYS
    ↓
UI: Not Ready for Booking / Public Display + missing-days warning
    ↓  (no CTA to generate days)
Vendor site-availability / booking create
    ↓
Blocked: no_active_event_days / EVENT_LAYOUT_NOT_READY
```

| Step | Expected | Actual | Status |
|------|----------|--------|--------|
| 1. Event create | Event record | Yes — `CarbootEventController::store` | OK |
| 2. Days created with event | Optional intent via generator | **Not automatic** | Gap |
| 3. How days created | `EventDayGenerator` / manual store | API only | Backend-only |
| 4. Who authorized | Organizer / super_admin | Role middleware | OK |
| 5. API route | `POST .../days/generate`, `POST .../days` | Present in `routes/api.php:155-162` | OK |
| 6. Frontend calls route | Should | **No client usage found** | Broken UX |
| 7. Reachable from nav | Day config screen | **No screen** | Missing |
| 8. Auto from starts/ends | Generator can derive | Only if API invoked | Not wired |
| 9. Edit event sync days | Should sync or warn | **Update does not touch days** | Stale risk |
| 10. Shorten event removes days | Not automatic | Manual delete / replace | Gap |
| 11. Extend adds days | Not automatic | Manual generate/replace | Gap |
| 12. Timezone | MYT wall clock in generator + validators | Uses `config('app.timezone')` | Implemented |
| 13. Delete event cascades days | FK `cascadeOnDelete` | Yes | OK |
| 14. Delete day permitted | If no allocation history | Controller 409 + DB restrict | Strong |
| 15. Bookings depend on day | Full-event active days | Reservation requires ≥1 active day | Strict |
| 16. Released-day recovery | Phase 2B.4 queue | Read-only recovery UI exists | Implemented |
| 17. Availability / public display | Days required for availability; readiness for publish | Yes | Strict |
| 18. Vendor selects days | Full-event default | Day IDs **prohibited** on create | By design |
| 19. Bookings store days | Via allocations, not selected list | Yes | OK |
| 20. Payment / withdrawal / check-in | Multi-day via allocations; exceptions release days | Implemented in services/UI | Partial product |

---

## 8. Organizer Configuration and Discoverability Audit

### D2 — Frontend discoverability

| Question | Finding |
|----------|---------|
| Day-configuration UI exists? | **No** dedicated page/component for generate/CRUD days |
| Route/component? | N/A — only readiness warning + later exception/recovery UIs that *consume* days |
| Linked from Events page? | Events form has Layout Management only (`StaffEventsPanel.vue:97-103`) |
| Linked from Layout Management? | Warning text only; no generate CTA (`EventLayoutReadinessPanel.vue`) |
| Hidden by role/flag? | Not hidden — **absent** |
| Actionable CTA? | **No** |
| Can Organizer configure from visible workflow? | **No** (API-only) |
| Label clarity? | “event days” / “EventDays” is technical; conflicts with already-entered start/end |

**Finding:** Organizer cannot configure event days through the visible event or layout forms.

**Evidence:**

- `frontend/src/views/dashboards/staff/StaffEventsPanel.vue:1-110` — no day fields / no generate
- `frontend/src/services/organizerEventLayoutApi.js` — layout endpoints only; no `/days`
- Repo-wide frontend search: no `/days/generate` or `/event-days` API clients
- Phase 2A.5 doc (git `d84488f`): “Backend only — no cinema UI”

**Impact:** Blocking readiness with no operable fix path in the product UI.

**Confidence:** Confirmed

---

## 9. Root-Cause Analysis

### 9.1 Confirmed Causes

1. **Missing Organizer UI for event-day configuration** while readiness and booking enforce active days.
2. **Event create/update does not materialise days** (`CarbootEventController::store/update`).
3. **Seed / rebuild data has zero `event_days`** for all events on `cmart_db_rebuild`.
4. **Kedah (id=4) fails solely on `NO_ACTIVE_EVENT_DAYS`** with layout otherwise ready.
5. **Phase sequencing:** 2A.5 backend days → 2A.6/7 allocations depend on days → 3.5 readiness requires days → 3.6 layout UI ships without day setup UX.

### 9.2 Highly Likely Contributing Causes

1. Product assumption that Organizers would call generate via API/tools/tests only (E2E fixture commands create days; production UI does not).
2. Copy still says “must be configured” implying a human step that the UI never taught.

### 9.3 Possible Causes Requiring More Evidence

1. Whether any external Postman/scripts were expected as temporary Organizer tooling (not in frontend).
2. Whether Phase 5 BM-first UX deferred day wording without removing enforcement.

### 9.4 Causes Ruled Out

| Cause | Why ruled out |
|-------|----------------|
| Corrupted/missing `event_days` table | Migration Ran; table readable; count=0 is empty data, not missing schema |
| Wrong readiness reason mapping | Frontend explicitly handles `NO_ACTIVE_EVENT_DAYS` |
| Layout incorrectly failing | Kedah has no other operational blockers |
| Auth blocking Organizer from days API | Organizer role is allowed; issue is discoverability, not 403 for organizers |
| Duplicate/orphan day rows causing false negative | Zero day rows exist |

---

## 10. Readiness Logic Audit

### 10.1 Booking Readiness

`EventLayoutReadinessService::operationalBlockers()` requires:

1. At least one **active** `event_day`
2. Active layout rows with categories, active sites, space, labels, identity uniqueness, row linkage, etc.

`operational_ready` is true only when that list is empty.

Booking create also calls `BookingSiteCategoryValidator::assertEventOperationallyLayoutReady()` → same assessment → `EVENT_LAYOUT_NOT_READY` if not ready.

Reservation independently requires active days (`no_active_event_days`).

### 10.2 Public-Display Readiness

`public_ready = operational_ready && publicBlockers===[]`.

If operational is not ready, `publicBlockers()` returns **[]** (no public-specific reasons appended), but `public_ready` remains **false**.

So missing event days **blocks both** booking readiness and public-display readiness.

### 10.3 Backend-to-Frontend Reason Mapping

| Backend code | Frontend primary copy |
|--------------|----------------------|
| `NO_ACTIVE_EVENT_DAYS` | Dedicated warning (`missingEventDaysWarning`); also mapped in `READINESS_BLOCKER_MESSAGES` but filtered from list |
| Other operational/public codes | `READINESS_BLOCKER_MESSAGES` in `organizerEventLayoutMessages.js:143-160` |
| Unknown code | Falls back to raw code via `readinessMessage()` |

Badges use backend booleans `operational_ready` / `public_ready` directly — accurate.

### 10.4 Refresh and Staleness Behaviour

- `refreshLayout({ force: true })` reloads `GET /organizer/events/{id}/layout`, which re-runs readiness server-side (`OrganizerEventLayoutController`).
- No client-side readiness cache beyond in-memory panel state.
- Refresh **cannot** create missing days; button label “Refresh Layout” may imply a fix it cannot perform.

**Confidence:** Confirmed

---

## 11. Security and Authorization Audit

### 11.1 Authentication

Day mutation routes sit under `auth:sanctum` + `role:organizer,super_admin` (`routes/api.php:74,132-162`).

Unauthenticated → 401; wrong role → 403 via `EnsureRole`.

### 11.2 Role and Ownership Enforcement

| Actor | Day mutate/list | Evidence |
|-------|-----------------|----------|
| Organizer | Allowed | Role list `carbootOperationalRoles()` |
| Super Admin | Allowed (organizer-equivalent match) | `ManagementRole::matches` |
| CMart Management | Denied | Test `EventLayoutAndDaysTest::test_cmart_management_cannot_generate_layout_or_days` |
| Community / vendor | Denied | Not in middleware role list |
| Event ownership / branch scope | **Not enforced** | No `organizer_id` / `branch_id` on events; any Organizer can target any `carboot_event` / `event_day` ID |

**Answers:**

- Can Organizer modify days outside “their” branch? **There is no branch isolation** — any Organizer can modify any event’s days (IDOR-by-shared-tenant model). Confidence: Confirmed for current single-tenant design.
- Can vendor call mutation endpoints? **No** (role middleware). Confidence: Confirmed.
- Can CMart Management mutate days? **No** (403). Confidence: Confirmed (test + middleware).
- Backend enforces independently of frontend? **Yes.** Confidence: Confirmed.

### 11.3 IDOR and Cross-Scope Risks

| Risk | Assessment |
|------|------------|
| Cross-event day update by guessing `event_day` id | Possible for any Organizer/super_admin — no per-event ownership check in `EventDayController` |
| Cross-branch | N/A — no branch model |
| Community forging day generate | Blocked by role |
| CMart Management governance bypass | Blocked for these routes |

### 11.4 Input Validation

| Check | Present? |
|-------|----------|
| Date format | Yes (`date`) |
| `ends_at` after `starts_at` | Yes |
| Duplicate date | Unique index + QueryException mapping |
| Within parent event range | **No** in `validateDay` / store |
| Past-date rejection | **No** |
| Timezone normalization | Yes (MYT) |
| Max range / DoS bound on generate | **No** explicit max span |
| Status enum | Yes |
| Bulk payload limits | Generate has no day-count cap beyond date span |
| Mass assignment | Fillable list used |
| Event status compatibility | **Not checked** when creating days |
| History lock on structural edits | Yes (409) |

### 11.5 Database Integrity

| Protection | Mechanism | Strength |
|------------|-----------|----------|
| Duplicate event+date | UNIQUE `event_days_event_date_unique` | Strong |
| Orphan days | FK cascade from events | Strong |
| Delete day with allocations | `restrictOnDelete` + app 409 | Strong |
| Double occupancy | UNIQUE day+site+active_lock + CHECK | Strong |
| Cross-event site/day mismatch at DB | Not a composite FK across event; enforced in reservation service | Moderate (service) |

### 11.6 Concurrency and Transaction Safety

- Day generate: `DB::transaction` in `EventDayGenerator`
- Reservation: outer booking transaction + `lockForUpdate` on booking, event, sites, days; unique constraint converts races to conflicts (`BookingDayAllocationReservationTest` covers stale race)
- Replace blocked when allocation history exists (`DomainConflictException`)

---

## 12. Security and Strictness Matrix

Scoring key for **Effective strength**: Strong / Moderate / Weak / Missing / Unknown.

| Rule | DB | Backend | Authorization | Frontend | Tests | Effective strength |
|------|:--:|:-------:|:--------------:|:--------:|:-----:|--------------------|
| Unique event date per event | Yes | Yes (422 map) | Role only | N/A | Covered | **Strong** |
| Date must fall within event range | No | No | — | No | Not covered | **Missing** |
| Only authorized Organizer can mutate | — | Middleware | Role; no ownership | Hidden (absent UI) | CMart denied covered | **Moderate** (role strong; ownership missing) |
| Bookings require configured days | — | Readiness + reservation | — | Warning only | Readiness + reservation tests | **Strong** |
| Allocated day cannot be deleted unsafely | Restrict FK | 409 history | — | N/A | Allocation history tests | **Strong** |
| Event date edit syncs days | No | No | — | No | Not covered | **Missing** |
| Vendor cannot pick arbitrary day subset at create | — | `prohibited` fields | Community routes | Full-event UX | Booking create tests | **Strong** |
| One active occupant per site-day | Unique + CHECK | lockForUpdate | — | — | Race test | **Strong** |
| CMart Management cannot mutate days | — | 403 | Role | Nav hidden | Explicit test | **Strong** |

---

## 13. Active Database Evidence

**Database inspected:** `cmart_db_rebuild` (resolved from Laravel config; credentials not exposed).

**Persistent-data safety:** Only `SELECT` / aggregate / in-memory readiness assessment; no writes.

### 13.1 Aggregate Findings

| Metric | Count |
|--------|------:|
| Events | 4 |
| Event-day rows | 0 |
| Events with zero days | 4 |
| Events with one day | 0 |
| Events with multiple days | 0 |
| Days outside parent range | 0 |
| Duplicate event/date pairs | 0 |
| Orphan event days | 0 |
| Booking-day allocations | 0 |
| Orphan allocations | 0 |
| Bookings without allocations | 1 (legacy seed booking on event 1, Approved) |
| Event days with dependent allocations | 0 |
| Layout rows (all events) | 4 (all on event 4) |
| Event sites | 64 (all on event 4) |

Events with valid physical layout failing readiness **solely** due to missing days: **1** (event id=4).

### 13.2 `Kedah International Kedah` Case Trace

| Field | Value |
|-------|--------|
| Event ID | **4** (unique title match) |
| Title | Kedah International Kedah |
| Status | Available |
| Range | `2026-07-25 10:00:00` → `2026-07-25 22:00:00` |
| `day_generation_mode` | `calendar_days` |
| Public layout published | null |
| Event-day count | **0** |
| Layout rows / active / public | 4 / 4 / 4 |
| Sites / active | 64 / 64 |
| Bookings | 0 |
| Allocations | 0 |
| `operational_ready` | **false** |
| `public_ready` | **false** |
| Operational blockers | **`NO_ACTIVE_EVENT_DAYS` only** |
| Public-specific blockers appended | none (gated by operational failure) |

**Why both badges fail:** `public_ready` requires operational readiness first.

**Expected vs gap:** For a single calendar day event, generate would create **one** active day; failure is missing generation, not multi-day complexity.

### 13.3 Data Anomalies

1. **System-wide zero `event_days`** after clean rebuild + seed — structural setup gap, not one bad row.
2. **Seed booking (id=1)** on event 1 is Approved with **zero** allocations — pre-allocation / legacy seed artefact; not caused by Kedah layout work.
3. Events 1–3 lack layouts **and** days (multiple blockers).

### 13.4 Persistent-Data Safety Confirmation

No INSERT/UPDATE/DELETE/DDL/migrate/seed/generate executed against `cmart_db_rebuild` during this audit.

---

## 14. Functional Dependency Map

```text
event_days
  ├─ EventLayoutReadinessService (operational gate)
  ├─ BookingSiteCategoryValidator::assertEventOperationallyLayoutReady
  ├─ VendorEventSiteAvailabilityService (hard fail if empty)
  ├─ BookingAllocationReservationService (allocates every active day × sites)
  ├─ BookingAllocationLifecycleService (confirm/release by day)
  ├─ Attendance exceptions (retain/release day IDs)
  ├─ OrganizerReleasedDayRecoveryService (released day×site slices)
  ├─ Organizer booking reassignment / category placement (retained day IDs)
  ├─ VendorBookingPresenter / withdrawal reconciliation UI
  └─ E2E fixture commands (create days for browser tests)
```

Independent of days: public calendar listing by `starts_at`/`ends_at`; event CRUD images/fees; physical layout row/site editing (until readiness/booking).

---

## 15. Is `event_days` Actually Required?

### 15.1 Requirements It Currently Supports

- Per-day site occupancy and concurrency control
- Multi-day full-event booking expansion
- Partial day release (attendance exceptions) without deleting the booking
- Released-day recovery inventory
- Explicit disable/cancel of an operating day without deleting the event
- Overnight vs calendar-day generation modes

### 15.2 Requirements That Could Work Without It

- Single-day events with one continuous window (could use event range only)
- Public calendar display
- Simple “book a bay for this event” without partial-day release

### 15.3 Current and Future Multi-Day Requirements

Backend already assumes multi-day (calendar span → N days; full-event allocations; exceptions). UI and seeders treat most events as single-day windows. Multi-day is **implemented below the UI**, not productized for Organizers.

### 15.4 Cost of Keeping It

- Extra concept for Organizers
- Sync risk when dates change
- Mandatory setup step (currently broken UX)
- More tests/fixtures

### 15.5 Cost of Removing It

- Rewrite allocation uniqueness model
- Rebuild attendance exception + recovery
- Data migration of any future allocations
- Large regression surface across Phase 2A–2B features

**Verdict:** For the **current codebase**, `event_days` is required as the occupancy spine. For **FYP Organizer UX**, making Organizers manually manage it is over-exposed.

---

## 16. Architecture Options

### 16.1 Option A — Explicit Organizer-Managed Days

Keep manual generate/CRUD as the primary path; build a full Day Configuration UI.

- **Benefits:** Maximum flexibility; matches Phase 2A.5 wording
- **Risks:** Training burden; easy to forget; current gap continues until UI ships
- **Complexity:** Medium frontend; low backend change
- **Migration:** None
- **Security:** Same as today; UI must not weaken auth
- **Suitability now:** Poor as *sole* model given BM-first / non-technical Organizers

### 16.2 Option B — Automatically Generated and Synchronized Days

On event create/update (and optionally layout readiness repair), call `EventDayGenerator` with history-safe replace rules.

- **Benefits:** Matches mental model “I already set start/end”; fixes Kedah immediately after save; reuses existing service
- **Risks:** Must define sync when bookings exist (already partially handled via replace block)
- **Complexity:** Low–medium backend; small frontend messaging
- **Migration:** Backfill generate for existing zero-day events (separate approved task)
- **Security:** Same endpoints/services; prefer server-side auto over exposing raw CRUD
- **Suitability now:** **Best fit**

### 16.3 Option C — Dynamically Derived Dates

Remove persistent days; derive from range at read/write time.

- **Benefits:** Simpler Organizer model
- **Risks:** Breaks allocation FKs, history, recovery, disable-one-day
- **Complexity:** Very high
- **Migration:** Hard
- **Suitability now:** Unsuitable for current allocation architecture

### 16.4 Option D — Single-Day Event Simplification

Product rule: one event = one operating day; still may keep one `event_day` row internally.

- **Benefits:** Matches most seeded events (including Kedah)
- **Risks:** Loses multi-day without a later redesign
- **Complexity:** Low if implemented as auto single-day generate
- **Suitability now:** Compatible **as a product constraint** layered on Option B (`single_session` or one calendar day)

### 16.5 Option E — Session-Based Model

Morning/evening sessions, pauses, cross-midnight sessions as first-class.

- **Benefits:** Richer ops
- **Risks:** Far beyond current FYP scope; UI/auth/pricing complexity
- **Suitability now:** Defer

---

## 17. Comparative Decision Matrix

**Scale:** 1 = poor/high cost, 5 = excellent/low cost for that criterion.  
**Note:** For “Backend/Frontend complexity” and “Migration risk” / “Testing burden”, higher score means *more favourable* (lower burden).

| Criterion | A Explicit | B Auto-sync | C Dynamic | D Single-day product | E Sessions |
|-----------|:----------:|:-----------:|:---------:|:--------------------:|:----------:|
| Organizer simplicity | 2 | 5 | 5 | 5 | 2 |
| Alignment with current Carboot workflow | 3 | 5 | 2 | 4 | 2 |
| Multi-day support | 5 | 5 | 3 | 1 | 5 |
| Data integrity | 4 | 4 | 2 | 4 | 3 |
| Security | 3 | 4 | 3 | 4 | 3 |
| Backend complexity (favourable) | 4 | 3 | 1 | 4 | 1 |
| Frontend complexity (favourable) | 2 | 4 | 4 | 4 | 1 |
| Migration risk (favourable) | 5 | 4 | 1 | 4 | 1 |
| Testing burden (favourable) | 3 | 3 | 1 | 4 | 1 |
| Future maintainability | 3 | 4 | 2 | 3 | 3 |
| Suitability for current FYP scope | 2 | 5 | 1 | 4 | 1 |
| **Total** | **36** | **46** | **25** | **41** | **23** |

**Weighting / override:** Option D scores well but is a **product constraint**, not a full replacement for the allocation spine. Prefer **B**, optionally defaulting mode to single-day behaviour for typical events. Do not pick C/E for current scope despite theoretical elegance.

---

## 18. Recommended Direction

### 18.1 Immediate Problem-Solving Direction

1. **Do not ask Organizers to understand “event days” as a separate academic concept.**
2. After product-owner approval: auto-generate days when an event is saved (or one-shot backfill + generate for existing events), then refresh layout readiness.
3. Until then, temporary Operator workaround (out of band): authenticated Organizer `POST /api/organizer/events/{id}/days/generate` — **not** implemented by this audit.

### 18.2 Short-Term Architecture Direction

**Option B**: persist `event_days`, generate/sync from `starts_at`/`ends_at` (+ `day_generation_mode`), keep allocation-history replace guards.

### 18.3 Long-Term Optional Direction

- Optional advanced UI to disable a single day or switch generation mode
- True multi-day marketing windows with clear Organizer preview of generated days
- Stronger ownership scoping if multi-organizer tenancy appears

### 18.4 Deferred or Unnecessary Work

- Option C removal of `event_days`
- Option E session model
- Large Organizer “day spreadsheet” CRUD as the primary path (Option A alone)

---

## 19. Organizer UX and Mental-Model Findings

| # | Question | Answer |
|---|----------|--------|
| 1 | Understand “event days”? | Unlikely without training |
| 2 | Know where to configure? | No |
| 3 | Notice actionable? | No — status only |
| 4 | Identifies missing date/operation? | No specific date or “Generate schedule” action |
| 5 | “Refresh Layout” implies fix? | Yes, misleading |
| 6 | Confused by existing start/end? | Yes — dates already entered |
| 7 | Layout Management correct place? | Only as a *symptom* surface; schedule belongs with Event save |
| 8 | Auto on save? | Yes — recommended |
| 9 | Show operating-days on form? | Optional read-only preview after auto-gen |
| 10 | CTA to config screen? | Needed if manual path retained |
| 11 | Too technical vs Phase 5 BM-first? | Yes — English technical jargon and EventDay labels elsewhere |

**Journey:**

```text
Current: Create event (dates) → Layout Management → Ready map → blocked on event days → stuck
Expected: Create/save event → days materialised → Layout → Ready for Booking
Failure point: missing generation + missing UI after Phase 3.5 mandatory readiness
Why stuck: warning without operable control
Minimum conceptual change: treat days as system schedule derived from event dates
```

---

## 20. Test Coverage and Confidence Assessment

| Area | Coverage |
|------|----------|
| Event-day create/generate/update/disable | **Covered** (`EventLayoutAndDaysTest`) |
| Authorization (CMart denied) | **Covered** |
| Cross-branch access | **Not covered** (no branch model) |
| Duplicate prevention | **Covered** (unique + 422) |
| Event-date synchronization | **Not covered** |
| Layout readiness incl. `NO_ACTIVE_EVENT_DAYS` | **Covered** |
| Public visibility readiness | **Partial** (readiness tests; publish flows elsewhere) |
| Vendor availability without days | **Covered** in availability/reservation tests |
| Multi-day booking allocations | **Covered** |
| Payment / withdrawal / recovery | **Partial–Covered** in dedicated feature tests |
| Concurrent allocation | **Covered** (race → conflict) |
| Event deletion cascade | **Partial** (schema); dedicated day cascade test not primary |
| Zero / one / multi days | **Partial** (fixtures create days; zero-day readiness covered) |
| Timezone / midnight | **Partial** (`single_session` overnight test) |
| Frontend calls generate API | **Not covered** (no client) |

**Tests executed in this audit:** none — `DB_DATABASE=cmart_test` for PHPUnit is **missing** on the MariaDB instance; running Feature tests would risk unsafe DB selection or failure before assertions.

---

## 21. Git History and Requirement Timeline

| Date/commit | Change | Intended purpose | Current consequence |
|-------------|--------|------------------|---------------------|
| 2026-07-14 `d84488f` | `event_days`, `EventDayGenerator`, `EventDayController`, allocations | Phase 2A.5–2A.7 occupancy spine; **backend only, no cinema UI** | Days mandatory later; UI never built |
| 2026-07-15 `a99f100` | Released-day recovery | Partial day release ops | Depends on day rows existing |
| 2026-07-16 `6890d6e` | `EventLayoutReadinessService` + Layout UI | Make layout bookable/public-ready | Adds `NO_ACTIVE_EVENT_DAYS` into Organizer UI without generate CTA |
| 2026-07-21 (report `08`) | Clean rebuild `cmart_db_rebuild` + seed | Restore DB | Seeded events still have **0** days |
| Ongoing Organizer use | Layout generated for Kedah | Physical map ready | Blocked solely on missing days |

Phase docs declared 2A.5 / 3.5 / 3.6 **Complete** while 2A.5 explicitly deferred cinema UI — creating a **documented completeness vs Organizer-operability mismatch**.

---

## 22. Risks if the Current Design Is Left Unchanged

1. **All events unbookable** until someone hits an undocumented API.
2. Organizer distrust / support load from non-actionable readiness.
3. Public map cannot publish while operational days missing.
4. Date edits silently desynchronize if days are later added manually.
5. Out-of-range manual days possible (validation gap).
6. Any Organizer can mutate any event’s days (shared-tenant IDOR).
7. Rebuild/seed procedures will keep reproducing the gap.

---

## 23. Questions Requiring Product-Owner Decision

1. Should operating days be **automatic** from event dates (Option B), or remain a **manual Organizer step** (Option A)?
2. Is multi-day Carboot in scope for this FYP, or should product default to **one operating day per event**?
3. When bookings/allocations exist and dates change, is the rule **block date change**, **block day replace**, or **allow non-destructive additive sync only**?
4. Should advanced day disable/cancel remain Organizer-visible or admin-only?
5. Is shared-tenant Organizer access to all events acceptable, or is ownership scoping required?

---

## 24. Proposed Next Investigation or Implementation Boundaries

**Do not start until PO decides Option A vs B (and multi-day scope):**

- Auto-generate on create/update
- Backfill existing events
- New Day Configuration UI
- Removing/replacing `event_days`
- Changing readiness rules to ignore days
- Seeder changes that insert days

**Safe follow-ups after decision:**

- Implementation plan + tests on `cmart_test`
- Explicit sync policy matrix
- UX copy in BM for readiness CTA

---

## 25. Commands Executed

```text
git status / branch / rev-parse / log (event_days-related paths)
php artisan about
php artisan migrate:status (filtered)
Laravel bootstrap read-only: config DB name; aggregate SELECTs; EventLayoutReadinessService::assess
git show d84488f / 6890d6e docs and file introductions
SHOW DATABASES probe for cmart_test (missing)
Frontend/backend ripgrep-style searches via tooling
```

No migrate, seed, tinker writes, generate, or destructive SQL.

---

## 26. Files Inspected

**Backend (primary):**  
`EventDay.php`, `EventDayController.php`, `EventDayGenerator.php`, `EventLayoutReadinessService.php`, `BookingAllocationReservationService.php`, `VendorEventSiteAvailabilityService.php`, `BookingSiteCategoryValidator.php`, `CarbootEvent.php`, `CarbootEventController.php`, `BookingController.php` (store/exception fragments), `BookingDayAllocation.php`, `OrganizerReleasedDayRecoveryService.php`, `OrganizerEventLayoutController.php`, `ManagementRole.php`, `EnsureRole.php`, `routes/api.php`, migrations `2026_07_14_000003`, `2026_07_14_000004`, tests `EventLayoutAndDaysTest.php`, `OrganizerEventLayoutReadinessTest.php`, `phpunit.xml`

**Frontend:**  
`EventLayoutReadinessPanel.vue`, `organizerEventLayoutMessages.js`, `organizerEventLayoutApi.js`, `OrganizerEventLayoutPanel.vue`, `StaffEventsPanel.vue`, related recovery/exception/withdrawal components (consumption only)

**Reports/docs:**  
`report/02_schema_and_data_source_inventory.md`, `report/08_phase2_clean_rebuild_report.md`, git-restored Phase 2A.5 / 3.5 / 3.6 markdown

---

## 27. Repository Change Verification

Intended sole audit write:

```text
report/event_days_deep_audit_and_decision_report.md
```

Pre-existing working-tree artefacts (not modified by this audit): recovery reports and path JSON under `report/` (`00`–`08`, `_phase0b_paths.json`, `_phase1_paths.json`, `command_log.md`) as observed at conversation start.

No application code, migrations, or `.env` changes.

---

## 28. Final Audit Verdict

**`event_days` is a mandatory occupancy/schedule spine already enforced by readiness and booking, but Organizer configuration was never productized—so layout-ready events such as Kedah International Kedah (id=4) correctly fail with `NO_ACTIVE_EVENT_DAYS` due to an implementation/UX gap, not because start/end dates are invalid.**

---

## Appendix — Direct Answers to Mandatory Questions

1. **What is `event_days` used for?** Persistent operational bookable days; basis for day×site allocations, availability, exceptions, recovery, and readiness.  
2. **Why aren’t `starts_at`/`ends_at` sufficient?** Booking inventory and concurrency are modelled per day row, not the parent window.  
3. **Who configures them?** Organizer / super_admin via API (intended).  
4. **How?** `POST /api/organizer/events/{id}/days/generate` or manual `POST .../days`.  
5. **Visible/usable in UI?** **No.**  
6. **Why Kedah fails booking readiness?** Zero active event days → `NO_ACTIVE_EVENT_DAYS`.  
7. **Public-display too?** **Yes** — `public_ready` requires operational readiness.  
8. **Expected or gap?** Enforcement is expected; **missing configuration path is the gap**.  
9. **Mandatory where?** Service/readiness/reservation layers (not DB NOT NULL count); unique/FK exist when rows present.  
10. **Duplicate prevention?** Unique `(carboot_event_id, operational_date)` + API mapping.  
11. **Out-of-range prevention?** **Not enforced.**  
12. **Unauthorized modification?** Sanctum + organizer/super_admin roles; CMart/community denied.  
13. **Unsafe delete after bookings?** App 409 + FK restrict.  
14. **Race conditions?** Transactions, `lockForUpdate`, unique active occupancy.  
15. **Stale days after date edit?** **Yes possible** — no sync.  
16. **Tests prove sync safety?** **No.**  
17. **Over-engineered for FYP scope?** Persistence is justified; **manual Organizer management is over-exposed**.  
18. **Break if removed?** Allocations, exceptions, recovery, multi-day occupancy, readiness as written.  
19. **Still work without it?** Calendar listing, simple single-window booking *if redesigned*.  
20. **Safest simplest direction?** **Option B auto-generate/sync**, optionally single-day default.  
21. **PO must decide?** Auto vs manual days; multi-day scope; date-change policy with existing allocations.  
22. **Do not implement until decided?** Auto-gen, backfill, day UI, schema removal, readiness rule weakening.
