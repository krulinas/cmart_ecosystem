<?php

namespace App\Notifications;

use App\Models\ReportRequest;
use App\Support\ReportNotificationType;
use App\Support\ReportType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportRequestDeclinedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ReportRequest $reportRequest,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $typeLabel = ReportType::label($this->reportRequest->report_type);
        $eventTitle = $this->reportRequest->carbootEvent?->title ?? 'an event';
        $reason = $this->reportRequest->decline_reason ?: 'No reason provided.';

        return [
            'type' => ReportNotificationType::REQUEST_DECLINED,
            'title' => 'Report request declined',
            'body' => "Organizer declined the {$typeLabel} for {$eventTitle}: {$reason}",
            'link' => '/admin#reports',
            'report_request_id' => $this->reportRequest->id,
            'carboot_event_id' => $this->reportRequest->carboot_event_id,
        ];
    }
}
