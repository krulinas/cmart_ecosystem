<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrganizerReleasedDayRecoveryPresenter;
use App\Services\OrganizerReleasedDayRecoveryService;
use App\Services\VendorBookingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizerReleasedDayRecoveryController extends Controller
{
    public function __construct(
        private readonly OrganizerReleasedDayRecoveryService $recoveryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validateFilters($request);
        $paginator = $this->recoveryService->paginated($filters);

        $items = collect($paginator->items());
        $includeDetail = $request->boolean('include_audit_timeline');

        return response()->json([
            'data' => OrganizerReleasedDayRecoveryPresenter::presentMany($items, $includeDetail),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFilters(Request $request): array
    {
        $validated = $request->validate([
            'event_id' => 'nullable|integer|exists:carboot_events,id',
            'event_day_id' => 'nullable|integer|exists:event_days,id',
            'recovery_state' => 'nullable|string|in:' . implode(',', OrganizerReleasedDayRecoveryService::RECOVERY_STATES),
            'payment_state' => 'nullable|string|in:'
                . VendorBookingPresenter::PAYMENT_STATE_UNPAID . ','
                . VendorBookingPresenter::PAYMENT_STATE_PAYMENT_SUBMITTED . ','
                . VendorBookingPresenter::PAYMENT_STATE_PAID,
            'release_reason' => 'nullable|string|in:organizer_day_exception',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'search' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'include_audit_timeline' => 'nullable|boolean',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $perPage = min(max($perPage, 5), 100);

        return [
            'event_id' => $validated['event_id'] ?? null,
            'event_day_id' => $validated['event_day_id'] ?? null,
            'recovery_state' => $validated['recovery_state'] ?? null,
            'payment_state' => $validated['payment_state'] ?? null,
            'release_reason' => $validated['release_reason'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'search' => trim((string) ($validated['search'] ?? '')),
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => $perPage,
        ];
    }
}
