<?php

namespace App\Http\Resources;

use App\Support\PostEventReportProgramme;
use App\Support\ReportDateTimeFormatter;
use App\Support\ReportType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Organizer report-centre payload including draft internals.
 *
 * @mixin \App\Models\GeneratedReport
 */
class OrganizerGeneratedReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = is_array($this->snapshot) ? $this->snapshot : [];
        $generatedAt = $snapshot['generated_at'] ?? null;
        $generatedAtDisplay = $snapshot['generated_at_display']
            ?? ($generatedAt ? ReportDateTimeFormatter::datetime($generatedAt) : null);

        return [
            'id' => $this->id,
            'carboot_event_id' => $this->carboot_event_id,
            'report_request_id' => $this->report_request_id,
            'report_type' => $this->report_type,
            'report_type_label' => ReportType::label($this->report_type),
            'version' => $this->version,
            'status' => $this->status,
            'snapshot' => $this->snapshot,
            'snapshot_generated_at' => $generatedAt,
            'snapshot_generated_at_display' => $generatedAtDisplay,
            'organizer_observations' => $this->organizer_observations,
            'organizer_recommendations' => $this->organizer_recommendations,
            'programme_introduction' => $this->programme_introduction,
            'programme_objectives' => is_array($this->programme_objectives) ? $this->programme_objectives : [],
            'objectives_not_applicable' => (bool) $this->objectives_not_applicable,
            'conclusion' => $this->conclusion,
            'publish_readiness' => PostEventReportProgramme::publishReadiness($this->resource),
            'prepared_by' => $this->whenLoaded('preparedByUser', fn () => [
                'id' => $this->preparedByUser->id,
                'name' => $this->preparedByUser->name,
            ]),
            'published_by' => $this->whenLoaded('publishedByUser', fn () => $this->publishedByUser ? [
                'id' => $this->publishedByUser->id,
                'name' => $this->publishedByUser->name,
            ] : null),
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'revision_reason' => $this->revision_reason,
            'supersedes_report_id' => $this->supersedes_report_id,
            'event_title_snapshot' => $this->event_title_snapshot,
            'event_starts_at_snapshot' => optional($this->event_starts_at_snapshot)?->toIso8601String(),
            'event_ends_at_snapshot' => optional($this->event_ends_at_snapshot)?->toIso8601String(),
            'event' => $this->whenLoaded('carbootEvent', fn () => [
                'id' => $this->carbootEvent->id,
                'title' => $this->carbootEvent->title,
                'status' => $this->carbootEvent->status,
                'starts_at' => optional($this->carbootEvent->starts_at)?->toIso8601String(),
                'ends_at' => optional($this->carbootEvent->ends_at)?->toIso8601String(),
            ]),
            'report_request' => $this->whenLoaded('reportRequest', fn () => $this->reportRequest ? [
                'id' => $this->reportRequest->id,
                'status' => $this->reportRequest->status,
            ] : null),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
