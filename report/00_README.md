# Carboot@CMart — Organizer Analytics & Reporting Hub

**Audit date:** 2026-07-28 (UTC+8)  
**Amended:** 2026-08-02 — vendor survey schema contract + vertical-slice implementation  
**Runtime smoke:** 2026-08-02 — local migrations applied; CSV import + analytics APIs verified  
**Repository:** `cmart_ecosystem`  
**Branch / commit at implementation start:** `main` @ `35af3fb`  
**Working tree note:** Unrelated uncommitted change in `frontend/src/components/EventCalendar.vue` — preserved.

---

## Purpose

This folder documents architecture, schema contracts, and **implementation progress** for the Organizer Analytics and Reporting Hub — event-scoped decision support built on Laravel + Vue + Python/FastAPI, reusing the existing generated-report workflow.

---

## Implementation status (2026-08-02, after smoke)

| Area | Status |
|------|--------|
| P0-01 database preflight | **Runtime verified** — local `cmart_db_rebuild`; no 1932 |
| Survey schema contract (`vendor_post_event_v1`) | **Complete** |
| Survey/analytics migrations | **Runtime verified** (batch 5 Ran) |
| Python validate + aggregate | **Runtime verified after fix** (`python-multipart`) |
| Laravel import + analytics APIs | **Runtime verified** (event 5 → 9/9) |
| Vue Analytics Hub wiring | **Runtime verified** (module + nav + API contract); full interactive browser pass incomplete |
| Report Centre draft + `vendor_survey` snapshot | **Runtime verified** (n=9, aggregate-only) |
| Community feedback submit linkage (Track A) | **Incomplete** — column exists; capture not wired |
| Production readiness | **Not claimed** — local smoke only |

Detail: [`10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md`](10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md).

---

## Document index

| File | Contents |
|------|----------|
| [`01_PROJECT_DIRECTION.md`](01_PROJECT_DIRECTION.md) | Problem statement, users, Laravel/Python roles, MVP boundary, non-goals |
| [`02_CURRENT_ANALYTICS_AUDIT.md`](02_CURRENT_ANALYTICS_AUDIT.md) | As-built inventory (pre-implementation audit) |
| [`03_ORGANIZER_USER_FLOW.md`](03_ORGANIZER_USER_FLOW.md) | Organizer journey step-by-step |
| [`04_DATA_AND_METRICS_MAPPING.md`](04_DATA_AND_METRICS_MAPPING.md) | Metric catalogue |
| [`05_GAP_ANALYSIS.md`](05_GAP_ANALYSIS.md) | Requirement vs implementation gaps |
| [`06_TARGET_ARCHITECTURE.md`](06_TARGET_ARCHITECTURE.md) | Target architecture on existing stack |
| [`07_IMPLEMENTATION_ROADMAP.md`](07_IMPLEMENTATION_ROADMAP.md) | Phased plan + slice progress overlay |
| [`08_DECISION_LOG.md`](08_DECISION_LOG.md) | Decisions, assumptions, open questions |
| [`09_SURVEY_SCHEMA_CONTRACT.md`](09_SURVEY_SCHEMA_CONTRACT.md) | `vendor_post_event_v1` column contract |
| [`10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md`](10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md) | Vertical-slice progress and smoke results |

---

## Key evidence paths

| Layer | Primary locations |
|-------|-------------------|
| Survey import | `SurveyImportService.php`, `OrganizerSurveyImportController.php` |
| Event analytics | `EventAnalyticsService.php`, `OrganizerEventAnalyticsController.php` |
| Python survey engine | `python_analytics/survey_schema.py`, `validate_survey_csv.py`, `event_aggregations.py`, `main.py` |
| Organizer hub UI | `OrganizerEventAnalyticsPanel.vue`, `/admin#event-analytics` |
