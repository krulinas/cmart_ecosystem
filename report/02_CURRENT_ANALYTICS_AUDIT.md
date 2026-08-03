# 02 — Current Analytics Audit

**Audit date:** 2026-07-28  
**Method:** Static repository inspection only. No runtime validation, migrations, or tests were executed.

---

## Executive summary

The repository contains **three loosely connected analytics/reporting tracks**:

1. **Post-Event Summary workflow** — Laravel services + Vue Report Centre panels; source-complete but depends on July 2026 migrations that documentation states may not be applied.
2. **Operational boss analytics** — Global revenue + Python word clouds; organizer/super_admin only; not event-scoped.
3. **Vendor self-service analytics** — Per-vendor PHP analytics; separate from organizer hub.

There is **no unified Organizer Analytics Hub page**, **no CSV import pipeline**, and **no event-scoped feedback linkage** in the current schema.

---

## Frontend inventory

### Management shell (organizer uses this as primary workspace)

| Path | Role | API connection | Status |
|------|------|----------------|--------|
| `frontend/src/views/dashboards/AdminDashboard.vue` | Unified `/admin` shell with hash navigation | `GET /management/notifications/unread-count` | **Connected** |
| `frontend/src/config/workspaceNav.js` | Nav sections and capability gates | — | **Connected** |
| `frontend/src/composables/useManagementAccess.js` | Capability checks | — | **Connected** |

### Organizer operational panels (not analytics hub)

| Path | Hash | APIs | Status |
|------|------|------|--------|
| `frontend/src/views/dashboards/organizer/OrganizerBookingsPanel.vue` | `#bookings` | Booking APIs | **Connected** |
| `frontend/src/views/dashboards/organizer/OrganizerEventLayoutPanel.vue` | `#layout` | Layout/site APIs | **Connected** |
| `frontend/src/views/dashboards/organizer/OrganizerItemReservationsPanel.vue` | `#item-reservations` | Item reservation APIs | **Connected** |
| `frontend/src/views/dashboards/staff/StaffEventsPanel.vue` | `#events` | Event APIs | **Connected** |
| `frontend/src/views/dashboards/staff/StaffFeedbackPanel.vue` | `#feedback` | `GET /organizer/feedbacks` | **Connected** (global feedback list, not event hub) |

### Organizer analytics panels (global scope)

| Path | Hash | APIs | Status |
|------|------|------|--------|
| `frontend/src/views/dashboards/boss/BossRevenuePanel.vue` | `#revenue` | `GET /boss/analytics/revenue`, `POST /profitability` | **Connected** — **global**, not per-event |
| `frontend/src/views/dashboards/boss/BossWordCloudPanel.vue` | `#analytics` | `GET /boss/analytics/wordcloud/{feedback\|products}` | **Connected** — requires Python service |
| `frontend/src/views/dashboards/boss/BossAuditLogsPanel.vue` | `#audit` | `GET /boss/audit-logs` | **Connected** — booking approval audit, global |

### Report workflow panels

| Path | Hash | APIs | Status |
|------|------|------|--------|
| `frontend/src/views/dashboards/organizer/OrganizerReportCentrePanel.vue` | `#report-centre` | `frontend/src/services/reportWorkflowApi.js` → `/organizer/report-requests`, `/organizer/generated-reports` | **Connected** (source-level) |
| `frontend/src/views/dashboards/management/CMartReportCentrePanel.vue` | `#reports` | Same service → `/cmart/report-requests`, `/cmart/generated-reports` | **Connected** (source-level) |
| `frontend/src/components/reports/PostEventSummaryView.vue` | — | Receives report prop from parent API | **Connected** (presentation only) |
| `frontend/src/components/reports/ReportNotificationActivity.vue` | — | Timeline from request detail | **Prototype** — labels email/WhatsApp as simulated |

### Vendor analytics

| Path | Route | APIs | Status |
|------|-------|------|--------|
| `frontend/src/views/dashboards/VendorDashboard.vue` | `/dashboard` | `GET /vendor/analytics/me`, `/vendor/bookings` | **Connected** |
| `frontend/src/components/VendorAnalyticsDashboard.vue` | Child | `GET /vendor/analytics/report` (export) | **Connected** |
| `frontend/src/components/VendorEventInsights.vue` | — | Props only | **Disconnected** — never imported; booth data unused |

### Orphaned / placeholder / mock

| Path | Issue |
|------|-------|
| `frontend/src/views/dashboards/UumDashboard.vue` | Static "Planned Module"; `/uum` redirects away — **not routed** |
| `frontend/src/views/dashboards/management/ManagementReportsPanel.vue` | Empty `load()`; retired stub — **not mounted** |
| `frontend/src/components/ImpactDashboard.vue` | Hard-coded metrics (`reusedItems: 12450`, etc.) — **not imported** |
| `frontend/src/components/management/StaffOperationalSnapshot.vue` | Calls `GET /organizer/operations-summary` — **not imported** |

