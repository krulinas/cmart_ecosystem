# Layout Management Domain and Architecture Audit

**Status:** Read-only audit (no implementation, no tests executed, no database mutations)  
**Date:** 2026-08-05  
**Scope:** Organizer Layout Management for Carboot event parking layouts at CMart Changlun  
**Evidence basis:** Backend models/services/controllers/migrations/routes and frontend Layout Management UI only  

---

## 1. Executive verdict

The working three-layer hypothesis is **directionally correct as a target domain model**, but **the current codebase does not implement it**. Layout is an **event-owned free-form graph** of `event_layout_rows` + `event_sites`, with a hard-coded A–D×16 generator as a convenience path—not a venue physical template.

1. **No venue physical template exists.** There is no `Venue` model, no reusable physical row/site catalogue, and no venue-scoped maximum-row constraint. Rows belong directly to `carboot_events`.
2. **`max_slots` is community RSVP capacity, not vendor/booth layout capacity.** Standard generation ignores it and always creates **64 active** sites (`A`–`D` × 16).
3. **Add Row is unrestricted free-form identity.** A fifth row labelled `Foodie` with zero sites is allowed by design today; readiness only detects the defect afterward via `ACTIVE_ROW_HAS_NO_ACTIVE_SITES`.
4. **Safe mutation locks exist for allocation history**, but **Undo Generation / Reset Layout do not exist**. Row edit/delete live mainly under collapsed “Advanced row tools,” so primary visual UX feels control-poor even though APIs exist.
5. **Recommended target:** **Option B — Venue physical template**, instantiated/configured per event, with Option A guardrails as an interim delivery phase only.

---

## 2. Current architecture

### 2.1 Frontend (Layout Management)

| Concern | Path | Notes |
| --- | --- | --- |
| Main panel | `frontend/src/views/dashboards/organizer/OrganizerEventLayoutPanel.vue` | Event selector, readiness, visual map, focused controls, advanced rows, publication |
| Readiness UI | `frontend/src/components/organizer/layout/EventLayoutReadinessPanel.vue` | Title copy: **Setup notice** |
| Visual map | `frontend/src/components/layout/VisualParkingLayout.vue` + `frontend/src/utils/visualParkingLayout.js` | Entrance/exit/aisle are **presentation heuristics**, not DB entities |
| Row card / advanced tools | `frontend/src/components/organizer/layout/EventLayoutRowCard.vue` | Edit / Delete / Archive / Generate Sites / reorder |
| Focused site/row strip | `frontend/src/components/organizer/layout/OrganizerFocusedSiteControls.vue` | Edit site/row when a site is selected |
| Add/Edit row modal | `frontend/src/components/organizer/layout/LayoutRowFormModal.vue` | Free-text **Row name** + category |
| Site form / generate | `LayoutSiteFormModal.vue`, `LayoutSiteGenerationModal.vue` | Per-row site CRUD/generate |
| Standard generate modal | `frontend/src/components/organizer/layout/StandardParkingLayoutModal.vue` | Categories A–D + space type |
| API client | `frontend/src/services/organizerEventLayoutApi.js` | Organizer layout endpoints |
| Copy / blocker messages | `frontend/src/utils/organizerEventLayoutMessages.js` | Includes `setupNoticeTitle: 'Setup notice'` |
| Helpers | `frontend/src/utils/organizerEventLayoutHelpers.js` | Sort/count/occupancy helpers |
| Public layout | `PublicEventLayoutSection.vue`, `publicEventLayoutApi.js`, `utils/publicEventLayout.js` | Visitor projection |

**Routing:** Layout is an Organizer admin hash panel (`#layout`), not a dedicated Vue Router page. Query `?eventId=` drives selection (`OrganizerEventLayoutPanel.vue`).

### 2.2 Backend routes (`backend/routes/api.php`)

All under `auth` + `role:` `ManagementRole::carbootOperationalRoles()` → `organizer`, `super_admin`, prefix `/organizer`:

| Method | Path | Controller@method |
| --- | --- | --- |
| GET | `/organizer/events/{carboot_event}/layout` | `OrganizerEventLayoutController@show` |
| GET | `/organizer/events/{carboot_event}/layout/readiness` | `OrganizerEventLayoutController@readiness` |
| POST | `/organizer/events/{carboot_event}/layout/standard-template` | `OrganizerEventLayoutController@generateStandardTemplate` |
| POST | `/organizer/events/{carboot_event}/layout/publish` | `OrganizerEventLayoutController@publish` |
| POST | `/organizer/events/{carboot_event}/layout/unpublish` | `OrganizerEventLayoutController@unpublish` |
| POST | `/organizer/events/{carboot_event}/layout/rows` | `OrganizerEventLayoutRowController@store` |
| PATCH | `/organizer/events/{carboot_event}/layout/rows/reorder` | `OrganizerEventLayoutRowController@reorder` |
| PATCH | `/organizer/events/{carboot_event}/layout/rows/{row}` | `OrganizerEventLayoutRowController@update` |
| DELETE | `/organizer/events/{carboot_event}/layout/rows/{row}` | `OrganizerEventLayoutRowController@destroy` |
| PATCH | `.../rows/{row}/archive` / `unarchive` | archive / unarchive |
| POST | `.../rows/{row}/sites` | `OrganizerEventLayoutSiteController@store` |
| POST | `.../rows/{row}/sites/generate` | `OrganizerEventLayoutSiteController@generate` |
| PATCH | `.../rows/{row}/sites/reorder` | reorder |
| PATCH | `.../sites/{site}` | update |
| DELETE | `.../sites/{site}` | destroy |

