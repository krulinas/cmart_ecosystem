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

    public static function siteLabelFor(string $rowLabel, int $position): string
    {
        $row = self::normalizeRowLabel($rowLabel);

        return $row.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    public static function expectedSiteLabelsForRow(string $rowLabel): array
    {
        $row = self::normalizeRowLabel($rowLabel);
        if (! self::isAllowedRowLabel($row)) {
            return [];
        }

        $labels = [];
        for ($position = 1; $position <= self::SITES_PER_ROW; $position++) {
            $labels[] = self::siteLabelFor($row, $position);
        }

        return $labels;
    }

    /**
     * @return array{row: string, position: int}|null
     */
    public static function parseCanonicalSiteLabel(string $label): ?array
    {
        $normalized = strtoupper(trim($label));
        if (! preg_match('/^([A-D])(0[1-9]|1[0-6])$/', $normalized, $matches)) {
            return null;
        }

        return [
            'row' => $matches[1],
            'position' => (int) $matches[2],
        ];
    }

    public static function isCanonicalSiteLabel(string $label): bool
    {
        return self::parseCanonicalSiteLabel($label) !== null;
    }

    /**
     * Exact missing canonical labels for a physical row, in numeric order.
     *
     * Does not repair duplicates/invalid labels — callers keep those as readiness issues.
     *
     * @param  list<string>  $existingLabels
     * @return list<string>
     */
    public static function missingSiteLabels(string $rowLabel, array $existingLabels): array
    {
        $row = self::normalizeRowLabel($rowLabel);
        if (! self::isAllowedRowLabel($row)) {
            return [];
        }

        $present = [];
        foreach ($existingLabels as $label) {
            $parsed = self::parseCanonicalSiteLabel((string) $label);
            if ($parsed === null || $parsed['row'] !== $row) {
                continue;
            }
            $present[self::siteLabelFor($row, $parsed['position'])] = true;
        }

        return array_values(array_filter(
            self::expectedSiteLabelsForRow($row),
            fn (string $expected) => ! isset($present[$expected]),
        ));
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
