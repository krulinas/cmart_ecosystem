<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->event_title_snapshot }} — {{ __('reports.pdf.title_suffix') }}</title>
    <style>
        @page { margin: 22mm 16mm 24mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.45;
        }
        .page-cover { page-break-after: always; }
        /*
         * DomPDF 3.x / CPDF: do NOT keep whole report sections together.
         * Oversized blocks (especially Vendor Survey Q1–Q13) force DomPDF into
         * pathological fragmentation — footer-only and near-empty pages.
         * Keep page-break-inside: avoid only for compact units that fit on one page.
         *
         * Also avoid position:fixed footers with negative bottom offsets — DomPDF
         * treats that as overflow and invents blank/footer-only pages.
         */
        .section { margin-bottom: 14px; }
        /* Do not force section-break: always — that wastes pages and fights DomPDF flow. */
        .keep-together { page-break-inside: avoid; }
        h1 { font-size: 22px; color: #014a7a; margin: 0 0 6px; letter-spacing: 0.04em; }
        h2 {
            font-size: 13px;
            color: #014a7a;
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 2px solid #b3e5fc;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            page-break-after: avoid;
        }
        h3 {
            font-size: 11px;
            color: #0277BD;
            margin: 8px 0 4px;
            /* Avoid page-break-after: avoid here — with many survey charts it
               pushes each Q1–Q13 block onto its own page in DomPDF. */
        }
        .eyebrow { font-size: 10px; letter-spacing: 0.18em; text-transform: uppercase; color: #0277BD; font-weight: bold; }
        .muted { color: #64748b; font-size: 10px; }
        .note { color: #64748b; font-size: 9.5px; margin-top: 6px; }
        .warn {
            background: #fffbeb;
            border-left: 3px solid #d97706;
            color: #92400e;
            padding: 8px 10px;
            margin-top: 8px;
            font-size: 10px;
            page-break-inside: avoid;
        }
        .cover {
            border: 1px solid #e2e8f0;
            padding: 28px 24px;
            min-height: 220mm;
            position: relative;
        }
        .cover-brand { margin-bottom: 28px; }
        .cover-brand img { height: 42px; }
        .cover-brand-fallback {
            display: inline-block;
            background: #0277BD;
            color: #fff;
            font-weight: bold;
            padding: 10px 14px;
            font-size: 14px;
            letter-spacing: 0.08em;
        }
        .cover-title { margin-top: 48px; }
        .cover-event { font-size: 20px; color: #0f172a; font-weight: bold; margin: 14px 0 8px; }
        .cover-meta td { padding: 5px 0; vertical-align: top; }
        .cover-meta th { text-align: left; width: 34%; color: #64748b; font-weight: normal; padding: 5px 0; }
        .cover-badge {
            display: inline-block;
            margin-top: 18px;
            padding: 5px 12px;
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .cover-badge.is-provisional {
            background: #fffbeb;
            color: #b45309;
            border-color: #fcd34d;
        }
        .cover-footer {
            position: absolute;
            left: 24px;
            right: 24px;
            bottom: 24px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            color: #64748b;
            font-size: 9.5px;
        }
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 0 -6px;
            page-break-inside: avoid;
        }
        .kpi-table td {
            width: 25%;
            background: #f0f9ff;
            border: 1px solid #e0f2fe;
            padding: 10px 8px;
            vertical-align: top;
        }
        .kpi-label { font-size: 9px; color: #0277BD; text-transform: uppercase; letter-spacing: 0.04em; }
        .kpi-value { font-size: 16px; font-weight: bold; color: #0f172a; margin-top: 4px; }
        .summary-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv th, table.kv td { padding: 6px 4px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        table.kv th { width: 48%; color: #475569; font-weight: normal; text-align: left; }
        table.kv td { font-weight: bold; color: #0f172a; }
        table.kv tr { page-break-inside: avoid; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td {
            padding: 5px 4px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            vertical-align: top;
            font-size: 10px;
        }
        table.data th { color: #475569; font-weight: bold; }
        table.data tr { page-break-inside: avoid; }
        /* Compact chart row as a small table (no floats). Keep label+track as one
         * visual unit without page-break-inside: avoid — DomPDF fragments badly
         * when dozens of avoided rows stack inside a long survey section. */
        table.bar-unit {
            width: 100%;
            border-collapse: collapse;
            margin: 2px 0 4px;
        }
        table.bar-unit td {
            padding: 0;
            vertical-align: bottom;
        }
        table.bar-unit .name { color: #334155; text-align: left; }
        table.bar-unit .val {
            color: #0f172a;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
            width: 1%;
            padding-left: 8px;
        }
        table.bar-unit .track-cell { padding-top: 2px; }
        .bar-track { height: 7px; background: #e2e8f0; border: 0; }
        .bar-fill { height: 7px; background: #0277BD; }
        .bar-fill.is-green { background: #059669; }
        .bar-fill.is-amber { background: #d97706; }
        .survey-block { margin-bottom: 6px; }
        .status-chips { margin-top: 6px; }
        .status-chip {
            display: inline-block;
            margin: 0 6px 6px 0;
            padding: 3px 8px;
            background: #fff;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #334155;
        }
        .narratives {
            white-space: pre-wrap;
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 12px;
            font-size: 11px;
            line-height: 1.55;
            color: #1e293b;
        }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 14px;
            font-size: 8.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 3px;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-table td { padding: 0; font-size: 8.5px; color: #94a3b8; vertical-align: top; }
        .footer-table .right { text-align: right; }
        .money-pos { color: #047857; }
        .money-warn { color: #b45309; }
    </style>
</head>
<body>
@php
    use App\Support\PostEventReportPresentation as Pres;
    use App\Support\ReportDateTimeFormatter;

    $snapshot = $snapshot ?? ($report->snapshot ?? []);
    $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
    $event = is_array($snapshot['event'] ?? null) ? $snapshot['event'] : [];
    $pipeline = is_array($sections['booking_pipeline'] ?? null) ? $sections['booking_pipeline'] : [];
    $attendance = is_array($sections['attendance'] ?? null) ? $sections['attendance'] : [];
    $payments = is_array($sections['payments'] ?? null) ? $sections['payments'] : [];
    $eventPerformance = is_array($sections['event_performance'] ?? null) ? $sections['event_performance'] : [];
    $feedback = is_array($sections['feedback'] ?? null) ? $sections['feedback'] : [];
    $utilisation = is_array($sections['site_day_utilisation'] ?? null) ? $sections['site_day_utilisation'] : [];
    $categories = is_array($sections['vendor_categories'] ?? null) ? $sections['vendor_categories'] : [];
    $survey = is_array($sections['vendor_survey'] ?? null) ? $sections['vendor_survey'] : [];
    $environmental = is_array($sections['environmental_social'] ?? null) ? $sections['environmental_social'] : [];
    $methodology = is_array($snapshot['methodology'] ?? null) ? $snapshot['methodology'] : [];

    $provisional = !empty($snapshot['provisional']);
    $coverStatus = $provisional
        ? __('reports.pdf.provisional')
        : (($report->status === 'published' || $report->status === 'superseded') ? __('reports.pdf.final') : null);

    $venue = $snapshot['venue'] ?? ($event['venue'] ?? 'CMart');
    $eventTitle = $report->event_title_snapshot ?: ($event['title'] ?? 'Carboot Event');
    $dateRange = $event['date_range_display']
        ?? ReportDateTimeFormatter::range(
            $event['starts_at'] ?? optional($report->event_starts_at_snapshot)?->toIso8601String(),
            $event['ends_at'] ?? optional($report->event_ends_at_snapshot)?->toIso8601String(),
        );

    $metric = function ($section, string $key, $legacy = null) {
        if (! is_array($section) || (($section['excluded'] ?? false) === true)) {
            return null;
        }
        if (array_key_exists($key, $section) && $section[$key] !== null) {
            return $section[$key];
        }
        if ($legacy !== null && array_key_exists($legacy, $section) && $section[$legacy] !== null) {
            return $section[$legacy];
        }
        return null;
    };

    $pipelineOk = !empty($pipeline) && empty($pipeline['excluded']);
    $paymentsOk = !empty($payments) && empty($payments['excluded']);
    $eventPerformanceOk = !empty($eventPerformance) && empty($eventPerformance['excluded']);
    $feedbackOk = !empty($feedback) && empty($feedback['excluded']);
    $attendanceRecorded = !empty($attendance['recorded']) && ($attendance['verified_check_in_count'] ?? null) !== null;
    $utilisationOk = !empty($utilisation['available']) && empty($utilisation['excluded']);
    $surveyOk = !empty($survey['available']) && empty($survey['excluded']);
    $envOk = !empty($environmental['available']);
    $categoryRows = [];
    if (!empty($categories['distribution']) && is_array($categories['distribution'])) {
        $categoryRows = $categories['distribution'];
    }

    $totalApps = $pipelineOk ? $metric($pipeline, 'total_bookings') : null;
    $approvedBookings = $pipelineOk ? $metric($pipeline, 'approved_count') : null;
    $approvedVendors = $pipelineOk ? $metric($pipeline, 'approved_unique_vendors') : null;
    $uniqueApplicants = $pipelineOk ? $metric($pipeline, 'unique_applicants') : null;
    $expected = $paymentsOk ? $metric($payments, 'expected_booth_fees', 'expected') : null;
    $collected = $paymentsOk ? $metric($payments, 'collected_booth_fees', 'collected') : null;
    $unpaid = $paymentsOk ? $metric($payments, 'unpaid_approved', 'outstanding') : null;
    $pendingPay = $paymentsOk ? $metric($payments, 'pending_verification_approved') : null;
    $refunded = $paymentsOk ? $metric($payments, 'refunded_approved') : null;
    $withoutInvoice = $paymentsOk ? $metric($payments, 'approved_bookings_without_invoice') : null;
    $collectionRate = Pres::collectionRate(
        $collected !== null ? (float) $collected : null,
        $expected !== null ? (float) $expected : null,
    );
    $paidWd = is_array($payments['paid_withdrawals'] ?? null) ? $payments['paid_withdrawals'] : [];
    $logoPath = Pres::resolveLogoPath();

    $statusBars = [];
    if ($pipelineOk) {
        $by = is_array($pipeline['by_approval_status'] ?? null) ? $pipeline['by_approval_status'] : [];
        $statusMap = [
            'Pending' => (int) ($metric($pipeline, 'pending_count') ?? (($by['Pending_Organizer'] ?? 0) + ($by['Pending_Staff'] ?? 0) + ($by['Pending_Boss'] ?? 0))),
            'Needs revision' => (int) ($metric($pipeline, 'needs_revision_count') ?? ($by['Needs_Revision'] ?? 0)),
            'Approved' => (int) ($metric($pipeline, 'approved_count') ?? ($by['Approved'] ?? 0)),
            'Rejected' => (int) ($metric($pipeline, 'rejected_count') ?? ($by['Rejected'] ?? 0)),
            'Cancelled' => (int) ($metric($pipeline, 'cancelled_count') ?? ($by['Cancelled'] ?? 0)),
            'Withdrawn' => (int) ($metric($pipeline, 'withdrawn_count') ?? ($by['Withdrawn'] ?? 0)),
        ];
        foreach ($statusMap as $label => $count) {
            if ($count > 0) {
                $statusBars[$label] = $count;
            }
        }
    }
    $statusMax = $statusBars !== [] ? max($statusBars) : 1;
    $categoryMax = 1;
    foreach ($categoryRows as $row) {
        $categoryMax = max($categoryMax, (int) ($row['count'] ?? 0));
    }

    $summaryBits = [];
    if ($totalApps !== null) {
        $summaryBits[] = (int) $totalApps . ' applications were recorded for this event';
    }
    if ($approvedBookings !== null) {
        $summaryBits[] = (int) $approvedBookings . ' approved bookings';
    }
    if ($approvedVendors !== null) {
        $summaryBits[] = (int) $approvedVendors . ' approved unique vendors';
    }
    if ($attendanceRecorded) {
        $summaryBits[] = (int) $attendance['verified_check_in_count'] . ' verified check-ins';
    }
    if ($utilisationOk && isset($utilisation['utilisation_percent'])) {
        $summaryBits[] = 'site-day utilisation of ' . $utilisation['utilisation_percent'] . '%';
    }
    if ($collected !== null) {
        $summaryBits[] = 'collected booth fees of ' . Pres::money($collected);
    }
    if ($surveyOk && isset($survey['respondent_count'])) {
        $summaryBits[] = (int) $survey['respondent_count'] . ' survey responses';
    }
    $executiveSummary = $summaryBits === []
        ? 'This report summarises the available snapshot for the selected event. Some operational or survey indicators were not recorded.'
        : 'Based on the frozen event snapshot, this report covers ' . implode(', ', $summaryBits) . '. Figures reflect recorded system and survey data only and do not imply an overall success judgement.';

    $publishedDisplay = $published_at_display
        ?? ReportDateTimeFormatter::datetime(optional($report->published_at)?->toIso8601String());
@endphp

<div class="footer">
    <table class="footer-table"><tr>
        <td>{{ $eventTitle }} · {{ __('reports.pdf.title_suffix') }} · {{ __('reports.pdf.version') }} {{ $report->version }}</td>
        <td class="right">{{ __('reports.pdf_ui.footer_note') }}</td>
    </tr></table>
</div>

{{-- 1. Cover --}}
<section class="page-cover">
    <div class="cover">
        <div class="cover-brand">
            @if ($logoPath)
                <img src="{{ $logoPath }}" alt="CMart">
            @else
                <span class="cover-brand-fallback">CMart</span>
            @endif
        </div>
        <div class="cover-title">
            <div class="eyebrow">POST-EVENT REPORT</div>
            <div class="cover-event">{{ $eventTitle }}</div>
            <div class="muted">{{ $dateRange ?? 'Event date not recorded' }}</div>
        </div>
        <table class="cover-meta" style="margin-top: 28px; width: 100%;">
            <tr><th>{{ __('reports.pdf_ui.venue') }}</th><td>{{ $venue }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.prepared_by') }}</th><td>{{ __('reports.pdf_ui.carboot_organizer') }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.prepared_for') }}</th><td>CMart</td></tr>
            <tr><th>{{ __('reports.pdf_ui.report_version') }}</th><td>Version {{ $report->version }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.publication_date') }}</th><td>{{ $publishedDisplay ?? __('reports.pdf_ui.not_yet_published') }}</td></tr>
        </table>
        @if ($coverStatus)
            <div class="cover-badge {{ $provisional ? 'is-provisional' : '' }}">{{ $coverStatus }}</div>
        @endif
        <div class="cover-footer">
            Official Post-Event Report for a single carboot event. Content is privacy-safe and limited to aggregated metrics plus organizer-authored assessment.
        </div>
    </div>
</section>

{{-- 2. Executive Summary --}}
<section class="section">
    <h2>1. Executive Summary</h2>
    @if ($provisional)
        <div class="warn">This report is Provisional. Figures reflect the available snapshot and may change if a later version is published.</div>
    @endif
    <table class="kpi-table">
        <tr>
            @if ($totalApps !== null)
                <td><div class="kpi-label">{{ __('reports.pdf_ui.applications') }}</div><div class="kpi-value">{{ (int) $totalApps }}</div></td>
            @endif
            @if ($approvedBookings !== null)
                <td><div class="kpi-label">{{ __('reports.pdf_ui.approved_bookings') }}</div><div class="kpi-value">{{ (int) $approvedBookings }}</div></td>
            @endif
            @if ($approvedVendors !== null)
                <td><div class="kpi-label">{{ __('reports.pdf_ui.approved_vendors') }}</div><div class="kpi-value">{{ (int) $approvedVendors }}</div></td>
            @endif
            @if ($attendanceRecorded)
                <td><div class="kpi-label">{{ __('reports.pdf_ui.verified_checkins') }}</div><div class="kpi-value">{{ (int) $attendance['verified_check_in_count'] }}</div></td>
            @endif
        </tr>
        <tr>
            @if ($utilisationOk && isset($utilisation['utilisation_percent']))
                <td><div class="kpi-label">{{ __('reports.pdf_ui.site_day_utilisation') }}</div><div class="kpi-value">{{ $utilisation['utilisation_percent'] }}%</div></td>
            @endif
            @if ($collected !== null)
                <td><div class="kpi-label">{{ __('reports.pdf_ui.collected_booth_fees') }}</div><div class="kpi-value">{{ Pres::money($collected) }}</div></td>
            @endif
            @if ($surveyOk && isset($survey['respondent_count']))
                <td><div class="kpi-label">{{ __('reports.pdf_ui.survey_respondents') }}</div><div class="kpi-value">{{ (int) $survey['respondent_count'] }}</div></td>
            @endif
            <td></td>
        </tr>
    </table>
    <div class="summary-box">{{ $executiveSummary }}</div>
</section>

{{-- 3. Event and Participation --}}
<section class="section">
    <h2>2. Event and Participation</h2>
    <div class="panel">
        <table class="kv">
            <tr><th>{{ __('reports.pdf.event_title') }}</th><td>{{ $eventTitle }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.date_time') }}</th><td>{{ $dateRange ?? __('reports.pdf_ui.not_recorded') }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.venue') }}</th><td>{{ $venue }}</td></tr>
        </table>
    </div>

    @if ($pipelineOk)
        <h3>{{ __('reports.pdf_ui.applications_pipeline') }}</h3>
        <table class="kv">
            <tr><th>{{ __('reports.pdf_ui.applications') }}</th><td>{{ $totalApps !== null ? (int) $totalApps : __('reports.pdf_ui.not_recorded') }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.unique_applicants') }}</th><td>{{ $uniqueApplicants !== null ? (int) $uniqueApplicants : __('reports.pdf_ui.not_recorded') }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.approved_bookings') }}</th><td>{{ $approvedBookings !== null ? (int) $approvedBookings : __('reports.pdf_ui.not_recorded') }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.approved_vendors') }}</th><td>{{ $approvedVendors !== null ? (int) $approvedVendors : __('reports.pdf_ui.not_recorded') }}</td></tr>
        </table>
        @if ($statusBars !== [])
            <div style="margin-top: 8px;">
                @foreach ($statusBars as $label => $count)
                    @php $pct = $statusMax > 0 ? round(($count / $statusMax) * 100) : 0; @endphp
                    <table class="bar-unit">
                        <tr>
                            <td class="name">{{ $label }}</td>
                            <td class="val">{{ $count }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" class="track-cell">
                                <div class="bar-track"><div class="bar-fill" style="width: {{ max(4, $pct) }}%;"></div></div>
                            </td>
                        </tr>
                    </table>
                @endforeach
            </div>
            <p class="note">Only statuses with recorded applications are shown. Application counts and unique-vendor counts are reported separately. Approved bookings are not verified attendance.</p>
        @endif
    @endif

    <h3>{{ __('reports.pdf_ui.verified_checkins') }}</h3>
    @if ($attendanceRecorded)
        <table class="kv">
            <tr><th>{{ __('reports.pdf_ui.verified_checkins') }}</th><td>{{ (int) $attendance['verified_check_in_count'] }}</td></tr>
        </table>
        <p class="note">A single check-in timestamp does not prove complete multi-day attendance.</p>
    @else
        <p class="muted">{{ $attendance['message'] ?? 'Attendance verification was not recorded for this event.' }}</p>
    @endif

    @if (!empty($utilisation) && empty($utilisation['excluded']))
        <h3>{{ __('reports.pdf_ui.site_day_utilisation') }}</h3>
        @if ($utilisationOk)
            <table class="kv">
                <tr><th>{{ __('reports.pdf_ui.available_site_days') }}</th><td>{{ $utilisation['available_active_site_days'] }}</td></tr>
                <tr><th>{{ __('reports.pdf_ui.occupied_site_days') }}</th><td>{{ $utilisation['occupied_site_days'] }}</td></tr>
                <tr><th>{{ __('reports.pdf_ui.site_day_utilisation') }}</th><td>{{ $utilisation['utilisation_percent'] }}%</td></tr>
            </table>
            @php
                $utilPct = (float) $utilisation['utilisation_percent'];
            @endphp
            <div style="margin-top:8px;">
                <div class="bar-track"><div class="bar-fill is-green" style="width: {{ max(2, min(100, $utilPct)) }}%;"></div></div>
            </div>
            <p class="note">Site-day utilisation = occupied active site-days ÷ available active site-days × 100. Unavailable sites are excluded. This is not unique physical-booth occupancy.</p>
        @else
            <p class="muted">{{ $utilisation['message'] ?? __('reports.pdf.not_available') }}</p>
        @endif
    @endif

    @if ($categoryRows !== [])
        <h3>{{ __('reports.pdf_ui.approved_vendor_categories') }}</h3>
        @foreach ($categoryRows as $row)
            @php
                $count = (int) ($row['count'] ?? 0);
                $pct = $categoryMax > 0 ? round(($count / $categoryMax) * 100) : 0;
                $label = $row['label'] ?? $row['category'] ?? 'Unspecified';
            @endphp
            <table class="bar-unit">
                <tr>
                    <td class="name">{{ $label }}</td>
                    <td class="val">{{ $count }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="track-cell">
                        <div class="bar-track"><div class="bar-fill" style="width: {{ max(4, $pct) }}%;"></div></div>
                    </td>
                </tr>
            </table>
        @endforeach
    @endif
</section>

{{-- 4. Financial Summary --}}
@if ($paymentsOk)
<section class="section">
    <h2>3. Financial Summary</h2>
    <p class="note" style="margin-top:0;">Site booking revenue from frozen booking price snapshots and invoices. Vendor survey sales are not organizer revenue.</p>
    <table class="kv">
        <tr><th>{{ __('reports.pdf_ui.expected_booking_revenue') }}</th><td>{{ Pres::money($metric($payments, 'expected_booking_revenue', 'expected_booth_fees') ?? $expected) ?? __('reports.pdf.not_available') }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.invoiced_amount') }}</th><td>{{ Pres::money($metric($payments, 'invoiced_amount')) ?? __('reports.pdf.not_available') }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.collected_revenue') }}</th><td class="money-pos">{{ Pres::money($metric($payments, 'collected_revenue', 'collected_booth_fees') ?? $collected) ?? __('reports.pdf.not_available') }}</td></tr>
        <tr>
            <th>{{ __('reports.pdf_ui.outstanding_balance') }}</th>
            <td class="money-warn">
                @if (($metric($payments, 'invoice_count', 'invoice_count_approved') ?? 0) > 0)
                    {{ Pres::money($metric($payments, 'outstanding_invoice_balance', 'outstanding') ?? $unpaid) ?? __('reports.pdf.not_available') }}
                @else
                    {{ __('reports.pdf.not_available') }}
                @endif
            </td>
        </tr>
        <tr><th>{{ __('reports.pdf_ui.unbilled_value') }}</th><td>{{ Pres::money($metric($payments, 'unbilled_booking_value')) ?? __('reports.pdf.not_available') }}</td></tr>
        <tr>
            <th>{{ __('reports.pdf_ui.collection_rate') }}</th>
            <td>
                @if ($metric($payments, 'collection_rate_percent') !== null)
                    {{ $metric($payments, 'collection_rate_percent') }}%
                @elseif (($metric($payments, 'invoice_count', 'invoice_count_approved') ?? 0) <= 0)
                    {{ __('reports.pdf.not_available') }}
                @elseif ($collectionRate !== null)
                    {{ $collectionRate }}%
                @else
                    —
                @endif
            </td>
        </tr>
        @if ($withoutInvoice !== null)
            <tr><th>{{ __('reports.pdf_ui.approved_without_invoice') }}</th><td>{{ (int) $withoutInvoice }}</td></tr>
        @endif
    </table>
    @if (!empty($paidWd['disclosure']))
        <p class="note">{{ $paidWd['disclosure'] }}</p>
    @endif
    @if (!empty($payments['potentially_incomplete']))
        <div class="warn">Financial summary may be incomplete because one or more approved bookings have no invoice. Unbilled booking value is not outstanding invoice balance.</div>
    @endif
</section>
@endif

@if ($eventPerformanceOk)
<section class="section">
    <h2>{{ __('reports.pdf_ui.event_performance') }}</h2>
    <table class="kv">
        <tr><th>{{ __('reports.pdf_ui.unique_approved_vendors') }}</th><td>{{ $metric($eventPerformance, 'unique_approved_vendors') ?? '—' }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.approved_bookings') }}</th><td>{{ $metric($eventPerformance, 'approved_bookings') ?? '—' }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.open_booking_sites') }}</th><td>{{ $metric($eventPerformance, 'open_booking_sites') ?? '—' }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.sites_sold') }}</th><td>{{ $metric($eventPerformance, 'sites_sold') ?? '—' }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.available_sites') }}</th><td>{{ $metric($eventPerformance, 'available_sites') ?? '—' }}</td></tr>
        <tr>
            <th>{{ __('reports.pdf_ui.site_utilisation') }}</th>
            <td>
                @if ($metric($eventPerformance, 'site_utilisation_percent') !== null)
                    {{ $metric($eventPerformance, 'site_utilisation_percent') }}%
                @else
                    —
                @endif
            </td>
        </tr>
    </table>
    @if ($categoryRows !== [])
        <h3 style="margin-top:12px;">{{ __('reports.pdf_ui.vendor_category_distribution') }}</h3>
        <p class="note" style="margin-top:0;">Primary metric: unique participating vendors. Category totals may exceed unique vendors if a vendor has multiple category-labelled bookings.</p>
        <table class="data">
            <thead>
                <tr>
                    <th>{{ __('reports.pdf_ui.category') }}</th>
                    <th>{{ __('reports.pdf_ui.unique_vendors') }}</th>
                    <th>{{ __('reports.pdf_ui.approved_bookings') }}</th>
                    <th>{{ __('reports.pdf_ui.sites_sold') }}</th>
                    <th>{{ __('reports.pdf_ui.share_of_vendors') }}</th>
                    <th>{{ __('reports.pdf_ui.expected_booking_revenue') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categoryRows as $row)
                    <tr>
                        <td>{{ $row['label'] ?? 'Uncategorised' }}</td>
                        <td>{{ (int) ($row['unique_vendors'] ?? 0) }}</td>
                        <td>{{ (int) ($row['approved_bookings'] ?? 0) }}</td>
                        <td>{{ (int) ($row['sites_sold'] ?? 0) }}</td>
                        <td>
                            @if (isset($row['vendor_percent']) && $row['vendor_percent'] !== null)
                                {{ $row['vendor_percent'] }}%
                            @elseif (isset($row['vendor_share_percent']) && $row['vendor_share_percent'] !== null)
                                {{ $row['vendor_share_percent'] }}%
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ Pres::money($row['expected_booking_revenue'] ?? null) ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
@endif

@if ($feedbackOk)
<section class="section">
    <h2>{{ __('reports.pdf_ui.feedback_summary') }}</h2>
    <p class="note" style="margin-top:0;">Aggregate In-app Feedback only. Raw comments are excluded from published reports.</p>
    @if (($metric($feedback, 'response_count') ?? 0) > 0)
        <table class="kv">
            <tr><th>{{ __('reports.pdf_ui.total_feedback') }}</th><td>{{ (int) $metric($feedback, 'response_count') }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.vendor_responses') }}</th><td>{{ (int) ($metric($feedback, 'vendor_response_count') ?? 0) }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.non_vendor_responses') }}</th><td>{{ (int) ($metric($feedback, 'non_vendor_response_count') ?? 0) }}</td></tr>
            <tr><th>{{ __('reports.pdf_ui.average_rating') }}</th><td>{{ $metric($feedback, 'average_rating') ?? '—' }}</td></tr>
            <tr>
                <th>{{ __('reports.pdf_ui.vendor_response_rate') }}</th>
                <td>
                    @if ($metric($feedback, 'vendor_response_rate_percent') !== null)
                        {{ $metric($feedback, 'vendor_response_rate_percent') }}%
                    @else
                        —
                    @endif
                </td>
            </tr>
        </table>
        @php
            $ratingDist = is_array($feedback['rating_distribution'] ?? null) ? $feedback['rating_distribution'] : [];
            $participationDist = is_array($feedback['participation_distribution'] ?? null)
                ? $feedback['participation_distribution']
                : (is_array($feedback['participation_type_distribution'] ?? null)
                    ? $feedback['participation_type_distribution']
                    : []);
        @endphp
        @if ($ratingDist !== [])
            <h3 style="margin-top:12px;">{{ __('reports.pdf_ui.rating_distribution') }}</h3>
            <table class="kv">
                @foreach ([5, 4, 3, 2, 1] as $star)
                    <tr>
                        <th>{{ $star }}★</th>
                        <td>{{ (int) ($ratingDist[$star] ?? $ratingDist[(string) $star] ?? 0) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
        @if ($participationDist !== [])
            <h3 style="margin-top:12px;">{{ __('reports.pdf_ui.participant_type_distribution') }}</h3>
            <table class="kv">
                @foreach ($participationDist as $pkey => $prow)
                    @php
                        if (is_array($prow)) {
                            $plabel = $prow['label'] ?? $prow['type'] ?? 'Other';
                            $pcount = (int) ($prow['count'] ?? 0);
                        } else {
                            $plabel = is_string($pkey) ? $pkey : 'Other';
                            $pcount = (int) $prow;
                        }
                    @endphp
                    <tr>
                        <th>{{ $plabel }}</th>
                        <td>{{ $pcount }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @else
        <p class="note">{{ $feedback['message'] ?? 'No feedback has been submitted for this event yet.' }}</p>
    @endif
</section>
@endif

{{-- 5. Vendor and Sales Insights --}}
@if ($surveyOk || $categoryRows !== [])
<section class="section">
    <h2>4. Vendor and Sales Insights</h2>
    @if ($surveyOk)
        <p class="note" style="margin-top:0;">{{ $survey['base_display'] ?? ('n = ' . (int) ($survey['respondent_count'] ?? 0) . ' responses') }}. Categorical survey aggregates only; exact total vendor revenue is not calculated.</p>
        @foreach (($survey['distributions'] ?? []) as $name => $distribution)
            @continue(empty($distribution['rows']) && empty($distribution['message']))
            <div class="survey-block">
                <h3>{{ Pres::distributionTitle((string) $name) }}</h3>
                @if (!empty($distribution['rows']))
                    <p class="note" style="margin-top:0;">
                        {{ $distribution['base_display'] ?? '' }}
                        @if (!empty($distribution['denominator_note']))
                            · {{ $distribution['denominator_note'] }}
                        @endif
                        @if (!empty($distribution['multi_select']))
                            · Multiple responses allowed; percentages may exceed 100%.
                        @endif
                    </p>
                    @php
                        $distMax = 1;
                        foreach ($distribution['rows'] as $row) {
                            $distMax = max($distMax, (int) ($row['count'] ?? 0));
                        }
                    @endphp
                    @foreach ($distribution['rows'] as $row)
                        @php
                            $count = (int) ($row['count'] ?? 0);
                            $pctBar = $distMax > 0 ? round(($count / $distMax) * 100) : 0;
                            $label = Pres::optionLabel($row['label'] ?? $row['key'] ?? null);
                            $pctText = ($row['percent'] ?? null) !== null ? ' · ' . $row['percent'] . '%' : '';
                        @endphp
                        <table class="bar-unit">
                            <tr>
                                <td class="name">{{ $label }}</td>
                                <td class="val">{{ $count }}{{ $pctText }}</td>
                            </tr>
                            <tr>
                                <td colspan="2" class="track-cell">
                                    <div class="bar-track"><div class="bar-fill" style="width: {{ max(4, $pctBar) }}%;"></div></div>
                                </td>
                            </tr>
                        </table>
                    @endforeach
                @elseif (!empty($distribution['message']))
                    <p class="muted">{{ $distribution['message'] }}</p>
                @endif
            </div>
        @endforeach
    @elseif ($categoryRows !== [])
        <p class="muted">Survey responses were not available for this event. Approved vendor categories are shown in Participation.</p>
    @endif
</section>
@endif

{{-- 6. Environmental and Social --}}
@if ($envOk)
<section class="section">
    <h2>5. Environmental and Social Insights</h2>
    <p class="note" style="margin-top:0;"><strong>Vendor-reported survey indicators.</strong> These indicators are based on vendor responses and are not direct measurements of waste, carbon emissions or total items sold.</p>
    <table class="kv">
        <tr><th>{{ __('reports.pdf_ui.vendors_reused') }}</th><td>{{ (int) ($environmental['vendors_reporting_reused_goods'] ?? 0) }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.plans_donate') }}</th><td>{{ (int) ($environmental['plans_to_donate'] ?? 0) }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.plans_recycle') }}</th><td>{{ (int) ($environmental['plans_to_recycle'] ?? 0) }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.plans_relist') }}</th><td>{{ (int) ($environmental['plans_to_relist_or_store'] ?? 0) }}</td></tr>
        <tr><th>{{ __('reports.pdf_ui.plans_dispose') }}</th><td>{{ (int) ($environmental['plans_to_dispose'] ?? 0) }}</td></tr>
    </table>
    @php $soldBands = $environmental['used_stock_sales_bands']['rows'] ?? []; @endphp
    @if (!empty($soldBands))
        <h3>{{ __('reports.distribution.items_sold_band') }}</h3>
        <p class="note" style="margin-top:0;">{{ $environmental['used_stock_sales_bands']['base_display'] ?? '' }}</p>
        @foreach ($soldBands as $row)
            <div class="status-chip">{{ Pres::optionLabel($row['label'] ?? $row['key'] ?? null) }}: {{ (int) ($row['count'] ?? 0) }}</div>
        @endforeach
    @endif
    @php $supportRows = $environmental['supporting_activity_effect']['rows'] ?? []; @endphp
    @if (!empty($supportRows))
        <h3>{{ __('reports.distribution.supporting_activity_attracted_visitors') }}</h3>
        @foreach ($supportRows as $row)
            <div class="status-chip">{{ Pres::optionLabel($row['label'] ?? $row['key'] ?? null) }}: {{ (int) ($row['count'] ?? 0) }}</div>
        @endforeach
    @endif
</section>
@endif

{{-- 7. Organizer Assessment --}}
@if (!empty($report->organizer_observations) || !empty($report->organizer_recommendations))
<section class="section">
    <h2>6. Organizer Assessment</h2>
    @if (!empty($report->organizer_observations))
        <h3>{{ __('reports.pdf_ui.organizer_observations') }}</h3>
        <div class="narratives">{{ $report->organizer_observations }}</div>
    @endif
    @if (!empty($report->organizer_recommendations))
        <h3>{{ __('reports.pdf_ui.recommendations') }}</h3>
        <div class="narratives">{{ $report->organizer_recommendations }}</div>
    @endif
</section>
@endif

{{-- 8. Methodology --}}
<section class="section">
    <h2>{{ __('reports.pdf.methodology') }}</h2>
    <table class="kv">
        <tr><th>{{ __('reports.methodology.single_event_scope') }}</th><td>This report covers one carboot event only.</td></tr>
        <tr><th>{{ __('reports.pdf_ui.report_version') }}</th><td>Version {{ $report->version }}@if($coverStatus) ({{ $coverStatus }})@endif</td></tr>
        @if (!empty($methodology['data_cut_off']) || !empty($snapshot['generated_at_display']))
            <tr><th>{{ __('reports.methodology.data_cut_off') }}</th><td>{{ $methodology['data_cut_off'] ?? $snapshot['generated_at_display'] }}</td></tr>
        @endif
        <tr><th>{{ __('reports.pdf_ui.apps_vs_vendors') }}</th><td>Application counts and unique applicant/vendor counts are separate.</td></tr>
        <tr><th>{{ __('reports.pdf_ui.approved_vs_attendance') }}</th><td>Approved bookings are not labelled as attendance unless verified check-ins are recorded.</td></tr>
        <tr><th>{{ __('reports.pdf_ui.site_day_utilisation') }}</th><td>Occupied active site-days ÷ available active site-days × 100.</td></tr>
        @if ($surveyOk)
            <tr><th>{{ __('reports.methodology.survey_respondent_base') }}</th><td>{{ $survey['base_display'] ?? ('n = ' . (int) $survey['respondent_count'] . ' responses') }}</td></tr>
            <tr><th>{{ __('reports.methodology.multi_select_note') }}</th><td>Multiple responses allowed; percentages may exceed 100%.</td></tr>
        @endif
        <tr><th>{{ __('reports.methodology.financial_inclusion_rules') }}</th><td>Collected booth fees include paid approved invoices and paid withdrawn bookings under the non-refundable withdrawal policy. Pending verification and refunds are shown separately when present.</td></tr>
        <tr><th>{{ __('reports.methodology.missing_data_rule') }}</th><td>Missing or unavailable metrics are omitted or shown as Not recorded / Not available — never invented as zero.</td></tr>
        @if (!empty($methodology['data_quality_warnings']) && is_array($methodology['data_quality_warnings']))
            <tr><th>{{ __('reports.methodology.data_quality_warnings') }}</th><td>{{ implode('; ', array_map('strval', $methodology['data_quality_warnings'])) }}</td></tr>
        @elseif (!empty($snapshot['data_quality_warnings']) && is_array($snapshot['data_quality_warnings']))
            <tr><th>{{ __('reports.methodology.data_quality_warnings') }}</th><td>{{ implode('; ', array_map('strval', $snapshot['data_quality_warnings'])) }}</td></tr>
        @endif
        <tr><th>{{ __('reports.pdf_ui.provisional_final') }}</th><td>Provisional means the snapshot may still change. Final means the published snapshot for this version is frozen.</td></tr>
    </table>
</section>

</body>
</html>
