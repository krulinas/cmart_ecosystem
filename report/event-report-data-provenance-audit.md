# Event Report Data Provenance Audit

**Date:** 2026-08-05  
**Scope:** Read-only. No implementation, migrations, seeders, or data changes.  
**Primary evidence:** `PostEventSummaryAggregator`, `ReportDraftService`, `ReportPublicationService`, `generated_reports` schema, PDF blade, survey models/Python aggregations, booking/invoice/site models.  
**Local schema check (read-only):** `generated_reports`, `report_requests`, `survey_responses`, `event_images`, `booking_day_allocations` tables **exist** in the current backend database.

---

## 1. Executive verdict

1. **A versioned JSON snapshot workflow already exists** (`draft → published → superseded` + revision drafts). Published metrics come from the frozen `generated_reports.snapshot` JSON, not from live recalculation on view/PDF — **except** PDF “Generated PDF” timestamp is live `now()`, and narratives live in separate mutable columns until publish.
2. **Event isolation for core aggregates is generally sound** (`carboot_event_id` on bookings, sites, reservations, survey responses). The report must **not** treat `carboot_events.status` values such as `Available` as post-event lifecycle status — that field is primarily **booth-registration capacity** (`Available` / `Almost Full` / `Closed`).
3. **Tier A today is narrow but real:** event identity (with caveats), booking approval pipeline counts, approved-booking invoice expected/collected/unpaid, site operational-status counts, approved-booking category labels, optional event-scoped item-reservation counts, organizer narrative fields.
4. **Critical gaps for the intended CMart PDF:** no check-in/attendance aggregation in the snapshot; no booth utilisation % from allocations; survey snapshot omits most instrument charts; **free-text survey comments are stored inside the CMart-visible snapshot**; PDF/`PostEventSummaryView` often coerce missing values to `0`; no poster/photo appendix in report storage; environmental kg/CO₂ claims are unsupported.
5. **Do not invent environmental or visitor figures.** Version 1 should be a **system-first, privacy-filtered, English, single-event** summary with honest null/omitted rendering and explicit methodology notes.

---

## 2. Existing report architecture

| Layer | Artefacts |
|-------|-----------|
| Routes | `backend/routes/api.php` — `/cmart/report-*`, `/organizer/report-*`, `/organizer/generated-reports*`, PDF download routes |
| Controllers | `CmartReportRequestController`, `CmartReportEventOptionsController`, `CmartGeneratedReportController`, `OrganizerReportRequestController`, `OrganizerGeneratedReportController` |
| Services | `PostEventSummaryAggregator`, `ReportDraftService`, `ReportPublicationService`, `ReportRequestTransitionService`, `ReportWorkflowAuditor`, `ReportWorkflowRecipientResolver`, `ExternalAlertSimulationService`, `ReportWorkflowTimelinePresenter`, `ReportNotificationReadService` |
| Models | `ReportRequest`, `GeneratedReport`, `ReportWorkflowAudit`, relations on `CarbootEvent` |
| Migrations | `2026_07_23_100002` requests, `100003` generated_reports, `100004` audits (+ notifications) |
| Resources | `CmartGeneratedReportResource`, `OrganizerGeneratedReportResource`, request resources |
| PDF | DomPDF via `OrganizerGeneratedReportController::downloadPdf` / CMart equivalent; view `resources/views/reports/post_event_summary.blade.php` |
| Frontend | `OrganizerReportCentrePanel.vue`, `CMartReportCentrePanel.vue`, `PostEventSummaryView.vue`, `reportWorkflowApi.js` |
| Related analytics (not the published CMart PDF path) | `EventAnalyticsService` + Python `event_aggregations.py` for richer survey charts in Organizer Analytics Hub |
| Tests | Governance access boundary coverage; **no dedicated Feature suite found** asserting snapshot field provenance / immutability / PII exclusion for Post-Event Summary |

