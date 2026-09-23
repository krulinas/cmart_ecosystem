<?php

namespace App\Support;

/**
 * Shared Post-Event Report visual specification from a frozen snapshot.
 * Presentation only — does not recompute authoritative analytics formulas.
 * Performance Across Events / live Python benchmarks are intentionally omitted.
 */
final class PostEventReportVisualSpec
{
    public const PALETTE = [
        'primary' => '#3970E4',
        'positive' => '#2E9D78',
        'finance' => '#2D439C',
        'warning' => '#E86F88',
        'survey' => '#925FD1',
        'highlight' => '#D5A800',
        'neutral' => '#E8EEF6',
    ];

    private const COMPOSITION = [
        '#3970E4',
        '#2E9D78',
        '#925FD1',
        '#D5A800',
        '#2D439C',
        '#E86F88',
    ];

    private const BOOKING_STATUS_ORDER = [
        'Pending_Organizer',
        'Pending_Staff',
        'Pending_Boss',
        'Needs_Revision',
        'Approved',
        'Rejected',
        'Cancelled',
        'Withdrawn',
    ];

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public static function fromSnapshot(array $snapshot): array
    {
        $pipeline = self::section($snapshot, 'booking_pipeline');
        $payments = self::section($snapshot, 'payments');
        $eventPerformance = self::section($snapshot, 'event_performance');
        $categories = self::section($snapshot, 'vendor_categories');
        $feedback = self::section($snapshot, 'feedback');
        $survey = self::section($snapshot, 'vendor_survey');

        $charts = [];

        if (is_array($eventPerformance)) {
            $rows = [];
            $sold = self::presentCount($eventPerformance['sites_sold'] ?? null);
            $available = self::presentCount($eventPerformance['available_sites'] ?? null);
            if ($sold !== null) {
                $rows[] = [
                    'key' => 'sites_sold',
                    'label' => 'Sites sold',
                    'count' => $sold,
                    'color' => self::PALETTE['primary'],
                ];
            }
            if ($available !== null) {
                $rows[] = [
                    'key' => 'sites_remaining',
                    'label' => 'Remaining open sites',
                    'count' => $available,
                    'color' => self::PALETTE['neutral'],
                ];
            }
            $charts['site_utilisation'] = [
                'id' => 'site_utilisation',
                'title' => 'Site utilisation',
                'type' => 'doughnut',
                'rows' => self::withColors($rows),
                'center_value' => isset($eventPerformance['site_utilisation_percent'])
                    ? $eventPerformance['site_utilisation_percent'].'%'
                    : null,
                'empty' => $rows === [],
            ];
        }

        // Vendor categories — adaptive doughnut (2–5) / ranked bar (6+) / compact (0–1)
        $dist = is_array($categories['distribution'] ?? null) ? $categories['distribution'] : [];
        $categoryRows = [];
        foreach ($dist as $row) {
            if (! is_array($row)) {
                continue;
            }
            $count = self::presentCount($row['unique_vendors'] ?? $row['count'] ?? null);
            if ($count === null || $count <= 0) {
                continue;
            }
            $categoryRows[] = [
                'key' => (string) ($row['key'] ?? $row['label'] ?? $count),
                'label' => (string) ($row['label'] ?? 'Uncategorised'),
                'count' => $count,
            ];
        }
        $charts['vendor_categories'] = [
            'id' => 'vendor_categories',
            'title' => 'Product categories — System Data',
            'type' => self::resolveCategoryChartType(count($categoryRows)),
            'rows' => self::withColors($categoryRows),
            'empty' => $categoryRows === [],
        ];

        if (is_array($payments)) {
            $rows = [];
            $collected = self::presentCount(
                $payments['collected_revenue']
                    ?? $payments['collected_booth_fees']
                    ?? $payments['collected']
                    ?? null
            );
            $outstanding = self::presentCount(
                $payments['outstanding_invoice_balance']
                    ?? $payments['outstanding']
                    ?? $payments['unpaid_approved']
                    ?? null
            );
            $invoiceCount = self::presentCount(
                $payments['invoice_count'] ?? $payments['invoice_count_approved'] ?? null
            );
            if ($collected !== null) {
                $rows[] = [
                    'key' => 'collected',
                    'label' => 'Collected',
                    'count' => $collected,
                    'color' => self::PALETTE['positive'],
                ];
            }
            if ($invoiceCount !== null && $invoiceCount > 0 && $outstanding !== null) {
                $rows[] = [
                    'key' => 'outstanding',
                    'label' => 'Outstanding',
                    'count' => $outstanding,
                    'color' => self::PALETTE['warning'],
                ];
            }
            $hasPositive = false;
            foreach ($rows as $row) {
                if ($row['count'] > 0) {
                    $hasPositive = true;
                    break;
                }
            }
            $charts['revenue_collection'] = [
                'id' => 'revenue_collection',
                'title' => 'Revenue collection',
                'type' => 'stacked_bar',
                'rows' => self::withColors($rows),
                'empty' => $rows === [] || ! $hasPositive,
            ];
        }

        if (is_array($pipeline)) {
            $by = is_array($pipeline['by_approval_status'] ?? null) ? $pipeline['by_approval_status'] : [];
            $keys = array_values(array_unique(array_merge(
                array_values(array_filter(self::BOOKING_STATUS_ORDER, fn ($k) => array_key_exists($k, $by))),
                array_keys($by),
            )));
            $rows = [];
            foreach ($keys as $key) {
                $count = self::presentCount($by[$key] ?? null);
                if ($count === null || $count <= 0) {
                    continue;
                }
                $rows[] = [
                    'key' => (string) $key,
                    'label' => (string) $key,
                    'count' => $count,
                ];
            }
            $charts['booking_status'] = [
                'id' => 'booking_status',
                'title' => 'Booking status mix',
                'type' => 'doughnut',
                'rows' => self::withColors($rows),
                'empty' => $rows === [],
            ];
        }

        if (is_array($feedback) && (int) ($feedback['response_count'] ?? 0) > 0) {
            $ratingDist = is_array($feedback['rating_distribution'] ?? null)
                ? $feedback['rating_distribution']
                : [];
            $rows = [];
            foreach ([1, 2, 3, 4, 5] as $star) {
                $count = self::presentCount($ratingDist[$star] ?? $ratingDist[(string) $star] ?? null);
                if ($count === null || $count <= 0) {
                    continue;
                }
                $rows[] = [
                    'key' => 'rating_'.$star,
                    'label' => $star.'★',
                    'count' => $count,
                ];
            }
            $charts['feedback_ratings'] = [
                'id' => 'feedback_ratings',
                'title' => 'Community feedback rating distribution',
                'type' => 'bar',
                'rows' => self::withColors($rows, [
                    self::PALETTE['warning'],
                    self::PALETTE['highlight'],
                    self::PALETTE['neutral'],
                    self::PALETTE['primary'],
                    self::PALETTE['positive'],
                ]),
                'empty' => $rows === [],
            ];
        }

        $surveyCharts = [];
        if (is_array($survey) && ! empty($survey['available']) && empty($survey['excluded'])) {
            $distributions = is_array($survey['distributions'] ?? null) ? $survey['distributions'] : [];
            foreach ($distributions as $key => $block) {
                if (! is_array($block) || ! empty($block['excluded'])) {
                    continue;
                }
                $rawRows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
                $rows = [];
                foreach ($rawRows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $count = self::presentCount($row['count'] ?? null);
                    if ($count === null) {
                        continue;
                    }
                    $rows[] = [
                        'key' => (string) ($row['key'] ?? $row['label'] ?? $count),
                        'label' => PostEventReportPresentation::optionLabel(
                            (string) ($row['label'] ?? $row['key'] ?? '')
                        ),
                        'count' => $count,
                        'percent' => $row['percent'] ?? null,
                    ];
                }
                $surveyCharts[] = [
                    'id' => 'survey_'.$key,
                    'title' => PostEventReportPresentation::distributionTitle((string) $key),
                    'type' => 'bar',
                    'rows' => self::withColors($rows),
                    'empty' => $rows === [],
                    'message' => $block['message'] ?? null,
                ];
            }
        }

        return [
            'palette' => self::PALETTE,
            'charts' => $charts,
            'survey_charts' => $surveyCharts,
            'include_performance_across_events' => false,
            'performance_across_events_note' =>
                'Performance Across Events is omitted from the official report because cross-event benchmark results are not frozen into the report snapshot.',
        ];
    }

