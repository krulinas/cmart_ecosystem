# Phase 4.0 — Item Reservation Rules & Architecture Audit

**Document date:** 2026-07-18  
**Repository:** CMart / Carboot@CMart (`cmart_ecosystem`)  
**Status:** Accepted architecture freeze for Phase 4 MVP planning  
**Scope of this task:** Documentation only. No migrations, schema changes, production code, routes, UI, tests, or database records were modified.

### Phase 4.1 architecture corrections — 2026-07-18

The following corrections supersede the affected Phase 4.0 statements throughout this document:

1. A zero-fee reservation uses `charge_status=not_required`, not `waived`. A waiver always records an Organizer actor, reason, and timestamp against an otherwise required fee.
2. A reservation keeps a numeric internal primary key but receives an opaque, non-sequential public reference such as `RSV-7K4M9Q2P`. Phase 4.2 owns the exact generator.
3. Reservation-history-aware item deletion cannot be enforced until `item_reservations` exists. Phase 4.1 preserves and tests the current owner-authorized delete and image cleanup path; Phase 4.2 adds the history guard in `VendorItemController::destroy()` before `$vendor_item->delete()`.

---

## 1. Executive summary

Phase 3.11 closed product work for category/layout and left “item reservation and extra charge” explicitly planned for Phase 4 (`docs/phase-3/phase-3-debt-register.md`, `docs/phase-3/phase-3-stakeholder-traceability-matrix.md`).

Repository inspection shows:

| Domain | Status |
|--------|--------|
| Vendor item catalogue (`vendor_items`, reuse marketplace) | **Exists and reusable** |
| Site-day “reservation” (`booking_day_allocations`) | **Exists — must not be reused for items** |
| Booking invoices (`invoices`) | **Booking-specific — must not be reused for item charges** |
| Item reservation / hold / quantity / charge | **Missing — required for Phase 4** |
| Scheduler-driven expiry | **Missing — defer from early subphases** |

Phase 4 MVP is a **reservation-recording and manual Organizer-owned service-fee tracking** feature. It is not e-commerce, not a payment gateway, and not a mutation of vendor site booking.

**Verdict of this audit:** architecture is freezable with no unresolved blockers. Implementation begins at Phase 4.1.

---

## 2. Repository areas inspected

### Backend

- `backend/app/Models` (all 25 models)
- `backend/app/Http/Controllers/Api` (marketplace, vendor items, bookings, organizer)
- `backend/app/Services` (eligibility, presenters, allocation reservation, audit loggers)
- `backend/app/Support` (roles, capabilities, community vendor intent, test DB guards)
- `backend/app/Exceptions/DomainConflictException.php`
- `backend/app/Console` (Kernel schedule empty; E2E fixture and cleanup commands)
- `backend/database/migrations` (vendor items, invoices, booking_day_allocations, audits)
- `backend/database/factories`, `backend/database/seeders`
- `backend/routes/api.php`
- `backend/tests/Concerns/CleansUpTestFixtures.php`
- `backend/app/Policies` — **empty** (no Policy classes; ownership checked in controllers)

### Frontend

- `frontend/src/views/public/ReuseMarketplace.vue`, `CommunityPortal.vue`, `PublicLanding.vue`
- `frontend/src/components/VendorItem*.vue`, `MarketplaceItem*.vue`, `ReuseItemImageGallery.vue`
- `frontend/src/components/VendorPaymentModal.vue`, organizer reconciliation / bookings panels
- `frontend/src/stores/auth.js`, `frontend/src/composables/useManagementAccess.js`
- `frontend/src/router/router.js`, `frontend/src/services/api.js`
- `frontend/tests/e2e` helpers and Phase 3 fixture pattern

### Documentation

- `docs/phase-2/` (especially ADR and allocation reservation engine)
- `docs/phase-3/` (closure, debt register, traceability, migration runbook)
- `docs/governance/`
- No prior `docs/phase-4/` documents; no docs index file exists

---

## 3. Existing relevant implementation inventory

### 3.1 Vendor items (real catalogue)

| Path | Role |
|------|------|
| `backend/app/Models/VendorItem.php` | Item aggregate owned by `user_id` |
| `backend/app/Models/ReuseItemImage.php` | Gallery rows (max 5 pattern) |
| `backend/database/migrations/2026_06_15_000002_create_vendor_items_table.php` | Schema: `active`/`inactive`, `DECIMAL(10,2)` price |
| `backend/database/migrations/2026_06_16_000001_create_reuse_item_images_table.php` | Image gallery |
| `backend/app/Http/Controllers/Api/VendorItemController.php` | Community owner CRUD |
| `backend/app/Http/Controllers/Api/MarketplaceController.php` | Public browse/show |
| `backend/app/Services/MarketplaceEligibility.php` | Public visibility = active + Approved upcoming booking |
| `backend/app/Services/MarketplaceItemPresenter.php` | Public-safe item + vendor summary |
| `backend/app/Services/VendorItemPresenter.php` | Owner-facing item payload |
| `frontend/src/components/VendorItemManager.vue` | Vendor registry UI |
| `frontend/src/components/VendorItemFormModal.vue` | Create/edit + images |
| `frontend/src/components/VendorItemDetailsModal.vue` | Vendor details |
| `frontend/src/views/public/ReuseMarketplace.vue` | Public catalogue (preview-only copy) |
| `frontend/src/components/public/MarketplaceItemCard.vue` | Public card |
| `frontend/src/components/MarketplaceItemDetailsModal.vue` | Public details (close-only CTA today) |
| `frontend/src/utils/vendorCatalog.js` | Conditions, pricing, price formatting |
| `frontend/src/utils/imageUrl.js` | Storage URL / gallery helpers |

