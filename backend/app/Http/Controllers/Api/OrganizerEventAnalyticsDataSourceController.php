<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\RawSurveyUpload;
use App\Services\EventAnalyticsDataSourceService;
use App\Services\EventAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class OrganizerEventAnalyticsDataSourceController extends Controller
{
    public function __construct(
        private readonly EventAnalyticsDataSourceService $sources,
        private readonly EventAnalyticsService $analytics,
    ) {}

    public function updateMode(Request $request, CarbootEvent $event)
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:combined,system_only,csv_only'],
        ]);

        try {
            $result = $this->sources->setSourceMode($event, $validated['mode']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Analytics source mode updated.',
            'analytics_source_mode' => $result['analytics_source_mode'],
            'overview' => $this->analytics->overview($result['event'], true),
        ]);
    }

    /**
     * Permanently delete the event's current CSV survey dataset.
     * DELETE /organizer/events/{event}/survey-imports/current
     *
     * Requires explicit permanent-deletion confirmation. Soft-exclude style
     * payloads are rejected — this endpoint never soft-excludes.
     */
    public function destroyCurrent(Request $request, CarbootEvent $event)
    {
        $this->assertTables();

        // Reject calls that still express the former soft-exclude / "remove from analytics" intent.
        if ($this->requestsSoftExcludeBehaviour($request)) {
            return response()->json([
                'message' => 'Soft exclusion is no longer supported. Permanent deletion requires confirm_permanent_deletion=true on DELETE /survey-imports/current.',
                'code' => 'survey_soft_exclude_rejected',
            ], 422);
        }

        $validated = $request->validate([
            'confirm_permanent_deletion' => ['required', 'accepted'],
        ], [
            'confirm_permanent_deletion.required' => 'Explicit confirmation is required to permanently delete CSV survey data.',
            'confirm_permanent_deletion.accepted' => 'Explicit confirmation is required to permanently delete CSV survey data.',
        ]);

        // Defensive: validation already enforces accepted, but keep intent explicit.
        if (! filter_var($validated['confirm_permanent_deletion'], FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'message' => 'Explicit confirmation is required to permanently delete CSV survey data.',
                'code' => 'survey_permanent_delete_confirmation_required',
            ], 422);
        }

        try {
            $result = $this->sources->permanentlyDeleteCurrentCsv($event);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to delete CSV survey data for this event.',
            ], 500);
        }

        return response()->json([
            'message' => 'CSV survey data permanently deleted. Analytics mode is now System Data only.',
            'deleted' => $result['deleted'],
            'deleted_batch_count' => $result['deleted_batch_count'],
            'deleted_response_count' => $result['deleted_response_count'],
            'analytics_source_mode' => $result['analytics_source_mode'],
            'summary' => $result['summary'],
            'overview' => $this->analytics->overview($result['event'], true),
        ]);
    }

    /**
     * @deprecated Soft "remove from analytics" is gone. Permanent deletion is DELETE .../current only.
     */
    public function removeCsv(CarbootEvent $event)
    {
        return $this->gone(
            'POST remove-from-analytics has been replaced. Soft exclusion is no longer available. '
            .'Permanently delete CSV data with DELETE /api/organizer/events/{event}/survey-imports/current '
            .'and confirm_permanent_deletion=true.'
        );
    }

    /**
     * Detect payloads that still intend soft-exclude / detach-from-analytics behaviour.
     */
    private function requestsSoftExcludeBehaviour(Request $request): bool
    {
        $softFlags = [
            'soft',
            'soft_exclude',
            'exclude_only',
            'remove_from_analytics',
            'detach_only',
            'keep_raw',
            'archive',
        ];

        foreach ($softFlags as $flag) {
            if (! $request->exists($flag)) {
                continue;
            }
            $value = $request->input($flag);
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        $mode = strtolower(trim((string) $request->input('mode', '')));
        if (in_array($mode, ['soft', 'exclude', 'remove_from_analytics', 'detach'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @deprecated Soft lifecycle disconnected from organizer UX.
     */
    public function activate(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->gone('Activate is no longer available. Upload a survey CSV after deleting the previous dataset.');
    }

    /**
     * @deprecated Soft lifecycle disconnected from organizer UX.
     */
    public function exclude(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->gone('Exclude is no longer available. Use Delete CSV Data to permanently remove the dataset.');
    }

    /**
     * @deprecated Soft lifecycle disconnected from organizer UX.
     */
    public function archive(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->gone('Archive is no longer available. Use Delete CSV Data to permanently remove the dataset.');
    }

    /**
     * @deprecated Soft lifecycle disconnected from organizer UX.
     */
    public function restore(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->gone('Restore is no longer available. Upload a survey CSV after deleting the previous dataset.');
    }

    /**
     * @deprecated Soft lifecycle disconnected from organizer UX.
     */
    public function undo(CarbootEvent $event)
    {
        return $this->gone('Undo is no longer available. Use Replace CSV or Delete CSV Data instead.');
    }

    private function gone(string $message)
    {
        return response()->json([
            'message' => $message,
            'code' => 'survey_lifecycle_deprecated',
        ], 410);
    }

    private function assertTables(): void
    {
        if (! Schema::hasTable('raw_survey_uploads') || ! Schema::hasTable('survey_responses')) {
            abort(503, 'Survey import tables are not available. Apply pending analytics migrations first.');
        }
    }
}
