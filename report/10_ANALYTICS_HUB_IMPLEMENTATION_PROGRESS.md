# 10 — Analytics Hub Implementation Progress

**Date:** 2026-08-02  
**Branch / commit at start:** `main` @ `35af3fb`  
**Scope:** First vertical slice — Organizer Analytics Hub + vendor survey import  
**Runtime smoke:** 2026-08-02 (local)

---

## Preflight (P0-01)

| Check | Result |
|-------|--------|
| Environment | **local** (`APP_ENV=local`, DB host `127.0.0.1`, database `cmart_db_rebuild`) |
| MariaDB error 1932 | **Not present** |
| Report workflow migrations | **Ran** |
| Survey/analytics migrations | **Ran** (batch 5) |
| `QUEUE_CONNECTION` | `sync` — synchronous import MVP |
| Analytics URL | `http://127.0.0.1:8001` |

---

## Status legend

| Label | Meaning |
|-------|---------|
| **Runtime verified** | Exercised successfully against local services/DB |
| **Runtime verified after fix** | Failed once; fixed; re-verified |
| **Implemented and connected** | Wired in source; not fully browser-exercised |
| **Incomplete** | Partial |
| **Deferred** | Out of this slice |
| **Blocked** | Cannot proceed without further approval/action |

---

## Feature status

| Feature | Status |
|---------|--------|
| Four analytics/survey migrations applied | **Runtime verified** |
| Tables/FKs/`(import_batch_id, respondent_id)` unique | **Runtime verified** |
| Python survey schema/validate/aggregate | **Runtime verified after fix** (`python-multipart` missing → installed + `requirements.txt`) |
| CSV import event 5 → 9/9 valid, 9 persisted | **Runtime verified** |
| Private raw file under `survey-imports/` | **Runtime verified** |
| Overview + all section endpoints | **Runtime verified** |
| Event isolation (event 6 empty) | **Runtime verified** |
| Recompute respondents = 9 | **Runtime verified** |
| Post-Event draft `vendor_survey` n=9, no row-level PII | **Runtime verified** |
| Vue `/admin#event-analytics` wiring + module serve | **Runtime verified** (Vite serves panel; nav/AdminDashboard connected; API contract matches UI bindings) |
| Interactive browser console walkthrough | **Incomplete** — no browser automation in this pass; UI binds to verified APIs |
| Track A feedback submit → `carboot_event_id` | **Incomplete / deferred next** |
| Async import job | **Deferred** |
| PDF generation | **Deferred** (not approved) |

---

## Runtime defects found and fixed

| Defect | Fix |
|--------|-----|
| FastAPI worker crash: `python-multipart` missing for `File` upload | `pip install python-multipart`; added to `requirements.txt`; restarted uvicorn |
| Import 502 could echo internal exception text | `OrganizerSurveyImportController@store` returns generic message only |

---

## Smoke evidence (event 5, sample CSV)

- total/valid/invalid rows: **9 / 9 / 0**
- persisted `survey_responses` (valid): **9**
- overview `respondent_count`: **9**
- other event survey: **empty / 0** (no leak)
- gross sales: categorical bands with denominators (e.g. `5 of 9 respondents (55.6%)`)
- small-sample flag: **false** (threshold = 5; n = 9 correctly not flagged)
- draft report id 1: snapshot `vendor_survey.respondent_count = 9`

---

## Safest next task

**Track A** — complete `feedbacks.carboot_event_id` capture on community feedback submit and event-scoped community-feedback aggregation in hub/snapshot.
