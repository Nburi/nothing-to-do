<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateIcons extends Command
{
    protected $signature = 'icons:generate';

    protected $description = 'Render app/PWA icons (paper background + forest triangle mark) into public/icons/';

    // Topografie brand tokens (light mode) — keep in sync with resources/css/app.css :root.
    private const PAPER = [241, 237, 226]; // #F1EDE2

    private const FOREST = [31, 107, 59]; // #1F6B3B

    // 512/192 for manifest.json, 180 for apple-touch-icon, 32/16 for <link rel=icon>.
    private const SIZES = [512, 192, 180, 32, 16];

    // Render at 4x target size, then downscale with imagecopyresampled() for clean
    // anti-aliased edges — GD's imagefilledpolygon() has no reliable native AA.
    private const SUPERSAMPLE = 4;

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD extension not loaded — install/enable it, or use the ImageMagick/Inkscape fallback documented in the PWA plan.');

            return self::FAILURE;
        }

        $outDir = public_path('icons');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        foreach (self::SIZES as $size) {
            $path = "{$outDir}/icon-{$size}.png";
            $this->renderIcon($size, $path);
            $this->info("icons/icon-{$size}.png");
        }

        return self::SUCCESS;
    }

    private function renderIcon(int $size, string $path): void
    {
        $big = $size * self::SUPERSAMPLE;

        $canvas = imagecreatetruecolor($big, $big);

        $paper = imagecolorallocate($canvas, ...self::PAPER);
        $forest = imagecolorallocate($canvas, ...self::FOREST);

        imagefilledrectangle($canvas, 0, 0, $big - 1, $big - 1, $paper);

        // Verified inside the maskable safe zone (centered circle, radius 0.4×size) —
        // base vertices land at ~0.35×size from center.
        $apex = [$big * 0.500, $big * 0.277];
        $left = [$big * 0.230, $big * 0.723];
        $right = [$big * 0.770, $big * 0.723];

        imagefilledpolygon($canvas, [
            $apex[0], $apex[1], $right[0], $right[1], $left[0], $left[1],
        ], $forest);

        $out = imagecreatetruecolor($size, $size);
        imagecopyresampled($out, $canvas, 0, 0, 0, 0, $size, $size, $big, $big);
        imagepng($out, $path, 6);

        imagedestroy($canvas);
        imagedestroy($out);
    }
}
