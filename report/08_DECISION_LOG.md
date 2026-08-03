# 08 — Decision Log

**Audit date:** 2026-07-28  
**Amended:** 2026-08-02 — vendor survey CSV reconciliation

Records **confirmed decisions** (from codebase/docs/instrument), **assumptions requiring validation**, **unresolved questions**, and **decisions required before implementation**.

**Label rule:** Values are **confirmed** only when supported by the questionnaire Codebook / source template. Values merely seen in nine sample rows are labelled **observed, not necessarily exhaustive**.

---

## Confirmed decisions (evidence-based)

| ID | Decision | Evidence | Date |
|----|----------|----------|------|
| D-01 | Laravel is the system of record, API gateway, and UI host for reporting | `OrganizerGeneratedReportController`, `reportWorkflowApi.js` | Existing |
| D-02 | Python FastAPI provides text/status analytics, not report workflow orchestration | `python_analytics/main.py`, `BossAnalyticsController` | Existing |
| D-03 | Power BI is **not** the current implementation direction | No Power BI references in repo; Python + Laravel stack in use | 2026-07-28 |
| D-04 | Single report type for MVP workflow: `post_event_summary` | `backend/app/Support/ReportType.php` | Existing |
| D-05 | CMart Management cannot access raw operational analytics | Governance docs + security tests | Existing |
| D-06 | Published reports are immutable; revision creates new version | `ReportPublicationService` | Existing |
| D-07 | In-app notifications are authoritative; email/WhatsApp are simulated | `ExternalAlertSimulationService` | Existing |
| D-08 | Optional metrics omitted honestly via `data_availability` — no fake zeros | `PostEventSummaryAggregator` | Existing |
| D-09 | Community feedback uses canonical `participation_type` + `community_backgrounds` | `FeedbackClassification.php` | Existing |
| D-10 | Organizer workspace is `/admin` hash navigation | `AdminDashboard.vue` | Existing |
| D-11 | Invoice totals represent **platform fees**, not vendor gross sales | Aggregator invoice summary | 2026-07-28 |
| D-12 | Categorical sales ranges must not be converted to exact RM without methodology | Audit rule; instrument uses bands | 2026-07-28 |
| D-13 | Current paper/digital instrument is a **vendor post-event survey** (`vendor_post_event_v1`) | Template title “SOAL SELIDIK VENDOR”; 53-column Data_Entry | 2026-08-02 |
| D-14 | Survey schema versioning: ingest tagged as `vendor_post_event_v1`; future instruments bump version | `09_SURVEY_SCHEMA_CONTRACT.md` | 2026-08-02 |
| D-15 | `carboot_event_id` assigned at **organizer upload route**, not required in every CSV row | Recommended identity rules; Panduan has no event column | 2026-08-02 |
| D-16 | Safe source-row identity = `import_batch_id + respondent_id`; preserve `source_row_number` and raw file | Schema contract §1 | 2026-08-02 |
| D-17 | `semakan_automatik` and `catatan_semakan` are **validation / import-review** outputs, not participant answers | Panduan rules 9–10; Codebook “Semakan” | 2026-08-02 |
| D-18 | Structured vendor surveys stay in **`survey_responses`** (separate from community `feedbacks`) | Schema contract §1; Q-04 resolved | 2026-08-02 |
| D-19 | Q4 is **vendor event-information source**, not visitor discovery and not organizer campaign analytics | Wording: “Di manakah anda mendapat maklumat acara ini?” | 2026-08-02 |
| D-20 | Hybrid storage: JSON arrays for multi-selects + scalar columns for single-selects | Schema contract §5 | 2026-08-02 |

---

## Assumptions requiring validation

