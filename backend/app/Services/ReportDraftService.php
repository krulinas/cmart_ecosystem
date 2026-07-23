<?php

namespace App\Services;

use App\Models\CarbootEvent;
use App\Models\GeneratedReport;
use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Models\User;
use App\Support\GeneratedReportStatus;
use App\Support\ReportRequestStatus;
use App\Support\ReportType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and refreshes draft generated reports (and revision drafts of published ones).
 */
class ReportDraftService
{
    public function __construct(
        private readonly PostEventSummaryAggregator $aggregator,
        private readonly ReportWorkflowAuditor $auditor,
        private readonly ReportRequestTransitionService $transitions,
    ) {}

    public function generate(
        CarbootEvent $event,
        User $user,
        ?ReportRequest $reportRequest = null,
        ?Request $httpRequest = null,
    ): GeneratedReport {
        $reportType = $reportRequest?->report_type ?? ReportType::POST_EVENT_SUMMARY;

        if (! ReportType::isValid($reportType)) {
            throw ValidationException::withMessages([
                'report_type' => 'Unsupported report type.',
            ]);
        }

        if ($reportRequest !== null) {
            if ((int) $reportRequest->carboot_event_id !== (int) $event->id) {
                throw ValidationException::withMessages([
                    'report_request_id' => 'Report request does not belong to this event.',
                ]);
            }

            if (! ReportRequestStatus::isActive($reportRequest->status)
                && $reportRequest->status !== ReportRequestStatus::FULFILLED
            ) {
                throw ValidationException::withMessages([
                    'report_request_id' => 'Cannot generate a draft from a declined or cancelled request.',
                ]);
            }
        }

        return DB::transaction(function () use ($event, $user, $reportRequest, $reportType, $httpRequest) {
            if ($reportRequest !== null
                && in_array($reportRequest->status, [
                    ReportRequestStatus::REQUESTED,
                    ReportRequestStatus::ACKNOWLEDGED,
                ], true)
            ) {
                $this->transitions->startPreparation($reportRequest, $user, $httpRequest);
                $reportRequest = $reportRequest->fresh();
            }

            $existingDraft = GeneratedReport::query()
                ->where('carboot_event_id', $event->id)
                ->where('report_type', $reportType)
                ->where('status', GeneratedReportStatus::DRAFT)
                ->when(
                    $reportRequest,
                    fn ($q) => $q->where('report_request_id', $reportRequest->id),
                    fn ($q) => $q->whereNull('report_request_id'),
                )
                ->lockForUpdate()
                ->first();

            if ($existingDraft) {
                return $this->regenerate($existingDraft, $user, $httpRequest);
            }

            $version = $this->nextVersion($event->id, $reportType);
            $snapshot = $this->aggregator->build($event);

            $report = GeneratedReport::create([
                'carboot_event_id' => $event->id,
                'report_request_id' => $reportRequest?->id,
                'report_type' => $reportType,
                'version' => $version,
                'status' => GeneratedReportStatus::DRAFT,
                'snapshot' => $snapshot,
                'prepared_by' => $user->id,
                'event_title_snapshot' => $event->title,
                'event_starts_at_snapshot' => $event->starts_at,
                'event_ends_at_snapshot' => $event->ends_at,
            ]);

            $this->auditor->record(
                ReportWorkflowAudit::ACTION_DRAFT_GENERATED,
                $user,
                $reportRequest,
                $report,
                $event->id,
                ['version' => $version, 'proactive' => $reportRequest === null],
                $httpRequest,
            );

            return $report->fresh(['carbootEvent', 'reportRequest', 'preparedByUser']);
        });
    }

    public function regenerate(
        GeneratedReport $report,
        User $user,
        ?Request $httpRequest = null,
    ): GeneratedReport {
        if ($report->status !== GeneratedReportStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft reports can be regenerated.',
            ]);
        }

        $report->loadMissing('carbootEvent');
        $event = $report->carbootEvent;
        if (! $event) {
            throw ValidationException::withMessages([
                'carboot_event_id' => 'Linked event is missing.',
            ]);
        }

        $snapshot = $this->aggregator->build($event);

        $report->update([
            'snapshot' => $snapshot,
            'prepared_by' => $user->id,
            'event_title_snapshot' => $event->title,
            'event_starts_at_snapshot' => $event->starts_at,
            'event_ends_at_snapshot' => $event->ends_at,
        ]);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_DRAFT_REGENERATED,
            $user,
            $report->reportRequest,
            $report->fresh(),
            $event->id,
            ['version' => $report->version],
            $httpRequest,
        );

        return $report->fresh(['carbootEvent', 'reportRequest', 'preparedByUser']);
    }

    public function createRevision(
        GeneratedReport $publishedReport,
        User $user,
        string $reason,
        ?Request $httpRequest = null,
    ): GeneratedReport {
        if ($publishedReport->status !== GeneratedReportStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => 'Only published reports can be revised.',
            ]);
        }

        $publishedReport->loadMissing('carbootEvent');
        $event = $publishedReport->carbootEvent;
        if (! $event) {
            throw ValidationException::withMessages([
                'carboot_event_id' => 'Linked event is missing.',
            ]);
        }

        return DB::transaction(function () use ($publishedReport, $user, $reason, $event, $httpRequest) {
            $openDraft = GeneratedReport::query()
                ->where('carboot_event_id', $publishedReport->carboot_event_id)
                ->where('report_type', $publishedReport->report_type)
                ->where('status', GeneratedReportStatus::DRAFT)
                ->lockForUpdate()
                ->exists();

            if ($openDraft) {
                throw ValidationException::withMessages([
                    'status' => 'A draft already exists for this event and report type. Finish or delete it before revising.',
                ]);
            }

            $version = $this->nextVersion($publishedReport->carboot_event_id, $publishedReport->report_type);
            $snapshot = $this->aggregator->build($event);

            $revision = GeneratedReport::create([
                'carboot_event_id' => $publishedReport->carboot_event_id,
                'report_request_id' => $publishedReport->report_request_id,
                'report_type' => $publishedReport->report_type,
                'version' => $version,
                'status' => GeneratedReportStatus::DRAFT,
                'snapshot' => $snapshot,
                'organizer_observations' => $publishedReport->organizer_observations,
                'organizer_recommendations' => $publishedReport->organizer_recommendations,
                'prepared_by' => $user->id,
                'revision_reason' => $reason,
                'supersedes_report_id' => $publishedReport->id,
                'event_title_snapshot' => $event->title,
                'event_starts_at_snapshot' => $event->starts_at,
                'event_ends_at_snapshot' => $event->ends_at,
            ]);

            $this->auditor->record(
                ReportWorkflowAudit::ACTION_REVISION_CREATED,
                $user,
                $revision->reportRequest,
                $revision,
                $event->id,
                [
                    'version' => $version,
                    'supersedes_report_id' => $publishedReport->id,
                    'revision_reason' => $reason,
                ],
                $httpRequest,
            );

            return $revision->fresh(['carbootEvent', 'reportRequest', 'preparedByUser', 'supersedesReport']);
        });
    }

    private function nextVersion(int $eventId, string $reportType): int
    {
        $max = GeneratedReport::query()
            ->where('carboot_event_id', $eventId)
            ->where('report_type', $reportType)
            ->max('version');

        return ((int) $max) + 1;
    }
}
