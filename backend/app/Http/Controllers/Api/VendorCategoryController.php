<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VendorCategoryResolver;
use Illuminate\Http\JsonResponse;

/**
 * Phase 3.7 — vendor/public-safe canonical category list.
 */
class VendorCategoryController extends Controller
{
    public function index(VendorCategoryResolver $resolver): JsonResponse
    {
        return response()->json([
            'categories' => $resolver->listVendorSelectable(),
        ]);
    }
}