Legacy Phase 2A site/day routes still exist (`EventSiteController`, `EventDayController`) under the same organizer group.

Public: `GET /events/{event}/layout` → `PublicEventLayoutController@show`.

### 2.3 Core services / models

| Class | Role |
| --- | --- |
| `App\Services\EventLayoutService` | `createRow`, `updateRow`, `reorderRows`, `deleteEmptyRow`, `archiveRow`, `unarchiveRow`, `createSite`, `generateSites`, `updateSite`, `deleteSite`, … |
| `App\Services\StandardEventLayoutGenerator` | Empty-layout-only A–D×16 generator |
| `App\Services\EventLayoutRowSiteGenerator` | Per-row bulk site generation (`MAX_SITES_PER_REQUEST = 100`) |
| `App\Services\EventSiteLayoutGenerator` | Legacy full-event bulk generation (Phase 2; no row binding) |
| `App\Services\EventLayoutReadinessService` | Operational + public readiness blockers |
| `App\Services\EventLayoutLockService` | Allocation-history locks; occupancy summaries |
| `App\Services\EventLayoutAuditLogger` + `EventLayoutAuditLog` | Append-only audit actions (no undo replay) |
| `App\Services\PublicEventLayoutService` | Public projection of active/public rows + active sites |
| `App\Services\VendorEventSiteAvailabilityService` | Vendor bookable sites (active only) |
| `App\Services\BookingAllocationReservationService` | Requires `EventSite::STATUS_ACTIVE` for reservation |
| Models | `EventLayoutRow`, `EventSite`, `EventDay`, `CarbootEvent`, `BookingDayAllocation`, `Space`, `VendorCategory` |

**Authorization:** Organizer-equivalent roles only (`ManagementRole::carbootOperationalRoles()`). No finer per-layout capability gate beyond that middleware.

### 2.4 Hypothesis check

| Layer | Present today? | Evidence |
| --- | --- | --- |
| 1. Venue physical template | **No** | No Venue model; standard template hard-coded in PHP constants |
| 2. Event capacity configuration | **Partial** | `operational_status` / `is_active` / `is_public`; **not** tied to `max_slots` or a vendor booking limit field |
| 3. Safe event layout operations | **Partial** | Locks + delete/archive protections; **no** undo/reset; Add Row unconstrained |

**Conclusion:** Current code treats event rows/sites as **unrestricted free-form event data**, with optional standard A–D convenience generation.

---

## 3. Current data model

### 3.1 Entity relationships (compact)

```text
CarbootEvent (1)
  ├── EventDay (*)                 operational calendar days
  ├── EventLayoutRow (*)           free-form rows owned by event
  │     ├── vendor_category_id     category assignment (not physical identity)
  │     └── EventSite (*)          physical bay instances for THIS event
  │           ├── space_id         pricing/size catalogue (Space)
  │           └── BookingDayAllocation (*)
  │                 └── Booking
  └── registeredUsers (*)          community RSVP; gated by max_slots

EventLayoutAuditLog (*)            per-event mutation audit (no snapshot undo API)
```

### 3.2 Ownership and identity answers

| Question | Answer |
| --- | --- |
| Do rows belong to a venue or event? | **Event only** (`event_layout_rows.carboot_event_id`) |
| Are physical sites recreated per event? | **Yes** — sites are event-scoped rows in `event_sites` |
| Reusable venue template? | **No** |
| Site code uniqueness | Unique per **event**: `(carboot_event_id, label)` and `(carboot_event_id, row_label, position_number)` |
| Row identity vs category label | Row identity = `event_layout_rows.label` (+ `slug`). Category is separate `vendor_category_id`. UI “Row name” can be set to a category-like string (`Foodie`) and becomes physical row identity |
| Empty rows valid? | **Yes at write time** — createRow does not require sites. Readiness later blocks |
| Entrance / exit / aisle | **Presentation + metadata hints**, not first-class entities. Standard generator stores orientation/aisle hints in `event_sites.metadata`; visual layer inserts aisle between B and C via `shouldInsertAisleBetween()` when layout looks like A–D×16 |

### 3.3 Coordinate / ordering fields

