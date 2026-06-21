<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Services\EventPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CarbootEventController extends Controller
{
    private const STATUSES = ['Available', 'Almost Full', 'Closed'];
    private const MAX_IMAGES = 5;

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
            ->map(fn (CarbootEvent $event) => EventPresenter::fromModel($event))
            ->values();

        return response()->json($events);
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvent($request);
        $event = CarbootEvent::create($validated);

        $this->attachUploadedImages($request, $event);

        return response()->json([
            'message' => '201 Created: Carboot event created successfully.',
            'event' => EventPresenter::fromModel($event->fresh('images')),
        ], 201);
    }

    public function show(CarbootEvent $carboot_event)
    {
        $carboot_event->load('images');

        return response()->json(EventPresenter::fromModel($carboot_event));
    }

    public function update(Request $request, CarbootEvent $carboot_event)
    {
        $validated = $this->validateEvent($request, true);

        if ($request->boolean('remove_poster')) {
            $this->removeAllImages($carboot_event);
            $validated['image_path'] = null;
        }

        $carboot_event->update($validated);

        if ($request->filled('remove_image_ids')) {
            $this->removeImagesById($carboot_event, (array) $request->input('remove_image_ids'));
        }

        $this->attachUploadedImages($request, $carboot_event);

        return response()->json([
            'message' => '200 OK: Carboot event updated successfully.',
            'event' => EventPresenter::fromModel($carboot_event->fresh('images')),
        ]);
    }

    public function destroy(CarbootEvent $carboot_event)
    {
        $carboot_event->delete();

        return response()->json([
            'message' => '200 OK: Carboot event deleted successfully.',
        ]);
    }

    private function validateEvent(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => ($partial ? 'sometimes|' : '') . 'required|string|max:255',
            'starts_at' => ($partial ? 'sometimes|' : '') . 'required|date',
            'ends_at' => ($partial ? 'sometimes|' : '') . 'required|date|after:starts_at',
            'status' => ['sometimes', 'required', Rule::in(self::STATUSES)],
            'description' => 'nullable|string|max:5000',
            'max_slots' => 'nullable|integer|min:1',
            'poster' => 'nullable|file|mimes:jpeg,jpg,png,webp|max:5120',
            'images' => 'nullable|array|max:' . self::MAX_IMAGES,
            'images.*' => 'file|mimes:jpeg,jpg,png,webp|max:5120',
            'remove_poster' => 'nullable|boolean',
            'remove_image_ids' => 'nullable|array',
            'remove_image_ids.*' => 'integer',
        ];

        if (!$partial) {
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

        return $validated;
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
            $this->syncPrimaryImagePath($event);

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
                'is_primary' => !$hasPrimary && $offset === 0,
            ]);

            if ($offset === 0 && !$hasPrimary) {
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

        if ($newPath !== $event->image_path) {
            if ($event->image_path && $event->image_path !== $newPath) {
                Storage::disk('public')->delete($event->image_path);
            }

            $event->updateQuietly(['image_path' => $newPath]);
        }
    }
}
