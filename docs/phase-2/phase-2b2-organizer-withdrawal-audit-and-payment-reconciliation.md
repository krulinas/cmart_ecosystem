# Phase 2B.2 — Organizer Withdrawal Audit and Payment Reconciliation Visibility

## Result

**Completed — Organizer withdrawal reconciliation verified.**

Phase 2B.2 adds an Organizer-only, read-only booking detail response and interface for interpreting withdrawn bookings. It preserves Phase 2B.1 lifecycle authority: the Booking is operationally withdrawn, allocations are released, and the Invoice remains the financial authority.

> The reconciliation view is read-only and does not reverse payment, recreate allocations, or modify Booking history.

> Payment-submitted withdrawal remains recorded as `Pending Verification`; it is not converted to `Paid`, `Unpaid`, or `Refunded`.

> CMart Management does not receive access to Organizer withdrawal reconciliation or raw booking audit history.

## Organizer authorization

The existing management route is retained:

```text
GET /api/bookings/{booking}
```

It remains inside the Carboot operational role middleware:

- Organizer: allowed
- Super Admin: established authority preserved
- CMart Management: `403`
- Community owner/non-owner: `403`
- Unauthenticated: `401`

No new route or broader authorization surface was introduced.

## Reconciliation response

`VendorBookingPresenter::presentForOrganizer($booking, true)` adds:

```json
{
  "withdrawal_reconciliation": {
    "is_withdrawn": true,
    "withdrawn_at": "...",
    "withdrawn_by": { "id": 10, "name": "Vendor Name" },
    "payment_state": "payment_submitted",
    "invoice_payment_status": "Pending Verification",
    "invoice_amount": "60.00",
    "payment_proof_present": true,
    "payment_verified": false,
    "payment_verified_at": null,
    "payment_verified_by": null,
    "no_refund_applied": true,
    "financial_history_preserved": true,
    "allocation_status": "released",
    "sites_released": true,
    "released_site_labels": ["A01", "A02"],
    "active_day_count": 2,
    "event_days": []
  }
}
```

Private fields are excluded: payment-proof path, allocation rows/IDs, `active_lock`, `released_by`, raw audit notes, IP addresses, and internal unknown action names.

## Payment-state mapping and no-refund interpretation

| Invoice state | Derived state | No-refund applied |
| --- | --- | ---: |
| `Paid` | `paid` | Yes |
| `Pending Verification` | `payment_submitted` | Yes |
| `Unpaid` or absent | `unpaid` | No |

Paid is displayed as paid with no refund. Payment submitted is displayed as proof submitted and verification not completed. Unpaid is displayed as unpaid with refund not applicable.

`financial_history_preserved` is a read-only indicator that the Invoice still exists. It is not an accounting entry.

## Financial-history preservation

Organizer reads do not mutate Booking, Invoice, allocations, or audit records. Invoice amount is formatted to two decimals. Payment-proof existence is represented only by `payment_proof_present`; the storage path is not returned.

Paid verification actor/time are derived from the latest existing `organizer_verified_payment` booking audit where available. No new verification columns or schema changes were introduced.

## Allocation, EventSite, and EventDay summary

The response reuses `site_selection()`:

- allocation state: `released`
- real labels: `released_site_labels`
- EventDays: safe dates/times in `event_days`
- active day count: `active_day_count`

No EventSite operational status is changed. Availability continues to derive from `active_lock = 1`; released sites naturally return to the normal vendor availability endpoint.

## Audit timeline

Source: `booking_audit_logs` / `BookingAuditLog`.

`BookingAuditPresenter` transforms eager-loaded audit rows and actors into a deterministic oldest-to-newest timeline:

```json
{
  "audit_timeline": [{
    "action": "vendor_withdraw",
    "label": "Vendor withdrew booking",
    "previous_status": "Approved",
    "new_status": "Withdrawn",
    "actor": { "id": 10, "name": "Vendor Name", "role": "community" },
    "occurred_at": "...",
    "summary": "Vendor withdrew after submitting payment proof · No refund policy applied · Sites released"
  }]
}
```

### Audit action mapping

- `vendor_submitted_booking` → Booking submitted
- `organizer_requested_revision` → Organizer requested revision
- `vendor_resubmitted` / `vendor_resubmitted_booking` → Vendor resubmitted booking
- `organizer_approved_booking` → Booking approved
- `organizer_verified_payment` → Payment verified
- `vendor_withdraw` → Vendor withdrew booking
- `organizer_rejected_booking` → Booking rejected
- `vendor_cancel` → Booking cancelled
- vendor request actions and generic status changes receive safe labels
- unknown actions → `other` / Booking activity recorded

Raw `revision_comment`, bracketed no-refund suffixes, HTML, IP addresses, and unknown internal action names are not exposed.

## Organizer UI

`OrganizerBookingsPanel.vue` now provides:

- a Details action for queue and registry rows;
- `OrganizerWithdrawalReconciliationModal.vue`;
- withdrawn actor/time;
- payment state and Invoice amount;
- proof-presence and verification indicators;
- no-refund outcome;
- released real site labels and EventDays;
- semantic audit timeline with loading and empty states.