### Public calendar (operational, not hub)

| Path | Notes |
|------|-------|
| `frontend/src/components/EventCalendar.vue` | Public event calendar; unrelated to analytics hub |

---

## Backend inventory

### Report workflow controllers (connected chain)

| Path | Endpoints | Status |
|------|-----------|--------|
| `backend/app/Http/Controllers/Api/CmartReportRequestController.php` | CMart request CRUD/cancel | **Implemented & connected** |
| `backend/app/Http/Controllers/Api/OrganizerReportRequestController.php` | Acknowledge/decline/prepare | **Implemented & connected** |
| `backend/app/Http/Controllers/Api/CmartGeneratedReportController.php` | View published reports, PDF, mark viewed | **Implemented & connected** |
| `backend/app/Http/Controllers/Api/OrganizerGeneratedReportController.php` | Draft lifecycle, publish, revise, PDF | **Implemented & connected** |
| `backend/app/Http/Controllers/Api/CmartReportEventOptionsController.php` | Event picker for CMart requests | **Implemented & connected** |

### Analytics controllers

| Path | Scope | Status |
|------|-------|--------|
| `backend/app/Http/Controllers/Api/BossAnalyticsController.php` | Global revenue + Python wordcloud proxy | **Connected** — not event-scoped |
| `backend/app/Http/Controllers/Api/VendorAnalyticsController.php` | Per authenticated vendor | **Connected** |
| `backend/app/Http/Controllers/AnalyticsController.php` | Blade admin + web proxy to Python | **Connected** — legacy path |
| `backend/app/Http/Controllers/Api/ManagementReportsController.php` | `operational-overview` | **Connected** — live queue telemetry, not generated reports |

### Core services

| Path | Purpose | Status |
|------|---------|--------|
| `backend/app/Services/PostEventSummaryAggregator.php` | Event snapshot JSON (schema v1) | **Implemented** — feedback omitted without `carboot_event_id` |
| `backend/app/Services/ReportDraftService.php` | Draft create/regenerate/revise | **Implemented** |
| `backend/app/Services/ReportPublicationService.php` | Publish, supersede, fulfill request | **Implemented** |
| `backend/app/Services/ReportRequestTransitionService.php` | Request state machine | **Implemented** |
| `backend/app/Services/ReportWorkflowAuditor.php` | Append-only audit | **Implemented** |
| `backend/app/Services/ReportWorkflowRecipientResolver.php` | Notification recipients | **Implemented** — no `is_active` filter |
| `backend/app/Services/ExternalAlertSimulationService.php` | Simulated email/WhatsApp | **Placeholder/mock** — audit only |
| `backend/app/Services/VendorAnalyticsService.php` | Vendor dashboard JSON | **Implemented** — `estimated_sales` = paid invoice total |
| `backend/app/Services/ReportNotificationReadService.php` | Badge filtering | **Implemented** |

### Models

| Path | Table | Notes |
|------|-------|-------|
| `backend/app/Models/ReportRequest.php` | `report_requests` | Event + type + status |
| `backend/app/Models/GeneratedReport.php` | `generated_reports` | JSON `snapshot`, versioning |
| `backend/app/Models/ReportWorkflowAudit.php` | `report_workflow_audits` | 17 action constants |
| `backend/app/Models/Feedback.php` | `feedbacks` | **No `carboot_event_id`** |
| `backend/app/Models/CarbootEvent.php` | `carboot_events` | `reportRequests()`, `generatedReports()` |

### Policies / permissions

- Capability-based: `backend/app/Support/ManagementCapability.php`, `frontend/src/utils/managementCapabilities.js`
- `generated_reports` capability: organizer, cmart_management, super_admin
- `carboot_operational_analytics`: organizer, super_admin only
- CMart Management **cannot** access `/boss/analytics/*` (verified in `backend/tests/Feature/WebAnalyticsSecurityTest.php`)

### Jobs

| Path | Relevance |
|------|-----------|
| `backend/app/Jobs/SendEventAlertEmailsJob.php` | Event alert emails only — **unrelated** to analytics |

**No queued jobs** for report generation, Python analytics, or PDF creation.

### API resources

- `backend/app/Http/Resources/OrganizerGeneratedReportResource.php`
- `backend/app/Http/Resources/CmartGeneratedReportResource.php`
- `backend/app/Http/Resources/CmartReportRequestResource.php`

---

## Python analytics inventory

