# Phase 3.5 — Organizer Layout Backend, Readiness and Locking

| Field | Value |
|-------|-------|
| **Status** | Complete |
| **Date** | 2026-07-16 |
| **Depends on** | Phase 3.4 schema, Phase 3.4A integrity corrections |
| **Next** | Phase 3.6 — Organizer Layout Management UI |

---

## 1. Objective

Expose a safe Organizer/Super Admin backend for category-based Carboot layout management: rows, sites, row-aware generation, readiness, structural locks, and append-only layout audits — without vendor eligibility, public layout endpoints, or frontend.

---

## 2. Authorization model

| Actor | Access |
|-------|--------|
| Organizer | Full layout API |
| Super Admin | Same internal endpoints |
| CMart Management | Denied (403) |
| Community | Denied (403) |
| Guest | Unauthorized (401) |

Routes live under `auth:sanctum` + `role:organizer,super_admin` + `/api/organizer` (same carboot-ops group as Phase 2 event sites/days).

---

## 3. Organizer API inventory

| Method | Route | Purpose |
|--------|-------|---------|
| GET | `/api/organizer/vendor-categories` | Canonical category lookup + usage |
| GET | `/api/organizer/events/{event}/layout` | Full layout projection |
| GET | `/api/organizer/events/{event}/layout/readiness` | Operational + public readiness |
| POST | `/api/organizer/events/{event}/layout/rows` | Create row |
| PATCH | `/api/organizer/events/{event}/layout/rows/reorder` | Reorder rows |
| PATCH | `/api/organizer/events/{event}/layout/rows/{row}` | Update row |
| DELETE | `/api/organizer/events/{event}/layout/rows/{row}` | Delete empty row |
| PATCH | `/api/organizer/events/{event}/layout/rows/{row}/archive` | Archive row |
| PATCH | `/api/organizer/events/{event}/layout/rows/{row}/unarchive` | Unarchive row |
| POST | `/api/organizer/events/{event}/layout/rows/{row}/sites` | Create site in row |
| POST | `/api/organizer/events/{event}/layout/rows/{row}/sites/generate` | Generate sites for one row |
| PATCH | `/api/organizer/events/{event}/layout/rows/{row}/sites/reorder` | Reorder sites |
| PATCH | `/api/organizer/events/{event}/layout/sites/{site}` | Update site |
| DELETE | `/api/organizer/events/{event}/layout/sites/{site}` | Delete site |

---

## 4. Category lookup contract

- Returns all canonical categories in `display_order`.
- Includes inactive/archived for Organizer visibility.
- `selectable_for_new_row` is true only when active and not archived.
- Usage summary: layout_rows, active_layout_rows, bookings, active_allocations.
- No mutation endpoints in this phase.

---

## 5. Layout response contract

Includes event summary, readiness, event-level locks, rows with category projection, nested sites with space + occupancy summary (`available` / `reserved` / `confirmed` / `released-history`), per-row and per-site lock flags, and unresolved sites (`event_layout_row_id` null).

Excludes booking PII, payment proofs, migration audits, and public endpoint semantics.

---

## 6. Row lifecycle

| Operation | Rules |
|-----------|-------|
| Create | Active category required; unique label; server slug; actor stamps |
| Update | Label/category blocked after any allocation history; description/order/public allowed; slug stable |
| Label rename | Mirrors to all child `event_sites.row_label` atomically |
| Reorder | Allowed after history |
| Delete | Empty only → else `409 ROW_NOT_EMPTY` |
| Archive | Blocked by active reserved/confirmed; allowed with released history; disables active sites |
| Unarchive | Requires assignable category; clears archive; `is_public=false`; does **not** reactivate sites |

---

## 7. Site lifecycle

| Operation | Rules |
|-----------|-------|
| Create | Active non-archived row + active category; mirrors `row_label`; no site category FK |
| Generate | Row-aware padded labels; atomic; no replace/delete of existing |
| Update | Structural fields locked after any history; move updates row + `row_label` |
| Disable | Blocked by active allocations; allowed with released history |
| Reorder | Allowed after history |
| Delete | Blocked by any allocation history |

---

## 8. Row-aware generation

Class: `App\Services\EventLayoutRowSiteGenerator`

