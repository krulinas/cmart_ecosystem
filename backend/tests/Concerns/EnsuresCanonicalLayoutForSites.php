<?php

namespace Tests\Concerns;

use App\Models\CarbootEvent;
use App\Models\EventLayoutRow;
use App\Models\EventSite;
use App\Models\VendorCategory;

/**
 * Phase 3.7 — attach canonical layout rows so POST /api/bookings fixtures remain valid.
 */
trait EnsuresCanonicalLayoutForSites
{
    protected function foodVendorCategory(): VendorCategory
    {
        return VendorCategory::query()->where('slug', 'food-beverages')->firstOrFail();
    }

    protected function ensureFoodLayoutRow(CarbootEvent $event, string $rowLabel = 'A'): EventLayoutRow
    {
        $category = $this->foodVendorCategory();

        return EventLayoutRow::query()->firstOrCreate(
            [
                'carboot_event_id' => $event->id,
                'label' => $rowLabel,
            ],
            [
                'vendor_category_id' => $category->id,
                'slug' => strtolower(preg_replace('/\s+/', '-', $rowLabel)) . '-' . $event->id,
                'display_order' => max(1, ord(strtoupper($rowLabel[0] ?? 'A')) - 64),
                'is_active' => true,
                'is_public' => true,
            ],
        );
    }

    protected function attachSiteToFoodLayout(CarbootEvent $event, EventSite $site, ?string $rowLabel = null): EventSite
    {
        $label = $rowLabel ?? ($site->row_label ?: 'A');
        $row = $this->ensureFoodLayoutRow($event, $label);
        $site->forceFill(['event_layout_row_id' => $row->id])->save();

        return $site->fresh();
    }
}
