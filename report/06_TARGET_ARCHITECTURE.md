# 06 — Target Architecture

**Audit date:** 2026-07-28  
**Amended note:** 2026-08-02 — see conflicts with `09_SURVEY_SCHEMA_CONTRACT.md` below.

Proposed architecture **extends the existing Laravel + Vue + Python stack**. No rewrite. Aligns with evidence in `PostEventSummaryAggregator`, `reportWorkflowApi.js`, and `python_analytics/main.py`.

### Amendment conflicts vs earlier draft (resolve in favour of schema contract)

| Earlier `06` draft | Contract / instrument (`09`) | Resolution |
|--------------------|------------------------------|------------|
| `promotion_channels` + `discovery_source` as survey fields | Q4 is vendor **event-information source** (`event_info_sources`); no visitor discovery column | Prefer `event_info_sources`; do not invent visitor discovery from this CSV |
| `items_sold_percent_band` | Q5 is used-goods sell-through → `items_sold_band` | Prefer `items_sold_band` naming + Codebook labels |
| `satisfaction_rating` numeric | Q9 is BM categorical `experience_rating` | Prefer categorical enum, not 1–5 stars |
| `participation_type` / `community_backgrounds` on survey_responses | Those belong to community `feedbacks`, not vendor CSV | Keep on Track A only |
| Implied event id in CSV | Event id from upload route | D-15 |

---

## Architecture overview

```mermaid
flowchart TB
    subgraph ingest [Data Ingestion]
        OPS[Laravel operational writes]
        FB[Community feedback form]
        CSV[CSV upload - new]
    end

    subgraph store [Storage Layer]
        RAW[(raw_survey_uploads - new)]
        CLEAN[(survey_responses - new)]
        OPSDB[(bookings, invoices, events, ...)]
        FBDB[(feedbacks + carboot_event_id)]
        AR[(analytics_results - new)]
        GR[(generated_reports)]
    end

    subgraph process [Processing]
        PY[Python FastAPI analytics engine]
        PHP[PostEventSummaryAggregator]
    end

    subgraph api [Laravel API]
        HUB[/organizer/events/{id}/analytics]
        RW[/organizer/generated-reports]
        IMP[/organizer/survey-imports]
    end

    subgraph ui [Vue Organizer UI]
        HUBP[Event Analytics Hub panel]
        RCP[Report Centre panel]
    end

    OPS --> OPSDB
    FB --> FBDB
    CSV --> IMP --> RAW
    IMP --> PY
    PY --> CLEAN
    PY --> AR
    OPSDB --> PHP
    FBDB --> PHP
    CLEAN --> PY
    AR --> HUB
    OPSDB --> HUB
    FBDB --> HUB
    PHP --> GR
    HUB --> HUBP
    RW --> RCP
```

---

## Layer responsibilities

### 1. Data ingestion

| Channel | Handler | Preservation |
|---------|---------|--------------|
| Operational (bookings, payments, sites) | Existing Laravel controllers | Source of truth in normalized tables |
| Live feedback form | `FeedbackController@store` | Extend to accept optional `carboot_event_id` (active/recent event context) |
| Post-event survey CSV | **New** `SurveyImportController` | Store raw file + row-level validation results |

**Raw-data preservation:** New table `raw_survey_uploads` (proposed):

- `id`, `carboot_event_id`, `uploaded_by`, `original_filename`, `storage_path`, `row_count`, `valid_count`, `error_count`, `status` (`pending|processing|completed|failed`), `error_log` (JSON), timestamps

Never discard failed rows silently — keep raw file for audit.

### 2. Schema validation (CSV)

**New Python module** (extend `python_analytics/`):

- `survey_schema.py` — column definitions, types, allowed categorical values
- `validate_survey_csv.py` — returns row errors + summary

**Laravel orchestration:**

