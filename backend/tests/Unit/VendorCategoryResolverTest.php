<?php

namespace Tests\Unit;

use App\Exceptions\AllocationValidationException;
use App\Models\VendorCategory;
use App\Services\VendorCategoryResolver;
use Tests\TestCase;

class VendorCategoryResolverTest extends TestCase
{
    private VendorCategoryResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(VendorCategoryResolver::class);
    }

    public function test_resolves_by_canonical_id(): void
    {
        $food = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
        $resolved = $this->resolver->resolveActiveById($food->id);
        $this->assertSame($food->id, $resolved->id);
    }

    public function test_resolves_exact_canonical_label(): void
    {
        $resolved = $this->resolver->resolveActiveByLabel('Food & Beverages');
        $this->assertSame('food-beverages', $resolved->slug);
    }

    public function test_resolves_others_alias_to_mixed_others(): void
    {
        $resolved = $this->resolver->resolveActiveByLabel('Others');
        $this->assertSame('mixed-others', $resolved->slug);
        $this->assertSame('Mixed / Others', $resolved->label);
    }

    public function test_rejects_food_and_drinks(): void
    {
        $this->expectException(AllocationValidationException::class);
        try {
            $this->resolver->resolveActiveByLabel('Food & Drinks');
        } catch (AllocationValidationException $e) {
            $this->assertSame('UNKNOWN_LEGACY_CATEGORY', $e->error);
            throw $e;
        }
    }

    public function test_rejects_case_only_difference(): void
    {
        $this->expectException(AllocationValidationException::class);
        try {
            $this->resolver->resolveActiveByLabel('food & beverages');
        } catch (AllocationValidationException $e) {
            $this->assertSame('UNKNOWN_LEGACY_CATEGORY', $e->error);
            throw $e;
        }
    }

    public function test_detects_id_string_mismatch(): void
    {
        $food = VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
        $this->expectException(AllocationValidationException::class);
        try {
            $this->resolver->resolveForOperationalUse($food->id, 'Clothing & Apparel');
        } catch (AllocationValidationException $e) {
            $this->assertSame('CATEGORY_FIELDS_MISMATCH', $e->error);
            throw $e;
        }
    }

    public function test_rejects_inactive_category(): void
    {
        $category = VendorCategory::query()->where('slug', 'household-items')->firstOrFail();
        $original = $category->is_active;
        $category->forceFill(['is_active' => false])->save();

        try {
            $this->expectException(AllocationValidationException::class);
            try {
                $this->resolver->resolveActiveById($category->id);
            } catch (AllocationValidationException $e) {
                $this->assertSame('CATEGORY_INACTIVE', $e->error);
                throw $e;
            }
        } finally {
            $category->forceFill(['is_active' => $original])->save();
        }
    }

    public function test_rejects_archived_category(): void
    {
        $category = VendorCategory::query()->where('slug', 'electronics-gadgets')->firstOrFail();
        $category->forceFill(['archived_at' => now()])->save();

        try {
            $this->expectException(AllocationValidationException::class);
            try {
                $this->resolver->resolveActiveById($category->id);
            } catch (AllocationValidationException $e) {
                $this->assertSame('CATEGORY_ARCHIVED', $e->error);
                throw $e;
            }
        } finally {
            $category->forceFill(['archived_at' => null])->save();
        }
    }

    public function test_list_vendor_selectable_returns_seven_public_active(): void
    {
        $list = $this->resolver->listVendorSelectable();
        $this->assertCount(7, $list);
        $this->assertSame('Pre-loved / Thrift', $list[0]['label']);
        $this->assertSame('Mixed / Others', $list[6]['label']);
        foreach ($list as $row) {
            $this->assertArrayHasKey('id', $row);
            $this->assertArrayHasKey('slug', $row);
            $this->assertArrayNotHasKey('usage', $row);
            $this->assertArrayNotHasKey('is_active', $row);
        }
    }
}