Governance boundary (from code + `docs/generated-report-workflow-progress.md`): Organizer owns draft/publish/revise/raw ops; CMart consumes published/superseded reports only.

---

## 3. Current report lifecycle

```text
CMart creates ReportRequest (event + type)
  → Organizer acknowledge / decline / start preparation
  → ReportDraftService.generate|regenerate
       → PostEventSummaryAggregator.build(event)
       → stores JSON in generated_reports.snapshot (status=draft)
  → Organizer may PATCH narratives (observations/recommendations)
  → ReportPublicationService.publish
       → requires event Closed OR ends_at past
       → status=published; prior published superseded if revision
       → does NOT rebuild snapshot at publish time
  → CMart notified; views snapshot + PDF from stored row
  → Revise → new draft version with NEW live aggregation; old published row remains
```

| Stage | Behaviour |
|-------|-----------|
| Draft calculation | Live aggregation into `snapshot` |
| Draft regenerate | Replaces `snapshot` from live data |
| Preview | Frontend reads stored draft `snapshot` |
| Publish | Status/metadata only; **snapshot not recalculated** |
| PDF | Renders **stored** `snapshot` (+ narrative columns); `generatedAt = now()` |
| Published retrieve | Returns stored JSON (`CmartGeneratedReportResource`) |
| Revision | New draft version; new live snapshot; copies prior narratives; links `supersedes_report_id` |

**Immutability answer:** Published Version N’s **metric snapshot JSON does not silently change** when bookings/payments/surveys later change. Opening/PDF uses stored JSON. A **revision** creates Version N+1 from **then-current live** data. Drafts before publish **do** move with regenerate.

**Mixture caveat:** `organizer_observations` / `organizer_recommendations` are columns outside the aggregator snapshot; they are part of the published row but are not “system metrics.” Event title/dates are duplicated on the report row at draft time.

**Provisional flag:** `snapshot.provisional = true` unless event `status === 'Closed'` or `ends_at` is past (`PostEventSummaryAggregator::isProvisional`). Publish eligibility uses the same Closed/past rule.

---

## 4. Event-scoping analysis

| Dataset | Join to event | Scoped safely? | Leak / caveat risks |
|---------|---------------|----------------|---------------------|
| Bookings | `bookings.carboot_event_id` | **Yes** | Counts **bookings**, not unique vendors; all approval statuses included in pipeline |
| Invoices | `invoices → bookings.carboot_event_id` + `Approved` | **Yes** | Excludes non-Approved; ignores `Pending Verification`/`Refunded`/`Failed` in outstanding |
| Event sites | `event_sites.carboot_event_id` | **Yes** | Statuses are operational (`active`/`unavailable`/`disabled`), not occupancy |
| Site occupancy | `booking_day_allocations` → day/site/booking | **Data exists; not used in report snapshot** | Needed for true utilisation |
| Item reservations | `item_reservations.carboot_event_id` | **Yes** (optional) | Marketplace activity for **that event**, not all history |
| Vendor items / listings | No `carboot_event_id` on items | **Not event-scoped** | Must not aggregate vendor lifetime listings into event report |
| Survey responses | `survey_responses.carboot_event_id` + active batch | **Yes** | CSV/system mix; not all vendors; free text privacy |
| Feedback | `feedbacks.carboot_event_id` (nullable column) | **Conditional** | Aggregator omits if column/table missing; zero responses ≠ “no schema” |
| News / CMart activities | `news_posts` — **no event FK** | **No** | Do not mix into carboot event report without new linkage |
| Event images / poster | `event_images.event_id` / `carboot_events.image_path` | **Yes for media** | **Not copied into report snapshot/appendix today** |
| Analytics cache | `analytics_results.carboot_event_id` | Event-scoped | Organizer hub only; not the published snapshot builder |

---

## 5. Complete provenance matrix