| Field | Table | Role |
| --- | --- | --- |
| `label` | `event_layout_rows` | Row identity string (A, B, Foodie, …) |
| `display_order` | `event_layout_rows` | Row sort order; uniqueness not enforced at DB except readiness public duplicate check |
| `row_label` | `event_sites` | Denormalized row identity on site; updated when row renamed |
| `position_number` | `event_sites` | Position within row identity |
| `grid_row` | `event_sites` | Visual/grid row index; **NOT NULL** in schema |
| `grid_column` | `event_sites` | Visual/grid column |
| `display_order` | `event_sites` | Site sort within row |
| `operational_status` | `event_sites` | `active` / `unavailable` / `disabled` |
| `event_layout_row_id` | `event_sites` | FK to row (nullable for legacy unresolved sites) |

**`grid_row` mandatory?** Schema requires it (`unsignedInteger`, non-nullable). `EventLayoutService::createSite` always writes `(int) $data['grid_row']`. `EventLayoutRowSiteGenerator` defaults omitted `grid_row` to **1** (can collide across multiple free-form rows if organizers generate without specifying distinct grid rows). Standard generator sets `grid_row` = 1..4 for A–D.

### 3.4 Key migrations

- `backend/database/migrations/2026_05_21_000002_create_carboot_events_table.php` — `max_slots` nullable unsignedInteger  
- `2026_07_14_000001_create_event_sites_table.php` — event sites foundation  
- `2026_07_16_000006_create_event_layout_rows_table.php` — event layout rows  
- `2026_07_16_000007_add_event_layout_row_id_to_event_sites_table.php`  
- `2026_07_16_000008_backfill_event_layout_rows_from_sites.php`  
- `2026_07_16_000012_create_event_layout_audit_logs_table.php`  

### 3.5 `max_slots` and readiness

`max_slots` is **not** part of layout readiness. Readiness cares about active days, active rows/sites, categories, duplicates, public flags—not registration capacity.

---

## 4. `max_slots` semantics

### 4.1 What the code actually does

| Path | Behaviour |
| --- | --- |
| Schema | `carboot_events.max_slots` nullable integer |
| Model | `CarbootEvent::$fillable` + cast; `syncCapacityStatus()` compares **registeredUsers count** to `max_slots` and sets event `status` (`Closed` / `Almost Full` / `Available`) |
| Registration | `EventRegistrationController::register` — pessimistic lock; rejects when `currentCount >= max_slots` |
| Event CRUD validation | `CarbootEventController` — `nullable|integer|min:1` |
| Staff UI | `StaffEventsPanel.vue` label: **“Max slots (optional)”** — no explanation that this is community RSVP |
| Layout generation | **Not read** by `StandardEventLayoutGenerator` |
| Layout readiness | **Not used** |
| Vendor booking | Uses **active** `EventSite` availability; **no** `max_slots` gate found in booking/layout allocation paths audited |
| Public layout | Active public sites only; ignores `max_slots` |
| Post-event reports | Explicitly note `max_slots` is **not** booth capacity (`PostEventSummaryAggregator`) |

### 4.2 Precise answers

| Question | Answer |
| --- | --- |
| Is `max_slots` a registration limit? | **Yes — community RSVP / registered users** |
| Is it a physical site count? | **No** |
| Used by both concepts? | **No** (not for layout/vendor capacity) |
| Does Standard Layout read it? | **No** |
| Can active bookable sites exceed it? | **Yes** (e.g. 64 active sites while `max_slots = 10`) |
| Can approved vendor bookings exceed it? | **Yes relative to `max_slots`** — vendor booking is not capped by `max_slots` |
| Can the event limit change after reservations? | Event update allows changing `max_slots`; **no layout/reservation reconciliation** found against active sites |
| Does UI wording explain meaning? | **Weak** — “Max slots (optional)” only |

### 4.3 Product implication

Observed “event booking limit of 10” **must not be assumed identical to `max_slots`** unless product explicitly redefines that field. Implementing “activate N sites from `max_slots`” would couple **community RSVP** to **vendor booth opening** incorrectly.

**Open product decision:** introduce a distinct field (e.g. `vendor_site_open_limit` / `target_active_sites`) **or** redefine `max_slots` with a breaking semantics change and UI rename. See §18.

---

## 5. Standard generation findings

**Entry:** UI `StandardParkingLayoutModal` → `generateStandardParkingLayout` → `POST .../layout/standard-template` → `OrganizerEventLayoutController::generateStandardTemplate` → `StandardEventLayoutGenerator::generate`.

