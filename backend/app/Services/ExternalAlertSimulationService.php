<?php

namespace App\Services;

use App\Models\GeneratedReport;
use App\Models\ReportRequest;
use App\Models\ReportWorkflowAudit;
use App\Models\User;
use App\Support\ManagementRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prototype-only email/WhatsApp alert simulation.
 *
 * Never sends mail, WhatsApp, SMS, or any external network request.
 * Failures must not roll back the authoritative in-app workflow.
 */
class ExternalAlertSimulationService
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const STATUS_SIMULATED = 'simulated';
    public const STATUS_SKIPPED = 'skipped';
    public const DELIVERY_MODE = 'simulated';

    public function __construct(
        private readonly ReportWorkflowAuditor $auditor,
        private readonly ReportWorkflowRecipientResolver $recipients,
    ) {}

    public function simulateRequestCreated(ReportRequest $request): void
    {
        $eventName = $request->carbootEvent?->title ?? 'an event';
        $this->simulateForRecipients(
            workflowEvent: 'report_request_created',
            recipients: $this->recipients->activeOrganizers(),
            recipientRole: ManagementRole::ORGANIZER,
            reportRequest: $request,
            generatedReport: null,
            eventId: $request->carboot_event_id,
            emailSubject: 'New Post-Event Summary Request',
            emailPreview: 'CMart Management requested a Post-Event Summary for “'.$eventName.'”. Open Report Centre to review the request.',
            whatsappPreview: 'CMart Management requested a Post-Event Summary for “'.$eventName.'”. Open Report Centre to review it.',
        );
    }

    public function simulateRequestDeclined(ReportRequest $request): void
    {
        $eventName = $request->carbootEvent?->title ?? 'an event';
        $this->simulateForRecipients(
            workflowEvent: 'report_request_declined',
            recipients: $this->recipients->activeCmartManagement(),
            recipientRole: ManagementRole::CMART_MANAGEMENT,
            reportRequest: $request,
            generatedReport: null,
            eventId: $request->carboot_event_id,
            emailSubject: 'Post-Event Summary Request Declined',
            emailPreview: 'The Post-Event Summary request for “'.$eventName.'” was declined by the Organizer. Open CMart Reports to review the reason.',
            whatsappPreview: 'The Post-Event Summary request for “'.$eventName.'” was declined by the Organizer. Open CMart Reports to review the reason.',
        );
    }

    public function simulateReportPublished(GeneratedReport $report, bool $isRevision = false): void
    {
        $eventName = $report->event_title_snapshot ?: ($report->carbootEvent?->title ?? 'an event');
        $workflowEvent = $isRevision ? 'report_revision_published' : 'report_published';
        $emailSubject = $isRevision
            ? 'Revised Post-Event Summary Published'
            : 'Post-Event Summary Published';
        $emailPreview = $isRevision
            ? 'A revised Post-Event Summary for “'.$eventName.'” has been published and is available in CMart Reports.'
            : 'The Post-Event Summary for “'.$eventName.'” has been published and is available in CMart Reports.';

        $this->simulateForRecipients(
            workflowEvent: $workflowEvent,
            recipients: $this->recipients->activeCmartManagement(),
            recipientRole: ManagementRole::CMART_MANAGEMENT,
            reportRequest: $report->reportRequest,
            generatedReport: $report,
            eventId: $report->carboot_event_id,
            emailSubject: $emailSubject,
            emailPreview: $emailPreview,
            whatsappPreview: $emailPreview,
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function simulateForRecipients(
        string $workflowEvent,
        Collection $recipients,
        string $recipientRole,
        ?ReportRequest $reportRequest,
        ?GeneratedReport $generatedReport,
        ?int $eventId,
        string $emailSubject,
        string $emailPreview,
        string $whatsappPreview,
    ): void {
        foreach ($recipients as $user) {
            $this->safeRecordChannel(
                workflowEvent: $workflowEvent,
                channel: self::CHANNEL_EMAIL,
                user: $user,
                recipientRole: $recipientRole,
                contact: $user->email,
                contactPresent: filled($user->email),
                skipReason: 'Email simulation skipped — no email address configured.',
                messagePreview: $emailSubject.': '.$emailPreview,
                reportRequest: $reportRequest,
                generatedReport: $generatedReport,
                eventId: $eventId,
            );

            $this->safeRecordChannel(
                workflowEvent: $workflowEvent,
                channel: self::CHANNEL_WHATSAPP,
                user: $user,
                recipientRole: $recipientRole,
                contact: $user->phone_number,
                contactPresent: filled($user->phone_number),
                skipReason: 'WhatsApp simulation skipped — no contact number configured.',
                messagePreview: $whatsappPreview,
                reportRequest: $reportRequest,
                generatedReport: $generatedReport,
                eventId: $eventId,
            );
        }
    }

    private function safeRecordChannel(
        string $workflowEvent,
        string $channel,
        User $user,
        string $recipientRole,
        ?string $contact,
        bool $contactPresent,
        string $skipReason,
        string $messagePreview,
        ?ReportRequest $reportRequest,
        ?GeneratedReport $generatedReport,
        ?int $eventId,
    ): void {
        try {
            $status = $contactPresent ? self::STATUS_SIMULATED : self::STATUS_SKIPPED;
            $action = $contactPresent
                ? ReportWorkflowAudit::ACTION_EXTERNAL_ALERT_SIMULATED
                : ReportWorkflowAudit::ACTION_EXTERNAL_ALERT_SKIPPED;

            $this->auditor->record(
                $action,
                null,
                $reportRequest,
                $generatedReport,
                $eventId,
                [
                    'workflow_event' => $workflowEvent,
                    'channel' => $channel,
                    'delivery_mode' => self::DELIVERY_MODE,
                    'status' => $status,
                    'recipient_user_id' => $user->id,
                    'recipient_role' => $recipientRole,
                    'recipient_masked' => $this->maskContact($channel, $contact),
                    'message_preview' => $messagePreview,
                    'skip_reason' => $contactPresent ? null : $skipReason,
                    'simulated_at' => now()->toIso8601String(),
                ],
                null,
            );
        } catch (Throwable $e) {
            Log::warning('Report external alert simulation failed (non-critical).', [
                'workflow_event' => $workflowEvent,
                'channel' => $channel,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function maskContact(string $channel, ?string $contact): ?string
    {
        if (! filled($contact)) {
            return null;
        }

        if ($channel === self::CHANNEL_EMAIL) {
            $parts = explode('@', $contact, 2);
            $local = $parts[0] ?? '';
            $domain = $parts[1] ?? '';
            $prefix = mb_substr($local, 0, 2);

            return $prefix.'***@'.$domain;
        }

        $digits = preg_replace('/\D+/', '', $contact) ?? '';
        $tail = mb_substr($digits, -4);

        return '+•••'.$tail;
    }
}
