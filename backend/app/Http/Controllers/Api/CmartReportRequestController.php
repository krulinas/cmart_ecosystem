<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmartReportRequestResource;
use App\Models\CarbootEvent;
use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Notifications\ReportRequestCreatedNotification;
use App\Services\ExternalAlertSimulationService;
use App\Services\ReportNotificationReadService;
use App\Services\ReportRequestTransitionService;
use App\Services\ReportWorkflowAuditor;
use App\Services\ReportWorkflowRecipientResolver;
use App\Support\ReportRequestStatus;
use App\Support\ReportType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CmartReportRequestController extends Controller
{
    public function __construct(
        private readonly ReportRequestTransitionService $transitions,
        private readonly ReportWorkflowAuditor $auditor,
        private readonly ReportWorkflowRecipientResolver $recipients,
        private readonly ExternalAlertSimulationService $externalAlerts,
        private readonly ReportNotificationReadService $notificationReads,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        // Single-venue fallback: all cmart_management accounts share the workspace request list.
        $query = ReportRequest::query()
            ->with(['carbootEvent', 'requester'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('carboot_event_id')) {
            $query->where('carboot_event_id', (int) $request->input('carboot_event_id'));
        }

        return CmartReportRequestResource::collection($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carboot_event_id' => 'required|integer|exists:carboot_events,id',
            'report_type' => 'required|string|in:'.implode(',', ReportType::all()),
            'message' => 'nullable|string|max:5000',
            'preferred_due_date' => 'nullable|date|after_or_equal:today',
        ]);

        $this->transitions->assertNoActiveDuplicate(
            (int) $validated['carboot_event_id'],
            $validated['report_type'],
        );

        $event = CarbootEvent::query()->findOrFail($validated['carboot_event_id']);

        $reportRequest = ReportRequest::create([
            'carboot_event_id' => $event->id,
            'requested_by' => $request->user()->id,
            'report_type' => $validated['report_type'],
            'message' => $validated['message'] ?? null,
            'preferred_due_date' => $validated['preferred_due_date'] ?? null,
            'status' => ReportRequestStatus::REQUESTED,
        ]);

        $reportRequest->load(['carbootEvent', 'requester']);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_REQUEST_CREATED,
            $request->user(),
            $reportRequest,
            null,
            $event->id,
            null,
            $request,
        );

        $organizers = $this->recipients->activeOrganizers();
        foreach ($organizers as $organizer) {
            $organizer->notify(new ReportRequestCreatedNotification($reportRequest));
        }

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_ORGANIZERS_NOTIFIED,
            $request->user(),
            $reportRequest,
            null,
            $event->id,
            [
                'channel' => 'in_app',
                'recipient_count' => $organizers->count(),
                'recipient_role' => 'organizer',
            ],
            $request,
        );

        $this->externalAlerts->simulateRequestCreated($reportRequest);

        return response()->json([
            'message' => '201 Created: Report request submitted to the Organizer.',
            'report_request' => new CmartReportRequestResource($reportRequest->fresh(['carbootEvent', 'requester'])),
        ], 201);
    }

    public function show(Request $request, ReportRequest $report_request): CmartReportRequestResource
    {
        $report_request->load(['carbootEvent', 'requester', 'audits.actor']);
        $this->notificationReads->markForRequest($request->user(), $report_request->id);

        return new CmartReportRequestResource($report_request);
    }

    public function cancel(Request $request, ReportRequest $report_request): JsonResponse
    {
        // Any authorized CMart Management account in the workspace may cancel while requested.
        $updated = $this->transitions->cancel($report_request, $request->user(), $request);
        $updated->load(['carbootEvent', 'requester']);

        return response()->json([
            'message' => '200 OK: Report request cancelled.',
            'report_request' => new CmartReportRequestResource($updated),
        ]);
    }
}
