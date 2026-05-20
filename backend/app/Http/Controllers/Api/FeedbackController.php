<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FeedbackController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Strict Validation matching our Vue frontend
        $validated = $request->validate([
            'service_rating' => 'required|integer|min:1|max:5',
            'value_rating' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string|max:1000',
            'media' => 'nullable|file|mimes:jpeg,png,jpg,mp4|max:5120', // Max 5MB
            'is_anonymous' => 'boolean',
        ]);

        // 2. Handle the optional media upload safely
        $mediaPath = null;
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('feedback_media', 'public');
        }

        // 3. Determine the User ID (Null if anonymous)
        $userId = ($request->is_anonymous || !Auth::guard('sanctum')->check()) 
                    ? null 
                    : Auth::guard('sanctum')->id();

        // 4. Save to your database using the Query Builder (No Model required!)
        DB::table('feedbacks')->insert([
            'user_id' => $userId,
            'rating' => (int) round(($validated['service_rating'] + $validated['value_rating']) / 2),
            'comments' => $validated['comments'] ?? null,
            'service_rating' => $validated['service_rating'],
            'value_rating' => $validated['value_rating'],
            'media_path' => $mediaPath,
            'helpful_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Maklum balas berjaya dihantar. Terima kasih!',
            'success' => true
        ], 201);
    }

    /**
     * Increment the helpful thumbs-up counter.
     */
    public function markHelpful($id)
    {
        DB::table('feedbacks')->where('id', $id)->increment('helpful_count');

        return response()->json([
            'message' => 'Feedback marked as helpful!',
            'success' => true
        ], 200);
    }

    /**
     * Display a listing of the community feedback.
     */
    public function index()
    {
        $feedbacks = DB::table('feedbacks')
            ->leftJoin('users', 'feedbacks.user_id', '=', 'users.id')
            ->select('feedbacks.*', 'users.name as user_name')
            ->orderBy('feedbacks.created_at', 'desc')
            ->get()
            ->map(function ($review) {
                // Format the user relationship so the Vue frontend doesn't break
                if ($review->user_name) {
                    $review->user = ['name' => $review->user_name];
                } else {
                    $review->user = null; // Anonymous
                }
                return $review;
            });

        return response()->json($feedbacks, 200);
    }
}