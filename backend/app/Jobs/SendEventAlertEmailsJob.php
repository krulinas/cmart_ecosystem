<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\EventAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Background job: send one email per registered user when an event is updated or cancelled.
 *
 * The HTTP controller / observer only dispatches this job — the actual SMTP work
 * happens here on a queue worker, keeping the staff dashboard responsive.
 */
class SendEventAlertEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string  $alertType  Either "updated" or "cancelled".
     * @param  array<string, mixed>  $eventSnapshot  Frozen copy of event data for the email body.
     * @param  array<int>  $userIds  Primary keys of users to notify.
     * @param  array<string, mixed>  $changes  Changed fields (only for "updated" alerts).
     */
    public function __construct(
        public string $alertType,
        public array $eventSnapshot,
        public array $userIds,
        public array $changes = [],
    ) {}

    /**
     * Laravel calls this method when a queue worker picks up the job.
     */
    public function handle(): void
    {
        $users = User::query()
            ->whereIn('id', $this->userIds)
            ->get();

        foreach ($users as $user) {
            $user->notify(new EventAlertNotification(
                alertType: $this->alertType,
                eventSnapshot: $this->eventSnapshot,
                changes: $this->changes,
            ));
        }
    }
}
