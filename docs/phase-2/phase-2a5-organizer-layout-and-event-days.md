# Phase 2A.5 — Organizer Layout Generation and Organizer-Defined Event Days

**Status:** Completed  
**Date:** 2026-07-14  
**Scope:** Backend only — no cinema UI, allocations, or booking reservation.

---

## 1. Summary

Phase 2A.5 adds two Organizer setup capabilities on top of Phase 2A.4 `event_sites`:

1. **Controlled bulk parking-layout generation** for an event.
2. **Explicit Organizer-defined operational `event_days`**, including `day_generation_mode` on `carboot_events`.

---

## 2. Schema

### `carboot_events.day_generation_mode`

| Value | Behaviour |
|-------|-----------|
| `calendar_days` (default) | One `event_day` per MYT calendar date in `[starts_at, ends_at]` |
| `single_session` | Exactly one `event_day` (supports overnight windows) |

### `event_days`

| Column | Notes |
|--------|-------|
| `carboot_event_id` | FK cascade |
| `operational_date` | Unique per event |
| `starts_at` / `ends_at` | Day window (MYT wall clock) |
| `operational_status` | `active` \| `cancelled` \| `disabled` |
| `display_order` | Stable ordering |

---

## 3. Services

| Service | Responsibility |
|---------|----------------|
| `EventSiteLayoutGenerator` | Build contiguous labelled bays from row definitions; optional replace |
| `EventDayGenerator` | Materialise days from mode + event window; optional replace |

Replace paths delete existing rows for the event. **Phase 2A.7+ must block replace when active allocations exist.**

---

## 4. API endpoints (Organizer / super_admin)

### Layout generation

```http
POST /api/organizer/events/{carboot_event}/sites/generate
```

```json
{
  "space_id": 1,
  "replace_existing": false,
  "rows": [
    { "row_label": "A", "count": 10, "start_position": 1, "grid_row": 1 },
    { "row_label": "B", "count": 10, "start_position": 1, "grid_row": 2 },
    { "row_label": "VIP", "label_prefix": "VIP-", "count": 2, "start_position": 1, "grid_row": 3 }
  ]
}
```

Produces labels such as `A01`…`A10`, `B01`…`B10`, `VIP-01`, `VIP-02`.

Existing single-site CRUD from Phase 2A.4 remains available.

### Event days

```http
GET    /api/organizer/events/{carboot_event}/days
POST   /api/organizer/events/{carboot_event}/days
POST   /api/organizer/events/{carboot_event}/days/generate
GET    /api/organizer/event-days/{event_day}
PUT/PATCH /api/organizer/event-days/{event_day}
DELETE /api/organizer/event-days/{event_day}
```

Generate payload:

```json
{
  "day_generation_mode": "calendar_days",
  "replace_existing": false
}
```

`day_generation_mode` may also be set on event create/update via `/api/carboot-events`.

---

## 5. Governance

Routes sit inside `role:organizer,super_admin` (carboot operational roles).  
`cmart_management` and `community` receive **403**.

---

## 6. Validation performed

```bash
php artisan migrate
php artisan test --filter=EventLayoutAndDaysTest
php artisan test --filter=EventSiteFoundationTest
```

---

## 7. Explicitly out of scope

- Booking-day allocations
- Vendor site reservation / payment confirmation
- Cinema-style frontend
- Withdrawal
- Per-day check-in

Next: **Phase 2A.6** may be considered already covered by this phase’s event-day work; proceed next to **Phase 2A.7 — booking_day_allocations** unless the project plan re-labels numbering.
