<?php

namespace App\Services;

use App\Models\ReportWorkflowAudit;
use App\Support\ManagementRole;
use Illuminate\Support\Collection;

/**
 * Builds role-appropriate report workflow timelines (no raw SQL / stack traces).
 */
class ReportWorkflowTimelinePresenter
{
    /**
     * @param  Collection<int, ReportWorkflowAudit>  $audits
     * @return list<array<string, mixed>>
     */
    public function forOrganizer(Collection $audits): array
    {
        return $audits
            ->map(fn (ReportWorkflowAudit $audit) => $this->present($audit, true))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ReportWorkflowAudit>  $audits
     * @return list<array<string, mixed>>
     */
    public function forCmart(Collection $audits): array
    {
        $allowed = [
            ReportWorkflowAudit::ACTION_REQUEST_CREATED,
            ReportWorkflowAudit::ACTION_ORGANIZERS_NOTIFIED,
            ReportWorkflowAudit::ACTION_EXTERNAL_ALERT_SIMULATED,
            ReportWorkflowAudit::ACTION_EXTERNAL_ALERT_SKIPPED,
            ReportWorkflowAudit::ACTION_REQUEST_ACKNOWLEDGED,
            ReportWorkflowAudit::ACTION_REQUEST_PREPARATION_STARTED,
            ReportWorkflowAudit::ACTION_REQUEST_DECLINED,
            ReportWorkflowAudit::ACTION_REQUEST_CANCELLED,
            ReportWorkflowAudit::ACTION_REQUEST_FULFILLED,
            ReportWorkflowAudit::ACTION_PUBLISHED,
            ReportWorkflowAudit::ACTION_CMART_NOTIFIED,
            ReportWorkflowAudit::ACTION_VIEWED,
            ReportWorkflowAudit::ACTION_DOWNLOADED,
        ];

        return $audits
            ->filter(fn (ReportWorkflowAudit $audit) => in_array($audit->action, $allowed, true))
            ->map(fn (ReportWorkflowAudit $audit) => $this->present($audit, false))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(ReportWorkflowAudit $audit, bool $organizerView): ?array
    {
        $meta = is_array($audit->metadata) ? $audit->metadata : [];

        if (in_array($audit->action, [
            ReportWorkflowAudit::ACTION_EXTERNAL_ALERT_SIMULATED,
            ReportWorkflowAudit::ACTION_EXTERNAL_ALERT_SKIPPED,
        ], true)) {
            return [
                'id' => $audit->id,
                'action' => $audit->action,
                'label' => $this->externalLabel($meta),
                'channel' => $meta['channel'] ?? null,
                'status' => $meta['status'] ?? null,
                'recipient_masked' => $meta['recipient_masked'] ?? null,
                'message_preview' => $meta['message_preview'] ?? null,
                'skip_reason' => $meta['skip_reason'] ?? null,
                'delivery_mode' => $meta['delivery_mode'] ?? 'simulated',
                'created_at' => optional($audit->created_at)?->toIso8601String(),
                'kind' => 'external_simulation',
            ];
        }

        return [
            'id' => $audit->id,
            'action' => $audit->action,
            'label' => $this->actionLabel($audit->action),
            'actor_name' => $organizerView ? ($audit->actor?->name) : null,
            'created_at' => optional($audit->created_at)?->toIso8601String(),
            'kind' => 'workflow',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function externalLabel(array $meta): string
    {
        $channel = ucfirst((string) ($meta['channel'] ?? 'alert'));
        $status = $meta['status'] ?? 'simulated';

        if ($status === 'skipped') {
            return $channel.' simulation skipped';
        }

        return $channel.' alert simulated';
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            ReportWorkflowAudit::ACTION_REQUEST_CREATED => 'CMart request submitted',
            ReportWorkflowAudit::ACTION_ORGANIZERS_NOTIFIED => 'Organizer in-app notifications created',
            ReportWorkflowAudit::ACTION_REQUEST_ACKNOWLEDGED => 'Request acknowledged',
            ReportWorkflowAudit::ACTION_REQUEST_PREPARATION_STARTED => 'Preparation started',
            ReportWorkflowAudit::ACTION_REQUEST_DECLINED => 'Request declined',
            ReportWorkflowAudit::ACTION_REQUEST_CANCELLED => 'Request cancelled',
            ReportWorkflowAudit::ACTION_REQUEST_FULFILLED => 'Request fulfilled',
            ReportWorkflowAudit::ACTION_DRAFT_GENERATED => 'Draft generated',
            ReportWorkflowAudit::ACTION_DRAFT_REGENERATED => 'Draft snapshot regenerated',
            ReportWorkflowAudit::ACTION_REVISION_CREATED => 'Revision draft created',
            ReportWorkflowAudit::ACTION_PUBLISHED => 'Report published',
            ReportWorkflowAudit::ACTION_CMART_NOTIFIED => 'CMart in-app notifications created',
            ReportWorkflowAudit::ACTION_VIEWED => 'Report viewed by CMart',
            ReportWorkflowAudit::ACTION_DOWNLOADED => 'Report downloaded by CMart',
            default => str_replace('_', ' ', $action),
        };
    }
}
