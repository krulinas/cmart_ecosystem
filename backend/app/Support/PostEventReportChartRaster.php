<?php

namespace App\Support;

/**
 * DomPDF-safe chart rasters. Prefers GD when available; falls back to a
 * minimal zlib PNG encoder so environments without GD still produce a bitmap.
 */
final class PostEventReportChartRaster
{
    /**
     * @param  list<array{label?: string, count?: float|int, color?: string}>  $rows
     */
    public static function doughnutDataUri(array $rows, int $size = 160): ?string
    {
        $segments = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['count']) || ! is_numeric($row['count'])) {
                continue;
            }
            $count = (float) $row['count'];
            if ($count <= 0) {
                continue;
            }
            $segments[] = [
                'count' => $count,
                'color' => self::hexToRgb((string) ($row['color'] ?? '#3970E4')),
            ];
        }
        if ($segments === []) {
            return null;
        }

        $total = array_sum(array_column($segments, 'count'));
        if ($total <= 0) {
            return null;
        }

        $png = extension_loaded('gd')
            ? self::renderWithGd($segments, $total, $size)
            : self::renderWithoutGd($segments, $total, $size);

        if ($png === null || $png === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * @param  list<array{count: float, color: array{0:int,1:int,2:int}}>  $segments
     */
    private static function renderWithGd(array $segments, float $total, int $size): ?string
    {
        $im = imagecreatetruecolor($size, $size);
        if ($im === false) {
            return null;
        }
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 255, 255, 255, 127);
        imagefill($im, 0, 0, $transparent);

        $cx = (int) floor($size / 2);
        $cy = (int) floor($size / 2);
        $outer = $size - 4;
        $inner = (int) round($outer * 0.55);
        $angle = -90.0;

        foreach ($segments as $seg) {
            $sweep = ($seg['count'] / $total) * 360.0;
            $color = imagecolorallocate($im, $seg['color'][0], $seg['color'][1], $seg['color'][2]);
            $start = (int) round($angle);
            $end = (int) round($angle + $sweep);
            imagefilledarc($im, $cx, $cy, $outer, $outer, $start, $end, $color, IMG_ARC_PIE);
            $angle += $sweep;
        }

        $hole = imagecolorallocate($im, 255, 255, 255);
        imagefilledellipse($im, $cx, $cy, $inner, $inner, $hole);

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return is_string($png) ? $png : null;
    }

    /**
     * @param  list<array{count: float, color: array{0:int,1:int,2:int}}>  $segments
     */
    private static function renderWithoutGd(array $segments, float $total, int $size): ?string
    {
        $cx = ($size - 1) / 2.0;
        $cy = ($size - 1) / 2.0;
        $outer = ($size / 2.0) - 2.0;
        $inner = $outer * 0.55;
        $pixels = array_fill(0, $size * $size * 3, 255);

        $angle = -90.0;
        foreach ($segments as $seg) {
            $sweep = ($seg['count'] / $total) * 360.0;
            self::fillWedge($pixels, $size, $cx, $cy, $outer, $inner, $angle, $angle + $sweep, $seg['color']);
            $angle += $sweep;
        }

        return self::encodePng($pixels, $size, $size);
    }

    /**
     * @param  list<int>  $pixels
     * @param  array{0:int,1:int,2:int}  $color
     */
    private static function fillWedge(
        array &$pixels,
        int $size,
        float $cx,
        float $cy,
        float $outer,
        float $inner,
        float $startDeg,
        float $endDeg,
        array $color,
    ): void {
        $start = fmod($startDeg + 360.0, 360.0);
        $end = fmod($endDeg + 360.0, 360.0);
        $crosses = $end < $start;

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $dx = ($x + 0.5) - $cx;
                $dy = ($y + 0.5) - $cy;
                $dist = hypot($dx, $dy);
                if ($dist > $outer || $dist < $inner) {
                    continue;
                }
                $deg = rad2deg(atan2($dy, $dx));
                if ($deg < 0) {
                    $deg += 360.0;
                }
                $inside = $crosses
                    ? ($deg >= $start || $deg <= $end)
                    : ($deg >= $start && $deg <= $end);
                if (! $inside) {
                    continue;
                }
                $idx = ($y * $size + $x) * 3;
                $pixels[$idx] = $color[0];
                $pixels[$idx + 1] = $color[1];
                $pixels[$idx + 2] = $color[2];
            }
        }
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) < 6) {
            return [57, 112, 228];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /** @param  list<int>  $pixels */
    private static function encodePng(array $pixels, int $width, int $height): ?string
    {
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00";
            for ($x = 0; $x < $width; $x++) {
                $idx = ($y * $width + $x) * 3;
                $raw .= chr($pixels[$idx]).chr($pixels[$idx + 1]).chr($pixels[$idx + 2]);
            }
        }
        $compressed = gzcompress($raw, 9);
        if ($compressed === false) {
            return null;
        }

        return "\x89PNG\r\n\x1a\n"
            .self::pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .self::pngChunk('IDAT', $compressed)
            .self::pngChunk('IEND', '');
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
