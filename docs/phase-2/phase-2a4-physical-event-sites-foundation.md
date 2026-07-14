# Phase 2A.4 — Physical Event Sites Foundation (completed)

**Status:** Completed  
**Date:** 2026-07-14

## Delivered

- `event_sites` table and `EventSite` model
- Organizer CRUD under `/api/organizer/...`
- Unique constraints: `(event, label)`, `(event, row_label, position_number)`
- `spaces` retained as booth-type catalogue via `space_id`
- Governance: community and `cmart_management` cannot manage sites

## See also

- ADR: `docs/phase-2/phase-2a-architecture-decision-record.md` (ADR-002, ADR-003)
- Tests: `backend/tests/Feature/EventSiteFoundationTest.php`
