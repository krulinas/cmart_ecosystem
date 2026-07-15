# Phase 2B.1 — Vendor Withdrawal, No-Refund Policy, and Allocation Release

## Result

**Completed — paid withdrawal and no-refund lifecycle verified.**

Vendor withdrawal now extends to paid and payment-submitted bookings under a canonical no-refund policy. Allocations release atomically, sites reopen through the normal availability endpoint, and payment/Invoice history remain preserved. Backend feature tests, frontend unit tests, and a real browser paid-withdrawal E2E flow all pass. Persistent database counts return exactly to baseline after fixture cleanup.

> A paid withdrawal is an operational withdrawal, not a financial reversal.

> Payment verification and Invoice history remain preserved after withdrawal.

> The released physical sites become available through the normal vendor availability endpoint.

---

## 1. Phase Objective

Implement the first controlled Phase 2B slice:

```
Vendor withdraws
→ Booking becomes Withdrawn
→ active allocations become Released
→ physical sites become available
→ payment and Invoice history remain unchanged
→ no refund is created
→ audit history records the withdrawal
```

---

## 2. Repository Withdrawal Path

| Layer | Location |
| ----- | -------- |
| Route | `PATCH /api/bookings/{booking}/withdraw` (`backend/routes/api.php`, `role:community` + `auth:sanctum`) |
| Controller | `BookingController::withdraw()` |
| Presenter policy | `VendorBookingPresenter::withdrawalPolicy()` / `withdrawalPaymentState()` |
| Allocation release | `BookingAllocationLifecycleService::releaseForBooking()` with `REASON_BOOKING_WITHDRAWN` (`booking_withdrawn`) |
| Audit | `BookingAuditLogger::log()` action `vendor_withdraw` |
| Vendor UI | `VendorBookingDetailsModal.vue` + `WithdrawBookingModal.vue` |
| Organizer UI | `OrganizerBookingsPanel.vue` read-only withdrawal summary |

---

## 3. Withdrawal Eligibility

### Withdrawable statuses

- `Pending_Organizer`
- `Needs_Revision`
- `Approved` (new for Phase 2B.1 — includes paid bookings)

### Terminal statuses (cannot withdraw)

- `Rejected`
- `Cancelled`
- `Withdrawn` (idempotent `200` success, no duplicate audit/release)

### Authorization

- Owning `community` vendor only
- `cmart_management` → `403`
- Unauthenticated → `401`
- Another vendor's booking → `403`

No new withdrawal deadline or event cutoff was introduced.

---

## 4. Unpaid Withdrawal (preserved)

Behavior unchanged from Phase 2A.7:

- Booking → `Withdrawn`
- Reserved allocations → `released`, `active_lock = NULL`
- Invoice/payment unchanged
- No acknowledgement required
- Simpler Malay/operational warning in UI

---

## 5. Payment-Proof-Submitted Withdrawal

When `Invoice.payment_status = 'Pending Verification'`:

- Withdrawal allowed for eligible booking statuses
- `acknowledge_no_refund: true` required (`422` if missing/false)
- Payment proof and Invoice preserved
- Allocations released with `release_reason = booking_withdrawn`
- Audit note includes `[no-refund policy applied; payment_state=payment_submitted]`

---

## 6. Verified Paid Withdrawal

When `Invoice.payment_status = 'Paid'`:

- Withdrawal allowed for `Approved` bookings (including confirmed allocations)
- `acknowledge_no_refund: true` required
- Invoice remains `Paid`, amount unchanged
- `confirmed_at` history preserved on allocation rows
- No refund record or refunded status created
- Audit records paid withdrawal and no-refund policy

---

## 7. No-Refund Rule

Withdrawal must **not**:

- Delete or mutate Invoice/payment proof
- Change `Paid` → `Unpaid` or `Refunded`
- Create refund records or negative transactions
- Zero or reduce Invoice amount

Operational status (`Withdrawn`) is separated from financial history.

---

## 8. Canonical Malay Warning

```
Anda boleh menarik diri selepas bayaran dibuat, tetapi bayaran tidak akan dipulangkan. Tapak yang telah ditempah akan dibuka semula kepada vendor lain.
```

Exposed via `withdrawal_policy.warning_message` and rendered in `WithdrawBookingModal`.

---

## 9. Payment-State Mapping

Centralized in `VendorBookingPresenter::withdrawalPaymentState()`:

| Invoice `payment_status` | Derived `payment_state` |
| ------------------------ | ----------------------- |
| `Paid` | `paid` |
| `Pending Verification` | `payment_submitted` |
| `Unpaid` or absent | `unpaid` |

Presenter block shape:

```json
{
  "withdrawal_policy": {
    "can_withdraw": true,
    "payment_state": "paid",
    "refund_allowed": false,
    "requires_no_refund_acknowledgement": true,
    "warning_message": "Anda boleh menarik diri selepas bayaran dibuat..."
  }
}
```

---

## 10. Transaction Sequence

Inside `DB::transaction()`:

1. Authorize vendor ownership
2. Validate terminal/eligibility states
3. Validate `acknowledge_no_refund` when payment submitted/paid
4. `lockForUpdate()` on booking row
5. Re-check status under lock
6. Update booking to `Withdrawn` with `withdrawn_at`, `withdrawn_by`, optional `withdrawal_reason`
7. `releaseForBooking(..., REASON_BOOKING_WITHDRAWN)`
8. Single `BookingAuditLogger::log(...)`
9. Commit and return `VendorBookingPresenter::presentForVendor()`

On failure: full rollback — booking status, allocations, payment, invoice, and audit remain unchanged.

---

## 11. Allocation Release

Reuses `BookingAllocationLifecycleService`. Per active allocation:

| Field | Outcome |
| ----- | ------- |
| `allocation_status` | `released` |
| `active_lock` | `NULL` |
| `released_at` | consistent timestamp |
| `released_by` | authenticated vendor |
| `release_reason` | `booking_withdrawn` |
| `reserved_at` / `confirmed_at` | preserved |

Rows are never deleted.

---

## 12–13. Payment and Invoice Preservation

Confirmed by `BookingWithdrawalNoRefundTest`:

- Invoice row preserved, amount unchanged
- `payment_status` remains `Paid` or `Pending Verification` as applicable
- Payment proof path preserved on Invoice
- No refund status or record created

---

## 14. Audit Behavior

- Action: `vendor_withdraw`
- One entry per successful withdrawal
- Repeated withdrawal on already-`Withdrawn` booking: idempotent `200`, no duplicate audit
- Paid/submitted note suffix: `[no-refund policy applied; payment_state=...]`
- No payment-proof contents stored in audit

---

## 15. Presenter Response

`presentForVendor()` and `presentForOrganizer()` include `withdrawal_policy`. After withdrawal, `site_selection.allocation_status` reports `released` when all allocations are released.

---

## 16. Vendor Confirmation UX

- Withdraw action shown only when `withdrawal_policy.can_withdraw === true`
- Paid/submitted: mandatory Malay warning + acknowledgement checkbox
- Unpaid: simpler warning, no refund language
- Accessible modal (`WithdrawBookingModal`), not `window.confirm`
- Confirm disabled until acknowledged (paid/submitted) or while submitting
- `PATCH /bookings/{id}/withdraw` with `{ acknowledge_no_refund: true }` when required
- Success updates modal booking state, emits `refreshed` to refresh My Bookings list
- Withdrawn display shows Released sites and no-refund notice for paid/submitted

---

## 17. Organizer Read-Only Display

`OrganizerBookingsPanel` shows compact `organizer-withdrawal-summary` for withdrawn bookings: status, payment state, no-refund where applicable, sites released. No manual refund or reassignment controls.

---

## 18. Site Availability Reopening

After withdrawal commit, `GET /api/vendor/events/{carboot_event}/site-availability` shows released sites as `available`. Occupancy authority remains `active_lock = 1`. `EventSite.operational_status` is not mutated.

---

## 19. API Error Responses

| Condition | Response |
| --------- | -------- |
| Unauthenticated | `401` |
| CMart Management / wrong owner | `403` |
| Rejected / Cancelled | `409` |
| Already Withdrawn | `200` idempotent |
| Invalid state | `409` |
| Missing `acknowledge_no_refund` (paid/submitted) | `422` |
| Allocation release conflict | `409` (transaction rollback) |

---

## 20. Backend Test Coverage

**File:** `backend/tests/Feature/BookingWithdrawalNoRefundTest.php`

| Scenario | Tests |
| -------- | ----- |
| Unpaid withdrawal + release + availability | 1 |
| Payment-submitted + acknowledgement + proof preserved | 1 |
| Paid + acknowledgement + financial preservation | 1 |
| Availability reopening after paid withdrawal | 1 |
| Idempotency | 1 |
| Terminal / authorization / 401 | 4 |
| Rollback on release failure (mocked) | 1 |

