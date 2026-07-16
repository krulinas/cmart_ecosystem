# Phase 3.3A — ADR Corrections and Skipped-Test Classification

| Field | Value |
|-------|-------|
| **Status** | Complete |
| **Date** | 2026-07-16 |
| **Depends on** | Phase 3.2 ADR, Phase 3.3 test isolation |
| **Blocks** | Phase 3.4 only if critical skipped gaps or ADR inconsistency remain (none do) |

---

## 1. Purpose

Bridge Phase 3.3 → Phase 3.4 by:

1. Integrating ten mandatory corrections into `docs/phase-3/phase-3-2-event-layout-architecture-decision.md`
2. Identifying and classifying all **22** skipped backend tests on `cmart_test`
3. Deciding Phase 3.4 readiness honestly

---

## 2. ADR amendment summary

Amended ADR status: **Accepted — Amended after Phase 3.3 validation**

| # | Correction | Status |
|---|------------|--------|
| 1 | Unknown legacy categories block NOT NULL; no silent Others mapping | Integrated |
| 2 | Canonical taxonomy: 7 MVP categories including Household Items + Mixed / Others | Integrated |
| 3 | Vendor-safe `GET /api/vendor-categories` vs Organizer categories | Integrated |
| 4 | Deterministic closed/ended/cancelled public layout | Integrated |
| 5 | Row rename blocked after any allocation history | Integrated |
| 6 | Immutable reassignment snapshot JSON + relational keys | Integrated |
| 7 | Category deactivation impact checks | Integrated |
| 8 | Separate public publication readiness | Integrated |
| 9 | Super Admin uses Organizer layout/occupancy — not vendor availability | Integrated |
| 10 | Phase 3.4 entry gate requires ADR corrections + skip classification | Integrated |

```text
Unknown values auto-map to Mixed / Others: No
```

---

## 3. Skipped-test discovery

| Item | Value |
|------|-------|
| Command | `php artisan test --display-skipped` |
| Working directory | `backend/` |
| Resolved database | `cmart_test` (guard active) |
| Result | 172 passed, 0 failed, **22 skipped**, 795 assertions |
| Source search | `markTestSkipped` across `backend/tests/` |

Count reconciliation: `--display-skipped` listed 22 SKIPPED lines matching 22 `markTestSkipped` call sites that fire when demo fixtures are absent on fresh `cmart_test`.

---

## 4. Complete classification table

Primary classification key: **B** = Missing fixture, **E** = Misleading skip (silently skips instead of creating fixtures), **F** = Critical regression gap if unrepaired **and** no passing replacement.

