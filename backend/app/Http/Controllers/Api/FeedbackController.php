<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    /**
     * Store a newly created feedback submission.
     * Strict Sanctum auth required. No anonymous submissions.
     */
    public function store(Request $request)
    {
        // 1. Strict Validation matching our members-only policy
        $validated = $request->validate([
            'service_rating' => 'required|integer|min:1|max:5',
            'value_rating' => 'required|integer|min:1|max:5',
            'reviewer_role' => ['required', Rule::in(['Shopper', 'Vendor', 'UUM Student', 'Local Resident'])],
            'comments' => [
            'required',
            'string',
            'max:2000',
            function ($attribute, $value, $fail) {
                // PHP word count logic (must NOT exceed 50 words)
                if (str_word_count(trim($value)) > 50) {
                    $fail('Please limit your feedback to a maximum of 50 words.'); 
                }
            },
        ],
            'media' => 'nullable|file|mimes:jpeg,png,jpg,mp4|max:5120', // Max 5MB
        ]);

        // 2. Handle the optional media upload safely
        $mediaPath = null;
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('feedback_media', 'public');
        }

        // 3. Save to database using the secure authenticated User ID
        DB::table('feedbacks')->insert([
            'user_id' => $request->user()->id, 
            'reviewer_role' => $validated['reviewer_role'],
            'rating' => (int) round(($validated['service_rating'] + $validated['value_rating']) / 2),
            'comments' => $validated['comments'],
            'service_rating' => $validated['service_rating'],
            'value_rating' => $validated['value_rating'],
            'media_path' => $mediaPath,
            'helpful_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Feedback submitted successfully. Thank you!',
            'success' => true
        ], 201);
    }

    /**
     * Increment the helpful thumbs-up counter for a specific feedback.
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
     * Display a listing of the community feedback for the Vue frontend.
     * (Publicly accessible so anyone can READ reviews)
     */
    public function index()
    {
        $feedbacks = DB::table('feedbacks')
            ->leftJoin('users', 'feedbacks.user_id', '=', 'users.id')
            ->select('feedbacks.*', 'users.name as user_name')
            ->orderBy('feedbacks.created_at', 'desc')
            ->get()
            ->map(function ($review) {
                // Format the user relationship
                if ($review->user_name) {
                    $review->user = ['name' => $review->user_name];
                } else {
                    $review->user = ['name' => 'Community Member']; // Fallback for legacy rows without a linked user
                }
                return $review;
            });

        return response()->json($feedbacks, 200);
    }
}