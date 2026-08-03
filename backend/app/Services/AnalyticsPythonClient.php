<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnalyticsPythonClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('services.analytics.url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function validateSurveyCsv(UploadedFile|string $fileOrPath, ?string $originalFilename = null): array
    {
        $path = $fileOrPath instanceof UploadedFile ? $fileOrPath->getRealPath() : $fileOrPath;
        $filename = $fileOrPath instanceof UploadedFile
            ? $fileOrPath->getClientOriginalName()
            : ($originalFilename ?: basename((string) $path));

        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('Survey CSV file is not readable for validation.');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders($this->headers())
                ->attach('file', file_get_contents($path), $filename)
                ->post($this->baseUrl().'/api/analytics/survey/validate');
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Analytics service is unavailable. Ensure the Python service is running on the configured analytics URL.',
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('detail') ?? 'Survey validation service returned an error.',
            );
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function aggregateSurvey(array $payload): array
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders($this->headers())
                ->acceptJson()
                ->post($this->baseUrl().'/api/analytics/survey/aggregate', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Analytics service is unavailable for survey aggregation.',
                0,
                $e,
            );
        }

        if (! $response->successful()) {
            throw new RuntimeException(
                $response->json('detail') ?? 'Survey aggregation service returned an error.',
            );
        }

        return $response->json() ?? [];
    }

    public function isReachable(): bool
    {
        try {
            $response = Http::timeout(3)->get($this->baseUrl().'/');

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $apiKey = config('services.analytics.api_key');

        return $apiKey ? ['X-Analytics-Key' => (string) $apiKey] : [];
    }
}