| ID | Assumption | Status | Validation needed | Owner |
|----|------------|--------|-------------------|-------|
| A-01 | Report workflow migrations not yet applied | **Resolved (2026-08-02)** | Applied (`2026_07_23_100001`–`100004`); readable | DevOps / DBA |
| A-02 | `cmart_db` may still have corruption | **Narrowed — no 1932 on core tables** | Survey tables pending apply; re-check after migrate | DBA |
| A-03 | One approved booking ≈ one vendor slot | Open | Multi-day allocation semantics | Product / dev |
| A-04 | Active event can be inferred for community feedback | Open | Explicit picker vs auto rule | Product |
| A-05 | Post-event survey collected via **CSV upload** of Data_Entry export | **Narrowed — provisionally yes** | Organizers already use Excel/CSV template; confirm this remains the primary collection path (vs future in-app form) | Stakeholders |
| A-06 | `vendor_items` active count proxy for reuse until survey proxies ingested | Open | Sustainability stakeholders | Product |
| A-07 | Python analytics on port 8001 | **Resolved (2026-08-02)** | `services.php` default + `.env` use `8001` | DevOps |
| A-08 | DomPDF acceptable for PDF | Open | Package policy | DevOps |
| A-09 | Small-n threshold of 5 | Open | Privacy review | Product / legal |
| A-10 | Survey respondents anonymous (no `user_id`) | **Supported by instrument** | Confirm no future vendor-account link required for MVP | Stakeholders |

---

## Unresolved questions

| ID | Question | Status | Resolution / remaining |
|----|----------|--------|------------------------|
| Q-01 | Gross sales band enum values? | **Resolved (confirmed)** | Codebook: `Kurang daripada RM50`; `RM51 hingga RM150`; `RM151 hingga RM300`; `Melebihi RM300`. Sample observed all four — still treat Codebook as authority. |
| Q-02 | % items sold band values? | **Resolved (confirmed)** | Codebook: `Suku (25%)`; `Separuh (50%)`; `Hampir habis (75%-100%)`; `Tiada (hanya jual barang baharu/makanan)`. Sample **observed** first, second, fourth only — third is confirmed but not observed. |
| Q-03 | Promotion / discovery fields? | **Narrowed** | **Vendor info sources (Q4)** confirmed: WhatsApp, media sosial, rakan/kenalan, pihak penganjur, lain-lain (+ teks). **Visitor discovery** and **organizer promotional-channel ROI** remain **uncollected**. Do not equate Q4 with visitor discovery. |
| Q-04 | Merge survey into `feedbacks`? | **Resolved** | **No** — keep `survey_responses` separate from community `feedbacks` (D-18). |
| Q-05 | Backfill historical `feedbacks.carboot_event_id`? | Open | Leave null / manual / date heuristic |
| Q-06 | Keep portfolio-level global analytics? | Open | Keep boss panels with “all events” default? |
| Q-07 | Who approves ESG definitions? | Narrowed | Full ESG deferred; Q2/Q7 circularity **proxies** approved for import analytics |
| Q-08 | CMart aggregated analytics access? | Open | Governance currently: published reports only |
| Q-09 | Max CSV upload size / row count? | **Narrowed — provisional** | Template sized to 500 data rows; recommend MVP limits **5 MB** and **500 respondents/batch** pending stakeholder confirm. Sample file has 9 rows. |
| Q-10 | Walk-in registration in MVP? | Open | Not in vendor survey instrument |

---

## Decisions required before implementation

