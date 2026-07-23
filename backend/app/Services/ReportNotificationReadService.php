<?php

namespace App\Services;

use App\Models\User;
use App\Support\ReportNotificationType;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Marks only related report-workflow notifications as read.
 */
class ReportNotificationReadService
{
    public function markForRequest(User $user, int $reportRequestId): int
    {
        return $this->markMatching($user, ['report_request_id' => $reportRequestId]);
    }

    public function markForReport(User $user, int $generatedReportId): int
    {
        return $this->markMatching($user, ['generated_report_id' => $generatedReportId]);
    }

    /**
     * @param  array<string, int>  $match
     */
    private function markMatching(User $user, array $match): int
    {
        $count = 0;
        $key = array_key_first($match);
        $value = $match[$key];

        $user->unreadNotifications()
            ->get()
            ->each(function (DatabaseNotification $notification) use ($key, $value, &$count) {
                $data = $notification->data ?? [];
                $type = $data['type'] ?? null;
                if (! is_string($type) || ! in_array($type, ReportNotificationType::all(), true)) {
                    return;
                }
                if ((int) ($data[$key] ?? 0) !== (int) $value) {
                    return;
                }
                $notification->markAsRead();
                $count++;
            });

        return $count;
    }
}
