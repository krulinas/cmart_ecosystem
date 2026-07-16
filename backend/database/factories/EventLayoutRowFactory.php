<?php

namespace Database\Factories;

use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Models\VendorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventLayoutRow>
 */
class EventLayoutRowFactory extends Factory
{
    protected $model = EventLayoutRow::class;

    public function definition(): array
    {
        $label = 'Row '.strtoupper(fake()->unique()->lexify('?'));

        return [
            'carboot_event_id' => null, // set via forEvent()
            'vendor_category_id' => null,
            'label' => $label,
            'slug' => Str::slug($label),
            'description' => null,
            'display_order' => fake()->numberBetween(1, 50),
            'is_active' => true,
            'is_public' => true,
            'created_by' => null,
            'updated_by' => null,
            'archived_at' => null,
        ];
    }

    public function forEvent(CarbootEvent $event): static
    {
        return $this->state(fn () => [
            'carboot_event_id' => $event->id,
        ]);
    }

    public function withCategory(?VendorCategory $category = null): static
    {
        return $this->state(fn () => [
            'vendor_category_id' => $category?->id ?? VendorCategory::query()->ordered()->value('id'),
        ]);
    }
}