Legend — **Privacy:** Agg = aggregated safe; PII-risk = may identify; Org = organizer-internal OK.  
**Live/Frozen:** Frozen = in published `snapshot`/row fields; Live = recalculated only on draft regenerate/revision.

| Report section | Metric | Tier | Source model/table | Source fields | Event join path | Current calculation | Correct calculation | Null/zero rule | Privacy risk | Required work |
|----------------|--------|------|--------------------|---------------|-----------------|---------------------|---------------------|----------------|--------------|---------------|
| Cover / meta | Event ID | A | `carboot_events` / `generated_reports` | `id` / `carboot_event_id` | Direct | Stored on report | Same | N/A | Agg | Label clearly |
| Cover / meta | Event name | A | `generated_reports.event_title_snapshot` (+ snapshot.event.title) | `title` | Direct | Copied at draft | Same; format EN | Missing → omit/`—` | Agg | TZ-aware display |
| Cover / meta | Venue | A | `CmartVenue::resolve` → snapshot.venue | event venue fields or `config/cmart.php` | Direct | Snapshot at draft | Same | Never invent alternate venue | Agg | Prefer config/event |
| Cover / meta | Organizer / venue owner | C | users/roles; no dedicated report fields | — | — | Not in snapshot | Manual or config labels | Don’t fake | Org names OK | Optional fields |
| Cover / meta | Start/end | A | `event_*_snapshot` + snapshot.event | `starts_at`,`ends_at` | Direct | ISO strings | Asia/Kuala_Lumpur EN format | Missing → `—` | Agg | PDF currently raw ISO |
| Cover / meta | Event status (`Available` etc.) | B | `carboot_events.status` | `status` | Direct | Copied into snapshot; PDF label “Status” | Relabel as **registration capacity status** or omit from post-event cover; use Closed/past for lifecycle | Don’t imply post-event “Available” | Agg | PDF/UI copy fix |
| Cover / meta | Report version / published by / at | A | `generated_reports` | `version`,`published_by`,`published_at`,`status` | Direct | On publish | Same | — | Org publisher name OK | — |
| Cover / meta | Provisional/final | A | snapshot.provisional + publish gate | ends_at/status | Direct | Flag + publish rule | Same | — | Agg | — |
| Cover / meta | Poster | C | `event_images` / `image_path` | paths | `event_id` | Not in snapshot | Appendix attachment + freeze on publish | Missing → omit appendix | Agg | Schema/media copy |
| Executive | High-level bullets | B | Derived from sections | various | — | Not synthesized | Template from Tier A only | No fake zeros | Agg | Template work |
| Participation | Total applications | A | `bookings` | count(*) | `carboot_event_id` | Sum of status groups | Same; label “bookings/applications” | 0 means none | Agg | — |
| Participation | By approval status | A | `bookings.approval_status` | group counts | same | `bookingApprovalCounts` | Include Pending_*, Needs_Revision, Approved, Rejected, Cancelled, Withdrawn explicitly | Absent status key ≠ 0 in prose; chart may show 0 | Agg | PDF currently shows only total+approved |
| Participation | Unique vendors | B | `bookings.user_id` | COUNT DISTINCT | same | **Not computed** | DISTINCT user_id (define status filter) | Null user edge | Agg | Aggregator change |
| Participation | Checked-in / attendance | B | `bookings.checked_in_at` | non-null count | same | **Not in snapshot** | Count checked-in Approved (+ rules) | Null = unknown, not 0 attendance | Agg | Use + label vs approved |
| Participation | No-show | C | — | — | — | None | Needs definition (approved paid not checked in?) | — | — | Product rule + field |
| Participation | Walk-in | D/C | — | — | — | No code references | Unsupported | — | — | Schema if needed |
| Participation | Multi-day attendance | B | `booking_day_allocations` + `event_days` | statuses | booking→event | Not in snapshot | Days confirmed vs released | — | Agg | New aggregates |
| Sites | Total sites | A | `event_sites` | count | `carboot_event_id` | `eventSiteSummary.total` | Same | 0 = no sites | Agg | — |
| Sites | By operational_status | A | `event_sites.operational_status` | active/unavailable/disabled | same | Group counts | Same; **not occupancy** | — | Agg | Relabel |
| Sites | Assigned / occupied / utilisation % | B | allocations + sites | occupying statuses | site/day→event | **Not computed** | Valid occupying allocations ÷ active sites (document multi-day) | No layout → omit % | Agg | New formula |
| Financial | Expected booth fees | A | `invoices.amount` for Approved bookings | sum | invoice→booking→event | Sum all invoice amounts | Same; MYR assumed (no currency column) | No invoices → 0 expected OK if approved exist without invoice? **edge** | Agg | Handle missing invoice |
| Financial | Collected (Paid) | A | `payment_status='Paid'` | sum amount | same | Sum Paid | Same | — | Agg | — |
| Financial | Outstanding Unpaid | B | `payment_status='Unpaid'` | sum | same | Sum Unpaid only | Include or separately report Pending Verification; don’t call Unpaid “all outstanding” | Pending ≠ Unpaid | Agg | Expand statuses |
| Financial | Failed/Refunded | B | invoice statuses | — | same | Not aggregated | Separate buckets | — | Agg | — |
| Financial | Paid withdrawal revenue | B | Withdrawn + Paid invoice | — | same | **Excluded** (Approved only) | Product decision: include as collected non-attendance or exclude | — | Agg | Explicit rule |
| Financial | Vendor gross sales | D as revenue / B as survey bands | survey `gross_sales_band` | bands | survey.event | Partial in snapshot | Survey estimate only; never organizer revenue | Missing answers ≠ RM 0 | Survey | Keep categorical |
| Categories | Approved category mix | A | booking snapshot/FK/product_category | COALESCE labels | Approved bookings | `vendorCategoryDistribution` | Same; authoritative = booking snapshot for event | — | Agg | — |
| Reuse listings | Active listings count | D for event report | `vendor_items` (no event FK) | — | none | Not event-safe | Omit from event report | — | — | — |
| Marketplace holds | Reservation status counts | B | `item_reservations` | status counts | `carboot_event_id` | Optional section | Label as marketplace holds for event; not sales | Omitted if table missing | Agg | — |
| Environment | kg diverted / CO₂ / RM avoided | D | — | — | — | None | **Forbidden** | — | — | — |
| Environment | Survey used-stock / unsold actions | B | survey JSON fields | item_conditions, unsold_item_actions, items_sold_band | event survey | **Not in PostEvent snapshot** (only in Python hub) | Aggregate with n=; conditional denominators | Unanswered ≠ 0% | Survey | Extend aggregator **without PII** |
| Survey | Response count | A/B | `survey_responses` via `forAnalytics` | count | event + active batch | In snapshot when present | Same + response rate vs approved unique vendors if known | Empty → unavailable not 0 insights | Agg | Rate needs Tier B |
| Survey | Full instrument charts | B | stored fields + Python | q1–q13 mapped columns | event | Hub yes / report snapshot **partial** | Port privacy-safe distributions into snapshot | Show n= | Agg | Align report with Python |
| Survey | Free-text comments | D for CMart PDF | text columns | difficulty_details, comments, etc. | event | **Included in snapshot.qualitative_comments** | Exclude from CMart payload/PDF or curated anonymised only | — | **PII-risk** | Filter allowlist |
| Community feedback | Avg rating | B | `feedbacks` | rating avg | `carboot_event_id` | If column present | Same; exclude hidden | Null avg if none | Agg | Frontend still has stale “not scoped” copy in places |
| Activities / visitors / weather | — | C | News unlinked; no weather model | — | — | None | Manual organizer input | — | Org | New optional fields |
| Appendix media | Photos/captions | C | `event_images` | path, sort, is_primary | event | Not frozen on report | Copy paths into snapshot on publish | — | Agg | Schema/API/PDF |

