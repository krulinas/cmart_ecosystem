<?php

namespace App\Support;

use App\Models\GeneratedReport;
use Carbon\Carbon;

/**
 * Prepares Post-Event Summary PDF view data without mutating stored snapshots.
 */
final class PostEventReportPdfViewData
{
    /**
     * @return array{
     *     report: GeneratedReport,
     *     snapshot: array<string, mixed>,
     *     pdf_downloaded_at_display: string,
     *     published_at_display: ?string,
     *     audience: string
     * }
     */
    public static function forAudience(GeneratedReport $report, string $audience = 'organizer'): array
    {
        $raw = is_array($report->snapshot) ? $report->snapshot : [];
        $snapshot = $audience === 'cmart'
            ? CmartReportSnapshotFilter::forCmart($raw)
            : self::forOrganizerPresentation($raw);

        $publishedAt = $report->published_at
            ? ReportDateTimeFormatter::datetime($report->published_at->toIso8601String())
            : null;

        return [
            'report' => $report,
            'snapshot' => $snapshot,
            'pdf_downloaded_at_display' => ReportDateTimeFormatter::datetime(
                Carbon::now(ReportDateTimeFormatter::TIMEZONE)->toIso8601String()
            ),
            'published_at_display' => $publishedAt,
            'audience' => $audience,
        ];
    }

    /**
     * Organizer PDF still must not render free-text survey comments.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private static function forOrganizerPresentation(array $snapshot): array
    {
        if (isset($snapshot['sections']['vendor_survey']) && is_array($snapshot['sections']['vendor_survey'])) {
            unset($snapshot['sections']['vendor_survey']['qualitative_comments']);
        }

        return $snapshot;
    }
}
