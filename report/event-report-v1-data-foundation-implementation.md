# Event Report V1 — Data Correctness and Privacy Foundation

Phase 1 implementation report. Scope: accurate, privacy-safe, event-specific Post-Event Summary data. Visual PDF redesign and media appendix are deferred.

## 1. Privacy changes

- New official snapshots no longer store `sections.vendor_survey.qualitative_comments` or other free-text survey fields.
- `App\Support\CmartReportSnapshotFilter` builds a privacy-safe snapshot tree for CMart consumers (JSON + PDF view data).
- Denied keys include free-text survey fields, emails, phones, names, user/vendor IDs, booking/invoice ID lists, payment proofs/references, and similar PII-like keys.
- Registration-capacity `event.status` / `max_slots` are stripped from CMart-facing snapshots so cover copy cannot show misleading `Available`.
- `CmartGeneratedReportResource` returns the filtered snapshot rather than forwarding raw stored JSON.
- Organizer PDF presentation also unsets `qualitative_comments` before render; the Blade template never prints free-text comments.
- Organizer-authored `organizer_observations` / `organizer_recommendations` remain version-specific and visible to CMart.

## 2. Snapshot schema additions (schema_version = 2)

Notable additions under the frozen snapshot:

- `language`, `timezone`, `generated_at_display`, `report_lifecycle_label`
- `event.starts_at_display` / `ends_at_display` / `date_range_display`
- Expanded `sections.booking_pipeline` (status counts, unique applicants, approved unique vendors)
- `sections.attendance` (verified check-ins vs not recorded)
- `sections.site_day_utilisation` (site-day formula + availability)
- Expanded `sections.payments` (expected/collected/unpaid/pending verification/refunded, paid withdrawals disclosure, missing-invoice warning) plus legacy aliases `expected` / `collected` / `outstanding`
- Categorical `sections.vendor_survey.distributions` with denominators and `n =` bases
- `sections.environmental_social` proxy indicators only
- Structured `methodology` and `data_quality_warnings`

Legacy schema_version 1 snapshots remain readable; missing new keys are not coerced to zero in preview/PDF.

## 3. Metric formulas

| Metric | Formula / rule |
| --- | --- |
| Unique applicants / approved unique vendors | `COUNT(DISTINCT user_id)` for the event, non-null `user_id` |
| Verified vendor check-ins | Count of event bookings with non-null `checked_in_at` |
| Site-day utilisation | Occupied active site-days ÷ available active site-days × 100 |
| Available active site-days | Active `event_sites` × event `event_days` |
| Occupied site-days | Distinct occupying (`reserved`/`confirmed` + `active_lock=1`) site/day pairs on active sites |
| Expected booth fees | Sum of invoice amounts for **approved** bookings |
| Collected booth fees | Paid approved invoices **plus** paid withdrawn invoices |
| Paid withdrawals disclosure | Count/amount of Paid invoices on Withdrawn bookings |

`max_slots` is never used as booth capacity.

## 4. Missing-versus-zero behaviour

- Genuine zeros remain when the metric was calculated and the result is zero (for example site-day utilisation 0% with a valid denominator).
- Attendance with zero check-ins is treated as **not recorded**, with message: `Attendance verification was not recorded for this event.`
- Empty survey sets `available: false` and `respondent_count: null` (not `0` as a fake chart base).
- Zero site-day denominator marks utilisation unavailable.
- Approved bookings without invoices are counted and flagged; they are not treated as RM0 due.
- Frontend `PostEventSummaryView.vue` and PDF Blade avoid `?? 0` display coercion for unavailable metrics.

## 5. Event-isolation safeguards

All aggregations filter by the selected `carboot_event_id` (bookings, invoices via booking relation, sites, days, allocations, reservations, feedback, survey responses). Lifetime `vendor_items` are not aggregated.

Feature coverage: shared vendor across Event A and Event B with separate invoices/surveys/allocations.

## 6. Financial inclusion rules

- Vendor survey sales bands are never treated as organizer revenue.
- Collected booth fees include non-refundable paid withdrawals, with explicit disclosure text.
- Pending Verification and Refunded amounts are reported separately when present.
- Missing invoices produce a data-quality warning and `potentially_incomplete = true`.

## 7. Survey aggregation and denominators