---

## 6. Participation and utilisation findings

### Distinctions available in system vs used in report

| Distinction | Exists in code/schema? | In Post-Event snapshot today? |
|-------------|------------------------|-------------------------------|
| Approved vs attended | `checked_in_at` set by pass verification | **No** — do not label approved as attendance |
| Booking vs unique vendor | `user_id` on bookings | **No** distinct count |
| Withdrawn vs Cancelled | Separate `approval_status` values | Counted in pipeline map; PDF under-displays |
| Paid withdrawal | Possible (Withdrawn + Paid invoice) | Invoices scoped to **Approved only** → paid withdrawals excluded from financials |
| Multi-day vs full attendance | `booking_day_allocations` + exceptions | **Not** in snapshot |
| Walk-in vs system booking | **No** walk-in concept found | Unsupported |

### Booth utilisation

- **Total available booths (layout):** `event_sites` where `operational_status = active` is the closest “available capacity” proxy; aggregator currently totals **all** operational statuses.
- **Assigned/occupied:** requires `booking_day_allocations` with `OCCUPYING_STATUSES` (`reserved`,`confirmed`) and `active_lock`, joined through event days/sites — **not implemented** in `PostEventSummaryAggregator`.
- Formula `assigned ÷ total × 100` is **derivable (Tier B)** after defining: active sites only? per-day vs unique sites? multi-site bookings?
- `max_slots` on events tracks **`event_user` registrations**, not site layout — unsafe as booth capacity for utilisation.

