<?php

namespace App\Services;

use App\Exceptions\AllocationValidationException;
use App\Models\VendorCategory;
use App\Support\Migrations\CategoryLegacyMapper;

/**
 * Phase 3.7 — strict canonical category resolution for operational writes.
 *
 * Exact label match + approved alias only. No fuzzy or case-insensitive matching.
 */
class VendorCategoryResolver
{
    /**
     * Resolve a category for new operational use (booking, profile, availability filter).
     *
     * @throws AllocationValidationException
     */
    public function resolveForOperationalUse(?int $vendorCategoryId, ?string $legacyLabel): VendorCategory
    {
        $hasId = $vendorCategoryId !== null;
        $trimmedLabel = CategoryLegacyMapper::normalize($legacyLabel);
        $hasLabel = $trimmedLabel !== null && $trimmedLabel !== '';

        if (! $hasId && ! $hasLabel) {
            throw new AllocationValidationException(
                'Please select a selling category first.',
                'CATEGORY_REQUIRED',
            );
        }

        $fromId = $hasId ? $this->resolveActiveById($vendorCategoryId) : null;
        $fromLabel = $hasLabel ? $this->resolveActiveByLabel($trimmedLabel) : null;

        if ($fromId && $fromLabel && (int) $fromId->id !== (int) $fromLabel->id) {
            throw new AllocationValidationException(
                'Category ID and category label do not identify the same category.',
                'CATEGORY_FIELDS_MISMATCH',
            );
        }

        return $fromId ?? $fromLabel;
    }

    /**
     * @throws AllocationValidationException
     */
    public function resolveActiveById(int $id): VendorCategory
    {
        $category = VendorCategory::query()->whereKey($id)->first();

        if (! $category) {
            throw new AllocationValidationException(
                'The selected category was not found.',
                'CATEGORY_NOT_FOUND',
            );
        }

        $this->assertOperationallySelectable($category);

        return $category;
    }

    /**
     * Exact canonical label or approved alias only.
     *
     * @throws AllocationValidationException
     */
    public function resolveActiveByLabel(string $label): VendorCategory
    {
        $normalized = CategoryLegacyMapper::normalize($label);
        if ($normalized === null || $normalized === '') {
            throw new AllocationValidationException(
                'Please select a selling category first.',
                'CATEGORY_REQUIRED',
            );
        }

        $labelToId = VendorCategory::query()
            ->get(['id', 'label'])
            ->mapWithKeys(fn (VendorCategory $c) => [$c->label => (int) $c->id])
            ->all();

        $mapped = CategoryLegacyMapper::resolve($normalized, $labelToId, true);

        if ($mapped['mapping_status'] !== CategoryLegacyMapper::STATUS_MAPPED
            || $mapped['matched_vendor_category_id'] === null
        ) {
            throw new AllocationValidationException(
                'Unknown product category. Select a canonical category.',
                'UNKNOWN_LEGACY_CATEGORY',
            );
        }

        return $this->resolveActiveById((int) $mapped['matched_vendor_category_id']);
    }

    /**
     * Soft resolve for display/migration metadata — does not throw for inactive.
     */
    public function tryResolveById(?int $id): ?VendorCategory
    {
        if ($id === null) {
            return null;
        }

        return VendorCategory::query()->whereKey($id)->first();
    }

    /**
     * Soft resolve recognized legacy string without requiring active status.
     */
    public function tryResolveByLabel(?string $label): ?VendorCategory
    {
        if ($label === null) {
            return null;
        }

        $normalized = CategoryLegacyMapper::normalize($label);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        $labelToId = VendorCategory::query()
            ->get(['id', 'label'])
            ->mapWithKeys(fn (VendorCategory $c) => [$c->label => (int) $c->id])
            ->all();

        $mapped = CategoryLegacyMapper::resolve($normalized, $labelToId, false);

        if ($mapped['mapping_status'] !== CategoryLegacyMapper::STATUS_MAPPED
            || $mapped['matched_vendor_category_id'] === null
        ) {
            return null;
        }

        return VendorCategory::query()->find($mapped['matched_vendor_category_id']);
    }

    /**
     * @throws AllocationValidationException
     */
    public function assertOperationallySelectable(VendorCategory $category): void
    {
        if ($category->archived_at !== null) {
            throw new AllocationValidationException(
                'This category is no longer active.',
                'CATEGORY_ARCHIVED',
            );
        }

        if (! $category->is_active) {
            throw new AllocationValidationException(
                'This category is no longer active.',
                'CATEGORY_INACTIVE',
            );
        }
    }

    /**
     * Vendor-safe projection (no usage counts / organizer metadata).
     *
     * @return array{id: int, slug: string, label: string, description: string|null, display_order: int}
     */
    public function presentSelectable(VendorCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'slug' => $category->slug,
            'label' => $category->label,
            'description' => $category->description,
            'display_order' => (int) $category->display_order,
        ];
    }

    /**
     * Compact category block for booking / availability responses.
     *
     * @return array{id: int, slug: string, label: string}
     */
    public function presentCompact(VendorCategory $category): array
    {
        return [
            'id' => (int) $category->id,
            'slug' => $category->slug,
            'label' => $category->label,
        ];
    }

    /**
     * Active + public categories for vendor selection endpoints.
     *
     * @return list<array{id: int, slug: string, label: string, description: string|null, display_order: int}>
     */
    public function listVendorSelectable(): array
    {
        return VendorCategory::query()
            ->active()
            ->where('is_public', true)
            ->ordered()
            ->get()
            ->map(fn (VendorCategory $c) => $this->presentSelectable($c))
            ->values()
            ->all();
    }
}
