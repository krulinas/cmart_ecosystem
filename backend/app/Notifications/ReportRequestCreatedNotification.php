<?php

namespace App\Notifications;

use App\Models\ReportRequest;
use App\Support\ReportNotificationType;
use App\Support\ReportType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportRequestCreatedNotification extends Notification
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

        return [
            'type' => ReportNotificationType::REQUEST_CREATED,
            'title' => 'New report request',
            'body' => "CMart requested a {$typeLabel} for {$eventTitle}.",
            'link' => '/admin#report-centre',
            'report_request_id' => $this->reportRequest->id,
            'carboot_event_id' => $this->reportRequest->carboot_event_id,
        ];
    }
}
