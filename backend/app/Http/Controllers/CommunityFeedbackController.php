<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommunityFeedbackController extends Controller
{
    /**
     * Handle the incoming feedback submission.
     */
    public function store(Request $request)
    {
        // 1. Strict Validation to protect your server
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
            // Stores in storage/app/public/feedback_media
            $mediaPath = $request->file('media')->store('feedback_media', 'public');
        }

        // 3. Determine the User ID (Null if anonymous)
        // Assuming you are using Laravel's default auth for logged-in users
        $userId = ($request->is_anonymous || !auth()->check()) ? null : auth()->id();

        // 4. Save to your database (Replace 'feedback' with your actual table name!)
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
            'message' => 'Feedback submitted successfully!',
            'success' => true
        ], 201);
    }

    /**
     * Increment the helpful thumbs-up counter.
     */
    public function markHelpful($id)
    {
        // Replace 'feedback' with your actual table name
        DB::table('feedbacks')->where('id', $id)->increment('helpful_count');

        return response()->json([
            'message' => 'Feedback marked as helpful!',
            'success' => true
        ], 200);
    }
}