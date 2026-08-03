# 05 — Gap Analysis

**Audit date:** 2026-07-28  
**Amended:** 2026-08-02 — survey CSV reconciliation (`09_SURVEY_SCHEMA_CONTRACT.md`)

Compares **intended Organizer Analytics and Reporting Hub requirements** against **current implementation**, with evidence, severity, dependencies, and recommended actions.

**Severity:** Critical | High | Medium | Low

**Gap class legend (survey-related):**

| Class | Meaning |
|-------|---------|
| **Missing collection** | Instrument / DB has no field |
| **External CSV present** | Field exists in `vendor_post_event_v1` outside the app |
| **Ingestion/storage** | Cannot load or persist CSV into Laravel |
| **Analytics calculation** | Data could exist but no aggregation/API/UI |
| **Event attribution** | Cannot safely scope to `carboot_event_id` |

---

## Gap register

| ID | Intended requirement | Current implementation | Evidence | Gap | Class | Severity | Dependency | Recommended action |
|----|---------------------|------------------------|----------|-----|-------|----------|------------|-------------------|
| G-01 | Unified Organizer Analytics Hub with event selector | Fragmented panels (`#revenue`, `#analytics`, `#report-centre`); no hub page | `AdminDashboard.vue`, `workspaceNav.js` — no `#event-analytics` | No cohesive exploration surface | Analytics calculation | **Critical** | Event-scoped APIs | Phase 3: `OrganizerEventAnalyticsPanel.vue` + event context |
| G-02 | Event-scoped operational analytics | Boss revenue/wordcloud are global | `BossAnalyticsController::revenue()` — no `carboot_event_id` filter | Misleading if used for single-event decisions | Analytics calculation / Event attribution | **Critical** | G-01 | Add `?carboot_event_id=` or `/organizer/events/{id}/analytics` |
| G-03 | Event-scoped **community** feedback in reports | Feedback omitted from snapshot | `PostEventSummaryAggregator::feedbackSummary()` L273–281 | Community satisfaction missing from reports | Event attribution | **Critical** | Migration | Add `feedbacks.carboot_event_id` + capture on submit (**Track A**) |
| G-04 | Report workflow DB tables applied | Migrations exist; docs say not executed | `docs/generated-report-workflow-progress.md` L137–145 | Entire lifecycle may fail at runtime | Ingestion/storage (infra) | **Critical** | DBA / env | Validate and apply migrations |
| G-05 | Post-event **vendor** survey CSV import & validation | No import code; external template+sample exist | No backend import; `09_SURVEY_SCHEMA_CONTRACT.md` | Cannot ingest 53-column vendor survey | **Ingestion/storage** | **High** | Schema contract (done) | Phase 1 Track B: upload + validate + store |
| G-06 | Gross sales band (categorical) | External CSV `q6_jualan_kasar` exists; not in DB | Codebook + sample CSV | Field **collected externally**; not stored/analysed in app | External CSV present → Ingestion + Analytics | **High** | G-05 | Import → `gross_sales_band`; band counts only |
| G-07 | Used-item % sold band | External CSV `q5_barang_terjual` exists; not in DB | Codebook (used goods) | Same as G-06 | External CSV present → Ingestion + Analytics | **High** | G-05 | Import → `items_sold_band` |
| G-08 | Vendor event-information sources | External CSV `q4_*` exists; earlier audit misframed as “promotional channels” | Q4: “Di manakah anda mendapat maklumat acara ini?” | Field present for **vendors**; organizer campaign / visitor discovery still missing | External CSV present (vendor info); **Missing collection** (visitor/organizer promo) | **High** / **Medium** | G-05 | Import → `event_info_sources`; keep visitor discovery as separate unavailable gap |
| G-09 | Visitor event discovery sources | No visitor survey | Instrument is vendor-only | Still genuinely unavailable for visitors | **Missing collection** | **Medium** | Future visitor instrument | Out of `vendor_post_event_v1` scope |
| G-10 | Purpose of selling (vendors) | External CSV `q8_tujuan_jualan` exists | Codebook 3 values | Vendor purpose available externally; visitor purpose still missing | External CSV present (vendor); Missing collection (visitor) | **Medium** | G-05 | Import → `sales_purpose` |
| G-10b | Vendor experience / improvement / comments / activity impacts | Q9–Q13 present in CSV | `09_SURVEY_SCHEMA_CONTRACT.md` | Structured vendor satisfaction & ops improvement exist externally | External CSV → Ingestion + Analytics | **High** | G-05 | Import normalized fields; hub charts |
| G-11 | Participation/background aggregates per event | DB fields exist; no aggregation | `feedbacks.participation_type`, `community_backgrounds`; no event FK | Cannot segment event **community** feedback | Event attribution + Analytics | **High** | G-03 | Aggregate after event linkage (Track A) |
| G-12 | Python analytics engine for survey metrics | Wordcloud + status summary only | `python_analytics/main.py` | No Q1–Q13 aggregations | Analytics calculation | **High** | G-05, storage | Phase 2 Track B aggregations |
| G-13 | Trends, anomalies, actionable findings | No comparison or anomaly UI | No cross-event charts; `advanced_analytics.py` broken | Step 6 of organizer journey absent | Analytics calculation | **High** | G-01, G-02, historical data | Phase 2–3: trend API + UI |
| G-14 | ESG / sustainability indicators | Deferred; mock only; survey has **proxies** (Q2/Q7) | `ImpactDashboard.vue`; Q7 unsold actions | Full ESG unavailable; circularity proxies available externally | Missing collection (ESG tonnes); External CSV (proxies) | **Medium** | G-05 for proxies | Import Q2/Q7 proxies; defer carbon/waste models |
| G-15 | Data completeness pre-flight UI | Only in generated snapshot | `data_availability` in snapshot JSON | Organizer cannot assess before generating | Analytics calculation | **Medium** | G-01 | `GET /organizer/events/{id}/data-readiness` |
| G-16 | Real external notifications (email/WhatsApp) | Simulated only | `ExternalAlertSimulationService`, `ReportNotificationActivity.vue` | External stakeholders not alerted | — | **Medium** | Mail config | Phase 5 optional |
| G-17 | Notification inbox UI | Unread badge only | `reportWorkflowApi.js` list/mark-read unused | Poor notification UX | — | **Low** | — | Add inbox panel |
| G-18 | Report workflow automated tests | Zero endpoint tests | Test grep | Regression risk | — | **Medium** | G-04 | Add tests when approved |
| G-19 | PDF generation reliability | Optional DomPDF | Controller `class_exists` → 503 | PDF may fail | — | **Medium** | Composer dep | Install DomPDF |
| G-20 | Python service config port mismatch | Default 8000 vs 8001 | `config/services.php` | Wordcloud 502 risk | — | **Medium** | DevOps | Align default to 8001 |
| G-21 | Python wordcloud includes hidden feedback | No `is_hidden` filter | `python_analytics/main.py` | Moderation undermined | Analytics calculation | **Low** | — | Filter hidden |
| G-22 | Vendor booth insights in dashboard | API returns `booth`; UI missing | `VendorEventInsights.vue` not imported | Vendor UX incomplete | — | **Low** | — | Mount or delete |
| G-23 | UUM oversight dashboard | Placeholder | `UumDashboard.vue` | Stakeholder view absent | — | **Low** | G-01 | Future scope |
| G-24 | Walk-in vendor counts | Documented deferred | Workflow progress doc | Operational gap | Missing collection | **Medium** | Process | Define walk-in workflow |
| G-25 | Check-in / attendance in reports | Column exists; not in snapshot | `bookings.checked_in_at` | Attendance insight missing | Analytics calculation | **Medium** | — | Add to aggregator |
| G-26 | Multiple report types | Single type only | `ReportType::POST_EVENT_SUMMARY` | Limited reporting | — | **Low** | — | After MVP |
| G-27 | Database environment health | Prior corruption documented | `report/02_schema_and_data_source_inventory.md` | Analytics may fail on broken DB | Infra | **Critical** | Infrastructure | Resolve DB first |
| G-28 | `advanced_analytics.py` | Broken orphan | Queries `ratings` | Dead code risk | — | **Low** | — | Remove or repair |
| G-29 | Service/value ratings independent | Form sets all three equal | `FeedbackController@store` | Granularity lost | — | **Low** | UI | Separate stars |
| G-30 | Small-n privacy suppression | Not implemented | — | Re-identification risk | Analytics calculation | **Medium** | G-11 / survey charts | Suppress when n < threshold |