`vendor_items` fields (migration evidence): `user_id`, `name`, `category`, `condition`, `pricing_type`, `price`, `description`, `image_path`, `status` (`active`|`inactive`). Later Phase 3 adds nullable `vendor_category_id` (writes still string-authoritative — debt).

### 3.2 Site-day reservation (must not be overloaded)

| Path | Role |
|------|------|
| `backend/app/Models/BookingDayAllocation.php` | Site occupancy per event day |
| `backend/app/Services/BookingAllocationReservationService.php` | Transactional site reservation + MySQL 1062 → conflict |
| `backend/database/migrations/2026_07_14_000004_create_booking_day_allocations_table.php` | `active_lock` unique concurrency pattern |
| `backend/database/migrations/2026_07_14_000005_fix_booking_day_allocations_status_lock_check.php` | CHECK + COALESCE NULL semantics |

Statuses: `reserved`, `confirmed`, `released`, `cancelled`. Unique `(event_day_id, event_site_id, active_lock)`.

### 3.3 Booking invoices (booking financial parent only)

| Path | Role |
|------|------|
| `backend/app/Models/Invoice.php` | Required `booking_id`; statuses `Paid`/`Unpaid`/`Pending Verification`/`Refunded` |
| `backend/database/migrations/2026_05_09_052404_create_invoices_table.php` | `amount FLOAT`; cascade on booking delete |
| `backend/database/migrations/2026_06_24_000001_add_payment_proof_to_invoices_table.php` | Proof path + submitted_at |
| Phase 2A ADR §4 | “One Invoice per booking remains the financial parent” |

No polymorphism. No fee/charge tables.

### 3.4 Vendor identity

| Path | Role |
|------|------|
| `backend/app/Models/User.php` | `role=community`; `vendor_status` |
| `backend/app/Models/VendorBusinessProfile.php` | Optional 1:1 business details |
| `backend/app/Support/CommunityVendorIntent.php` | Intent inferred from status/profile/bookings/items |
| `backend/app/Http/Middleware/EnsureVendorApproved.php` | Registered but **not** applied to `/vendor/items` routes |
| `backend/routes/api.php` | `/vendor/items*` behind `auth:sanctum` + `role:community` |

Vendor is not a database role. Item ownership is `vendor_items.user_id`.

### 3.5 Audit patterns

| Pattern | Path | Deletion safety |
|---------|------|-----------------|
| Booking audit | `BookingAuditLog` + `BookingAuditLogger` | Actor/booking FKs **cascade** — unsafe to copy |
| Layout audit | `EventLayoutAuditLog` | Event delete **cascades** |
| Category migration audit | `CategoryMigrationAudit` + append uniqueness | Strongest append observation pattern |
| Category override / attendance exception | Dedicated history tables + restrict FKs | Prefer this style for Phase 4 |

No polymorphic audit framework. No Policy classes.

### 3.6 Explicit absences

No models/tables/routes/UI for: item reservation, inventory quantity, reservation fee, reservation charge confirmation, item hold expiry scheduler, reservation payment proof.

Public marketplace copy currently states purchases are in-person and there is no online checkout, delivery, or postage (`ReuseMarketplace.vue`). Reservation must be introduced as an additive hold + Organizer service fee, not as online purchase of the item price.

---

## 4. Reusable architecture

**Reuse safely:**

1. **`VendorItem` as the reservable listing aggregate** — extend; do not invent a second catalogue.
2. **`MarketplaceEligibility` / `MarketplaceItemPresenter` / `VendorItemPresenter`** — extend for reservability flags and privacy.
3. **`active_lock` + unique partial occupancy** from `booking_day_allocations` — copy the *pattern* onto item reservations (new table).
4. **`DomainConflictException` + MySQL error 1062 constraint-name matching** — same collision translation as `BookingAllocationReservationService`.
5. **Route prefixes** — `/marketplace/*`, `/vendor/*`, `/organizer/*`, `/api` global prefix.
6. **Manual payment UX patterns** — `VendorPaymentModal.vue` (proof UX if later needed), `OrganizerBookingsPanel.vue` verify flow, `OrganizerWithdrawalReconciliationModal.vue` timeline.
7. **Money storage for new fields** — follow `vendor_items.price` `DECIMAL(10,2)`, not invoice `FLOAT`.
8. **Image stack** — `reuse-items` disk path, `MultiImageUploadField.vue`, `ReuseItemImageGallery.vue`, `imageUrl.js`.
9. **E2E fixture command pattern** — Artisan fixture + JSON + cleanup (`E2EPublicLayoutFixtures`, phase3 helpers).
10. **Test cleanup trait** — extend `CleansUpTestFixtures` with reverse-FK deletes for new tables.
11. **Governance** — `ManagementRole`, `ManagementCapability`, `useManagementAccess.js`; CMart Management excluded from Carboot ops.

---

## 5. Unsafe reuse candidates

