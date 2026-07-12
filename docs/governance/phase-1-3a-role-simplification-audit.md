    # Phase 1.3A — Role Simplification Audit & Legacy Mapping Diagnosis

**Status:** Audit only — no code, ENUM, seed, test, route, or data changes performed.  
**Date:** 2026-07-11  
**Scope:** Carboot@CMart Digital Platform (Laravel backend + Vue SPA)  
**Target direction:** Simplify toward `community | organizer | cmart_management | super_admin`

---

## 1. Executive summary

The codebase already has a Phase 1.2 capability layer (`ManagementCapability` + `EnsureCapability`) that correctly separates **Organizer-owned Carboot analytics** from **CMart Management generated reports**. The remaining problem is **role identity debt**, not missing analytics locks.

| Finding | Severity | Verdict |
|---|---|---|
| `organizer` and `cmart_management` are real DB roles with correct capability boundaries | Low | Keep as canonical |
| `manager` is a full Organizer bridge (`admin@cmart.com` still uses it) | High (migration risk) | Migrate → `organizer`, then remove |
| `uum` is in the ENUM and has a stub UI route, but is **not** wired into management helpers | Medium (orphan) | Migrate → `organizer` (or remove if confirmed unused) |
| Live local DB has **0** `uum` users | Info | Safe to migrate/remove after prod confirmation |
| `staff` is deeply embedded in the two-stage booking pipeline (`Pending_Staff` → `Pending_Boss`) | High | Keep temporarily (Option A); do not merge into `cmart_management` yet |
| `admin` is **not** a DB role — it is a route/workspace label (`/admin`, `admin@cmart.com`) | Low | Keep as portal naming only |
| Vendor identity is `community` + `vendor_status` (+ business profile), not a separate role | Low | Keep |
| E2E suite and many feature tests still depend on `manager` + `staff` | High (test plan) | Update in Phase 1.3B after data migration |

**Recommended final model (after safe migration):**

1. `organizer` — canonical UUM / Carboot Organizer  
2. `cmart_management` — venue-side activities + generated reports  
3. `community` — public/vendor side  
4. `super_admin` — reserved technical/HQ only  

**Deferred (not removed in first migration pass):**

- `staff` — keep until booking workflow is redesigned or capability-gated into organizer desk  
- Temporary alias/bridge for `manager` during rollout is acceptable, but data should move to `organizer`

**No critical unauthenticated internal route was found** for analytics. `/admin/analytics` and `/api/proxy/analytics/*` are behind `auth:sanctum` + `boss` (organizer-equivalent analytics gate). Report any production `uum` count before ENUM shrink.

---

## 2. Current role inventory

### 2.1 Database ENUM (verified live)

```text
enum('community','staff','manager','organizer','cmart_management','super_admin','uum')
```

Source: live MySQL column `users.role` + migration `2026_07_09_000001_add_organizer_and_cmart_management_roles.php`.

### 2.2 Live local user counts (read-only aggregate)

| Role | Count (local) |
|---|---|
| `community` | 1 |
| `staff` | 1 |
| `manager` | 1 |
| `organizer` | 1 |
| `cmart_management` | 1 |
| `super_admin` | 1 |
| `uum` | **0** |

**Assumption:** This is the local/dev database. Production must be re-checked before any ENUM removal. See open questions.

### 2.3 Canonical helper constants

**Present in `ManagementRole` / frontend `ROLES`:**

- `staff`, `manager`, `organizer`, `cmart_management`, `super_admin`
- Legacy string aliases (normalized, not ENUM values today): `cmart_staff`, `cmart_admin`, `boss`

**Absent from `ManagementRole` helpers but present in ENUM + frontend routing:**

- `uum` — **not** normalized, **not** in `isManagementUser()`, **not** in capability maps

### 2.4 Key files touching roles

