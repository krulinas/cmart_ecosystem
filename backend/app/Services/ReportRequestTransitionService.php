<?php

namespace App\Services;

use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Models\User;
use App\Support\ReportRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Enforces report-request status transitions and persists actor timestamps.
 */
class ReportRequestTransitionService
{
    public function __construct(
        private readonly ReportWorkflowAuditor $auditor,
    ) {}

    /**
     * One active request per event + type (requested|acknowledged|in_progress).
     *
     * @throws ValidationException
     */
    public function assertNoActiveDuplicate(int $eventId, string $reportType): void
    {
        $exists = ReportRequest::query()
            ->where('carboot_event_id', $eventId)
            ->where('report_type', $reportType)
            ->whereIn('status', ReportRequestStatus::ACTIVE)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'carboot_event_id' => 'An active report request already exists for this event and type.',
            ]);
        }
    }

    public function acknowledge(ReportRequest $reportRequest, User $actor, ?Request $httpRequest = null): ReportRequest
    {
        $this->assertTransition(
            $reportRequest,
            ReportRequestStatus::ACKNOWLEDGED,
            ReportRequestStatus::ACTOR_ORGANIZER,
        );

        $reportRequest->update([
            'status' => ReportRequestStatus::ACKNOWLEDGED,
            'acknowledged_by' => $actor->id,
            'acknowledged_at' => now(),
        ]);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_REQUEST_ACKNOWLEDGED,
            $actor,
            $reportRequest->fresh(),
            null,
            null,
            null,
            $httpRequest,
        );

        return $reportRequest->fresh();
    }

    public function startPreparation(ReportRequest $reportRequest, User $actor, ?Request $httpRequest = null): ReportRequest
    {
        $this->assertTransition(
            $reportRequest,
            ReportRequestStatus::IN_PROGRESS,
            ReportRequestStatus::ACTOR_ORGANIZER,
        );

        $updates = [
            'status' => ReportRequestStatus::IN_PROGRESS,
        ];

        if ($reportRequest->acknowledged_at === null) {
            $updates['acknowledged_by'] = $actor->id;
            $updates['acknowledged_at'] = now();
        }

        $reportRequest->update($updates);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_REQUEST_PREPARATION_STARTED,
            $actor,
            $reportRequest->fresh(),
            null,
            null,
            null,
            $httpRequest,
        );

        return $reportRequest->fresh();
    }

    public function decline(
        ReportRequest $reportRequest,
        User $actor,
        string $reason,
        ?string $responseMessage = null,
        ?Request $httpRequest = null,
    ): ReportRequest {
        $this->assertTransition(
            $reportRequest,
            ReportRequestStatus::DECLINED,
            ReportRequestStatus::ACTOR_ORGANIZER,
        );

        $reportRequest->update([
            'status' => ReportRequestStatus::DECLINED,
            'declined_by' => $actor->id,
            'declined_at' => now(),
            'decline_reason' => $reason,
            'response_message' => $responseMessage,
        ]);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_REQUEST_DECLINED,
            $actor,
            $reportRequest->fresh(),
            null,
            null,
            ['decline_reason' => $reason],
            $httpRequest,
        );

        return $reportRequest->fresh();
    }

    public function cancel(ReportRequest $reportRequest, User $actor, ?Request $httpRequest = null): ReportRequest
    {
        $this->assertTransition(
            $reportRequest,
            ReportRequestStatus::CANCELLED,
            ReportRequestStatus::ACTOR_CMART,
        );

        $reportRequest->update([
            'status' => ReportRequestStatus::CANCELLED,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
        ]);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_REQUEST_CANCELLED,
            $actor,
            $reportRequest->fresh(),
            null,
            null,
            null,
            $httpRequest,
        );

        return $reportRequest->fresh();
    }

    public function markFulfilled(
        ReportRequest $reportRequest,
        ?User $actor = null,
        ?Request $httpRequest = null,
    ): ReportRequest {
        $this->assertTransition(
            $reportRequest,
            ReportRequestStatus::FULFILLED,
            ReportRequestStatus::ACTOR_SYSTEM,
        );

        $reportRequest->update([
            'status' => ReportRequestStatus::FULFILLED,
            'fulfilled_at' => now(),
        ]);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_REQUEST_FULFILLED,
            $actor,
            $reportRequest->fresh(),
            null,
            null,
            null,
            $httpRequest,
        );

        return $reportRequest->fresh();
    }

    /**
     * @throws ValidationException
     */
    private function assertTransition(ReportRequest $reportRequest, string $to, string $actor): void
    {
        if (! ReportRequestStatus::canTransition($reportRequest->status, $to, $actor)) {
            throw ValidationException::withMessages([
                'status' => sprintf(
                    'Cannot transition report request from "%s" to "%s" as %s.',
                    $reportRequest->status,
                    $to,
                    $actor,
                ),
            ]);
        }
    }
}
