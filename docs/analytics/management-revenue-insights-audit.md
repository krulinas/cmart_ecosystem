# Management Revenue / Insights Audit Report

**Project:** CMart Ecosystem  
**Scope:** Management portal Insights (Revenue, Word Cloud, Audit Log) — frontend → backend → database  
**Audit date:** 8 July 2026

---

# 1. Executive Summary

The Management **Insights → Revenue** page is **partially meaningful**, not production-grade decision analytics.

| Aspect | Verdict |
|--------|---------|
| Overall page | **Partially meaningful** — some KPIs use real DB data, but labels, formulas, and UX mislead managers |
| Data quality | **Mixed** — invoice totals are real; category/utilization metrics are weak or wrong |
| Admin can decide from it? | **Partially** — rough paid vs unpaid is visible; verification queue, per-event fill, and follow-up lists are missing |
| Demo safety | **Risky** — sparse DB → empty charts; seeded demo → misleading 100% F&B; Tools tab shows hardcoded “impact” numbers |

### Top 5 urgent issues

1. **“Revenue by Category” chart shows booking counts, not revenue** — title is wrong (`BossAnalyticsController::revenue` groups by `count(*)`, not `sum(amount)`).
2. **Paid + Unpaid ≠ Total Revenue** — `Pending Verification` and `Refunded` are excluded from KPI cards but may exist in DB.
3. **Space utilization is conceptually wrong** — compares **all-time approved vendor bookings** to **upcoming event `max_slots`**, which is **community RSVP capacity**, not vendor booth capacity.
4. **“Space Utilization & Profitability” mixes real summary text with a manual profit simulator** — default inputs (`space_id: 1`, parking rate RM1, etc.) look like system data.
5. **Manager Tools tab shows hardcoded placeholder “Live Analytics”** (`ImpactDashboard` with fixed numbers 12450 / 892000 / 156 / 89) — not connected to revenue insights.

---

# 2. Current Management Role Context

| Tier | Role(s) | Purpose | Insights access |
|------|---------|---------|-----------------|
| **Tier 1** | `staff`, `cmart_staff` | First-level booking screening, payment proof review, operational queues | **No** Insights nav; Tools shows real `StaffOperationalSnapshot` from `/staff/operations-summary` |
| **Tier 2** | `manager`, `cmart_admin`, `boss` | Final approval, branch monitoring, revenue/payment insights | **Full** Insights: Revenue, Word Cloud, Audit Log |
| **Tier 3** | `super_admin` | Reserved HQ governance; **currently reuses manager operational UI** | Same APIs as manager via `ManagementRole::canAccessManagerRoutes()`; banner: “Tier 3 · Reserved HQ Access” |

**Visibility logic:**

- Frontend: `useManagementAccess.shouldLoadManagerPanels` → `workflowRoleKey(effectiveRole) === 'manager'`
- `super_admin` maps to manager workflow (`workflowRoleKey`)
- Staff preview: managers/super_admins can toggle “View as Staff” → hides Insights nav and blocks `#revenue` hash via router guard

**Manager-only sections display:** Correct for staff (hidden) and managers (shown). **Bug/UX:** `Mgr` badge still shows **on manager’s own sidebar** for Insights items — redundant and confusing.

---

# 3. Sidebar Insights Audit

| Nav item | Hash | Meaning | Staff | Manager | Super Admin | Label UX | Recommendation |
|----------|------|---------|-------|---------|-------------|------------|----------------|
| **Revenue** | `#revenue` | Branch revenue KPIs + charts + profit simulator | Hidden | Visible | Visible (manager mode) | Clear | **Keep** — redesign content |
| **Word Cloud** | `#analytics` | Text frequency from feedback + approved product descriptions | Hidden | Visible | Visible | “Word Cloud” is jargon | **Rename** to “Text Insights” or “Feedback & Product Terms” |
| **Audit Log** | `#audit` | Booking approval status transitions | Hidden | Visible | Visible | Clear | **Keep** — extend to payment actions later |
| **MGR badge** | — | Short for `managerOnly: true` on nav item | N/A (staff don’t see items) | **Still shown** | **Still shown** | Cryptic; shown to people who already have access | **Remove for managers** OR show lock + tooltip only in staff-preview docs |

### MGR badge — specific answers