---

## 7. Financial findings

| Rule in `invoiceSummaryForApprovedBookings` | Implication |
|---------------------------------------------|-------------|
| Only `approval_status = Approved` | Rejected/Cancelled/Withdrawn invoices excluded |
| `expected = sum(amount)` | Includes Unpaid + Paid (+ any other statuses present on those invoices) |
| `collected = Paid only` | Correct for collected booth fees |
| `outstanding = Unpaid only` | **Pending Verification** and **Refunded** omitted from outstanding |
| Currency | No `currency` column; UI assumes **MYR** |
| Rounding | `round(..., 2)` |

**Organizer booth-fee revenue ≠ vendor survey sales.** Survey `gross_sales_band` is categorical estimate only.

**Null vs zero:** An approved booking without an invoice yields no invoice row — expected total may understate “should have been invoiced.” Do not silently treat missing invoice as RM 0 due without a note.

---

## 8. Vendor / reuse findings

**Authoritative category for an event report:** booking-time resolution already used:

`category_label_snapshot → vendor_categories.label → product_category → Uncategorised`

for **Approved** bookings. Prefer this over live business profile (profile can change after the event).

**Reuse / environmental:**

| Signal | Tier | Notes |
|--------|------|-------|
| Event item-reservation status counts | B | Event-scoped marketplace holds/completions |
| Vendor private item listings | D for event | No event FK |
| Survey new/used (`item_conditions`) | B | Survey-reported; need n= and conditional denominators |
| Survey unsold actions (donate/recycle/dispose/relist) | B | Proxy circularity indicators only |
| Exact items sold count / kg / CO₂ / RM avoided | D | **Unsupported — do not calculate** |

---

## 9. Survey findings

### Storage and event linkage

- Table `survey_responses` with direct `carboot_event_id`.
- Import via `raw_survey_uploads` batches; analytics use `SurveyResponse::forAnalytics($eventId)` (valid + active + active CSV batch and/or system submissions).
- Instrument contract: Python `survey_schema.py` — **13 questions**, spreadsheet-style **required headers list (~53 columns including respondent_id and review fields)**.
- Multi-select → JSON arrays; free text → text columns; `validation_status`; optional `is_active` / `vendor_user_id` / `submission_source`.

