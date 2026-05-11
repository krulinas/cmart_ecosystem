<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Illuminate\Http\Request;

class SpaceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Space::orderBy('price')->get();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'space_size' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'status' => 'required|in:Available,Full',
        ]);

        return response()->json(Space::create($validated), 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return Space::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $space = Space::findOrFail($id);

        $validated = $request->validate([
            'space_size' => 'sometimes|required|string|max:255',
            'price' => 'sometimes|required|numeric|min:0',
            'status' => 'sometimes|required|in:Available,Full',
        ]);

        $space->update($validated);

        return $space;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Space::destroy($id);

        return response()->json(['message' => '200 OK: Space deleted successfully.']);
    }
}
