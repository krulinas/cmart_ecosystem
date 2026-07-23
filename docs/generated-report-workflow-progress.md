# Generated Report Workflow Progress

## Canonical Goal

Implement CMart Management ↔ Organizer Post-Event Summary workflow:

CMart requests → Organizer notified (in-app + simulated email/WhatsApp) → Organizer acknowledges/declines/prepares → draft snapshot → publish → CMart notified → CMart views/prints published report.

Organizer may also generate proactive reports (`report_request_id` null).

## Governance Boundary

- **Organizer**: raw analytics, bookings, payments, events, layouts; owns request handling, draft generation, publication, revision.
- **CMart Management**: news/activities; create/cancel requests; shared workspace request visibility; view published reports; no raw analytics, queues, drafts, or publish/revise.
- **Community / public**: no report APIs or pages.
- **Super Admin**: organizer-equivalent technical API access; **not** a default daily notification recipient.

## Locked Product Decisions

1. Organization-wide CMart request visibility (single-venue fallback: all `cmart_management` accounts).
2. Original `requested_by` remains recorded for accountability.
3. Any authorized CMart Management account may cancel while status is `requested`; `cancelled_by` / `cancelled_at` recorded.
4. New requests notify **all** users with exact role `organizer` (not `->first()`, not Super Admin by default).
5. Publication / decline / revision notify **all** active `cmart_management` accounts.
6. Super Admin is technical access only for daily alerts.
7. Report badges count **report-workflow notification types only**.
8. Email and WhatsApp alerts are **simulations only** (`delivery_mode: simulated`; status `simulated` | `skipped`).
9. Simulations never claim sent/delivered and never call external networks.
10. Core schema failures stop generation; optional metrics may be marked unavailable (never fake zero as official).
11. Venue uses centralized resolution (`config/cmart.php` + `CmartVenue`) and is stored in the snapshot.
12. HTML/PDF read venue from the stored snapshot.
13. MySQL `ONLY_FULL_GROUP_BY` / strict mode remains enabled.
14. Published reports remain immutable; revision creates a new version.
15. In-app notification remains the authoritative channel.
16. Duplicate active requests blocked per event + report type for `requested|acknowledged|in_progress`.

## Existing Architecture Discovered

Prior “Generated Reports” was live queue telemetry via `StaffOperationsController::operationsSummary`. Retired from CMart UI; Organizer-only operational overview retained.

## Runtime Bug Record — Generate Draft SQL

| Field | Detail |
| --- | --- |
| Error | `SQLSTATE[42000]: 1055 'bookings.category_label_snapshot' isn't in GROUP BY` |
| Root cause | Category aggregation used `COALESCE(...)` with separate bound parameters in SELECT vs GROUP BY under MySQL `ONLY_FULL_GROUP_BY` |
| Service | `PostEventSummaryAggregator::vendorCategoryDistribution` |
| Fix | Derived subquery resolving `resolved_category`, then outer `GROUP BY resolved_category` |
| Fallback preserved | `category_label_snapshot` → `vendor_categories.label` → `product_category` → `Uncategorised` |
| Strict mode | Preserved (not disabled) |
| Failure behaviour | Core aggregation errors abort draft generation inside the draft transaction (no partial draft / inconsistent request state) |
| Runtime validation | Still required |

## Phase A — CMart Portal Repair

Complete (source-level). See prior entries: panel mount gating, toast dedupe, identity sanitization, operational-overview restricted, Report Centre nav.

## Phase B–E — Report Workflow Foundation

Complete (source-level). Requests, drafts, publish, versioning, PDF/print, in-app notifications.

## Repair Task — Runtime Correctness (this update)

Complete (source-level): SQL repair, shared CMart visibility, recipient resolver, external alert simulation, badge filtering, core schema integrity, venue centralization, CMart identity presenter cleanup, timelines.

## CMart Request Visibility

