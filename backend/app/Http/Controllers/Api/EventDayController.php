<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\DomainConflictException;
use App\Models\CarbootEvent;
use App\Models\EventDay;
use App\Services\EventDayGenerator;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Phase 2A.5 — Organizer-defined operational event days.
 */
class EventDayController extends Controller
{
    public function __construct(
        private readonly EventDayGenerator $eventDayGenerator,
    ) {
    }

    public function index(CarbootEvent $carboot_event): JsonResponse
    {
        $days = EventDay::query()
            ->forEvent($carboot_event->id)
            ->ordered()
            ->get()
            ->map(fn (EventDay $day) => $this->present($day))
            ->values();

        return response()->json([
            'event_id' => $carboot_event->id,
            'event_title' => $carboot_event->title,
            'day_generation_mode' => $carboot_event->day_generation_mode,
            'days' => $days,
            'meta' => [
                'total' => $days->count(),
                'active' => $days->where('operational_status', EventDay::STATUS_ACTIVE)->count(),
            ],
        ]);
    }

    public function generate(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        $validated = $request->validate([
            'day_generation_mode' => [
                'sometimes',
                'string',
                Rule::in(EventDayGenerator::MODES),
            ],
            'replace_existing' => 'sometimes|boolean',
        ]);

        if (isset($validated['day_generation_mode'])
            && $validated['day_generation_mode'] !== $carboot_event->day_generation_mode
        ) {
            if ($this->eventDayGenerator->eventHasAllocationHistory($carboot_event)) {
                return response()->json([
                    'message' => '409 Conflict: This event already has vendor booking allocations. Its operating dates cannot be changed because existing bookings depend on those dates.',
                    'error' => EventDayGenerator::ERROR_OPERATING_DATES_LOCKED,
                ], 409);
            }

            $carboot_event->update([
                'day_generation_mode' => $validated['day_generation_mode'],
            ]);
            $carboot_event->refresh();
        }

        try {
            $result = $this->eventDayGenerator->generate(
                $carboot_event,
                (bool) ($validated['replace_existing'] ?? false),
            );
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => '409 Conflict: ' . $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => '422 Unprocessable Entity: ' . $exception->getMessage(),
            ], 422);
        }

        $days = collect($result['created'])
            ->map(fn (EventDay $day) => $this->present($day))
            ->values();

        return response()->json([
            'message' => '201 Created: Operational event days generated successfully.',
            'event_id' => $carboot_event->id,
            'day_generation_mode' => $result['mode'],
            'replaced' => $result['replaced'],
            'created_count' => $days->count(),
            'days' => $days,
        ], 201);
    }

    public function store(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        try {
            $validated = $this->validateDay($request, false, $carboot_event);
        } catch (InvalidArgumentException $exception) {
            return $this->rangeValidationResponse($exception);
        }

        try {
            $day = EventDay::create([
                ...$validated,
                'carboot_event_id' => $carboot_event->id,
            ]);
        } catch (QueryException $exception) {
            return $this->uniqueConstraintResponse($exception);
        }

        return response()->json([
            'message' => '201 Created: Event day created successfully.',
            'day' => $this->present($day),
        ], 201);
    }

    public function show(EventDay $event_day): JsonResponse
    {
        $event_day->load('carbootEvent:id,title,day_generation_mode');

        return response()->json([
            'day' => $this->present($event_day),
        ]);
    }

    public function update(Request $request, EventDay $event_day): JsonResponse
    {
        if ($event_day->hasAllocationHistory()) {
            $blocked = $this->structuralFieldsAttempted($request, $event_day);
            if ($blocked !== []) {
                return response()->json([
                    'message' => '409 Conflict: Event day operational identity cannot change after allocation history exists.',
                    'error' => 'event_day_history_structural_lock',
                    'blocked_fields' => $blocked,
                ], 409);
            }
        }

        $event_day->loadMissing('carbootEvent');

        try {
            $validated = $this->validateDay($request, true, $event_day->carbootEvent, $event_day);
        } catch (InvalidArgumentException $exception) {
            return $this->rangeValidationResponse($exception);
        }

        try {
            $event_day->update($validated);
        } catch (QueryException $exception) {
            return $this->uniqueConstraintResponse($exception);
        }

        return response()->json([
            'message' => '200 OK: Event day updated successfully.',
            'day' => $this->present($event_day->fresh()),
        ]);
    }

    public function destroy(EventDay $event_day): JsonResponse
    {
        if ($event_day->hasAllocationHistory()) {
            return response()->json([
                'message' => '409 Conflict: Event day has allocation history and cannot be deleted.',
                'error' => 'event_day_has_allocation_history',
            ], 409);
        }

        $event_day->delete();

        return response()->json([
            'message' => '200 OK: Event day deleted successfully.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function structuralFieldsAttempted(Request $request, EventDay $day): array
    {
        $blocked = [];
        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');

        if ($request->exists('operational_date')) {
            $incoming = Carbon::parse($request->input('operational_date'), $tz)->toDateString();
            $current = optional($day->operational_date)?->toDateString();
            if ($incoming !== $current) {
                $blocked[] = 'operational_date';
            }
        }

        if ($request->exists('starts_at')) {
            $incoming = Carbon::parse($request->input('starts_at'), $tz)->timezone($tz)->format('Y-m-d H:i:s');
            $current = optional($day->starts_at)?->format('Y-m-d H:i:s');
            if ($incoming !== $current) {
                $blocked[] = 'starts_at';
            }
        }

        if ($request->exists('ends_at')) {
            $incoming = Carbon::parse($request->input('ends_at'), $tz)->timezone($tz)->format('Y-m-d H:i:s');
            $current = optional($day->ends_at)?->format('Y-m-d H:i:s');
            if ($incoming !== $current) {
                $blocked[] = 'ends_at';
            }
        }

        return $blocked;
    }

    private function validateDay(
        Request $request,
        bool $partial = false,
        ?CarbootEvent $event = null,
        ?EventDay $existingDay = null,
    ): array {
        $required = $partial ? 'sometimes|' : '';

        $validated = $request->validate([
            'operational_date' => [
                $required . 'required',
                'date',
            ],
            'starts_at' => [
                $required . 'required',
                'date',
            ],
            'ends_at' => [
                $required . 'required',
                'date',
                'after:starts_at',
            ],
            'operational_status' => [
                $partial ? 'sometimes' : 'nullable',
                'string',
                Rule::in(EventDay::OPERATIONAL_STATUSES),
            ],
            'display_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');

        if (isset($validated['operational_date'])) {
            $validated['operational_date'] = Carbon::parse($validated['operational_date'], $tz)
                ->toDateString();
        }

        if (isset($validated['starts_at'])) {
            $validated['starts_at'] = Carbon::parse($validated['starts_at'], $tz)
                ->timezone($tz)
                ->format('Y-m-d H:i:s');
        }

        if (isset($validated['ends_at'])) {
            $validated['ends_at'] = Carbon::parse($validated['ends_at'], $tz)
                ->timezone($tz)
                ->format('Y-m-d H:i:s');
        }

        if (! isset($validated['operational_status']) && ! $partial) {
            $validated['operational_status'] = EventDay::STATUS_ACTIVE;
        }

        if (! isset($validated['display_order']) && ! $partial) {
            $validated['display_order'] = 0;
        }

        if ($event) {
            $operationalDate = $validated['operational_date']
                ?? optional($existingDay?->operational_date)?->toDateString();
            $startsAt = $validated['starts_at']
                ?? optional($existingDay?->starts_at)?->format('Y-m-d H:i:s');
            $endsAt = $validated['ends_at']
                ?? optional($existingDay?->ends_at)?->format('Y-m-d H:i:s');

            if ($operationalDate && $startsAt && $endsAt) {
                $this->eventDayGenerator->assertDayFitsEvent(
                    $event,
                    $operationalDate,
                    $startsAt,
                    $endsAt,
                );
            }
        }

        return $validated;
    }

    private function rangeValidationResponse(InvalidArgumentException $exception): JsonResponse
    {
        return response()->json([
            'message' => '422 Unprocessable Entity: ' . $exception->getMessage(),
            'error' => EventDayGenerator::ERROR_DAY_OUTSIDE_EVENT_RANGE,
        ], 422);
    }

    private function present(EventDay $day): array
    {
        return [
            'id' => $day->id,
            'carboot_event_id' => $day->carboot_event_id,
            'event_title' => $day->relationLoaded('carbootEvent')
                ? $day->carbootEvent?->title
                : null,
            'operational_date' => optional($day->operational_date)?->toDateString(),
            'starts_at' => optional($day->starts_at)?->toIso8601String(),
            'ends_at' => optional($day->ends_at)?->toIso8601String(),
            'operational_status' => $day->operational_status,
            'display_order' => $day->display_order,
            'created_at' => optional($day->created_at)?->toIso8601String(),
            'updated_at' => optional($day->updated_at)?->toIso8601String(),
        ];
    }

    private function uniqueConstraintResponse(QueryException $exception): JsonResponse
    {
        if (str_contains($exception->getMessage(), 'event_days_event_date_unique')) {
            return response()->json([
                'message' => '422 Unprocessable Entity: An operational day with this date already exists for this event.',
                'error' => 'duplicate_operational_date',
            ], 422);
        }

        throw $exception;
    }
}