| Path | Type | Integration | Status |
|------|------|-------------|--------|
| `python_analytics/main.py` | FastAPI service | Laravel HTTP via `config/services.php` | **Connected** (runtime-dependent) |
| `python_analytics/text_analytics.py` | NLP tokenization | Used by `main.py` | **Connected** |
| `python_analytics/generate_analytics.py` | Offline DB → CSV | Not called by Laravel | **Disconnected** |
| `python_analytics/seed_wordcloud_data.py` | Demo seed | Dev only | **Disconnected** |
| `python_analytics/advanced_analytics.py` | Sentiment prototype | Queries non-existent `ratings` column | **Broken / orphaned** |
| `python_analytics/feedback_word_cloud.csv` | Export artifact | `Word,Frequency` schema | **Static output** |
| `python_analytics/vendor_word_cloud.csv` | Export artifact | `Word,Frequency` schema | **Static output** |

### Python endpoints consumed by Laravel

| Endpoint | Data source | Filters |
|----------|-------------|---------|
| `GET /api/analytics/status-summary` | `bookings.approval_status` | Global |
| `GET /api/analytics/wordcloud/feedback` | `feedbacks.comments` | Global; ignores `is_hidden`, ratings, participation |
| `GET /api/analytics/wordcloud/products` | `bookings.product_details` | Approved bookings only; global |

**Config risk:** `backend/config/services.php` defaults analytics URL to port `8000`; `.env.example` and Python README use `8001`.

---

## Database inventory

### Report workflow tables (migrations exist; application docs say may be unapplied)

| Migration | Table |
|-----------|-------|
| `backend/database/migrations/2026_07_23_100001_create_notifications_table.php` | `notifications` |
| `backend/database/migrations/2026_07_23_100002_create_report_requests_table.php` | `report_requests` |
| `backend/database/migrations/2026_07_23_100003_create_generated_reports_table.php` | `generated_reports` |
| `backend/database/migrations/2026_07_23_100004_create_report_workflow_audits_table.php` | `report_workflow_audits` |

### Operational / analytics source tables

| Table | Used for | Event-scoped |
|-------|----------|--------------|
| `carboot_events` | Event metadata | Yes |
| `bookings` | Approvals, categories (`carboot_event_id`, `product_category`, `vendor_category_id`) | Yes |
| `invoices` | Payment amounts/status | Via booking |
| `event_sites` | Site operational status | Yes |
| `vendor_categories` | Category labels | Via booking |
| `item_reservations` | Reuse reservation counts | Yes |
| `vendor_items` | Reuse listings | Vendor-scoped |
| `feedbacks` | Ratings, comments, participation_type, community_backgrounds | **No event FK** |
| `spaces` | Legacy space model; used in global boss revenue | Partial |

### Prior recovery audit note

`report/02_schema_and_data_source_inventory.md` (2026-07-20) documents MariaDB corruption (error 1932) and missing Phase 3+ tables in `cmart_db`. **This audit does not re-verify live DB state** — schema readiness is assessed from migration source files only.

---

## API inventory (analytics & reporting)

### Organizer report workflow (`auth:sanctum`, organizer-equivalent role)

```
GET    /api/organizer/report-requests
GET    /api/organizer/report-requests/{id}
POST   /api/organizer/report-requests/{id}/acknowledge
POST   /api/organizer/report-requests/{id}/start-preparation
POST   /api/organizer/report-requests/{id}/decline
GET    /api/organizer/generated-reports
POST   /api/organizer/generated-reports
GET    /api/organizer/generated-reports/{id}
PATCH  /api/organizer/generated-reports/{id}/narratives
POST   /api/organizer/generated-reports/{id}/regenerate
POST   /api/organizer/generated-reports/{id}/publish
DELETE /api/organizer/generated-reports/{id}
POST   /api/organizer/generated-reports/{id}/revise
GET    /api/organizer/generated-reports/{id}/pdf
```

### CMart report workflow (`role:cmart_management` + `generated_reports` capability)

```
GET    /api/cmart/report-events
GET    /api/cmart/report-requests
POST   /api/cmart/report-requests
GET    /api/cmart/report-requests/{id}
POST   /api/cmart/report-requests/{id}/cancel
GET    /api/cmart/generated-reports
GET    /api/cmart/generated-reports/{id}
GET    /api/cmart/generated-reports/{id}/pdf
POST   /api/cmart/generated-reports/{id}/mark-viewed
```

### Boss operational analytics (`boss` middleware)

```
GET    /api/boss/analytics/revenue
GET    /api/boss/analytics/wordcloud/{feedback|products}
GET    /api/boss/audit-logs
```

### Vendor analytics (`role:community`)

```
GET    /api/vendor/analytics/me
GET    /api/vendor/analytics/report
```

### Feedback (partial analytics relevance)

```
POST   /api/feedback/submit
GET    /api/feedbacks (public summary)
GET    /api/organizer/feedbacks (staff moderation)
```

---