| Rule | Value |
| --- | --- |
| Previous | `requested_by = auth user` only |
| Final | All `cmart_management` accounts share the request list (single-venue workspace fallback) |
| Creator | Still stored in `requested_by` |
| Cancel | Any CMart Management account while `requested`; records `cancelled_by` / `cancelled_at` |

## Notification Recipient Rules

| Event | In-app recipients |
| --- | --- |
| Request created | All users with role `organizer` |
| Acknowledge | All `cmart_management` |
| Decline | All `cmart_management` |
| Publish / revision publish | All `cmart_management` |
| Super Admin | Not default daily recipient |
| Active-status field | None on `users` — all matching role rows are treated as recipients (documented limitation) |

## External Email and WhatsApp Simulation

| Item | Rule |
| --- | --- |
| Events | request created; decline; publish; revision publish |
| Storage | `report_workflow_audits` actions `external_alert_simulated` / `external_alert_skipped` |
| Status values | `delivery_mode: simulated`, `status: simulated\|skipped` |
| Contact sources | `users.email`, `users.phone_number` |
| Missing contact | Skipped record with honest reason; does not fail workflow |
| Masking | `or***@domain`, `+•••1234` |
| UI disclosure | “Email and WhatsApp alerts shown here are simulations… No external message was sent.” |
| Real send | None |

## Report Badge Filtering

| Item | Rule |
| --- | --- |
| Previous | All unread DB notifications |
| Final | Backend-filtered by `ReportNotificationType` |
| Organizer badge types | `report_request_created` |
| CMart badge types | `report_request_acknowledged`, `report_request_declined`, `report_published`, `report_revised` |
| Read behaviour | Opening related request/report marks matching report notifications read; opening Report Centre does **not** mark all unread |

## Core Schema Integrity

| Classification | Tables / behaviour |
| --- | --- |
| Core (abort if missing) | `carboot_events`, `bookings`, `invoices`, `event_sites`, `vendor_categories` + required booking/invoice columns |
| Optional | `item_reservations`, event-scoped `feedbacks.carboot_event_id` → `data_availability` omit |

## Venue Resolution

| Order | Source |
| --- | --- |
| 1 | Event venue/location fields if present |
| 2 | `config('cmart.default_venue_name')` |
| 3 | Literal fallback inside `CmartVenue` only |
| Snapshot | Stores `venue` and `event.venue` at generation time |
| HTML/PDF | Read from snapshot |

## CMart Identity Cleanup

| Visible label | Source |
| --- | --- |
| Sidebar role line | Frontend `roleDisplayLabel` → always `CMart Management` for that role |
| Role badge | Theme `roleBadge` → `CMart Management` |
| Department chip | `UserAuthPresenter` sanitizes Organizer residue → `Venue & Activities`; AdminDashboard `shellDepartment` fallback |
| Branch / venue | Theme `venueLabel` / sanitized `branch_name` → CMart Changlun (or profile branch if not Main Branch) |
| Brand title/subtitle | Theme `CMart` / `Venue & Activities` |

## Database and Migration Impact

Migrations created earlier (still **not executed**):

1. `backend/database/migrations/2026_07_23_100001_create_notifications_table.php`
2. `backend/database/migrations/2026_07_23_100002_create_report_requests_table.php`
3. `backend/database/migrations/2026_07_23_100003_create_generated_reports_table.php`
4. `backend/database/migrations/2026_07_23_100004_create_report_workflow_audits_table.php`

This repair task created **no new migration**.

## Access-Control Matrix

| Action | Organizer | Super Admin | CMart Management | Community |
| --- | --- | --- | --- | --- |
| Create/list/cancel requests | No / API org-eq only | Technical API | Yes (shared list) | No |
| Ack/decline/prepare | Yes | Yes (technical) | No | No |
| Draft/publish/revise | Yes | Yes (technical) | No | No |
| View published | Yes | Yes | Yes | No |
| Daily request notify | Yes | No (default) | N/A | No |
| Raw analytics / bookings | Yes | Yes | No | No |

## Exact Files Changed

