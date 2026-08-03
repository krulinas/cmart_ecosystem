<?php

namespace App\Exceptions;

use App\Models\RawSurveyUpload;
use RuntimeException;

class SurveyReplacementRequiredException extends RuntimeException
{
    public function __construct(
        public readonly RawSurveyUpload $activeBatch,
        string $message = 'A survey dataset already exists for this event. Confirm replacement to continue.',
    ) {
        parent::__construct($message);
    }
}