- **What does “MGR” mean?** Abbreviation for “manager-only” nav item (`WorkspaceShell.vue` lines 76–81, `item.managerOnly`).
- **Is it necessary?** **No** for managers who already see the section. Useful only as a **staff training hint** (but staff never see those links).
- **Remove for managers?** **Yes** — hide when `canSeeManagerSections === true`.
- **Should staff see “Manager only” instead?** Staff don’t see Insights links at all; if you add greyed-out items for training, use **“Manager only”** or a lock icon + tooltip.
- **Lock icon/tooltip?** **Recommended** over `Mgr`.

---

# 4. Frontend Architecture Map

| Path | Purpose | Key state/API | UI | Weaknesses |
|------|---------|---------------|-----|------------|
| `frontend/src/views/dashboards/AdminDashboard.vue` | Shell orchestrator, lazy-loads panels | `activeSection`, `shouldLoadManagerPanels`, section cache | All management sections | Manager panels only mount when `shouldLoadManagerPanels` |
| `frontend/src/layouts/WorkspaceShell.vue` | Sidebar, mobile nav, badges | `navGroups`, `Mgr` badge | Grouped nav | `Mgr` badge shown to managers |
| `frontend/src/config/workspaceNav.js` | Nav definitions | `MANAGER_ONLY_HASHES` | — | Hash `#analytics` labeled “Word Cloud” |
| `frontend/src/config/managementWorkspaceTheme.js` | Tier themes | `resolveWorkspaceThemeKey` maps super_admin → manager theme | Theme tokens | — |
| `frontend/src/composables/useManagementAccess.js` | Role helpers | `shouldLoadManagerPanels`, `canSeeManagerSections` | — | — |
| `frontend/src/composables/useWorkspaceNav.js` | Filter nav by role | `canAccessHash` | — | — |
| `frontend/src/utils/managementRoles.js` | Role normalization | `workflowRoleKey`, `matchesRole` | — | — |
| `frontend/src/stores/bossPreview.js` | “View as Staff” toggle | `effectiveRole` | — | — |
| `frontend/src/stores/auth.js` | Session | `isBoss`, `isSuperAdmin` | — | `isBoss` = manager or above |
| `frontend/src/views/dashboards/boss/BossRevenuePanel.vue` | **Revenue page** | `data`, Chart.js refs | KPI cards, 2 charts, utilization + simulator | Mislabeled charts; no empty states on charts; hardcoded `calcData` defaults |
| `frontend/src/views/dashboards/boss/BossWordCloudPanel.vue` | Word cloud | `feedbackData`, `productsData` | Two word clouds + top terms | Depends on Python service; good empty states |
| `frontend/src/views/dashboards/boss/BossAuditLogsPanel.vue` | Audit table | `logs`, pagination | Paginated table | No payment audit; good empty state |
| `frontend/src/views/dashboards/staff/StaffBookingsPanel.vue` | Operations + payment verify | `paymentFilter`, verify modal | Booking registry, payment status, verify button | Real payment workflow — **not surfaced in Revenue** |
| `frontend/src/views/dashboards/staff/StaffToolsPanel.vue` | Tools | `impactMetrics` **hardcoded** | Staff snapshot OR `ImpactDashboard` | **Manager view uses PLACEHOLDER impact metrics** |
| `frontend/src/components/management/StaffOperationalSnapshot.vue` | Staff KPI cards | `/staff/operations-summary` | Real operational counts | Staff only |
| `frontend/src/components/ImpactDashboard.vue` | “Our Impact” cards | Props with **default hardcoded numbers** | Sustainability-style metrics | **PLACEHOLDER** — not revenue analytics |
| `frontend/src/router/router.js` | Route guards | Blocks `MANAGER_ONLY_HASHES` for staff view | — | Aligns with frontend nav |

---

# 5. Backend Architecture Map