| Area | Files |
|---|---|
| Role helpers | `backend/app/Support/ManagementRole.php`, `frontend/src/utils/managementRoles.js` |
| Capabilities | `backend/app/Support/ManagementCapability.php`, `frontend/src/utils/managementCapabilities.js` |
| Middleware | `EnsureRole`, `EnsureCapability`, `EnsureBossOnly`, `EnsureVendorApproved`, `Authenticate` |
| Kernel aliases | `role`, `boss`, `manager`→`EnsureBossOnly`, `capability`, `vendor.approved` |
| Auth payload | `UserAuthPresenter` (`governance_capabilities`, `maps_to_future_organizer`) |
| Routes | `backend/routes/api.php`, `backend/routes/web.php` |
| Migrations | `2014_10_12_000000`, `2026_05_12_000001`, `2026_06_15_000001`, `2026_07_09_000001` |
| Seeder | `DatabaseSeeder.php` (no `uum` account) |
| Frontend workspace | `AdminDashboard.vue`, `workspaceNav.js`, `useManagementAccess.js`, `bossPreview.js` |
| Orphan UUM UI | `UumDashboard.vue`, `/uum` route, `auth.homeForUser()` / redirect allowlist |
| Tests | `GovernanceAccessBoundaryTest`, `WebAnalyticsSecurityTest`, `ManagementCapabilityTest`, `BookingStaffStageAssistTest`, E2E manager/staff specs |

### 2.5 Legacy role history (high level)

| Era | ENUM |
|---|---|
| Original create migration (current file state) | `community, cmart_staff, cmart_admin, uum` |
| Pre-Carboot (from `2026_05_12` down) | `Vendor, Admin, Community` |
| Phase RBAC normalize (`2026_05_12`) | `community, cmart_staff, cmart_admin, uum` |
| Standardize names (`2026_06_15`) | `community, staff, manager, super_admin, uum` |
| Phase 1.2 add roles (`2026_07_09`) | + `organizer`, `cmart_management` |

**`uum` predates Phase 1.2** and has been carried forward through every ENUM rewrite without being integrated into management capability helpers.

---

## 3. Current role behavior matrix

Legend: **Y** = yes, **N** = no, **P** = partial / conditional, **—** = not applicable / stub.

| Capability / question | `community` | `staff` | `manager` | `organizer` | `cmart_management` | `super_admin` | `uum` | `admin` (label) |
|---|---|---|---|---|---|---|---|---|
| Log into management portal (`/admin`, `/management/login`) | N | Y | Y | Y | Y | Y | N (redirects to `/uum`) | N/A (not a role) |
| Access vendor/community portal (`/dashboard`, vendor APIs) | Y | N | N | N | N | N | N | N/A |
| Carboot operations (bookings, events, feedback ops APIs) | N | Y | Y | Y | N | Y | N | N/A |
| Approve/reject bookings (final `Pending_Boss` → Approved) | N | N | Y | Y | N | Y | N | N/A |
| Staff queue (`Pending_Staff` forward) | N | Y | Y (assist) | Y (assist) | N | Y (assist) | N | N/A |
| Approve/manage vendors as a dedicated admin flow | N | N* | N* | N* | N* | N* | N | N/A |
| Manage Carboot events CRUD | N | Y | Y | Y | N | Y | N | N/A |
| Manage CMart news/activities | N | Y | Y | Y | Y | Y | N | N/A |
| Raw Carboot analytics (`boss` / proxy) | N | N | Y | Y | N | Y | N | N/A |
| Generated reports (`/api/management/reports/*`) | N | N | Y | Y | Y | Y | N | N/A |
| System/super-admin reserved areas | N | N | N | N | N | Y (same ops as organizer today) | Stub only | N/A |
| Used in seeders | Y | Y | Y (`admin@cmart.com`) | Y | Y | Y | **N** | email only |
| Used in backend tests | Y | Y | Y | Y | Y | Y | **N** | email only |
| Used in frontend labels/nav | Vendor UI | Heavy | Legacy Organizer | Organizer | Venue Manager | Reserved HQ | UUM Oversight stub | `/admin` workspace |
| Stored in DB ENUM | Y | Y | Y | Y | Y | Y | Y | **N** |
| Needed long-term | Y | **Review** | **No** (bridge) | Y | Y | Y | **No** (alias of Organizer) | Portal path only |

\*Vendor “approval” today is primarily **booking approval**, not a separate vendor-account admin UI. `vendor_status` exists on `users`; `EnsureVendorApproved` is registered but **intentionally not applied** so pending vendors keep `/dashboard` access.