| Topic | Finding |
| --- | --- |
| Default rows | **4** — constants `ROW_LABELS = ['A','B','C','D']` |
| Sites per row | **16** — `SITES_PER_ROW = 16` |
| Always 64 active? | **Yes** — every created site uses `EventSite::STATUS_ACTIVE` |
| Respects event booking limits / `max_slots`? | **No** |
| Target active-site count? | **Not supported** |
| Site selection for activation | N/A — all generated sites active |
| Idempotent / repeatable? | **Rejected** if any row or site exists (`LAYOUT_ALREADY_EXISTS`) |
| Can duplicate rows/sites? | Not via standard generator on non-empty layout; empty-only |
| Replaces existing layout? | **No** — refuses |
| Protects reserved/booked? | Blocks if allocation history / structural lock (`ALLOCATION_HISTORY_PRESENT`); also refuses if public layout published |
| Partial generation? | **No** (all-or-nothing 4×16) |
| Transactional? | **Yes** — `DB::transaction` + event `lockForUpdate` |
| Midway failure | Rolls back; no partial rows/sites |
| Undo metadata? | Audit action `ACTION_STANDARD_TEMPLATE_GENERATED` recorded; **no undo/reset API** |

### 5.1 Proposed behaviour compatibility

> Generate complete physical four-row template, activate only sites opened for the event; remainder disabled/not open; confirm before generate.

| Aspect | Compatible with current code? |
| --- | --- |
| Create 64 physical sites | Partially — today creates 64, but all **active** |
| Activate exactly N | **No** — no N parameter; ignores limits |
| Remaining 54 not bookable | **No** — all 64 active and vendor-bookable |
| Confirmation before generate | **Partial UI** — confirm copy exists; does **not** state activation vs limit |
| Respect venue A–D only | Hard-coded in generator only; Add Row can still add row 5 afterward |

**Verdict:** Proposed behaviour is **not compatible** without generator + capacity-field + readiness + UI changes.

---

## 6. Add Row findings

### 6.1 Flow

1. UI: `openCreateRow` → `LayoutRowFormModal` (label + `vendor_category_id` + active/public flags).  
2. API: `POST /organizer/events/{id}/layout/rows`.  
3. Service: `EventLayoutService::createRow` — normalizes label, uniqueness per event label, auto `display_order = max+1`, creates **row only** (no sites).  
4. Readiness recalculated on next layout fetch / assess — empty active row → `ACTIVE_ROW_HAS_NO_ACTIVE_SITES`.

### 6.2 Why fifth row `Foodie` with zero sites was accepted

| Check | Present? |
| --- | --- |
| Maximum-row constraint | **Missing** |
| Venue-template constraint | **Missing** (no venue template) |
| Arbitrary labels as physical identity | **Yes** — label is free string ≤32; “Foodie” becomes row identity |
| Save without active sites | **Yes** |
| Duplicate row label | Rejected (`ROW_LABEL_CONFLICT`) — but `Foodie` ≠ `A`/`B`/`C`/`D` |
| Backend knows CMart has four rows | **No** (only standard generator constants know A–D) |
| Add Row intent | Implemented as **free-form physical row creation**, not category grouping. Category is a separate required FK |

Frontend also keeps Add Row enabled whenever `rows.length > 0` (header) and on empty state—**no disable when A–D already exist**.

### 6.3 Proposed correct rule (validated)

- Organizer may add only **unused physical rows from the selected venue’s template**.  
- If A–D present for CMart template, Add Row disabled + backend rejects fifth physical row.  
- Category rename/assignment must not create a physical row.  
- New row cannot persist without valid sites (or must be created in one transactional row+sites operation).  

**Do not hard-code four rows globally** — bind to venue template cardinality.

---

## 7. Edit / delete / disable / undo findings

### 7.1 Capability inventory

| Operation | Backend | Primary UI surface |
| --- | --- | --- |
| Edit row (label/category/flags) | `updateRow` | Advanced row card + focused strip `edit-row` |
| Rename display label | Same as label (identity rename) | Modal; locked after allocation history |
| Change category | `vendor_category_id` update | Modal; locked after history |
| Reorder row | `reorderRows` | Move Up/Down in advanced card |
| Delete row | `deleteEmptyRow` — only if **no sites** and not history-locked | Advanced card; disabled when sites exist |
| Disable/archive row | `archiveRow` (disables active sites, sets inactive/non-public) | Advanced card; blocked if active allocations |
| Unarchive row | `unarchiveRow` — does **not** auto-enable sites | Advanced card |
| Edit site | `updateSite` | Focused controls / site card / modal |
| Disable site | `operational_status` → `disabled` / `unavailable` | Focused + toggle; blocked if active allocations (`disable_locked`) |
| Delete site | `deleteSite` — blocked if any allocation history | Confirm dialogs |
| Undo generation | **Missing** | — |
| Reset layout | **Missing** | — |
| Restore previously disabled sites | Manual re-enable only | Set Active |

### 7.2 Recommended operation-safety matrix

