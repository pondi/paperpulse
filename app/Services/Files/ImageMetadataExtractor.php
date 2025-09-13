<?php

namespace App\Services\Files;

use Exception;

class ImageMetadataExtractor
{
    public static function extract(string $filePath): array
    {
        $metadata = [];

        try {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo !== false) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['mime_type'] = $imageInfo['mime'] ?? null;
                $metadata['bits'] = $imageInfo['bits'] ?? null;
                $metadata['channels'] = $imageInfo['channels'] ?? null;
            }

            if (function_exists('exif_read_data') && in_array(strtolower(pathinfo($filePath, PATHINFO_EXTENSION)), ['jpg', 'jpeg'])) {
                $exifData = @exif_read_data($filePath);
                if ($exifData !== false) {
                    $metadata['exif'] = [
                        'camera_make' => $exifData['Make'] ?? null,
                        'camera_model' => $exifData['Model'] ?? null,
                        'date_taken' => $exifData['DateTime'] ?? null,
                        'gps_latitude' => self::extractGPSCoordinate($exifData, 'GPSLatitude', 'GPSLatitudeRef'),
                        'gps_longitude' => self::extractGPSCoordinate($exifData, 'GPSLongitude', 'GPSLongitudeRef'),
                    ];
                }
            }
        } catch (Exception $e) {
            // Swallow; callers decide how to log
        }

        return $metadata;
    }

    private static function extractGPSCoordinate(array $exifData, string $coordinateKey, string $refKey): ?float
    {
        if (! isset($exifData[$coordinateKey], $exifData[$refKey])) {
            return null;
        }

        $coordinates = $exifData[$coordinateKey];
        $ref = $exifData[$refKey];
        if (! is_array($coordinates) || count($coordinates) !== 3) {
            return null;
        }

        $decimal = self::convertDMSToDecimal($coordinates[0], $coordinates[1], $coordinates[2]);
        if (in_array($ref, ['S', 'W'])) {
            $decimal *= -1;
        }

        return $decimal;
    }

    private static function convertDMSToDecimal($degrees, $minutes, $seconds): float
    {
        $degreesDecimal = self::fractionToDecimal($degrees);
        $minutesDecimal = self::fractionToDecimal($minutes);
        $secondsDecimal = self::fractionToDecimal($seconds);

        return $degreesDecimal + ($minutesDecimal / 60) + ($secondsDecimal / 3600);
    }

    private static function fractionToDecimal($fraction): float
    {
        if (is_numeric($fraction)) {
            return (float) $fraction;
        }

        if (is_string($fraction) && strpos($fraction, '/') !== false) {
            [$num, $den] = array_map('floatval', explode('/', $fraction));
            if ($den != 0.0) {
                return $num / $den;
            }
        }

        return 0.0;
    }
}