### Capability resolution (Phase 1.2 source of truth)

| Role | `staff_queue_assist` | `carboot_operations` | `cmart_activity_management` | `carboot_operational_analytics` | `generated_reports` |
|---|---|---|---|---|---|
| `staff` | Y | Y | Y | N | N |
| `manager` | Y | Y | Y | Y | Y |
| `organizer` | Y | Y | Y | Y | Y |
| `cmart_management` | N | N | Y | N | Y |
| `super_admin` | Y | Y | Y | Y | Y |
| `uum` | N (not management user) | N | N | N | N |
| `community` | N | N | N | N | N |

---

## 4. Role overlap diagnosis

### A. `uum` vs `organizer`

| Question | Finding |
|---|---|
| Functionally the same? | **No in code today.** `organizer` is a full management/Carboot role. `uum` is an orphan ENUM value with a read-only stub page (`UumDashboard.vue`) and no API management access. |
| Treated differently in capability helpers? | **Yes.** `uum` is ignored by `ManagementRole::normalize()` / `isManagementUser()` / all capability checks. |
| Both present in DB ENUM? | **Yes.** |
| Any users currently using `uum`? | **Local: 0.** Production unknown — must confirm. |
| Would migrating `uum` → `organizer` break anything? | **Code breakage risk: low** if zero users. Migrated users would **gain** full Organizer powers (intended for UUM = Organizer). Stub `/uum` route would become unused and should later redirect to `/admin`. |

**Recommendation:** Migrate `uum` → `organizer`, then remove from ENUM after confirmation. Do not keep both long-term.

### B. `manager` vs `organizer`

| Question | Finding |
|---|---|
| Exact access `manager` still has? | Full Organizer-equivalent: Carboot ops, final booking approval, raw analytics, generated reports, space CRUD (boss routes), Staff Portal Assist. |
| Just legacy Organizer access? | **Yes** — explicitly documented as transitional bridge (`mapsToFutureOrganizer`, labels “Carboot Organizer (Legacy)”). |
| Tests/seeders depend on `manager`? | **Heavily.** Seeder `admin@cmart.com` = `manager`. Feature + E2E suites use manager credentials and role strings. |
| Does `admin@cmart.com` still use `manager`? | **Yes** (seeder + tests). |
| What breaks if removed without migration? | Login still works only if role remains valid ENUM; removing without rewriting users fails ENUM write. Code that creates `role => manager` in tests breaks. E2E `E2E_MANAGER_*` accounts fail if role no longer grants Organizer powers. |

**Recommendation:** Data-migrate `manager` → `organizer`, keep temporary `matches()`/normalize bridge for one release if needed, then remove.

### C. `staff` vs `cmart_management`

| Question | Finding |
|---|---|
| Truly needed as separate role? | **Yes for now.** Booking pipeline is two-stage and role-keyed: staff may only act on `Pending_Staff`; final approve is manager/organizer/super_admin via `workflowRoleKey()`. |
| Staff queue/assist still required? | **Yes.** Controllers, UI (`StaffBookingsPanel`), E2E Phase 3/4A, and `BookingStaffStageAssistTest` all depend on it. Organizer “Staff Portal Assist” preview also assumes a staff view. |
| Can staff merge into `cmart_management` safely? | **Not safely today.** `cmart_management` is denied `carboot_operations` (correct for venue-side). Merging would either (a) grant venue managers booking power (wrong), or (b) strip staff of queue access (breaks operations). |
| What must change first? | Either redesign to single-stage Organizer approval, or introduce capability/tier flags **within** Organizer (or a subordinate ops capability) without using `cmart_management`. |

**Recommendation: Option A** — keep `staff` temporarily as a Carboot operations sub-role (not under CMart Management). Revisit merge only after workflow redesign. Do **not** migrate `staff` → `cmart_management` in Phase 1.3B.

### D. `admin`

| Question | Finding |
|---|---|
| Actual DB role? | **No** (not in current ENUM). Historical `Admin` / `cmart_admin` were migrated away. |
| Route/workspace naming? | **Yes** — `/admin`, `AdminDashboard.vue`, email `admin@cmart.com`, “Admin Management Mode” copy for super_admin. |
| Confusing labels? | **Yes.** Stakeholders may think “Admin” is a role. Prefer “management workspace” / “Organizer workspace” wording later. |

