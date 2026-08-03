# 01 — Project Direction

**Audit date:** 2026-07-28

---

## System problem statement

Carboot@CMart runs recurring carboot sale events involving vendors, visitors, organizers, and CMart management. Organizers need to understand **what happened at each event** — participation levels, vendor mix, payments, reuse activity, community feedback, and operational issues — and produce **evidence-based reports** for CMart stakeholders.

The original proposal referenced Power BI. The **current implementation direction** (evidenced by `python_analytics/`, `BossAnalyticsController`, and Laravel report services) is:

- **Laravel** — application, API, database integration, authorization, organizer/CMart UI, report workflow orchestration
- **Python** — analytics engine for text processing, aggregations, and (future) survey analytics beyond simple SQL

Today the system solves **operational event management** (bookings, layouts, payments, feedback moderation) and has begun a **Post-Event Summary report lifecycle**, but lacks a unified **Organizer Analytics and Reporting Hub**.

---

## Target users

| User | Primary need |
|------|--------------|
| **Organizer** | Event-scoped analytics, insight exploration, draft/publish reports, respond to CMart requests |
| **CMart Management** | Request post-event reports, view published summaries, no raw operational analytics |
| **Vendor (community)** | Personal booth analytics only — not the hub audience |
| **Super Admin** | Technical/organizer-equivalent access; not default notification recipient |
| **Public / visitors** | Submit feedback; no analytics access |

Governance is documented in `docs/generated-report-workflow-progress.md` and enforced via `ManagementCapability` / route middleware.

---

## Organizer-first analytics direction

The hub should be **event-centric**:

1. Organizer selects an event (or receives a CMart report request for a specific event).
2. System assesses **data completeness** for that event.
3. Organizer views an **analytics overview** and drills into vendor, economic, feedback, reuse, and operational dimensions.
4. Organizer generates a **versioned event report** with narratives.
5. Published report flows to CMart through the existing request/fulfil lifecycle.

**Current state:** Steps 4–5 are largely implemented at source level for one report type (`post_event_summary`). Steps 2–3 have no dedicated hub; analytics panels are mostly **global**, not event-scoped.

---

## Role of Laravel

| Responsibility | Current evidence |
|----------------|------------------|
| Auth & RBAC | `ManagementRole`, `EnsureBossOnly`, `managementCapabilities.js` |
| CRUD & workflows | Bookings, events, layouts, item reservations, feedback moderation |
| Report request lifecycle | `ReportRequestTransitionService`, `OrganizerReportRequestController`, `CmartReportRequestController` |
| Snapshot aggregation (PHP) | `PostEventSummaryAggregator` |
| API for Vue dashboards | `routes/api.php`, `reportWorkflowApi.js` |
| In-app notifications | Laravel `notifications` table (migration exists; execution status uncertain) |
| PDF rendering | `resources/views/reports/post_event_summary.blade.php` via DomPDF (optional dependency) |

Laravel should remain the **system of record**, **authorization boundary**, and **UI/API gateway**.

---

## Role of Python

| Responsibility | Current evidence |
|----------------|------------------|
| FastAPI microservice | `python_analytics/main.py` on port 8001 |
| Word-cloud analytics | `/api/analytics/wordcloud/{feedback\|products}` |
| Booking status summary | `/api/analytics/status-summary` |
| Offline CSV export (not integrated) | `python_analytics/generate_analytics.py` → `Word,Frequency` CSVs |

Python is **connected** for word clouds and status summary via `BossAnalyticsController` and `AnalyticsController` proxy. It is **not yet** the engine for event-scoped survey analytics, CSV validation, or report snapshot enrichment.

**Intended future role:** validate/import survey CSVs, compute derived metrics, write `analytics_results` (proposed) back for Laravel to expose.

---

## Intended decision-support value

Organizers should be able to answer:

- How many vendors participated and in which categories?
- What revenue was collected vs outstanding for the event?
- How did visitors rate the event and what themes appear in feedback?
- What reuse/reservation activity occurred?
- What operational or satisfaction issues should inform the next event?

Reports should support **CMart accountability** with immutable published snapshots, audit trails, and honest `data_availability` flags when metrics cannot be computed.

---

## MVP boundary

**In scope for MVP hub (proposed):**

- Event selector and data-completeness panel
- Event-scoped operational metrics (bookings, payments, sites, categories, item reservations)
- Event-scoped feedback aggregates (after `feedbacks.carboot_event_id`)
- Post-Event Summary draft → publish workflow (existing)
- CMart request → fulfil lifecycle (existing)
- Basic Python text analytics (word clouds) optionally scoped by event

**Out of MVP (defer):**

- Power BI integration
- Real email/WhatsApp delivery (currently simulated)
- ESG scoring models without operational data
- Exact revenue inference from categorical sales-range survey fields
- Anomaly detection / ML forecasting
- UUM read-only oversight dashboard (`UumDashboard.vue` is a placeholder)

---

## Future scope

- CSV upload of post-event vendor/participant survey responses with schema validation
- Participation-type and community-background distributions per event
- Promotional channel and discovery-source metrics (requires new survey fields)
- Gross sales range and % items sold (categorical survey data — band analysis only)
- Sustainability indicators derived from reuse listings and item reservations
- Report types beyond `post_event_summary`
- Notification inbox UI (API exists; list UI missing)
- Background jobs for heavy Python analytics

---

## Explicit non-goals (this audit cycle)

- Implementing new features or running migrations
- Running automated tests, builds, or E2E
- Modifying production application code
- Inventing metrics without data support
- Replacing Laravel with a separate analytics frontend
- Converting categorical sales ranges into exact RM revenue without documented methodology
