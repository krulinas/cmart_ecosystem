<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FeedbackModerationTest extends TestCase
{
    private ?Feedback $testFeedback = null;

    protected function tearDown(): void
    {
        if ($this->testFeedback) {
            Feedback::where('id', $this->testFeedback->id)->delete();
            $this->testFeedback = null;
        }

        parent::tearDown();
    }

    private function createTestFeedback(array $overrides = []): Feedback
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user not found. Run database seeders.');
        }

        $this->testFeedback = Feedback::create(array_merge([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Shopper',
            'comments' => 'E2E moderation test feedback with enough words for validation.',
            'rating' => 2,
            'service_rating' => 2,
            'value_rating' => 2,
            'helpful_count' => 0,
            'is_hidden' => false,
        ], $overrides));

        return $this->testFeedback;
    }

    public function test_staff_can_hide_and_unhide_feedback(): void
    {
        $staff = User::where('email', 'staff@cmart.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seeded staff user not found.');
        }

        $feedback = $this->createTestFeedback();

        Sanctum::actingAs($staff);

        $this->putJson("/api/feedbacks/{$feedback->id}", ['is_hidden' => true])
            ->assertOk()
            ->assertJsonPath('feedback.is_hidden', true);

        $this->putJson("/api/feedbacks/{$feedback->id}", ['is_hidden' => false])
            ->assertOk()
            ->assertJsonPath('feedback.is_hidden', false);
    }

    public function test_staff_can_mark_feedback_reviewed(): void
    {
        $staff = User::where('email', 'staff@cmart.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seeded staff user not found.');
        }

        $feedback = $this->createTestFeedback();

        Sanctum::actingAs($staff);

        $this->postJson("/api/feedbacks/{$feedback->id}/reviewed")
            ->assertOk()
            ->assertJsonPath('feedback.reviewed_by', $staff->id)
            ->assertJsonPath('feedback.reviewed_by_name', $staff->name);

        $this->assertNotNull($this->testFeedback->fresh()->reviewed_at);
    }

    public function test_staff_cannot_delete_feedback(): void
    {
        $staff = User::where('email', 'staff@cmart.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seeded staff user not found.');
        }

        $feedback = $this->createTestFeedback();

        Sanctum::actingAs($staff);

        $this->deleteJson("/api/feedbacks/{$feedback->id}")
            ->assertForbidden();

        $this->assertNotNull(Feedback::find($feedback->id));
    }

    public function test_manager_can_delete_feedback(): void
    {
        $manager = User::where('email', 'admin@cmart.com')->first();
        if (!$manager) {
            $this->markTestSkipped('Seeded manager user not found.');
        }

        $feedback = $this->createTestFeedback();

        Sanctum::actingAs($manager);

        $this->deleteJson("/api/feedbacks/{$feedback->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->testFeedback = null;
        $this->assertNull(Feedback::find($feedback->id));
    }

    public function test_public_endpoint_does_not_show_hidden_feedback(): void
    {
        $feedback = $this->createTestFeedback(['is_hidden' => true]);

        $response = $this->getJson('/api/feedbacks');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($feedback->id));
    }

    public function test_public_endpoint_only_shows_published_official_reply(): void
    {
        $manager = User::where('email', 'admin@cmart.com')->first();
        if (!$manager) {
            $this->markTestSkipped('Seeded manager user not found.');
        }

        $feedback = $this->createTestFeedback([
            'official_reply_text' => 'Draft reply should not appear publicly.',
            'official_reply_status' => 'draft',
            'official_reply_by' => $manager->id,
        ]);

        $draftResponse = $this->getJson('/api/feedbacks');
        $draftResponse->assertOk();
        $draftRow = collect($draftResponse->json('data'))->firstWhere('id', $feedback->id);
        $this->assertNotNull($draftRow);
        $this->assertNull($draftRow['official_reply']);

        $feedback->update([
            'official_reply_status' => 'published',
            'official_reply_published_at' => now(),
        ]);

        $publishedResponse = $this->getJson('/api/feedbacks');
        $publishedResponse->assertOk();
        $publishedRow = collect($publishedResponse->json('data'))->firstWhere('id', $feedback->id);
        $this->assertNotNull($publishedRow);
        $this->assertSame('Draft reply should not appear publicly.', $publishedRow['official_reply']['text']);
        $this->assertArrayNotHasKey('status', $publishedRow['official_reply']);
    }

    public function test_public_endpoint_supports_rating_filter_and_summary(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user not found.');
        }

        $high = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Shopper',
            'comments' => 'Excellent carboot weekend with friendly vendors and great finds.',
            'rating' => 5,
            'service_rating' => 5,
            'value_rating' => 5,
            'helpful_count' => 0,
            'is_hidden' => false,
        ]);

        $low = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Vendor',
            'comments' => 'Parking was difficult during peak hours on Saturday morning.',
            'rating' => 2,
            'service_rating' => 2,
            'value_rating' => 2,
            'helpful_count' => 0,
            'is_hidden' => false,
        ]);

        $response = $this->getJson('/api/feedbacks?rating=5');

        $response->assertOk()
            ->assertJsonStructure(['summary' => ['average_rating', 'total_reviews', 'distribution']]);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($high->id));
        $this->assertFalse($ids->contains($low->id));

        Feedback::whereIn('id', [$high->id, $low->id])->delete();
    }

    public function test_public_endpoint_search_matches_comment_text(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user not found.');
        }

        $match = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Local Resident',
            'comments' => 'UniqueSearchPhrase for public review explorer test case.',
            'rating' => 4,
            'service_rating' => 4,
            'value_rating' => 4,
            'helpful_count' => 0,
            'is_hidden' => false,
        ]);

        $this->getJson('/api/feedbacks?search=UniqueSearchPhrase')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $match->id);

        Feedback::where('id', $match->id)->delete();
    }

    public function test_public_sort_is_stable_for_same_timestamp_rows(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user not found.');
        }

        // Two rows sharing an identical created_at — id must break the tie.
        $sharedTime = now();

        $first = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Shopper',
            'comments' => 'StableSortPhrase earlier row for deterministic ordering test.',
            'rating' => 4,
            'service_rating' => 4,
            'value_rating' => 4,
            'helpful_count' => 0,
            'is_hidden' => false,
            'created_at' => $sharedTime,
            'updated_at' => $sharedTime,
        ]);

        $second = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Vendor',
            'comments' => 'StableSortPhrase later row for deterministic ordering test.',
            'rating' => 4,
            'service_rating' => 4,
            'value_rating' => 4,
            'helpful_count' => 0,
            'is_hidden' => false,
            'created_at' => $sharedTime,
            'updated_at' => $sharedTime,
        ]);

        // Newest: higher id first.
        $newest = collect($this->getJson('/api/feedbacks?search=StableSortPhrase&sort=newest')->json('data'))
            ->pluck('id')->values()->all();
        $this->assertSame([$second->id, $first->id], $newest);

        // Oldest: lower id first.
        $oldest = collect($this->getJson('/api/feedbacks?search=StableSortPhrase&sort=oldest')->json('data'))
            ->pluck('id')->values()->all();
        $this->assertSame([$first->id, $second->id], $oldest);

        Feedback::whereIn('id', [$first->id, $second->id])->delete();
    }

    public function test_public_highest_and_lowest_rating_sort(): void
    {
        $vendor = User::where('email', 'vendor@cmart.com')->first();
        if (!$vendor) {
            $this->markTestSkipped('Seeded vendor user not found.');
        }

        $low = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Shopper',
            'comments' => 'RatingSortPhrase low rated row for ordering verification.',
            'rating' => 1,
            'service_rating' => 1,
            'value_rating' => 1,
            'helpful_count' => 0,
            'is_hidden' => false,
        ]);

        $high = Feedback::create([
            'user_id' => $vendor->id,
            'reviewer_role' => 'Vendor',
            'comments' => 'RatingSortPhrase high rated row for ordering verification.',
            'rating' => 5,
            'service_rating' => 5,
            'value_rating' => 5,
            'helpful_count' => 0,
            'is_hidden' => false,
        ]);

        $highest = collect($this->getJson('/api/feedbacks?search=RatingSortPhrase&sort=highest_rating')->json('data'))
            ->pluck('id')->values()->all();
        $this->assertSame([$high->id, $low->id], $highest);

        $lowest = collect($this->getJson('/api/feedbacks?search=RatingSortPhrase&sort=lowest_rating')->json('data'))
            ->pluck('id')->values()->all();
        $this->assertSame([$low->id, $high->id], $lowest);

        Feedback::whereIn('id', [$low->id, $high->id])->delete();
    }
}
