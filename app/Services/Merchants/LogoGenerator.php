<?php

namespace App\Services\Merchants;

/**
 * Generates SVG logos for merchants internally.
 * Single responsibility: Create merchant avatars without external dependencies.
 */
class LogoGenerator
{
    /**
     * Generate an SVG logo for a merchant.
     */
    public static function generateSvg(string $name, ?string $backgroundColor = null, ?string $textColor = null): string
    {
        $initials = self::getInitials($name);
        $bgColor = $backgroundColor ?? self::generateColorFromName($name);
        $txtColor = $textColor ?? self::getContrastColor($bgColor);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">
            <rect width="128" height="128" fill="{$bgColor}" />
            <text x="64" y="64" font-family="system-ui, -apple-system, sans-serif" 
                  font-size="48" font-weight="600" fill="{$txtColor}" 
                  text-anchor="middle" dominant-baseline="middle">
                {$initials}
            </text>
        </svg>
        SVG;
    }

    /**
     * Get initials from merchant name.
     */
    private static function getInitials(string $name): string
    {
        $words = explode(' ', trim($name));
        $initials = '';

        // Take first letter of first two words
        foreach (array_slice($words, 0, 2) as $word) {
            if (strlen($word) > 0) {
                $initials .= strtoupper(substr($word, 0, 1));
            }
        }

        // If no initials, use first two chars of name
        if (empty($initials)) {
            $initials = strtoupper(substr($name, 0, 2));
        }

        return $initials;
    }

    /**
     * Generate a consistent color from merchant name.
     */
    private static function generateColorFromName(string $name): string
    {
        // Use hash to generate consistent color
        $hash = md5($name);
        $hue = hexdec(substr($hash, 0, 2)) * 360 / 255;

        // Use HSL for better color distribution
        // Saturation 65%, Lightness 55% for pleasant colors
        return self::hslToHex($hue, 65, 55);
    }

    /**
     * Convert HSL to Hex color.
     *
     * @param  float  $h  Hue (0-360)
     * @param  float  $s  Saturation (0-100)
     * @param  float  $l  Lightness (0-100)
     */
    private static function hslToHex(float $h, float $s, float $l): string
    {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;

            $r = self::hueToRgb($p, $q, $h + 1 / 3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1 / 3);
        }

        return sprintf('#%02x%02x%02x',
            round($r * 255),
            round($g * 255),
            round($b * 255)
        );
    }

    /**
     * Helper for HSL to RGB conversion.
     */
    private static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    /**
     * Get contrasting text color for background.
     */
    private static function getContrastColor(string $hexColor): string
    {
        $hex = str_replace('#', '', $hexColor);

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate relative luminance
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? '#000000' : '#FFFFFF';
    }
}
