<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\FeedbackEligibilityService;
use App\Support\FeedbackClassification;
use App\Support\ManagementRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeedbackController extends Controller
{
    private const MIN_WORDS = 5;
    private const MAX_WORDS = 100;
    private const PER_PAGE = 6;
    private const MAX_IMAGES = 3;

    public function __construct(
        private readonly FeedbackEligibilityService $eligibility,
    ) {}

    /**
     * Authenticated feedback options: visible events + vendor-eligible events.
     */
    public function options(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('api.unauthenticated')], 401);
        }

        $vendorEvents = $this->eligibility->eligibleVendorEventsForUser($user);
        $hasVendorEligibility = $vendorEvents !== [];

        return response()->json([
            'visible_events' => $this->eligibility->visibleEventsForFeedback(),
            'vendor_eligible_events' => $vendorEvents,
            'vendor_eligible' => $hasVendorEligibility,
            'vendor_ineligible_message' => FeedbackEligibilityService::VENDOR_INELIGIBLE_MESSAGE,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => __('api.unauthenticated')], 401);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'participation_type' => ['required', 'string', Rule::in(FeedbackClassification::PARTICIPATION_TYPES)],
            'carboot_event_id' => [
                'required',
                'integer',
                Rule::exists('carboot_events', 'id'),
            ],
            'community_backgrounds' => ['nullable', 'array'],
            'community_backgrounds.*' => ['string', Rule::in(FeedbackClassification::COMMUNITY_BACKGROUNDS)],
            // Legacy clients may still send reviewer_role; ignore for write path when participation_type is present.
            'reviewer_role' => 'nullable|string|max:50',
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
            'media' => 'nullable|file|mimes:jpeg,png,jpg,webp|max:5120',
            'images' => 'nullable|array|max:' . self::MAX_IMAGES,
            'images.*' => 'file|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $backgrounds = FeedbackClassification::normalizeCommunityBackgrounds(
            $validated['community_backgrounds'] ?? [],
        );

        $rawBackgrounds = $validated['community_backgrounds'] ?? [];
        if (
            is_array($rawBackgrounds)
            && count($rawBackgrounds) > 1
            && in_array(FeedbackClassification::PREFER_NOT_TO_SAY, $rawBackgrounds, true)
        ) {
            throw ValidationException::withMessages([
                'community_backgrounds' => [
                    '"Prefer not to say" cannot be combined with other community background options.',
                ],
            ]);
        }

        $participationType = $validated['participation_type'];
        $participationLabel = FeedbackClassification::participationLabel($participationType);
        $eventId = (int) $validated['carboot_event_id'];

        // Never trust client event selection for vendor without an Approved booking.
        if ($this->eligibility->isVendorParticipation($participationType)) {
            $this->eligibility->assertVendorMaySubmit($user, $eventId);
        } else {
            // Non-vendor: event must exist (including Closed); no booking check.
            $this->eligibility->assertEventAcceptsFeedback($eventId);
        }

        $payload = [
            'user_id' => $user->id,
            'carboot_event_id' => $eventId,
            'participation_type' => $participationType,
            'community_backgrounds' => $backgrounds === [] ? null : $backgrounds,
            // Mirror human-readable participation label for legacy list/search surfaces.
            'reviewer_role' => $participationLabel,
            'rating' => $validated['rating'],
            'comments' => $validated['comments'],
            'service_rating' => $validated['rating'],
            'value_rating' => $validated['rating'],
            'is_hidden' => false,
        ];

        $existing = $this->eligibility->findExistingFeedback($user, $eventId, $participationType);
        $updated = false;

        if ($existing) {
            // One submission per user + event + participation type — update in place.
            $existing->fill($payload);
            if ($existing->media_path === null) {
                $existing->media_path = null;
            }
            $existing->save();
            $feedback = $existing;
            $updated = true;
            // Only attach new images when provided; keep existing gallery otherwise.
            if ($request->hasFile('images') || $request->hasFile('media')) {
                $this->attachUploadedImages($request, $feedback);
            }
        } else {
            $payload['media_path'] = null;
            $payload['helpful_count'] = 0;
            $feedback = Feedback::create($payload);
            $this->attachUploadedImages($request, $feedback);
        }

        return response()->json([
            'message' => $updated
                ? 'Feedback updated successfully. Thank you!'
                : 'Feedback submitted successfully. Thank you!',
            'success' => true,
            'updated' => $updated,
            'feedback' => $this->formatFeedback($feedback->load(['user', 'images'])),
        ], $updated ? 200 : 201);
    }

    public function markHelpful($id)
    {
        $feedback = Feedback::findOrFail($id);
        $feedback->increment('helpful_count');

        return response()->json([
            'message' => __('api.feedback_marked_as_helpful'),
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
        $query = Feedback::with(['user', 'reviewedByUser', 'officialReplyByUser', 'images'])
            ->orderByDesc('created_at');

        $this->applyStaffFilter($query, $request->query('filter', 'all'));

        $feedbacks = $query->get()
            ->map(fn ($review) => $this->formatFeedback($review, true));

        return response()->json($feedbacks, 200);
    }

    public function show(Feedback $feedback)
    {
        return response()->json(
            $this->formatFeedback($feedback->load(['user', 'reviewedByUser', 'officialReplyByUser', 'images']), true),
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
            'message' => __('api.feedback_updated_successfully'),
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser', 'images']),
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
            'message' => __('api.feedback_marked_as_reviewed'),
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser', 'images']),
                true
            ),
        ], 200);
    }

    public function updateOfficialReply(Request $request, Feedback $feedback)
    {
        if (
            $feedback->official_reply_status === 'published'
            && !ManagementRole::isOrganizerEquivalent($request->user()->role)
        ) {
            return response()->json([
                'message' => __('api.organizer_approval_required_to_edit_a_published_reply'),
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
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser', 'images']),
                true
            ),
        ], 200);
    }

    public function publishOfficialReply(Request $request, Feedback $feedback)
    {
        if (!ManagementRole::canAccessOrganizerRoutes($request->user()->role)) {
            return response()->json(['message' => __('api.organizer_access_required')], 403);
        }

        $validated = $request->validate([
            'official_reply_text' => 'sometimes|nullable|string|max:2000',
        ]);

        $text = trim($validated['official_reply_text'] ?? $feedback->official_reply_text ?? '');

        if ($text === '') {
            return response()->json(['message' => __('api.reply_text_is_required_before_publishing')], 422);
        }

        $feedback->update([
            'official_reply_text' => $text,
            'official_reply_status' => 'published',
            'official_reply_by' => $request->user()->id,
            'official_reply_published_at' => now(),
        ]);

        return response()->json([
            'message' => __('api.official_reply_published'),
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser', 'images']),
                true
            ),
        ], 200);
    }

    public function destroy(Request $request, Feedback $feedback)
    {
        if (!ManagementRole::canAccessOrganizerRoutes($request->user()->role)) {
            return response()->json(['message' => __('api.organizer_access_required')], 403);
        }

        $feedback->delete();

        return response()->json([
            'message' => __('api.feedback_deleted_successfully'),
            'success' => true,
        ], 200);
    }

    public function destroyImage(Request $request, Feedback $feedback, string $image)
    {
        if (!ManagementRole::canAccessOrganizerRoutes($request->user()->role)) {
            return response()->json(['message' => __('api.organizer_access_required')], 403);
        }

        if ($image === 'legacy') {
            $this->removeLegacyMedia($feedback);
        } else {
            $imageId = (int) $image;
            if ($imageId < 1) {
                return response()->json(['message' => __('api.feedback_image_not_found')], 404);
            }

            $record = $feedback->images()->whereKey($imageId)->first();
            if (!$record) {
                return response()->json(['message' => __('api.feedback_image_not_found')], 404);
            }

            $this->removeRelatedImage($feedback, $record);
        }

        return response()->json([
            'message' => __('api.feedback_attachment_removed'),
            'feedback' => $this->formatFeedback(
                $feedback->fresh(['user', 'reviewedByUser', 'officialReplyByUser', 'images']),
                true
            ),
        ], 200);
    }

    private function applyPublicFilters($query, array $validated, Request $request): void
    {
        $search = trim($validated['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('comments', 'like', '%' . $search . '%')
                    ->orWhere('reviewer_role', 'like', '%' . $search . '%')
                    ->orWhere('participation_type', 'like', '%' . $search . '%');
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
            $legacyRoles = FeedbackClassification::legacyReviewerRolesForParticipation($reviewerType);
            $query->where(function ($q) use ($reviewerType, $legacyRoles) {
                $q->where('participation_type', $reviewerType);
                if ($legacyRoles !== []) {
                    $q->orWhere(function ($legacy) use ($legacyRoles) {
                        $legacy->whereNull('participation_type')
                            ->whereIn('reviewer_role', $legacyRoles);
                    });
                }
                // Allow legacy filter values still used by older clients/bookmarks.
                $q->orWhere(function ($legacy) use ($reviewerType) {
                    $legacy->whereNull('participation_type')
                        ->where('reviewer_role', $reviewerType);
                });
            });
        }

        if ($request->boolean('with_photo')) {
            $this->constrainWithPhoto($query);
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
            'with_photo' => $this->constrainWithPhoto($query),
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
        $backgrounds = is_array($review->community_backgrounds)
            ? FeedbackClassification::normalizeCommunityBackgrounds($review->community_backgrounds)
            : [];

        $participationType = $review->participation_type
            ?: FeedbackClassification::legacyReviewerRoleToParticipation($review->reviewer_role);

        $participationLabel = FeedbackClassification::participationLabel($participationType)
            ?: ($review->reviewer_role ?: null);

        $data = [
            'id' => $review->id,
            'user_name' => $review->user?->name ?? 'Community Member',
            'participation_type' => $participationType,
            'participation_type_label' => $participationLabel,
            'community_backgrounds' => $backgrounds,
            'community_background_labels' => FeedbackClassification::communityBackgroundLabelsFor($backgrounds),
            // Legacy key retained for existing public/admin badges; prefer participation_type_label.
            'role' => $participationLabel,
            'rating' => $this->resolveRating($review),
            'comment' => $review->comments,
            'created_at' => $review->created_at?->toIso8601String(),
            'official_reply' => $this->formatOfficialReply($review, $forManagement),
        ];

        if ($forManagement) {
            $images = $this->managementImages($review);
            $data['is_hidden'] = (bool) $review->is_hidden;
            $data['reviewed_at'] = $review->reviewed_at?->toIso8601String();
            $data['reviewed_by'] = $review->reviewed_by;
            $data['reviewed_by_name'] = $review->reviewedByUser?->name;
            $data['images'] = $images;
            $data['image_count'] = count($images);
            $data['proof_url'] = $images[0]['image_url'] ?? null;
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

    private function constrainWithPhoto($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($legacy) {
                $legacy->whereNotNull('media_path')->where('media_path', '!=', '');
            })->orWhereHas('images');
        });
    }

    private function collectUploadFiles(Request $request): array
    {
        $files = [];

        if ($request->hasFile('images')) {
            foreach ((array) $request->file('images') as $file) {
                if ($file) {
                    $files[] = $file;
                }
            }
        }

        if ($request->hasFile('media')) {
            $files[] = $request->file('media');
        }

        return array_slice($files, 0, self::MAX_IMAGES);
    }

    private function attachUploadedImages(Request $request, Feedback $feedback): void
    {
        $files = $this->collectUploadFiles($request);
        if ($files === []) {
            return;
        }

        $firstPath = null;
        foreach ($files as $offset => $file) {
            $path = $file->store('feedback_media', 'public');
            $feedback->images()->create([
                'image_path' => $path,
                'sort_order' => $offset,
            ]);
            if ($firstPath === null) {
                $firstPath = $path;
            }
        }

        if ($firstPath) {
            $feedback->updateQuietly(['media_path' => $firstPath]);
        }
    }

    private function managementImages(Feedback $review): array
    {
        $review->loadMissing('images');

        $images = $review->images
            ->map(fn ($image) => $image->toApiArray())
            ->values()
            ->all();

        $relatedPaths = collect($images)
            ->pluck('image_path')
            ->filter()
            ->all();

        $legacyPath = $review->normalizedMediaPath();
        if ($legacyPath && !in_array($legacyPath, $relatedPaths, true)) {
            $url = $this->resolveProofUrl($legacyPath);
            if ($url) {
                array_unshift($images, [
                    'id' => null,
                    'image_path' => $legacyPath,
                    'image_url' => $url,
                    'sort_order' => -1,
                    'is_legacy' => true,
                ]);
            }
        }

        return array_values($images);
    }

    private function removeRelatedImage(Feedback $feedback, $image): void
    {
        $path = $image->normalizedImagePath() ?: $image->image_path;

        // Avoid FeedbackImage::deleting deleting the same path a second time.
        $image->image_path = null;
        $image->delete();

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        $this->syncMediaPathPointer($feedback, $path);
    }

    private function removeLegacyMedia(Feedback $feedback): void
    {
        $path = $feedback->normalizedMediaPath();
        if (!$path) {
            return;
        }

        $shared = $feedback->images()
            ->get()
            ->contains(fn ($image) => $image->normalizedImagePath() === $path);

        // Only delete the physical file when no related row still references it.
        if (!$shared) {
            Storage::disk('public')->delete($path);
        }

        $this->syncMediaPathPointer($feedback, $path);
    }

    /**
     * Keep feedbacks.media_path as a backward-compatible pointer to the first
     * remaining related image, never to a deleted file.
     */
    private function syncMediaPathPointer(Feedback $feedback, ?string $removedPath = null): void
    {
        $remaining = $feedback->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $pointer = $feedback->normalizedMediaPath();
        $remainingPaths = $remaining
            ->map(fn ($image) => $image->normalizedImagePath())
            ->filter()
            ->values()
            ->all();

        if ($remaining->isNotEmpty()) {
            // Keep the existing pointer when it still names a remaining image.
            if ($pointer && in_array($pointer, $remainingPaths, true)) {
                return;
            }

            $feedback->updateQuietly([
                'media_path' => $remaining->first()->image_path,
            ]);

            return;
        }

        // No related images remain. Preserve an untouched legacy-only pointer
        // that does not match the file we just removed; otherwise clear it.
        if ($pointer && $removedPath && $pointer !== $removedPath) {
            return;
        }

        if ($feedback->media_path !== null) {
            $feedback->updateQuietly(['media_path' => null]);
        }
    }
}