| Candidate | Why unsafe |
|-----------|------------|
| `booking_day_allocations` / `BookingAllocationReservationService` | Physical site-day occupancy; statuses and uniqueness keys are site/day scoped |
| `invoices` / `Invoice` | Required `booking_id`; FLOAT; payment-proof/verification coupled to booking approval and allocation confirmation |
| Booking `approval_status` / withdrawal | Must not change when an item is reserved or cancelled |
| `BookingAuditLog` cascade FKs | Deleting actor/booking erases history |
| Status word `reserved` on allocations | Ambiguous if reused for item holds in shared presenters without `item_` prefix |
| Soft-delete | Repository has **no** SoftDeletes convention; do not introduce casually for MVP |
| Laravel Scheduler | `Console/Kernel.php` schedule is empty — do not depend on cron for Phase 4.1–4.3 |
| `EnsureVendorApproved` as silent gate | Not currently applied to item CRUD; changing it would alter Phase 1–3 behaviour — Phase 4 must decide eligibility explicitly without silently tightening existing item CRUD unless scoped |

---

## 6. Missing capabilities (required for Phase 4)

1. `item_reservations` aggregate with lifecycle + fee snapshot + active lock
2. `item_reservation_audits` append-oriented history
3. Event-level (or equivalent) Organizer service-fee configuration + snapshot
4. Reservation create/cancel/confirm-charge/waive/expire/complete APIs
5. Community “my reservations” and vendor “reservations on my items” reads
6. Organizer reservation queue + reconciliation UI
7. Public/community reserve CTA and conflict refresh UX
8. Presenter fields: `is_reservable`, `reservation_state` (derived), charge summary
9. Fixture command + cleanup for E2E
10. Hard-delete guards so item/reservation history cannot be wiped casually
11. Vendor-item write path making `vendor_category_id` authoritative (Phase 3 debt, Phase 4 preparation)

---

## 7. Frozen MVP decisions

| # | Decision | Frozen value |
|---|----------|--------------|
| D1 | Extra charge ownership | Organizer-owned **reservation service fee** (not vendor deposit) |
| D2 | Payment handling | Manual / off-platform; system records amount, status, note, confirmer, timestamp only |
| D3 | Who may reserve | Authenticated `community` only; not public visitors; not organizer/cmart_management/super_admin via community endpoint |
| D4 | Self-reserve | Forbidden (`reserving_user_id !== item.user_id`) |
| D5 | Active reservation invariant | At most one active reservation per item; DB-enforced |
| D6 | Separation from site booking | No mutation of bookings, invoices, allocations, or booking withdrawal coupling |
| D7 | Item ownership | Authoritative: `vendor_items.user_id` (community user). Business profile optional. Booking/event are eligibility/context, not owners |
| D8 | Listing model | **Option B:** vendor-global items; public/reservable only when eligibility holds |
| D9 | Quantity | One physical unit per listing; no quantity/stock decrement |
| D10 | Reservation IDs | Numeric internal PK + opaque, non-sequential public reference (for example `RSV-7K4M9Q2P`); generator deferred to Phase 4.2 |
| D11 | Expiry | Manual Organizer expire in MVP; **no** scheduled auto-expiry in Phase 4.1–4.4 |
| D12 | Fee configuration | Event-level `item_reservation_service_fee` `DECIMAL(10,2)`; snapshot onto reservation at create |
| D13 | Charge waiver | Organizer may waive with required reason; preserve original required amount |
| D14 | Completion | Single-actor mark “collected/handed over” by Organizer **or** item vendor |
| D15 | Charge storage | Columns on `item_reservations` (no separate charge table; no `invoices` reuse) |
| D16 | Item status source of truth | Keep `active`/`inactive` publication; **do not** store `reserved` on the item; derive hold from active reservation row |
| D17 | Audit | Dedicated `item_reservation_audits`; restrict/null FKs; no cascade wipe |
| D18 | Zero-fee charge | `reservation_status=confirmed`, `charge_status=not_required`, fee snapshot `0.00`; no waiver actor/reason |

---

## 8. Item ownership model

**Authoritative owner:** `vendor_items.user_id` → `users.id` where operational actor is a `community` user acting as vendor.

**Not authoritative:**

- `vendor_business_profiles` — display/enrichment only
- `bookings` — eligibility and event context only
- `event_sites` — unrelated to item holds

**Reservation buyer:** `item_reservations.reserving_user_id` → authenticated community user.

**Operational event context:** snapshot `carboot_event_id` (and optional `booking_id` of the vendor’s Approved booking for that event) at reservation creation using the same selection rule as `MarketplaceEligibility::upcomingApprovedEventForUser()`.

---

## 9. Vendor listing eligibility

### Frozen rule — Option B (smallest change consistent with existing code)

Evidence: `MarketplaceEligibility` already allows private item management for any community user, while public preview requires:

```text
item.status = active
AND owner has Booking.approval_status = Approved
AND that booking’s CarbootEvent.ends_at >= now()
```

**MVP rules:**

| Action | Rule |
|--------|------|
| Create/edit draft-like inactive item | Any authenticated `community` owner (unchanged) |
| Set item `active` | Allowed for owner; public visibility still derived |
| Appear in public marketplace | Existing eligibility |
| Accept new reservation | Item publicly previewable **and** no active reservation **and** event fee configured (non-null) |
| Vendor views own items | Always (owner) |

### Booking / event lifecycle interactions

| Event | Effect on items | Effect on existing reservations |
|-------|-----------------|----------------------------------|
| Booking rejected / withdrawn | Item drops from public eligibility | **Do not** auto-delete/cancel; Organizer resolves active holds |
| Paid booking withdrawn | Same; booking invoice untouched by Phase 4 | Same; reservation charge history preserved |
| Attendance days reduced | No direct item effect | No direct effect |
| Event cancelled / ended | New reservations blocked (eligibility fails) | Active holds remain until cancel/expire/complete |
| Item has active reservation | Vendor may not hard-delete; unpublish (`inactive`) blocked while active hold exists | Hold remains authoritative |

