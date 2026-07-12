<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffOperationsSummaryTest extends TestCase
{
    private const OPERATIONAL_KEYS = [
        'pending_organizer_review',
        'needs_revision',
        'payment_proofs_to_check',
        'upcoming_events',
        'feedback_to_review',
    ];

    public function test_organizer_can_fetch_operations_summary_with_operational_counts_only(): void
    {
        $organizer = User::where('email', 'admin@cmart.com')->first();
        if (!$organizer) {
            $this->markTestSkipped('Seeded organizer user (admin@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($organizer);

        $response = $this->getJson('/api/staff/operations-summary');

        $response->assertOk();
        $response->assertJsonStructure(array_merge(self::OPERATIONAL_KEYS, ['pending_staff_review']));

        $payload = $response->json();
        foreach (self::OPERATIONAL_KEYS as $key) {
            $this->assertIsInt($payload[$key]);
        }

        $this->assertSame($payload['pending_organizer_review'], $payload['pending_staff_review']);

        $this->assertArrayNotHasKey('revenue', $payload);
        $this->assertArrayNotHasKey('economic_value', $payload);
        $this->assertArrayNotHasKey('economic_value_rm', $payload);
        $this->assertArrayNotHasKey('audit_logs', $payload);
    }

    public function test_organizer_demo_account_can_fetch_operations_summary(): void
    {
        $organizer = User::where('email', 'organizer@cmart.com')->first();
        if (!$organizer) {
            $this->markTestSkipped('Seeded organizer user (organizer@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($organizer);

        $this->getJson('/api/staff/operations-summary')
            ->assertOk()
            ->assertJsonStructure(self::OPERATIONAL_KEYS);
    }

    public function test_cmart_management_cannot_fetch_operations_summary(): void
    {
        $venue = User::where('email', 'staff@cmart.com')->first();
        if (!$venue) {
            $this->markTestSkipped('Seeded cmart_management demo (staff@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($venue);

        $this->getJson('/api/staff/operations-summary')
            ->assertForbidden();
    }

    public function test_vendor_cannot_fetch_operations_summary(): void
    {
        $vendor = User::where('role', 'community')->first();
        if (!$vendor) {
            $this->markTestSkipped('No community vendor user found in database.');
        }

        Sanctum::actingAs($vendor);

        $this->getJson('/api/staff/operations-summary')
            ->assertForbidden();
    }

    public function test_guest_cannot_fetch_operations_summary(): void
    {
        $this->getJson('/api/staff/operations-summary')
            ->assertUnauthorized();
    }
}
