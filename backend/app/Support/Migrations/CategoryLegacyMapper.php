<?php

namespace App\Support\Migrations;

/**
 * Phase 3.4 — exact legacy category normalization and approved mapping.
 *
 * No fuzzy matching. Unknown values remain unresolved.
 */
class CategoryLegacyMapper
{
    public const BACKFILL_VERSION = 'phase_3_4_v1';

    public const STATUS_MAPPED = 'mapped';

    public const STATUS_UNRESOLVED = 'unresolved';

    public const STATUS_SKIPPED_NULL = 'skipped_null';

    public const REASON_EXACT_MATCH = 'exact_match';

    public const REASON_APPROVED_ALIAS = 'approved_alias';

    public const REASON_UNKNOWN_VALUE = 'unknown_value';

    public const REASON_EMPTY_REQUIRED = 'empty_required_value';

    public const REASON_NULL_OPTIONAL = 'null_optional_value';

    /** Stable SHA-256 input when normalized_value is SQL NULL. */
    public const NULL_HASH_SENTINEL = '__CATEGORY_NULL__';

    /**
     * Deterministic SHA-256 of the normalized category value (or null sentinel).
     */
    public static function normalizedValueHash(?string $normalizedValue): string
    {
        $input = $normalizedValue === null ? self::NULL_HASH_SENTINEL : $normalizedValue;

        return hash('sha256', $input);
    }

    /**
     * Canonical MVP taxonomy seed rows.
     *
     * @return list<array{slug: string, label: string, display_order: int, description: null, is_active: bool, is_public: bool}>
     */
    public static function canonicalCategories(): array
    {
        return [
            [
                'slug' => 'pre-loved-thrift',
                'label' => 'Pre-loved / Thrift',
                'description' => null,
                'display_order' => 1,
                'is_active' => true,
                'is_public' => true,
            ],
            [
                'slug' => 'food-beverages',
                'label' => 'Food & Beverages',
                'description' => null,
                'display_order' => 2,
                'is_active' => true,
                'is_public' => true,
            ],
            [
                'slug' => 'clothing-apparel',
                'label' => 'Clothing & Apparel',
                'description' => null,
                'display_order' => 3,
                'is_active' => true,
                'is_public' => true,
            ],
            [
                'slug' => 'handicrafts-art',
                'label' => 'Handicrafts & Art',
                'description' => null,
                'display_order' => 4,
                'is_active' => true,
                'is_public' => true,
            ],
            [
                'slug' => 'electronics-gadgets',
                'label' => 'Electronics & Gadgets',
                'description' => null,
                'display_order' => 5,
                'is_active' => true,
                'is_public' => true,
            ],
            [
                'slug' => 'household-items',
                'label' => 'Household Items',
                'description' => null,
                'display_order' => 6,
                'is_active' => true,
                'is_public' => true,
            ],
            [
                'slug' => 'mixed-others',
                'label' => 'Mixed / Others',
                'description' => null,
                'display_order' => 7,
                'is_active' => true,
                'is_public' => true,
            ],
        ];
    }

    /**
     * Approved alias labels → canonical label.
     *
     * @return array<string, string>
     */
    public static function approvedAliases(): array
    {
        return [
            'Others' => 'Mixed / Others',
        ];
    }

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return preg_replace('/\s+/u', ' ', $trimmed) ?? $trimmed;
    }

    /**
     * @param  array<string, int>  $labelToId  canonical label => id
     * @return array{
     *   mapping_status: string,
     *   reason_code: string,
     *   normalized_value: string|null,
     *   matched_vendor_category_id: int|null,
     *   matched_label: string|null
     * }
     */
    public static function resolve(?string $originalValue, array $labelToId, bool $required = false): array
    {
        if ($originalValue === null) {
            return [
                'mapping_status' => self::STATUS_SKIPPED_NULL,
                'reason_code' => self::REASON_NULL_OPTIONAL,
                'normalized_value' => null,
                'matched_vendor_category_id' => null,
                'matched_label' => null,
            ];
        }

        $normalized = self::normalize($originalValue);

        if ($normalized === '') {
            return [
                'mapping_status' => $required ? self::STATUS_UNRESOLVED : self::STATUS_SKIPPED_NULL,
                'reason_code' => self::REASON_EMPTY_REQUIRED,
                'normalized_value' => '',
                'matched_vendor_category_id' => null,
                'matched_label' => null,
            ];
        }

        $aliases = self::approvedAliases();
        if (isset($aliases[$normalized])) {
            $canonicalLabel = $aliases[$normalized];
            $id = $labelToId[$canonicalLabel] ?? null;

            return [
                'mapping_status' => $id ? self::STATUS_MAPPED : self::STATUS_UNRESOLVED,
                'reason_code' => self::REASON_APPROVED_ALIAS,
                'normalized_value' => $normalized,
                'matched_vendor_category_id' => $id,
                'matched_label' => $id ? $canonicalLabel : null,
            ];
        }

        if (isset($labelToId[$normalized])) {
            return [
                'mapping_status' => self::STATUS_MAPPED,
                'reason_code' => self::REASON_EXACT_MATCH,
                'normalized_value' => $normalized,
                'matched_vendor_category_id' => $labelToId[$normalized],
                'matched_label' => $normalized,
            ];
        }

        return [
            'mapping_status' => self::STATUS_UNRESOLVED,
            'reason_code' => self::REASON_UNKNOWN_VALUE,
            'normalized_value' => $normalized,
            'matched_vendor_category_id' => null,
            'matched_label' => null,
        ];
    }
}
