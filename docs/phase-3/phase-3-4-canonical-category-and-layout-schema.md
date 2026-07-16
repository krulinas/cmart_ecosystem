# Phase 3.4 — Canonical Category and Event Layout Schema

| Field | Value |
|-------|-------|
| **Status** | Complete (amended by Phase 3.4A on 2026-07-16) |
| **Date** | 2026-07-16 |
| **Depends on** | Phase 3.2 ADR (amended), Phase 3.3 isolation, Phase 3.3A classification |
| **Next** | Phase 3.5 — Organizer Layout Backend and Readiness |
| **Amendment** | [phase-3-4a-schema-integrity-corrections.md](./phase-3-4a-schema-integrity-corrections.md) |

---

## 1. Objective

Add the additive database foundation for category taxonomy and first-class event layout rows without changing Phase 2 booking, allocation, payment, withdrawal, or site-generation write paths.

---

## 2. Schema introduced

| Object | Purpose |
|--------|---------|
| `vendor_categories` | Canonical selectable taxonomy (7 rows) |
| `category_migration_audits` | Append-only mapping audit |
| `event_layout_rows` | One-category-per-row layout grouping |
| `bookings.vendor_category_id` | Nullable FK |
| `bookings.category_label_snapshot` | Nullable historical label |
| `vendor_business_profiles.vendor_category_id` | Nullable FK |
| `user_booking_preferences.vendor_category_id` | Nullable FK |
| `vendor_items.vendor_category_id` | Nullable FK |
| `event_sites.event_layout_row_id` | Nullable FK to layout row |

Legacy fields retained: `product_category`, `business_category`, `vendor_items.category`, `event_sites.row_label`.

---

## 3. Migration order

```text
2026_07_16_000001_create_vendor_categories_table
2026_07_16_000002_seed_canonical_vendor_categories
2026_07_16_000003_create_category_migration_audits_table
2026_07_16_000004_add_vendor_category_fks_and_booking_snapshot
2026_07_16_000005_backfill_vendor_category_relationships
2026_07_16_000006_create_event_layout_rows_table
2026_07_16_000007_add_event_layout_row_id_to_event_sites_table
2026_07_16_000008_backfill_event_layout_rows_from_sites
```

### Phase 3.4A additive corrections (committed history preserved)

```text
2026_07_16_000009_harden_event_site_layout_row_foreign_key
2026_07_16_000010_make_category_migration_audits_append_only
2026_07_16_000011_add_event_layout_delete_ordering_trigger
```

Support classes:

- `App\Support\Migrations\CategoryLegacyMapper`
- `App\Support\Migrations\Phase34SchemaBackfill`

---

## 4. Canonical taxonomy

| Slug | Label | Order |
|------|-------|------:|
| `pre-loved-thrift` | Pre-loved / Thrift | 1 |
| `food-beverages` | Food & Beverages | 2 |
| `clothing-apparel` | Clothing & Apparel | 3 |
| `handicrafts-art` | Handicrafts & Art | 4 |
| `electronics-gadgets` | Electronics & Gadgets | 5 |
| `household-items` | Household Items | 6 |
| `mixed-others` | Mixed / Others | 7 |

```text
Canonical category count: 7
Unknown values automatically mapped to Mixed / Others: No
```

---

## 5. Legacy mapping rules

1. Trim whitespace; collapse internal whitespace to one space.
2. Preserve case and punctuation.
3. Exact label match → mapped.
4. Approved alias only: `Others` → `Mixed / Others`.
5. Unknown / case / punctuation variants → unresolved (`vendor_category_id` NULL) + audit row.
6. Original string columns are never rewritten.

---

## 6. Unknown-value policy

Unresolved values remain NULL FKs. Final `NOT NULL` is deferred until Phase 3.7+ write-path migration and zero unresolved audits.

---

## 7. Category migration audit

Table: `category_migration_audits`

### Phase 3.4 (original)

Uniqueness: `(source_table, source_primary_key, source_column, backfill_version)`  
Reruns updated the same audit row (idempotent but **not** append-only).

### Phase 3.4A amendment (2026-07-16)

- Added `normalized_value_hash` `CHAR(64)` (SHA-256; null → `__CATEGORY_NULL__`).
- Unique: `(source_table, source_primary_key, source_column, backfill_version, normalized_value_hash)` as `category_migration_audits_append_only_unique`.
- Backfill uses `insertOrIgnore` — changed values create new immutable observations; same value reruns neither duplicate nor mutate.
- `updated_at` remains but is not mutated after insert.