### E. `community` vs internal users

| Question | Finding |
|---|---|
| One `users` table? | **Yes.** Public and internal users share `users` with `role` discriminator. |
| Vendor identity? | `role = community` + `vendor_status` (`none|pending|approved|suspended`) + optional `vendor_business_profiles` + intent signals (`CommunityVendorIntent`). |
| Simplest safe model? | Keep single table. Do not invent a `vendor` DB role. Keep pending vendors on `/dashboard` (`EnsureVendorApproved` remains unused until product intentionally gates features). |

---

## 5. Database and migration findings

### Current exact ENUM

```sql
ENUM('community','staff','manager','organizer','cmart_management','super_admin','uum')
```

### Which migration introduced `organizer` and `cmart_management`?

`backend/database/migrations/2026_07_09_000001_add_organizer_and_cmart_management_roles.php`

Down migration remaps: `organizer`→`manager`, `cmart_management`→`staff`.

### Was `uum` present before Phase 1.2?

**Yes** — present since at least the Carboot RBAC rewrite (`2026_05_12`) and carried through `2026_06_15`.

### Proposed safe migration path (plan only — do not execute in 1.3A)

**Phase 1.3B-1 — data remap (keep ENUM values):**

```sql
UPDATE users SET role = 'organizer' WHERE role IN ('manager', 'uum');
-- staff: DO NOT remap in this pass
```

**Phase 1.3B-2 — after code no longer writes legacy roles — shrink ENUM:**

Widen to VARCHAR → assert no remaining `manager`/`uum` (and later `staff` if retired) → rebuild ENUM to:

```text
community | organizer | cmart_management | super_admin
-- plus staff if still required
```

Recommended interim ENUM after first cleanup:

```text
community | staff | organizer | cmart_management | super_admin
```

### Rollback risks

| Risk | Detail |
|---|---|
| Irreversible identity loss | Once `manager` and `uum` both become `organizer`, down migration cannot restore which was which without a backup column/table. |
| Existing down of `2026_07_09` | Maps all organizers back to `manager` — conflicts with “organizer is canonical” if rolled back carelessly. |
| E2E credentials | `admin@cmart.com` role change requires env/docs updates. |
| Booking status strings | `Pending_Boss` naming remains even after role rename — cosmetic only, not a DB role. |

**Mitigation:** Before remap, snapshot `users.id, email, role` to a backup table `role_migration_audit_2026_07`.

### Seeders / factories / tests that must change (later)

| Asset | Change needed |
|---|---|
| `DatabaseSeeder` | `admin@cmart.com` → `organizer` (or replace with `organizer@cmart.com` as primary demo); remove/avoid `manager`; no `uum` |
| Management profiles | Retitle “Branch Manager” → Organizer wording |
| `UserFactory` | Currently omits `role` — add explicit states (`organizer()`, `cmartManagement()`, `staff()`, `community()`) when touching tests |
| Feature tests | Replace manager assertions with organizer (keep 1 bridge test temporarily) |
| E2E | Update manager specs to organizer credentials/labels over time |

---

## 6. Backend access-control findings

### Route groups (summary)

| Middleware / gate | Roles / capability | Routes |
|---|---|---|
| `role:community` | community | Vendor APIs, booking create, vendor analytics |
| `carbootOperationalRoles()` | staff, manager, organizer, super_admin (+ legacy aliases) | Staff queue, bookings CRUD (non-delete), invoices, feedback ops, carboot-events |
| Nested `organizerEquivalentRoles()` | manager, organizer, super_admin (+ legacy) | Publish official reply, delete feedback |
| `cmartActivityRoles()` | staff, manager, organizer, cmart_management, super_admin | news-posts |
| `capability:generated_reports` | manager, organizer, cmart_management, super_admin | `/management/reports/operational-overview` |
| `boss` (= analytics owners) | manager, organizer, super_admin | delete booking, profitability, boss analytics, audit logs, spaces mutate |
| Web `auth:sanctum` + `boss` | same | `/admin/analytics`, `/api/proxy/analytics/*` |