| Operation | No history | Reserved (active) | Booked/confirmed (active) | Historical/released only |
| --- | --- | --- | --- | --- |
| Hard delete site | Allow | Reject | Reject | Reject (current: any history blocks) |
| Soft archive / disable site | Allow | Reject disable if active occupancy | Reject | Allow disable/structure lock already forbids structural change; disable allowed if no active occupancy |
| Close for booking (disable unused) | Allow | N/A for that site | N/A | Prefer disable over delete |
| Hard delete empty row | Allow | N/A | N/A | Allow if still empty |
| Delete row with sites | Reject (current) | Reject | Reject | Reject unless sites first safely handled |
| Archive row | Allow if no active alloc | Reject | Reject | Allow (current archive_locked = active only) |
| Rename / category change | Allow | Reject (history lock) | Reject | Reject (history lock) |
| Undo generation | Allow if no history + matches last generate | Reject | Reject | Reject |
| Reset layout | Allow with confirm if no history | Reject | Reject | Reject |

**Distinguish:** hard delete vs archive vs disable vs “close for booking” (map to `disabled`/`unavailable`) vs undo (replay/delete generation set).

### 7.3 Undo support

Audit log alone is **insufficient for safe undo** without a reverse operation that:

1. Confirms last action was `standard_parking_layout_generated` (or stores a generation batch id).  
2. Confirms **zero** `booking_day_allocations` for those sites.  
3. Deletes sites then rows in a transaction (respecting DB delete-order triggers).  

A **layout snapshot** table would be safer for reset after partial manual edits. Current code has neither undo endpoint nor snapshot.

---

## 8. Event-switch findings

### 8.1 Current behaviour (`OrganizerEventLayoutPanel.vue`)

| Topic | Behaviour |
| --- | --- |
| Reactive state | `selectedEventId`, `layout`, `loading`, `loadError`, `loadToken`, `focusedSiteId` |
| Fetch | `refreshLayout` → `getOrganizerEventLayout` |
| Old layout during load | **Remains visible** when `layout` already set — loading skeleton only when `loading && !layout` |
| Selector disabled while loading layout? | **No** — only `:disabled="loadingEvents"` |
| Request cancellation | Soft race guard via `loadToken` (stale responses ignored) |
| Rapid A→B→C | Latest token wins; older responses discarded |
| Selected site cleared | Cleared on `onEventSelected` / query watch |
| Readiness stale? | While old `layout` remains on screen during fetch, readiness badges still belong to **previous** event until replace |
| Atomic title/readiness/layout replace | Only after successful fetch assigns `layout.value = data` |
| On failure | Sets `layout = null` and shows error — **does not retain previous layout** |

### 8.2 Recommended UI state model

States: **Idle → Loading selected event → Loaded → Empty → Error**.

Recommended interaction (product): disable selector briefly; skeleton/cover previous layout; announce `Loading layout for [Event Name]…`; replace title + readiness + layout together; subtle loaded confirmation; on failure **retain previous** layout + error toast. No shake for normal loading; respect `prefers-reduced-motion`.

---

## 9. Setup Notice explanation

### 9.1 Implementation

- Backend: `EventLayoutReadinessService::assess`  
- UI: `EventLayoutReadinessPanel` titled **Setup notice**  
- Placement: **Above** parking layout in `OrganizerEventLayoutPanel` (intentional: blockers before the map)

### 9.2 Ready for Booking (`operational_ready`)

True when `operationalBlockers()` is empty. Blockers:

| Code | Trigger | User-facing (via `readinessMessage`) | Blocks booking readiness | Blocks public | Prevent earlier? |
| --- | --- | --- | --- | --- | --- |
| `NO_ACTIVE_EVENT_DAYS` | No active `event_days` | Dedicated warning copy | Yes | Yes (public requires operational) | Event-day setup UX |
| `NO_ACTIVE_LAYOUT_ROWS` | No active rows | Mapped message | Yes | Yes | Empty layout is expected until generate |
| `ACTIVE_ROW_MISSING_CATEGORY` | Active row null category | Yes | Yes | Yes | Require category on create (already required on create) |
| `ROW_CATEGORY_INACTIVE` | Category inactive/archived | Yes | Yes | Yes | Category lifecycle |
| `ACTIVE_ROW_HAS_NO_ACTIVE_SITES` | Active row with zero active sites | Yes — observed Foodie case | Yes | Yes | **Should reject empty row at write** |
| `ACTIVE_SITE_MISSING_ROW` / `UNRESOLVED_ACTIVE_SITES` | Legacy sites without row | Yes | Yes | Yes | Migration/cleanup |
| `SITE_EVENT_ROW_MISMATCH` | Site row wrong event | Yes | Yes | Yes | Integrity constraint |
| `ACTIVE_SITE_MISSING_SPACE` | Null space | Yes | Yes | Yes | Require on create |
| `ACTIVE_SITE_INVALID_LABEL` | Empty label | Yes | Yes | Yes | Validation |
| `DUPLICATE_ACTIVE_SITE_IDENTITY` | Dup label or row_label:position | Yes | Yes | Yes | Unique indexes + generator |

**Note:** Readiness does **not** compare active site count to any booking/RSVP limit.

### 9.3 Ready for Public Display (`public_ready`)

Requires operational ready **and** empty `publicBlockers`:

