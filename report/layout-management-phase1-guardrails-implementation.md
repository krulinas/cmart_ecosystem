# Layout Management Phase 1 — Immediate Guardrails and UX

**Status:** Implemented (code + additive migration created; migration **not** executed)  
**Date:** 2026-08-05  
**Basis:** `report/layout-management-domain-audit.md` + confirmed product decisions  

---

## Executive summary

Phase 1 introduces a dedicated **`vendor_site_open_limit`** (UI: **Vendor sites to open**), keeps `max_slots` as community RSVP capacity, and centralizes the interim CMart physical layout as **A–D × 16 = 64**.

Standard generation now creates **all 64 physical sites as NOT OPEN (`disabled`)**, then requires the organizer to **manually select exactly N** sites to open. Add Row is constrained to unused A–D identities and creates 16 disabled sites transactionally. Layout Readiness (renamed from Setup notice) reports new capacity and template blockers. Event switching shows a loading overlay and retains the previous layout on failure.

**Verification status: Not run, per user instruction.**

---

## Exact files changed

### Created

| File |
| --- |
| `backend/database/migrations/2026_08_05_000001_add_vendor_site_open_limit_to_carboot_events_table.php` |
| `backend/app/Support/CmartCarbootPhysicalLayout.php` |
| `frontend/src/config/cmartCarbootPhysicalLayout.js` |
| `report/layout-management-phase1-guardrails-implementation.md` |

### Modified (backend)

| File |
| --- |
| `backend/app/Models/CarbootEvent.php` |
| `backend/app/Models/EventLayoutAuditLog.php` |
| `backend/app/Services/EventPresenter.php` |
| `backend/app/Services/StandardEventLayoutGenerator.php` |
| `backend/app/Services/EventLayoutService.php` |
| `backend/app/Services/EventLayoutReadinessService.php` |
| `backend/app/Http/Controllers/Api/CarbootEventController.php` |
| `backend/app/Http/Controllers/Api/OrganizerEventLayoutController.php` |
| `backend/app/Http/Controllers/Api/OrganizerEventLayoutRowController.php` |
| `backend/routes/api.php` |

### Modified (frontend)

| File |
| --- |
| `frontend/src/views/dashboards/organizer/OrganizerEventLayoutPanel.vue` |
| `frontend/src/views/dashboards/staff/StaffEventsPanel.vue` |
| `frontend/src/components/organizer/layout/LayoutRowFormModal.vue` |
| `frontend/src/components/organizer/layout/StandardParkingLayoutModal.vue` |
| `frontend/src/components/organizer/layout/OrganizerFocusedSiteControls.vue` |
| `frontend/src/components/organizer/layout/EventLayoutRowCard.vue` |
| `frontend/src/services/organizerEventLayoutApi.js` |
| `frontend/src/utils/organizerEventLayoutMessages.js` |
| `frontend/src/utils/visualParkingLayout.js` |
| `frontend/src/utils/visualParkingLayoutCopy.js` |

---

## Migration filename and field semantics

**File:** `backend/database/migrations/2026_08_05_000001_add_vendor_site_open_limit_to_carboot_events_table.php`

| Field | Type | Meaning |
| --- | --- | --- |
| `vendor_site_open_limit` | `unsignedInteger` nullable | Number of vendor parking sites that may be **opened** for booking on this event (1–64 for CMart) |
| `max_slots` | unchanged | Community RSVP / registration capacity only |

**Confirmation: migration was created but not executed.**

---

## Standard-generation and manual-selection workflow

1. Organizer sets **Vendor sites to open** on the event (Events form / API).  
2. Generate Standard Layout requires a configured limit; otherwise backend rejects with `VENDOR_SITE_OPEN_LIMIT_NOT_SET`.  
3. Generator creates rows A–D and **64 sites** with `operational_status = disabled`.  
4. UI enters **Select Open Sites**; organizer toggles sites until `Selected X of N`.  
5. Confirm calls `POST /organizer/events/{id}/layout/open-sites` with exact `site_ids` count = limit.  
6. Backend transaction opens selected sites and closes others (respecting disable locks). Incomplete selection leaves readiness **Not Ready for Booking** (`ACTIVE_SITE_COUNT_BELOW_VENDOR_LIMIT`).

Authoritative physical definition: `App\Support\CmartCarbootPhysicalLayout` (mirrored by `frontend/src/config/cmartCarbootPhysicalLayout.js`).

---

## Organizer / public visibility behaviour

