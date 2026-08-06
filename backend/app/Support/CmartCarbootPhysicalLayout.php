<?php

namespace App\Support;

/**
 * Interim authoritative physical layout for CMart Changlun Carboot parking.
 *
 * Phase 1 stand-in for a future venue-template table. Do not duplicate these
 * constants elsewhere — import this class.
 */
final class CmartCarbootPhysicalLayout
{
    public const ROW_LABELS = ['A', 'B', 'C', 'D'];

    public const SITES_PER_ROW = 16;

    public const TEMPLATE_KEY = 'standard_parking_4x16';

    public static function physicalSiteCapacity(): int
    {
        return count(self::ROW_LABELS) * self::SITES_PER_ROW;
    }

    public static function isAllowedRowLabel(string $label): bool
    {
        return in_array(strtoupper(trim($label)), self::ROW_LABELS, true);
    }

    public static function normalizeRowLabel(string $label): string
    {
        return strtoupper(trim($label));
    }

    /**
     * @return list<string>
     */
    public static function unusedRowLabels(array $existingLabels): array
    {
        $used = [];
        foreach ($existingLabels as $label) {
            $normalized = self::normalizeRowLabel((string) $label);
            if (self::isAllowedRowLabel($normalized)) {
                $used[$normalized] = true;
            }
        }

        return array_values(array_filter(
            self::ROW_LABELS,
            fn (string $label) => ! isset($used[$label]),
        ));
    }

    public static function allTemplateRowsPresent(array $existingLabels): bool
    {
        return self::unusedRowLabels($existingLabels) === [];
    }

    /**
     * Grid row index (1-based) for a template row label.
     */
    public static function gridRowForLabel(string $label): int
    {
        $normalized = self::normalizeRowLabel($label);
        $index = array_search($normalized, self::ROW_LABELS, true);
        if ($index === false) {
            return 1;
        }

        return $index + 1;
    }
}