- Separate from Phase 2 `EventSiteLayoutGenerator` (full-event bulk, no layout-row link).
- Max count: 100.
- Labels: `{PREFIX}{padded number}` (e.g. A01…A10).
- Conflicts → `SITE_LABEL_CONFLICT` / `SITE_POSITION_CONFLICT` with no partial writes.

---

## 9. Operational readiness

Service: `EventLayoutReadinessService`

Blockers: `NO_ACTIVE_EVENT_DAYS`, `NO_ACTIVE_LAYOUT_ROWS`, `ACTIVE_ROW_MISSING_CATEGORY`, `ROW_CATEGORY_INACTIVE`, `ACTIVE_ROW_HAS_NO_ACTIVE_SITES`, `ACTIVE_SITE_MISSING_ROW`, `SITE_EVENT_ROW_MISMATCH`, `ACTIVE_SITE_MISSING_SPACE`, `ACTIVE_SITE_INVALID_LABEL`, `UNRESOLVED_ACTIVE_SITES`, `DUPLICATE_ACTIVE_SITE_IDENTITY`.

---

## 10. Public-layout readiness

Requires operational readiness plus: `NO_PUBLIC_ROWS`, `PUBLIC_ROW_CATEGORY_NOT_PUBLIC`, `PUBLIC_ROW_HAS_NO_VISIBLE_SITES`, `EMPTY_PUBLIC_LAYOUT`, `INVALID_PUBLIC_ROW_ORDER`.

No public layout HTTP endpoint in Phase 3.5.

---

## 11. Lock matrix

Implemented by `EventLayoutLockService` per Phase 3.5 ADR matrices (rename/category/delete/archive for rows; structure/disable/delete for sites). Reorder and description/public remain allowed after history.

---

## 12. Audit model

Table: `event_layout_audit_logs` (migration `2026_07_16_000012`)

`booking_audit_logs` requires `booking_id` and cannot store layout actions.

Actions: `layout_row_created|updated|reordered|archived|unarchived|deleted`, `event_site_created|updated|reordered|deleted`, `event_sites_generated`.

Safe before/after snapshots only; no passwords, tokens, payment proofs, or PII.

---

## 13. Concurrency behaviour

Structural mutations wrap `DB::transaction` with `lockForUpdate` on event → row → sites (ordered by id). Unique conflicts map to stable `409` codes.

---

## 14. Error contract

- `422` validation / inactive category (`CATEGORY_INACTIVE`, `INVALID_*`)
- `409` domain conflicts (`ROW_LABEL_LOCKED`, `ROW_CATEGORY_LOCKED`, `ROW_NOT_EMPTY`, `ACTIVE_ALLOCATIONS_PRESENT`, `SITE_STRUCTURE_LOCKED`, `SITE_HAS_ALLOCATION_HISTORY`, `SITE_LABEL_CONFLICT`, `SITE_POSITION_CONFLICT`, `ROW_LABEL_CONFLICT`, …)
- `404` cross-event row/site
- `401` / `403` authz

---

## 15. Phase 2 compatibility

Existing Phase 2 site/day generators, vendor availability, booking create/reserve/lifecycle/withdrawal remain unchanged. New sites set `event_layout_row_id` and mirror `row_label`. Transitional FKs stay nullable.

---

## 16. Test evidence

Feature suites: `OrganizerEventLayout*`, `OrganizerVendorCategoryLookupTest` (32 passed). Compatibility suites for sites/days/availability/booking/allocation/withdrawal/governance remain green. Full suite re-verified in execution report.

---

## 17. Persistent-data validation

`cmart_db` never receives Phase 3.4–3.5 migrations. Baseline counts unchanged.

---

## 18. Known limitations

- Organizer UI deferred to Phase 3.6
- Vendor eligibility / public layout deferred
- Existing Phase 2 `/sites/generate` does not create `EventLayoutRow` links
- 22 skipped seed-demo tests remain Phase 3.11 debt
- Phase 3.4A audit `down()` still collapses multi-observation history — not executed in this phase

---

## 19. Phase 3.6 entry criteria

| Requirement | Status |
|-------------|--------|
| Organizer layout API | Met |
| Category lookup | Met |
| Row/site lifecycle + locks | Met |
| Readiness | Met |
| Row-aware generation | Met |
| Audit | Met |
| Authz | Met |
| Phase 2 compatibility | Met |
| Dev DB untouched | Met |

Phase 3.6 may implement the Organizer Layout Management UI against these endpoints.
