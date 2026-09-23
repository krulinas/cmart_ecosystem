<?php

namespace App\Support;

/**
 * Compare Post-Event snapshots excluding volatile timestamps.
 */
final class PostEventReportSnapshotCompare
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function metricsChanged(?array $before, ?array $after): bool
    {
        return self::canonical($before) !== self::canonical($after);
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public static function canonical(?array $snapshot): string
    {
        if (! is_array($snapshot)) {
            return '';
        }

        $copy = $snapshot;
        self::stripTimestamps($copy);

        return json_encode(self::ksortRecursive($copy), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function stripTimestamps(array &$node): void
    {
        foreach (['generated_at', 'generated_at_display', 'data_cut_off'] as $key) {
            unset($node[$key]);
        }

        if (isset($node['methodology']) && is_array($node['methodology'])) {
            unset($node['methodology']['data_cut_off']);
        }

        if (isset($node['programme']) && is_array($node['programme'])) {
            // Programme narrative text is frozen from editor columns; metric change detection
            // should focus on analytics sections. Still include programme.details numeric fields.
            if (isset($node['programme']['details']) && is_array($node['programme']['details'])) {
                unset($node['programme']['details']['data_cut_off']);
            }
            // Exclude narrative prose from metric comparison — regenerate always refreshes
            // analysis text even when metrics are identical.
            unset(
                $node['programme']['introduction'],
                $node['programme']['objectives'],
                $node['programme']['objectives_not_applicable'],
                $node['programme']['conclusion'],
                $node['programme']['analysis'],
                $node['programme']['executive_summary'],
            );
        }

        foreach ($node as &$value) {
            if (is_array($value)) {
                self::stripTimestamps($value);
            }
        }
        unset($value);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function ksortRecursive(array $value): array
    {
        foreach ($value as &$child) {
            if (is_array($child)) {
                $child = self::ksortRecursive($child);
            }
        }
        unset($child);
        ksort($value);

        return $value;
    }
}