| # | File | Test Class | Test Method | Skip Message | Actual Skip Condition | Dependency | Legacy Role? | Behaviour Protected | Classification | Risk | Recommended Action | Target Phase | Blocks 3.4? |
| -: | ---- | ---------- | ----------- | ------------ | --------------------- | ---------- | -----------: | ------------------- | -------------- | ---- | ------------------ | ------------ | ----------: |
| 1 | `backend/tests/Feature/CommunityVendorIntentTest.php` | CommunityVendorIntentTest | `test_seeded_vendor_user_returns_vendor_mode_with_signals` | Seeded vendor user not found. | `User::where('email','vendor@cmart.com')->first()` null | `vendor@cmart.com` | No (community) | Seeded vendor `community_mode` signals | B + E | Low | Replace with factory vendor + profile | 3.11 | No |
| 2 | `backend/tests/Feature/CommunityVendorIntentTest.php` | CommunityVendorIntentTest | `test_management_user_payload_has_no_community_mode` | Seeded management demo user not found. | `staff@cmart.com` null | `staff@cmart.com` email (role `cmart_management`) | No | Management payload lacks community_mode | B + E | Low | Provision `cmart_management` via factory | 3.11 | No |
| 3 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_organizer_can_hide_and_unhide_feedback` | Seeded organizer user not found. | `admin@cmart.com` null | `admin@cmart.com` (+ vendor for feedback) | No | Organizer hide/unhide feedback | B + E | Medium | Factory organizer + community author | 3.11 | No |
| 4 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_organizer_can_mark_feedback_reviewed` | Seeded organizer user not found. | `admin@cmart.com` null | `admin@cmart.com` | No | Organizer mark reviewed | B + E | Medium | Factory setup | 3.11 | No |
| 5 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_cmart_management_cannot_hide_feedback` | Seeded cmart_management user not found. | `venue@cmart.com` null | `venue@cmart.com` | No | CMart Mgmt forbidden hide | B + E | Medium | Factory `cmart_management` | 3.11 | No |
| 6 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_cmart_management_cannot_delete_feedback` | Seeded cmart_management user not found. | `venue@cmart.com` null | `venue@cmart.com` | No | CMart Mgmt forbidden delete | B + E | Medium | Factory `cmart_management` | 3.11 | No |
| 7 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_organizer_can_delete_feedback` | Seeded organizer user (admin@cmart.com) not found. | `admin@cmart.com` null | `admin@cmart.com` | No | Organizer delete feedback | B + E | Medium | Factory setup | 3.11 | No |
| 8 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_public_endpoint_does_not_show_hidden_feedback` | Seeded vendor user not found. Run database seeders. | `createTestFeedback()` → `vendor@cmart.com` null | `vendor@cmart.com` | No | Hidden feedback excluded from public list | B + E | Medium | Factory community user for feedback author | 3.11 | No |
| 9 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_public_endpoint_only_shows_published_official_reply` | Seeded organizer user not found. | `admin@cmart.com` null | `admin@cmart.com` + vendor | No | Draft official reply not public | B + E | Medium | Factory setup | 3.11 | No |
| 10 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_public_endpoint_supports_rating_filter_and_summary` | Seeded vendor user not found. | `vendor@cmart.com` null | `vendor@cmart.com` | No | Public rating filter + summary | B + E | Low | Factory community user | 3.11 | No |
| 11 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_public_endpoint_search_matches_comment_text` | Seeded vendor user not found. | `vendor@cmart.com` null | `vendor@cmart.com` | No | Public search | B + E | Low | Factory community user | 3.11 | No |
| 12 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_public_sort_is_stable_for_same_timestamp_rows` | Seeded vendor user not found. | `vendor@cmart.com` null | `vendor@cmart.com` | No | Stable sort by id tie-break | B + E | Low | Factory community user | 3.11 | No |
| 13 | `backend/tests/Feature/FeedbackModerationTest.php` | FeedbackModerationTest | `test_public_highest_and_lowest_rating_sort` | Seeded vendor user not found. | `vendor@cmart.com` null | `vendor@cmart.com` | No | Highest/lowest rating sort | B + E | Low | Factory community user | 3.11 | No |
| 14 | `backend/tests/Feature/GovernanceAccessBoundaryTest.php` | GovernanceAccessBoundaryTest | `test_cmart_management_cannot_access_carboot_operational_analytics_endpoints` | Seeded cmart_management demo (staff@cmart.com) not found. Run database seeders. | `staff@cmart.com` null | `staff@cmart.com` | No | CMart Mgmt denied raw analytics | B + E | Low | Duplicate of provisioned test `#65` in same class — retire or factory | 3.11 | No |
| 15 | `backend/tests/Feature/GovernanceAccessBoundaryTest.php` | GovernanceAccessBoundaryTest | `test_community_vendor_can_still_access_dashboard_apis_while_pending` | No community vendor user found in database. | No `role=community` user | Any community user | No | Pending community vendor dashboard APIs | B + E | Medium | Create community user in-test (TracksProvisionedUsers already available) | 3.11 | No |
| 16 | `backend/tests/Feature/MarketplacePublicAccessTest.php` | MarketplacePublicAccessTest | `test_vendor_private_items_endpoint_still_returns_own_items` | Seeded vendor user (vendor@cmart.com) not found. | `vendor@cmart.com` null | `vendor@cmart.com` | No | Vendor private items list | B + E | Low | Covered by `VendorPrivateItemsAccessTest` (passing, factory-based) — retire or align | 3.11 | No |
| 17 | `backend/tests/Feature/StaffOperationsSummaryTest.php` | StaffOperationsSummaryTest | `test_organizer_can_fetch_operations_summary_with_operational_counts_only` | Seeded organizer user (admin@cmart.com) not found. Run database seeders. | `admin@cmart.com` null | `admin@cmart.com` | No | Organizer operations-summary payload shape | B + E | Medium | Factory organizer; keep assertion of no revenue keys | Before 3.5 or 3.11 | No |
| 18 | `backend/tests/Feature/StaffOperationsSummaryTest.php` | StaffOperationsSummaryTest | `test_organizer_demo_account_can_fetch_operations_summary` | Seeded organizer user (admin@cmart.com) not found. Run database seeders. | `admin@cmart.com` null | `admin@cmart.com` | No | Demo email can fetch summary | B + E | Low | Obsolete if #17 factory-fixed — delete duplicate | 3.11 | No |
| 19 | `backend/tests/Feature/StaffOperationsSummaryTest.php` | StaffOperationsSummaryTest | `test_cmart_management_cannot_fetch_operations_summary` | Seeded cmart_management demo (staff@cmart.com) not found. Run database seeders. | `staff@cmart.com` null | `staff@cmart.com` | No | CMart Mgmt forbidden operations-summary | B + E | Medium | Factory `cmart_management` | 3.11 | No |
| 20 | `backend/tests/Feature/StaffOperationsSummaryTest.php` | StaffOperationsSummaryTest | `test_vendor_cannot_fetch_operations_summary` | No community vendor user found in database. | No community user | Any community | No | Community forbidden operations-summary | B + E | Medium | Factory community user | 3.11 | No |
| 21 | `backend/tests/Feature/WebAnalyticsSecurityTest.php` | WebAnalyticsSecurityTest | `test_community_users_cannot_access_analytics_proxy_endpoints` | No community vendor user found in database. | No community user | Any community | No | Community denied analytics proxy | B + E | Low | Factory community; pattern matches provisioned tests in same class | 3.11 | No |
| 22 | `backend/tests/Feature/WebAnalyticsSecurityTest.php` | WebAnalyticsSecurityTest | `test_cmart_management_demo_cannot_access_raw_analytics_proxy_endpoints` | Seeded cmart_management demo (staff@cmart.com) not found. Run database seeders. | `staff@cmart.com` null | `staff@cmart.com` | No | Demo CMart Mgmt denied analytics | B + E | Low | Duplicate of `test_cmart_management_users_cannot_access_raw_analytics_proxy_endpoints` (passing) | 3.11 | No |

