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
            ],
        ]);
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
