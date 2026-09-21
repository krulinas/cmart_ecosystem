<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email sent to community users when a carboot event they joined is updated or cancelled.
 */
class EventAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $alertType  "updated" or "cancelled"
     * @param  array<string, mixed>  $eventSnapshot
     * @param  array<string, mixed>  $changes
     */
    public function __construct(
        public string $alertType,
        public array $eventSnapshot,
        public array $changes = [],
    ) {}

    /**
     * We only send email for now (no database or SMS channels).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the outgoing email using Laravel's fluent MailMessage helper.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->eventSnapshot['title'] ?? __('mail.default_title');
        $startsAt = $this->eventSnapshot['starts_at'] ?? __('mail.tbc');

        if ($this->alertType === 'cancelled') {
            return (new MailMessage)
                ->subject(__('mail.event_cancelled_subject', ['title' => $title]))
                ->greeting(__('mail.hello', ['name' => $notifiable->name]))
                ->line(__('mail.cancelled_line', ['title' => $title]))
                ->line(__('mail.originally_scheduled', ['starts' => $startsAt]))
                ->line(__('mail.contact_management'))
                ->salutation(__('mail.salutation'));
        }

        $mail = (new MailMessage)
            ->subject(__('mail.event_updated_subject', ['title' => $title]))
            ->greeting(__('mail.hello', ['name' => $notifiable->name]))
            ->line(__('mail.updated_line', ['title' => $title]));

        if (! empty($this->changes)) {
            $mail->line(__('mail.changes_intro'));
            foreach ($this->changes as $field => $value) {
                // Skip Laravel internal timestamp-only noise when possible.
                if (in_array($field, ['updated_at'], true)) {
                    continue;
                }
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $mail->line("- {$field}: {$displayValue}");
            }
        }

        $mail->line(__('mail.current_start', ['starts' => $startsAt]));

        return $mail->salutation(__('mail.salutation'));
    }
}
