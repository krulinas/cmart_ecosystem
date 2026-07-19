# Phase 3.11 — Hardening, Migration Readiness and Closure

## 1. Objective

Phase 3.11 closes the category-based event-layout programme without adding Phase 4 product scope. It hardens migration rollback, proves isolated fresh and legacy upgrades, removes avoidable test skips and lint debt, adds a read-only deployment preflight, and exercises the complete cross-role journey.

## 2. Repository continuity

- Branch: `main`, tracking `origin/main`, no ahead/behind divergence at start.
- Starting commit: `f542072 Update Public Category Layout and Visitor Navigation`.
- Earlier Phase 3 milestones present: `3592cd4`, `ce58f6e`, `7818ae0`, `6890d6e`, `7f03c4d`, `f542072`.
- Starting tree: clean; no untracked files or merge conflicts.
- No reset, clean, branch switch, commit, push, or history rewrite was performed.

## 3. Capability inventory

| Capability | Implemented | Tested | Main evidence |
|---|---:|---:|---|
| Canonical categories and backfill | Yes | Yes | `Phase34CategoryAndLayoutSchemaTest`, legacy upgrade simulation |
| Category layout rows and physical sites | Yes | Yes | Organizer layout and Phase 3.11 E2E suites |
| Readiness and publication | Yes | Yes | `PublicEventLayoutTest`, Phase 3.10/3.11 E2E |
| Canonical booking FK/snapshot | Yes | Yes | `Phase37VendorCategoryEligibilityTest`, Phase 3.11 E2E |
| Category-aware booking enforcement | Yes | Yes | Phase 3.7 and booking allocation suites |
| Organizer reassignment and override | Yes | Yes | `OrganizerBookingSiteReassignmentTest`, Phase 3.11 E2E |
| Public privacy projection | Yes | Yes | `PublicEventLayoutTest`, Phase 3.10/3.11 E2E |
| Withdrawal compatibility | Yes | Yes | `BookingWithdrawalNoRefundTest`, Phase 3.11 E2E |

## 4. Phase 3.4A rollback correction

The previous `down()` deleted all but the earliest observation in every colliding append-only audit group. The final guarded contract queries for legacy-key collisions before any schema operation. A collision throws `RuntimeException`; rows and the append-only unique index remain unchanged. With no collision, rollback restores the legacy index without deleting rows, and re-forward succeeds.

`Phase34SchemaBackfill::writeAudit()` now performs a normal insert. It suppresses only SQLSTATE `23000` with MySQL/MariaDB error `1062`, and only after confirming that the exact unique observation exists. Truncation, FK, nullability, schema, and connection failures are rethrown. The helper supports both the pre-3.4A schema and the later hash schema, fixing a zero-to-current migration-chain defect discovered by the legacy simulation.

Focused result: 13 tests passed, 64 assertions.

## 5. Skipped-test results

All 19 observed skips were demo-seed dependencies and therefore avoidable.

| Test/file | Previous condition | Root cause | Final action |
|---|---|---|---|
| `WebAnalyticsSecurityTest` (1 observed) | seeded management account absent | Missing deterministic fixture | `TracksProvisionedUsers` |
| `FeedbackModerationTest` (11) | seeded vendor/Organizer/management absent | Missing deterministic fixture | role-specific provisioned users |
| `CommunityVendorIntentTest` (2) | seeded vendor/management absent | Missing deterministic fixture | local tracked users |
| `MarketplacePublicAccessTest` (1) | seeded vendor absent | Missing deterministic fixture | existing `createVendor()` |
| `StaffOperationsSummaryTest` (3 observed) | seeded Organizer/management absent | Missing deterministic fixture | `TracksProvisionedUsers` |
| `GovernanceAccessBoundaryTest` (1 observed) | seeded management absent | Missing deterministic fixture | provisioned role fixture |

Final full suite: 0 skipped. No test was deleted or weakened.

## 6. Lint results

- Starting visible Oxlint debt: 12 unused imports, parameters, and caught errors.
- After Oxlint passed, ESLint exposed additional latent environment and source errors that had previously been masked by the sequential script.
- Safe fixes removed unused values, declared Node/Mocha globals only for applicable E2E files, and assigned a multi-word component name.
- Final `npm run lint`: Oxlint 0 errors; ESLint 0 errors; exit 0.

## 7. Data-integrity audit

Final isolated preflight counts:

| Invariant | `cmart_test` | `cmart_e2e_db` | Status |
|---|---:|---:|---|
| Canonical FK null + recognized legacy | 0 | 0 | Pass |
| Canonical FK null + unknown legacy | 0 | 0 | Pass |
| FK/legacy mismatch | 0 | 0 | Pass |
| Booking snapshot missing | 0 | 0 | Pass |
| Booking snapshot inconsistent | 0 | 0 | Pass |
| Active site missing row | 0 | 0 | Pass |
| Site/row event mismatch | 0 | 0 | Pass |
| Active row missing category | 0 | 0 | Pass |
| Inactive/archived category used operationally | 0 | 0 | Pass |
| Published layout not public-ready | 0 | 0 | Pass |
| Active override inconsistent with placement | 0 | 0 | Pass |
| Multiple active overrides | 0 | 0 | Pass |
| Active allocation with release metadata | 0 | 0 | Pass |
| Released allocation with `active_lock=1` | 0 | 0 | Pass |

`cmart_db` has not received Phase 3, so Phase 3 invariants are reported as unavailable rather than falsely reported as zero.

## 8. Legacy mirror status

