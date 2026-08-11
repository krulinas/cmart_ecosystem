<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;

/**
 * Internal physical-site catalogue. Not a pricing catalogue.
 * Vendor booking totals use event.site_price × site count.
 */
class SpaceController extends Controller
{
    public function index()
    {
        return Space::query()
            ->orderBy('space_size')
            ->get(['id', 'space_size', 'status', 'created_at', 'updated_at']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'space_size' => 'required|string|max:255',
            'status' => 'required|in:Available,Full',
        ]);

        return response()->json(Space::create($validated), 201);
    }

    public function show($id)
    {
        return Space::query()
            ->select(['id', 'space_size', 'status', 'created_at', 'updated_at'])
            ->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $space = Space::findOrFail($id);

        $validated = $request->validate([
            'space_size' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:Available,Full',
        ]);

        $space->update($validated);

        return $space->fresh();
    }

    public function destroy($id)
    {
        Space::destroy($id);

        return response()->json(['message' => '200 OK: Space deleted successfully.']);
    }
}
