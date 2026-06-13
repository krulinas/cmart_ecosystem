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
        $title = $this->eventSnapshot['title'] ?? 'Carboot Event';
        $startsAt = $this->eventSnapshot['starts_at'] ?? 'TBC';

        if ($this->alertType === 'cancelled') {
            return (new MailMessage)
                ->subject("Event cancelled: {$title}")
                ->greeting("Hello {$notifiable->name},")
                ->line("We are sorry to inform you that the event \"{$title}\" has been cancelled.")
                ->line("Originally scheduled to start: {$startsAt}")
                ->line('If you have questions, please contact Cmart Management.')
                ->salutation('— Cmart Ecosystem Team');
        }

        $mail = (new MailMessage)
            ->subject("Event updated: {$title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The event \"{$title}\" you registered for has been updated by Cmart Management.");

        if (! empty($this->changes)) {
            $mail->line('The following details were changed:');
            foreach ($this->changes as $field => $value) {
                // Skip Laravel internal timestamp-only noise when possible.
                if (in_array($field, ['updated_at'], true)) {
                    continue;
                }
                $displayValue = is_array($value) ? json_encode($value) : (string) $value;
                $mail->line("- {$field}: {$displayValue}");
            }
        }

        $mail->line("Current start time: {$startsAt}");

        return $mail->salutation('— Cmart Ecosystem Team');
    }
}
