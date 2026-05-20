<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_rating' => 'required|integer|min:1|max:5',
            'value_rating' => 'required|integer|min:1|max:5',
            'reviewer_role' => ['required', Rule::in(['Shopper', 'Vendor', 'UUM Student', 'Local Resident'])],
            'comments' => [
                'required',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    if (str_word_count(trim($value)) > 50) {
                        $fail('Please limit your feedback to a maximum of 50 words.');
                    }
                },
            ],
            'media' => 'nullable|file|mimes:jpeg,png,jpg,mp4|max:5120',
        ]);

        $mediaPath = null;
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('feedback_media', 'public');
        }

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'reviewer_role' => $validated['reviewer_role'],
            'rating' => (int) round(($validated['service_rating'] + $validated['value_rating']) / 2),
            'comments' => $validated['comments'],
            'service_rating' => $validated['service_rating'],
            'value_rating' => $validated['value_rating'],
            'media_path' => $mediaPath,
            'helpful_count' => 0,
            'is_hidden' => false,
        ]);

        return response()->json([
            'message' => 'Feedback submitted successfully. Thank you!',
            'success' => true,
            'feedback' => $this->formatFeedback($feedback->load('user')),
        ], 201);
    }

    public function markHelpful($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->increment('helpful_count');

        return response()->json([
            'message' => 'Feedback marked as helpful!',
            'success' => true,
        ], 200);
    }

    /** Public listing — visible reviews only. */
    public function index()
    {
        $feedbacks = Feedback::with('user')
            ->where('is_hidden', false)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($review) => $this->formatFeedback($review));

        return response()->json($feedbacks, 200);
    }

    /** Staff listing — includes hidden reviews. */
    public function staffIndex()
    {
        $feedbacks = Feedback::with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($review) => $this->formatFeedback($review));

        return response()->json($feedbacks, 200);
    }

    public function show(Feedback $feedback)
    {
        return response()->json($this->formatFeedback($feedback->load('user')), 200);
    }

    public function update(Request $request, Feedback $feedback)
    {
        $validated = $request->validate([
            'is_hidden' => 'sometimes|boolean',
        ]);

        $feedback->update($validated);

        return response()->json([
            'message' => '200 OK: Feedback updated successfully.',
            'feedback' => $this->formatFeedback($feedback->fresh('user')),
        ], 200);
    }

    public function destroy(Feedback $feedback)
    {
        $feedback->delete();

        return response()->json([
            'message' => '200 OK: Feedback deleted successfully.',
            'success' => true,
        ], 200);
    }

    private function formatFeedback(Feedback $review): Feedback
    {
        $review->setAttribute('user', [
            'name' => $review->user?->name ?? 'Community Member',
        ]);

        return $review;
    }
}
