<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\ItemReservation;
use App\Services\ItemReservationCancellationService;
use App\Services\ItemReservationPresenter;
use App\Services\ItemReservationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemReservationController extends Controller
{
    public function store(Request $request, ItemReservationService $service): JsonResponse
    {
        $validated = $request->validate([
            'vendor_item_id' => 'required|integer',
        ]);

        try {
            $reservation = $service->create(
                $request->user(),
                (int) $validated['vendor_item_id'],
            );
        } catch (DomainConflictException $exception) {
            $status = $exception->error === 'item_reservation_fee_not_configured' ? 422 : 409;

            return $this->conflictResponse($exception, $status);
        }

        return response()->json([
            'message' => '201 Created: Item reservation created successfully.',
            'reservation' => ItemReservationPresenter::forReservingUser($reservation),
        ], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $paginator = ItemReservation::query()
            ->with(['carbootEvent', 'vendorUser.businessProfile'])
            ->where('reserving_user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (ItemReservation $reservation) => ItemReservationPresenter::forReservingUser($reservation))
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
        $this->authorizeReservingUser($request, $item_reservation);

        return response()->json([
            'reservation' => ItemReservationPresenter::forReservingUser($item_reservation),
        ]);
    }

    public function cancel(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationCancellationService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $reservation = $service->cancel(
                $item_reservation,
                $request->user(),
                ItemReservationCancellationService::ACTOR_RESERVING_USER,
                $validated['reason'] ?? null,
            );
        } catch (DomainConflictException $exception) {
            return $this->conflictResponse($exception, 409);
        }

        return response()->json([
            'message' => '200 OK: Item reservation cancelled successfully.',
            'reservation' => ItemReservationPresenter::forReservingUser($reservation),
        ]);
    }

    private function authorizeReservingUser(
        Request $request,
        ItemReservation $reservation,
    ): void {
        if ((int) $reservation->reserving_user_id !== (int) $request->user()->id) {
            throw (new ModelNotFoundException)->setModel(
                ItemReservation::class,
                [$reservation->public_reference],
            );
        }
    }

    private function conflictResponse(
        DomainConflictException $exception,
        int $status,
    ): JsonResponse {
        return response()->json([
            'message' => $exception->getMessage(),
            'error' => $exception->error,
        ], $status);
    }
}
