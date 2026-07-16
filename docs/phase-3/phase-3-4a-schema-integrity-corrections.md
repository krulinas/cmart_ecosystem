# Phase 3.4A — Schema Integrity Corrections

| Field | Value |
|-------|-------|
| **Status** | Complete |
| **Date** | 2026-07-16 |
| **Depends on** | Phase 3.4 Canonical Category and Layout Schema |
| **Next** | Phase 3.5 — Organizer Layout Backend and Readiness |

---

## 1. Objective

Harden two Phase 3.4 schema contracts before Organizer layout APIs write production layout data:

1. Prevent hard-delete of `event_layout_rows` while physical `event_sites` still reference them.
2. Make `category_migration_audits` genuinely append-only while preserving idempotent backfill reruns.

---

## 2. Original integrity risks

### Event site → layout row FK

Phase 3.4 used `event_sites.event_layout_row_id` → `nullOnDelete`. Deleting a layout row nullified site FKs and orphaned physical sites from the Event → Row → Site structure.

### Category migration audits

Uniqueness was `(source_table, source_primary_key, source_column, backfill_version)`. Reruns **updated** the same audit row, so a changed legacy value (e.g. `Food & Drinks` → `Food & Beverages`) overwrote the original observation.

---

## 3. Row deletion contract

| Scenario | Behaviour |
|----------|-----------|
| Empty unused row | Hard-delete allowed |
| Row with one or more sites | Hard-delete rejected (`RESTRICT`) |
| Site relationship after rejected delete | Preserved (not nulled) |
| Sites themselves | Not deleted by row delete attempt |
| Site reassignment to another valid row | Allowed via fixture/direct update |
| Rows with allocation history | Cannot delete (sites blocked by row RESTRICT; allocations separately protect sites) |

`event_layout_row_id` remains **nullable** for Phase 3.4 compatibility. No site-level category column was added.

---

## 4. Event deletion compatibility

### Conflict

Both `event_sites` and `event_layout_rows` cascade from `carboot_events`. With site→row `RESTRICT`, InnoDB sibling CASCADE can attempt to delete rows while sites still reference them (`SQLSTATE 1451` on `event_sites_event_layout_row_id_foreign`).

### Resolution (schema-level, not application services)

Additive migration `2026_07_16_000011_add_event_layout_delete_ordering_trigger` installs:

```text
BEFORE DELETE ON carboot_events
→ DELETE event_sites for the event
→ DELETE event_layout_rows for the event
```

Allocation history still blocks site deletion via `booking_day_allocations.event_site_id` `RESTRICT`, so events with allocation history remain protected. Empty-row and unallocated site events delete successfully.

**Do not** revert to `nullOnDelete` to paper over this conflict.

---

## 5. Append-only audit contract

After insert, normal backfill must not mutate:

```text
source_table, source_primary_key, source_column,
original_value, normalized_value, normalized_value_hash,
mapping_status, matched_vendor_category_id, reason_code,
backfill_version, metadata, created_at
```

`updated_at` remains on the table for Laravel timestamps compatibility but is set **once on insert** and is not updated by backfill reruns. Audit rows are treated as immutable.

---

## 6. Hash calculation

| Item | Definition |
|------|------------|
| Column | `normalized_value_hash` `CHAR(64) NOT NULL` |
| Algorithm | SHA-256 hex of normalized value |
| Null sentinel | `__CATEGORY_NULL__` (constant `CategoryLegacyMapper::NULL_HASH_SENTINEL`) |
| Helper | `CategoryLegacyMapper::normalizedValueHash(?string)` |

Human-readable `original_value` and `normalized_value` are retained for investigation.

---

## 7. Unique constraint

```text
category_migration_audits_append_only_unique
(source_table, source_primary_key, source_column, backfill_version, normalized_value_hash)
```

---

## 8. Idempotency behaviour

`Phase34SchemaBackfill::writeAudit` uses `insertOrIgnore` keyed by the append-only unique identity.

- Same value + same version → no duplicate, no mutation
- Changed value → new row; prior row preserved
- New `backfill_version` → new row; prior version preserved

---

## 9. Changed-value history behaviour

Example: unresolved `Food & Drinks` then corrected to `Food & Beverages` yields **two** audit rows. Original unresolved observation remains. Booking `product_category` legacy string is never rewritten by audit insertion.

---

## 10. Migration approach

Phase 3.4 migrations `000001`–`000008` are **committed** (`7818ae0`) and applied on `cmart_test`. They were **not** rewritten.

Additive corrections on `cmart_test` only:

```text
2026_07_16_000009_harden_event_site_layout_row_foreign_key
2026_07_16_000010_make_category_migration_audits_append_only
2026_07_16_000011_add_event_layout_delete_ordering_trigger
```

`cmart_db` was never migrated.

---

## 11. Rollback

| Migration | Down behaviour |
|-----------|----------------|
| `000011` | Drop ordering trigger |
| `000010` | Collapse duplicate append-only history to earliest row per legacy key, drop hash, restore pre-3.4A unique |
| `000009` | Restore `nullOnDelete` on site→row FK |

Validated: rollback step=3 → re-forward → integrity tests pass. Fresh migrate on `cmart_test` includes corrections.

---

## 12. Test evidence

| Suite | Result |
|-------|--------|
| `Phase34ASchemaIntegrityTest` | Pass |
| `Phase34CategoryAndLayoutSchemaTest` | Pass |
| `CategoryLegacyMapperTest` | Pass (incl. hash) |
| Phase 2 layout/booking/allocation/withdrawal/governance | Pass |
| Full `php artisan test` | **203 passed**, **22 skipped**, **954 assertions**, exit 0 |

Skipped count unchanged (Phase 3.3A fixture debt).

---

## 13. Development database verification

`cmart_db` before and after:

| Table | Before | After | Diff |
|-------|-------:|------:|-----:|
| users | 4 | 4 | 0 |
| carboot_events | 6 | 6 | 0 |
| spaces | 2 | 2 | 0 |
| event_sites | 0 | 0 | 0 |
| event_days | 0 | 0 | 0 |
| bookings | 0 | 0 | 0 |
| booking_day_allocations | 0 | 0 | 0 |
| invoices | 0 | 0 | 0 |
| booking_audit_logs | 0 | 0 | 0 |
| vendor_business_profiles | 1 | 1 | 0 |
| vendor_items | 1 | 1 | 0 |
| user_booking_preferences | 1 | 1 | 0 |
| vendor_categories | MISSING | MISSING | 0 |
| event_layout_rows | MISSING | MISSING | 0 |
| category_migration_audits | MISSING | MISSING | 0 |

Development database mutated: **No**

---

## 14. Remaining limitations

- Site→row FK remains nullable until Phase 3.5+ readiness enforces assignment.
- Row archive/lock behaviour is Phase 3.5.
- `000010` down collapses multi-observation history (documented; irreversible for those collapsed rows).
- Shared Space catalogue price normalization remains Phase 3.11 test debt (does not change production pricing).
- 22 skipped seed-demo tests remain Phase 3.11 debt.

---

## 15. Phase 3.5 entry gate

| Requirement | Status |
|-------------|--------|
| Row FK restrictive | Met |
| Row delete cannot orphan sites | Met |
| Audit append-only | Met |
| Same-value rerun idempotent | Met |
| Changed values preserve history | Met |
| Phase 3.4 compatibility | Met |
| Full backend suite passes | Met |
| Development DB unchanged | Met |
| TestingDatabaseGuard active | Met |

**Phase 3.5 may proceed.** Do not implement eligibility, public layout, or frontend in 3.5 beyond Organizer layout backend + readiness/locking as scoped.