Withdrawal of a site booking must never cascade-delete `item_reservations` or rewrite charge history.

---

## 10. Event and booking relationship

| Concept | MVP binding |
|---------|-------------|
| Item row | Vendor-global; **no** required `carboot_event_id` on `vendor_items` |
| Public listing presence | Derived via owner’s Approved upcoming booking |
| Reservation row | **Snapshots** `carboot_event_id` (+ optional `vendor_booking_id`) at create |
| Booking mutation | Forbidden from reservation flows |

If a vendor has multiple Approved upcoming events, use the same earliest-`starts_at` selection as `upcomingApprovedEventForUser()`. Document that ambiguity as known MVP limitation; do not build multi-event item targeting in MVP.

---

## 11. Item lifecycle

Canonical publication statuses remain:

| Status | Meaning |
|--------|---------|
| `inactive` | Not publicly listed; owner may edit |
| `active` | Eligible for public listing **if** booking eligibility holds |

**Derived reservability** (not a stored item status):

```text
is_reservable =
  MarketplaceEligibility::isItemPubliclyPreviewable(item)
  AND NOT EXISTS active item_reservation for item
  AND event.item_reservation_service_fee IS NOT NULL
```

On reservation **completion**, set item to `inactive` (collected / no longer offered) inside the same transaction as clearing `active_lock`. Historical reservations remain.

**Hard delete:** forbidden when any `item_reservations` row exists for the item. This guard is activated in Phase 4.2, after the table exists, at `VendorItemController::destroy()` before deletion. Phase 4.1 does not query a future table or block current owner-authorized deletion. Existing `VendorItem::deleting` file cleanup remains the deletion mechanism.

---

## 12. Reservation lifecycle

Canonical `reservation_status` values:

| Status | `active_lock` | Meaning |
|--------|---------------|---------|
| `pending_charge` | `1` | Hold placed; Organizer service fee outstanding |
| `confirmed` | `1` | Fee confirmed or waived; item still held for pickup |
| `cancelled` | `NULL` | Hold released; history kept |
| `expired` | `NULL` | Organizer-expired hold; history kept |
| `completed` | `NULL` | Item collected/handed over; item set inactive |

**Active statuses** (hold the item): `pending_charge`, `confirmed`.

---

## 13. Manual charge lifecycle

Charge fields live on `item_reservations` (not `invoices`).

Canonical `charge_status` values:

| Status | Meaning |
|--------|---------|
| `required` | Fee amount > 0 snapshot; awaiting Organizer confirmation |
| `confirmed` | Organizer recorded off-platform payment received |
| `waived` | Organizer waived with reason; original amount preserved |
| `not_required` | Configured fee snapshot is `0.00`; no charge or Organizer waiver evidence is required |
| `cancelled` | Reservation cancelled before confirmation; charge no longer collectable |

**Never** use a bare status named `paid` without confirmer + timestamp. Prefer `charge_status=confirmed` plus `charge_confirmed_by` / `charge_confirmed_at` / `charge_confirmation_note`.

Zero-fee events: allow `item_reservation_service_fee = 0.00`. Create the reservation as `reservation_status=confirmed`, `charge_status=not_required`, with no waiver actor, reason, or timestamp.

---

## 14. Legal state transitions

### Reservation

| From | To | Actor | Guard |
|------|----|-------|-------|
| — | `pending_charge` | Community (non-owner) | Eligibility + lock + fee > 0 |
| — | `confirmed` | Community (non-owner) | Eligibility + lock + fee = 0 |
| `pending_charge` | `confirmed` | Organizer | Charge confirm or waive |
| `pending_charge` | `cancelled` | Reserving user, vendor owner, Organizer | — |
| `pending_charge` | `expired` | Organizer | Explicit expire action |
| `confirmed` | `cancelled` | Vendor owner or Organizer | No-refund acknowledgement if charge was confirmed |
| `confirmed` | `expired` | Organizer | Explicit expire + note |
| `confirmed` | `completed` | Vendor owner or Organizer | — |
| `cancelled`/`expired`/`completed` | * | — | Terminal; no reopen except new reservation on available item |

Community may **not** cancel after `confirmed` in MVP (Organizer/vendor only).

### Charge

| From | To | Actor |
|------|----|-------|
| `required` | `confirmed` | Organizer |
| `required` | `waived` | Organizer (+ reason) |
| `required` | `cancelled` | System with reservation cancel/expire while still required |
| `confirmed` / `waived` / `not_required` | — | Immutable except audit notes; cancellation of reservation does **not** rewrite to refunded |

---

## 15. Conceptual schema

No migrations in Phase 4.0. Conceptual tables only.

### 15.1 Extend `carboot_events`

| Column | Type | Notes |
|--------|------|-------|
| `item_reservation_service_fee` | `DECIMAL(10,2) NULL` | Organizer-configured; NULL = reservations not open for that event |

### 15.2 `item_reservations`