## Report-generation inventory

### Snapshot builder: `PostEventSummaryAggregator`

**Sections in snapshot (`schema_version: 1`):**

| Section | Source | Event-scoped |
|---------|--------|--------------|
| `booking_pipeline` | `bookings.approval_status` counts | Yes |
| `payments` | `invoices` for approved bookings | Yes |
| `event_sites` | `event_sites.operational_status` | Yes |
| `item_reservations` | `item_reservations.reservation_status` | Yes (optional table) |
| `vendor_categories` | Category label distribution | Yes |
| `feedback` | `feedbacks` avg rating + count | **Omitted** — no `carboot_event_id` |

**Honest degradation:** `data_availability` object marks omitted optional metrics; core schema failures abort generation.

### PDF / HTML

- Template: `backend/resources/views/reports/post_event_summary.blade.php`
- Returns **503** if DomPDF not installed (`class_exists` check in controllers)

### Report types

- Only `post_event_summary` in `backend/app/Support/ReportType.php`

---

## Currently connected flows

```
CMart creates request → organizers notified (in-app)
  → organizer acknowledges/declines/prepares
  → organizer generates draft (PostEventSummaryAggregator)
  → organizer edits narratives → publish
  → CMart views published report + PDF + mark viewed
```

```
Organizer opens /admin#revenue → BossRevenuePanel → /boss/analytics/revenue (global)
Organizer opens /admin#analytics → BossWordCloudPanel → Python wordcloud API (global)
Vendor opens /dashboard → VendorAnalyticsService (self only)
```

---

## Incomplete flows

| Flow | Gap |
|------|-----|
| Event-scoped analytics exploration | No event picker on analytics panels |
| Data sufficiency UI before report | Only implicit via aggregator errors / `data_availability` in snapshot |
| Feedback in post-event report | Blocked by schema |
| Survey CSV import | No backend or frontend |
| Notification inbox | Unread count badge only; list/mark-read APIs unused in UI |
| External alerts | Simulated only |
| Runtime validation of report workflow | Documented as still required in `docs/generated-report-workflow-progress.md` |
| Report workflow automated tests | **Zero** feature tests for report endpoints |

---

## Mock / hard-coded outputs

| Location | Nature |
|----------|--------|
| `ExternalAlertSimulationService` | Simulated delivery_mode; never sends |
| `ReportNotificationActivity.vue` | UI disclosure of simulation |
| `ImpactDashboard.vue` | Hard-coded sustainability numbers |
| `UumDashboard.vue` | Placeholder text |
| `VendorAnalyticsService` `estimated_sales` | Proxy for paid invoices, not actual sales |
| `python_analytics/seed_wordcloud_data.py` | Demo data injection |

---

## Unused / duplicated components

| Item | Issue |
|------|-------|
| `ManagementReportsPanel.vue` | Superseded by `CMartReportCentrePanel.vue` |
| `VendorEventInsights.vue` | Built but not mounted |
| `StaffOperationalSnapshot.vue` | Built but not mounted |
| `ImpactDashboard.vue` | Orphaned mock |
| `advanced_analytics.py` | Orphaned broken script |
| Dual analytics entry: Blade `admin/analytics` + Vue boss panels | Overlapping Python proxy paths |

---

## Technical risks

| Risk | Severity | Evidence |
|------|----------|----------|
| Report workflow migrations may not be applied | **Critical** | `docs/generated-report-workflow-progress.md` lines 137–145 |
| Database corruption / missing Phase 3 tables in dev | **Critical** | `report/02_schema_and_data_source_inventory.md` |
| No `feedbacks.carboot_event_id` | **High** | `PostEventSummaryAggregator::feedbackSummary()` |
| Python service port/config mismatch | **Medium** | `config/services.php` vs `.env.example` |
| Global analytics misused for event decisions | **Medium** | `BossAnalyticsController::revenue()` |
| DomPDF optional — PDF 503 | **Medium** | Controller `class_exists` checks |
| No report workflow tests | **Medium** | Test grep |
| Python wordcloud includes hidden feedback | **Low** | `main.py` query |
| `advanced_analytics.py` hardcoded credentials | **Low** (dev only) | Script content |

---

## Test coverage (analytics/reporting)

| Test file | Covers |
|-----------|--------|
| `backend/tests/Feature/WebAnalyticsSecurityTest.php` | Web proxy access boundaries |
| `backend/tests/Feature/GovernanceAccessBoundaryTest.php` | RBAC boundaries |
| `backend/tests/Feature/FeedbackModerationTest.php` | Feedback moderation |
| `backend/tests/Feature/Phase44CompletionAndPublicationGuardTest.php` | Publication guards (related) |

**No dedicated tests** for report request CRUD, draft generation, publish, or CMart consumption endpoints.
