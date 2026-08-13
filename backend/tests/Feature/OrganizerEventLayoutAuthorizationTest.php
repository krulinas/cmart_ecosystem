<?php

namespace Tests\Feature;

use App\Models\EventSite;
use App\Models\User;
use App\Models\VendorCategory;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CleansUpTestFixtures;
use Tests\Concerns\Phase35EventLayoutFixtures;
use Tests\TestCase;

class OrganizerEventLayoutAuthorizationTest extends TestCase
{
    use CleansUpTestFixtures;
    use Phase35EventLayoutFixtures;

    protected function tearDown(): void
    {
        $this->cleanupTrackedFixtures();
        parent::tearDown();
    }

    private function createUser(string $role): User
    {
        return $this->trackUser(User::create([
            'name' => 'Phase35 ' . $role . ' ' . uniqid(),
            'email' => 'p35-' . $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'vendor_status' => 'none',
        ]));
    }

    /**
     * @return array<string, int>
     */
    private function roleExpectations(): array
    {
        return [
            'organizer' => 200,
            'super_admin' => 200,
            'cmart_management' => 403,
            'community' => 403,
        ];
    }

    public function test_layout_read_authorization_by_role(): void
    {
        $event = $this->createEvent();

        $this->getJson("/api/organizer/events/{$event->id}/layout")
            ->assertUnauthorized();

        foreach ($this->roleExpectations() as $role => $expectedStatus) {
            Sanctum::actingAs($this->createUser($role));

            $this->getJson("/api/organizer/events/{$event->id}/layout")
                ->assertStatus($expectedStatus);
        }
    }

    public function test_layout_row_create_authorization_by_role(): void
    {
        $event = $this->createEvent();
        $category = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();

        $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
            'label' => 'A',
            'vendor_category_id' => $category->id,
        ])->assertUnauthorized();

        $allowedLabels = [
            'organizer' => 'A',
            'super_admin' => 'B',
        ];

        foreach ($this->roleExpectations() as $role => $expectedStatus) {
            Sanctum::actingAs($this->createUser($role));

            $response = $this->postJson("/api/organizer/events/{$event->id}/layout/rows", [
                'label' => $allowedLabels[$role] ?? 'C',
                'vendor_category_id' => $category->id,
            ]);

            if (in_array($role, ['organizer', 'super_admin'], true)) {
                $response->assertCreated();
                $rowId = (int) $response->json('row.id');
                $siteIds = EventSite::query()
                    ->forEvent($event->id)
                    ->where('event_layout_row_id', $rowId)
                    ->pluck('id')
                    ->all();
                $this->createdSiteIds = array_merge($this->createdSiteIds, $siteIds);
            } else {
                $response->assertStatus($expectedStatus);
            }
        }
    }
}