See [phase-3-4a-schema-integrity-corrections.md](./phase-3-4a-schema-integrity-corrections.md).

---

## 8. Booking snapshot behaviour

When a booking maps successfully:

- `vendor_category_id` = canonical id
- `category_label_snapshot` = canonical label (e.g. `Others` → snapshot `Mixed / Others`)

Unresolved bookings keep both NULL; `product_category` unchanged.

Current `BookingController` still writes only `product_category` until Phase 3.7.

---

## 9. Event layout row structure

`event_layout_rows`: event FK, nullable `vendor_category_id`, label, slug, description, display_order, is_active, is_public, created_by/updated_by, archived_at.

Unique `(carboot_event_id, label)` and `(carboot_event_id, slug)`.

Backfilled rows have `vendor_category_id = NULL` (no category inference).

---

## 10. Event-site relationship

### Phase 3.4 (original)

`event_sites.event_layout_row_id` nullable, `nullOnDelete` (safe with event cascade).  
`row_label` remains the Phase 2 generator write source until Phase 3.5.

### Phase 3.4A amendment (2026-07-16)

- FK delete behaviour changed to **`restrictOnDelete`** — deleting a row cannot orphan/null sites.
- Empty unused rows may still be hard-deleted.
- InnoDB event-cascade sibling ordering conflict resolved by trigger `cmart_before_delete_carboot_event_layout` (delete sites, then rows, before event delete). Allocation `RESTRICT` still blocks events with history.
- Column remains nullable; `row_label` unchanged.

See [phase-3-4a-schema-integrity-corrections.md](./phase-3-4a-schema-integrity-corrections.md).

---

## 11. Row backfill rules

- Group by `carboot_event_id` + normalized `row_label`
- Display order: min site `display_order`, then min `grid_row`, then label
- Slug: `Str::slug(label)` with deterministic `-2`, `-3` suffixes on collision
- Blank/whitespace-only `row_label` → unresolved site (no invented row)
- Same label on different events → separate rows

---

## 12. Transitional nullable fields

All new operational FKs and `category_label_snapshot` remain nullable in Phase 3.4.

---

## 13. Compatibility guarantees

- Site generation (`EventSiteLayoutGenerator`) unchanged
- Booking create/reserve/lifecycle/withdrawal unchanged
- Legacy strings and `row_label` preserved
- Full suite (Phase 3.4): 191 passed, 22 skipped, 0 failed
- Full suite (Phase 3.4A): 203 passed, 22 skipped, 0 failed, 954 assertions

---

## 14. Rollback behaviour

Rolling back the eight Phase 3.4 migrations on `cmart_test` drops new tables/columns and re-seeds cleanly on re-migrate. Legacy string columns are never modified by rollback.

Phase 3.4A corrections roll back independently (`000011` → `000010` → `000009`). `000010` down collapses multi-hash history before restoring the original unique key.

---

## 15. Test strategy

- Unit: `CategoryLegacyMapperTest`
- Feature: `Phase34CategoryAndLayoutSchemaTest`, `Phase34ASchemaIntegrityTest`
- Compatibility: existing layout/allocation/booking/withdrawal/governance suites
- Guard: Phase 3.3 `TestingDatabaseGuard` remains active

---

## 16. Persistent-data validation

`cmart_db` counts before and after Phase 3.4 / 3.4A work: **unchanged**. Phase 3.4 / 3.4A migrations were never applied to `cmart_db`.

---

## 17. Known limitations

- Empty `cmart_test` has no legacy category rows to backfill until fixtures create them (covered by controlled tests).
- Shared `spaces` catalogue `firstOrCreate` price conflicts across older tests; Creation/Availability tests now normalize Standard price to 30.
- Skip debt (22) remains for Phase 3.11.
- Site→row FK nullability and row archive/lock remain for Phase 3.5+.

---

## 18. Phase 3.5 entry criteria

| Requirement | Status |
|-------------|--------|
| Seven canonical categories | Met |
| Unknown mapping audited, not silent | Met |
| Layout rows + site FK exist (nullable) | Met |
| Booking snapshot fields exist (nullable) | Met |
| Phase 2 write paths still work | Met |
| Test isolation intact | Met |
| Dev DB untouched | Met |
| Restrictive site→row FK (3.4A) | Met |
| Append-only category audits (3.4A) | Met |

Phase 3.5 may implement Organizer layout APIs, readiness, locking, and row-aware site generation — still without vendor eligibility enforcement (Phase 3.7) or public layout (Phase 3.10).