- PHP categorical aggregates aligned to stored survey fields / existing schema name.
- Overall respondent base: active analytics responses for the event (`SurveyResponse::forAnalytics`).
- Conditional reused-item questions (`items_sold_band`, `unsold_item_actions`) use respondents with `terpakai` in `item_conditions`.
- Multi-select rows expose `multi_select_note` (percentages may exceed 100%).
- Free-text is excluded from snapshot and CMart surfaces.
- No exact RM total is derived from sales bands.
- No new Python runtime dependency for PDF generation.

## 8. Exact files changed

### Backend

- `app/Services/PostEventSummaryAggregator.php` (rewrite, schema v2)
- `app/Support/CmartReportSnapshotFilter.php` (new)
- `app/Support/ReportDateTimeFormatter.php` (new)
- `app/Support/PostEventReportPdfViewData.php` (new)
- `app/Http/Resources/CmartGeneratedReportResource.php`
- `app/Http/Controllers/Api/CmartGeneratedReportController.php`
- `app/Http/Controllers/Api/OrganizerGeneratedReportController.php`
- `resources/views/reports/post_event_summary.blade.php`
- `tests/Unit/CmartReportSnapshotFilterTest.php` (new)
- `tests/Feature/EventReportV1DataFoundationTest.php` (new)

### Frontend

- `src/components/reports/PostEventSummaryView.vue`
- `tests/unit/postEventSummaryView.test.js` (new)

### Report

- `report/event-report-v1-data-foundation-implementation.md` (this file)

## 9. Tests added or changed

**Backend**

- Unit: CMart filter + KL English date formatting
- Feature: privacy allowlist / historical free-text, no free-text in new snapshots, event isolation, immutability + revision, booking pipeline + unique vendors, attendance, site-day utilisation (multi-day + zero denominator), financial paid withdrawals + missing invoice, survey categorical/conditional/multi-select, no-response survey, legacy snapshot compatibility, CMart API filtering

**Frontend**

- Source-level unit checks for missing≠zero, cover status, no free-text, attendance/utilisation messaging, legacy payment keys

## 10. Commands and results

```text
# Backend
php vendor/bin/phpunit tests/Unit/CmartReportSnapshotFilterTest.php tests/Feature/EventReportV1DataFoundationTest.php --testdox
# Result: OK — 14 tests, 92 assertions (1 PHPUnit deprecation unrelated to assertions)

php -l on changed PHP files
# Result: No syntax errors detected

# Frontend
node --test tests/unit/postEventSummaryView.test.js
# Result: 5/5 pass

npm run build
# Result: success (vite production build)

npm run lint
# Result: fails on pre-existing unused `mode` in src/utils/surveyChartConfig.js (unrelated to this phase)
```

Local `cmart_test` required migration repair before Feature tests could run (tables existed without matching migration rows for some report/survey migrations). No development/production seed data was modified for fixtures.

## 11. DomPDF verification status

**Not visually verified.** PDF view data plumbing was updated (`PostEventReportPdfViewData`, Blade honesty/privacy), and privacy filtering of PDF snapshot data is covered by Feature assertions, but no DomPDF binary was rendered/inspected in a browser for layout.

## 12. Remaining limitations

- Visual 6–8 page PDF redesign not done.
- Poster/photo media appendix not implemented.
- Activities, visitor estimates, weather, walk-ins not in scope.
- Attendance is a single `checked_in_at` count; multi-day complete attendance is explicitly not claimed.
- Survey inclusion still follows event `analytics_source_mode` (`combined` / `csv_only` include survey; `system_only` excludes it).
- Organizer JSON resource still returns the stored snapshot for drafts; new snapshots omit free-text, and CMart/PDF paths filter historical free-text.
- Unsupported ESG calculations remain forbidden; only survey proxies are shown.
- Frontend lint currently fails on an unrelated pre-existing oxlint finding.

## 13. Inputs required for the later visual/PDF phase

- Confirmed page budget and section order for the polished PDF.
- Brand/typography assets and any mandatory CMart cover elements.
- Whether organizer observations should appear on a dedicated page.
- Chart styling requirements for categorical survey distributions.
- Media appendix rules (poster/photo upload limits, captions, retention).
- Whether Provisional/Final should also drive organizer-facing badges beyond the current cover label.
- Decision on whether official reports should always include survey aggregates even when analytics mode is `system_only`.
