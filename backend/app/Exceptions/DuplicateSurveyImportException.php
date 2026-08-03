<?php

namespace App\Exceptions;

use App\Models\RawSurveyUpload;
use RuntimeException;

class DuplicateSurveyImportException extends RuntimeException
{
    public function __construct(
        public readonly RawSurveyUpload $existingBatch,
        string $message = 'This file has already been imported for the selected event.',
    ) {
        parent::__construct($message);
    }
}