### What the **official report snapshot** currently stores

From `vendorSurveySummary`: respondent_count; counts for `gross_sales_band`, `experience_rating`, `sales_purpose`; **qualitative_comments groups with truncated free text**; notes/limitations.

**Not** written into Post-Event snapshot (but available in Organizer Analytics via Python): product categories, item conditions, difficulty rates, info sources, items_sold_band, unsold actions, improvement areas, supporting-activity impacts — with proper denominators/`n`.

### Privacy

- Snapshot free-text groups are returned to CMart through `snapshot` in `CmartGeneratedReportResource`.
- PDF blade does not currently print them, but **API exposure is already a CMart privacy risk**.
- Recommendation: **exclude free-text from CMart PDF and from CMart API snapshot** (or replace with organizer-curated anonymised excerpts). Keep raw text organizer-internal / Analytics Hub only.

### Response rate

- Numerator: analytics respondent_count.
- Denominator options: approved unique vendors; approved bookings; invited vendors — **none wired in snapshot**. Without denominator, show `n = X respondents` only (Tier B for rate).

---

## 10. Activities / manual-input findings

| Need | Status |
|------|--------|
| Supporting activities tied to carboot event | **Missing** — `NewsPost` has no `carboot_event_id` |
| Visitor estimates, weather, incidents | **Missing** as structured fields |
| Organizer achievements / challenges / recommendations / key findings | **Partial** — free-text `organizer_observations` / `organizer_recommendations` on `generated_reports` |
| Event conclusion | Manual narrative only |

Treat visitor/weather/activity attendance as **Tier C manual** (or omit), not inventable from bookings.

---

## 11. Media and appendix findings

| Asset | Attachment today | In report snapshot? | Appendix readiness |
|-------|------------------|---------------------|--------------------|
| Event poster | `event_images` / legacy `image_path` | No | Needs copy-on-publish or stable storage refs |
| Gallery photos | `event_images` (sort_order, is_primary) | No | Same; captions **not** in `EventImage` fillable |
| Layout diagram | Layout rows/sites; not a dedicated “layout image” in report | No | Separate export/work |
| Report-version media | None | — | New relation if appendix must survive event media edits |

**Work required (not implementing):** publish-time media manifest in snapshot; PDF appendix rendering; optional captions; access control for private files.

---

## 12. Privacy findings

### Already relatively safe in aggregator metrics

Approval counts, invoice totals, site operational counts, category label counts, reservation status counts, survey categorical counts (without respondent ids in those count maps).

### Unsafe / risky if published to CMart as-is

| Risk | Location |
|------|----------|
| Free-text survey comments | `snapshot.sections.vendor_survey.qualitative_comments` |
| Publisher/preparer **names** | Resources expose `published_by.name` / `prepared_by.name` (organizational, usually OK) |
| Full raw `snapshot` passthrough | CMart resource returns entire snapshot without allowlist filter |

### Not currently dumped by aggregator (good)

Vendor name, phone, email, address, user id, payment proof paths, receipt numbers, per-respondent survey rows.

### Recommended CMart allowlist (conceptual)

Event meta (title, dates, venue, version, provisional); booking_pipeline aggregates; payments aggregates; event_sites operational aggregates; vendor_categories distribution; item_reservations aggregates (optional); vendor_survey **categorical only** + respondent_count + limitations; data_availability notes; organizer narratives (organizer-authored).  
**Deny:** qualitative_comments, any user/vendor identity fields, invoice ids, booking ids lists.

---

## 13. Published snapshot / versioning findings

| Question | Finding |
|----------|---------|
| Complete immutable metric snapshot? | **Yes** — JSON `snapshot` frozen at last draft generate/regenerate before publish |
| Recalculate on open? | **No** |
| Recalculate on PDF? | **No** for metrics; PDF stamp time is live |
| V1 changes if live data edits? | **No** for snapshot metrics |
| Revision? | New version + new live snapshot; prior published → `superseded` |
| Narratives | Stored on row; copied into revision draft; published with the version |
| Media immutability | **Not** snapshot-backed today — live event images can change under the same event |