    /**
     * Adaptive category chart type — mirrors frontend resolveCategoryChartType().
     */
    public static function resolveCategoryChartType(int $validCount): string
    {
        if ($validCount >= 2 && $validCount <= 5) {
            return 'doughnut';
        }
        if ($validCount >= 6) {
            return 'bar';
        }

        return 'compact';
    }

    /**
     * Canonical contract slice for JS↔PHP parity tests.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>
     */
    public static function toContract(array $spec): array
    {
        $charts = [];
        foreach (($spec['charts'] ?? []) as $id => $chart) {
            if (! is_array($chart)) {
                continue;
            }
            $rows = [];
            foreach (($chart['rows'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'key' => (string) ($row['key'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'count' => (float) ($row['count'] ?? 0),
                    'color' => (string) ($row['color'] ?? ''),
                ];
            }
            $charts[$id] = [
                'id' => (string) ($chart['id'] ?? $id),
                'type' => (string) ($chart['type'] ?? ''),
                'empty' => (bool) ($chart['empty'] ?? false),
                'rows' => $rows,
            ];
        }

        return [
            'palette' => $spec['palette'] ?? self::PALETTE,
            'charts' => $charts,
    'includePerformanceAcrossEvents' => (bool) ($spec['include_performance_across_events'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>|null
     */
    private static function section(array $snapshot, string $key): ?array
    {
        $block = $snapshot['sections'][$key] ?? null;
        if (! is_array($block) || ! empty($block['excluded'])) {
            return null;
        }

        return $block;
    }

    private static function presentCount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>|null  $colors
     * @return list<array<string, mixed>>
     */
    private static function withColors(array $rows, ?array $colors = null): array
    {
        $palette = $colors ?? self::COMPOSITION;
        foreach ($rows as $i => $row) {
            if (empty($row['color'])) {
                $rows[$i]['color'] = $palette[$i % count($palette)];
            }
        }

        return $rows;
    }
}
