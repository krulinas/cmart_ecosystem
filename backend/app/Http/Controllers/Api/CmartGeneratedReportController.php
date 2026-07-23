<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CmartGeneratedReportResource;
use App\Models\GeneratedReport;
use App\Models\ReportWorkflowAudit;
use App\Services\ReportWorkflowAuditor;
use App\Services\ReportNotificationReadService;
use App\Support\GeneratedReportStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CmartGeneratedReportController extends Controller
{
    public function __construct(
        private readonly ReportWorkflowAuditor $auditor,
        private readonly ReportNotificationReadService $notificationReads,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = GeneratedReport::query()
            ->with(['publishedByUser'])
            ->whereIn('status', GeneratedReportStatus::cmartVisible())
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($request->filled('carboot_event_id')) {
            $query->where('carboot_event_id', (int) $request->input('carboot_event_id'));
        }

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->string('report_type')->toString());
        }

        return CmartGeneratedReportResource::collection($query->paginate(25));
    }

    public function show(GeneratedReport $generated_report): CmartGeneratedReportResource|JsonResponse
    {
        if (! in_array($generated_report->status, GeneratedReportStatus::cmartVisible(), true)) {
            return response()->json([
                'message' => '404 Not Found: Published report not available.',
            ], 404);
        }

        $generated_report->load(['publishedByUser']);
        $this->notificationReads->markForReport(request()->user(), $generated_report->id);

        return new CmartGeneratedReportResource($generated_report);
    }

    public function downloadPdf(GeneratedReport $generated_report): Response|JsonResponse
    {
        if (! in_array($generated_report->status, GeneratedReportStatus::cmartVisible(), true)) {
            return response()->json([
                'message' => '404 Not Found: Published report not available.',
            ], 404);
        }

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return response()->json([
                'message' => '503 Service Unavailable: PDF generation is not available.',
            ], 503);
        }

        $filename = sprintf(
            'cmart-report-%s-v%d.pdf',
            $generated_report->report_type,
            $generated_report->version,
        );

        $pdf = Pdf::loadView('reports.post_event_summary', [
            'report' => $generated_report,
            'generatedAt' => now(),
        ])->setPaper('a4');

        app(ReportWorkflowAuditor::class)->record(
            ReportWorkflowAudit::ACTION_DOWNLOADED,
            request()->user(),
            $generated_report->reportRequest,
            $generated_report,
            $generated_report->carboot_event_id,
            ['version' => $generated_report->version, 'format' => 'pdf'],
            request(),
        );

        return $pdf->stream($filename);
    }

    public function markViewed(Request $request, GeneratedReport $generated_report): JsonResponse
    {
        if (! in_array($generated_report->status, GeneratedReportStatus::cmartVisible(), true)) {
            return response()->json([
                'message' => '404 Not Found: Published report not available.',
            ], 404);
        }

        $this->auditor->record(
            ReportWorkflowAudit::ACTION_VIEWED,
            $request->user(),
            $generated_report->reportRequest,
            $generated_report,
            $generated_report->carboot_event_id,
            ['version' => $generated_report->version],
            $request,
        );

        return response()->json([
            'message' => '200 OK: Report view recorded.',
        ]);
    }
}
