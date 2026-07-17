# Phase 3.8 — Organizer Category Mismatch Override and Safe Site Reassignment

## 1. Objective

Provide an explicit, auditable Organizer workflow to reassign vendor bookings to different physical sites within the same event, and—only when necessary—approve a deliberate mismatch between the vendor booking category and the assigned layout-row category. Phase 3.7 vendor enforcement remains strict; Organizer override is never a silent bypass.

## 2. Governance

| Actor | Placement inspection | Reassignment options | Site reassignment | Override details |
| ----- | -------------------: | -------------------: | ----------------: | ---------------: |
| Organizer | Allowed | Allowed | Allowed | Full |
| Super Admin | Allowed | Allowed | Allowed | Full |
| CMart Management | Denied (403) | Denied | Denied | None |
| Community vendor | Denied | Denied | Denied | Neutral notice only |
| Guest | Denied (401) | Denied | Denied | None |

Routes live under `/api/organizer/*` within `carbootOperationalRoles()` (`organizer`, `super_admin` only).

## 3. Reassignment eligibility

Allowed booking statuses: `Pending_Organizer`, `Needs_Revision`, `Approved`.

Blocked: terminal statuses (`Rejected`, `Cancelled`, `Withdrawn`), check-in, missing invoice, no active allocations, confirmed allocations, payment submitted/paid, started/inactive EventDays.

## 4. Financial invariance

- Same site count (`SITE_COUNT_CHANGE_NOT_SUPPORTED`)
- Same `space_id` and unit price (`SITE_PRICE_CHANGE_NOT_SUPPORTED`)
- Invoice amount and status unchanged
- Booking quantity unchanged
- No invoice recalculation

## 5. Target-site rules

Targets must belong to the same event, be active, have valid active categorized rows, match required Space/price, be contiguous in one row, be available on all retained EventDays, and not conflict with other bookings. Category is read from `event_layout_rows.vendor_category_id` only.

## 6. Category compatibility

| Placement | Override needed | Acknowledgement | Reason |
| --------- | --------------: | --------------: | -----: |
| Same category | No | No | No |
| Different category | Yes | `acknowledge_category_override: true` | Required (10–1000 chars) |

Booking `vendor_category_id`, `category_label_snapshot`, and `product_category` are never rewritten.

## 7. Override storage

Table: `booking_category_overrides`

- Single active row enforced via `UNIQUE (booking_id, active_lock)` where active uses `active_lock = 1`
- Status lifecycle: `active`, `revoked`, `superseded`
- Immutable snapshots for booking/assigned categories, rows, sites, reason, actor, timestamps
- `booking_id` FK: `restrictOnDelete` (history preserved; booking deletion blocked while overrides exist)
- Actor FKs: `nullOnDelete` (business record survives user deletion)

## 8. Override lifecycle

1. First mismatch → create `active` override + `organizer_category_override_applied` audit
2. Mismatch → new mismatch → supersede prior + new active override
3. Mismatch → compatible reassignment → revoke active override automatically
4. Same mismatched sites with new reason → supersede (new history row)
5. Manual revoke while still mismatched → not exposed (automatic revoke only on compatible placement)

## 9. Assignment fingerprint

SHA-256 over booking status, invoice status, active allocation IDs/statuses/site IDs, active override ID/status, and relevant `updated_at` values. Submitted on reassignment; mismatch returns `409 ASSIGNMENT_CHANGED`. Authorization is not derived from the fingerprint.

## 10. Placement endpoint

`GET /api/organizer/bookings/{booking}/category-placement`

Returns booking category, current assignment, compatibility, active override summary, history count, reassignment blockers, and fingerprint.

## 11. Options endpoint

`GET /api/organizer/bookings/{booking}/site-reassignment-options`

Returns eligible rows/sites with `category_compatible`, `override_required`, owned sites included, occupancy-safe filtering, and fingerprint.

## 12. Reassignment endpoint

