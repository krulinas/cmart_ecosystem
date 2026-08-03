<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DuplicateSurveyImportException;
use App\Exceptions\SurveyReplacementRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Resources\RawSurveyUploadResource;
use App\Models\CarbootEvent;
use App\Models\RawSurveyUpload;
use App\Services\SurveyImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class OrganizerSurveyImportController extends Controller
{
    public function __construct(
        private readonly SurveyImportService $imports,
    ) {}

    public function index(CarbootEvent $event)
    {
        $this->assertSurveyTables();

        $batches = RawSurveyUpload::query()
            ->where('carboot_event_id', $event->id)
            ->with(['uploader:id,name', 'duplicateOf:id,original_filename'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return RawSurveyUploadResource::collection($batches);
    }

    public function show(CarbootEvent $event, RawSurveyUpload $batch)
    {
        $this->assertSurveyTables();
        $this->assertBatchBelongsToEvent($event, $batch);

        $batch->load(['uploader:id,name', 'duplicateOf:id,original_filename']);

        return new RawSurveyUploadResource($batch);
    }

    public function store(Request $request, CarbootEvent $event)
    {
        $this->assertSurveyTables();

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'replace_existing' => ['sometimes', 'boolean'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $validated['file'];
        $replace = (bool) ($validated['replace_existing'] ?? false);

        try {
            $batch = $this->imports->import($event, $file, $request->user(), $replace);
        } catch (DuplicateSurveyImportException $e) {
            $existing = $e->existingBatch->loadMissing('uploader:id,name');

            return response()->json([
                'message' => 'This file has already been imported for the selected event.',
                'code' => 'survey_import_duplicate',
                'existing_batch' => (new RawSurveyUploadResource($existing))->toArray($request),
            ], 409);
        } catch (SurveyReplacementRequiredException $e) {
            $active = $e->activeBatch->loadMissing('uploader:id,name');

            return response()->json([
                'message' => 'A survey dataset already exists for this event. Replace it with the newly validated file?',
                'code' => 'survey_import_replace_required',
                'active_batch' => (new RawSurveyUploadResource($active))->toArray($request),
            ], 409);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'survey_import_rejected',
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Survey import failed. Check the analytics service and try again.',
            ], 502);
        }

        $batch->load('uploader:id,name');

        return (new RawSurveyUploadResource($batch))
            ->response()
            ->setStatusCode(201);
    }

    private function assertSurveyTables(): void
    {
        if (! Schema::hasTable('raw_survey_uploads') || ! Schema::hasTable('survey_responses')) {
            abort(503, 'Survey import tables are not available. Apply pending analytics migrations first.');
        }
    }

    private function assertBatchBelongsToEvent(CarbootEvent $event, RawSurveyUpload $batch): void
    {
        if ((int) $batch->carboot_event_id !== (int) $event->id) {
            abort(404);
        }
    }
}
