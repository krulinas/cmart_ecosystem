<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\ItemReservation;
use App\Services\ItemReservationCancellationService;
use App\Services\ItemReservationLifecycleService;
use App\Services\ItemReservationPresenter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorItemReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paginator = ItemReservation::query()
            ->with(['carbootEvent', 'reservingUser'])
            ->where('vendor_user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (ItemReservation $reservation) => ItemReservationPresenter::forVendor($reservation))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, ItemReservation $item_reservation): JsonResponse
    {
        $this->authorizeVendor($request, $item_reservation);

        return response()->json([
            'reservation' => ItemReservationPresenter::forVendor($item_reservation),
        ]);
    }

    public function cancel(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationCancellationService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
            'acknowledge_no_refund' => 'nullable|boolean',
        ]);

        try {
            $reservation = $service->cancel(
                $item_reservation,
                $request->user(),
                ItemReservationCancellationService::ACTOR_VENDOR,
                $validated['reason'] ?? null,
                (bool) ($validated['acknowledge_no_refund'] ?? false),
            );
        } catch (DomainConflictException $exception) {
            $status = $exception->error === 'cancellation_reason_required' ? 422 : 409;

            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], $status);
        }

        return response()->json([
            'message' => '200 OK: Item reservation cancelled successfully.',
            'reservation' => ItemReservationPresenter::forVendor($reservation),
        ]);
    }

    public function complete(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationLifecycleService $service,
    ): JsonResponse {
        $this->authorizeVendor($request, $item_reservation);

        try {
            $reservation = $service->complete($item_reservation, $request->user());
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        return response()->json([
            'message' => '200 OK: Reservation marked completed and the item is now inactive.',
            'reservation' => ItemReservationPresenter::forVendor($reservation),
        ]);
    }

    private function authorizeVendor(
        Request $request,
        ItemReservation $reservation,
    ): void {
        if ((int) $reservation->vendor_user_id !== (int) $request->user()->id) {
            throw (new ModelNotFoundException)->setModel(
                ItemReservation::class,
                [$reservation->public_reference],
            );
        }
    }
}