| Code | Trigger |
| --- | --- |
| `NO_PUBLIC_ROWS` | No active public rows |
| `INVALID_PUBLIC_ROW_ORDER` | Duplicate `display_order` among public rows |
| `PUBLIC_ROW_CATEGORY_NOT_PUBLIC` | Category not active+public |
| `PUBLIC_ROW_HAS_NO_VISIBLE_SITES` | Public row with no active sites |
| `EMPTY_PUBLIC_LAYOUT` | Zero visible active sites across public rows |

Publish endpoint enforces `public_ready` (`PUBLIC_LAYOUT_NOT_PUBLISHABLE`).

### 9.4 Positioning / naming evaluation

| Question | Verdict |
| --- | --- |
| Why above Parking Layout? | Surfaces blockers before operators act on the map; appropriate |
| Rename to Layout Readiness? | **Yes recommended** — clearer than “Setup notice” |
| Keep technical details collapsed? | **Yes** — current `<details>` pattern is good |
| Detection as substitute for prevention? | **Partially yes** — empty fifth row proves readiness is a secondary safety net; invalid writes should be rejected earlier |

Do **not** remove Setup Notice/readiness — it gates publish and communicates multi-factor readiness (days + layout + public).

---

## 10. Domain-rule gap matrix

| Proposed rule | Status |
| --- | --- |
| Active/bookable sites cannot exceed event booking limit | **Missing** (and limit field ambiguous — see `max_slots`) |
| Physical sites may exceed limit; excess disabled | **Missing** as a generation policy |
| Generated layouts cannot duplicate identities | **Partially enforced** — unique indexes + empty-only standard gen; free-form generate can still conflict and throw |
| Cannot add physical row outside venue template | **Missing** |
| Empty rows cannot be saved as ready | **Partial** — allowed to save; readiness blocks |
| Reserved/booked sites cannot be deleted | **Enforced** (any allocation history) |
| Rows containing reserved/booked sites cannot be deleted | **Enforced** (`delete_locked` if sites or history) |
| Booking-limit reduction cannot invalidate reservations silently | **Missing** (no limit↔layout coupling) |
| Undo generation only before allocation history | **Missing** (no undo) |
| Event switching clears stale selected/readiness state | **Partial** — clears focus; readiness/layout can remain stale until load completes; error clears layout entirely |
| Readiness secondary; reject invalid writes earlier | **Partial** — readiness strong; createRow weak |
| Backend enforcement mandatory | **Partial** — locks exist; capacity/template rules absent |

---

## 11. Architecture alternatives

### Option A — Minimal guardrails

Keep event-owned rows/sites; add validation and safer operations.

| Area | Changes |
| --- | --- |
| Schema | Optional `target_active_sites` (or clarified capacity field); maybe `generation_batch_id` on sites |
| Backend | Max-row rules (config or soft constants per known venue); reject empty rows; generation activation capping; undo-empty-generation; readiness rule for active-count vs limit |
| Frontend | Disable Add Row when cap reached; confirmations; loading states; surface advanced ops |
| Migration/cleanup | Soft-disable invalid empty rows; no mass delete |
| Historical events | Low impact if rules are prospective + cleanup tools |
| Multi-venue | Weak — hard-coded/config caps drift |
| Complexity | Low–medium |
| Risks | Continues free-form identity; venue knowledge remains tribal |
| Advantages | Fastest path to stop Foodie-class defects |
| Disadvantages | Does not encode physical truth |

### Option B — Venue physical template (**recommended**)

Reusable physical template; events instantiate/configure.

| Area | Changes |
| --- | --- |
| Schema | `venues`, `venue_layout_templates`, `venue_layout_rows`, `venue_layout_sites` (codes, order, aisle markers); event rows/sites reference template identities; event overrides for active/open + category |
| Backend | Instantiate template on generate; Add Row = attach unused template row only; capacity = activate subset; locks unchanged |
| Frontend | Template-aware Add Row; show physical vs open counts; clearer confirmations |
| Migration/cleanup | Map existing A–D layouts to template; quarantine free-form extras for organizer resolution |
| Historical events | Keep event-owned instances; link retrospectively where labels match |
| Multi-venue | First-class |
| Complexity | Medium–high |
| Risks | Migration of free-form labels (`Foodie`); dual-write during transition |
| Advantages | Matches physical venue; prevents invalid rows at source; supports future venues without global hard-coding |
| Disadvantages | Larger initial delivery |

### Option C — Versioned venue template

Option B + immutable template versions.

| Area | Changes |
| --- | --- |
| Schema | Template `version`, event pins `venue_layout_template_version_id` |
| Backend | Instantiation from pinned version; physical venue changes create new version |
| Frontend | Show template version on layout header |
| Migration | Backfill version 1 for current CMart 4×16 |
| Historical | Strongest isolation |
| Multi-venue | Strong |
| Complexity | Highest |
| Risks | Over-engineering if physical layout rarely changes |
| Advantages | Best historical fidelity |
| Disadvantages | Costly now for one stable CMart layout |

