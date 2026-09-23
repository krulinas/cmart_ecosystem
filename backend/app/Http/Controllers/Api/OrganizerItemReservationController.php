<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\ItemReservation;
use App\Models\ItemReservationAudit;
use App\Services\ItemReservationLifecycleService;
use App\Services\ItemReservationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizerItemReservationController extends Controller
{
    public function index(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'reservation_status' => ['nullable', Rule::in(ItemReservation::STATUSES)],
            'charge_status' => ['nullable', Rule::in(ItemReservation::CHARGE_STATUSES)],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:48',
        ]);

        $paginator = ItemReservation::query()
            ->with(['vendorUser.businessProfile', 'reservingUser'])
            ->where('carboot_event_id', $carboot_event->id)
            ->when(
                $validated['reservation_status'] ?? null,
                fn ($query, $status) => $query->where('reservation_status', $status),
            )
            ->when(
                $validated['charge_status'] ?? null,
                fn ($query, $status) => $query->where('charge_status', $status),
            )
            ->latest()
            ->latest('id')
            ->paginate(min((int) ($validated['per_page'] ?? 20), 48))
            ->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (ItemReservation $reservation) => ItemReservationPresenter::forOrganizerQueue($reservation))
                ->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'event_summary' => $this->eventSummary($carboot_event),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummary(CarbootEvent $event): array
    {
        $fee = $event->item_reservation_service_fee;
        $enabled = $fee !== null;

        $statusCounts = ItemReservation::query()
            ->where('carboot_event_id', $event->id)
            ->selectRaw('reservation_status, COUNT(*) as aggregate')
            ->groupBy('reservation_status')
            ->pluck('aggregate', 'reservation_status');

        $eligibleListings = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('vendor_item_event_selections')) {
            $eligibleListings = (int) \Illuminate\Support\Facades\DB::table('vendor_item_event_selections')
                ->where('carboot_event_id', $event->id)
                ->count();
        }

        $pending = (int) ($statusCounts['pending_charge'] ?? 0);
        $confirmed = (int) ($statusCounts['confirmed'] ?? 0);
        $completed = (int) ($statusCounts['completed'] ?? 0);
        $cancelled = (int) ($statusCounts['cancelled'] ?? 0);
        $expired = (int) ($statusCounts['expired'] ?? 0);

        return [
            'enabled' => $enabled,
            'service_fee_amount' => $enabled ? (float) $fee : null,
            'service_fee_currency' => 'MYR',
            'eligible_listings_count' => $eligibleListings,
            'total' => (int) $statusCounts->sum(),
            'pending_charge' => $pending,
            'confirmed' => $confirmed,
            'completed' => $completed,
            'cancelled_expired' => $cancelled + $expired,
        ];
    }

    public function show(ItemReservation $item_reservation): JsonResponse
    {
        return response()->json([
            'reservation' => ItemReservationPresenter::forOrganizer($item_reservation),
        ]);
    }

    public function audits(ItemReservation $item_reservation): JsonResponse
    {
        return response()->json([
            'audits' => $item_reservation->audits()
                ->with('actorUser')
                ->get()
                ->map(fn (ItemReservationAudit $audit) => ItemReservationPresenter::auditEntry($audit))
                ->values(),
        ]);
    }

    public function confirmCharge(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationLifecycleService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'note' => 'required|string|max:500',
        ]);

        return $this->mutate(fn () => $service->confirmCharge(
            $item_reservation,
            $request->user(),
            $validated['note'],
        ), '200 OK: Manual service-fee payment recorded and reservation confirmed.');
    }

    public function waiveCharge(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationLifecycleService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return $this->mutate(fn () => $service->waiveCharge(
            $item_reservation,
            $request->user(),
            $validated['reason'],
        ), '200 OK: Service fee waived and reservation confirmed.');
    }

    public function cancel(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationLifecycleService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'acknowledge_no_refund' => 'nullable|boolean',
        ]);

        return $this->mutate(fn () => $service->organizerCancel(
            $item_reservation,
            $request->user(),
            $validated['reason'],
            (bool) ($validated['acknowledge_no_refund'] ?? false),
        ), '200 OK: Reservation cancelled by the Organizer.');
    }

    public function expire(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationLifecycleService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return $this->mutate(fn () => $service->expire(
            $item_reservation,
            $request->user(),
            $validated['reason'],
        ), '200 OK: Reservation manually expired by the Organizer.');
    }

    public function complete(
        Request $request,
        ItemReservation $item_reservation,
        ItemReservationLifecycleService $service,
    ): JsonResponse {
        return $this->mutate(
            fn () => $service->complete($item_reservation, $request->user()),
            '200 OK: Reservation marked completed and the item is now inactive.',
        );
    }

    private function mutate(callable $operation, string $message): JsonResponse
    {
        try {
            $reservation = $operation();
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        return response()->json([
            'message' => $message,
            'reservation' => ItemReservationPresenter::forOrganizer($reservation),
        ]);
    }
}
