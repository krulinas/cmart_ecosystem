<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\VendorItem;
use App\Models\VendorItemEventListing;
use App\Services\VendorItemEventListingService;
use App\Services\VendorItemPresenter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorItemEventListingController extends Controller
{
    public function eligibleEvents(Request $request, VendorItemEventListingService $service): JsonResponse
    {
        $bookings = $service->eligibleEventsForVendor($request->user());

        return response()->json([
            'events' => $bookings->map(fn ($booking) => [
                'booking_id' => $booking->id,
                'carboot_event_id' => $booking->carboot_event_id,
                'title' => $booking->carbootEvent?->title,
                'starts_at' => $booking->carbootEvent?->starts_at?->toIso8601String(),
                'ends_at' => $booking->carbootEvent?->ends_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function index(
        Request $request,
        int $carboot_event,
        VendorItemEventListingService $service,
    ): JsonResponse {
        try {
            $listings = $service->listingsForVendorEvent($request->user(), $carboot_event);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }

        return response()->json([
            'listings' => $listings->map(fn (VendorItemEventListing $listing) => [
                'id' => $listing->id,
                'carboot_event_id' => $listing->carboot_event_id,
                'vendor_booking_id' => $listing->vendor_booking_id,
                'selected_at' => $listing->selected_at?->toIso8601String(),
                'item' => VendorItemPresenter::fromModel($listing->vendorItem),
            ])->values(),
        ]);
    }

    public function store(
        Request $request,
        int $carboot_event,
        VendorItemEventListingService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'vendor_item_ids' => 'required_without:select_all_unsold|array|min:1',
            'vendor_item_ids.*' => 'integer',
            'select_all_unsold' => 'sometimes|boolean',
        ]);

        try {
            $listings = ! empty($validated['select_all_unsold'])
                ? $service->selectAllUnsold($request->user(), $carboot_event)
                : $service->selectItems(
                    $request->user(),
                    $carboot_event,
                    $validated['vendor_item_ids'] ?? [],
                );
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        return response()->json([
            'message' => __('api.event_items_selected_successfully'),
            'listings' => $listings->map(fn (VendorItemEventListing $listing) => [
                'id' => $listing->id,
                'vendor_item_id' => $listing->vendor_item_id,
                'carboot_event_id' => $listing->carboot_event_id,
            ])->values(),
        ], 201);
    }

    public function destroy(
        Request $request,
        int $carboot_event,
        VendorItem $vendor_item,
        VendorItemEventListingService $service,
    ): JsonResponse {
        try {
            $service->deselectItem($request->user(), $carboot_event, (int) $vendor_item->id);
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        } catch (DomainConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => $exception->error,
            ], 409);
        }

        return response()->json([
            'message' => __('api.event_item_removed_successfully'),
        ]);
    }
}
