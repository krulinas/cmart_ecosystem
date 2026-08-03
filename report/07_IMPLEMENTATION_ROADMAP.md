# 07 — Implementation Roadmap

**Audit date:** 2026-07-28  
**Amended:** 2026-08-02 — parallel Track A / Track B; schema contract gate before `survey_responses`  
**Amended:** 2026-08-02 (pm) — first vertical slice implemented in source (see progress overlay)

Dependency-aware plan for the Organizer Analytics and Reporting Hub. **Automated test execution is not included** unless explicitly approved in a later stage.

### Vertical-slice progress overlay (2026-08-02)

| Task | Status |
|------|--------|
| P0-01 DB health | **Runtime verified** — local `cmart_db_rebuild`; no 1932 |
| P0-02 / P2-09 port align | **Runtime verified** — analytics on `8001` |
| P0-03 / P0-03a schema contract | **Done** — `09_SURVEY_SCHEMA_CONTRACT.md` |
| P1-A01 feedbacks FK migration | **Runtime verified** — nullable column present |
| P1-A02 feedback submit capture | **Incomplete** — **next recommended task** |
| P1-B00–B04 survey validate + import | **Runtime verified after fix** — sample CSV 9/9 on event 5; `python-multipart` added |
| P1-B05 dedicated data-readiness route | **Merged into** analytics overview `data_readiness` (5/5 ready after import) |
| P2-01–P2-04, P2-06–P2-07 analytics | **Runtime verified** — overview/sections/recompute; cache row written |
| P2-05 event wordcloud | **Implemented** — optional `event_id` (column now exists; not smoke-focused) |
| P3-01–P3-10 hub UI + import | **Runtime verified** (wiring + API contract); interactive browser pass incomplete |
| P4-02 / P4-05 report hooks | **Runtime verified** — draft snapshot `vendor_survey` n=9; CTA preselect wired in source |
| P2-08 / P3-12 orphan cleanup | **Deferred** |
| Async `ProcessSurveyImportJob` | **Deferred** — sync path used |

Full detail: [`10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md`](10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md).

### Parallel tracks after P0-01

| Track | Focus | Gap IDs |
|-------|-------|---------|
| **Track A** | Live community `feedbacks` event linkage | G-03, G-11 |
| **Track B** | Vendor survey CSV ingestion & analytics (`vendor_post_event_v1`) | G-05–G-08, G-10, G-10b, G-12, G-14 proxies |

Tracks may proceed in parallel after database health is confirmed.

**Hard gate:** `P1-05` (`survey_responses` migration) **must not begin** until `report/09_SURVEY_SCHEMA_CONTRACT.md` defines the complete normalized mapping (status: **complete as of 2026-08-02**). Further enum changes require a schema-version bump (`vendor_post_event_v2`), not silent alteration of v1.

---

## Phase 0 — Audit resolution and architecture decisions

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P0-01 | Validate database health and migration state in target environment | `backend/database/migrations/2026_07_23_*`, env config | Access to target DB | Confirmed list of applied vs pending migrations | Report workflow tables exist and are readable; no 1932 errors | **Critical** — blocked on corrupt DB | **P0** |
| P0-02 | Confirm analytics service connectivity | `backend/config/services.php`, `backend/.env`, `python_analytics/.env` | P0-01 | Python reachable on agreed port with matching API key | Wordcloud endpoint returns 200 from Laravel proxy | Port mismatch causes 502 | **P0** |
| P0-03 | Survey schema reconciliation & stakeholder ack of `vendor_post_event_v1` | `report/09_SURVEY_SCHEMA_CONTRACT.md`, `report/08_DECISION_LOG.md` | External template + sample CSV | Contract approved; Codebook enums locked for v1 | Decision log marks Q-01/Q-02 resolved; observed≠exhaustive noted | Scope creep if enums reinvented | **P0** |
| P0-03a | *(Gate)* Confirm normalized mapping complete before storage migration | `09_SURVEY_SCHEMA_CONTRACT.md` §5 | P0-03 | Mapping frozen for v1 | P1-05 may start only after this gate | — | **P0** |
| P0-04 | Confirm MVP metric set (ops DB + survey-after-import) | `04_DATA_AND_METRICS_MAPPING.md`, `05_GAP_ANALYSIS.md` | P0-03 | Prioritized metric list for Phases 1–3 | Stakeholders agree genuinely unavailable metrics stay out | — | **P0** |
| P0-05 | Install/verify DomPDF for PDF path | `composer.json`, report controllers | P0-01 | PDF endpoints return file not 503 | Organizer + CMart PDF download works | Optional dep today | **P1** |

