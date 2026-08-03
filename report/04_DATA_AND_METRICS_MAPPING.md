# 04 — Data and Metrics Mapping

**Audit date:** 2026-07-28  
**Amended:** 2026-08-02 — reconciled with external vendor survey schema `vendor_post_event_v1` (see `09_SURVEY_SCHEMA_CONTRACT.md`)

**Availability legend:**

| Status | Meaning |
|--------|---------|
| **Directly available** | Field exists and is queryable in the operational database today |
| **Derivable** | Can be computed from existing operational fields with documented logic |
| **Derivable after import** | Present in external survey CSV; becomes computable once import pipeline stores normalized rows |
| **Available from external survey CSV — import pipeline not implemented** | Collected outside the app (template + sample responses exist); not ingested into Laravel DB |
| **Partially available** | Some dimensions exist; event scope or completeness gaps |
| **Blocked by missing event linkage** | Data exists (DB or CSV path) but cannot be safely scoped to an event |
| **Unavailable** | No supporting schema, questionnaire field, or collection mechanism |
| **Requires future operational data** | Needs new survey fields, processes, or integrations beyond current instrument |

**Important:** Categorical gross-sales ranges must **not** be converted to exact RM revenue without a documented methodology. Invoice totals are **platform fees**, not vendor gross sales.

**Survey source (external):** `Carboot_Data_Entry_Template_Complete.xlsx` / sample CSV (53 columns, 9 sample rows). Schema contract: `report/09_SURVEY_SCHEMA_CONTRACT.md`.

---

## Data sources summary

| Source | Location | Event link | Notes |
|--------|----------|------------|-------|
| Operational DB | `bookings`, `invoices`, `event_sites`, `vendor_categories`, `item_reservations` | Via `carboot_event_id` | Core post-event snapshot |
| Community feedback form | `feedbacks` table | **No `carboot_event_id`** | `participation_type`, `community_backgrounds`, `rating`, `comments` |
| Vendor reuse marketplace | `vendor_items`, `reuse_item_images` | Indirect via vendor user | Not event-scoped |
| Python word clouds | `feedbacks.comments`, `bookings.product_details` | Global queries | No event filter |
| CSV (repo) | `python_analytics/*_word_cloud.csv` | N/A | Export output only; `Word,Frequency` schema |
| Survey CSV (proposed) | — | — | **Not implemented** |

### Feedback schema (effective)

From migrations under `backend/database/migrations/`:

`id`, `user_id`, `reviewer_role`, `participation_type`, `community_backgrounds` (JSON), `comments`, `rating`, `service_rating`, `value_rating`, `media_path`, `helpful_count`, `is_hidden`, moderation fields, timestamps.

Classification enums: `backend/app/Support/FeedbackClassification.php`

---

## Metric catalogue

### A. Event overview

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Total bookings | How many booking records exist for this event? | `bookings` | `COUNT(*)` WHERE `carboot_event_id` | Per event | approval_status | **Directly available** | Aggregate only | KPI card | Includes non-approved |
| Approved vendor count | How many vendors were approved? | `bookings` | `COUNT` WHERE `approval_status='Approved'` | Per event | — | **Directly available** | Aggregate | KPI card | One booking ≈ one vendor slot (verify multi-day allocations) |
| Booking pipeline breakdown | What is the approval funnel? | `bookings.approval_status` | `GROUP BY approval_status` | Per event | status | **Directly available** | Aggregate | Stacked bar | Implemented in `PostEventSummaryAggregator` |
| Event site count | How many sites were configured? | `event_sites` | `COUNT(*)` | Per event | operational_status | **Directly available** | Aggregate | KPI + breakdown | Requires `event_sites` migration applied |
| Site status breakdown | How many sites active/disabled? | `event_sites.operational_status` | `GROUP BY operational_status` | Per event | — | **Directly available** | Aggregate | Donut chart | In snapshot |
| Provisional report flag | Is event still in progress? | `carboot_events.status`, `ends_at` | `isProvisional()` logic | Per event | — | **Derivable** | N/A | Badge | In snapshot |
| Max slots vs approved | Is the event full? | `carboot_events.max_slots`, `bookings` | approved / max_slots | Per event | — | **Derivable** | Aggregate | Gauge | Boss revenue uses **upcoming** event globally — not per selected event |
| Data completeness score | Is data sufficient for reporting? | Multiple tables | Weighted checklist | Per event | — | **Partially available** | N/A | Checklist UI | Only in snapshot `data_availability`; no dedicated score |