---

## 12. Recommended target architecture

**Choose Option B — Venue physical template**, delivered in phases that begin with Option A guardrails so invalid writes stop immediately.

### Target domain layers (validated)

1. **Venue physical template** — allowed rows, order, site codes, aisle/entrance/exit markers, max physical capacity.  
2. **Event capacity configuration** — explicit open/active count (new field or clearly renamed), per-site open/closed, categories, multi-day via `event_days`.  
3. **Safe operations** — activate/deactivate, category/label (display), add unused template rows only, delete empty accidental rows, undo pre-allocation generation, refuse destructive ops after history.

### Non-goals for v1 of Option B

- Do not treat `max_slots` as booth capacity without an explicit product rename.  
- Do not mass-delete historical reserved sites.

---

## 13. Existing invalid-data cleanup strategy

**Non-destructive; do not execute in this audit.**

### Classification

| Class | Examples | Action |
| --- | --- | --- |
| Safe to delete | Empty active row with **zero** sites and **no** audit-required retention need; duplicate empty accidental row | Organizer-confirmed delete via existing `deleteEmptyRow` |
| Safe to disable/archive | Active row with sites all unused/no history but unwanted; excess active sites above intended open count | Archive row / set sites `disabled` |
| Requires organizer resolution | Fifth row with ambiguous meaning (`Foodie`); duplicate physical labels; sites missing coordinates in practice; active count ≫ intended open count with mixed occupancy | Present report in UI; choose archive vs map-to-category-only |
| Must remain | Any site/row touched by `booking_day_allocations` (reserved/confirmed/released history) | Never hard-delete; disable/close only when locks allow |

### Suggested cleanup sequence (future)

1. Read-only inventory command: rows with zero sites; rows beyond template; active_site_count vs intended limit; sites lacking valid grid; occupancy summary.  
2. Auto-propose: empty zero-site rows → delete candidates; non-template rows → archive candidates.  
3. Organizer approval UI / artisan `--dry-run` then apply.  
4. No mass deletion; always skip history-bearing sites.

---

## 14. Required business rules (refined)

1. **Active/bookable sites cannot exceed the event’s vendor-open limit** (distinct from community `max_slots` unless product merges them deliberately). — **Missing**  
2. Physical sites may exceed the open limit; excess must be `disabled` / not open. — **Missing** as policy  
3. Generated layouts cannot duplicate row/site identities. — **Partial**  
4. An event cannot add a physical row outside its venue template. — **Missing**  
5. Empty rows cannot be persisted (or cannot be active). — **Partial** (readiness only)  
6. Reserved/booked sites cannot be hard-deleted. — **Enforced** (history)  
7. Rows containing sites/history cannot be hard-deleted. — **Enforced**  
8. Reducing the vendor-open limit cannot silently invalidate active reservations — reject or require release workflow. — **Missing**  
9. Undo generation only before any allocation history. — **Missing**  
10. Event switching must not show previous readiness as current; clear/cover stale state. — **Partial**  
11. Readiness remains secondary; invalid writes rejected earlier. — **Partial**  
12. Backend enforcement mandatory; hiding UI insufficient. — **Partial**

---

## 15. Test-case specification only

> Specification only. **Do not create or run** test files as part of this audit.  
> Historical note: prior full-suite runs observed unrelated failures in areas such as `BookingDayAllocationReservationTest`, `EventDayAutomationTest`, `EventLayoutAndDaysTest`, `Phase34ASchemaIntegrityTest` — treat as historical context only; do not execute or fix here.

### Capacity and generation

- Limit 10 + Standard generation → 64 physical sites, exactly 10 active (requires new semantics).  
- Limit 64 + Standard generation → 64 active.  
- Limit above physical capacity → rejected.  
- Limit zero/null behaviour documented and enforced.  
- Repeated Standard generation → no duplicates (`LAYOUT_ALREADY_EXISTS`).  
- Standard generation with existing reservation → protected/rejected.  
- Reduce limit below reserved-site count → rejected.

### Physical rows

- Venue template A–D → fifth row rejected.  
- Unused template row can be added.  
- Add Row hidden/disabled when all template rows used.  
- Empty row rejected.  
- Duplicate physical row rejected.  
- Category rename does not create physical row.  
- Different venue with six rows supports six rows.

### Edit/delete/undo

- Delete empty accidental row → allowed.  
- Delete row with reserved site → rejected.  
- Delete booked site → rejected.  
- Disable unused site → allowed.  
- Undo immediately after generation → allowed.  
- Undo after allocation history → rejected.  
- Reset without history → allowed with confirmation.  
- Reset with history → rejected.

### Event switching

- Loading indicator appears.  
- Old selected-site state clears.  
- Readiness and layout belong to same event.  
- Rapid A→B→C displays only C.  
- Failed request retains previous layout.  
- Reduced-motion preference respected.

### Readiness

