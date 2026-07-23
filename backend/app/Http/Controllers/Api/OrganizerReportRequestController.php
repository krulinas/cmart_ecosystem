<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizerReportRequestResource;
use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Notifications\ReportRequestAcknowledgedNotification;
use App\Notifications\ReportRequestDeclinedNotification;
use App\Services\ExternalAlertSimulationService;
use App\Services\ReportNotificationReadService;
use App\Services\ReportRequestTransitionService;
use App\Services\ReportWorkflowAuditor;
use App\Services\ReportWorkflowRecipientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Notifications\Notification;

class OrganizerReportRequestController extends Controller
{
    public function __construct(
        private readonly ReportRequestTransitionService $transitions,
        private readonly ReportWorkflowRecipientResolver $recipients,
        private readonly ExternalAlertSimulationService $externalAlerts,
        private readonly ReportWorkflowAuditor $auditor,
        private readonly ReportNotificationReadService $notificationReads,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ReportRequest::query()
            ->with(['carbootEvent', 'requester', 'acknowledgedByUser'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('carboot_event_id')) {
            $query->where('carboot_event_id', (int) $request->input('carboot_event_id'));
        }

        return OrganizerReportRequestResource::collection($query->paginate(25));
    }

    public function show(Request $request, ReportRequest $report_request): OrganizerReportRequestResource
    {
        $report_request->load(['carbootEvent', 'requester', 'acknowledgedByUser', 'audits.actor']);
        $this->notificationReads->markForRequest($request->user(), $report_request->id);

        return new OrganizerReportRequestResource($report_request);
    }

    public function acknowledge(Request $request, ReportRequest $report_request): JsonResponse
    {
        $updated = $this->transitions->acknowledge($report_request, $request->user(), $request);
        $updated->load(['carbootEvent', 'requester', 'acknowledgedByUser']);

        $this->notifyAllCmart(new ReportRequestAcknowledgedNotification($updated), $updated);

        return response()->json([
            'message' => '200 OK: Report request acknowledged.',
            'report_request' => new OrganizerReportRequestResource($updated),
        ]);
    }

    public function startPreparation(Request $request, ReportRequest $report_request): JsonResponse
    {
        $updated = $this->transitions->startPreparation($report_request, $request->user(), $request);
        $updated->load(['carbootEvent', 'requester', 'acknowledgedByUser']);

        return response()->json([
            'message' => '200 OK: Report preparation started.',
            'report_request' => new OrganizerReportRequestResource($updated),
        ]);
    }

    public function decline(Request $request, ReportRequest $report_request): JsonResponse
    {
        $validated = $request->validate([
            'decline_reason' => 'required|string|min:3|max:5000',
            'response_message' => 'nullable|string|max:5000',
        ]);

        $updated = $this->transitions->decline(
            $report_request,
            $request->user(),
            $validated['decline_reason'],
            $validated['response_message'] ?? null,
            $request,
        );
        $updated->load(['carbootEvent', 'requester', 'acknowledgedByUser']);

        $this->notifyAllCmart(new ReportRequestDeclinedNotification($updated), $updated);
        $this->externalAlerts->simulateRequestDeclined($updated);

        return response()->json([
            'message' => '200 OK: Report request declined.',
            'report_request' => new OrganizerReportRequestResource($updated),
        ]);
    }

    private function notifyAllCmart(Notification $notification, ReportRequest $reportRequest): void
    {
        $users = $this->recipients->activeCmartManagement();
        foreach ($users as $user) {
            $user->notify($notification);
        }

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_CMART_NOTIFIED,
            null,
            $reportRequest,
            null,
            $reportRequest->carboot_event_id,
            [
                'channel' => 'in_app',
                'recipient_count' => $users->count(),
                'recipient_role' => 'cmart_management',
            ],
            null,
        );
    }
}