| Field | Current dependency | Decision |
|---|---|---|
| `bookings.product_category` | API compatibility, search, analytics, passes, presenters | Still required |
| `vendor_business_profiles.business_category` | profile/auth/marketplace/analytics responses | Still required |
| `user_booking_preferences.product_category` | saved-booking API and checkout hydration | Still required; canonical write completion is debt |
| `vendor_items.category` | item validation, search, marketplace display | Still required; canonical write completion is debt |
| `event_sites.row_label` | legacy site APIs, generators, display, compatibility validation | Still required |

No legacy column was removed or stopped from being written.

## 9. NOT NULL readiness

| Column | Isolated null count | Decision |
|---|---:|---|
| `bookings.vendor_category_id` | 0 | Ready for new writes; deferred until `cmart_db` upgrade audit |
| `vendor_business_profiles.vendor_category_id` | 0 | Ready for new writes; deferred until `cmart_db` upgrade audit |
| `event_sites.event_layout_row_id` | 0 active | Blocked for historical/unresolved sites |
| `event_layout_rows.vendor_category_id` | 0 active | Blocked for deterministic legacy rows requiring classification |

No `NOT NULL` constraint was added. Historical compatibility and pre-rollout evidence do not yet satisfy the closure criteria for irreversible hardening.

## 10. Trigger readiness

- Runtime engine: MariaDB `10.4.32`.
- Trigger: `cmart_before_delete_carboot_event_layout`.
- `CREATE TRIGGER` privilege: present for the tested account.
- Definition verified in `information_schema.TRIGGERS`.
- Behavior tested: unallocated event deletion succeeds; allocation history blocks destructive deletion.
- SQL uses MySQL/MariaDB-compatible `BEFORE DELETE ... FOR EACH ROW`.
- Raw trigger deletes bypass Eloquent model events; this is intentional database ordering behavior.
- Rollback drops only the trigger. Deployments must confirm trigger privilege before migration.

## 11. Preflight command

`php artisan phase3:preflight --json`

The default mode is read-only. It reports environment/database, engine/version, pending migrations, trigger privilege/presence/definition, canonical category and unresolved-audit counts, all closure invariants, and rollout readiness. It has no repair or migration mode.

## 12. Fresh migration

Both `cmart_test` and `cmart_e2e_db` were dropped and rebuilt from the complete migration chain. All 61 migrations ran, the trigger was created, 7 categories were seeded, no migration remained pending, and preflight reported 0 integrity violations.

## 13. Legacy upgrade simulation

`Phase3LegacyUpgradeTest` creates only `cmart_phase3_upgrade_test`, guarded by `Phase3UpgradeDatabaseGuard`. It migrates to the pre-Phase-3 checkpoint, seeds exact, `Others`, unknown, and legacy row/site data, then runs the remaining chain.

- Exact values canonicalized.
- `Others` mapped to `Mixed / Others`.
- Unknown values remained null and were audited as `unresolved`.
- Unknown values never became `Mixed / Others`.
- Changed unknown observations appended; reruns were idempotent.
- Legacy row/site linking was deterministic.
- No seeded record disappeared.
- Result: 1 passed, 20 assertions.
- Final fixture residue: zero; the seven canonical categories and migrated schema remain.

## 14. Cross-role E2E

`npm run test:e2e:phase311`: 7 passed, 0 failed. It covers publication, canonical vendor booking, compatible reassignment, mismatch acknowledgement/override, guest privacy, withdrawal with unchanged public map, and authorization boundaries.

The suite found and corrected a presenter defect where superseded reassignment allocations inflated current site quantity. Current selection now excludes allocations released by reassignment while preserving history elsewhere.

## 15. Full regression

- Backend: 320 passed, 0 failed, 0 skipped, 1570 assertions, 465.75 seconds.
- Frontend unit: 80 passed, 0 failed, 0 skipped.
- Lint: Oxlint and ESLint passed.
- Build: passed; one non-blocking chunk-size warning.
- Phase 3.9 E2E: 5 passed.
- Phase 3.10 E2E: 6 passed.
- Phase 3.11 E2E: 7 passed.

## 16. Persistent database validation

- `cmart_test`: migrated, 7 canonical categories, 0 fixture-controlled rows, 0 invariant violations after cleanup proof.
- `cmart_e2e_db`: migrated, 7 canonical categories, 0 E2E residue, 0 invariant violations.
- `cmart_phase3_upgrade_test`: migrated, 7 canonical categories, 0 fixture residue.
- `cmart_db`: unchanged; 4 users, 1 profile, 1 preference, 1 item, 6 events, 0 sites, 2 spaces, 0 bookings. Phase 3 tables remain absent and 14 migrations remain pending.

## 17. Rollout readiness

Rollout readiness: Prepared. Development migration executed: No.

`cmart_db` preflight confirms MariaDB 10.4.32 and trigger privilege, but correctly reports Phase 3 schema absent, trigger absent, and 14 pending migrations. Backup and controlled migration remain operator actions governed by the rollout runbook.

## 18. Remaining debt

- Legacy preference and vendor-item write paths do not yet make their nullable canonical FK authoritative.
- Legacy mirrors remain active compatibility contracts.
- Historical row/category nullability requires real-data classification after rollout preflight.
- Frontend main bundle remains above the 500 kB advisory threshold.
- PHPUnit XML uses a deprecated schema.
- Node warns about `shell: true` argument handling in E2E fixture subprocesses.

## 19. Phase 3 closure decision

Phase 3 implementation is migration-safe, privacy-safe, regression-safe, testable, and documented in isolated environments. Phase 3 product work is closed. Controlled deployment to `cmart_db` remains deliberately unexecuted and requires the runbook approval gate.

## 20. Phase 4 entry criteria

Phase 4 may begin only as a separate change set. It must not be combined with the Phase 3 migration rollout, and it must retain canonical category enforcement, Organizer governance, append-only override/audit history, isolated databases, and the public privacy boundary.
