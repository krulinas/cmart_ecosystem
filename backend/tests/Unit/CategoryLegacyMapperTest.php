<?php

namespace Tests\Unit;

use App\Support\Migrations\CategoryLegacyMapper;
use PHPUnit\Framework\TestCase;

class CategoryLegacyMapperTest extends TestCase
{
    /** @var array<string, int> */
    private array $labelToId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->labelToId = [];
        foreach (CategoryLegacyMapper::canonicalCategories() as $index => $category) {
            $this->labelToId[$category['label']] = $index + 1;
        }
    }

    public function test_canonical_taxonomy_has_exactly_seven_categories(): void
    {
        $categories = CategoryLegacyMapper::canonicalCategories();

        $this->assertCount(7, $categories);
        $this->assertSame(
            [
                'pre-loved-thrift',
                'food-beverages',
                'clothing-apparel',
                'handicrafts-art',
                'electronics-gadgets',
                'household-items',
                'mixed-others',
            ],
            array_column($categories, 'slug'),
        );
        $this->assertSame(
            [
                'Pre-loved / Thrift',
                'Food & Beverages',
                'Clothing & Apparel',
                'Handicrafts & Art',
                'Electronics & Gadgets',
                'Household Items',
                'Mixed / Others',
            ],
            array_column($categories, 'label'),
        );
        $this->assertNotContains('Others', array_column($categories, 'label'));
        $this->assertNotContains('Food & Drinks', array_column($categories, 'label'));
        $this->assertNotContains('Preloved Clothes', array_column($categories, 'label'));
    }

    public function test_exact_legacy_value_maps(): void
    {
        $resolved = CategoryLegacyMapper::resolve('Food & Beverages', $this->labelToId, true);

        $this->assertSame(CategoryLegacyMapper::STATUS_MAPPED, $resolved['mapping_status']);
        $this->assertSame(CategoryLegacyMapper::REASON_EXACT_MATCH, $resolved['reason_code']);
        $this->assertSame('Food & Beverages', $resolved['matched_label']);
    }

    public function test_others_alias_maps_to_mixed_others(): void
    {
        $resolved = CategoryLegacyMapper::resolve('Others', $this->labelToId, true);

        $this->assertSame(CategoryLegacyMapper::STATUS_MAPPED, $resolved['mapping_status']);
        $this->assertSame(CategoryLegacyMapper::REASON_APPROVED_ALIAS, $resolved['reason_code']);
        $this->assertSame('Mixed / Others', $resolved['matched_label']);
        $this->assertSame($this->labelToId['Mixed / Others'], $resolved['matched_vendor_category_id']);
    }

    public function test_mixed_others_and_household_items_map_exactly(): void
    {
        $mixed = CategoryLegacyMapper::resolve('Mixed / Others', $this->labelToId, true);
        $household = CategoryLegacyMapper::resolve('Household Items', $this->labelToId, true);

        $this->assertSame(CategoryLegacyMapper::STATUS_MAPPED, $mixed['mapping_status']);
        $this->assertSame(CategoryLegacyMapper::STATUS_MAPPED, $household['mapping_status']);
        $this->assertSame('Mixed / Others', $mixed['matched_label']);
        $this->assertSame('Household Items', $household['matched_label']);
    }

    public function test_whitespace_normalization(): void
    {
        $resolved = CategoryLegacyMapper::resolve("  Food   &   Beverages  ", $this->labelToId, true);

        $this->assertSame(CategoryLegacyMapper::STATUS_MAPPED, $resolved['mapping_status']);
        $this->assertSame('Food & Beverages', $resolved['normalized_value']);
    }

    public function test_case_difference_does_not_auto_map(): void
    {
        $resolved = CategoryLegacyMapper::resolve('food & beverages', $this->labelToId, true);

        $this->assertSame(CategoryLegacyMapper::STATUS_UNRESOLVED, $resolved['mapping_status']);
        $this->assertSame(CategoryLegacyMapper::REASON_UNKNOWN_VALUE, $resolved['reason_code']);
        $this->assertNull($resolved['matched_vendor_category_id']);
    }

    public function test_punctuation_difference_does_not_auto_map(): void
    {
        $resolved = CategoryLegacyMapper::resolve('Food and Beverages', $this->labelToId, true);

        $this->assertSame(CategoryLegacyMapper::STATUS_UNRESOLVED, $resolved['mapping_status']);
        $this->assertNull($resolved['matched_vendor_category_id']);
    }

    public function test_stakeholder_wording_is_not_auto_mapped(): void
    {
        foreach (['Food & Drinks', 'Preloved Clothes', 'Thrift', 'Clothes', 'Mixed'] as $value) {
            $resolved = CategoryLegacyMapper::resolve($value, $this->labelToId, true);
            $this->assertSame(CategoryLegacyMapper::STATUS_UNRESOLVED, $resolved['mapping_status'], $value);
            $this->assertNull($resolved['matched_vendor_category_id'], $value);
        }
    }

    public function test_null_optional_is_skipped(): void
    {
        $resolved = CategoryLegacyMapper::resolve(null, $this->labelToId, false);

        $this->assertSame(CategoryLegacyMapper::STATUS_SKIPPED_NULL, $resolved['mapping_status']);
        $this->assertSame(CategoryLegacyMapper::REASON_NULL_OPTIONAL, $resolved['reason_code']);
    }

    public function test_normalized_value_hash_is_deterministic_sha256(): void
    {
        $hash = CategoryLegacyMapper::normalizedValueHash('Food & Beverages');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', 'Food & Beverages'), $hash);
        $this->assertSame($hash, CategoryLegacyMapper::normalizedValueHash('Food & Beverages'));
    }

    public function test_null_normalized_value_uses_stable_sentinel_hash(): void
    {
        $hash = CategoryLegacyMapper::normalizedValueHash(null);

        $this->assertSame(
            hash('sha256', CategoryLegacyMapper::NULL_HASH_SENTINEL),
            $hash,
        );
        $this->assertNotSame(
            CategoryLegacyMapper::normalizedValueHash(''),
            $hash,
        );
    }
}
