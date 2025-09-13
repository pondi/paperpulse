<?php

namespace App\Services\Receipts;

use App\Models\LineItem;
use App\Models\Receipt;

class LineItemsCreator
{
    public static function create(Receipt $receipt, array $items, array $vendors = []): void
    {
        $vendorIdCache = [];
        $resolveVendorId = function (?string $name) use (&$vendorIdCache) {
            if (!$name) { return null; }
            $key = mb_strtolower(trim($name));
            if (isset($vendorIdCache[$key])) {
                return $vendorIdCache[$key];
            }
            $vendor = \App\Models\Vendor::firstOrCreate(['name' => trim($name)]);
            $vendorIdCache[$key] = $vendor->id;
            return $vendor->id;
        };

        $vendorSet = array_map(fn($v) => mb_strtolower(trim($v)), $vendors);

        foreach ($items as $item) {
            $itemName = $item['name'] ?? $item['description'] ?? '';
            $explicitVendor = $item['vendor'] ?? $item['brand'] ?? null;

            $vendorId = null;
            if (!empty($explicitVendor)) {
                $vendorId = $resolveVendorId($explicitVendor);
            } elseif (!empty($itemName) && !empty($vendorSet)) {
                $match = null;
                foreach ($vendorSet as $v) {
                    if ($v !== '' && mb_strpos(mb_strtolower($itemName), $v) !== false) { $match = $v; break; }
                }
                if ($match) { $vendorId = $resolveVendorId($match); }
            }

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
}

