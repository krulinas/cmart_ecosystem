<?php

namespace App\Services;

use App\Models\GeneratedReport;
use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Append-only writer for report request / publication workflow audits.
 */
class ReportWorkflowAuditor
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $action,
        ?User $actor = null,
        ?ReportRequest $reportRequest = null,
        ?GeneratedReport $report = null,
        ?int $eventId = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): ReportWorkflowAudit {
        $resolvedEventId = $eventId
            ?? $reportRequest?->carboot_event_id
            ?? $report?->carboot_event_id;

        return ReportWorkflowAudit::create([
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'report_request_id' => $reportRequest?->id,
            'generated_report_id' => $report?->id,
            'carboot_event_id' => $resolvedEventId,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
        ]);
    }
}
