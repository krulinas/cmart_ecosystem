<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Support\ManagementRole;
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

    /** Public listing — visible reviews only, paginated with filters and summary. */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:newest,oldest,highest_rating,lowest_rating',
            'rating' => 'nullable|in:5,4,3,2_or_below',
            'reviewer_type' => 'nullable|string|max:50',
            'with_photo' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:24',
        ]);

        $query = Feedback::with('user')->where('is_hidden', false);

        $this->applyPublicFilters($query, $validated, $request);

        // Stable ordering: include id as a tiebreaker so rows sharing a
        // created_at timestamp keep a deterministic, visible order.
        $sort = $validated['sort'] ?? 'newest';
        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'highest_rating' => $query->orderByDesc('rating')->orderByDesc('created_at')->orderByDesc('id'),
            'lowest_rating' => $query->orderBy('rating')->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $perPage = (int) ($validated['per_page'] ?? self::PER_PAGE);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'summary' => $this->buildPublicSummary(),
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

    /** Staff listing — includes hidden reviews with optional filters. */
    public function staffIndex(Request $request)
    {
        $query = Feedback::with(['user', 'reviewedByUser', 'officialReplyByUser'])
            ->orderByDesc('created_at');

        $this->applyStaffFilter($query, $request->query('filter', 'all'));

        $feedbacks = $query->get()
            ->map(fn ($review) => $this->formatFeedback($review, true));

        return response()->json($feedbacks, 200);
    }

    public function show(Feedback $feedback)
    {
        return response()->json(
            $this->formatFeedback($feedback->load(['user', 'reviewedByUser', 'officialReplyByUser']), true),
            200
        );
    }

    public function update(Request $request, Feedback $feedback)
    {
        $validated = $request->validate([
            'is_hidden' => 'sometimes|boolean',
        ]);

        $feedback->update(collect($validated)->only('is_hidden')->all());

        return response()->json([
            'message' => '200 OK: Feedback updated successfully.',
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser']),
                true
            ),
        ], 200);
    }

    public function markReviewed(Request $request, Feedback $feedback)
    {
        $feedback->update([
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Feedback marked as reviewed.',
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser']),
                true
            ),
        ], 200);
    }

    public function updateOfficialReply(Request $request, Feedback $feedback)
    {
        if (
            $feedback->official_reply_status === 'published'
            && ManagementRole::isStaffRole($request->user()->role)
        ) {
            return response()->json([
                'message' => '403 Forbidden: Manager approval required to edit a published reply.',
            ], 403);
        }

        $validated = $request->validate([
            'official_reply_text' => 'nullable|string|max:2000',
        ]);

        $text = trim($validated['official_reply_text'] ?? '');

        if ($text === '') {
            $feedback->update([
                'official_reply_text' => null,
                'official_reply_status' => null,
                'official_reply_by' => null,
                'official_reply_published_at' => null,
            ]);
        } else {
            $feedback->update([
                'official_reply_text' => $text,
                'official_reply_status' => 'draft',
                'official_reply_by' => $request->user()->id,
                'official_reply_published_at' => null,
            ]);
        }

        return response()->json([
            'message' => $text === '' ? 'Official reply removed.' : 'Official reply draft saved.',
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser']),
                true
            ),
        ], 200);
    }

    public function publishOfficialReply(Request $request, Feedback $feedback)
    {
        if (!ManagementRole::canAccessManagerRoutes($request->user()->role)) {
            return response()->json(['message' => '403 Forbidden: Manager access required.'], 403);
        }

        $validated = $request->validate([
            'official_reply_text' => 'sometimes|nullable|string|max:2000',
        ]);

        $text = trim($validated['official_reply_text'] ?? $feedback->official_reply_text ?? '');

        if ($text === '') {
            return response()->json(['message' => 'Reply text is required before publishing.'], 422);
        }

        $feedback->update([
            'official_reply_text' => $text,
            'official_reply_status' => 'published',
            'official_reply_by' => $request->user()->id,
            'official_reply_published_at' => now(),
        ]);

        return response()->json([
            'message' => 'Official reply published.',
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser']),
                true
            ),
        ], 200);
    }

    public function destroy(Request $request, Feedback $feedback)
    {
        if (!ManagementRole::canAccessManagerRoutes($request->user()->role)) {
            return response()->json(['message' => '403 Forbidden: Manager access required.'], 403);
        }

        $feedback->delete();

        return response()->json([
            'message' => '200 OK: Feedback deleted successfully.',
            'success' => true,
        ], 200);
    }

    private function applyPublicFilters($query, array $validated, Request $request): void
    {
        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('comments', 'like', '%' . $search . '%')
                    ->orWhere('reviewer_role', 'like', '%' . $search . '%');
            });
        }

        $rating = $validated['rating'] ?? null;
        if ($rating === '2_or_below') {
            $query->where(function ($q) {
                $q->whereBetween('rating', [1, 2])
                    ->orWhere(function ($inner) {
                        $inner->where('rating', 0)
                            ->where(function ($legacy) {
                                $legacy->whereBetween('service_rating', [1, 2])
                                    ->orWhereBetween('value_rating', [1, 2]);
                            });
                    });
            });
        } elseif (in_array($rating, ['3', '4', '5'], true)) {
            $query->where('rating', (int) $rating);
        }

        $reviewerType = trim($validated['reviewer_type'] ?? '');
        if ($reviewerType !== '') {
            $query->where('reviewer_role', $reviewerType);
        }

        if ($request->boolean('with_photo')) {
            $query->whereNotNull('media_path')->where('media_path', '!=', '');
        }
    }

    private function buildPublicSummary(): array
    {
        $visible = Feedback::query()
            ->where('is_hidden', false)
            ->get(['rating', 'service_rating', 'value_rating']);

        $distribution = ['5' => 0, '4' => 0, '3' => 0, '2' => 0, '1' => 0];
        $ratedValues = [];

        foreach ($visible as $review) {
            $rating = $this->resolveRating($review);
            if ($rating === null || $rating < 1 || $rating > 5) {
                continue;
            }
            $ratedValues[] = $rating;
            $distribution[(string) $rating]++;
        }

        $ratedCount = count($ratedValues);
        $average = $ratedCount > 0 ? round(array_sum($ratedValues) / $ratedCount, 1) : 0;

        return [
            'average_rating' => $average,
            'total_reviews' => $visible->count(),
            'distribution' => $distribution,
        ];
    }

    private function applyStaffFilter($query, string $filter): void
    {
        match ($filter) {
            'visible' => $query->where('is_hidden', false),
            'hidden' => $query->where('is_hidden', true),
            'unreviewed' => $query->whereNull('reviewed_at'),
            'reviewed' => $query->whereNotNull('reviewed_at'),
            'with_photo' => $query->whereNotNull('media_path')->where('media_path', '!=', ''),
            'low_rating' => $query->where(function ($q) {
                $q->whereBetween('rating', [1, 2])
                    ->orWhere(function ($inner) {
                        $inner->where('rating', 0)
                            ->where(function ($legacy) {
                                $legacy->whereBetween('service_rating', [1, 2])
                                    ->orWhereBetween('value_rating', [1, 2]);
                            });
                    });
            }),
            default => null,
        };
    }

    private function formatFeedback(Feedback $review, bool $forManagement = false): array
    {
        $data = [
            'id' => $review->id,
            'user_name' => $review->user?->name ?? 'Community Member',
            'role' => $review->reviewer_role,
            'rating' => $this->resolveRating($review),
            'comment' => $review->comments,
            'proof_url' => $this->resolveProofUrl($review->media_path),
            'created_at' => $review->created_at?->toIso8601String(),
            'official_reply' => $this->formatOfficialReply($review, $forManagement),
        ];

        if ($forManagement) {
            $data['is_hidden'] = (bool) $review->is_hidden;
            $data['reviewed_at'] = $review->reviewed_at?->toIso8601String();
            $data['reviewed_by'] = $review->reviewed_by;
            $data['reviewed_by_name'] = $review->reviewedByUser?->name;
        }

        return $data;
    }

    private function formatOfficialReply(Feedback $review, bool $forManagement): ?array
    {
        if (!$review->official_reply_text) {
            return null;
        }

        if (!$forManagement && $review->official_reply_status !== 'published') {
            return null;
        }

        if (!$forManagement) {
            return [
                'text' => $review->official_reply_text,
            ];
        }

        return [
            'text' => $review->official_reply_text,
            'status' => $review->official_reply_status,
            'by_name' => $review->officialReplyByUser?->name,
            'published_at' => $review->official_reply_published_at?->toIso8601String(),
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

        // Video feedback is intentionally left as future enhancement.
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
