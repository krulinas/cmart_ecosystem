<?php

namespace App\Services;

use App\Models\GeneratedReport;
use App\Models\ReportWorkflowAudit;
use App\Models\User;
use App\Notifications\ReportPublishedNotification;
use App\Support\GeneratedReportStatus;
use App\Support\ReportRequestStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Publishes a draft generated report and notifies CMart Management consumers.
 */
class ReportPublicationService
{
    public function __construct(
        private readonly ReportWorkflowAuditor $auditor,
        private readonly ReportRequestTransitionService $transitions,
        private readonly ReportWorkflowRecipientResolver $recipients,
        private readonly ExternalAlertSimulationService $externalAlerts,
    ) {}

    public function publish(GeneratedReport $report, User $publisher, ?Request $httpRequest = null): GeneratedReport
    {
        $report->loadMissing(['carbootEvent', 'reportRequest']);

        if ($report->status !== GeneratedReportStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft reports can be published.',
            ]);
        }

        $event = $report->carbootEvent;
        if (! $this->isEligibleForPublication($event?->status, $event?->ends_at)) {
            throw ValidationException::withMessages([
                'carboot_event_id' => 'Event must be Closed or past ends_at before publishing a post-event summary.',
            ]);
        }

        $isRevision = (bool) $report->supersedes_report_id;

        $published = DB::transaction(function () use ($report, $publisher, $httpRequest, $isRevision) {
            $report->refresh();

            if ($report->status !== GeneratedReportStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft reports can be published.',
                ]);
            }

            if ($report->supersedes_report_id) {
                GeneratedReport::query()
                    ->whereKey($report->supersedes_report_id)
                    ->where('status', GeneratedReportStatus::PUBLISHED)
                    ->update(['status' => GeneratedReportStatus::SUPERSEDED]);
            }

            $report->update([
                'status' => GeneratedReportStatus::PUBLISHED,
                'published_by' => $publisher->id,
                'published_at' => now(),
            ]);

            if ($report->report_request_id && $report->reportRequest) {
                $linked = $report->reportRequest->fresh();
                if ($linked && $linked->status !== ReportRequestStatus::FULFILLED) {
                    try {
                        $this->transitions->markFulfilled($linked, $publisher, $httpRequest);
                    } catch (ValidationException) {
                        // Linked request may already be terminal; publication still succeeds.
                    }
                }
            }

            $published = $report->fresh(['carbootEvent', 'preparedByUser', 'publishedByUser', 'reportRequest']);

            $this->auditor->record(
                ReportWorkflowAudit::ACTION_PUBLISHED,
                $publisher,
                $published->reportRequest,
                $published,
                $published->carboot_event_id,
                [
                    'version' => $published->version,
                    'is_revision' => $isRevision,
                ],
                $httpRequest,
            );

            $this->notifyCmartManagement($published, $isRevision);

            return $published;
        });

        // Simulations are non-critical and run after the publication transaction commits.
        $this->externalAlerts->simulateReportPublished($published, $isRevision);

        return $published;
    }

    public function isEligibleForPublication(?string $status, $endsAt): bool
    {
        if ($status === 'Closed') {
            return true;
        }

        if ($endsAt === null) {
            return false;
        }

        return $endsAt instanceof \Carbon\CarbonInterface
            ? $endsAt->isPast()
            : \Carbon\Carbon::parse($endsAt)->isPast();
    }

    private function notifyCmartManagement(GeneratedReport $report, bool $isRevision): void
    {
        $users = $this->recipients->activeCmartManagement();
        $notification = new ReportPublishedNotification($report, $isRevision);

        foreach ($users as $user) {
            $user->notify($notification);
        }

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_CMART_NOTIFIED,
            null,
            $report->reportRequest,
            $report,
            $report->carboot_event_id,
            [
                'channel' => 'in_app',
                'recipient_count' => $users->count(),
                'recipient_role' => 'cmart_management',
                'is_revision' => $isRevision,
            ],
            null,
        );
    }
}