### Still using legacy naming

| Item | Notes |
|---|---|
| Middleware alias `boss` / `manager` | Both point to `EnsureBossOnly` (analytics gate) — confusing name vs role `manager` |
| Status `Pending_Boss` | Workflow state, not a role |
| Path prefix `/staff/*` | Operational, not limited to role `staff` (organizer-equivalent also allowed by route list) |
| `/admin` | Workspace path, not role |

### Routes that should eventually say `organizer`

- All current `boss` / organizer-equivalent analytics and final-approval paths  
- Nested feedback publish/delete  
- Space mutate, booking destroy  

### Routes that should stay `cmart_management` (+ organizer where shared)

- news/activity management  
- generated reports only (already capability-gated)

### Frontend hide vs backend enforce

| Area | Frontend | Backend | Risk |
|---|---|---|---|
| Raw analytics nav | Hidden without analytics capability | Enforced via `boss` | OK |
| Generated reports | Shown for cmart_management; hidden when user has raw analytics | Enforced via `capability` | OK |
| Bookings for cmart_management | Nav hidden (no carboot_operations) | `/api/bookings` forbidden | OK |
| News for cmart_management | Shown | Allowed | OK |
| `/uum` | Role-gated in router | No dedicated API surface | Low — stub only |
| Hash `#revenue|#analytics|#audit` | Router redirects non-manager-workflow away | API still enforces | OK |

### Leftover unauthenticated internal/admin route?

**Not found** for analytics proxy or admin analytics page. Both require Sanctum auth + boss capability.

### Notable product gaps (not necessarily security bugs)

1. No dedicated “vendor account approval” admin API — vendor lifecycle is booking-centric + `vendor_status` field.  
2. `staff` can CRUD carboot events (ops desk power) — confirm if Organizer-only is desired later.  
3. `super_admin` currently has full Organizer operational powers (reserved but not nerfed).  
4. `ManagementReportsController` reuses `StaffOperationsController::operationsSummary()` — fine for counts, but naming may confuse auditors.

---

## 7. Frontend / navigation / label findings

### Confusing or legacy-facing labels

| Current label | Where | Later recommendation |
|---|---|---|
| Manager / Manager Portal / Manager Review | Bookings UI, Assist banner, status chips | “Organizer” / “Organizer Review” |
| Boss / `isBoss` / `bossPreview` | Store + middleware naming | Rename gradually to organizer preview (code alias OK short-term) |
| Admin / `/admin` / Admin Management Mode | Routes + HQ banner | Keep path if costly; UI: “Management workspace” / “Organizer workspace” |
| Carboot Organizer (Legacy) | manager role label | Remove after migration |
| CMart Venue Manager | cmart_management label | Prefer **“CMart Management”** |
| UUM Oversight | `/uum` stub | Replace with Organizer workspace; drop separate UUM role UI |
| Staff Portal / Tier 1 | staff theme | Keep while `staff` exists; clarify “Carboot Operations Staff” ≠ CMart Management |
| Management Analytics (if any leftover) | Prefer “Carboot Analytics” (Organizer) vs “Generated Reports” (CMart) | Already mostly split in nav groups |

### Preferred future wording (confirm for 1.3B UI pass)

- **Organizer** / **Carboot Organizer** for UUM/Organizer  
- **CMart Management** for venue-side role  
- **Generated Reports** for CMart consumable reports  
- **Carboot Analytics** only for Organizer  
- **Super Admin** only for reserved technical access  
- Avoid **Manager** as a final visible role  
- Avoid **Admin** as a role label  

---

## 8. Test impact findings

### Backend tests depending on `manager`

