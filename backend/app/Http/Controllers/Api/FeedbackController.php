<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedbackController extends Controller
{
    private const MIN_WORDS = 5;
    private const MAX_WORDS = 100;
    private const PER_PAGE = 6;

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'reviewer_role' => ['required', Rule::in(['Shopper', 'Vendor', 'UUM Student', 'Local Resident'])],
            'comments' => [
                'required',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    $count = $this->countWords($value);
                    if ($count < self::MIN_WORDS) {
                        $fail('Please write at least ' . self::MIN_WORDS . ' words in your feedback.');
                    }
                    if ($count > self::MAX_WORDS) {
                        $fail('Please limit your feedback to a maximum of ' . self::MAX_WORDS . ' words.');
                    }
                },
            ],
            'media' => 'nullable|file|mimes:jpeg,png,jpg|max:5120',
        ]);

        $mediaPath = null;
        if ($request->hasFile('media')) {
            $mediaPath = $request->file('media')->store('feedback_media', 'public');
        }

        $feedback = Feedback::create([
            'user_id' => $request->user()->id,
            'reviewer_role' => $validated['reviewer_role'],
            'rating' => $validated['rating'],
            'comments' => $validated['comments'],
            'service_rating' => $validated['rating'],
            'value_rating' => $validated['rating'],
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

    /** Public listing — visible reviews only, paginated. */
    public function index(Request $request)
    {
        $paginated = Feedback::with('user')
            ->where('is_hidden', false)
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $paginated->getCollection()
                ->map(fn ($review) => $this->formatFeedback($review))
                ->values(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
            'last_page' => $paginated->lastPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
        ], 200);
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

    private function formatFeedback(Feedback $review): array
    {
        return [
            'id' => $review->id,
            'user_name' => $review->user?->name ?? 'Community Member',
            'role' => $review->reviewer_role,
            'rating' => $this->resolveRating($review),
            'comment' => $review->comments,
            'proof_url' => $this->resolveProofUrl($review->media_path),
            'created_at' => $review->created_at?->toIso8601String(),
            'is_hidden' => (bool) $review->is_hidden,
        ];
    }

    private function resolveRating(Feedback $review): ?int
    {
        if ($review->rating >= 1 && $review->rating <= 5) {
            return (int) $review->rating;
        }

        $service = (int) ($review->service_rating ?? 0);
        $value = (int) ($review->value_rating ?? 0);

        if ($service >= 1 && $value >= 1) {
            return (int) round(($service + $value) / 2);
        }

        if ($service >= 1) {
            return $service;
        }

        if ($value >= 1) {
            return $value;
        }

        return null;
    }

    private function resolveProofUrl(?string $mediaPath): ?string
    {
        if (!$mediaPath) {
            return null;
        }

        if (preg_match('/\.(mp4|mov|webm|avi)$/i', $mediaPath)) {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $mediaPath), '/');

        return asset('storage/' . $normalized);
    }

    private function countWords(string $text): int
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY));
    }
}