The modal is responsive and read-only. No refund, reversal, restoration, reallocation, or export controls were added.

## Filters

The existing server-filtered registry now supports:

- status `Withdrawn`;
- payment `Paid`;
- payment `Payment Submitted` (`Pending Verification` API value);
- payment `Unpaid`;
- no-refund applied Yes/No.

`no_refund_applied` is validated as a Boolean and always scopes results to withdrawn bookings.

## Vendor privacy boundary

`presentForVendor()` continues exposing vendor-safe status, Invoice state, withdrawal policy, no-refund outcome, and site summary. It explicitly excludes:

- `withdrawal_reconciliation`;
- `audit_timeline` / raw `audit_logs`;
- Organizer verification actor;
- payment-proof storage path;
- raw allocation rows and `active_lock`.

## Query strategy

- Registry and queue queries eager-load user/business profile, space, Invoice, event, withdrawal actor, EventSites, and EventDays.
- Presenter enrichment uses those loaded relations.
- Full `auditLogs.actor` is loaded only by selected booking detail (`GET /api/bookings/{booking}`), not for every registry row.
- This avoids one Invoice/allocation/site/day query per booking and avoids loading full audit history across paginated lists.

## Backend tests

`OrganizerWithdrawalReconciliationTest.php`: 6 tests, 57 assertions.

Coverage includes paid, payment-submitted, unpaid, safe proof Boolean, real labels/days, released status, safe audit, verification actor/time, non-mutation, Vendor privacy, Organizer/Super Admin/CMart Management/community/guest governance, and server filters.

Phase 2B.1 and allocation/availability/governance regressions also pass.

## Frontend unit tests

`organizerWithdrawalReconciliation.test.js`: 12 tests.

Coverage includes payment labels, amount/proof indicators, released sites, no-refund filters, unknown audit fallback, stable test IDs, loading/empty states, text-only rendering, forbidden controls, and Vendor-component privacy.

All frontend unit tests: 31 passed.

## Browser payment-submitted withdrawal

Fixture mode:

```bash
php artisan e2e:site-fixtures create-payment-submitted-booking --json
```

It creates a temporary community vendor, open event, two EventDays, contiguous EventSites, Approved Booking, `Pending Verification` Invoice with safe proof marker, and reserved allocations.

The real headless Chrome flow verifies:

1. Vendor sees Pending Verification, reserved sites, real labels, and exact Malay warning.
2. Acknowledgement is required.
3. Withdrawal produces `Withdrawn` + `Released`.
4. Invoice remains Pending Verification and proof remains present.
5. Sites become available.
6. Organizer opens real booking detail.
7. Reconciliation shows Payment Submitted, no refund, amount, proof presence, released labels/days.
8. Audit timeline shows `vendor_withdraw`, actor, timestamp, and transition.
9. No refund/reassignment/restoration controls exist.
10. API omits proof marker/path and `active_lock`.

Result: 1 browser test passed.

## Cleanup proof and persistent data

| Table | Baseline | During fixture | Final |
| --- | ---: | ---: | ---: |
| users | 4 | 5 | 4 |
| carboot_events | 6 | 7 | 6 |
| event_sites | 0 | 3 | 0 |
| event_days | 0 | 2 | 0 |
| spaces | 2 | 2 | 2 |
| bookings | 0 | 1 | 0 |
| invoices | 0 | 1 | 0 |
| booking_day_allocations | 0 | 4 | 0 |
| booking_audit_logs | 0 | 1 after withdrawal | 0 |
| news_posts | 1 | 1 | 1 |
| feedbacks | 5 | 5 | 5 |

Cleanup is idempotent and foreign-key-safe. Shared Space rows are preserved.

## Validation results

- Migrations: all applied; no new migration
- Organizer reconciliation: 6 passed (57 assertions)
- Withdrawal: 10 passed (72 assertions)
- Allocation lifecycle: 10 passed (44 assertions)
- Availability: 12 passed (32 assertions)
- Governance/payment group: 33 passed (85 assertions)
- Full backend: 166 passed, 2 skipped (658 assertions)
- Frontend unit: 31 passed
- Browser E2E: 1 passed
- Production build: passed
- Oxlint repository baseline: 12 pre-existing unrelated errors; 0 Phase 2B.2 errors
- Targeted ESLint: Phase 2B.2 files pass

## Known limitations and export readiness

The response is structurally suitable for a future reconciliation export, but this phase implements no CSV/PDF/report export. Payment verification actor/time are audit-derived because the Invoice schema has no dedicated verification actor columns.

## Deferred work

Not implemented: refund processing/approval, financial reversal, partial refund, site reallocation/swapping, partial withdrawal, waitlist, expiration, manual assignment, CSV/PDF export, payment redesign, pass/check-in redesign, or analytics.

## Recommended next Phase 2B slice

Phase 2B.3 should focus on an Organizer reconciliation queue or report-ready read model with explicit product requirements and pagination, without adding refund or reallocation actions.
