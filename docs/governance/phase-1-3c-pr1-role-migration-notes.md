# Phase 1.3C PR1 — Canonical Role Migration Foundation (Implementation Notes)

**Date:** 2026-07-12  
**Option chosen:** **Option B — Compatibility PR1** (no ENUM shrink)  
**Blueprint:** [`phase-1-3b-direct-organizer-workflow-diagnosis.md`](phase-1-3b-direct-organizer-workflow-diagnosis.md)  
**Test status:** Full backend suite green — **79 passed (255 assertions)** after migration.

---

## 1. What PR1 changed

| Layer | Change |
|---|---|
| **Data migration** | New `2026_07_12_000001_remap_legacy_roles_to_canonical.php`: snapshots all users into `role_migration_audit_202607`, then remaps `manager → organizer` and `uum → organizer`. `staff` is **not** remapped (PR2). ENUM **not** shrunk. |
| **`ManagementRole.php`** | Canonical constants reorganized; `normalize()` now maps `manager`, `uum`, `cmart_admin`, `boss` → `organizer`; `isOrganizerEquivalent()` = organizer + super_admin only; `matches()` organizer-authority bridge; `staff` marked TEMPORARY; `workflowRoleKey()` unchanged in behavior (organizer/super_admin still map to the legacy `manager` state-machine key until PR2). |
| **`ManagementCapability.php`** | `manager` removed from all capability allow-lists (legacy strings inherit via normalize); analytics = organizer + super_admin; `staff` retained in Carboot ops lists as documented TEMPORARY compatibility; `cmart_management` still has zero Carboot booking authority. |
| **Seeder** | `admin@cmart.com` → role `organizer`, name "Carboot Organizer (Ops)", profile title "Carboot Organizer"; `staff@cmart.com` kept as `staff` with explicit TEMPORARY comment (PR2 remaps it to `cmart_management`); no `manager`/`uum` seeds remain. |
| **`UserFactory`** | Added canonical states: `community()`, `organizer()`, `cmartManagement()`, `superAdmin()`. No legacy states added. |
| **Backend tests** | Manager-identity tests retargeted to organizer; one explicit temporary legacy bridge test added; new guard tests (see §7). |
| **Frontend helpers** | `managementRoles.js` / `managementCapabilities.js` mirror the backend: legacy identities normalize to organizer; "CMart Management" label replaces "CMart Venue Manager"; `Carboot Organizer (Legacy)` label removed. No UI components/routes touched. |

## 2 & 3. Option chosen and why

**Option B (Compatibility PR1).** Reasons:

1. The two-stage booking pipeline (`Pending_Staff → Pending_Boss`) still exists and needs a `staff`-stage actor. Removing `staff` from the ENUM or from the Carboot operational capability lists before PR2 would break `BookingStaffStageAssistTest`, `StaffOperationsSummaryTest`, `FeedbackModerationTest`, staff routes, and the staff E2E suite.
2. `BookingStaffStageAssistTest` still needs one legacy-string write path validated at the unit level (the legacy bridge test writes no DB rows; feature tests now create `organizer`).
3. Shrinking `users.role` in PR1 would strand `manager`-string inserts anywhere a stale code path or an unmigrated environment still writes them; the dual-accept window costs nothing and closes in PR2.

## 4. ENUM status

**Deferred to PR2.** `users.role` is still:

```text
enum('community','staff','manager','organizer','cmart_management','super_admin','uum')
```

Data no longer contains `manager` or `uum` (guarded by test `test_no_users_hold_legacy_manager_or_uum_roles_after_migration`). PR2 shrinks to `community | organizer | cmart_management | super_admin` after remapping `staff → cmart_management`.

Local verification after migration:

```text
roles_after: {"community":1,"staff":1,"organizer":2,"cmart_management":1,"super_admin":1}
audit_rows: 6
```

## 5. Compatibility bridges remaining (all temporary)

