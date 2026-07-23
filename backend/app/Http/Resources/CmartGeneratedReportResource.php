<?php

namespace App\Http\Resources;

use App\Support\ReportType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * CMart-facing published/superseded report payload (snapshot + consumer fields only).
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
        return [
            'id' => $this->id,
            'carboot_event_id' => $this->carboot_event_id,
            'report_type' => $this->report_type,
            'report_type_label' => ReportType::label($this->report_type),
            'version' => $this->version,
            'status' => $this->status,
            'snapshot' => $this->snapshot,
            'organizer_observations' => $this->organizer_observations,
            'organizer_recommendations' => $this->organizer_recommendations,
            'published_at' => optional($this->published_at)?->toIso8601String(),
            'published_by' => $this->whenLoaded('publishedByUser', fn () => $this->publishedByUser ? [
                'id' => $this->publishedByUser->id,
                'name' => $this->publishedByUser->name,
            ] : null),
            'event_title_snapshot' => $this->event_title_snapshot,
            'event_starts_at_snapshot' => optional($this->event_starts_at_snapshot)?->toIso8601String(),
            'event_ends_at_snapshot' => optional($this->event_ends_at_snapshot)?->toIso8601String(),
            'revision_reason' => $this->revision_reason,
            'supersedes_report_id' => $this->supersedes_report_id,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