| Test | Why | 1.3B recommendation |
|---|---|---|
| `GovernanceAccessBoundaryTest::test_manager_can_access_...` | Uses `admin@cmart.com` as manager | Retarget to `organizer`; keep one temporary `test_legacy_manager_alias_still_maps` if bridge remains |
| `GovernanceAccessBoundaryTest` me payload | Asserts `maps_to_future_organizer` on manager | Switch primary to organizer; optional legacy assert |
| `WebAnalyticsSecurityTest` manager can access analytics | Seeded manager | Use organizer |
| `StaffOperationsSummaryTest` manager access | Seeded manager | Use organizer |
| `FeedbackModerationTest` manager publish/delete | Seeded manager | Use organizer |
| `BookingStaffStageAssistTest` | Creates `manager` users for assist + final approve | Change creates to `organizer`; keep 1 legacy bridge case while ENUM still allows `manager` |
| `ManagementCapabilityTest::test_manager_remains_legacy_organizer_bridge` | Documents bridge | Keep until ENUM drops `manager`, then delete |

### Backend tests depending on `staff`

| Test | Why | Recommendation |
|---|---|---|
| `BookingStaffStageAssistTest` staff transitions | Core pipeline | **Keep** while staff role remains |
| `StaffOperationsSummaryTest` | Seeded staff | Keep |
| `FeedbackModerationTest` staff review | Seeded staff | Keep |
| `GovernanceAccessBoundaryTest` staff denied analytics | Boundary | Keep |
| `WebAnalyticsSecurityTest` staff denied | Boundary | Keep |
| `CommunityVendorIntentTest` staff negative case | Seeded staff | Keep |

### Backend tests depending on `uum`

**None found.** Low test risk for `uum` removal after data check.

### E2E depending on manager/staff

Heavy dependency: `auth.manager-login`, `manager.booking-approval`, `access.manager-confirmation`, staff forward/review specs, env `E2E_MANAGER_*` / `E2E_STAFF_*`.

**Plan:** After seeder changes, point `E2E_MANAGER_*` at an **organizer** account (name can stay temporarily). Keep staff E2E until staff role retires. Add new E2E: cmart_management can open reports, cannot open analytics; organizer can open analytics.

### Tests to add for final simplification

1. `uum` users (if any fixture) migrate to organizer and reach `/admin`.  
2. No user remains with `manager` after migration command.  
3. `cmart_management` denied bookings + analytics; allowed news + reports.  
4. Organizer equals former manager matrix for booking approve/delete/analytics.  
5. Staff still cannot final-approve.  
6. Community pending vendor still reaches vendor dashboard APIs.

---

## 9. Security / access risks

### Confirmed healthy (Phase 1.2)

- CMart Management denied raw analytics API + web proxy.  
- Generated reports capability-gated.  
- Community cannot hit staff/boss endpoints (covered by tests).  
- Analytics web routes authenticated.

### Risks / debt to track in 1.3B

| Risk | Severity | Notes |
|---|---|---|
| Dual Organizer identities (`manager` + `organizer`) | Medium | Easy to mis-seed or mis-assign accounts |
| Orphan `uum` ENUM | Medium | If a prod user exists with `uum`, they cannot use management APIs but can hit stub `/uum`; migrating grants full Organizer — confirm intent |
| `staff` still has Carboot event CRUD | Low–Medium | Broader than “queue assist” wording |
| `super_admin` = full daily Organizer | Low | Product intent says reserved; technically not nerfed |
| Naming `boss` middleware | Low | Cognitive hazard for future contributors |
| `EnsureVendorApproved` unused | Info | Intentional; do not enable without product decision |
| Frontend-only hash guards | Low | Backend still enforces; OK |

**No critical unauthenticated admin analytics exposure found in this audit.** No patching performed (per 1.3A rules).

---

## 10. Recommended final simplified role model

| Role | Decision | Rationale |
|---|---|---|
| `organizer` | **Keep as canonical** | UUM = Organizer; full Carboot authority + analytics + reports |
| `cmart_management` | **Keep as canonical** | Venue/side activities + generated reports only |
| `community` | **Keep** | Public/vendor side; vendor via profile/status |
| `super_admin` | **Keep reserved** | Technical/HQ; do not use as daily Organizer substitute long-term (optional later nerf) |
| `uum` | **Migrate → `organizer`, then remove** | Same real-world authority; currently orphaned in code |
| `manager` | **Migrate → `organizer`, then remove** | Legacy Organizer bridge |
| `staff` | **Keep temporarily (Option A)** | Two-stage booking workflow; do not fold into `cmart_management` |
| `admin` | **Not a DB role** | Keep as workspace path label only; fix copy later |

