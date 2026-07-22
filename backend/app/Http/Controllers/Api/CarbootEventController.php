<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Services\EventDayGenerator;
use App\Services\EventPresenter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class CarbootEventController extends Controller
{
    private const STATUSES = ['Available', 'Almost Full', 'Closed'];

    private const MAX_IMAGES = 5;

    private const SCHEDULE_FIELDS = ['starts_at', 'ends_at', 'day_generation_mode'];

    public function __construct(
        private readonly EventDayGenerator $eventDayGenerator,
    ) {
    }

    public function publicIndex()
    {
        $events = CarbootEvent::query()
            ->with('images')
            ->where('status', '!=', 'Closed')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CarbootEvent $event) => EventPresenter::fromModel($event))
            ->values();

        return response()->json($events);
    }

    public function publicShow(CarbootEvent $carboot_event)
    {
        $carboot_event->load('images');

        if ($carboot_event->status === 'Closed' || $carboot_event->ends_at < now()) {
            return response()->json([
                'message' => 'This event is no longer available for booking. Please choose another event.',
                'available' => false,
            ], 404);
        }

        return response()->json(array_merge(
            EventPresenter::fromModel($carboot_event),
            ['available' => true],
        ));
    }

    public function index()
    {
        $events = CarbootEvent::query()
            ->with('images')
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (CarbootEvent $event) => EventPresenter::fromModel($event, true))
            ->values();

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvent($request);
        $saveAsDefault = $request->boolean('save_as_default_site_price');
        unset($validated['save_as_default_site_price']);

        try {
            $event = DB::transaction(function () use ($request, $validated, $saveAsDefault) {
                $event = CarbootEvent::create($validated);
                $this->eventDayGenerator->materializeForEvent($event);

                if ($saveAsDefault) {
                    $this->persistOrganizerDefaultSitePrice($request->user(), $event->site_price);
                }

                return $event;
            });
        } catch (DomainConflictException $exception) {
            return $this->conflictResponse($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->unprocessableResponse($exception);
        }

        $this->attachUploadedImages($request, $event);

        return response()->json([
            'message' => '201 Created: Carboot event created successfully.',
            'event' => EventPresenter::fromModel($event->fresh('images'), true),
        ], 201);
    }

    public function show(CarbootEvent $carboot_event)
    {
        $carboot_event->load('images');

        return response()->json(EventPresenter::fromModel($carboot_event, true));
    }

    public function update(Request $request, CarbootEvent $carboot_event)
    {
        $validated = $this->validateEvent($request, true);
        $saveAsDefault = $request->boolean('save_as_default_site_price');
        unset($validated['save_as_default_site_price']);
        $scheduleChanging = $this->scheduleFieldsChanging($carboot_event, $validated);

        if ($scheduleChanging && $this->eventDayGenerator->eventHasAllocationHistory($carboot_event)) {
            return $this->conflictResponse(new DomainConflictException(
                'This event already has vendor booking allocations. Its operating dates cannot be changed because existing bookings depend on those dates.',
                EventDayGenerator::ERROR_OPERATING_DATES_LOCKED,
            ));
        }

        if ($request->boolean('remove_poster')) {
            $this->removeAllImages($carboot_event);
            $validated['image_path'] = null;
        }

        try {
            DB::transaction(function () use ($request, $carboot_event, $validated, $scheduleChanging, $saveAsDefault) {
                $carboot_event->update($validated);

                if ($scheduleChanging) {
                    $this->eventDayGenerator->materializeForEvent($carboot_event->fresh());
                }

                if ($saveAsDefault && array_key_exists('site_price', $validated)) {
                    $this->persistOrganizerDefaultSitePrice($request->user(), $validated['site_price']);
                } elseif ($saveAsDefault) {
                    $this->persistOrganizerDefaultSitePrice(
                        $request->user(),
                        $carboot_event->fresh()->site_price,
                    );
                }
            });
        } catch (DomainConflictException $exception) {
            return $this->conflictResponse($exception);
        } catch (InvalidArgumentException $exception) {
            return $this->unprocessableResponse($exception);
        }

        if ($request->filled('remove_image_ids')) {
            $this->removeImagesById($carboot_event, (array) $request->input('remove_image_ids'));
        }

        $this->attachUploadedImages($request, $carboot_event);

        return response()->json([
            'message' => '200 OK: Carboot event updated successfully.',
            'event' => EventPresenter::fromModel($carboot_event->fresh('images'), true),
        ]);
    }

    public function destroy(CarbootEvent $carboot_event)
    {
        $carboot_event->delete();

        return response()->json([
            'message' => '200 OK: Carboot event deleted successfully.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function scheduleFieldsChanging(CarbootEvent $event, array $validated): bool
    {
        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');

        foreach (self::SCHEDULE_FIELDS as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            if ($field === 'day_generation_mode') {
                $incoming = $validated[$field] ?: CarbootEvent::DAY_MODE_CALENDAR;
                $current = $event->day_generation_mode ?: CarbootEvent::DAY_MODE_CALENDAR;
                if ($incoming !== $current) {
                    return true;
                }

                continue;
            }

            $incoming = Carbon::parse((string) $validated[$field], $tz)
                ->timezone($tz)
                ->format('Y-m-d H:i:s');
            $current = optional($event->{$field})?->copy()->timezone($tz)->format('Y-m-d H:i:s');

            if ($incoming !== $current) {
                return true;
            }
        }

        return false;
    }

    private function conflictResponse(DomainConflictException $exception): JsonResponse
    {
        return response()->json([
            'message' => '409 Conflict: '.$exception->getMessage(),
            'error' => $exception->error,
        ], 409);
    }

    private function unprocessableResponse(InvalidArgumentException|Throwable $exception): JsonResponse
    {
        return response()->json([
            'message' => '422 Unprocessable Entity: '.$exception->getMessage(),
        ], 422);
    }

    private function validateEvent(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => ($partial ? 'sometimes|' : '').'required|string|max:255',
            'starts_at' => ($partial ? 'sometimes|' : '').'required|date',
            'ends_at' => ($partial ? 'sometimes|' : '').'required|date|after:starts_at',
            'status' => ['sometimes', 'required', Rule::in(self::STATUSES)],
            'description' => 'nullable|string|max:5000',
            'max_slots' => 'nullable|integer|min:1',
            'item_reservation_service_fee' => [
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:99999999.99',
            ],
            'site_price' => array_values(array_filter([
                $partial ? 'sometimes' : null,
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
                'max:99999999.99',
            ])),
            'save_as_default_site_price' => 'sometimes|boolean',
            'day_generation_mode' => [
                'sometimes',
                'required',
                'string',
                Rule::in(CarbootEvent::DAY_GENERATION_MODES),
            ],
            'poster' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:'.self::MAX_IMAGES,
            'images.*' => 'file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_poster' => 'nullable|boolean',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer',
        ];

        if (! $partial) {
            $rules['status'] = ['required', Rule::in(self::STATUSES)];
        }

        $validated = $request->validate($rules);

        unset(
            $validated['poster'],
            $validated['images'],
            $validated['remove_poster'],
            $validated['remove_image_ids'],
        );

        if (array_key_exists('max_slots', $validated) && $validated['max_slots'] === '') {
            $validated['max_slots'] = null;
        }

        if (isset($validated['site_price'])) {
            $validated['site_price'] = number_format((float) $validated['site_price'], 2, '.', '');
        }

        if (isset($validated['starts_at'])) {
            $validated['starts_at'] = $this->normalizeEventDatetime($validated['starts_at']);
        }

        if (isset($validated['ends_at'])) {
            $validated['ends_at'] = $this->normalizeEventDatetime($validated['ends_at']);
        }

        return $validated;
    }

    private function persistOrganizerDefaultSitePrice($user, mixed $sitePrice): void
    {
        if (! $user) {
            return;
        }

        $normalized = number_format((float) $sitePrice, 2, '.', '');
        if ((float) $normalized <= 0) {
            return;
        }

        $user->forceFill(['default_site_price' => $normalized])->save();
    }

    /**
     * Treat incoming datetimes as Malaysia wall-clock times (matches datetime-local inputs).
     */
    private function normalizeEventDatetime(string $value): string
    {
        return Carbon::parse($value, config('app.timezone'))
            ->timezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    private function collectUploadFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('poster')) {
            $files[] = $request->file('poster');
        }

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if ($file) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function attachUploadedImages(Request $request, CarbootEvent $event): void
    {
        $files = $this->collectUploadFiles($request);
        if ($files === []) {
            return;
        }

        $existingCount = $event->images()->count();
        $availableSlots = self::MAX_IMAGES - $existingCount;

        if ($availableSlots <= 0) {
            return;
        }

        $hasPrimary = $event->images()->where('is_primary', true)->exists();

        foreach (array_slice($files, 0, $availableSlots) as $offset => $file) {
            $path = $file->store('events', 'public');

            $event->images()->create([
                'image_path' => $path,
                'sort_order' => $existingCount + $offset,
                'is_primary' => ! $hasPrimary && $offset === 0,
            ]);

            if ($offset === 0 && ! $hasPrimary) {
                $hasPrimary = true;
            }
        }

        $this->syncPrimaryImagePath($event->fresh('images'));
    }

    private function removeImagesById(CarbootEvent $event, array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $images = $event->images()->whereIn('id', $ids)->get();
        foreach ($images as $image) {
            $image->delete();
        }

        $this->reassignPrimaryIfNeeded($event);
        $this->syncPrimaryImagePath($event->fresh('images'));
    }

    private function removeAllImages(CarbootEvent $event): void
    {
        $event->load('images');

        foreach ($event->images as $image) {
            $image->delete();
        }

        if ($event->image_path) {
            Storage::disk('public')->delete($event->image_path);
        }

        $event->updateQuietly(['image_path' => null]);
    }

    private function reassignPrimaryIfNeeded(CarbootEvent $event): void
    {
        if ($event->images()->where('is_primary', true)->exists()) {
            return;
        }

        $first = $event->images()->orderBy('sort_order')->orderBy('id')->first();
        if ($first) {
            $first->update(['is_primary' => true]);
        }
    }

    private function syncPrimaryImagePath(CarbootEvent $event): void
    {
        $event->loadMissing('images');

        $primary = $event->images->firstWhere('is_primary', true)
            ?? $event->images->sortBy('sort_order')->first();

        $newPath = $primary?->image_path;

        if ($newPath === null) {
            return;
        }

        if ($newPath !== $event->image_path) {
            if ($event->image_path && $event->image_path !== $newPath) {
                Storage::disk('public')->delete($event->image_path);
            }

            $event->updateQuietly(['image_path' => $newPath]);
        }
    }
}
