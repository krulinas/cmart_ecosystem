<?php

namespace Database\Seeders;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class FeedbackSeeder extends Seeder
{
    /**
     * Idempotent demo feedback for local/dev visibility only.
     * Invoked by DemoContentSeeder - not by DatabaseSeeder.
     * Uses text-only rows when orphan media files are absent.
     */
    public function run(): void
    {
        if (app()->environment(['production', 'prod', 'uat', 'staging'])) {
            throw new \RuntimeException(
                'FeedbackSeeder refused: demo feedback must not run in production, UAT, or staging.'
            );
        }

        $vendor = User::where('email', 'vendor@cmart.com')->first();
        $userId = $vendor?->id;

        $mediaFiles = $this->discoverFeedbackMediaFiles();

        $demos = [
            [
                'comments' => 'Great atmosphere every weekend. Vendors are friendly and parking is easy to find near the main entrance.',
                'participation_type' => 'visitor_shopper',
                'community_backgrounds' => ['changlun_resident'],
                'reviewer_role' => 'Visitor / Shopper',
                'rating' => 5,
                'service_rating' => 5,
                'value_rating' => 5,
                'media_index' => 0,
            ],
            [
                'comments' => 'Booking a booth through the portal saved me a lot of time. Staff approval was quick and the process felt smooth.',
                'participation_type' => 'vendor',
                'community_backgrounds' => ['outside_changlun'],
                'reviewer_role' => 'Vendor',
                'rating' => 5,
                'service_rating' => 5,
                'value_rating' => 4,
                'media_index' => 1,
            ],
            [
                'comments' => 'Love bringing friends here after class. Plenty of food options and good prices for students on a budget.',
                'participation_type' => 'visitor_shopper',
                'community_backgrounds' => ['uum_student'],
                'reviewer_role' => 'Visitor / Shopper',
                'rating' => 4,
                'service_rating' => 4,
                'value_rating' => 5,
                'media_index' => 2,
            ],
            [
                'comments' => 'Our family visits almost every month. Clean layout, helpful security, and a nice community vibe overall.',
                'participation_type' => 'visitor_shopper',
                'community_backgrounds' => ['changlun_resident'],
                'reviewer_role' => 'Visitor / Shopper',
                'rating' => 5,
                'service_rating' => 5,
                'value_rating' => 5,
                'media_index' => 3,
            ],
            [
                'comments' => 'Mega carboot day was well organized with clear signage. Will definitely come again for preloved finds.',
                'participation_type' => 'visitor_shopper',
                'community_backgrounds' => ['outside_changlun'],
                'reviewer_role' => 'Visitor / Shopper',
                'rating' => 4,
                'service_rating' => 4,
                'value_rating' => 4,
                'media_index' => 4,
            ],
        ];

        foreach ($demos as $demo) {
            $mediaPath = $this->resolveMediaPath($mediaFiles, $demo['media_index']);

            Feedback::updateOrCreate(
                ['comments' => $demo['comments']],
                [
                    'user_id' => $userId,
                    'participation_type' => $demo['participation_type'],
                    'community_backgrounds' => $demo['community_backgrounds'],
                    'reviewer_role' => $demo['reviewer_role'],
                    'rating' => $demo['rating'],
                    'service_rating' => $demo['service_rating'],
                    'value_rating' => $demo['value_rating'],
                    'media_path' => $mediaPath,
                    'helpful_count' => 0,
                    'is_hidden' => false,
                ]
            );
        }
    }

    /** @return list<string> Basenames sorted for stable media_index mapping. */
    private function discoverFeedbackMediaFiles(): array
    {
        $disk = Storage::disk('public');
        if (!$disk->exists('feedback_media')) {
            return [];
        }

        $files = collect($disk->files('feedback_media'))
            ->filter(fn (string $path) => preg_match('/\.png$/i', $path))
            ->sort()
            ->values()
            ->all();

        return array_map(fn (string $path) => basename($path), $files);
    }

    private function resolveMediaPath(array $mediaFiles, int $index): ?string
    {
        if (!isset($mediaFiles[$index])) {
            return null;
        }

        $relative = 'feedback_media/' . $mediaFiles[$index];

        return Storage::disk('public')->exists($relative) ? $relative : null;
    }
}
