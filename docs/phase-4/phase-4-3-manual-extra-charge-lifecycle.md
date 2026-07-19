# Phase 4.3 — Manual Extra Charge Lifecycle

## Objective

Complete the backend manual service-fee workflow on top of the frozen Phase 4.2
reservation aggregate: Organizer event queue/detail/audit APIs, manual charge
confirmation, charge waiver, Organizer cancellation, manual expiry, and vendor
cancellation of confirmed reservations with an explicit no-refund
acknowledgement. No reservation-facing Vue UI, scheduler, payment gateway,
refund record, or payout logic is included.

The fee remains an Organizer-owned, manual, off-platform charge. The system
records that the Organizer confirmed receipt; it never claims to process money.

## Files Changed

| File | Change |
|---|---|
| `backend/database/migrations/2026_07_19_000003_add_charge_waiver_actor_to_item_reservations.php` | New focused additive migration: nullable `charge_waived_by` (FK users, `ON DELETE SET NULL`) and `charge_waived_at` |
| `backend/app/Models/ItemReservation.php` | Waiver fields fillable/cast, `chargeWaiver()` relationship |
| `backend/app/Models/ItemReservationAudit.php` | New action constants: `charge_confirmation_recorded`, `charge_waived`, `reservation_confirmed`, `reservation_expired` |
| `backend/app/Services/ItemReservationLifecycleService.php` | New: confirm, waive, Organizer cancel, manual expire — all transactional and locked |
| `backend/app/Services/ItemReservationCancellationService.php` | Vendor may now cancel `confirmed` with reason + acknowledgement; charge-history-preserving termination |
| `backend/app/Services/ItemReservationPresenter.php` | `forOrganizerQueue`, `forOrganizer`, `auditEntry` presenters |
| `backend/app/Http/Controllers/Api/OrganizerItemReservationController.php` | New thin Organizer controller (queue, detail, audits, four mutations) |
| `backend/app/Http/Controllers/Api/VendorItemReservationController.php` | Accepts `acknowledge_no_refund`; maps `cancellation_reason_required` to 422 |
| `backend/routes/api.php` | Seven Organizer reservation routes inside the existing `organizer` + `carbootOperationalRoles` group |
| `backend/tests/Feature/Phase43ManualChargeLifecycleTest.php` | New focused suite (12 tests, 198 assertions) |
| `docs/phase-3/phase-3-debt-register.md` | Reservation debt row advanced to Phase 4.3 complete |

## Organizer API

All routes live inside the existing `auth:sanctum` + `role:organizer,super_admin`
(`carbootOperationalRoles`) group, so CMart Management and community users
receive `403` from middleware without touching reservation code:

```text
GET  /api/organizer/events/{carboot_event}/item-reservations
GET  /api/organizer/item-reservations/{public_reference}
GET  /api/organizer/item-reservations/{public_reference}/audits
POST /api/organizer/item-reservations/{public_reference}/confirm-charge   { note }
POST /api/organizer/item-reservations/{public_reference}/waive-charge     { reason }
POST /api/organizer/item-reservations/{public_reference}/cancel           { reason, acknowledge_no_refund? }
POST /api/organizer/item-reservations/{public_reference}/expire           { reason }
```

Super Admin uses the same routes under the existing emergency Organizer-style
convention; every mutation records the acting user in lifecycle fields and
audits, so Super Admin actions stay auditable.

## Queue and Query Strategy

* Event-scoped: `where('carboot_event_id', $event->id)` on the route-bound event.
* Filters: `reservation_status` and `charge_status`, validated against the model
  constant lists.
* Pagination: default 20 per page, capped at 48, newest first (`created_at`,
  then `id`).
* Eager loads `vendorUser.businessProfile` and `reservingUser`; a query-count
  test proves the total query count is identical for one and three reservations.
* Queue rows expose reference, statuses, fee snapshot, item name, and the
  operational identities only — no internal IDs, invoices, or proof paths.

## Charge Confirmation Rules

`pending_charge + required → confirmed + confirmed`, allowed only from exactly
that state. Records `charge_confirmed_by`, `charge_confirmed_at`, and the
required bounded plain-text note (max 500). `active_lock` stays `1`. Two audits
are inserted atomically in the same transaction:
`charge_confirmation_recorded` (with `manual_off_platform_payment: true` and the
fee snapshot in metadata) and `reservation_confirmed`. Repeats and resolved
charges return deterministic `409` (`reservation_not_pending_charge`,
`charge_already_resolved`) without touching evidence or audits.

## Charge Waiver Rules

`pending_charge + required → confirmed + waived` with a required reason.
Waiver evidence is stored in the new dedicated `charge_waived_by` /
`charge_waived_at` columns plus the existing `charge_waive_reason`, keeping it
fully distinct from confirmation evidence (`charge_confirmed_*` stay `NULL`).
Audits: `charge_waived` + `reservation_confirmed`. A zero-fee `not_required`
reservation is not a waiver: it can be neither confirmed nor waived, and no
audit ever claims payment confirmation for it.