| Path | Routes | Logic | Models | Weaknesses |
|------|--------|-------|--------|------------|
| `backend/routes/api.php` | Boss group under `middleware('boss')` | Revenue, wordcloud, audit, profitability | — | Legacy name `boss` = manager+ |
| `backend/app/Http/Middleware/EnsureBossOnly.php` | — | `ManagementRole::canAccessManagerRoutes()` | — | super_admin allowed ✓ |
| `backend/app/Http/Controllers/Api/BossAnalyticsController.php` | `GET /boss/analytics/revenue`, `GET /boss/analytics/wordcloud/{source}` | Revenue aggregation; proxies word cloud to Python | `Invoice`, `Booking`, `CarbootEvent`, `Space` | Category = counts; utilization wrong; payment KPI gap |
| `backend/app/Http/Controllers/Api/BookingController.php` | `POST /profitability` | `checkProfitability()` — space price vs parking opportunity cost | `Space` | **Simulator only** — one space price, not event revenue |
| `backend/app/Http/Controllers/Api/AuditLogController.php` | `GET /boss/audit-logs` | Paginated audit logs | `BookingAuditLog` | Status changes only |
| `backend/app/Http/Controllers/Api/StaffOperationsController.php` | `GET /staff/operations-summary` | Operational counts incl. `payment_proofs_to_check` | `Booking`, `Invoice`, etc. | Not used on Revenue page |
| `backend/app/Http/Controllers/AnalyticsController.php` | Web routes (Blade) | Proxy to Python | — | Parallel path; management UI uses BossAnalytics |
| `backend/app/Services/BookingAuditLogger.php` | — | Writes audit on status change | `BookingAuditLog` | No payment verification logging |
| `backend/app/Support/ManagementRole.php` | — | RBAC normalization | — | super_admin → manager routes |
| `python_analytics/main.py` | Port **8001** | Word cloud from `feedbacks.comments`, `bookings.product_details` | Direct MySQL | Must be running; API key optional |

---

# 6. API Endpoint Audit

| Method | URL | Auth | Role | Controller | Frontend caller | Response (key fields) | Data type | Empty risk | Risks |
|--------|-----|------|------|------------|-----------------|----------------------|-----------|------------|-------|
| GET | `/api/boss/analytics/revenue` | Sanctum | manager, super_admin, legacy boss/admin | `BossAnalyticsController@revenue` | `BossRevenuePanel.load` | `summary`, `by_category`, `by_space`, `by_payment_status` | **REAL** (DB) | Yes — no approved bookings/invoices | Misleading formulas |
| POST | `/api/profitability` | Sanctum | manager+ | `BookingController@checkProfitability` | `BossRevenuePanel.calculateProfit` | `net_profit`, `is_profitable`, `message` | **DERIVED** (manual inputs) | No | Not real branch P&L |
| GET | `/api/boss/analytics/wordcloud/feedback` | Sanctum | manager+ | `BossAnalyticsController@wordcloud` | `BossWordCloudPanel` | `terms`, `total_documents` | **REAL** (if Python up) | Yes — no feedback text | 502 if Python down; default Laravel URL wrong in `config/services.php` fallback |
| GET | `/api/boss/analytics/wordcloud/products` | Sanctum | manager+ | same | same | same | **REAL** | Yes — no approved products | same |
| GET | `/api/boss/audit-logs` | Sanctum | manager+ | `AuditLogController@index` | `BossAuditLogsPanel` | Paginated `data[]` | **REAL** | Yes — no audit rows | Payment actions not logged |
| GET | `/api/staff/operations-summary` | Sanctum | staff+ | `StaffOperationsController` | `StaffOperationalSnapshot` | `payment_proofs_to_check`, etc. | **REAL** | Can be all zeros | Not on Revenue page |

**Permission alignment:** E2E confirms staff GET `/boss/analytics/revenue` → **403**; manager → **200**. No super_admin-specific E2E; backend `EnsureBossOnly` includes `super_admin` — **no mismatch expected**.

**Config risk:** `backend/config/services.php` defaults `analytics.url` to `http://127.0.0.1:8000` (Laravel), but `backend/.env.example` uses **8001**. Wrong env → word cloud 502.

---

# 7. Data Source Audit (Revenue page metrics)