| Column | Type | Null | Notes |
|--------|------|------|-------|
| `id` | BIGINT PK | no | Numeric, repository convention |
| `public_reference` | VARCHAR | no | Unique opaque, non-sequential value; e.g. `RSV-7K4M9Q2P`; exact generator deferred to Phase 4.2 |
| `vendor_item_id` | FK → vendor_items | no | `restrictOnDelete` |
| `reserving_user_id` | FK → users | no | `restrictOnDelete` |
| `vendor_user_id` | FK → users | no | Snapshot of item owner; `restrictOnDelete` |
| `carboot_event_id` | FK → carboot_events | no | Snapshot; `restrictOnDelete` |
| `vendor_booking_id` | FK → bookings | yes | Snapshot of Approved booking used for eligibility; `nullOnDelete` |
| `reservation_status` | STRING/ENUM | no | See §12 |
| `active_lock` | TINYINT | yes | `1` when active; `NULL` when terminal |
| `service_fee_amount` | DECIMAL(10,2) | no | Immutable snapshot |
| `service_fee_currency` | CHAR(3) | no | Default `MYR` |
| `charge_status` | STRING/ENUM | no | See §13 |
| `charge_confirmation_note` | TEXT | yes | Manual payment note |
| `charge_confirmed_by` | FK → users | yes | `nullOnDelete` |
| `charge_confirmed_at` | DATETIME | yes | |
| `charge_waive_reason` | TEXT | yes | Required when waived |
| `cancellation_reason` | TEXT | yes | |
| `cancelled_by` | FK → users | yes | `nullOnDelete` |
| `cancelled_at` | DATETIME | yes | |
| `expired_by` | FK → users | yes | `nullOnDelete` |
| `expired_at` | DATETIME | yes | |
| `completed_by` | FK → users | yes | `nullOnDelete` |
| `completed_at` | DATETIME | yes | |
| `item_name_snapshot` | VARCHAR | no | Preserve listing title at reserve time |
| `created_at` / `updated_at` | timestamps | no | |

**Soft delete:** none.  
**Hard delete:** forbidden in application for normal ops; test cleanup only via explicit reverse-FK order.

### 15.3 `item_reservation_audits`

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT PK | |
| `item_reservation_id` | FK restrict | |
| `actor_user_id` | FK nullOnDelete | Never cascade |
| `action` | STRING | See §21 |
| `from_reservation_status` | STRING NULL | |
| `to_reservation_status` | STRING NULL | |
| `from_charge_status` | STRING NULL | |
| `to_charge_status` | STRING NULL | |
| `note` | TEXT NULL | |
| `metadata` | JSON NULL | Supplemental only |
| `created_at` | timestamp | No updates expected |

Optional DB trigger or model guard to reject UPDATE/DELETE in later hardening (mirror Phase 3.11 intent); MVP minimum is restrictive FKs + no destroy endpoints.

---

## 16. Constraints and indexes

### `item_reservations`

1. **Unique active hold:** `UNIQUE (vendor_item_id, active_lock)` — only one row with `active_lock=1` per item; multiple historical rows with `active_lock NULL` allowed (MySQL/MariaDB NULL unique semantics — same as `bda_day_site_active_lock_unique`).
2. **CHECK status/lock coupling** (MariaDB-compatible, COALESCE pattern from `2026_07_14_000005_...`):

```text
(reservation_status IN ('pending_charge','confirmed') AND COALESCE(active_lock,0)=1)
OR
(reservation_status IN ('cancelled','expired','completed') AND active_lock IS NULL)
```

3. Unique `public_reference`
4. Indexes: `(reserving_user_id, reservation_status)`, `(vendor_user_id, reservation_status)`, `(carboot_event_id, reservation_status)`, `(charge_status)`, `(vendor_item_id, reservation_status)`

### `item_reservation_audits`

- Index `(item_reservation_id, created_at)`
- Index `(action)`

---

## 17. Concurrency design

Mirror `BookingAllocationReservationService` locking discipline.

### Reservation create — transaction steps

1. Authenticate; require `role=community`.
2. Reject if role is organizer / cmart_management / super_admin (403).
3. `BEGIN`
4. `SELECT … FROM vendor_items WHERE id=? FOR UPDATE`
5. Confirm item exists; else 404
6. Confirm `user_id !== auth.id` (403 self-reserve)
7. Confirm `MarketplaceEligibility::isItemPubliclyPreviewable` (404 or 422 — prefer **404** for non-public to avoid leakage; **422** if active but fee missing)
8. Resolve event via `upcomingApprovedEventForUser`; require non-null fee column
9. Confirm no active reservation under lock (application check)
10. Snapshot fee, item name, event id, optional booking id
11. `INSERT` reservation with `active_lock=1` and status per fee
12. Insert audit `reservation_created`
13. `COMMIT`
14. Return presenter

### Duplicate-key handling

Catch `QueryException` with SQLSTATE/driver code **1062** and message containing the active-lock unique name → throw `DomainConflictException` with error code `item_already_reserved` → HTTP **409**.

### Idempotency

- Create is **not** idempotent across retries after success (second call → 409).
- Confirm/waive/cancel/complete: reject illegal transitions with **409** or **422**; do not silently rewrite.
- No automatic retry loops in API clients beyond user-driven refresh.

### Lock order

Always: **item row → insert reservation**. Never lock reservation before item when creating. Organizer mutations lock **reservation row** (`FOR UPDATE`) then optionally item when completing (to set inactive).

---

## 18. Authorization matrix

Legend: Y = allowed; N = denied; O = ownership-conditioned.