| Bridge | Location | Removed in |
|---|---|---|
| `staff` role + Carboot ops capability | `ManagementRole`, `ManagementCapability`, seeder, routes | **PR2** |
| `manager`/`uum`/`cmart_admin`/`boss` → organizer normalization | `normalize()` backend + frontend | PR2 (after ENUM shrink) or PR3 |
| Legacy role strings in route role lists | `carbootOperationalRoles()` etc. | PR2 |
| `workflowRoleKey()` returning legacy `'manager'` state-machine key | `ManagementRole`, `managementRoles.js` | **PR2** (state machine rewrite) |
| `manager_assisted_tier1_review` audit action label | `BookingController` | PR2 |
| Single legacy bridge test | `ManagementCapabilityTest::test_legacy_manager_and_uum_identities_normalize_to_organizer` | PR2 |
| `isManagerRole()` deprecated helpers | backend + frontend | PR3 |
| Staff Portal Assist UI, `/uum` route, staff labels | frontend | **PR3** |

## 6. What PR2 must do next

1. Booking status migration: `Pending_Staff`/`Pending_Boss` → `Pending_Organizer` (+ ENUM change, backup table `booking_status_migration_audit_202607`).
2. Rewrite `BookingController::STATE_TRANSITIONS` to the direct Organizer matrix; remove Staff Portal Assist branch and `workflowRoleKey` indirection.
3. Remap `staff → cmart_management` (data) and shrink `users.role` ENUM to the four canonical roles.
4. Remove `staff` from Carboot operational capability/route lists; restrict payment verification and pass check-in to organizer (+ super_admin).
5. Update seeder `staff@cmart.com` → `cmart_management`; delete the legacy bridge test; rewrite `BookingStaffStageAssistTest` as the direct Organizer workflow test.
6. Rename operational metric keys (`pending_staff_review` → `pending_organizer_review`).

## 7. Tests run

```bash
php artisan migrate --force        # ran the new remap migration (audit table + data remap)
php artisan test                   # FULL SUITE: 79 passed (255 assertions)
```

Covered filters explicitly: `ManagementCapabilityTest`, `GovernanceAccessBoundaryTest`, `WebAnalyticsSecurityTest`, `CommunityVendorBookingAccessTest`, `BookingStaffStageAssistTest`, `StaffOperationsSummaryTest`, `FeedbackModerationTest`, `CommunityVendorIntentTest`.

New/updated coverage:

- `test_legacy_manager_and_uum_identities_normalize_to_organizer` (the single temporary legacy bridge test)
- `test_cmart_management_never_gains_carboot_booking_authority`
- `test_no_users_hold_legacy_manager_or_uum_roles_after_migration`
- Manager-named feature tests retargeted to organizer identity (booking state machine untouched)

## 8. Risks and rollback

| Risk | Note |
|---|---|
| Rollback restores `manager`/`uum` from `role_migration_audit_202607` | `php artisan migrate:rollback --step=1`; users created after the snapshot are untouched. Rolling back **drops the audit table** — take a copy first if you need the audit long-term. |
| Users created post-snapshot cannot be "restored" | They never had legacy roles; no action needed. |
| Production `uum`/`manager` counts unknown | The migration snapshots and remaps whatever exists; run preflight counts on production before deploying. |
| E2E `E2E_MANAGER_*` credentials | `admin@cmart.com` still logs in and reaches `/admin` (now organizer) — no E2E env change required in PR1. Staff E2E specs unaffected (`staff@cmart.com` still `staff`). |
| Stale frontend localStorage with role `manager`/`uum` | `normalizeRole` maps them to organizer; next `/auth/me` refresh returns the canonical role from the migrated DB. |

## Deployability

**PR1 is safe to deploy alone.** The booking workflow, staff queue, analytics boundaries, vendor dashboard access (including pending vendors), and E2E credentials all keep working. PR2 must follow in the same release train to complete the governance cutover.