1. Organizer uploads CSV via `POST /api/organizer/events/{event}/survey-imports`
2. Laravel stores raw file, dispatches job `ProcessSurveyImportJob` (new)
3. Job calls Python `POST /api/analytics/validate-survey` or runs CLI
4. Valid rows → `survey_responses` table; invalid rows → `import_row_errors`

**Proposed `survey_responses` columns** — authoritative detail in `09_SURVEY_SCHEMA_CONTRACT.md` §5:

- Identity: `import_batch_id`, `carboot_event_id` (from upload route), `schema_version` (`vendor_post_event_v1`), `respondent_id`, `source_row_number`
- Multi-select JSON: `product_categories`, `item_conditions`, `event_info_sources`, `unsold_item_actions`, `improvement_areas`, `supporting_activity_impacts`
- Scalars: `has_difficulty`, `difficulty_details`, `items_sold_band`, `gross_sales_band`, `sales_purpose`, `experience_rating`, `comments_and_suggestions`, `supporting_activity_attracted_visitors`
- Import metadata (not analytics): `import_auto_review_flags`, `import_review_notes`, `validation_status`

*Codebook enums for Q5/Q6/Q8/Q9/Q12 are confirmed; sample-only observations are not exhaustive (see `08_DECISION_LOG.md`).*

### 3. Cleaned response storage

- Deduplicate by business rules (e.g. one vendor response per event per vendor_id if linked)
- Normalize categorical values to canonical enums
- Link to `carboot_event_id` always required for survey imports
- Merge strategy with live `feedbacks` table: **keep separate** `survey_responses` for structured post-event survey; optionally cross-reference feedback IDs

### 4. Python processing

Extend `python_analytics/main.py`:

| Endpoint | Purpose |
|----------|---------|
| `POST /api/analytics/validate-survey` | Schema validation |
| `POST /api/analytics/aggregate/event/{id}` | Compute all event metrics → JSON |
| `GET /api/analytics/wordcloud/feedback?event_id=` | Event-scoped word cloud |
| Existing wordcloud/status | Retain global endpoints for backward compatibility |

**Processing outputs** stored in `analytics_results` (proposed):

- `carboot_event_id`, `metric_key`, `metric_version`, `payload` (JSON), `computed_at`, `source_tables`, `row_count`

Enables cache invalidation and recomputation without regenerating full reports.

### 5. Analytics-result storage

Laravel reads `analytics_results` for hub dashboards. `PostEventSummaryAggregator` optionally **merges** analytics_results into snapshot sections at draft generation time (versioned copy in `generated_reports.snapshot`).

**Principle:** Published report snapshot remains **immutable**; analytics_results are live until snapshotted into a draft.

### 6. Laravel API exposure

**New organizer endpoints** (proposed):

```
GET  /api/organizer/events/{event}/data-readiness
GET  /api/organizer/events/{event}/analytics/overview
GET  /api/organizer/events/{event}/analytics/{section}   # vendors|economics|feedback|reuse|operations
POST /api/organizer/events/{event}/survey-imports
GET  /api/organizer/events/{event}/survey-imports/{batch}
POST /api/organizer/events/{event}/analytics/recompute
```

Retain existing:

- `/api/boss/analytics/*` — deprecate for event use or add event filter
- `/api/organizer/generated-reports/*` — unchanged lifecycle

**Authorization:** `ManagementCapability::canAccessCarbootOperationalAnalytics` + event existence check. No CMart access to raw analytics (per governance doc).

### 7. Organizer dashboard consumption

**New panel:** `frontend/src/views/dashboards/organizer/OrganizerEventAnalyticsPanel.vue`

- Route: `/admin#event-analytics`
- Shared event picker (persist in composable `useEventAnalyticsContext.js`)
- Tabs matching metric sections in `04_DATA_AND_METRICS_MAPPING.md`
- Data readiness banner at top
- "Generate report" CTA linking to Report Centre with event pre-selected

**Retain:**

- `OrganizerReportCentrePanel.vue` — report lifecycle
- `BossWordCloudPanel.vue` — optional global view or fold into hub
- `PostEventSummaryView.vue` — report preview