---

## 14. Tier A / B / C / D classification

### Tier A — Ready (with correct labels)

- Event id, title, starts/ends (format later), venue snapshot, report version/status, published_at/by, provisional flag  
- Booking counts by `approval_status` + total + approved_count  
- Invoice expected / Paid collected / Unpaid outstanding for **Approved** bookings (MYR assumed)  
- Event site totals + counts by `operational_status`  
- Approved booking category distribution (label-only)  
- Organizer observations / recommendations fields  

### Tier B — Derivable with caveats

- Unique vendors; check-in attendance; multi-day allocation stats; true utilisation %  
- Full payment status breakdown (Pending Verification, Refunded)  
- Paid-but-withdrawn policy  
- Item reservation section (optional table)  
- Event-scoped feedback averages when linked  
- Survey categorical charts + response rate; items_sold / conditions / unsold actions from stored survey fields  
- Relabel/omit misleading `Available` event status on cover  

### Tier C — Missing / manual

- Walk-in counts; visitor estimates; weather; incidents; structured activity linkage; photo captions; organizer “key findings” structured fields; poster/photo appendix freeze  

### Tier D — Unsupported / unsafe

- kg waste diverted, CO₂ avoided, monetary loss avoided, exact reused units sold  
- Lifetime vendor listing aggregates as event metrics  
- Treating approved bookings as “actual attendance” without saying so  
- Publishing survey free-text to CMart without curation  
- Mixing unlinked CMart news into the carboot event report  

---

## 15. Minimum viable system-first report (Version 1)

Intended structure vs feasibility:

| # | Section | V1 recommendation |
|---|---------|-------------------|
| 1 | Cover | **Ready** — title, dates (KL format), venue, version, published meta; **omit or relabel** capacity `status`; English only |
| 2 | Executive Summary | **Conditional** — short bullets from Tier A only; mark provisional if flagged |
| 3 | Event and Participation | **Ready** — booking pipeline; explicitly **“Approved bookings (not verified attendance)”**; optional check-in if added (B) |
| 4 | Financial Summary | **Ready** — expected/collected/unpaid for approved; note Pending Verification gap; MYR |
| 5 | Vendor and Sales Insights | **Partial** — category distribution ready; survey bands only if `available` with `n=`; no RM totals |
| 6 | Environmental and Social Insights | **Mostly omit** — at most survey circularity proxies if added without invention; **no kg/CO₂** |
| 7 | Organizer Assessment | **Ready** — observations/recommendations text |
| 8 | Appendix A — Materials | **Omit until media freeze exists** |
| 9 | Appendix B — Methodology | **Ready** — data_availability, analytics_source_mode, scope notes, null/zero policy |

**Conditionally render:** vendor_survey, item_reservations, feedback when `available` / not omitted.  
**Omit entirely for V1:** environmental inventions, walk-ins, visitor counts, unlinked activities, PII comments.

---

## 16. Data / schema / API gaps

1. CMart snapshot **allowlist filter** (strip qualitative comments and any future PII).  
2. Aggregator extensions: unique vendors, check-ins, allocation utilisation, richer payment statuses, fuller survey categoricals **without** free text.  
3. PDF/UI: stop `?? 0` for missing sections; English KL datetime; status labelling.  
4. Optional: report media manifest table/JSON; caption fields; activity–event FK if needed.  
5. Dedicated provenance/immutability/privacy Feature tests (largely absent today).  
6. Align Post-Event survey section with Python hub aggregations for parity (privacy-safe subset).

---

## 17. Exact files affected by future implementation

### Must change