| ID | Decision | Status | Recommended default | Must decide by |
|----|----------|--------|---------------------|----------------|
| R-01 | Apply report workflow migrations | **Done (applied)** | Yes | Phase 0 |
| R-02 | Add `feedbacks.carboot_event_id` | **Source migration created; not applied** | Yes — nullable FK | Track A start |
| R-03 | Feedback event attribution rule | Open | Explicit picker when ambiguous | Before P1-A02 |
| R-04 | Survey CSV schema sign-off | **Largely resolved** | Adopt `vendor_post_event_v1` contract; stakeholder ack of Codebook enums; sample values labelled observed≠exhaustive | Before P1-B02 (gate P0-03a) |
| R-05 | Simulated external alerts for MVP | Open | Yes | Phase 5 |
| R-06 | Snapshot schema v2 when survey sections added | Open | Yes | Phase 4 |
| R-07 | Remove `ImpactDashboard.vue` mock | Open | Yes | Phase 3 |
| R-08 | Install DomPDF | Open | Yes if PDF required | Phase 0 |
| R-09 | Queue driver for CSV import | **MVP decided: sync** | Current env `QUEUE_CONNECTION=sync` → synchronous import in `SurveyImportService`; async job deferred | Before P1-B04 |
| R-10 | Deprecate `ManagementReportsPanel.vue` | Open | Delete | Phase 3 |
| R-11 | Schema versioning policy | **Decided** | Tag each import with `schema_version`; breaking instrument changes → new version id | — |
| R-12 | Event assignment during upload | **Decided** | From organizer route `/events/{id}/survey-imports` | — |
| R-13 | Composite source-row identity | **Decided** | `(import_batch_id, respondent_id)` + `source_row_number` | — |
| R-14 | Treatment of validation columns | **Decided** | Store as import metadata; exclude from analytic metrics | — |
| R-15 | Separation of vendor survey vs public feedback | **Decided** | Separate tables/pipelines (Track A vs Track B) | — |

---

## Confirmed vs observed-only enum notes

| Field | Confirmed (Codebook) | Observed in sample (n=9) | Unconfirmed / not in sample |
|-------|----------------------|--------------------------|-----------------------------|
| Q5 items sold | 4 bands | 3 bands | `Hampir habis (75%-100%)` not observed |
| Q6 gross sales | 4 bands | All 4 | — |
| Q8 purpose | 3 values | 2 values | `Pendapatan Utama` not observed |
| Q9 experience | 4 values | 2 values | `Sangat tidak memuaskan`, `Kurang memuaskan` not observed |
| Q12 activity attracted | Ya / Tidak pasti / Tidak | Ya, Tidak pasti | `Tidak` not observed |
| Binary options | Full one-hot sets | Subset selected | Many options only appear as `0` |

---

## Rejected / deferred options (for record)

| Option | Reason deferred |
|--------|-----------------|
| Power BI embedded dashboards | Not in current stack |
| Convert sales bands to RM revenue | D-12 |
| Fake zero metrics when data missing | Workflow rule 10 |
| Merge vendor survey into `feedbacks` | D-18 |
| Treat Q4 as visitor discovery | Questionnaire wording is vendor info source (D-19) |
| Claim ESG tonnes from survey | Instrument has action proxies only |
| Invent enums from sample alone | Sample not exhaustive |

---

## Change log

| Date | Entry |
|------|-------|
| 2026-07-28 | Initial audit decision log |
| 2026-08-02 | Survey CSV amendment: D-13–D-20; A-05 narrowed; Q-01–Q-04, Q-09 resolved/narrowed; R-04/R-11–R-15 |
| 2026-08-02 | Vertical slice: A-01/A-07 resolved; R-01 done; R-02 migration source-only; R-09 sync MVP; see `10_ANALYTICS_HUB_IMPLEMENTATION_PROGRESS.md` |

---

## Sign-off checklist (pre-Phase 1 storage)

- [x] A-01/A-02 database state validated (report tables OK; no 1932; survey migrations pending)  
- [x] Q-01–Q-02 survey band enums confirmed from Codebook  
- [x] Q-04 storage separation decided  
- [x] R-04 / P0-03a schema contract written (`09_SURVEY_SCHEMA_CONTRACT.md`)  
- [ ] Stakeholder formal ack of `vendor_post_event_v1`  
- [ ] R-03 community feedback attribution rule agreed  
- [ ] R-08 PDF dependency confirmed  
- [ ] P0-04 MVP metric list stakeholder-approved  