| Action | Public | Community | Reserving user | Item vendor | Organizer | CMart Mgmt | Super Admin |
|--------|:------:|:---------:|:--------------:|:-----------:|:---------:|:----------:|:-----------:|
| Browse published items | Y | Y | Y | Y | Y* | Y* | Y* |
| View private item draft | N | N | N | O | N† | N | N† |
| Create item | N | Y | — | Y | N | N | N |
| Edit item | N | N | N | O | N | N | N |
| Publish/unpublish item | N | N | N | O‡ | N | N | N |
| Reserve item | N | Y§ | — | N | N | N | N |
| View own reservation | N | N | Y | N | Y | N | Y¶ |
| View reservation for owned item | N | N | N | Y | Y | N | Y¶ |
| View all event reservations | N | N | N | N | Y | N | Y¶ |
| Confirm manual charge | N | N | N | N | Y | N | Y¶ |
| Waive charge | N | N | N | N | Y | N | Y¶ |
| Cancel reservation | N | N | Y (pending only) | Y | Y | N | Y¶ |
| Expire reservation | N | N | N | N | Y | N | Y¶ |
| Complete reservation | N | N | N | Y | Y | N | Y¶ |
| View audit timeline | N | N | limited | limited | full | N | full¶ |

\* Read via public marketplace endpoints only.  
† No Organizer draft inspection in MVP unless later explicit ops tool.  
‡ Unpublish blocked while active reservation exists.  
§ Community and not item owner; not staff roles.  
¶ Super Admin may use **explicit** Organizer-prefixed operational endpoints for emergency continuity; must not use community reserve endpoint. Prefer audit `organizer_override` metadata when acting as emergency.

CMart Management: **no** reservation operations or raw reservation queues (generated report summaries deferred).

---

## 19. API contract proposal

Follow existing `/api` prefixes in `backend/routes/api.php`. No Laravel route names required (current convention).

### Public

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| GET | `/marketplace/items` | none | Extend presenter with `is_reservable`, `has_active_reservation` (boolean only) |
| GET | `/marketplace/items/{vendor_item}` | none | Same; no other users’ reservation PII |

### Community reservation user

| Method | Path | Auth | Body / behaviour |
|--------|------|------|------------------|
| POST | `/reservations` | community | `{ vendor_item_id }` → create; 201 / 401 / 403 / 404 / 409 / 422 |
| GET | `/reservations/me` | community | List own reservations |
| GET | `/reservations/{item_reservation}` | community owner of reservation | Detail |
| POST | `/reservations/{item_reservation}/cancel` | reserving user | Only `pending_charge`; reason optional |

### Vendor

| Method | Path | Auth |
|--------|------|------|
| Existing | `/vendor/items` CRUD | community owner |
| GET | `/vendor/item-reservations` | community | Reservations on owned items |
| GET | `/vendor/item-reservations/{item_reservation}` | owner vendor |
| POST | `/vendor/item-reservations/{id}/cancel` | owner vendor |
| POST | `/vendor/item-reservations/{id}/complete` | owner vendor |

### Organizer

| Method | Path | Auth |
|--------|------|------|
| GET | `/organizer/events/{carboot_event}/item-reservations` | carboot_operations |
| GET | `/organizer/item-reservations/{item_reservation}` | carboot_operations |
| PATCH | `/organizer/events/{carboot_event}/item-reservation-fee` | set fee |
| POST | `/organizer/item-reservations/{id}/confirm-charge` | note required |
| POST | `/organizer/item-reservations/{id}/waive-charge` | reason required |
| POST | `/organizer/item-reservations/{id}/cancel` | reason; no-refund ack if charge confirmed |
| POST | `/organizer/item-reservations/{id}/expire` | reason |
| POST | `/organizer/item-reservations/{id}/complete` | — |
| GET | `/organizer/item-reservations/{id}/audits` | full timeline |

### HTTP semantics

| Code | Meaning |
|------|---------|
| 401 | Unauthenticated |
| 403 | Authenticated but role/ownership forbids |
| 404 | Item/reservation not visible or missing |
| 409 | Active reservation conflict / illegal concurrent transition (`DomainConflictException`) |
| 422 | Validation / fee not configured / illegal state without concurrency |

Idempotency: mutating POSTs are single-transition; repeat after success → 409/422.

---

## 20. Presenter and privacy boundaries

### Public / anonymous

Allowed: item catalogue fields already in `MarketplaceItemPresenter`; booleans `is_reservable`, `has_active_reservation`; public event title/date; public vendor business_name/logo/category.

Forbidden: reserving user identity, phones, emails, charge notes, audit metadata, payment paths, IP, internal IDs of other users’ reservations, Organizer reconciliation fields.

### Reserving user

Own reservation: reference, statuses, fee snapshot, charge status (not other users), item snapshot, event summary, limited own audit actions.

### Item vendor

Reservations on owned items: reserving user **display name** only (no phone/email unless an existing approved channel already exposes it — MVP: name only), statuses, fee, timestamps, limited audit.

### Organizer

Full operational fields: actors, notes, waive reasons, confirmation evidence, full audit, booking snapshot ids.

### CMart Management

No reservation operational payloads.

---

## 21. Audit event design

Append-only rows on `item_reservation_audits`. Recommended actions for frozen lifecycle:

| Action | When |
|--------|------|
| `reservation_created` | Create |
| `charge_confirmation_recorded` | Organizer confirm |
| `charge_waived` | Organizer waive |
| `reservation_confirmed` | Transition to confirmed (fee confirm, waive, or zero-fee `not_required` create) |
| `reservation_cancelled` | Cancel |
| `reservation_expired` | Expire |
| `reservation_completed` | Complete |
| `item_archived_on_completion` | Item set inactive with completion |
| `organizer_override` | Super Admin / emergency note flag in metadata |

Item create/publish/unpublish continue to rely on existing item timestamps unless a later subphase adds item-level audit; not required to block reservation MVP.

**Retention:** never delete audit or reservation rows on cancel/expire/complete/unpublish. Test DB cleanup only.

