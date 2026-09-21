<?php

namespace App\Support;

/**
 * Presentation-only labels and formatting for Post-Event Summary surfaces.
 * Does not change snapshot aggregation.
 */
final class PostEventReportPresentation
{
    public static function optionLabel(?string $key): string
    {
        if ($key === null || $key === '') {
            return '—';
        }

        $raw = trim($key);
        $normalized = strtolower($raw);
        $translated = __('reports.option.'.$normalized);

        if ($translated !== 'reports.option.'.$normalized) {
            return $translated;
        }

        $translatedRaw = __('reports.option.'.$raw);
        if ($translatedRaw !== 'reports.option.'.$raw) {
            return $translatedRaw;
        }

        // Already human-readable Malay/English codebook values: keep as-is for legacy.
        if (! str_contains($raw, '_') && preg_match('/[A-Za-z]/', $raw)) {
            return $raw;
        }

        $spaced = str_replace('_', ' ', $raw);

        return ucfirst($spaced);
    }

    public static function distributionTitle(string $key): string
    {
        $translated = __('reports.distribution.'.$key);

        return $translated !== 'reports.distribution.'.$key
            ? $translated
            : ucfirst(str_replace('_', ' ', $key));
    }

    public static function methodologyLabel(string $key): string
    {
        $translated = __('reports.methodology.'.$key);

        return $translated !== 'reports.methodology.'.$key
            ? $translated
            : ucfirst(str_replace('_', ' ', $key));
    }

    public static function money(null|int|float|string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return 'RM ' . number_format((float) $value, 2);
    }

    public static function collectionRate(?float $collected, ?float $expected): ?float
    {
        if ($collected === null || $expected === null || $expected <= 0) {
            return null;
        }

        return round(($collected / $expected) * 100, 1);
    }

    public static function resolveLogoPath(): ?string
    {
        $candidates = [
            public_path('cmart_logo.png'),
            base_path('../frontend/public/cmart_logo.png'),
            base_path('public/cmart_logo.png'),
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
