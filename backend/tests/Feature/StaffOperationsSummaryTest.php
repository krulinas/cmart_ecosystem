<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffOperationsSummaryTest extends TestCase
{
    private const OPERATIONAL_KEYS = [
        'pending_staff_review',
        'needs_revision',
        'payment_proofs_to_check',
        'upcoming_events',
        'feedback_to_review',
    ];

    public function test_staff_can_fetch_operations_summary_with_operational_counts_only(): void
    {
        $staff = User::where('email', 'staff@cmart.com')->first();
        if (!$staff) {
            $this->markTestSkipped('Seeded staff user (staff@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/staff/operations-summary');

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

    public function test_manager_can_fetch_operations_summary(): void
    {
        $manager = User::where('email', 'admin@cmart.com')->first();
        if (!$manager) {
            $this->markTestSkipped('Seeded manager user (admin@cmart.com) not found. Run database seeders.');
        }

        Sanctum::actingAs($manager);

        $this->getJson('/api/staff/operations-summary')
            ->assertOk()
            ->assertJsonStructure(self::OPERATIONAL_KEYS);
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
