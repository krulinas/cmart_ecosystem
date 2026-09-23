<?php

namespace App\Support;

/**
 * DomPDF-safe SVG / HTML chart fragments for Post-Event reports.
 * Never embeds Chart.js canvas.
 */
final class PostEventReportChartSvg
{
    /**
     * @param  list<array{label?: string, count?: float|int, color?: string}>  $rows
     */
    public static function doughnut(array $rows, int $size = 120): string
    {
        $segments = self::normalize($rows);
        if ($segments === []) {
            return '<p class="muted">No data available.</p>';
        }

        $total = array_sum(array_column($segments, 'count'));
        if ($total <= 0) {
            return '<p class="muted">No data available.</p>';
        }

        $cx = $size / 2;
        $cy = $size / 2;
        $r = ($size / 2) - 4;
        $inner = $r * 0.55;
        $angle = -90.0;
        // DomPDF frequently drops SVG elliptical-arc path commands; use polygon wedges
        // (line segments only) so doughnuts render as visible filled shapes, not legends alone.
        $paths = '';

        foreach ($segments as $seg) {
            $sweep = ($seg['count'] / $total) * 360.0;
            if ($sweep <= 0) {
                continue;
            }
            $paths .= self::donutSlicePolygon($cx, $cy, $r, $inner, $angle, $angle + $sweep, $seg['color']);
            $angle += $sweep;
        }

        $legend = self::legendHtml($segments, $total);
        $dataUri = PostEventReportChartRaster::doughnutDataUri($rows, max(120, $size));
        $visual = $dataUri
            ? '<img src="'.$dataUri.'" width="'.$size.'" height="'.$size.'" alt="Doughnut chart" data-chart-visual="1" />'
            : '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$size.' '.$size.'" xmlns="http://www.w3.org/2000/svg" data-chart-visual="1">'
                .$paths
                .'</svg>';

        return '<table class="chart-wrap keep-together" data-chart-kind="doughnut"><tr>'
            .'<td style="width:'.($size + 8).'px;vertical-align:middle;">'
            .$visual
            .'</td>'
            .'<td style="vertical-align:middle;" data-chart-legend="1">'.$legend.'</td>'
            .'</tr></table>';
    }

    /**
     * @param  list<array{label?: string, count?: float|int, color?: string}>  $rows
     */
    public static function stackedBar(array $rows, int $maxWidth = 280): string
    {
        $segments = self::normalize($rows);
        if ($segments === []) {
            return '<p class="muted">No data available.</p>';
        }

        $total = array_sum(array_column($segments, 'count'));
        if ($total <= 0) {
            return '<p class="muted">No data available.</p>';
        }

        $html = '<div class="stacked-bar keep-together" style="width:'.$maxWidth.'px;background:#E8EEF6;height:18px;font-size:0;">';
        foreach ($segments as $seg) {
            if ($seg['count'] <= 0) {
                continue;
            }
            $width = max(2, (int) round(($seg['count'] / $total) * $maxWidth));
            $html .= '<span style="display:inline-block;height:18px;width:'.$width.'px;background:'.$seg['color'].';"></span>';
        }
        $html .= '</div>';
        $html .= self::legendHtml($segments, $total);

        return $html;
    }

    /**
     * Compact single-value / empty fallback (0–1 categories).
     *
     * @param  list<array{label?: string, count?: float|int, color?: string}>  $rows
     */
    public static function compact(array $rows): string
    {
        $segments = self::normalize($rows);
        if ($segments === []) {
            return '<p class="muted">No category recorded.</p>';
        }
        $seg = $segments[0];

        return '<table class="keep-together" style="border-collapse:collapse;font-size:10px;">'
            .'<tr><td style="padding:6px 10px;background:#F5F8FE;border:1px solid #E8EEF6;">'
            .'<span style="display:inline-block;width:8px;height:8px;background:'.$seg['color'].';margin-right:6px;"></span>'
            .'<strong>'.e($seg['label']).'</strong>'
            .' · '.e((string) $seg['count'])
            .' unique vendor'.($seg['count'] == 1 ? '' : 's')
            .'</td></tr></table>';
    }

