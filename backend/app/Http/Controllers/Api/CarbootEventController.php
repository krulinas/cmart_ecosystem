<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CarbootEventController extends Controller
{
    private const STATUSES = ['Available', 'Almost Full', 'Closed'];

    public function publicIndex()
    {
        $events = CarbootEvent::query()
            ->where('status', '!=', 'Closed')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        return response()->json($events);
    }

    public function index()
    {
        return response()->json(
            CarbootEvent::orderByDesc('starts_at')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validateEvent($request);
        $validated['image_path'] = $this->storePoster($request);

        $event = CarbootEvent::create($validated);

        return response()->json([
            'message' => '201 Created: Carboot event created successfully.',
            'event' => $event,
        ], 201);
    }

    public function show(CarbootEvent $carboot_event)
    {
        return response()->json($carboot_event);
    }

    public function update(Request $request, CarbootEvent $carboot_event)
    {
        $validated = $this->validateEvent($request, true);
        $imagePath = $this->resolvePosterPath($request, $carboot_event);

        if ($request->hasFile('poster') || $request->boolean('remove_poster')) {
            $validated['image_path'] = $imagePath;
        }

        $carboot_event->update($validated);

        return response()->json([
            'message' => '200 OK: Carboot event updated successfully.',
            'event' => $carboot_event->fresh(),
        ]);
    }

    public function destroy(CarbootEvent $carboot_event)
    {
        $this->deletePosterFile($carboot_event->image_path);
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
            'remove_poster' => 'nullable|boolean',
        ];

        if (!$partial) {
            $rules['status'] = ['required', Rule::in(self::STATUSES)];
        }

        $validated = $request->validate($rules);

        unset($validated['poster'], $validated['remove_poster']);

        if (array_key_exists('max_slots', $validated) && $validated['max_slots'] === '') {
            $validated['max_slots'] = null;
        }

        return $validated;
    }

    private function storePoster(Request $request): ?string
    {
        if (!$request->hasFile('poster')) {
            return null;
        }

        return $request->file('poster')->store('carboot_event_posters', 'public');
    }

    private function resolvePosterPath(Request $request, CarbootEvent $event): ?string
    {
        if ($request->boolean('remove_poster')) {
            $this->deletePosterFile($event->image_path);

            return null;
        }

        if ($request->hasFile('poster')) {
            $this->deletePosterFile($event->image_path);

            return $request->file('poster')->store('carboot_event_posters', 'public');
        }

        return $event->image_path;
    }

    private function deletePosterFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