**Total: 22**

Primary category for all 22: **Missing fixture (B)** exposed by Phase 3.3 isolation. All also **Misleading skip (E)** because absence of demo data yields skip rather than self-contained setup or hard fail. **None** are Category F blockers for Phase 3.4 schema/backfill.

---

## 5. Classification counts

| Classification | Count |
|----------------|------:|
| Legitimate environment-conditional (A) | 0 |
| Missing fixture (B) | 22 |
| Legacy governance assumption (C) | 0 |
| Obsolete (D) | 0 (2–3 are near-duplicates of passing tests; treat as B until retired) |
| Misleading skip (E) | 22 (co-classified with B) |
| Critical regression gap (F) | 0 (for Phase 3.4; governance covered by passing provisioned tests) |
| Unable to classify (G) | 0 |

---

## 6. Answers to critical classification questions

1. **`admin@cmart.com`:** FeedbackModerationTest #3,4,7,9; StaffOperationsSummaryTest #17,18
2. **`staff@cmart.com`:** CommunityVendorIntentTest #2; GovernanceAccessBoundaryTest #14; StaffOperationsSummaryTest #19; WebAnalyticsSecurityTest #22
3. **`vendor@cmart.com`:** CommunityVendorIntentTest #1; FeedbackModerationTest #8,10–13 (via `createTestFeedback`); MarketplacePublicAccessTest #16
4. **Any persistent demo:** All 22 — yes (email lookup or “any community user exists”)
5. **Removed roles (`staff`/`manager`/`uum` as role values):** None of the skips create or require those roles. Comments reference PR1 remap of `admin@cmart.com` from legacy manager **email** to organizer role. `staff@cmart.com` is a **demo email** for `cmart_management`, not role=`staff`.
6. **Two-stage Staff/Manager booking flow:** None
7. **Current Organizer governance:** #14 (demo duplicate); stronger coverage in same class via `provisionUser` tests that **pass**
8. **CMart Management boundaries:** #5,6,14,19,22 — demo skips; provisioned replacements pass for analytics (#14/#22 equivalents)
9. **Overlap existing passing tests:** #14 ↔ `test_cmart_management_is_denied_raw_analytics_but_can_access_generated_reports`; #22 ↔ `test_cmart_management_users_cannot_access_raw_analytics_proxy_endpoints`; #16 ↔ `VendorPrivateItemsAccessTest`; #1 ↔ factory community vendor mode tests in same class
10. **No valid replacement:** FeedbackModerationTest suite (#3–13) and StaffOperationsSummary role matrix (#17,19,20) lack full factory-based replacements today
11. **Fixable with factories:** All 22
12. **Require seeders:** None should — seeders are the root cause of skips on `cmart_test`
13. **Should not use seeders:** All 22
14. **Misleading silent skips:** All 22
15. **Must fix before Phase 3.4:** None (no schema/backfill/allocation/payment dependency)
16. **May wait until 3.11:** All 22 (prefer earlier for #17 operations-summary if Organizer UI expands)
17. **Caused by isolated DB:** Yes — fresh `cmart_test` has no demo accounts
18. **Hidden `cmart_db` dependence revealed:** Yes
19. **Needs CreatesCanonicalOrganizer helper:** Yes — FeedbackModeration, StaffOperationsSummary, and several governance demos
20. **Needs CreatesCanonicalCommunityVendor helper:** Yes — Feedback, CommunityVendorIntent, Marketplace, WebAnalytics, Governance #15

---

## 7. Fixture strategy recommendation (do not implement in 3.3A)

```text
Each backend feature test creates the minimum data it requires.
```

Prefer: factories, unique emails, canonical roles (`community`, `organizer`, `cmart_management`, `super_admin`), test-local cleanup on `cmart_test`.

Avoid: `DatabaseSeeder` dependence, fixed demo emails, silent `markTestSkipped` when fixture missing, legacy roles.

Suggested future helpers (Phase 3.11):

- `CreatesCanonicalOrganizer`
- `CreatesCanonicalCommunityVendor`
- `CreatesCanonicalCmartManagement`
- `CreatesBookableCarbootEvent` / `CreatesPhysicalEventLayout` (layout phases)

---

## 8. Coverage impact

| Behaviour | Skipped Coverage | Existing Passing Replacement | Remaining Gap | Risk |
|-----------|------------------|------------------------------|---------------|------|
| CMart Mgmt denied raw analytics | #14, #22 | `GovernanceAccessBoundaryTest::test_cmart_management_is_denied...`; `WebAnalyticsSecurityTest::test_cmart_management_users_cannot...` | Demo-email path only | Low |
| Community vendor mode | #1 | Same class factory tests for profile/status | Seeded-vendor signals only | Low |
| Vendor private items | #16 | `VendorPrivateItemsAccessTest` | None material | Low |
| Feedback moderation | #3–13 | None self-contained | Full feedback moderation on isolated DB | Medium (non-Phase-3) |
| Operations summary authz | #17–20 | Guest unauthorized only (`test_guest_cannot_fetch...`) | Organizer/CMart/community matrix | Medium (non-Phase-3) |
| Pending community dashboard | #15 | Partial via booking access tests | Pending-status path | Low–Medium |
| Allocation / payment / withdrawal / layout | — | Extensive Phase 2 feature tests (passing) | None from these skips | None for 3.4 |

---

## 9. Repair roadmap

| Test or Group | Recommended Action | Target Phase | Priority | Blocks Phase 3.4? |
| ------------- | ------------------ | ------------ | -------- | ----------------: |
| FeedbackModerationTest (11) | Replace seed lookups with factories; fail if create fails | 3.11 | Medium | No |
| StaffOperationsSummaryTest (4 role cases) | Factory organizer / cmart_management / community; delete demo duplicate #18 | 3.11 (or before Organizer UI work) | Medium | No |
| Governance #14 / WebAnalytics #22 | Retire as duplicates of provisioned tests | 3.11 | Low | No |
| CommunityVendorIntent #1–2 | Factory fixtures | 3.11 | Low | No |
| Marketplace #16 | Retire or redirect to VendorPrivateItemsAccessTest pattern | 3.11 | Low | No |
| Governance #15 / WebAnalytics #21 | Create community user via TracksProvisionedUsers | 3.11 | Low | No |

---

## 10. Phase 3.4 readiness

```text
READY FOR PHASE 3.4 WITH NON-BLOCKING TEST DEBT
```

Non-blocking debt: 22 seed-dependent skips (feedback + operations-summary + demo duplicates). None hide category/layout schema, backfill, allocation, or canonical role authorization without replacement evidence.

---

## 11. References

- Amended ADR: `docs/phase-3/phase-3-2-event-layout-architecture-decision.md`
- Isolation: `docs/phase-3/phase-3-3-test-environment-isolation-and-baseline-safety.md`
- Evidence: `php artisan test --display-skipped` on 2026-07-16 against `cmart_test`
