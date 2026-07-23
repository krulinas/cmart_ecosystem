<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizerGeneratedReportResource;
use App\Models\CarbootEvent;
use App\Models\GeneratedReport;
use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Services\ReportDraftService;
use App\Services\ReportPublicationService;
use App\Services\ReportWorkflowAuditor;
use App\Support\GeneratedReportStatus;
use App\Support\ReportType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;
use Throwable;

class OrganizerGeneratedReportController extends Controller
{
    public function __construct(
        private readonly ReportDraftService $drafts,
        private readonly ReportPublicationService $publication,
        private readonly ReportWorkflowAuditor $auditor,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = GeneratedReport::query()
            ->with(['carbootEvent', 'reportRequest', 'preparedByUser', 'publishedByUser'])
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('carboot_event_id')) {
            $query->where('carboot_event_id', (int) $request->input('carboot_event_id'));
        }

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->string('report_type')->toString());
        }

        return OrganizerGeneratedReportResource::collection($query->paginate(25));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'carboot_event_id' => 'required|integer|exists:carboot_events,id',
            'report_request_id' => 'nullable|integer|exists:report_requests,id',
            'report_type' => 'nullable|string|in:'.implode(',', ReportType::all()),
        ]);

        $event = CarbootEvent::query()->findOrFail($validated['carboot_event_id']);
        $reportRequest = null;

        if (! empty($validated['report_request_id'])) {
            $reportRequest = ReportRequest::query()->findOrFail($validated['report_request_id']);
        }

        try {
            $report = $this->drafts->generate($event, $request->user(), $reportRequest, $request);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to generate the draft report. Please try again or contact support.',
            ], 500);
        }

        return response()->json([
            'message' => '201 Created: Draft report generated.',
            'generated_report' => new OrganizerGeneratedReportResource($report),
        ], 201);
    }

    public function show(GeneratedReport $generated_report): OrganizerGeneratedReportResource
    {
        $generated_report->load([
            'carbootEvent',
            'reportRequest',
            'preparedByUser',
            'publishedByUser',
            'supersedesReport',
        ]);

        return new OrganizerGeneratedReportResource($generated_report);
    }

    public function updateNarratives(Request $request, GeneratedReport $generated_report): JsonResponse
    {
        if ($generated_report->status !== GeneratedReportStatus::DRAFT) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only draft reports can have narratives updated.',
            ], 422);
        }

        $validated = $request->validate([
            'organizer_observations' => 'nullable|string|max:20000',
            'organizer_recommendations' => 'nullable|string|max:20000',
        ]);

        $generated_report->update($validated);

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_DRAFT_NARRATIVES_UPDATED,
            $request->user(),
            $generated_report->reportRequest,
            $generated_report->fresh(),
            $generated_report->carboot_event_id,
            null,
            $request,
        );

        $generated_report->load(['carbootEvent', 'reportRequest', 'preparedByUser', 'publishedByUser']);

        return response()->json([
            'message' => '200 OK: Narratives updated.',
            'generated_report' => new OrganizerGeneratedReportResource($generated_report),
        ]);
    }

    public function regenerate(Request $request, GeneratedReport $generated_report): JsonResponse
    {
        try {
            $report = $this->drafts->regenerate($generated_report, $request->user(), $request);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to regenerate the draft snapshot. Please try again or contact support.',
            ], 500);
        }

        return response()->json([
            'message' => '200 OK: Draft snapshot regenerated.',
            'generated_report' => new OrganizerGeneratedReportResource($report),
        ]);
    }

    public function publish(Request $request, GeneratedReport $generated_report): JsonResponse
    {
        $report = $this->publication->publish($generated_report, $request->user(), $request);

        return response()->json([
            'message' => '200 OK: Report published.',
            'generated_report' => new OrganizerGeneratedReportResource($report),
        ]);
    }

    public function destroy(Request $request, GeneratedReport $generated_report): JsonResponse
    {
        if ($generated_report->status !== GeneratedReportStatus::DRAFT) {
            return response()->json([
                'message' => '422 Unprocessable Entity: Only draft reports can be deleted.',
            ], 422);
        }

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_DRAFT_DESTROYED,
            $request->user(),
            $generated_report->reportRequest,
            $generated_report,
            $generated_report->carboot_event_id,
            ['version' => $generated_report->version],
            $request,
        );

        $generated_report->delete();

        return response()->json([
            'message' => '200 OK: Draft report deleted.',
        ]);
    }

    public function revise(Request $request, GeneratedReport $generated_report): JsonResponse
    {
        $validated = $request->validate([
            'revision_reason' => 'required|string|min:3|max:2000',
        ]);

        $revision = $this->drafts->createRevision(
            $generated_report,
            $request->user(),
            $validated['revision_reason'],
            $request,
        );

        return response()->json([
            'message' => '201 Created: Revision draft created.',
            'generated_report' => new OrganizerGeneratedReportResource($revision),
        ], 201);
    }

    public function downloadPdf(GeneratedReport $generated_report): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json([
                'message' => '503 Service Unavailable: PDF generation is not available.',
            ], 503);
        }

        $filename = sprintf(
            'organizer-report-%s-v%d.pdf',
            $generated_report->report_type,
            $generated_report->version,
        );

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.post_event_summary', [
            'report' => $generated_report,
            'generatedAt' => now(),
        ])->setPaper('a4');

        return $pdf->stream($filename);
    }
}
