<?php

namespace App\Services\Receipts;

use App\Models\LineItem;
use App\Models\Receipt;
use App\Models\Vendor;

class LineItemsCreator
{
    public static function create(Receipt $receipt, array $items, array $vendors = []): void
    {
        $vendorIdCache = [];
        $vendorSet = self::normalizeVendorNames($vendors);

        foreach ($items as $item) {
            $itemName = $item['name'] ?? $item['description'] ?? '';
            $vendorId = self::resolveVendorForItem($item, $itemName, $vendorSet, $vendorIdCache);

            LineItem::create([
                'receipt_id' => $receipt->id,
                'vendor_id' => $vendorId,
                'text' => $itemName !== '' ? $itemName : 'Unknown Item',
                'sku' => $item['sku'] ?? null,
                'qty' => $item['quantity'] ?? 1,
                'price' => $item['unit_price'] ?? $item['price'] ?? 0,
                'total' => $item['total_price'] ?? $item['total'] ?? (($item['unit_price'] ?? $item['price'] ?? 0) * ($item['quantity'] ?? 1)),
            ]);
        }
    }

    private static function normalizeVendorNames(array $vendors): array
    {
        return array_map(
            static fn ($v) => mb_strtolower(trim((string) $v)),
            $vendors
        );
    }

    private static function resolveVendorForItem(
        array $item,
        string $itemName,
        array $vendorSet,
        array &$vendorIdCache
    ): ?int {
        $explicitVendor = $item['vendor'] ?? $item['brand'] ?? null;

        if (! empty($explicitVendor)) {
            return self::resolveVendorId($explicitVendor, $vendorIdCache);
        }

        if ($itemName === '' || empty($vendorSet)) {
            return null;
        }

        $match = null;
        foreach ($vendorSet as $v) {
            if ($v !== '' && mb_strpos(mb_strtolower($itemName), $v) !== false) {
                $match = $v;
                break;
            }
        }

        return $match ? self::resolveVendorId($match, $vendorIdCache) : null;
    }

    private static function resolveVendorId(?string $name, array &$vendorIdCache): ?int
    {
        if (! $name) {
            return null;
        }

        $key = mb_strtolower(trim($name));
        if (isset($vendorIdCache[$key])) {
            return $vendorIdCache[$key];
        }

        $vendor = Vendor::firstOrCreate(['name' => trim($name)]);
        $vendorIdCache[$key] = $vendor->id;

        return $vendor->id;
    }
}
