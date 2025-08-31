<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DateFormatterService
{
    /**
     * Format a date according to user preferences
     */
    public function format($date, $format = null, $timezone = null)
    {
        if (! $date) {
            return null;
        }

        $user = Auth::user();

        // Convert to Carbon instance if needed
        if (! ($date instanceof Carbon)) {
            $date = Carbon::parse($date);
        }

        // Use user's timezone or provided timezone
        $timezone = $timezone ?? ($user ? $user->preference('timezone', 'UTC') : 'UTC');
        $date = $date->timezone($timezone);

        // Use user's date format or provided format
        $format = $format ?? ($user ? $user->preference('date_format', 'Y-m-d') : 'Y-m-d');

        return $date->format($format);
    }

    /**
     * Format a datetime according to user preferences
     */
    public function formatDateTime($date, $format = null, $timezone = null)
    {
        if (! $date) {
            return null;
        }

        $user = Auth::user();

        // Convert to Carbon instance if needed
        if (! ($date instanceof Carbon)) {
            $date = Carbon::parse($date);
        }

        // Use user's timezone or provided timezone
        $timezone = $timezone ?? ($user ? $user->preference('timezone', 'UTC') : 'UTC');
        $date = $date->timezone($timezone);

        // Use user's date format or provided format with time
        $dateFormat = $format ?? ($user ? $user->preference('date_format', 'Y-m-d') : 'Y-m-d');
        $format = $dateFormat.' H:i';

        return $date->format($format);
    }

    /**
     * Convert date to user's timezone
     */
    public function toUserTimezone($date, $timezone = null)
    {
        if (! $date) {
            return null;
        }

        $user = Auth::user();
        $timezone = $timezone ?? ($user ? $user->preference('timezone', 'UTC') : 'UTC');

        // Convert to Carbon instance if needed
        if (! ($date instanceof Carbon)) {
            $date = Carbon::parse($date);
        }

        return $date->timezone($timezone);
    }

    /**
     * Convert date from user's timezone to UTC
     */
    public function toUTC($date, $timezone = null)
    {
        if (! $date) {
            return null;
        }

        $user = Auth::user();
        $timezone = $timezone ?? ($user ? $user->preference('timezone', 'UTC') : 'UTC');

        // Convert to Carbon instance if needed
        if (! ($date instanceof Carbon)) {
            $date = Carbon::parse($date, $timezone);
        }

        return $date->timezone('UTC');
    }

    /**
     * Get localized date format examples
     */
    public function getFormatExamples()
    {
        $now = Carbon::now();
        $formats = [
            'Y-m-d' => $now->format('Y-m-d'),
            'd/m/Y' => $now->format('d/m/Y'),
            'm/d/Y' => $now->format('m/d/Y'),
            'd.m.Y' => $now->format('d.m.Y'),
            'F j, Y' => $now->format('F j, Y'),
            'j F Y' => $now->format('j F Y'),
        ];

        return $formats;
    }
}