`PATCH /api/organizer/bookings/{booking}/site-assignment`

Payload: `event_site_ids`, `assignment_fingerprint`, optional override acknowledgement/reason.

Returns updated organizer booking projection and `category_placement` summary.

## 13. Allocation diff behaviour

| Type | Behaviour |
| ---- | --------- |
| Unchanged day/site pairs | Preserved |
| Removed pairs | `reserved` → `released`, `release_reason = organizer_site_reassignment` |
| Added pairs | Reactivate released row if exists, else create `reserved` |
| Attendance-exception days | Not restored |
| Historical released rows | Never deleted |

## 14. Attendance-exception preservation

Reassignment applies only to EventDays represented by current active allocations. Previously released attendance-exception days are not re-added.

## 15. Audit behaviour

Actions: `organizer_site_reassignment`, `organizer_category_override_applied`, `organizer_category_override_superseded`, `organizer_category_override_revoked`.

Snapshots include categories, site/row labels, compatibility, override ID, reason, actor. No payment proof, tokens, or unnecessary PII.

## 16. Organizer UI

Integrated into `OrganizerWithdrawalReconciliationModal`:

- Placement summary (BM): Kategori Tempahan, Kategori Baris, Tapak Semasa, Status Keserasian, Pengecualian
- Action: **Susun Semula Tapak**
- Modal: compatible rows shown; mismatched rows gated behind **Tunjukkan baris kategori lain**; override warning, acknowledgement, reason; fingerprint submission; conflict recovery preserves form state

Vendor receives optional neutral `placement_exception` notice when override is active.

## 17. Error contract

Stable codes include `BOOKING_NOT_REASSIGNABLE`, `BOOKING_PAYMENT_LOCKED`, `BOOKING_ALLOCATION_CONFIRMED`, `EVENT_DAY_ALREADY_STARTED`, `SITE_COUNT_CHANGE_NOT_SUPPORTED`, `SITE_PRICE_CHANGE_NOT_SUPPORTED`, `TARGET_SITE_UNAVAILABLE`, `TARGET_SITE_SELECTION_INVALID`, `TARGET_SITE_MIXED_ROWS`, `CATEGORY_OVERRIDE_*`, `ASSIGNMENT_CHANGED`, `EVENT_LAYOUT_NOT_READY`.

Frontend BM mapping: `frontend/src/services/organizerSiteReassignmentMessages.js`.

## 18. Concurrency

Transaction lock order: Booking → Invoice → Event → allocations → target sites/rows/categories. Fingerprint prevents stale modal submissions. Target site conflicts return `409 TARGET_SITE_UNAVAILABLE`. Failed operations roll back entirely.

## 19. Authorization

Organizer/Super Admin only on all three Phase 3.8 endpoints. CMart Management and Community receive 403; guest receives 401.

## 20. Tests

Backend: `OrganizerBookingSiteReassignmentTest` (12 cases). Frontend: `organizerSiteReassignment.test.js`. Compatibility suites re-run with Phase 3.7 tests unchanged.

## 21. E2E

Blocked: `cmart_db` / `cmart_e2e_db` lack Phase 3 schema on persistent development database. Substitute validation via backend feature tests and frontend unit/build checks.

## 22. Persistent-data validation

Phase 3.8 migration applied only to `cmart_test`. `cmart_db` not migrated; `booking_category_overrides` remains MISSING before/after.

## 23. Known limitations

- No reassignment after payment
- No price/quantity/Space changes
- No vendor self-override
- No manual revoke while mismatched
- No public layout or enhanced vendor TGV UX

## 24. Phase 3.9 entry criteria

Phase 3.9 (Enhanced Vendor TGV-Style Category Site Selection UX) may proceed when:

- Organizer mismatch placement always creates durable override history
- Vendor normal booking remains strict
- Full backend and frontend suites pass on `cmart_test`
- Persistent `cmart_db` unchanged