---

## 22. Cancellation, expiry, and completion rules

| Topic | Rule |
|-------|------|
| Cancel ≠ delete | Status transition + `active_lock=NULL` |
| Community cancel | Only `pending_charge` |
| Vendor cancel | `pending_charge` or `confirmed`; if charge confirmed, require no-refund acknowledgement text |
| Organizer cancel | Any active; same acknowledgement when charge confirmed |
| Expire | Organizer only; sets `expired`; clears lock; item becomes reservable again if still active+eligible |
| Complete | Vendor or Organizer; clears lock; sets item `inactive` |
| After charge confirmed | No automated refund; charge_status stays `confirmed` |
| History | All rows retained |

Automatic scheduler expiry: **deferred** (Kernel schedule empty; avoid untestable cron dependency in early phases).

---

## 23. Booking and Invoice isolation

Frozen isolation contract:

| Existing aggregate | Phase 4 may read? | Phase 4 may write? |
|--------------------|-------------------|--------------------|
| `bookings` | Yes (eligibility / snapshot FK) | **No** status/withdrawal/allocation changes |
| `invoices` | No need for MVP | **No** create/update/delete |
| `booking_day_allocations` | No | **No** |
| Payment proof paths | No | **No** |
| Booking audit logs | No | **No** |

Phase 2A ADR remains: booking invoice is the financial parent **for site bookings only**. Phase 4 service fees are a **separate financial parent** on `item_reservations`.

---

## 24. Frontend architecture proposal

Do not implement in 4.0. Recommended screens:

### Public / community

| Screen | Build from |
|--------|------------|
| Catalogue | `ReuseMarketplace.vue` + `MarketplaceItemCard.vue` |
| Item details + Reserve CTA | `MarketplaceItemDetailsModal.vue` |
| Reserve confirm modal | New; modal conventions from `VendorItemFormModal.vue` |
| My reservations | New section on community/vendor dashboard or profile |
| Cancel warning | Confirm modal pattern from organizer bookings |

Auth gate: redirect guests to login before reserve (router `requiresAuth` + community role).

Conflict UX: follow `Registration.vue` / site-selection 409 refresh — reload item, show toast, disable reserve if no longer reservable.

### Vendor

| Screen | Build from |
|--------|------------|
| My items | `VendorItemManager.vue` |
| Item form | `VendorItemFormModal.vue` (+ fee/reservability read-only hints) |
| Reservations on my items | New list; registry pattern from `VendorHistoryReceipts.vue` |
| Complete / cancel | Modal + toast |

### Organizer

| Screen | Build from |
|--------|------------|
| Event reservation queue | `OrganizerBookingsPanel.vue` filter/registry pattern |
| Fee config control | Event management panel section |
| Confirm / waive charge | Verification confirm pattern in organizer bookings |
| Audit timeline | `OrganizerWithdrawalReconciliationModal.vue` timeline |
| Access | `useManagementAccess.js` + `workspaceNav.js` new section requiring `carboot_operations` |

### Shared utilities

- Toasts: `vue-toastification` via `main.js`
- API: new `frontend/src/services/itemReservationsApi.js` (Phase 3 focused-service style)
- Money: prefer consolidating MYR helpers; interim reuse `vendorCatalog.js` / `Intl.NumberFormat('en-MY')`
- Images: existing upload stack unchanged in reservation phases (images already implemented)

---

## 25. Testing strategy

### Backend (PHPUnit)

Must include:

- Auth 401 / role 403 boundaries (community vs organizer vs cmart_management vs super_admin)
- Self-reserve rejection
- Public presenter privacy (no reserving-user PII)
- Eligibility: no Approved upcoming booking → not reservable
- Fee null → 422; fee 0 → confirmed+`not_required` path; fee > 0 → pending_charge
- One-active-reservation + deterministic 1062 → 409 (`item_already_reserved`)
- Optional true concurrency test (parallel requests) where CI allows
- Charge snapshot immutability when event fee later changes
- Confirm / waive authorization and audit rows
- Cancel / expire / complete transitions and lock clearing
- Completion sets item inactive
- Archive/delete blocked with history
- Booking withdrawal does not mutate reservation rows or invoices
- Invoice row count unchanged across reservation flows
- Allocation rows unchanged
- Append-only audit (no update endpoint; delete restricted)
- Fixture cleanup restores baseline (`CleansUpTestFixtures` extension)
- Duplicate-key error specificity by constraint name

### Frontend unit

- Catalogue reservability badges
- Reserve modal success / 409 refresh
- Role visibility (management nav absence for cmart_management)
- Vendor ownership list
- Organizer confirm/waive forms
- Presenter field assumptions (no forbidden keys)

### E2E (minimal)

1. Successful reserve → pending_charge visible to user/vendor/organizer  
2. Stale/conflict second reserve → 409 + UI refresh  
3. Organizer confirm charge  
4. Cancel or expire → item reservable again  
5. Access denial (guest reserve, cmart_management ops, self-reserve)

---

## 26. Fixture and cleanup strategy

Follow Phase 3 E2E pattern:

- Artisan command e.g. `e2e:item-reservation-fixtures` writing JSON markers
- Isolated E2E database verification (existing helpers)
- Cleanup order (reverse FK):

1. `item_reservation_audits`
2. `item_reservations`
3. `reuse_item_images` / vendor items (model delete for files)
4. invoices/allocations/bookings/events/users as already ordered in `CleansUpTestFixtures`