**Total:** 10 tests, 72 assertions — all passing.

Availability regression extended in existing `VendorEventSiteAvailabilityTest` (released allocation does not block).

---

## 21. Frontend Unit Coverage

**File:** `frontend/tests/unit/bookingWithdrawal.test.js`

7 tests covering Malay warning, unpaid warning, acknowledgement rules, `can_withdraw` policy, withdrawn no-refund notice, terminal booking action hiding.

---

## 22. Browser E2E Coverage

**File:** `frontend/tests/e2e/specs/vendor.withdrawal.spec.js`

Real headless Chrome flow:

1. `php artisan e2e:site-fixtures create-paid-booking --json`
2. Login as fixture vendor
3. Open paid confirmed booking details (real site labels)
4. Verify Malay no-refund warning; cancel once without mutation
5. Confirm with acknowledgement checkbox
6. Verify `Withdrawn`, `Released`, no-refund notice, payment still `Paid`
7. Verify withdraw action hidden
8. Verify API: allocation `booking_withdrawn`, site `available`
9. `cleanup` restores baseline

**Result:** 1 passing (~16s)

> Note: `vendor.booking-withdraw.spec.js` (unpaid flow via live booking form) requires a persistent event with active EventDays/EventSites in the local database. The current persistent baseline has `event_sites=0` / `event_days=0`; that spec is an environment prerequisite, not a Phase 2B.1 regression. Unpaid withdrawal is covered by backend feature tests and shares the same modal/API path.

---

## 23. Fixture Strategy

Extended `E2ESiteFixtures` command:

```bash
php artisan e2e:site-fixtures create-paid-booking --json
php artisan e2e:site-fixtures cleanup
```

Creates: vendor, bookable event, 2 EventDays, contiguous EventSites, Approved booking, Paid Invoice, confirmed allocations, marker `E2E-SITE-FIX` in `product_details`.

Reuses Phase 2A.8.1 `create` / `cleanup` infrastructure. CLI-only, test/local guarded, idempotent, failure-safe cleanup.

---

## 24. Cleanup Proof

| Table | Baseline | During fixture | Final after cleanup |
| ----- | -------: | -------------: | ------------------: |
| users | 4 | +1 temp vendor | 4 |
| carboot_events | 6 | +1 temp event | 6 |
| event_sites | 0 | +2 | 0 |
| event_days | 0 | +2 | 0 |
| spaces | 2 | 2 (shared) | 2 |
| bookings | 0 | +1 | 0 |
| invoices | 0 | +1 | 0 |
| booking_day_allocations | 0 | +4 | 0 |
| booking_audit_logs | 0 | +1+ | 0 |
| news_posts | 1 | 1 | 1 |
| feedbacks | 5 | 5 | 5 |

Final counts exactly equal baseline.

---

## 25. Lint Result

| Metric | Count |
| ------ | ----: |
| Repository-wide oxlint errors (before) | 12 |
| Repository-wide oxlint errors (after) | 12 |
| Pre-existing unrelated errors | 12 |
| Phase 2B.1 file errors | 0 |

Phase 2B.1 files: zero oxlint/eslint errors. Production `npm run build` succeeds.

---

## 26. Full Backend Suite Result

```
php artisan test → 160 passed, 2 skipped (602 assertions)
```

One intermittent ordering flake was observed once in `BookingCreationWithAllocationsTest` under a prior combined run; isolated re-runs and the final full suite both pass. Not introduced by Phase 2B.1.

---

## 27. Known Limitations

- No refund processing, partial withdrawal, site swapping, or waitlist
- Unpaid browser E2E (`vendor.booking-withdraw.spec.js`) depends on persistent EventDay/EventSite configuration
- `EnsureVendorApproved` remains dormant (Phase 2A parity unchanged)

---

## 28. Deferred Refund Behavior

Refund creation, approval, reversal, credit notes, and organizer discretionary refunds are explicitly out of scope and not implemented.

---

## 29. Next Phase 2B Recommendation

**Phase 2B.2 — Organizer operational visibility for withdrawn paid bookings** (read-only payment reconciliation view, withdrawal audit surfacing in organizer detail panel, optional export) or **Phase 2B.2 — Payment-submitted withdrawal browser E2E** using the same fixture pattern with `Pending Verification` Invoice state.

Do not begin automatically; await review.