| UI label | Example | Frontend var | Backend field | DB source | Formula | Real? | Problem | Recommendation |
|----------|---------|--------------|---------------|-----------|---------|-------|---------|----------------|
| **Total Revenue** | RM 30.00 | `data.summary.total_revenue` | `summary.total_revenue` | `invoices.amount` | `sum(amount)` where booking `approval_status = Approved` | **REAL** | Includes unpaid; label should be “Expected revenue (approved)” | Rename; optional event filter |
| **Paid** | RM 30.00 | `data.summary.paid_revenue` | `summary.paid_revenue` | `invoices` | `sum(amount)` where `payment_status = Paid` | **REAL** | “Collected” clearer | Rename to “Collected” |
| **Unpaid** | RM 0.00 | `data.summary.unpaid_revenue` | `summary.unpaid_revenue` | `invoices` | `sum(amount)` where `payment_status = Unpaid` only | **DERIVED** | Misses Pending Verification & Refunded | Full payment breakdown |
| **F&B Share** | 100% | `data.summary.fb_share_percent` | same | `bookings.product_category` | F&B approved count / all approved count | **DERIVED** | Count-based, not revenue; demo seed = 1 F&B booking → 100% | “Category mix (bookings)” or revenue-weighted |
| **Revenue by Category** (chart) | Doughnut | `data.by_category` | `by_category` | `bookings.product_category` | `count(*)` per category | **DERIVED** | **Not revenue** — mislabeled | `sum(invoices.amount)` by category |
| **Payment Status** (chart) | Bar chart | `data.by_payment_status` | `by_payment_status` | `invoices.payment_status` | `count`, `sum(amount)` per status | **REAL** | Empty if no invoices; no empty-state UI | Empty state + table |
| **Reference event** | CMart Weekly Carboot | `data.summary.reference_event` | `reference_event` | `carboot_events.title` | Next event `starts_at >= now()` | **REAL** | Not tied to metrics below | Event selector |
| **Approved bookings vs max slots** | 1 / 120 | `approved_bookings`, `max_slots_reference` | same | `bookings`, `carboot_events.max_slots` | All approved count / next event max_slots | **DERIVED** | **Wrong domains** — vendor bookings vs community RSVP slots | Per-event vendor fill or rename |
| **Fill %** | 0.8% | `utilization_percent` | same | — | `approved / max_slots * 100` | **DERIVED** | Meaningless ratio | Fix denominator |
| **Space ID** | 1 | `calcData.space_id` | — | — | User input | **MANUAL** | Looks like system field | Move to “Profit Simulator” |
| **Spaces Closed** | 10 | `calcData.parking_lots_used` | — | — | User input | **MANUAL** | Misleading label | Rename “Parking lots used” |
| **Parking Rate / Hour** | RM 1 | `calcData.regular_parking_rate` | — | — | User input | **MANUAL** | Default RM1 unrealistic | Simulator section only |
| **Event Duration** | 8 hrs | `calcData.hours_occupied` | — | — | User input | **MANUAL** | Not from `carboot_events.ends_at - starts_at` | Optional prefill from event |
| **Calculate Profit** | Net profit RM… | `profitResult` | `POST /profitability` | `spaces.price` | `space.price - (lots × rate × hours)` | **DERIVED** | Single space; not aggregate revenue | Label “Profit Simulator” |
| **by_space list** | Standard · N bookings · RM X | `data.by_space` | `by_space` | `spaces`, `bookings` | `count(*)`, `sum(spaces.price)` | **DERIVED** | Uses `spaces.price`, not `invoices.amount`; ignores tapak | Use invoice amounts |

---

# 8. Revenue Calculation Deep Dive

**Source:** `BossAnalyticsController::revenue()` (lines 42–101)

| Question | Answer |
|----------|--------|
| Based on approved bookings? | **Indirectly** — only invoices with `booking.approval_status = Approved` |
| Based on invoices? | **Yes** — all amounts from `invoices.amount` |
| Paid invoices only for total? | **No** — total = all approved-booking invoices regardless of payment status |
| Unpaid = actual outstanding? | **Partial** — only `Unpaid` enum; excludes `Pending Verification` (money in limbo) |
| Rejected/withdrawn included? | **No** — filtered by approved |
| Pending bookings included? | **No** |
| Tapak count? | **No** — `tapak_quantity` used at booking creation only; **not stored** on `bookings` table; amount stored on invoice |
| Booth type price? | **Not for KPIs** — invoice amount set at booking as `tapak_quantity × RM 20` (hardcoded rate in `BookingController::store`) |
| Hardcoded rates? | Vendor booking: **RM 20 per tapak**; seeder demo invoice uses `spaces.price` (RM 30) — **inconsistency** |
| Missing payment status? | Defaults `Unpaid` on create; excluded from paid sum |
| Missing invoice? | Approved booking **excluded** from revenue totals but **included** in `approved_bookings` / category counts |
| Null category? | Grouped as SQL null key; chart may show blank label |