---

### B. Vendor and participant profile

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Vendor category distribution | What types of vendors participated? | `bookings` + `vendor_categories` | Resolved label counts | Per event | approved only | **Directly available** | No vendor PII in snapshot | Bar chart | `PostEventSummaryAggregator::vendorCategoryDistribution` |
| Product category (legacy booking) | What product categories were booked? | `bookings.product_category` | `GROUP BY product_category` | Per event / global | approved | **Directly available** | Aggregate | Bar chart | Boss revenue uses global scope |
| Survey product categories (Q1) | What did vendors report selling? | External CSV `q1_*` → `product_categories` | Count selected one-hots / respondents | Per event after import | category | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Bar chart | Taxonomy differs from booking categories; compare after import |
| Item condition new/used (Q2) | New vs used vs N/A mix? | CSV `q2_*` → `item_conditions` | Multi-select frequencies | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Donut | `tidak_berkenaan` for food/services |
| Participation type mix | Who gave feedback — shoppers, vendors, crew? | `feedbacks.participation_type` | `GROUP BY participation_type` | Global today | date range | **Partially available** / **Blocked by missing event linkage** | Aggregate | Donut | Live community feedback — not the vendor CSV |
| Community background mix | What community segments participated in feedback? | `feedbacks.community_backgrounds` | JSON array unnest + count | Global | participation_type | **Partially available** / **Blocked by missing event linkage** | Aggregate; avoid small-n identification | Stacked bar | Live community feedback |
| Community feedback respondent count | How many public feedback responses? | `feedbacks` | `COUNT(*)` | Per event (future) / global (today) | hidden=false | **Blocked by missing event linkage** | Aggregate | KPI | Needs `feedbacks.carboot_event_id` |
| Vendor survey respondent count | How many vendor survey forms for the event? | External CSV rows / `survey_responses` | `COUNT(*)` per import batch | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | KPI | Event id from upload route, not CSV column |
| Purpose of selling (Q8) | Why are vendors selling? | CSV `q8_tujuan_jualan` → `sales_purpose` | Categorical distribution | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Bar chart | Vendor purpose — not visitor purpose |
| Vendor business profile completeness | Are vendors profile-complete? | `vendor_business_profiles` | Field presence % | Per vendor / global | — | **Derivable** | Vendor-level | Table | Vendor analytics only (`VendorAnalyticsService`) |
| Walk-in vendor count | How many non-booked walk-ins? | — | — | — | — | **Unavailable** | — | — | Not in vendor survey instrument |
| Participant demographics (age, gender) | Who attended demographically? | — | — | — | — | **Unavailable** | — | — | Not in vendor survey or DB |

---

### C. Economic outcomes

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Expected invoice total | What fees were invoiced for approved vendors? | `invoices` via approved `bookings` | `SUM(amount)` | Per event | payment_status | **Directly available** | Aggregate RM | KPI | Platform fees, not vendor gross sales |
| Collected revenue | How much was paid? | `invoices.payment_status='Paid'` | `SUM(amount)` | Per event | — | **Directly available** | Aggregate | KPI | In snapshot `payments.collected` |
| Outstanding revenue | How much remains unpaid? | `invoices.payment_status='Unpaid'` | `SUM(amount)` | Per event | — | **Directly available** | Aggregate | KPI | In snapshot |
| Invoice count | How many invoices issued? | `invoices` | `COUNT` | Per event | — | **Directly available** | Aggregate | KPI | In snapshot |
| Payment status breakdown | Paid vs unpaid distribution? | `invoices` | `GROUP BY payment_status` | Per event / global | — | **Directly available** | Aggregate | Donut | Boss panel global |
| Gross sales range (vendor survey Q6) | What sales band did vendors report? | CSV `q6_jualan_kasar` → `gross_sales_band` | Categorical band counts | Per event after import | vendor | **Available from external survey CSV — import pipeline not implemented** | Aggregate bands only | Histogram | Bands confirmed in Codebook; **do not convert to exact RM** |
| Exact vendor gross sales RM | What did vendors earn? | — | — | — | — | **Unavailable** | — | — | Do not infer from invoice fees or sales bands |
| % of used items sold (vendor survey Q5) | What used-item sell-through did vendors report? | CSV `q5_barang_terjual` → `items_sold_band` | Categorical % bands | Per event after import | used-goods vendors | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Distribution | Applies to **barangan terpakai**; includes N/A for new/food |
| Economic value of reuse | What RM value was reused? | `vendor_items` price? `item_reservations.service_fee_amount` | Sum fees or listed prices | Per event / vendor | — | **Partially available** | Aggregate | KPI | No standard methodology in codebase; `ImpactDashboard` hard-coded mock |
| F&B share of vendors | What share is food & beverage? | `bookings.product_category` | F&B count / total approved | Global / per event | — | **Derivable** | Aggregate | % indicator | Boss `fb_share_percent` — global; survey Q1 also supports after import |

