<?php

namespace App\Http\Resources;

use App\Support\CmartReportSnapshotFilter;
use App\Support\ReportDateTimeFormatter;
use App\Support\ReportType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CMart-facing published/superseded report payload.
 *
 * Constructs a privacy-safe snapshot rather than forwarding raw stored JSON.
 *
 * @mixin \App\Models\GeneratedReport
 */
class CmartGeneratedReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = CmartReportSnapshotFilter::forCmart(
            is_array($this->snapshot) ? $this->snapshot : null
        );

        $starts = optional($this->event_starts_at_snapshot)?->toIso8601String();
        $ends = optional($this->event_ends_at_snapshot)?->toIso8601String();
        $publishedAt = optional($this->published_at)?->toIso8601String();

        $provisional = (bool) ($snapshot['provisional'] ?? false);
        $coverStatus = $provisional
            ? 'Provisional'
            : ($this->status === 'published' || $this->status === 'superseded' ? 'Final' : null);

        return [
            'id' => $this->id,
            'carboot_event_id' => $this->carboot_event_id,
            'report_type' => $this->report_type,
            'report_type_label' => ReportType::label($this->report_type),
            'version' => $this->version,
            'status' => $this->status,
            'cover_status' => $coverStatus,
            'snapshot' => $snapshot,
            'organizer_observations' => $this->organizer_observations,
            'organizer_recommendations' => $this->organizer_recommendations,
            'published_at' => $publishedAt,
            'published_at_display' => ReportDateTimeFormatter::datetime($publishedAt),
            'published_by' => $this->whenLoaded('publishedByUser', fn () => $this->publishedByUser ? [
                'id' => $this->publishedByUser->id,
                'name' => $this->publishedByUser->name,
            ] : null),
            'event_title_snapshot' => $this->event_title_snapshot,
            'event_starts_at_snapshot' => $starts,
            'event_ends_at_snapshot' => $ends,
            'event_date_range_display' => ReportDateTimeFormatter::range($starts, $ends)
                ?? ($snapshot['event']['date_range_display'] ?? null),
            'revision_reason' => $this->revision_reason,
            'supersedes_report_id' => $this->supersedes_report_id,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