## Cancellation and No-Refund Rules

Charge history on termination (shared rule for Organizer cancel, vendor cancel,
and expiry): `required → cancelled`; `confirmed`, `waived`, and `not_required`
remain unchanged.

* **Organizer cancel** — allowed from `pending_charge` or `confirmed`; reason
  required (422 if missing); `acknowledge_no_refund: true` required when the
  charge is `confirmed`, otherwise `409 no_refund_acknowledgement_required`;
  `active_lock` cleared; confirmation evidence untouched; one
  `reservation_cancelled` audit (with `no_refund_acknowledged` metadata when
  applicable); no refund row or status of any kind.
* **Vendor cancel** — the existing endpoint now also accepts `confirmed`
  reservations on owned items: reason required
  (`422 cancellation_reason_required`), acknowledgement required for a
  confirmed charge, `active_lock` cleared, one audit. Non-owners still get
  `404`.
* **Reserving user** — unchanged Phase 4.2 rule: pending-only
  (`409 reservation_not_pending` for confirmed).
* A cancelled item becomes reservable again when otherwise eligible.

## Manual Expiry

Organizer-only, from `pending_charge` or `confirmed`, with a required reason.
Sets `reservation_status = expired`, clears `active_lock`, records `expired_by`
and `expired_at`, applies the shared charge-termination rule, and inserts one
`reservation_expired` audit carrying the reason. Repeats return
`409 reservation_not_active`. No scheduler or automatic timeout exists.

## Transaction and Concurrency Model

Every mutation runs inside `DB::transaction()`, re-fetches the reservation with
`lockForUpdate()`, re-checks state under lock, rejects illegal transitions with
`DomainConflictException` (rolled back, no partial writes, no duplicate audits),
updates lifecycle fields, and inserts its audits before commit. The Phase 4.2
reservation-creation lock order is untouched; lifecycle mutations lock only the
reservation row and insert audits.

## Privacy and Authorization

* CMart Management and community users: `403` on all Organizer reservation
  routes (middleware).
* Organizer detail/queue expose public references, statuses, fee snapshot,
  item/event summaries, operational vendor and reserving-user identity
  (name/email/business name), and lifecycle evidence — never passwords, tokens,
  payment-proof paths, IPs, or internal database IDs.
* Vendor and reserving-user presenters are unchanged from Phase 4.2.
* Unauthorized reservation references still resolve to `404`, not `403`.

## Booking and Payment Isolation

The lifecycle service reads and writes only `item_reservations` and
`item_reservation_audits`. A dedicated test creates a booking with an invoice,
runs all four mutations (confirm, waive, cancel, expire) on separate
reservations, and asserts raw booking and invoice rows are byte-identical, the
invoice count is unchanged, and no `booking_day_allocations` or
`booking_audit_logs` rows appear.

## Schema Change

One focused additive migration only: nullable `charge_waived_by`
(FK → `users`, `ON DELETE SET NULL`, history-preserving) and nullable
`charge_waived_at` on `item_reservations`. Rollback drops only those two
columns; a rollback/reapply cycle was executed against the testing database and
the Phase 4.2 tables, columns, and constraints were verified intact.

## Tests and Validation

* Focused: `Phase43ManualChargeLifecycleTest` — 12 passed, 198 assertions
  (queue scope/filters/pagination/privacy/query-count, confirmation, waiver,
  zero-fee guard, Organizer cancellation, vendor confirmed cancellation,
  manual expiry, detail/audit timeline, financial isolation, waiver schema).
* Backend full: `php artisan test` — **360 passed, 0 failed, 0 skipped,
  2006 assertions** (baseline 348/1808 plus the 12 new tests).
* Frontend: `npm run test:unit` — **85 passed, 0 failed, 0 skipped**.
* Lint: Oxlint 0 errors, ESLint 0 errors, Laravel Pint passed.
* No production frontend files changed, so no frontend build was required.
* E2E: unchanged by design — no Phase 4 reservation E2E yet.

## Persistent Cleanup

Test teardown deletes audits before reservations, then items, bookings, events,
and users. Post-suite markers verified at zero: `phase43-%` users, `Phase43%`
events/items/reservations, orphan reservation audits, and `phase42-%` residue.

## Deferred

* `confirmed → completed` transition (reservation completion).
* All reservation-facing Vue UI (Phase 4.4).
* Automated expiry scheduler/timeouts.
* Payment gateway, FPX/card, proof upload, refund records, payouts, escrow,
  split payment, and invoice reuse (permanently out of the manual-fee design).

## Verdict

PHASE 4.3 COMPLETE — READY FOR PHASE 4.4