---

## Phase 1 — Dual foundation (Track A + Track B)

### Track A — Community feedback event linkage

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P1-A01 | Add `feedbacks.carboot_event_id` migration | New migration, `Feedback.php`, `FeedbackController.php` | P0-01 | Nullable FK to `carboot_events` | Column exists; existing rows nullable | Backfill ambiguity | **P0** |
| P1-A02 | Capture event context on feedback submit | `CommunityFeedback.vue`, `FeedbackController@store` | P1-A01 | New feedback linked when context available | Submitted feedback has `carboot_event_id` when provided | Wrong event attribution | **P0** |
| P1-A03 | Extend `PostEventSummaryAggregator` community feedback section | `PostEventSummaryAggregator.php`, `PostEventSummaryView.vue` | P1-A01 | Event-scoped community `response_count`, `average_rating` | Snapshot shows feedback when column exists | — | **P0** |

*(Legacy IDs P1-01…P1-03 map to P1-A01…P1-A03.)*

### Track B — Vendor survey CSV ingestion

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P1-B00 | Implement validator against `vendor_post_event_v1` contract | `python_analytics/survey_schema.py`, `validate_survey_csv.py` | **P0-03a** (contract complete) | Row-level validation for 53 columns + exclusivity rules | Sample CSV validates; unknown enums rejected | Enum drift | **P0** |
| P1-B01 | Create `raw_survey_uploads` migration + model | New migration, model, policy | P0-01, P0-03a | Raw file + batch metadata + `schema_version` | Upload record with `carboot_event_id` from route | — | **P1** |
| P1-B02 | Create `survey_responses` migration + model | New migration, model | **P0-03a**, P1-B01 | Hybrid columns per §5 of schema contract | Valid rows persist; unique `(import_batch_id, respondent_id)` | Schema churn if started early | **P1** |
| P1-B03 | Survey CSV upload API | `OrganizerSurveyImportController.php`, `routes/api.php` | P1-B01, P1-B02 | `POST /organizer/events/{id}/survey-imports` | Event id from route; raw file retained; source_row_number stored | Large files | **P1** |
| P1-B04 | Wire Laravel → Python validation job | `ProcessSurveyImportJob.php` | P1-B00, P1-B03 | Status pending→completed/failed | Organizer sees valid/error counts; validation columns not treated as answers | Queue not running | **P1** |
| P1-B05 | Data readiness API (ops + survey batch status) | `OrganizerEventDataReadinessController.php` | P1-A01, P1-B01 | `GET /organizer/events/{id}/data-readiness` | Checklist includes survey import state | — | **P1** |

*(Legacy P1-04…P1-09 map into P1-B01…P1-B05 / P1-B00; **P1-05 ≡ P1-B02** gated by P0-03a.)*

---