---

### D. Item reuse and sustainability

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Item reservation count | How many reuse reservations at the event? | `item_reservations` | `COUNT` by `reservation_status` | Per event | status | **Directly available** | Aggregate | Bar chart | Optional table; snapshot handles omission |
| Reservation status breakdown | Pending vs completed reservations? | `item_reservations.reservation_status` | `GROUP BY` | Per event | — | **Directly available** | Aggregate | Stacked bar | In snapshot when table exists |
| Active reuse listings (vendor) | How many items listed for reuse? | `vendor_items.status` | `COUNT` active/inactive | Per vendor | — | **Directly available** | Vendor-scoped | Donut | `VendorAnalyticsService`; not event-scoped |
| Items reused (proxy) | How many reuse items active? | `vendor_items` | `COUNT` active | Per vendor | — | **Partially available** | Proxy metric | KPI | `items_reused` = active listing count — not items physically sold |
| ESG / carbon / waste diverted | What sustainability impact? | — | — | — | — | **Unavailable** | — | — | No carbon/waste measurement methodology; survey does not collect kg/CO₂ |
| Reuse marketplace activity at event | Buyer-seller matches at event? | `item_reservations` | Completed count | Per event | completed | **Derivable** | Aggregate | KPI | Requires reservations linked to event |
| Circularity / reuse proxies (survey Q2+Q7) | What happens to unsold used goods? | CSV `item_conditions` + `unsold_item_actions` | Frequency of `sumbangkan`, `kitar_semula`, `simpan_acara_lain`, `buang`, etc. | Per event after import | used-goods subset | **Available from external survey CSV — import pipeline not implemented** / **Derivable after import** | Aggregate | Stacked bar | Proxy only — not verified diversion tonnes |
| Used-goods vendor share (Q2) | What share sold used items? | CSV `q2_terpakai` | % with terpakai selected | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | KPI | Complements marketplace listing metrics |

---

### E. Experience and satisfaction

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Average overall rating (community) | How satisfied were public respondents? | `feedbacks.rating` | `AVG(rating)` | Global / per event (future) | hidden=false | **Partially available** / **Blocked by missing event linkage** | Aggregate | Star + number | Live feedback — separate from vendor survey |
| Rating distribution (1–5) | How are ratings spread? | `feedbacks.rating` | `GROUP BY rating` | Global | — | **Directly available** | Aggregate | Bar chart | `FeedbackController::buildPublicSummary` |
| Service rating | How was service rated? | `feedbacks.service_rating` | `AVG` | Global | — | **Partially available** | Aggregate | KPI | Form currently copies overall rating to service/value on submit |
| Value rating | Value for money? | `feedbacks.value_rating` | `AVG` | Global | — | **Partially available** | Same as above | KPI | Not independently collected in UI |
| Feedback response count | How many community responses? | `feedbacks` | `COUNT` | Per event (future) | — | **Blocked by missing event linkage** | Aggregate | KPI | Snapshot omits without event FK |
| Vendor experience (survey Q9) | How did vendors rate their experience? | CSV `q9_pengalaman` → `experience_rating` | Categorical distribution | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Ordered bar | 4-level BM scale from Codebook |
| Satisfaction themes (community text) | What do people say? | `feedbacks.comments` | Word frequency | Global | — | **Directly available** | Avoid quoting individuals in reports | Word cloud | Python `main.py`; includes hidden feedback |
| Vendor comments / suggestions (Q11) | What do vendors suggest? | CSV `q11_komen_cadangan` → `comments_and_suggestions` | Text themes / listing | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Redact before publish | Word cloud / quotes | Sample mostly `Tiada` |
| Purpose of selling (see also §B Q8) | Why do vendors sell? | CSV `q8_tujuan_jualan` | Categorical distribution | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Bar | Visitor “purpose of attending” remains **Unavailable** |
| Helpful votes | Which feedback is useful? | `feedbacks.helpful_count` | `SUM` / top N | Global | — | **Directly available** | Public aggregate | Leaderboard | Not in reports |

