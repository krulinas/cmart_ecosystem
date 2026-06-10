<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(): View
    {
        return view('admin.analytics');
    }

    public function getStatusSummary(): JsonResponse
    {
        return $this->proxyAnalytics('/api/analytics/status-summary');
    }

    public function getFeedbackCloud(): JsonResponse
    {
        return $this->proxyAnalytics('/api/analytics/wordcloud/feedback');
    }

    public function getProductCloud(): JsonResponse
    {
        return $this->proxyAnalytics('/api/analytics/wordcloud/products');
    }

    private function proxyAnalytics(string $path): JsonResponse
    {
        $baseUrl = rtrim((string) config('services.analytics.url'), '/');
        $apiKey = config('services.analytics.api_key');

        try {
            $request = Http::timeout(15);

            if ($apiKey) {
                $request = $request->withHeaders(['X-Analytics-Key' => $apiKey]);
            }

            $response = $request->get("{$baseUrl}{$path}");
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Analytics service is unavailable. Please ensure the Python FastAPI server is running.',
            ], 503);
        }

        return $this->transformProxyResponse($response);
    }

    private function transformProxyResponse(Response $response): JsonResponse
    {
        if ($response->successful()) {
            return response()->json($response->json());
        }

        $status = $response->status();
        $message = $response->json('detail') ?? $response->json('message') ?? 'Analytics service returned an error.';

        if ($status >= 500 || $status === 0) {
            return response()->json(['message' => $message], 503);
        }

        return response()->json(['message' => $message], $status);
    }
}
