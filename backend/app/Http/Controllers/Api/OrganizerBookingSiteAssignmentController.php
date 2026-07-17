<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AllocationValidationException;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\OrganizerBookingCategoryPlacementService;
use App\Services\OrganizerBookingSiteReassignmentService;
use App\Services\VendorBookingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 3.8 — Organizer category placement inspection and site reassignment.
 */
class OrganizerBookingSiteAssignmentController extends Controller
{
    public function categoryPlacement(
        Booking $booking,
        OrganizerBookingCategoryPlacementService $placementService,
    ): JsonResponse {
        return response()->json($placementService->placementPayload($booking));
    }

    public function reassignmentOptions(
        Booking $booking,
        OrganizerBookingCategoryPlacementService $placementService,
    ): JsonResponse {
        try {
            return response()->json($placementService->optionsPayload($booking));
        } catch (AllocationValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 422);
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }
    }

    public function reassign(
        Request $request,
        Booking $booking,
        OrganizerBookingSiteReassignmentService $reassignmentService,
    ): JsonResponse {
        $validated = $request->validate([
            'event_site_ids' => 'required|array|min:1',
            'event_site_ids.*' => 'required|integer|distinct',
            'assignment_fingerprint' => 'required|string|max:128',
            'acknowledge_category_override' => 'sometimes|boolean',
            'override_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $result = $reassignmentService->reassign(
                $booking,
                $request->user(),
                $validated['event_site_ids'],
                $validated['assignment_fingerprint'],
                (bool) ($validated['acknowledge_category_override'] ?? false),
                $validated['override_reason'] ?? null,
            );

            return response()->json([
                'message' => '200 OK: Booking site assignment updated successfully.',
                'booking' => VendorBookingPresenter::presentForOrganizer($result['booking'], true),
                'category_placement' => $result['placement'],
            ]);
        } catch (AllocationValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 422);
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }
    }
}
