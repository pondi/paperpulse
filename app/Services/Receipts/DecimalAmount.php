<?php

namespace App\Services\Receipts;

use InvalidArgumentException;

class DecimalAmount
{
    public static function parse(mixed $value, ?string $decimalSeparator = null): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new InvalidArgumentException('Amount must be a number or decimal string');
        }
        $text = trim((string) $value);
        $negative = str_starts_with($text, '(') && str_ends_with($text, ')');
        if ($negative) {
            $text = substr($text, 1, -1);
        }
        if (preg_match('/^([+-]?)([\d.,\x{00a0}\x{202f} ]+)$/u', $text, $matches) !== 1) {
            throw new InvalidArgumentException('Malformed financial amount');
        }
        if ($negative && $matches[1] !== '') {
            throw new InvalidArgumentException('Amount has conflicting signs');
        }
        $negative = $negative || $matches[1] === '-';
        $number = $matches[2];
        $separator = $decimalSeparator;
        if ($separator === null) {
            if (str_contains($number, ',') && str_contains($number, '.')) {
                $separator = strrpos($number, ',') > strrpos($number, '.') ? ',' : '.';
            } elseif (preg_match('/[.,](\d+)$/', $number, $fraction)) {
                if (strlen($fraction[1]) === 3 && is_string($value)) {
                    throw new InvalidArgumentException('Ambiguous financial separator; supply a locale');
                }
                $separator = str_contains($number, ',') ? ',' : '.';
            }
        }
        if ($separator !== null && ! in_array($separator, ['.', ','], true)) {
            throw new InvalidArgumentException('Unsupported decimal separator');
        }
        $parts = $separator === null ? [$number] : explode($separator, $number);
        if (count($parts) > 2 || (isset($parts[1]) && ! preg_match('/^\d{1,6}$/', $parts[1]))) {
            throw new InvalidArgumentException('Malformed decimal fraction');
        }
        $integer = $parts[0];
        if (! preg_match('/^\d+$/', $integer)) {
            $group = $separator === ',' ? '.' : ',';
            if (! preg_match('/^\d{1,3}(?:'.preg_quote($group, '/').'\d{3})+$/', $integer)
                && ! preg_match('/^\d{1,3}(?:[ \x{00a0}\x{202f}]\d{3})+$/u', $integer)) {
                throw new InvalidArgumentException('Malformed thousands grouping');
            }
            $integer = preg_replace('/[., \x{00a0}\x{202f}]/u', '', $integer);
        }
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = $parts[1] ?? '';
        $sign = $negative && ($integer !== '0' || trim($fraction, '0') !== '') ? '-' : '';

        return $sign.$integer.($fraction !== '' ? '.'.$fraction : '');
    }

    public static function minorUnits(mixed $value): int
    {
        $decimal = self::parse($value);
        $negative = str_starts_with($decimal, '-');
        $parts = explode('.', ltrim($decimal, '-'));
        $fraction = $parts[1] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new InvalidArgumentException('Money has unsupported precision');
        }
        if (strlen($parts[0]) > 13) {
            throw new InvalidArgumentException('Money is outside supported range');
        }
        $minor = (int) $parts[0] * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minor : $minor;
    }

    public static function multiplyPrice(mixed $price, mixed $quantity): string
    {
        $decimal = self::parse($quantity);
        $parts = explode('.', $decimal);
        $scale = 10 ** strlen($parts[1] ?? '');
        $units = (int) str_replace('.', '', $decimal);
        $product = self::minorUnits($price) * $units;
        if (! is_int($product)) {
            throw new InvalidArgumentException('Line item is outside supported range');
        }
        $rounded = intdiv(abs($product) + intdiv($scale, 2), $scale);

        return self::format($product < 0 ? -$rounded : $rounded);
    }

    public static function format(int $minor): string
    {
        return ($minor < 0 ? '-' : '').intdiv(abs($minor), 100).'.'.str_pad((string) (abs($minor) % 100), 2, '0', STR_PAD_LEFT);
    }
}