### Interim target after Phase 1.3B (realistic)

```text
community | staff | organizer | cmart_management | super_admin
```

### Stretch target after workflow redesign (later phase)

```text
community | organizer | cmart_management | super_admin
```

---

## 11. Phase 1.3B implementation plan

### Substep 1 — Canonical role constants / helpers cleanup

1. Document `uum` as legacy alias → normalize to `organizer` in `ManagementRole::normalize()` / frontend `normalizeRole` **only after** deciding alias vs hard migrate.  
2. Prefer **hard data migrate** over permanent alias.  
3. Update `isOrganizerEquivalent` comments; stop calling Organizer “future”.  
4. Keep `matches(required: manager)` bridge until callers stop requesting `manager`.  
5. Do **not** add `uum` into `cmart_management` paths.

### Substep 2 — Data migration strategy

1. Backup `users (id, email, role)`.  
2. Count prod/staging by role (especially `uum`, `manager`).  
3. `UPDATE ... SET role='organizer' WHERE role IN ('manager','uum')`.  
4. Leave `staff` unchanged.  
5. After code deploy that no longer writes legacy roles, shrink ENUM (separate migration).  
6. Record rollback using backup table only.

### Substep 3 — Seeder / factory cleanup

1. `admin@cmart.com` → role `organizer` (or deprecate email in favor of `organizer@cmart.com`).  
2. Align management profile titles with Organizer / CMart Management.  
3. Add factory states for stable tests.  
4. Do not seed `uum` or `manager` after cutover.

### Substep 4 — Backend capability cleanup

1. Remove `manager` from capability allow-lists only after data+tests migrated (or keep one release bridge).  
2. Rename comments from “legacy manager bridge” to “removed”.  
3. Consider renaming middleware alias `boss` → `carboot_analytics` (alias old name temporarily).  
4. Keep staff capabilities intact.

### Substep 5 — Route / middleware cleanup

1. Replace documentation/examples that say “manager routes” with organizer.  
2. Keep `carbootOperationalRoles()` including `staff` until staff retired.  
3. Ensure `cmart_management` never enters carboot operational route list.  
4. Plan `/uum` → redirect to `/admin` (or login home) after role removal.

### Substep 6 — Frontend navigation / label cleanup

1. Replace visible “Manager” with “Organizer” where role-facing.  
2. Rename “CMart Venue Manager” → “CMart Management”.  
3. Keep Staff Portal Assist but say “assisting operations queue”.  
4. Soft-deprecate `/uum` page.  
5. Avoid advertising `/admin` as an “Admin role”.

### Substep 7 — Test update plan

1. Primary happy-path tests use `organizer`.  
2. One temporary legacy bridge test for `manager` while ENUM remains.  
3. Update E2E manager credentials to organizer account.  
4. Keep staff E2E suite.  
5. Add cmart_management report/analytics boundary E2E if feasible.

### Substep 8 — Smoke test checklist

- [ ] `organizer@` / migrated admin login → `/admin`, bookings, analytics hashes  
- [ ] `venue@` (`cmart_management`) → news + reports; 403 analytics + bookings  
- [ ] `staff@` → staff queue forward; cannot final approve; 403 analytics  
- [ ] `vendor@` / pending community → `/dashboard` APIs OK  
- [ ] `hq@` super_admin still reserved but functional  
- [ ] No user with `manager` or `uum` after migration  
- [ ] PHPUnit governance + analytics security tests green  
- [ ] E2E login + booking approval path green  

### Substep 9 — Rollback plan

1. Restore roles from `role_migration_audit_*` backup.  
2. Revert code deploy that removed bridges.  
3. Do not run ENUM shrink until dual-write window ends.  
4. If ENUM already shrunk, restore from DB backup before shrink migration.

---

## 12. Files likely modified in Phase 1.3B

### Backend