| File | Change | Phase | Runtime validation required |
| --- | --- | --- | --- |
| `docs/generated-report-workflow-progress.md` | Canonical ledger | All | No |
| `backend/config/cmart.php` | Central venue config | Repair G | Yes |
| `backend/app/Support/CmartVenue.php` | Venue resolver | Repair G | Yes |
| `backend/app/Support/ReportType.php` | Report type constants | B | No |
| `backend/app/Support/ReportRequestStatus.php` | Request lifecycle | B | Yes |
| `backend/app/Support/GeneratedReportStatus.php` | Report lifecycle | B | Yes |
| `backend/app/Support/ReportNotificationType.php` | Badge/type IDs | Repair F | Yes |
| `backend/app/Models/ReportRequest.php` | Request model | B | Yes |
| `backend/app/Models/GeneratedReport.php` | Report model | B | Yes |
| `backend/app/Models/ReportWorkflowAudit.php` | Audit + simulation actions | B/E/Repair C | Yes |
| `backend/app/Models/CarbootEvent.php` | Report relations | B | No |
| `backend/database/migrations/2026_07_23_100001_create_notifications_table.php` | Notifications table | B | Yes (migrate later) |
| `backend/database/migrations/2026_07_23_100002_create_report_requests_table.php` | Requests table | B | Yes (migrate later) |
| `backend/database/migrations/2026_07_23_100003_create_generated_reports_table.php` | Reports table | B | Yes (migrate later) |
| `backend/database/migrations/2026_07_23_100004_create_report_workflow_audits_table.php` | Audits table | B | Yes (migrate later) |
| `backend/app/Services/PostEventSummaryAggregator.php` | Snapshot + SQL fix + core schema | C/Repair A/B | Yes |
| `backend/app/Services/ReportDraftService.php` | Draft/revision | C | Yes |
| `backend/app/Services/ReportPublicationService.php` | Publish + CMart notify + simulation hook | D/Repair C | Yes |
| `backend/app/Services/ReportRequestTransitionService.php` | Status transitions | B | Yes |
| `backend/app/Services/ReportWorkflowAuditor.php` | Audit writer | B | Yes |
| `backend/app/Services/ReportWorkflowRecipientResolver.php` | Role recipient sets | Repair 2/3 | Yes |
| `backend/app/Services/ExternalAlertSimulationService.php` | Email/WA simulation | Repair C | Yes |
| `backend/app/Services/ReportWorkflowTimelinePresenter.php` | Timelines | Repair D | Yes |
| `backend/app/Services/ReportNotificationReadService.php` | Selective mark-read | Repair F | Yes |
| `backend/app/Services/UserAuthPresenter.php` | CMart profile sanitization | Repair E | Yes |
| `backend/app/Notifications/ReportRequestCreatedNotification.php` | In-app create | B | Yes |
| `backend/app/Notifications/ReportRequestAcknowledgedNotification.php` | In-app ack | B | Yes |
| `backend/app/Notifications/ReportRequestDeclinedNotification.php` | In-app decline | B | Yes |
| `backend/app/Notifications/ReportPublishedNotification.php` | In-app publish/revise | D/Repair | Yes |
| `backend/app/Http/Controllers/Api/CmartReportRequestController.php` | CMart requests + shared visibility | B/Repair 1 | Yes |
| `backend/app/Http/Controllers/Api/CmartReportEventOptionsController.php` | Safe event picker | B | Yes |
| `backend/app/Http/Controllers/Api/CmartGeneratedReportController.php` | CMart published consume/PDF | D | Yes |
| `backend/app/Http/Controllers/Api/OrganizerReportRequestController.php` | Organizer request actions | B/Repair | Yes |
| `backend/app/Http/Controllers/Api/OrganizerGeneratedReportController.php` | Draft/publish/revise + SQL error handling | C/D/Repair A | Yes |
| `backend/app/Http/Controllers/Api/ManagementNotificationController.php` | Filtered unread counts | E/Repair F | Yes |
| `backend/app/Http/Controllers/Api/ManagementReportsController.php` | Operational overview doc | A | No |
| `backend/app/Http/Resources/CmartReportRequestResource.php` | CMart request + timeline | B/Repair D | Yes |
| `backend/app/Http/Resources/OrganizerReportRequestResource.php` | Organizer request + timeline | B/Repair D | Yes |
| `backend/app/Http/Resources/CmartGeneratedReportResource.php` | CMart published payload | D | Yes |
| `backend/app/Http/Resources/OrganizerGeneratedReportResource.php` | Organizer draft/published payload | C | Yes |
| `backend/resources/views/reports/post_event_summary.blade.php` | PDF from snapshot venue | E/Repair G | Yes |
| `backend/routes/api.php` | Report + notification routes | B–E | Yes |
| `backend/tests/Feature/GovernanceAccessBoundaryTest.php` | operational-overview Forbidden for CMart | A | Later (not run) |
| `frontend/src/views/dashboards/AdminDashboard.vue` | Panel gating + Report Centres + shell department | A/Repair E | Yes |
| `frontend/src/layouts/WorkspaceShell.vue` | Report badge slot | E/Repair F | Yes |
| `frontend/src/config/workspaceNav.js` | Reports + Report Centre nav | A/E | Yes |
| `frontend/src/config/managementWorkspaceTheme.js` | CMart/Organizer branding | A/E | Yes |
| `frontend/src/utils/managementRoles.js` | CMart display identity | A/Repair E | Yes |
| `frontend/src/composables/useManagementAccess.js` | `canAccessGeneratedReports` | A | No |
| `frontend/src/services/reportWorkflowApi.js` | Report APIs client | B–E | Yes |
| `frontend/src/components/reports/PostEventSummaryView.vue` | Snapshot HTML preview | C/Repair G | Yes |
| `frontend/src/components/reports/ReportNotificationActivity.vue` | Simulation disclosure UI | Repair C | Yes |
| `frontend/src/views/dashboards/management/CMartReportCentrePanel.vue` | CMart Report Centre | B/D/Repair | Yes |
| `frontend/src/views/dashboards/organizer/OrganizerReportCentrePanel.vue` | Organizer Report Centre | B–E/Repair | Yes |
| `frontend/src/views/dashboards/management/ManagementReportsPanel.vue` | Retired stub | A | No |
| `frontend/src/views/dashboards/organizer/OrganizerEventLayoutPanel.vue` | 403 toast guard | A | Yes |
| `frontend/src/views/dashboards/organizer/OrganizerItemReservationsPanel.vue` | 403 toast guard | A | Yes |
| `frontend/src/assets/main.css` | Print styles | E | Yes |

## Deferred Work

- Event-scoped feedback distributions / participation & community-background aggregates
- Walk-in vendor counts
- Sustainability / ESG inventions
- Real email / WhatsApp integrations
- Richer site utilization % from occupancy rules
- Typed notification inbox UI beyond badge + timeline

## Assumptions Made

- Single-venue CMart Management workspace (no venue assignment table found).
- No `users.is_active` field — all role matches receive alerts.
- Venue fallback centralized via `config/cmart.php`.

## Blocking Decisions

None

## Known Risks / Remaining Risks

- Feature remains inert until the four migrations are applied.
- Unread badge filter scans unread notification collection in PHP (fine for small volumes).
- Simulation metadata volume grows with recipient count × channels.
- Generate Draft / Publish still require manual runtime confirmation after this repair.

## Validation Status

Implemented at source level.  
Runtime validation pending.  
Automated validation not run.

Automated testing, linting, builds, migrations, database commands, real email delivery, real WhatsApp delivery, PDF execution, and E2E validation were not run because implementation and testing are being handled as separate stages.

## Next Recommended Step

Manual validation sequence:

```text
Generate Draft
→ Preview
→ Publish
→ CMart notification activity
→ CMart view
→ Print/PDF
```
