<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RawSurveyUploadResource;
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

    public function activate(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->mutateBatch($event, $batch, fn () => $this->sources->activateBatch($event, $batch), 'Batch activated for analytics.');
    }

    public function exclude(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->mutateBatch($event, $batch, fn () => $this->sources->excludeBatch($event, $batch), 'Batch excluded from analytics.');
    }

    public function removeCsv(CarbootEvent $event)
    {
        $this->assertTables();

        try {
            $result = $this->sources->removeCsvFromAnalytics($event);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to remove CSV from analytics.'], 500);
        }

        return response()->json([
            'message' => 'CSV removed from analytics. Analytics mode is now System only.',
            'analytics_source_mode' => $result['analytics_source_mode'],
            'batch' => $result['batch']
                ? new RawSurveyUploadResource($result['batch']->load('uploader:id,name'))
                : null,
            'overview' => $this->analytics->overview($result['event'], true),
        ]);
    }

    public function archive(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->mutateBatch($event, $batch, fn () => $this->sources->archiveBatch($event, $batch), 'Batch archived.');
    }

    public function restore(CarbootEvent $event, RawSurveyUpload $batch)
    {
        return $this->mutateBatch($event, $batch, fn () => $this->sources->restoreBatch($event, $batch), 'Batch restored into analytics.');
    }

    public function undo(CarbootEvent $event)
    {
        $this->assertTables();

        try {
            $batch = $this->sources->undoImport($event);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to undo the survey import.'], 500);
        }

        return response()->json([
            'message' => 'Previous survey import restored.',
            'batch' => new RawSurveyUploadResource($batch->load('uploader:id,name')),
            'overview' => $this->analytics->overview($event->fresh(), true),
        ]);
    }

    /**
     * @param  callable(): RawSurveyUpload  $action
     */
    private function mutateBatch(CarbootEvent $event, RawSurveyUpload $batch, callable $action, string $message)
    {
        $this->assertTables();
        $this->assertBatchBelongs($event, $batch);

        try {
            $updated = $action();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Unable to update the survey import batch.'], 500);
        }

        return response()->json([
            'message' => $message,
            'batch' => new RawSurveyUploadResource($updated->load(['uploader:id,name', 'duplicateOf:id,original_filename'])),
            'overview' => $this->analytics->overview($event->fresh(), true),
        ]);
    }

    private function assertTables(): void
    {
        if (! Schema::hasTable('raw_survey_uploads') || ! Schema::hasTable('survey_responses')) {
            abort(503, 'Survey import tables are not available. Apply pending analytics migrations first.');
        }
    }

    private function assertBatchBelongs(CarbootEvent $event, RawSurveyUpload $batch): void
    {
        if ((int) $batch->carboot_event_id !== (int) $event->id) {
            abort(404);
        }
    }
}
