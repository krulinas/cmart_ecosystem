# Phase 3.6 — Organizer Layout Management UI

| Field | Value |
|-------|-------|
| **Status** | Complete |
| **Date** | 2026-07-16 |
| **Depends on** | Phase 3.5 Organizer layout backend |
| **Next** | Phase 3.7 — Vendor Category Eligibility and Booking Write Migration |

---

## 1. Objective

Deliver an Organizer/Super Admin workspace for managing category-based Carboot layout rows and physical sites on top of Phase 3.5 APIs — without vendor eligibility, public layout, or drag-and-drop.

---

## 2. Route and navigation

| Item | Value |
|------|-------|
| Route | `/admin#layout` (hash section inside management workspace) |
| Query | `?eventId=` optional deep link |
| Allowed | Organizer, Super Admin (`CARBOOT_OPERATIONS`) |
| Denied (nav hidden) | CMart Management, Community |
| Entry | Nav item **Urus Susun Atur**; Events list button **Urus Susun Atur** |

Backend authorization remains authoritative.

---

## 3. Page structure

`OrganizerEventLayoutPanel.vue`:

1. Event selector + header (name, status, counts, refresh)
2. Readiness panel (operational + public)
3. Unresolved sites warning
4. Empty / error / loading states
5. Row cards with TGV-style site grids
6. Modals for row form, site form, site generation

---

## 4. Readiness presentation

Operational and public badges in BM. Blockers mapped via `READINESS_BLOCKER_MESSAGES` (primary BM text; backend code shown as secondary detail).

---

## 5. Row-management UX

Create / edit (lock-aware) / Naikkan–Turunkan reorder / delete empty / archive / unarchive — all via Phase 3.5 endpoints. Confirmations in BM.

---

## 6. Site-management UX

Create, generate with live preview, edit/move (structure lock), reverse-order reorder action, disable/enable, delete — lock explanations in BM.

---

## 7. TGV-style site grid

Responsive card grid per row; occupancy and status badges; lock badge; colour + text (not colour alone).

---

## 8. Lock-state presentation

Uses backend `locks` projections. Disabled controls carry `title` / helper text. No independent lock inference that replaces backend authority.

---

## 9. Error-code mapping

Centralised in `organizerEventLayoutMessages.js` (`LAYOUT_ERROR_MESSAGES`). Form data retained on 409 (modals stay open with `formError`).

---

## 10. Important Malay copy

Centralised `LAYOUT_COPY` — page title, actions, confirmations, readiness, locks, success toasts, conflicts. **No full i18n framework added.**

---

## 11. API integration

`frontend/src/services/organizerEventLayoutApi.js` — all Phase 3.5 layout methods + `/spaces` + `/carboot-events`.

---

## 12. State management

Page-level refs in `OrganizerEventLayoutPanel` (no large Pinia store). Load token prevents stale overwrite; `mutating` prevents double-submit.

---

## 13. Accessibility

Dialog roles, labelled headings, Escape-to-close, aria-labels on icon/action buttons, text+colour states, keyboard-usable controls.

---

## 14. Responsive behaviour

Single-column on mobile; wrapping action groups; site grid 2→5 columns by breakpoint; modals bottom-sheet on small screens.

---

## 15. Test strategy

Node `node:test` unit tests for helpers, messages, and SFC wiring/API path assertions.

---

## 16. Browser E2E

Blocked unless Phase 3 schema is available on the E2E database. Default `cmart_db` does not contain Phase 3 tables; migrating it is out of scope. Unit/integration + production build substitute validation.

---

## 17. Persistent-data validation

No E2E fixture mutations performed against `cmart_db` in this phase.

---

## 18. Known limitations

- Site reorder uses reverse-order confirmation (no drag-and-drop; no per-tile left/right yet)
- Unresolved sites are visible but not auto-assigned
- E2E deferred pending isolated DB with Phase 3 migrations
- Vendor eligibility / public layout deferred to later phases

---

## 19. Phase 3.7 entry criteria

| Requirement | Status |
|-------------|--------|
| Organizer layout UI usable | Met |
| Category source from API | Met |
| Readiness + locks visible | Met |
| Row/site lifecycle wired | Met |
| Frontend unit tests | Met |
| Production build | See execution report |
| E2E | Honestly blocked if DB lacks Phase 3 |

Phase 3.7 may implement vendor category eligibility and booking write migration — not public layout.
