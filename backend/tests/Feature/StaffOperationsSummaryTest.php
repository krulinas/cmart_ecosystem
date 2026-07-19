<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\TracksProvisionedUsers;
use Tests\TestCase;

class StaffOperationsSummaryTest extends TestCase
{
    use TracksProvisionedUsers;

    private const OPERATIONAL_KEYS = [
        'pending_organizer_review',
        'needs_revision',
        'payment_proofs_to_check',
        'upcoming_events',
        'feedback_to_review',
    ];

    protected function tearDown(): void
    {
        $this->cleanupProvisionedUsers();
        parent::tearDown();
    }

    public function test_organizer_can_fetch_operations_summary_with_operational_counts_only(): void
    {
        $organizer = $this->organizer();

        Sanctum::actingAs($organizer);

        $response = $this->getJson('/api/organizer/operations-summary');

        $response->assertOk();
        $response->assertJsonStructure(self::OPERATIONAL_KEYS);

        $payload = $response->json();
        foreach (self::OPERATIONAL_KEYS as $key) {
            $this->assertIsInt($payload[$key]);
        }

        $this->assertArrayNotHasKey('revenue', $payload);
        $this->assertArrayNotHasKey('economic_value', $payload);
        $this->assertArrayNotHasKey('economic_value_rm', $payload);
        $this->assertArrayNotHasKey('audit_logs', $payload);
    }

    public function test_organizer_demo_account_can_fetch_operations_summary(): void
    {
        $organizer = $this->organizer();

        Sanctum::actingAs($organizer);

        $this->getJson('/api/organizer/operations-summary')
            ->assertOk()
            ->assertJsonStructure(self::OPERATIONAL_KEYS);
    }

    public function test_cmart_management_cannot_fetch_operations_summary(): void
    {
        $venue = $this->provisionUser(
            'summary-management@example.test',
            'cmart_management',
            'Summary CMart Management',
        );

        Sanctum::actingAs($venue);

        $this->getJson('/api/organizer/operations-summary')
            ->assertForbidden();
    }

    public function test_vendor_cannot_fetch_operations_summary(): void
    {
        $vendor = $this->provisionUser(
            'summary-vendor@example.test',
            'community',
            'Summary Community Vendor',
        );

        Sanctum::actingAs($vendor);

        $this->getJson('/api/organizer/operations-summary')
            ->assertForbidden();
    }

    public function test_guest_cannot_fetch_operations_summary(): void
    {
        $this->getJson('/api/organizer/operations-summary')
            ->assertUnauthorized();
    }

    private function organizer(): User
    {
        return $this->provisionUser(
            'summary-organizer@example.test',
            'organizer',
            'Summary Organizer',
        );
    }
}