---

## Survey gap reassessment (2026-08-02)

| Topic | Previous status | Amended status |
|-------|-----------------|----------------|
| Gross sales bands | Unavailable (no field) | **External CSV present**; blocked by ingestion (G-06) |
| % items sold bands | Unavailable | **External CSV present** (used-goods Q5); blocked by ingestion (G-07) |
| “Promotional channels” | Unavailable | **Reframed:** vendor **event-info sources** in CSV (Q4); organizer campaign analytics still missing; visitor discovery still missing (G-08/G-09) |
| Purpose of participation | Unavailable | **Vendor purpose of selling** in CSV (Q8); visitor purpose still missing (G-10) |
| Vendor experience / improvements | Unavailable / partial | **External CSV present** Q9–Q13 (G-10b) |
| Circularity proxies | Requires future data | **External CSV present** Q2/Q7 (G-14 proxies) |
| CSV import pipeline | High — schema undefined | Remains **High** — schema now contracted; implementation still missing (G-05) |

---

## Gap summary by severity

| Severity | Count | Top themes |
|----------|-------|------------|
| **Critical** | 5 | Hub missing, global analytics, community feedback event link, DB migrations/health |
| **High** | ~10 | Survey **ingestion** (not missing fields), Python survey analytics, community feedback aggregates |
| **Medium** | ~11 | Visitor discovery, ESG tonnes, walk-ins, notifications, privacy |
| **Low** | 6 | Orphans, port config, minor UX |