---

### F. Promotion and engagement

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Organizer promotional-channel effectiveness | Which outbound channels drove attendance? | — | — | — | — | **Unavailable** | — | — | Not collected as organizer campaign analytics |
| Vendor event-information sources (Q4) | Where did vendors get event information? | CSV `q4_*` → `event_info_sources` | Multi-select frequencies | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Bar chart | Questionnaire: “Di manakah anda mendapat maklumat acara ini?” — **vendor** info source, not visitor discovery |
| Visitor event discovery sources | How did visitors hear about the event? | — | — | — | — | **Unavailable** | — | — | No visitor survey instrument in scope |
| Supporting-activity effectiveness (Q12) | Did side activities attract more visitors (vendor perception)? | CSV `q12_aktiviti_tarik_pengunjung` | Categorical distribution | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Bar | Perception metric |
| Supporting-activity impacts (Q13) | What effects did concurrent activities have? | CSV `q13_*` → `supporting_activity_impacts` | Multi-select frequencies | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Stacked bar | Mix of positive/negative impacts |
| News/activity engagement | Did CMart posts drive interest? | `news_posts` | Views? shares? | — | — | **Unavailable** | — | — | No analytics on news engagement |
| Booking conversion rate | Registrations vs approvals? | `bookings` | approved / total | Per event | — | **Derivable** | Aggregate | Funnel | Not pre-built |
| Check-in rate | Who attended vs booked? | `bookings.checked_in_at` | checked-in / approved | Per event | — | **Derivable** | Aggregate | % | Column exists (`2026_06_18_000002`); not in snapshot |
| Social / WhatsApp links | Vendor promotion links? | `bookings.whatsapp_link` | Count non-null | Per event | — | **Partially available** | — | — | Link presence only, not performance |

---

### G. Operational improvement

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Operational issues (community text) | What problems were reported publicly? | `feedbacks.comments` | NLP themes / word cloud | Global | — | **Partially available** | Redact PII in reports | Word cloud | Not categorized issue taxonomy |
| Vendor registration/info difficulty (Q3) | Did vendors face registration or info difficulty? | CSV `q3_kesukaran`, `q3_kesukaran_teks` | % Yes + text themes | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate + redacted text | KPI + list | Sample observed only `0` (Tidak) |
| Vendor improvement priorities (Q10) | What should organizers improve? | CSV `q10_*` → `improvement_areas` | Multi-select frequencies | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Aggregate | Ranked bar | Includes structured taxonomy + other text |
| Vendor suggestions (Q11) | What improvements are suggested? | CSV `q11_komen_cadangan` | Text themes | Per event after import | — | **Available from external survey CSV — import pipeline not implemented** | Redact | Themes | Complements Q10 |
| Participant suggestions (community) | What do public respondents suggest? | `feedbacks.comments` | Same as community issues | Global | — | **Partially available** | Same | Word cloud | Separate from vendor survey |
| Booking revision rate | How many bookings needed revision? | `bookings.approval_status` | Needs_Revision count | Per event | — | **Directly available** | Aggregate | KPI | In pipeline breakdown |
| Attendance exceptions | No-shows / exceptions? | `booking_attendance_exceptions` | `COUNT` | Per event | — | **Derivable** | Aggregate | Table | Table may not exist in all environments |
| Layout/site operational issues | Site disabled counts? | `event_sites.operational_status` | disabled count | Per event | — | **Directly available** | Aggregate | KPI | In snapshot |
| Staff moderation backlog | Unreviewed feedback? | `feedbacks.reviewed_at` | `COUNT` WHERE null | Global | — | **Directly available** | Staff only | Queue count | Staff panel, not hub |
| Audit trail of booking decisions | Who approved what? | `booking_audit_logs` | Paginated log | Global | — | **Directly available** | Staff names visible | Table | `BossAuditLogsPanel` |