- `app/Support/ManagementRole.php`  
- `app/Support/ManagementCapability.php`  
- `app/Http/Middleware/EnsureRole.php` (messages)  
- `app/Http/Middleware/EnsureBossOnly.php` (naming/comments)  
- `app/Http/Kernel.php` (optional alias rename)  
- `app/Services/UserAuthPresenter.php`  
- `app/Http/Controllers/Api/BookingController.php` (comments / workflowRoleKey docs)  
- `routes/api.php` / `routes/web.php` (comments; capability names)  
- `database/seeders/DatabaseSeeder.php`  
- `database/factories/UserFactory.php`  
- **New** data migration (remap + later ENUM shrink)  
- Tests listed in §8  

### Frontend

- `utils/managementRoles.js`  
- `utils/managementCapabilities.js`  
- `stores/auth.js` (`uum` home branch)  
- `utils/postAuthRedirect.js`  
- `router/router.js` (`/uum`)  
- `config/managementWorkspaceTheme.js`  
- `config/workspaceNav.js` (labels only if in scope)  
- `composables/useManagementAccess.js` / `bossPreview.js` (naming)  
- `views/dashboards/UumDashboard.vue` (deprecate/redirect)  
- `views/dashboards/AdminDashboard.vue` (copy)  
- Booking status display labels (`bookingDisplay.js`, chips)  
- E2E env/docs/specs referencing manager  

### Docs

- This audit + a short Phase 1.3B runbook (optional)

---

## 13. Commands / tests recommended before Phase 1.3B

Read-only / non-destructive checks:

```bash
# Backend unit/feature (governance)
cd backend
php artisan test --filter=ManagementCapabilityTest
php artisan test --filter=GovernanceAccessBoundaryTest
php artisan test --filter=WebAnalyticsSecurityTest
php artisan test --filter=BookingStaffStageAssistTest

# Role inventory (aggregates only)
php artisan tinker --execute="echo json_encode(App\Models\User::query()->selectRaw('role, count(*) as c')->groupBy('role')->pluck('c','role'));"

# Confirm ENUM
php -r "/* bootstrap + SHOW COLUMNS users.role */"
```

E2E (after credentials known):

```bash
cd frontend
npm run test:e2e:headless -- auth.staff-login.spec.js auth.manager-login.spec.js
npm run test:e2e:headless -- access.staff-action-guard.spec.js access.manager-confirmation.spec.js
```

**Do not** run `migrate:fresh`, `db:wipe`, or destructive reseeds as part of 1.3A/1.3B prep on shared environments.

---

## 14. Risks, assumptions, and open questions

### Assumptions

1. Local DB role counts are representative of **dev only**; production may differ.  
2. Real-world UUM authority equals Organizer (stakeholder direction given).  
3. Two-stage booking approval remains required for the near term.  
4. CMart Management must not own raw Carboot analytics (already encoded).  
5. Vendor remains community-scoped (no separate vendor role).

### Open questions (need confirmation before/during 1.3B)

1. **Production `uum` count and emails** — any live UUM accounts?  
2. **Production `manager` count** — is `admin@cmart.com` pattern used outside seed?  
3. Should migrated `uum` users get **full** Organizer analytics immediately? (Recommended: yes.)  
4. Is **staff event CRUD** still desired, or Organizer-only?  
5. Should Phase 1.3B also rename `Pending_Boss` → `Pending_Organizer` (larger UX/API change — recommend **defer**)?  
6. Keep `/admin` path forever, or plan rename to `/management` later?  
7. Should `super_admin` be nerfed away from daily booking approval in a later phase?  
8. Is there a required **vendor_status admin UI** for Organizer, or is booking approval enough?

### Rollback / safety principles for 1.3B

- Migrate data before removing ENUM values.  
- Keep `staff` until workflow redesign.  
- Prefer additive redirects over hard deletes of `/uum` in the same PR as ENUM shrink.  
- Never conflate `staff` with `cmart_management`.

---

## Definition of done (Phase 1.3A)

This document provides:

1. Full role inventory  
2. Behavior matrix  
3. Overlap diagnosis (`uum`/`manager`/`staff`/`admin`/`community`)  
4. DB/migration findings and proposed (not executed) migration path  
5. Access-control and label audits  
6. Test impact analysis  
7. Recommended final model + phased 1.3B plan  

**No roles removed, no ENUM changed, no user data migrated, no application code refactored.**

---

*End of Phase 1.3A audit.*
