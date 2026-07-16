<?php

namespace Database\Factories;

use App\Models\VendorCategory;
use App\Support\Migrations\CategoryLegacyMapper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorCategory>
 */
class VendorCategoryFactory extends Factory
{
    protected $model = VendorCategory::class;

    public function definition(): array
    {
        $index = fake()->unique()->numberBetween(1000, 999999);

        return [
            'slug' => 'factory-category-'.$index,
            'label' => 'Factory Category '.$index,
            'description' => null,
            'display_order' => $index,
            'is_active' => true,
            'is_public' => true,
            'archived_at' => null,
        ];
    }

    public function canonical(string $slug): static
    {
        $canonical = collect(CategoryLegacyMapper::canonicalCategories())
            ->firstWhere('slug', $slug);

        if (! $canonical) {
            throw new \InvalidArgumentException("Unknown canonical slug: {$slug}");
        }

        return $this->state(fn () => [
            'slug' => $canonical['slug'],
            'label' => $canonical['label'],
            'description' => $canonical['description'],
            'display_order' => $canonical['display_order'],
            'is_active' => true,
            'is_public' => true,
            'archived_at' => null,
        ]);
    }
}
