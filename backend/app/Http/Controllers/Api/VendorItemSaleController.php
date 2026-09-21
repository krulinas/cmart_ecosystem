<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Models\VendorItem;
use App\Models\VendorItemSale;
use App\Services\VendorItemSaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorItemSaleController extends Controller
{
    public function storeWalkIn(
        Request $request,
        VendorItem $vendor_item,
        VendorItemSaleService $service,
    ): JsonResponse {
        if ((int) $vendor_item->user_id !== (int) $request->user()->id) {
            return response()->json([
                'message' => __('api.you_do_not_have_permission_to_access_this_item'),
            ], 403);
        }

        $validated = $request->validate([
            'carboot_event_id' => 'required|integer',
            'final_sale_price' => 'required|numeric|min:0',
        ]);

        try {
            $sale = $service->recordWalkInSale(
                $vendor_item,
                $request->user(),
                (int) $validated['carboot_event_id'],
                $validated['final_sale_price'],
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
            'message' => __('api.walk_in_sale_recorded_successfully'),
            'sale' => $this->present($sale),
        ], 201);
    }

    private function present(VendorItemSale $sale): array
    {
        return [
            'id' => $sale->id,
            'vendor_item_id' => $sale->vendor_item_id,
            'carboot_event_id' => $sale->carboot_event_id,
            'sale_source' => $sale->sale_source,
            'item_name_snapshot' => $sale->item_name_snapshot,
            'asking_price_snapshot' => $sale->asking_price_snapshot !== null
                ? round((float) $sale->asking_price_snapshot, 2)
                : null,
            'final_sale_price' => round((float) $sale->final_sale_price, 2),
            'currency' => $sale->currency,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'event' => [
                'title' => $sale->carbootEvent?->title,
                'starts_at' => $sale->carbootEvent?->starts_at?->toIso8601String(),
            ],
        ];
    }
}