- `backend/app/Services/PostEventSummaryAggregator.php`  
- `backend/resources/views/reports/post_event_summary.blade.php`  
- `frontend/src/components/reports/PostEventSummaryView.vue`  
- `backend/app/Http/Resources/CmartGeneratedReportResource.php` (allowlist)  
- New/extended Feature tests under `backend/tests/Feature/`  

### May change

- `ReportDraftService` / `ReportPublicationService` (publish-time freeze extras)  
- `OrganizerGeneratedReportController` / CMart PDF controller  
- `OrganizerReportCentrePanel.vue` / `CMartReportCentrePanel.vue`  
- Survey import/analytics only if report consumes more fields  
- `EventImage` model/migrations if captions/appendix freeze  
- `docs/generated-report-workflow-progress.md`  

### Should not change

- Booking approval state machine, invoice payment APIs, layout assignment engines (consume only)  
- Inventing ESG formulas in Python or PHP  
- Public community APIs  
- Vendor PII presentation layers for CMart  

---

## 18. Recommended implementation sequence

1. **Privacy hard gate:** CMart allowlist; remove free-text from published consumer payload/PDF.  
2. **Honest rendering:** null/omit vs zero; English KL dates; fix Status labelling (`Available`).  
3. **Expand Tier A display:** full booking status table; payment footnotes.  
4. **Tier B metrics:** unique vendors; check-ins labelled; utilisation from allocations; payment status splits.  
5. **Survey categoricals** into snapshot with `n=` (no comments).  
6. **Appendix media freeze** + PDF appendix.  
7. **Optional manual fields** (visitors/weather/activities) only after product confirms.  
8. **Tests** after each layer (isolation, immutability, privacy).

---

## 19. Test plan

| Area | Recommended checks |
|------|--------------------|
| Event isolation | Aggregator for event A never includes B’s bookings/invoices/sites/surveys |
| Cross-event leakage | Shared vendor with two events counted only in each event |
| Booking statuses | All known statuses appear; withdrawn ≠ cancelled |
| Unique vendors | Distinct user_id rules |
| Multi-day | Occupying vs released allocations |
| Utilisation | Division by zero / no sites → omit not 0% |
| Payments | Paid/Unpaid/Pending Verification/Refunded; Approved-only scope |
| Paid withdrawal | Documented include/exclude |
| Survey denominators | Conditional questions; unanswered ≠ 0% |
| No-response survey | `available:false` / empty note; no fake charts |
| Null vs zero | Missing section omitted; empty counts explicit |
| Privacy | No names/phones/emails/comment texts in CMart JSON/PDF |
| Immutability | Mutate live booking after publish; V1 snapshot unchanged |
| Revision | V2 new snapshot; V1 superseded |
| Media appendix | When implemented, publish freeze vs live replace |
| Time zone | Asia/Kuala_Lumpur EN formatting |
| Partial reports | Provisional + data_availability |
| Synthetic 7 vendors | Later fixture set — **not created in this audit** |

---

## 20. Open questions and blockers

1. **Should CMart ever see survey free-text?** Code currently embeds it in snapshot — product should forbid for V1.  
2. **Paid + Withdrawn:** count as collected booth revenue or exclude?  
3. **Attendance definition:** `checked_in_at` only, or day-level allocations?  
4. **Utilisation denominator:** active sites vs all sites; per-day vs unique site-days.  
5. **Event `status` on cover:** keep as capacity signal or replace with Closed/Completed derived state?  
6. **Missing invoices on Approved bookings:** treat as data-quality flag?  
7. **Activity linkage:** required for V1 social section or permanently manual/omitted?  
8. **Could not verify in this audit:** runtime DomPDF output on a real published row; production whether every environment has identical migrations; content of any existing published snapshots in DB (not dumped — privacy); end-to-end seven-vendor demo (forbidden to create here); whether `analytics_source_mode=csv_only` is used in practice for official CMart PDFs.

---

*End of provenance audit. Awaiting approval before any implementation.*
