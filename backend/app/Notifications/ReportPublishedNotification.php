<?php

namespace App\Notifications;

use App\Models\GeneratedReport;
use App\Support\ReportNotificationType;
use App\Support\ReportType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public GeneratedReport $report,
        public bool $isRevision = false,
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
        $typeLabel = ReportType::label($this->report->report_type);
        $eventTitle = $this->report->event_title_snapshot
            ?: ($this->report->carbootEvent?->title ?? 'an event');

        if ($this->isRevision) {
            return [
                'type' => ReportNotificationType::REVISED,
                'title' => 'Revised report published',
                'body' => "A revised {$typeLabel} for {$eventTitle} is ready to view (v{$this->report->version}).",
                'link' => '/admin#reports',
                'generated_report_id' => $this->report->id,
                'carboot_event_id' => $this->report->carboot_event_id,
                'version' => $this->report->version,
            ];
        }

        return [
            'type' => ReportNotificationType::PUBLISHED,
            'title' => 'Report published',
            'body' => "A {$typeLabel} for {$eventTitle} is ready to view (v{$this->report->version}).",
            'link' => '/admin#reports',
            'generated_report_id' => $this->report->id,
            'carboot_event_id' => $this->report->carboot_event_id,
            'version' => $this->report->version,
        ];
    }
}