- Empty row blocks booking/public display.  
- Active-site count exceeding limit blocks readiness (new rule).  
- Missing event days blocks readiness.  
- Fully valid layout becomes ready.  
- Technical blocker codes map to understandable messages.

---

## 16. Exact files affected

### Must change (for Option B / interim A)

| Area | Files |
| --- | --- |
| Models/migrations | New venue/template migrations; possibly `CarbootEvent` capacity field; `EventLayoutRow`/`EventSite` FKs to template identities |
| Layout generator | `backend/app/Services/StandardEventLayoutGenerator.php` |
| Readiness | `backend/app/Services/EventLayoutReadinessService.php` |
| Row/site domain | `backend/app/Services/EventLayoutService.php`, `EventLayoutRowSiteGenerator.php` |
| Controllers/requests | `OrganizerEventLayoutController.php`, `OrganizerEventLayoutRowController.php`, `OrganizerEventLayoutSiteController.php` (+ Form Requests if introduced) |
| Routes | `backend/routes/api.php` (undo/reset endpoints if added) |
| Frontend Layout Management | `OrganizerEventLayoutPanel.vue`, `LayoutRowFormModal.vue`, `StandardParkingLayoutModal.vue`, `EventLayoutReadinessPanel.vue`, `organizerEventLayoutMessages.js`, `organizerEventLayoutApi.js` |
| Event selector UX | same panel (loading/race/retain-on-error) |
| Confirmations | Standard generate + reset/undo modals |
| Locks (extend) | `EventLayoutLockService.php` |
| Audit | `EventLayoutAuditLog.php` / logger (undo/reset actions) |

### May change

| Area | Files |
| --- | --- |
| Visual aisle heuristics | `frontend/src/utils/visualParkingLayout.js`, `VisualParkingLayout.vue` |
| Public/vendor projections | `PublicEventLayoutService.php`, `VendorEventSiteAvailabilityService.php` |
| Event forms | `StaffEventsPanel.vue`, `CarbootEventController.php` (capacity wording) |
| Focused/advanced controls | `OrganizerFocusedSiteControls.vue`, `EventLayoutRowCard.vue` |
| Cleanup command | New `app/Console/Commands/...` (future) |
| Future tests | New Feature/unit tests under `backend/tests/...` and frontend unit tests (spec only now) |
| Resources | Inline presenters in controllers today; extract if DTOs/resources added |

### Should not change (for this domain fix)

| Area | Rationale |
| --- | --- |
| Booking payment / withdrawal core | Orthogonal |
| Event Report aggregators / privacy filters | Already correctly exclude misusing `max_slots` as booth capacity |
| Community registration locking mechanics | Keep RSVP semantics unless product merges capacities deliberately |
| Unrelated failing historical suites | Out of scope |

---

## 17. Recommended implementation phases

1. **Clarify capacity semantics (product decision gate)** — RSVP `max_slots` vs vendor-open limit; name fields in UI.  
2. **Interim guardrails (Option A)** — reject empty rows; optional max active rows config; standard generate activation capping against vendor-open limit; readiness check for active_count > limit; event-switch loading UX.  
3. **Safe undo empty standard generation** — transactional delete of generation batch when no allocation history.  
4. **Venue template schema + CMart 4×16 seed (Option B)** — instantiate on generate; constrain Add Row.  
5. **Non-destructive cleanup tooling** — inventory + organizer-approved archive/delete of empty invalid rows.  
6. **Hardening** — tests from §15; multi-venue second template proof; consider Option C only if physical layout versions become real.

---

## 18. Open questions and blockers

### Critical blockers

1. **Capacity field identity:** Is the organizer’s “limit 10” meant to be `max_slots` (community RSVP), a new vendor-open limit, or simply “number of sites to activate”? **Cannot implement correct generation without this decision.**  
2. **Existing reserved sites** on layouts that need structural correction — any reset/undo/delete path must remain locked.  
3. **Invalid free-form rows already in databases** (`Foodie`, empty rows) — need cleanup policy before enforcing template constraints on historical events.  
4. **Whether category labels may ever equal row codes** — today conflation is easy in UI (“Row name”).

### Open product questions

- Should disabled physical sites appear on the public map as “not open,” or be hidden?  
- Is `unavailable` vs `disabled` the preferred “Not Open for This Event” status?  
- Should Add Row disappear entirely once template rows are exhausted, or show “all physical rows in use”?  
- Multi-venue timeline — drives Option B vs prolonged Option A.

### Non-blockers

- Setup Notice rename to Layout Readiness.  
- Moving advanced row tools into clearer primary controls.  
- Reduced-motion polish on event switch.

---

## Audit execution confirmation

- No backend/frontend tests run  
- No PHPUnit/Pest/`php artisan test`  
- No migrations, seeders, fixtures, or demo users  
- No layout generation or API mutations  
- No database-modifying commands  
- No lint/build/package install  
- No commit/push/PR  
- Sole artifact written: `report/layout-management-domain-audit.md`