    /**
     * @param  list<array{label?: string, count?: float|int, color?: string, percent?: float|int|null}>  $rows
     */
    public static function horizontalBars(array $rows, int $maxWidth = 220): string
    {
        $segments = self::normalize($rows);
        if ($segments === []) {
            return '<p class="muted">No data available.</p>';
        }

        $max = max(array_column($segments, 'count')) ?: 1;
        $html = '<table class="bar-chart keep-together" style="width:100%;border-collapse:collapse;">';
        foreach ($segments as $seg) {
            $pct = max(2, (int) round(($seg['count'] / $max) * 100));
            $width = (int) round(($pct / 100) * $maxWidth);
            $meta = (string) $seg['count'];
            if (isset($seg['percent']) && $seg['percent'] !== null) {
                $meta .= ' · '.$seg['percent'].'%';
            }
            $html .= '<tr>'
                .'<td style="padding:3px 6px 3px 0;width:38%;font-size:9.5px;">'
                .e($seg['label']).'</td>'
                .'<td style="padding:3px 0;">'
                .'<div style="background:#E8EEF6;height:10px;width:'.$maxWidth.'px;">'
                .'<div style="background:'.$seg['color'].';height:10px;width:'.$width.'px;"></div>'
                .'</div></td>'
                .'<td style="padding:3px 0 3px 6px;font-size:9.5px;white-space:nowrap;font-weight:bold;">'
                .e($meta).'</td>'
                .'</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * @param  list<array{label?: string, count?: float|int, color?: string}>  $rows
     * @return list<array{label: string, count: float, color: string, percent?: float|int|null}>
     */
    private static function normalize(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (! isset($row['count']) || $row['count'] === null || $row['count'] === '') {
                continue;
            }
            if (! is_numeric($row['count'])) {
                continue;
            }
            $out[] = [
                'label' => (string) ($row['label'] ?? ''),
                'count' => (float) $row['count'],
                'color' => (string) ($row['color'] ?? '#3970E4'),
                'percent' => $row['percent'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{label: string, count: float, color: string}>  $segments
     */
    private static function legendHtml(array $segments, float $total): string
    {
        $html = '<table style="border-collapse:collapse;font-size:9.5px;">';
        foreach ($segments as $seg) {
            $share = $total > 0 ? round(($seg['count'] / $total) * 100, 1) : 0;
            $html .= '<tr>'
                .'<td style="padding:2px 6px 2px 0;"><span style="display:inline-block;width:8px;height:8px;background:'
                .$seg['color'].';"></span></td>'
                .'<td style="padding:2px 8px 2px 0;">'.e($seg['label']).'</td>'
                .'<td style="padding:2px 0;font-weight:bold;white-space:nowrap;">'
                .e((string) $seg['count']).' · '.$share.'%</td>'
                .'</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Approximate a donut wedge with a polygon (DomPDF-safe — no arc commands).
     */
    private static function donutSlicePolygon(
        float $cx,
        float $cy,
        float $r,
        float $inner,
        float $startDeg,
        float $endDeg,
        string $color,
    ): string {
        $sweep = $endDeg - $startDeg;
        if ($sweep <= 0) {
            return '';
        }
        // ~1 point per 3 degrees, minimum 2 outer points.
        $steps = max(2, (int) ceil($sweep / 3));
        $outer = [];
        $innerPts = [];
        for ($i = 0; $i <= $steps; $i++) {
            $deg = $startDeg + ($sweep * ($i / $steps));
            $outer[] = self::polar($cx, $cy, $r, $deg);
            $innerPts[] = self::polar($cx, $cy, $inner, $deg);
        }
        $points = [];
        foreach ($outer as $p) {
            $points[] = sprintf('%.2f,%.2f', $p[0], $p[1]);
        }
        for ($i = count($innerPts) - 1; $i >= 0; $i--) {
            $points[] = sprintf('%.2f,%.2f', $innerPts[$i][0], $innerPts[$i][1]);
        }

        return '<polygon points="'.implode(' ', $points).'" fill="'.$color.'" stroke="#ffffff" stroke-width="1"/>';
    }

    /** @return array{0: float, 1: float} */
    private static function polar(float $cx, float $cy, float $r, float $deg): array
    {
        $rad = deg2rad($deg);

        return [$cx + $r * cos($rad), $cy + $r * sin($rad)];
    }
}
