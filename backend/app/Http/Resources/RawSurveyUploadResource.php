<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\RawSurveyUpload */
class RawSurveyUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'carboot_event_id' => $this->carboot_event_id,
            'schema_name' => $this->schema_name,
            'schema_version' => $this->schema_version,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'checksum_short' => $this->shortenedChecksum(),
            'status' => $this->status,
            'is_active' => (bool) ($this->is_active ?? false),
            'submission_source' => $this->submission_source ?? 'csv_import',
            'duplicate_of_id' => $this->duplicate_of_id,
            'superseded_by_id' => $this->superseded_by_id,
            'superseded_at' => optional($this->superseded_at)?->toIso8601String(),
            'excluded_at' => optional($this->excluded_at)?->toIso8601String(),
            'archived_at' => optional($this->archived_at)?->toIso8601String(),
            'status_label' => $this->humanStatusLabel(),
            'total_row_count' => $this->total_row_count,
            'valid_row_count' => $this->valid_row_count,
            'invalid_row_count' => $this->invalid_row_count,
            'validation_summary' => $this->validation_summary,
            'failure_message' => $this->failure_message,
            'processing_started_at' => optional($this->processing_started_at)?->toIso8601String(),
            'processing_finished_at' => optional($this->processing_finished_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => [
                'id' => $this->uploader?->id,
                'name' => $this->uploader?->name,
            ]),
            'duplicate_of' => $this->whenLoaded('duplicateOf', fn () => $this->duplicateOf ? [
                'id' => $this->duplicateOf->id,
                'original_filename' => $this->duplicateOf->original_filename,
            ] : null),
        ];
    }
}