```php
// Total revenue
Invoice::whereHas('booking', fn ($q) => $q->where('approval_status', 'Approved'))->sum('amount');

// Category chart (COUNT not revenue)
Booking::where('approval_status', 'Approved')->groupBy('product_category')->count();
```

---

# 9. Payment Status Deep Dive

**Enum values** (after migration `2026_06_24_000001`): `Paid`, `Unpaid`, `Pending Verification`, `Refunded`

| Status | Receipt/pass unlock | Counts as paid in analytics | Counts as unpaid in analytics | In payment chart |
|--------|--------------------|-----------------------------|-------------------------------|------------------|
| **Paid** | Yes (`VendorEventPassService`) | Yes | No | Yes |
| **Unpaid** | No | No | Yes | Yes |
| **Pending Verification** | No | **No** | **No** | Yes (in breakdown query) |
| **Refunded** | No | No | No | Yes (in breakdown) |

**Workflow:**

1. Approved + `Unpaid` → vendor submits proof → `Pending Verification`
2. Staff/manager `PATCH /bookings/{id}/verify-payment` → `Paid` (only from Pending Verification)

**Analytics gap:** KPI cards use only Paid/Unpaid → **Pending Verification invisible** in top cards despite being operationally critical (`StaffOperationsController` tracks `payment_proofs_to_check`).

**Financial accuracy:** **Not fully accurate** for demos or live ops until Pending Verification and Refunded are in KPI math.

---

# 10. Category Analytics Deep Dive

- **Source:** `bookings.product_category` — validated enum at booking time:
  - Pre-loved / Thrift, Food & Beverages, Clothing & Apparel, Handicrafts & Art, Electronics & Gadgets, Others
- **Normalized:** Yes (enum), not free text
- **F&B 100%:** Seeder creates **one** approved F&B booking → `fb_share_percent = 100%` — **demo artifact**, not necessarily real dominance
- **F&B Share too narrow:** Yes — ignores revenue and other categories’ monetary contribution
- **Better metrics:** Category Mix (% bookings), Top Category by revenue, expected vs collected per category
- **Admin value:** Helps balance vendor variety and spot over-concentration (e.g. too much F&B)

---

# 11. Space Utilization & Profitability Deep Dive

| Topic | Current behavior | Verdict |
|-------|------------------|---------|
| Event capacity | `carboot_events.max_slots` | **Community RSVP** capacity (`event_user`), not vendor booths |
| Approved slots | `count(approved bookings)` globally | **Not per event** |
| Tapak | Not in analytics | Invoice amount reflects tapak×20 at booking time only |
| Booth types | `by_space` groups by `spaces.space_size` | Uses `spaces.price`, not invoice |
| Calculator | `POST /profitability` | **Manual simulation** — opportunity cost of closing parking |
| Space ID / parking fields | Frontend `calcData` defaults | **MANUAL** — not system analytics |
| Section name | “Space Utilization & Profitability” | **Misleading** | Split into “Event fill (real)” + “Profit Simulator (what-if)” |

**Real utilization** would need: per `carboot_event_id`, approved vendor bookings (and ideally tapak slots) vs a defined vendor capacity (currently **no vendor capacity column** exists).

---

# 12. Empty/Blank UI Panel Audit

| Panel | Why empty | Root cause | Fix |
|-------|-----------|------------|-----|
| **Revenue by Category** chart | No canvas chart if `catLabels.length === 0` | No approved bookings | Add “No approved bookings yet” empty state |
| **Payment Status** chart | `payments.length === 0` | No invoices for approved bookings | Empty state message |
| **Charts with 1 category** | Renders | Works | OK |
| **by_space list** | `v-if="data.by_space?.length"` | No approved bookings | Hidden — OK |
| **Word Cloud** | Handled | No text in DB / Python down | Good empty + error states |
| **Audit Log** | “No audit records yet” | No status transitions logged | OK |
| **Profit result** | Hidden until click | By design | OK |

**Not broken rendering** — mostly **empty data** or **silent skip** when arrays empty (charts leave blank 264px boxes).

---

# 13. Decision-Support Audit