Extend `CleansUpTestFixtures` with tracked reservation and audit IDs. Prefer Eloquent deletes for items to fire file cleanup hooks.

---

## 27. Migration and compatibility risks

| Risk | Mitigation |
|------|------------|
| `vendor_items.user_id` cascadeOnDelete | Phase 4 FKs from reservations must `restrictOnDelete` so user delete cannot silently wipe holds; block user delete when reservations exist or null carefully — prefer restrict on reservation FKs |
| Invoice FLOAT vs DECIMAL | New fee columns DECIMAL only |
| `cmart_db` still missing Phase 3 migrations | Phase 4 must **not** combine with Phase 3 rollout (`phase-3-11` entry criteria) |
| Vendor category string writes | Phase 4.1 should make `vendor_category_id` authoritative on item writes (debt register) |
| Marketplace copy says “no reservations” | UI copy update in Phase 4.4 when reserve ships |
| Status vocabulary clash (`reserved`) | Always prefix APIs/UI with **item reservation** |
| MariaDB NULL unique semantics | Use proven `active_lock` pattern + COALESCE CHECK |
| No SoftDeletes | Phase 4.2 history guard in `VendorItemController::destroy()` + restrictive reservation FKs; Phase 4.1 preserves current deletion |

---

## 28. Recommended Phase 4 subphases

### Phase 4.0 — Rules and Architecture Audit (this document)

- Exit: architecture frozen; no production code

### Phase 4.1 — Item Listing Foundation

- **Objective:** Harden `vendor_items` for reservation readiness without enabling reserve yet  
- **Dependencies:** Phase 4.0  
- **Scope:** Event fee column; canonical `vendor_category_id` writes; presenter flags stubbed `is_reservable=false` until 4.2; tests preserving the centralized owner-authorized item delete and image cleanup path; no reservation table  
- **Exclusions:** Reserve API, charge confirm, UI reserve CTA, scheduler  
- **Validation:** Backend tests for fee column + category write authority; no booking/invoice regressions  
- **Exit:** Items remain preview-only to users; schema ready

The `item_reservations` schema and reservation-history delete guard ship in 4.2 only. Phase 4.1 stays listing/fee/category/readiness.

### Phase 4.2 — Reservation Engine and Concurrency

- **Objective:** Create reservation aggregate + DB lock + create/cancel(pending) APIs  
- **Dependencies:** 4.1  
- **Scope:** Migrations for reservations/audits; create transaction; 409 mapping; community/vendor reads; tests including collision  
- **Exclusions:** Charge confirm/waive UI; completion; auto-expiry; full Organizer queue UI  
- **Exit:** One-active invariant proven in tests

### Phase 4.3 — Manual Extra Charge Lifecycle

- **Objective:** Organizer confirm/waive/expire/complete + audit timeline API  
- **Dependencies:** 4.2  
- **Scope:** Charge transitions; Organizer endpoints; isolation tests vs invoices/allocations  
- **Exclusions:** Payment gateway; full frontend polish  
- **Exit:** Manual charge lifecycle complete at API level

### Phase 4.4 — Vendor, Community and Organizer UI

- **Objective:** Wire Vue surfaces and copy updates  
- **Dependencies:** 4.3  
- **Scope:** Reserve CTA, my reservations, vendor tracking, organizer queue/fee/reconcile  
- **Exclusions:** E2E full suite closure  
- **Exit:** Role-appropriate UI complete; unit tests green

### Phase 4.5 — E2E, Hardening and Closure

- **Objective:** Fixture E2E, cleanup, privacy/lint closure, debt updates  
- **Dependencies:** 4.4  
- **Scope:** Five minimal E2E flows; cleanup; documentation closure verdict  
- **Exclusions:** Auto-scheduler expiry unless explicitly pulled in with cron proof  
- **Exit:** Phase 4 MVP closed

---

## 29. Deferred features

- Payment gateway / FPX / card / wallet
- Automated refunds, payouts, split payment, escrow, disputes
- Reservation payment-proof upload (optional later; Organizer note may suffice for MVP)
- Scheduled auto-expiry / cron
- Multi-quantity inventory
- Event-specific item copies / multi-event targeting UI
- Guest checkout / claim later
- CMart Management reservation reports
- Polymorphic billing refactor
- Soft deletes
- Item-level moderation queue for Organizer beyond reservation ops
- Push/email notifications
- UUID public identifiers

---

## 30. Unresolved blockers

**None.**

Recorded non-blockers (explicit decisions already frozen above):

- Phase 3 `cmart_db` rollout still pending — operational, not an architecture contradiction; Phase 4 remains a separate change set.
- Marketplace preview copy currently denies “reservations” — product copy update belongs in Phase 4.4, not a schema conflict.
- Exact string `PHASE 3.11 COMPLETE — PHASE 3 CLOSED, READY FOR PHASE 4` is not literal in `phase-3-11-hardening-migration-readiness-and-closure.md`; substance confirms Phase 3 product closure and Phase 4 entry criteria — accepted as closed for Phase 4.0 start.

---

## 31. Phase 4.0 verdict

```text
PHASE 4.0 COMPLETE — READY FOR PHASE 4.1
```

Architecture freeze covers ownership, eligibility (Option B), quantity, fee ownership, manual charge tracking, invoice/allocation isolation, lifecycles, concurrency, authorization, APIs, privacy, audit, tests, fixtures, and subphases. No production implementation was performed in Phase 4.0.

**Next:** Phase 4.1 — Item Listing Foundation (event fee column, category write authority, delete/archive guards, presenter stubs; no reservation create API).
