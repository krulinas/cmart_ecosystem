<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CarbootEvent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles community users registering (RSVP) for carboot events.
 *
 * The register() method uses a database transaction with pessimistic row locking
 * so that concurrent requests cannot overbook an event beyond max_slots.
 */
class EventRegistrationController extends Controller
{
    /**
     * POST /api/events/{carboot_event}/register
     *
     * Attach the authenticated user to the event inside a locked transaction.
     * Returns 422 when the event is full, closed, or already in the past.
     */
    public function register(Request $request, CarbootEvent $carboot_event): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($user, $carboot_event) {
                /*
                 * lockForUpdate() places a pessimistic write-lock on the event row.
                 * Any other registration request for the SAME event will block here
                 * until this transaction commits, preventing race-condition overbooking.
                 */
                $event = CarbootEvent::query()
                    ->whereKey($carboot_event->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Business rules checked AFTER the lock is acquired.
                if ($event->status === 'Closed') {
                    return [
                        'status' => 422,
                        'body' => ['message' => '422 Unprocessable Entity: This event is closed for registration.'],
                    ];
                }

                if ($event->ends_at->isPast()) {
                    return [
                        'status' => 422,
                        'body' => ['message' => '422 Unprocessable Entity: This event has already ended.'],
                    ];
                }

                // Check if this user already registered (inside the same transaction).
                $alreadyRegistered = $event->registeredUsers()
                    ->where('users.id', $user->id)
                    ->exists();

                if ($alreadyRegistered) {
                    return [
                        'status' => 422,
                        'body' => ['message' => '422 Unprocessable Entity: You are already registered for this event.'],
                    ];
                }

                // Count current registrations while we still hold the row lock.
                $currentCount = $event->registeredUsers()->count();

                if ($event->max_slots !== null && $currentCount >= $event->max_slots) {
                    return [
                        'status' => 422,
                        'body' => ['message' => '422 Unprocessable Entity: This event is full. No slots remaining.'],
                    ];
                }

                // Safe to attach — capacity check passed under lock.
                $event->registeredUsers()->attach($user->id, [
                    'registered_at' => now(),
                ]);

                // Keep the public status column in sync with actual occupancy.
                $event->syncCapacityStatus();

                return [
                    'status' => 201,
                    'body' => [
                        'message' => '201 Created: You have successfully registered for this event.',
                        'event' => $event->fresh(),
                    ],
                ];
            });
        } catch (\Illuminate\Database\QueryException $exception) {
            /*
             * The unique index on (carboot_event_id, user_id) is a last-resort guard.
             * If two requests slip through, MySQL raises a duplicate-key error (1062).
             */
            if ($exception->errorInfo[1] === 1062) {
                return response()->json([
                    'message' => '422 Unprocessable Entity: You are already registered for this event.',
                ], 422);
            }

            throw $exception;
        }

        return response()->json($result['body'], $result['status']);
    }
}