### 8. Report generation

**Flow unchanged at core:**

1. Organizer triggers draft → `ReportDraftService` → `PostEventSummaryAggregator`
2. **Enhancement:** Aggregator pulls latest `analytics_results` + `survey_responses` aggregates
3. Organizer edits narratives → publish → `ReportPublicationService`

**New snapshot sections (future schema v2):**

- `survey_responses` (counts, band distributions)
- `participation_profile` (from feedback + survey)
- `sustainability` (reservations + reuse proxies)
- `feedback` (when `carboot_event_id` exists)

### 9. Report versioning

**Already implemented:**

- `generated_reports.version`, `supersedes_report_id`, status `published|superseded|draft`
- `OrganizerGeneratedReportController@revise` creates new version

**Enhancement:** Store `snapshot_schema_version` in snapshot JSON; migration path for v1 → v2 readers in `PostEventSummaryView.vue`.

### 10. Organizer-to-CMart report lifecycle

**Retain existing workflow** documented in `docs/generated-report-workflow-progress.md`:

```
CMart request → organizer acknowledge/decline/prepare
  → draft → publish → CMart view/PDF/mark-viewed
  → optional revision (supersedes prior)
```

**Enhancements (Phase 5):**

- Attach analytics hub link in CMart notification metadata
- Real email optional; in-app remains authoritative
- `ReportWorkflowAudit` already captures actions — extend with `survey_import_completed`

### 11. Authorization and privacy boundaries

| Data | Organizer | CMart | Vendor | Public |
|------|-----------|-------|--------|--------|
| Event analytics hub | ✅ | ❌ | ❌ | ❌ |
| Raw survey CSV rows | ✅ | ❌ | ❌ | ❌ |
| Published report snapshot | ✅ | ✅ (published only) | ❌ | ❌ |
| Vendor PII | ❌ in snapshots | ❌ | Self only | ❌ |
| Global boss revenue | ✅ | ❌ | ❌ | ❌ |

**Small-n rule:** API returns `suppressed: true` when cell count < 5 for participation/background breakdowns.

### 12. Error and processing-status handling

| Stage | Status surface |
|-------|----------------|
| CSV upload | `raw_survey_uploads.status` + row error download |
| Python aggregation | `analytics_results` timestamp + `last_error` on failure |
| Draft generation | Existing 4xx/5xx from `OrganizerGeneratedReportController`; transaction rollback |
| Python unavailable | 502 with message (pattern from `BossAnalyticsController`) |
| Missing optional tables | `data_availability` omission — never fake zeros |

**UI:** Processing spinner on hub; Report Centre shows import status badge per event.

---

## Technology choices (confirmed stack)

| Component | Choice | Rationale |
|-----------|--------|-----------|
| Application framework | Laravel 11 | Existing |
| Frontend | Vue 3 + Tailwind | Existing `AdminDashboard` shell |
| Analytics engine | Python FastAPI | Existing `python_analytics/` |
| Report PDF | DomPDF (Blade template) | Existing `post_event_summary.blade.php` |
| Queue | Laravel `jobs` table | For CSV processing (new) |
| Caching | `analytics_results` table | Simpler than Redis for MVP |

**Not recommended:** Power BI embedding, separate analytics SPA, rewriting report workflow in Python.

---

## Migration from current state

1. Apply pending report workflow migrations (if not applied)
2. Add `feedbacks.carboot_event_id` + backfill strategy (optional/nullable)
3. Add `survey_responses`, `raw_survey_uploads`, `analytics_results` tables
4. Extend Python service with validation + aggregation endpoints
5. Build hub panel consuming new APIs
6. Bump snapshot schema to v2 in aggregator
7. Deprecate global-only boss panels for event decisions (keep for portfolio view)

---

## Non-goals in target architecture

- Real-time streaming analytics
- Multi-tenant venue support beyond current single-venue fallback
- Public analytics dashboards
- Automatic conversion of sales bands to RM totals