---

### H. Data quality

| Metric | Organizer question | Source | Calculation | Aggregation | Filters | Availability | Privacy | Visualization | Limitations |
|--------|-------------------|--------|-------------|-------------|---------|--------------|---------|---------------|-------------|
| Core schema presence | Can report generate? | `Schema::hasTable/Column` | Boolean checks | Per environment | — | **Directly available** | N/A | Error message | `PostEventSummaryAggregator::assertCoreSchema` |
| Optional metric omission flags | What is missing? | Aggregator | `data_availability` map | Per report | — | **Directly available** | N/A | List in report | Honest omission — no fake zeros |
| Feedback event linkage | Can feedback be scoped? | `feedbacks` | `hasColumn carboot_event_id` | — | — | **Unavailable** | — | — | Column not migrated |
| Survey CSV import status | Was post-event survey uploaded? | Proposed `raw_survey_uploads` | Status + counts | Per event | — | **Unavailable** in app (pipeline missing) | N/A | Badge | External CSV exists; app cannot track uploads yet |
| Hidden feedback exclusion | Are moderated-out rows excluded? | `feedbacks.is_hidden` | Filter in queries | — | — | **Partially available** | — | — | Snapshot would filter; Python wordcloud does **not** |
| Legacy role normalization | Are old reviewer_role values consistent? | `feedbacks.reviewer_role` vs `participation_type` | Mapping | — | — | **Partially available** | — | — | `FeedbackClassification::legacyReviewerRoleToParticipation` |
| Database health | Is DB readable? | Environment | Connection + table read | — | — | **Requires validation** | — | — | Prior audit: error 1932 on `cmart_db` |

---

## CSV structures

### A. In-repo analytics export (not survey import)

```csv
Word,Frequency
sedap,14
makanan,12
```

**Files:** `python_analytics/feedback_word_cloud.csv`, `python_analytics/vendor_word_cloud.csv`  
**Direction:** Database → CSV export (offline).

### B. External vendor post-event survey (`vendor_post_event_v1`)

- **53 columns**, Q1–Q13 + validation columns — fully documented in [`09_SURVEY_SCHEMA_CONTRACT.md`](09_SURVEY_SCHEMA_CONTRACT.md).
- Sample responses exist (n=9) but are **not ingested** by the application.
- Import pipeline, normalized `survey_responses` storage, and event-scoped analytics computation remain unimplemented.

---

## Metrics summary by availability (post-amendment)

| Status | Approx. count | Examples |
|--------|---------------|----------|
| Directly available (operational DB) | ~22 | Booking pipeline, payments, site counts |
| Derivable (operational DB) | ~8 | Utilization %, check-in rate |
| Available from external survey CSV — import not implemented | ~16 | Q1–Q13 survey metrics listed above |
| Derivable after import | ~2 | Circularity proxies from Q2+Q7 |
| Partially available | ~12 | Community feedback (global) |
| Blocked by missing event linkage | ~4 | Event-scoped community feedback ratings/counts |
| Unavailable | ~6 | Exact RM sales, visitor discovery, demographics, ESG tonnes, walk-ins, organizer campaign analytics |

---

## Privacy considerations (cross-cutting)

- **Post-event snapshot** explicitly excludes vendor identity (`vendor_categories` note in aggregator).
- **Boss audit logs** expose staff actor names — organizer-only.
- **Vendor analytics** includes vendor email in export report — vendor-self only.
- **Small-n suppression** not implemented — participation/background charts could identify individuals in small events.
- **Feedback photos** (`media_path`) — public listing may need redaction rules in reports.