## Phase 2 — Python analytics engine

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P2-01 | Create `analytics_results` migration | New migration, model | P0-01 | Cached metric storage per event | Rows keyed by event + metric_key | Stale cache | **P1** |
| P2-02 | Event-scoped aggregation endpoint (ops + survey) | `python_analytics/main.py`, new `event_aggregations.py` | P1-B02, P1-A01 | `POST /api/analytics/aggregate/event/{id}` | Returns JSON metrics bundle | Performance on large events | **P1** |
| P2-03 | Community participation/background distributions | Python aggregation module | P1-A01, P2-02 | Per-event breakdowns | Matches SQL spot-checks | Small-n privacy | **P1** |
| P2-04 | Survey band & multi-select aggregations (Q1–Q13) | Python module | P1-B02, P2-02 | Categorical histograms only | No RM conversion; uses Codebook enums | Misinterpretation | **P1** |
| P2-05 | Event-scoped community wordcloud | `python_analytics/main.py` | P1-A01 | `?event_id=` filter on feedback wordcloud | Terms limited to event feedback | Low volume events | **P2** |
| P2-06 | Filter hidden feedback in Python | `python_analytics/main.py` | — | `is_hidden = 0` in queries | Hidden rows excluded | — | **P2** |
| P2-07 | Laravel recompute trigger | `OrganizerEventAnalyticsController.php` | P2-02 | `POST .../analytics/recompute` | Refreshes `analytics_results` | Long-running job | **P2** |
| P2-08 | Repair or remove `advanced_analytics.py` | `python_analytics/advanced_analytics.py` | — | No broken scripts in repo | Script runs or is deleted | — | **P3** |
| P2-09 | Align `config/services.php` default port | `backend/config/services.php` | — | Default `8001` | Matches `.env.example` | — | **P2** |

---

## Phase 3 — Organizer analytics dashboard

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P3-01 | Event analytics context composable | `frontend/src/composables/useEventAnalyticsContext.js` | P1-B05 | Shared selected event state | Persists across hub tabs | — | **P0** |
| P3-02 | Event Analytics Hub panel | `OrganizerEventAnalyticsPanel.vue` | P3-01, P2-02 | `/admin#event-analytics` | Overview KPIs load for selected event | — | **P0** |
| P3-03 | Hub API service module | `frontend/src/services/eventAnalyticsApi.js` | P1-B05, organizer analytics endpoints | Centralized API calls | All tabs use service | — | **P1** |
| P3-04 | Vendors tab | Hub sub-components | P3-02 | Category distribution chart | Matches snapshot data | — | **P1** |
| P3-05 | Economics tab | Hub sub-components | P3-02 | Invoice summary for event | Matches `payments` snapshot section | — | **P1** |
| P3-06 | Feedback tab (community + vendor survey summary) | Hub sub-components | P1-A03, P2-03, P2-04 | Ratings + participation + Q9 summary | Suppressed when n < 5 | Privacy | **P1** |
| P3-07 | Reuse tab (reservations + Q2/Q7 proxies) | Hub sub-components | P3-02, P2-04 | Item reservation counts + survey circularity | Matches snapshot + survey | — | **P2** |
| P3-08 | Operations tab | Hub sub-components | P2-05, P2-04 | Word cloud + Q3/Q10 | Event-scoped themes | — | **P2** |
| P3-09 | CSV upload UI | Hub import wizard component | P1-B03, P1-B04 | Upload + error report download | Organizer sees validation results | UX complexity | **P1** |
| P3-10 | Nav integration | `workspaceNav.js`, `AdminDashboard.vue` | P3-02 | Hub link in organizer nav | Capability-gated visible | — | **P1** |
| P3-11 | Refactor boss revenue to optional event filter | `BossAnalyticsController.php`, `BossRevenuePanel.vue` | P3-01 | Global default + event override | Filter param works | Breaking change | **P2** |
| P3-12 | Remove or wire orphaned components | `ImpactDashboard.vue`, `VendorEventInsights.vue`, `StaffOperationalSnapshot.vue` | — | No dead mock UI in repo | Components mounted or deleted | — | **P3** |

---

## Phase 4 — Generated report workflow

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P4-01 | Bump snapshot schema to v2 | `PostEventSummaryAggregator.php` | P1-A03, P2-04 | New sections in snapshot | `schema_version: 2` | Backward compat | **P1** |
| P4-02 | Merge analytics_results into snapshot | `PostEventSummaryAggregator.php` | P2-01, P4-01 | Draft includes survey + participation sections | Published report immutable copy | Stale analytics | **P1** |
| P4-03 | Enhance PostEventSummaryView | `PostEventSummaryView.vue` | P4-01 | Renders v1 and v2 sections | Graceful fallback for old reports | — | **P1** |
| P4-04 | Add attendance/check-in to snapshot | `PostEventSummaryAggregator.php` | — | Check-in rate in snapshot | Uses `checked_in_at` | — | **P2** |
| P4-05 | Hub → Report Centre deep link | `OrganizerEventAnalyticsPanel.vue`, `OrganizerReportCentrePanel.vue` | P3-02 | Pre-selected event on generate | One-click draft creation | — | **P2** |
| P4-06 | PDF template v2 sections | `post_event_summary.blade.php` | P4-01 | PDF shows new metrics | Print layout acceptable | — | **P2** |