| Manager question | Available? | Where | Missing |
|------------------|------------|-------|---------|
| Expected revenue? | **Partial** | Total Revenue card | Per-event; pending approvals excluded |
| Collected? | **Partial** | Paid card | Rename; no trend |
| Still unpaid? | **Partial** | Unpaid card | Pending Verification omitted |
| Payments need verification? | **No** on Revenue | Staff snapshot / bookings panel only | Add to Revenue “Needs attention” |
| Vendors need follow-up? | **No** | — | Outstanding payment list |
| Categories dominate? | **Partial** | Mislabeled chart (counts) | Revenue-weighted mix |
| Space filling up? | **No** (misleading) | Utilization line | Per-event vendor capacity model |
| Too many pending approvals? | **No** on Revenue | Bookings / staff snapshot | Manager queue KPI on Revenue |
| What to do next? | **No** | — | Action-oriented “Needs attention” section |

---

# 14. Security and Access Control Audit

| Check | Status | Evidence |
|-------|--------|----------|
| Staff blocked from revenue API | **Pass** | E2E `access.staff-action-guard.spec.js`; `EnsureBossOnly` |
| Manager allowed | **Pass** | E2E `access.manager-confirmation.spec.js` |
| super_admin allowed | **Pass** (code) | `ManagementRole::canAccessManagerRoutes` includes super_admin |
| Frontend nav aligned | **Pass** | `useWorkspaceNav` + router hash guard |
| Staff preview hides insights | **Pass** | `bossPreview.viewAsStaff` |
| Sensitive data to vendor/public | **Pass** | Boss routes behind auth + boss middleware |
| UI/API mismatch | **None found** for staff/manager | — |

---

# 15. Testing Coverage Audit

| Test | Covers | Gaps |
|------|--------|------|
| `frontend/tests/e2e/specs/access.staff-action-guard.spec.js` | Staff 403 on `/boss/analytics/revenue`, audit | Word cloud, profitability |
| `frontend/tests/e2e/specs/access.manager-confirmation.spec.js` | Manager 200 on revenue + audit | Response body assertions |
| `frontend/tests/e2e/specs/access.staff-tools-snapshot.spec.js` | Staff tools ≠ hardcoded Impact | Manager tools placeholder not tested |
| `backend/tests/Feature/StaffOperationsSummaryTest.php` | Staff/manager operations summary | No revenue endpoint tests |
| — | — | **No** `BossAnalyticsController` unit/feature tests |
| — | — | **No** super_admin E2E |
| — | — | **No** payment status aggregation tests |
| — | — | **No** chart empty-state UI tests |

---

# 16. Problems Found (Prioritized)

### Critical

1. **Utilization metric compares vendor bookings to community RSVP slots** — `BossAnalyticsController.php:68-76` — misleads fill-rate decisions — **Backend + Frontend**
2. **“Revenue by Category” shows counts not RM** — same file `:52-56` — wrong chart semantics — **Backend + Frontend**

### High

3. **Pending Verification excluded from unpaid/outstanding KPIs** — `:49-50` — underreports risk — **Backend**
4. **Profit simulator presented as utilization analytics** — `BossRevenuePanel.vue:41-76` — demo confusion — **Frontend**
5. **Manager Tools shows hardcoded ImpactDashboard** — `StaffToolsPanel.vue:67-72` — fake “Live Analytics” — **Frontend**
6. **Python analytics URL mis-default** — `config/services.php:41` vs `.env.example` — word cloud failures — **Config**

### Medium

7. **MGR badge shown to managers** — `WorkspaceShell.vue:76-81` — UX — **Frontend**
8. **F&B Share naming and count-only logic** — misleading with sparse data — **Backend + Frontend**
9. **by_space uses space.price not invoice.amount** — revenue mismatch — **Backend**
10. **No tapak column on bookings** — can't report slots sold — **Database** (future)
11. **Invoice amount vs space price inconsistency** (RM20×tapak vs seeder RM30) — **Seeder / booking logic**

### Low

12. **Audit log doesn't include payment verification** — incomplete trail — **Backend**
13. **No event/date filter on revenue dashboard** — all-time only — **Backend + Frontend**
14. **Chart empty states missing** — blank boxes — **Frontend**

---

# 17. Recommended Analytics Redesign (real data only)

### A. KPI cards

- Total Expected Revenue (approved invoices)
- Collected Revenue (`Paid`)
- Outstanding Revenue (`Unpaid` + `Pending Verification`)
- Payment Completion Rate (`Paid / expected`)
- Pending Verification Count (from existing `StaffOperationsController` query)

### B. Needs Attention

