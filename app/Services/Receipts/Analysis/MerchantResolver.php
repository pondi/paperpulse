<?php

namespace App\Services\Receipts\Analysis;

use App\Contracts\Services\ReceiptEnricherContract;
use App\Contracts\Services\ReceiptParserContract;
use App\Models\Merchant;

class MerchantResolver
{
    public static function resolve(array $data, ReceiptParserContract $parser, ReceiptEnricherContract $enricher, ?int $userId = null): ?Merchant
    {
        $merchantData = $parser->extractMerchantData($data);

        return $enricher->findOrCreateMerchant($merchantData, $userId);
    }
}