---

## Phase 5 — Organizer-to-CMart lifecycle

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P5-01 | Runtime validation checklist (manual) | `docs/generated-report-workflow-progress.md` | P0-01, P4-02 | Documented smoke-test results | Full request→publish→CMart view path verified | Env-specific | **P0** |
| P5-02 | Notification inbox UI | New panel or Report Centre tab | Existing notification APIs | List + mark read | Uses `reportWorkflowApi.js` list endpoints | — | **P2** |
| P5-03 | Audit action for survey import | `ReportWorkflowAudit.php`, auditor | P1-B04 | Import events in timeline | Visible in request/report timeline | — | **P3** |
| P5-04 | Optional real email notifications | `ExternalAlertSimulationService` → real mailer | Mail config decision | Config flag toggles real send | Simulated remains default in dev | Spam/misdelivery | **P3** |
| P5-05 | CMart report view enriches with data availability | `CMartReportCentrePanel.vue` | P4-01 | CMart sees honest omission notes | No fake metrics displayed | — | **P2** |

---

## Phase 6 — Usability evaluation and refinement

| Task ID | Objective | Affected files | Prerequisites | Expected result | Acceptance criteria | Risk | Priority |
|---------|-----------|----------------|---------------|-----------------|---------------------|------|----------|
| P6-01 | Organizer usability walkthrough | — | Phases 3–5 complete | Findings document | 9-step journey ≥80% complete | — | **P1** |
| P6-02 | Small-n suppression tuning | API + hub charts | P3-06 | Threshold configurable | No identifiable cells in reports | Over-suppression | **P2** |
| P6-03 | Cross-event trend views | Hub + Python | 2+ events with data | Month-over-month comparison | Organizer can compare two events | Low data volume | **P3** |
| P6-04 | Performance profiling large CSV imports | Python + queue | P1-B04 | Import < 30s for N rows | Documented limits | Timeout | **P2** |
| P6-05 | Documentation update | `docs/generated-report-workflow-progress.md`, `report/00_README.md` | All phases | Dev setup guide for analytics hub | New developer can run hub locally | — | **P2** |

---

## Recommended execution order (first 10 tasks)

1. **P0-01** — Validate DB health and migration state (**first**)  
2. **P0-03 / P0-03a** — Acknowledge `vendor_post_event_v1` contract (gate before P1-B02)  
3. **P1-A01** — Add `feedbacks.carboot_event_id` (Track A)  
4. **P1-B00** — Python validator for `vendor_post_event_v1` (Track B, parallel with A)  
5. **P1-A02** — Capture event context on community feedback submit  
6. **P1-B01** — `raw_survey_uploads` migration  
7. **P1-B02** — `survey_responses` migration (**only after P0-03a**)  
8. **P1-A03** — Aggregator community feedback section  
9. **P1-B03** — Survey CSV upload API (`carboot_event_id` from route)  
10. **P1-B04** — Import job wiring Laravel ↔ Python validation  

---

## Phase completion gates

| Phase | Gate |
|-------|------|
| Phase 0 | DB healthy; `09_SURVEY_SCHEMA_CONTRACT` acknowledged; Python reachable |
| Phase 1 | Track A: event-linked community feedback in snapshot; Track B: CSV upload stores validated rows |
| Phase 2 | `analytics_results` populated for test event (ops + survey) |
| Phase 3 | Organizer can select event and view hub tabs including survey metrics |
| Phase 4 | v2 snapshot publishes through existing workflow |
| Phase 5 | CMart receives enriched report; lifecycle smoke-tested |
| Phase 6 | Usability findings addressed or deferred with rationale |

---

## Out of scope for this roadmap

- Automated PHPUnit / frontend test suites (unless later approved)
- Power BI integration
- ESG scoring models without data
- Multi-venue expansion