- Awaiting manager (`Pending_Boss` count)
- Needs revision (`Needs_Revision`)
- Outstanding payment amount (Unpaid)
- Pending verification (count + RM)
- Low fill rate (only after fixing per-event vendor metric)

### C. Payment Breakdown

Table/chart: Paid | Pending Payment (Unpaid) | Pending Verification | Refunded — with count + RM each

### D. Revenue by Category

Category | Approved bookings | Expected RM | Collected RM | Share %

### E. Event Space Utilization

Event selector | Approved vendor bookings for event | Vendor capacity (new field or documented proxy) | Fill % | Expected vs collected for event

### F. Profit Simulator

Separate card, labeled **“What-if: Parking opportunity cost”**, manual inputs, not mixed with KPIs

---

# 18. Implementation Plan (future — do not implement now)

### Phase 1 — UX cleanup (Low risk)

- Files: `BossRevenuePanel.vue`, `WorkspaceShell.vue`, `workspaceNav.js`
- Fix labels, empty states, MGR badge, split simulator section
- Verify: manual UI check + existing E2E

### Phase 2 — Backend corrections (Medium risk)

- Files: `BossAnalyticsController.php`, optional new `ManagementAnalyticsService.php`
- Fix category revenue, payment breakdown in summary, per-event filters
- Verify: `php artisan test` + new feature tests

### Phase 3 — Frontend redesign (Medium risk)

- Files: `BossRevenuePanel.vue`, new subcomponents
- Needs Attention section, payment table, event selector
- Verify: E2E manager flow

### Phase 4 — Tests

- `BossAnalyticsRevenueTest.php`, super_admin access test, payment aggregation tests
- Verify: `php artisan test --filter=BossAnalytics`

### Phase 5 — Demo validation checklist

- [ ] Seed has mixed categories and payment statuses
- [ ] Python analytics on 8001
- [ ] Charts non-empty or show intentional empty states
- [ ] Manager Tools does not show hardcoded impact OR is labeled demo
- [ ] Utilization metric definition documented for presenter

---

# 19. Demo / Presentation Explanation (for boss)

> Analytics matters because CMart needs to see **money expected**, **money collected**, and **what needs action today** — not just booking counts.  
> The system **already tracks** real invoices, payment proof workflow, categories, and approval audit trails in the database.  
> The Revenue page uses **real invoice data** for top-level totals, but some charts and utilization figures are **not yet decision-ready** — they need clearer labels and corrected formulas.  
> **HQ (Tier 3)** is reserved for future multi-branch governance; today HQ uses the same manager tools so operations aren’t duplicated.  
> **Branch managers** own analytics because they finalize approvals and monitor payments.

---

# 20. Final Verdict

| Question | Answer |
|----------|--------|
| Keep as is? | **No** |
| Minor cleanup? | **Insufficient** |
| Major redesign? | **Yes** — decision-oriented layout + formula fixes |
| Backend changes needed? | **Yes** — category revenue, payment buckets, utilization model |
| Frontend-only enough? | **No** |
| Demo-safe? | **Caution** — empty charts, 100% F&B with seed data, hardcoded Tools metrics |

**Overall:** **Partially meaningful, mixed real and misleading derived metrics — redesign recommended before treating as production analytics.**

---

## Public item listing policy (Step 2b)

**Date:** 8 July 2026

**Decision:** Specific vendor item listings are intentionally hidden from the public Carboot Preview (`/marketplace`) and public API (`GET /api/marketplace/items`) until event-day check-in based publishing exists. Vendors retain private item management via `/api/vendor/items`. No dummy data or fake availability was added.

---

## Step 2 Cleanup Applied

**Date:** 8 July 2026

**Summary:** Frontend honesty cleanup for Manager/Admin Insights UI (Step 2). Changes include: removed redundant `Mgr` sidebar badges for users with Insights access; clearer Revenue KPI labels and helper text; honest empty states for category and payment charts; renamed “Revenue by Category” to “Bookings by Category”; split Event Space Snapshot from Profit Simulator with manual-input labeling; removed hardcoded `ImpactDashboard` metrics from Manager Tools in favor of a neutral placeholder message.

**Backend analytics formulas:** Not redesigned in this step. Existing `/boss/analytics/revenue` response and calculations are unchanged.

**Dummy data:** None added. No fake chart values or sample metrics were introduced.