| Audience | Behaviour |
| --- | --- |
| Organizer | Sees all physical sites; disabled/closed styled muted and labelled **NOT OPEN** |
| Public / vendor | Existing projections continue to expose **active/open sites only**; disabled sites are not bookable inventory |

Reservation and booking locks are unchanged.

---

## Add Row restrictions

- Physical identity must be unused **A–D** (backend + unused-row select UI).  
- When A–D are all present: **Add Row stays visible but disabled** with  
  `All physical rows for this venue are already in use.`  
- Create requires `space_id` and creates **16 disabled sites** in the same transaction.  
- Free-text identities rejected (`ROW_OUTSIDE_VENUE_TEMPLATE`).  
- Legacy rows such as `Foodie` are **not deleted**; flagged `outside_venue_template` + readiness `ROW_OUTSIDE_VENUE_TEMPLATE`.

---

## Readiness rules

Renamed UI title: **Layout Readiness**.

New / strengthened blockers:

| Code | Meaning |
| --- | --- |
| `VENDOR_SITE_OPEN_LIMIT_NOT_SET` | Limit null on legacy/new events |
| `ACTIVE_SITE_COUNT_BELOW_VENDOR_LIMIT` | Open count &lt; limit |
| `ACTIVE_SITE_COUNT_EXCEEDS_VENDOR_LIMIT` | Open count &gt; limit |
| `ROW_OUTSIDE_VENUE_TEMPLATE` | Non A–D row present |
| `ACTIVE_ROW_HAS_NO_ACTIVE_SITES` | Active row with **zero physical sites** |

Existing day/category/duplicate/public blockers preserved. Backend write guards accompany readiness (not readiness-only).

---

## Event-switch behaviour

- Selector disabled while loading.  
- Focused site cleared immediately.  
- Overlay: `Loading layout for [Event Name]…` with reduced-motion-friendly pulse (no shake).  
- Readiness hidden during switch so prior badges are not attributed to the new event.  
- Title / readiness / layout / counts update together on success.  
- Failure retains previous layout, shows error, resets selector to last successful event.  
- `loadToken` race protection preserved; `aria-live` status region added.

---

## Backward-compatibility decisions

- No automatic rewrite of existing layouts or site statuses.  
- Null `vendor_site_open_limit` → readiness warning only.  
- Invalid legacy rows reported, not auto-deleted.  
- Allocation/history locks remain authoritative for disable/delete/archive.  
- Reducing limit below protected occupied sites rejected (`VENDOR_SITE_OPEN_LIMIT_BELOW_PROTECTED`).

---

## Documented acceptance scenarios

1. Limit 10 → organizer sees 64 sites and must manually select exactly 10.  
2. Public/vendor expose only those 10 active sites.  
3. Selecting 9 of 10 cannot confirm (UI + backend).  
4. Selecting 11 of 10 cannot confirm (UI prevents; backend rejects mismatch).  
5. Limit above 64 rejected on event validation.  
6. Backend rejects activating sites beyond the limit.  
7. Reducing limit below protected reserved/booked sites rejected.  
8. Fifth physical row rejected.  
9. Add Row disabled with explanation when A–D exist.  
10. Unused valid row creates 16 disabled sites.  
11. Event switching shows loading feedback without stale readiness.  
12. Failed event load retains previous valid layout.  
13. Legacy invalid row reported, not auto-deleted.  
14. Existing events not silently rewritten.

---

## Deferred Phase 2 work

- Full DB-backed venue physical-template architecture / multi-venue  
- Undo generation / full reset  
- Historical invalid-row cleanup tooling  
- Drag-and-drop floor-plan editing  
- Event report changes  
- Booking/payment workflow changes  
- Automated tests  

---

## Manual review checklist

1. Run migration locally when ready:  
   `php artisan migrate`  
   (not executed in this session).  
2. Create/edit an event: set **Vendor sites to open** = 10; confirm **Max slots** still independent.  
3. Layout Management → generate standard → confirm 64 NOT OPEN sites → select exactly 10 → confirm.  
4. Verify vendor/public map shows only 10 open sites.  
5. Attempt Add Row with A–D present → button disabled + explanation.  
6. Attempt Add Row with missing row (e.g. only A–C) → creates row + 16 NOT OPEN sites.  
7. Switch events quickly → overlay + no stale readiness; fail a load (optional) → previous layout retained.  
8. Confirm Layout Readiness title and new blocker messages.  
9. Confirm legacy `Foodie`-style rows show outside-venue badge and readiness blocker without auto-delete.

---

## Verification status

`Not run, per user instruction.`
