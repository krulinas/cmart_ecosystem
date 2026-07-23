<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->event_title_snapshot }} — Post-Event Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 32px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 22px; margin-bottom: 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        .meta { color: #555; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { width: 40%; color: #444; }
        .section-note { color: #666; font-size: 11px; margin-top: 4px; }
        .narratives { white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Post-Event Summary</h1>
    <div class="meta">
        <div><strong>Event:</strong> {{ $report->event_title_snapshot }}</div>
        <div><strong>Version:</strong> {{ $report->version }} ({{ $report->status }})</div>
        <div><strong>Published:</strong> {{ optional($report->published_at)->format('Y-m-d H:i') ?? '—' }}</div>
        <div><strong>Generated PDF:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</div>
    </div>

    @php($snapshot = $report->snapshot ?? [])
    @php($sections = $snapshot['sections'] ?? [])
    @php($event = $snapshot['event'] ?? [])

    <h2>Event</h2>
    <table>
        <tr><th>Venue</th><td>{{ $snapshot['venue'] ?? ($event['venue'] ?? '—') }}</td></tr>
        <tr><th>Status</th><td>{{ $event['status'] ?? '—' }}</td></tr>
        <tr><th>Starts</th><td>{{ $event['starts_at'] ?? optional($report->event_starts_at_snapshot)->toIso8601String() ?? '—' }}</td></tr>
        <tr><th>Ends</th><td>{{ $event['ends_at'] ?? optional($report->event_ends_at_snapshot)->toIso8601String() ?? '—' }}</td></tr>
        <tr><th>Provisional snapshot</th><td>{{ !empty($snapshot['provisional']) ? 'Yes' : 'No' }}</td></tr>
    </table>

    <h2>Bookings</h2>
    <table>
        <tr><th>Total bookings</th><td>{{ $sections['booking_pipeline']['total_bookings'] ?? 0 }}</td></tr>
        <tr><th>Approved</th><td>{{ $sections['booking_pipeline']['approved_count'] ?? 0 }}</td></tr>
    </table>

    <h2>Payments (approved bookings)</h2>
    <table>
        <tr><th>Expected</th><td>{{ number_format((float) ($sections['payments']['expected'] ?? 0), 2) }}</td></tr>
        <tr><th>Collected (Paid)</th><td>{{ number_format((float) ($sections['payments']['collected'] ?? 0), 2) }}</td></tr>
        <tr><th>Outstanding (Unpaid)</th><td>{{ number_format((float) ($sections['payments']['outstanding'] ?? 0), 2) }}</td></tr>
    </table>

    @if (!empty($report->organizer_observations))
        <h2>Organizer observations</h2>
        <div class="narratives">{{ $report->organizer_observations }}</div>
    @endif

    @if (!empty($report->organizer_recommendations))
        <h2>Organizer recommendations</h2>
        <div class="narratives">{{ $report->organizer_recommendations }}</div>
    @endif

    @if (!empty($snapshot['data_availability']))
        <h2>Data availability notes</h2>
        <table>
            @foreach ($snapshot['data_availability'] as $key => $value)
                <tr><th>{{ $key }}</th><td>{{ is_scalar($value) ? $value : json_encode($value) }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
