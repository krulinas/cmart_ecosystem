<?php

namespace App\Http\Resources;

use App\Models\ReportWorkflowAudit;
use App\Services\ReportWorkflowTimelinePresenter;
use App\Support\ReportType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ReportRequest */
class OrganizerReportRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timeline = [];
        if ($this->relationLoaded('audits')) {
            $timeline = app(ReportWorkflowTimelinePresenter::class)
                ->forOrganizer($this->audits);
        }

        return [
            'id' => $this->id,
            'carboot_event_id' => $this->carboot_event_id,
            'event' => $this->whenLoaded('carbootEvent', fn () => [
                'id' => $this->carbootEvent->id,
                'title' => $this->carbootEvent->title,
                'starts_at' => optional($this->carbootEvent->starts_at)?->toIso8601String(),
                'ends_at' => optional($this->carbootEvent->ends_at)?->toIso8601String(),
                'status' => $this->carbootEvent->status,
            ]),
            'report_type' => $this->report_type,
            'report_type_label' => ReportType::label($this->report_type),
            'message' => $this->message,
            'preferred_due_date' => optional($this->preferred_due_date)?->toDateString(),
            'status' => $this->status,
            'decline_reason' => $this->decline_reason,
            'response_message' => $this->response_message,
            'acknowledged_at' => optional($this->acknowledged_at)?->toIso8601String(),
            'acknowledged_by' => $this->whenLoaded('acknowledgedByUser', fn () => $this->acknowledgedByUser ? [
                'id' => $this->acknowledgedByUser->id,
                'name' => $this->acknowledgedByUser->name,
            ] : null),
            'declined_at' => optional($this->declined_at)?->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)?->toIso8601String(),
            'fulfilled_at' => optional($this->fulfilled_at)?->toIso8601String(),
            'requested_by' => $this->whenLoaded('requester', fn () => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
            ]),
            'timeline' => $timeline,
            'notification_activity' => collect($timeline)
                ->filter(fn ($row) => ($row['kind'] ?? null) === 'external_simulation'
                    || in_array($row['action'] ?? '', [
                        ReportWorkflowAudit::ACTION_ORGANIZERS_NOTIFIED,
                        ReportWorkflowAudit::ACTION_CMART_NOTIFIED,
                    ], true))
                ->values()
                ->all(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
