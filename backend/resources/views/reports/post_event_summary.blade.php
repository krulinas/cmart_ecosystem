<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->event_title_snapshot }} — Post-Event Report</title>
    <style>
        @page { margin: 22mm 16mm 20mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.45;
        }
        .page-cover { page-break-after: always; }
        .section { page-break-inside: avoid; margin-bottom: 16px; }
        .section-break { page-break-before: always; }
        h1 { font-size: 22px; color: #014a7a; margin: 0 0 6px; letter-spacing: 0.04em; }
        h2 {
            font-size: 13px;
            color: #014a7a;
            margin: 0 0 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #b3e5fc;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        h3 { font-size: 11px; color: #0277BD; margin: 12px 0 6px; }
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
        .kpi-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px; }
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
        }
        .panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv th, table.kv td { padding: 6px 4px; border-bottom: 1px solid #eef2f7; vertical-align: top; }
        table.kv th { width: 48%; color: #475569; font-weight: normal; text-align: left; }
        table.kv td { font-weight: bold; color: #0f172a; }
        .bar-row { margin: 5px 0 7px; }
        .bar-label { overflow: hidden; margin-bottom: 2px; }
        .bar-label .name { float: left; color: #334155; }
        .bar-label .val { float: right; color: #0f172a; font-weight: bold; }
        .bar-track { clear: both; height: 7px; background: #e2e8f0; border: 0; }
        .bar-fill { height: 7px; background: #0277BD; }
        .bar-fill.is-green { background: #059669; }
        .bar-fill.is-amber { background: #d97706; }
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
            bottom: -14mm;
            font-size: 8.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
        .footer .right { float: right; }
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
    $utilisation = is_array($sections['site_day_utilisation'] ?? null) ? $sections['site_day_utilisation'] : [];
    $categories = is_array($sections['vendor_categories'] ?? null) ? $sections['vendor_categories'] : [];
    $survey = is_array($sections['vendor_survey'] ?? null) ? $sections['vendor_survey'] : [];
    $environmental = is_array($sections['environmental_social'] ?? null) ? $sections['environmental_social'] : [];
    $methodology = is_array($snapshot['methodology'] ?? null) ? $snapshot['methodology'] : [];

    $provisional = !empty($snapshot['provisional']);
    $coverStatus = $provisional
        ? 'Provisional'
        : (($report->status === 'published' || $report->status === 'superseded') ? 'Final' : null);

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
    <span>{{ $eventTitle }} · Post-Event Report · Version {{ $report->version }}</span>
    <span class="right">Prepared for CMart · Snapshot figures are not recalculated on download</span>
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
            <tr><th>Venue</th><td>{{ $venue }}</td></tr>
            <tr><th>Prepared by</th><td>Carboot Organizer</td></tr>
            <tr><th>Prepared for</th><td>CMart</td></tr>
            <tr><th>Report version</th><td>Version {{ $report->version }}</td></tr>
            <tr><th>Publication date</th><td>{{ $publishedDisplay ?? 'Not yet published' }}</td></tr>
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
                <td><div class="kpi-label">Applications</div><div class="kpi-value">{{ (int) $totalApps }}</div></td>
            @endif
            @if ($approvedBookings !== null)
                <td><div class="kpi-label">Approved bookings</div><div class="kpi-value">{{ (int) $approvedBookings }}</div></td>
            @endif
            @if ($approvedVendors !== null)
                <td><div class="kpi-label">Approved vendors</div><div class="kpi-value">{{ (int) $approvedVendors }}</div></td>
            @endif
            @if ($attendanceRecorded)
                <td><div class="kpi-label">Verified check-ins</div><div class="kpi-value">{{ (int) $attendance['verified_check_in_count'] }}</div></td>
            @endif
        </tr>
        <tr>
            @if ($utilisationOk && isset($utilisation['utilisation_percent']))
                <td><div class="kpi-label">Site-day utilisation</div><div class="kpi-value">{{ $utilisation['utilisation_percent'] }}%</div></td>
            @endif
            @if ($collected !== null)
                <td><div class="kpi-label">Collected booth fees</div><div class="kpi-value">{{ Pres::money($collected) }}</div></td>
            @endif
            @if ($surveyOk && isset($survey['respondent_count']))
                <td><div class="kpi-label">Survey respondents</div><div class="kpi-value">{{ (int) $survey['respondent_count'] }}</div></td>
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
            <tr><th>Event</th><td>{{ $eventTitle }}</td></tr>
            <tr><th>Date &amp; time</th><td>{{ $dateRange ?? 'Not recorded' }}</td></tr>
            <tr><th>Venue</th><td>{{ $venue }}</td></tr>
        </table>
    </div>

    @if ($pipelineOk)
        <h3>Applications and pipeline</h3>
        <table class="kv">
            <tr><th>Applications</th><td>{{ $totalApps !== null ? (int) $totalApps : 'Not recorded' }}</td></tr>
            <tr><th>Unique applicants</th><td>{{ $uniqueApplicants !== null ? (int) $uniqueApplicants : 'Not recorded' }}</td></tr>
            <tr><th>Approved bookings</th><td>{{ $approvedBookings !== null ? (int) $approvedBookings : 'Not recorded' }}</td></tr>
            <tr><th>Approved vendors</th><td>{{ $approvedVendors !== null ? (int) $approvedVendors : 'Not recorded' }}</td></tr>
        </table>
        @if ($statusBars !== [])
            <div style="margin-top: 8px;">
                @foreach ($statusBars as $label => $count)
                    @php $pct = $statusMax > 0 ? round(($count / $statusMax) * 100) : 0; @endphp
                    <div class="bar-row">
                        <div class="bar-label"><span class="name">{{ $label }}</span><span class="val">{{ $count }}</span></div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ max(4, $pct) }}%;"></div></div>
                    </div>
                @endforeach
            </div>
            <p class="note">Only statuses with recorded applications are shown. Application counts and unique-vendor counts are reported separately. Approved bookings are not verified attendance.</p>
        @endif
    @endif

    <h3>Verified check-ins</h3>
    @if ($attendanceRecorded)
        <table class="kv">
            <tr><th>Verified check-ins</th><td>{{ (int) $attendance['verified_check_in_count'] }}</td></tr>
        </table>
        <p class="note">A single check-in timestamp does not prove complete multi-day attendance.</p>
    @else
        <p class="muted">{{ $attendance['message'] ?? 'Attendance verification was not recorded for this event.' }}</p>
    @endif

    @if (!empty($utilisation) && empty($utilisation['excluded']))
        <h3>Site-day utilisation</h3>
        @if ($utilisationOk)
            <table class="kv">
                <tr><th>Available active site-days</th><td>{{ $utilisation['available_active_site_days'] }}</td></tr>
                <tr><th>Occupied site-days</th><td>{{ $utilisation['occupied_site_days'] }}</td></tr>
                <tr><th>Site-day utilisation</th><td>{{ $utilisation['utilisation_percent'] }}%</td></tr>
            </table>
            @php
                $utilPct = (float) $utilisation['utilisation_percent'];
            @endphp
            <div class="bar-row" style="margin-top:8px;">
                <div class="bar-track"><div class="bar-fill is-green" style="width: {{ max(2, min(100, $utilPct)) }}%;"></div></div>
            </div>
            <p class="note">Site-day utilisation = occupied active site-days ÷ available active site-days × 100. Unavailable sites are excluded. This is not unique physical-booth occupancy.</p>
        @else
            <p class="muted">{{ $utilisation['message'] ?? 'Not available for this event' }}</p>
        @endif
    @endif

    @if ($categoryRows !== [])
        <h3>Approved vendor categories</h3>
        @foreach ($categoryRows as $row)
            @php
                $count = (int) ($row['count'] ?? 0);
                $pct = $categoryMax > 0 ? round(($count / $categoryMax) * 100) : 0;
                $label = $row['label'] ?? $row['category'] ?? 'Unspecified';
            @endphp
            <div class="bar-row">
                <div class="bar-label"><span class="name">{{ $label }}</span><span class="val">{{ $count }}</span></div>
                <div class="bar-track"><div class="bar-fill" style="width: {{ max(4, $pct) }}%;"></div></div>
            </div>
        @endforeach
    @endif
</section>

{{-- 4. Financial Summary --}}
@if ($paymentsOk)
<section class="section section-break">
    <h2>3. Financial Summary</h2>
    <p class="note" style="margin-top:0;">Organizer booth-fee invoices only. Vendor-reported survey sales are not organizer revenue.</p>
    <table class="kv">
        <tr><th>Expected booth fees</th><td>{{ Pres::money($expected) ?? 'Not available for this event' }}</td></tr>
        <tr><th>Collected booth fees</th><td class="money-pos">{{ Pres::money($collected) ?? 'Not available for this event' }}</td></tr>
        <tr><th>Unpaid amount</th><td class="money-warn">{{ Pres::money($unpaid) ?? 'Not available for this event' }}</td></tr>
        @if ($pendingPay !== null)
            <tr><th>Pending verification</th><td>{{ Pres::money($pendingPay) }}</td></tr>
        @endif
        @if ($refunded !== null)
            <tr><th>Refunded</th><td>{{ Pres::money($refunded) }}</td></tr>
        @endif
        @if ($collectionRate !== null)
            <tr><th>Collection rate</th><td>{{ $collectionRate }}%</td></tr>
        @endif
        @if ($withoutInvoice !== null)
            <tr><th>Approved bookings without invoices</th><td>{{ (int) $withoutInvoice }}</td></tr>
        @endif
    </table>
    @if (!empty($paidWd['disclosure']))
        <p class="note">{{ $paidWd['disclosure'] }}</p>
    @endif
    @if (!empty($payments['potentially_incomplete']))
        <div class="warn">Financial summary may be incomplete because one or more approved bookings have no invoice. Missing invoices are not treated as RM 0.00 due.</div>
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
                    <div class="bar-row">
                        <div class="bar-label"><span class="name">{{ $label }}</span><span class="val">{{ $count }}{{ $pctText }}</span></div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ max(4, $pctBar) }}%;"></div></div>
                    </div>
                @endforeach
            @elseif (!empty($distribution['message']))
                <p class="muted">{{ $distribution['message'] }}</p>
            @endif
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
        <tr><th>Vendors reporting reused / preloved goods</th><td>{{ (int) ($environmental['vendors_reporting_reused_goods'] ?? 0) }}</td></tr>
        <tr><th>Plans to donate</th><td>{{ (int) ($environmental['plans_to_donate'] ?? 0) }}</td></tr>
        <tr><th>Plans to recycle</th><td>{{ (int) ($environmental['plans_to_recycle'] ?? 0) }}</td></tr>
        <tr><th>Plans to relist / store</th><td>{{ (int) ($environmental['plans_to_relist_or_store'] ?? 0) }}</td></tr>
        <tr><th>Plans to dispose</th><td>{{ (int) ($environmental['plans_to_dispose'] ?? 0) }}</td></tr>
    </table>
    @php $soldBands = $environmental['used_stock_sales_bands']['rows'] ?? []; @endphp
    @if (!empty($soldBands))
        <h3>Used-stock sold bands</h3>
        <p class="note" style="margin-top:0;">{{ $environmental['used_stock_sales_bands']['base_display'] ?? '' }}</p>
        @foreach ($soldBands as $row)
            <div class="status-chip">{{ Pres::optionLabel($row['label'] ?? $row['key'] ?? null) }}: {{ (int) ($row['count'] ?? 0) }}</div>
        @endforeach
    @endif
    @php $supportRows = $environmental['supporting_activity_effect']['rows'] ?? []; @endphp
    @if (!empty($supportRows))
        <h3>Perceived effect of supporting activities</h3>
        @foreach ($supportRows as $row)
            <div class="status-chip">{{ Pres::optionLabel($row['label'] ?? $row['key'] ?? null) }}: {{ (int) ($row['count'] ?? 0) }}</div>
        @endforeach
    @endif
</section>
@endif

{{-- 7. Organizer Assessment --}}
@if (!empty($report->organizer_observations) || !empty($report->organizer_recommendations))
<section class="section section-break">
    <h2>6. Organizer Assessment</h2>
    @if (!empty($report->organizer_observations))
        <h3>Organizer observations</h3>
        <div class="narratives">{{ $report->organizer_observations }}</div>
    @endif
    @if (!empty($report->organizer_recommendations))
        <h3>Recommendations</h3>
        <div class="narratives">{{ $report->organizer_recommendations }}</div>
    @endif
</section>
@endif

{{-- 8. Methodology --}}
<section class="section">
    <h2>7. Methodology and Data Notes</h2>
    <table class="kv">
        <tr><th>Report scope</th><td>This report covers one carboot event only.</td></tr>
        <tr><th>Report version</th><td>Version {{ $report->version }}@if($coverStatus) ({{ $coverStatus }})@endif</td></tr>
        @if (!empty($methodology['data_cut_off']) || !empty($snapshot['generated_at_display']))
            <tr><th>Data cut-off</th><td>{{ $methodology['data_cut_off'] ?? $snapshot['generated_at_display'] }}</td></tr>
        @endif
        <tr><th>Applications vs unique vendors</th><td>Application counts and unique applicant/vendor counts are separate.</td></tr>
        <tr><th>Approved bookings vs attendance</th><td>Approved bookings are not labelled as attendance unless verified check-ins are recorded.</td></tr>
        <tr><th>Site-day utilisation</th><td>Occupied active site-days ÷ available active site-days × 100.</td></tr>
        @if ($surveyOk)
            <tr><th>Survey response base</th><td>{{ $survey['base_display'] ?? ('n = ' . (int) $survey['respondent_count'] . ' responses') }}</td></tr>
            <tr><th>Multi-select questions</th><td>Multiple responses allowed; percentages may exceed 100%.</td></tr>
        @endif
        <tr><th>Financial inclusion</th><td>Collected booth fees include paid approved invoices and paid withdrawn bookings under the non-refundable withdrawal policy. Pending verification and refunds are shown separately when present.</td></tr>
        <tr><th>Missing data</th><td>Missing or unavailable metrics are omitted or shown as Not recorded / Not available — never invented as zero.</td></tr>
        @if (!empty($methodology['data_quality_warnings']) && is_array($methodology['data_quality_warnings']))
            <tr><th>Data-quality warnings</th><td>{{ implode('; ', array_map('strval', $methodology['data_quality_warnings'])) }}</td></tr>
        @elseif (!empty($snapshot['data_quality_warnings']) && is_array($snapshot['data_quality_warnings']))
            <tr><th>Data-quality warnings</th><td>{{ implode('; ', array_map('strval', $snapshot['data_quality_warnings'])) }}</td></tr>
        @endif
        <tr><th>Provisional / Final</th><td>Provisional means the snapshot may still change. Final means the published snapshot for this version is frozen.</td></tr>
    </table>
</section>

</body>
</html>