---

## Requirement area coverage (amended)

| Area | Data existence | App readiness | Primary remaining gap |
|------|----------------|---------------|------------------------|
| Event overview metrics | Strong (DB) | ~70% | Event selector UI |
| Vendor profile (survey) | Strong (external CSV) | ~5% in app | Ingestion + hub |
| Economic outcomes | Fees in DB + sales bands in CSV | ~45% ops / 0% survey | Import Q6 |
| Reuse/sustainability | Reservations in DB + Q2/Q7 proxies in CSV | ~30% | Import proxies; no ESG tonnes |
| Vendor experience | Q9–Q11 in CSV | ~0% in app | Ingestion |
| Community satisfaction | In `feedbacks` | Blocked | Event linkage (Track A) |
| Vendor info sources | Q4 in CSV | ~0% in app | Ingestion (not visitor discovery) |
| Visitor discovery | None | 0% | Missing collection |
| Operational improvement | Q3/Q10 in CSV + community text | Partial | Ingestion for structured vendor ops |
| Report lifecycle | Source-complete | ~75% | Runtime/migration validation |
| Organizer hub UX | — | ~20% | No unified hub |

---

## Dependencies graph (critical path) — parallel tracks

```
G-27/G-04 Database health & migrations
    ├── Track A (community feedback)
    │     G-03 feedbacks.carboot_event_id
    │       └── G-11 participation aggregates
    │             └── Hub feedback tab
    └── Track B (vendor survey CSV)
          09_SURVEY_SCHEMA_CONTRACT (complete)
            └── G-05 import + storage
                  └── G-06/G-07/G-08/G-10/G-10b/G-14-proxies
                        └── G-12 Python survey aggregations
                              └── G-01/G-02 Event analytics hub
                                    └── G-13 Trends & anomalies
```

---

## What is NOT a gap (already adequate)

- Post-Event Summary snapshot aggregation logic (`PostEventSummaryAggregator`)
- Report request state machine (`ReportRequestTransitionService`)
- Organizer/CMart Report Centre Vue panels and `reportWorkflowApi.js`
- RBAC separation (CMart cannot access boss analytics)
- Immutable published reports with versioning
- Honest `data_availability` omission (no fake zeros)
- Vendor self-service analytics (`VendorAnalyticsService`)
