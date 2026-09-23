<?php

namespace App\Support;

use App\Models\GeneratedReport;
use Illuminate\Support\Str;

/**
 * Presentation filenames for Post-Event PDF downloads.
 */
final class PostEventReportPdfFilename
{
    public static function forReport(GeneratedReport $report, string $audience = 'organizer'): string
    {
        $title = $report->event_title_snapshot
            ?: (is_array($report->snapshot) ? ($report->snapshot['event']['title'] ?? null) : null)
            ?: 'event';

        $slug = Str::slug((string) $title);
        if ($slug === '') {
            $slug = 'event';
        }

        $prefix = $audience === 'cmart' ? 'cmart' : 'organizer';

        return sprintf(
            '%s-post-event-report-%s-v%d.pdf',
            $prefix,
            $slug,
            (int) $report->version,
        );
    }
}
